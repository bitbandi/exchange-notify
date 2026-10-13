<?php

namespace App\Helpers;

use \ccxt\Exchange;
use \ccxt\ArgumentsRequired;
use \ccxt\BadSymbol;
use \ccxt\Precise;
use Amashukov\Etherscan\AddressHistoryQuery;
use Amashukov\Etherscan\EtherscanClient;
use Amashukov\Etherscan\EtherscanClientInterface;
use Amashukov\Etherscan\Sort;
use Amashukov\Etherscan\Vo\TokenTransfer;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

// not a real ccxt exchange, only the erc-20 token transfers of a wallet address
// (passed as symbol "network:address", optionally "network:address:tokencontract")
class Etherscan extends Exchange
{
    protected $clients = array();
    protected $transfers = array();

    public function describe(): mixed {
        return $this->deep_extend(parent::describe(), array(
            'id' => 'etherscan',
            'name' => 'Etherscan',
            'has' => array(
                'fetchMyTrades' => false,
                'fetchBalance' => false,
                'fetchDeposits' => true,
                'fetchWithdrawals' => true,
            ),
            'options' => array(
                'defaultNetwork' => 'ethereum',
                'pageSize' => 1000,
                // network name => [etherscan v2 chain id, display name]
                'networks' => array(
                    'ethereum' => array(1, 'Ethereum'),
                    'mainnet' => array(1, 'Ethereum'),
                    'gnosis' => array(100, 'Gnosis'),
                    'arbitrum' => array(42161, 'Arbitrum'),
                    'base' => array(8453, 'Base'),
                    'optimism' => array(10, 'Optimism'),
                    'polygon' => array(137, 'Polygon'),
                    'bsc' => array(56, 'BNB'),
                    'bnb' => array(56, 'BNB'),
                    'avalanche' => array(43114, 'Avalanche'),
                    'linea' => array(59144, 'Linea'),
                    'sepolia' => array(11155111, 'Sepolia'),
                ),
            ),
        ));
    }

    protected function client(int $chainId): EtherscanClientInterface {
        if (!array_key_exists($chainId, $this->clients)) {
            $this->clients[$chainId] = new EtherscanClient(new Client(), new HttpFactory(), (string) $this->apiKey, $chainId);
        }
        return $this->clients[$chainId];
    }

    public function fetch_deposits(?string $code = null, ?int $since = null, ?int $limit = null, $params = array()) {
        return $this->fetch_token_transfers('deposit', $code, $since, $limit);
    }

    public function fetch_withdrawals(?string $code = null, ?int $since = null, ?int $limit = null, $params = array()) {
        return $this->fetch_token_transfers('withdrawal', $code, $since, $limit);
    }

    protected function fetch_token_transfers(string $type, ?string $symbol, ?int $since, ?int $limit): array {
        // no address configured for this direction
        if ($symbol === null) {
            return array();
        }
        if (!preg_match('/^(?:([a-z0-9_]+):)?(0x[0-9a-fA-F]{40})(?::(0x[0-9a-fA-F]{40}))?$/', $symbol, $m)) {
            throw new ArgumentsRequired($this->id . ' fetch' . ucfirst($type) . 's() requires a wallet address (network:address, optionally network:address:token) as symbol argument');
        }
        $network = ($m[1] !== '') ? $m[1] : $this->options['defaultNetwork'];
        if (!array_key_exists($network, $this->options['networks'])) {
            throw new BadSymbol($this->id . ' unknown network ' . $network . ', supported: ' . implode(', ', array_keys($this->options['networks'])));
        }
        list($chainId, $chainName) = $this->options['networks'][$network];
        $address = strtolower($m[2]);
        // all tokens unless one contract is given
        $token = isset($m[3]) ? strtolower($m[3]) : null;
        $pageSize = ($limit !== null) ? $limit : $this->options['pageSize'];
        // deposits and withdrawals of the same address come from one request
        $key = $chainId . ':' . $address . ':' . $token . ':' . $pageSize;
        if (!array_key_exists($key, $this->transfers)) {
            // newest first, so the page holds the latest transfers
            $query = new AddressHistoryQuery(pageSize: $pageSize, sort: Sort::Descending);
            $this->transfers[$key] = $this->client($chainId)->tokenTransfers($address, $token, $query);
        }
        // a transaction may move several transfers of the same token in the same direction: sum them
        $result = array();
        foreach ($this->transfers[$key] as $transfer) {
            $incoming = ($transfer->to === $address);
            if (($type === 'deposit') !== $incoming || ($transfer->from === $address && $incoming)) {
                // self transfers are skipped
                continue;
            }
            // zero value transfers are address poisoning spam
            if ($transfer->value === '0') {
                continue;
            }
            // grouping key only, id/txid of the transaction is the plain hash
            $index = $transfer->hash . ':' . $transfer->contractAddress . ':' . ($incoming ? 'in' : 'out');
            if (array_key_exists($index, $result)) {
                $result[$index]['info']['value'] = Precise::string_add($result[$index]['info']['value'], $transfer->value);
            } else {
                $result[$index] = $this->parse_token_transfer($transfer, $type, $network, $chainName);
            }
        }
        foreach ($result as $index => $transaction) {
            $amount = $this->format_amount($transaction['info']['value'], $transaction['info']['tokenDecimal']);
            $result[$index]['amount'] = $this->parse_number($amount);
        }
        $result = $this->sort_by(array_values($result), 'timestamp');
        return $this->filter_by_since_limit($result, $since, $limit);
    }

    protected function parse_token_transfer(TokenTransfer $transfer, string $type, string $network, string $chainName): array {
        $info = get_object_vars($transfer);
        $info['network'] = $network;
        // "USDC@Ethereum", the column holds 32 chars
        $currency = substr(($transfer->tokenSymbol ?? $transfer->contractAddress) . '@' . $chainName, 0, 32);
        $deposit = ($type === 'deposit');
        $timestamp = $transfer->timestamp * 1000;
        return array(
            'info' => $info,
            'id' => $transfer->hash,
            'txid' => $transfer->hash,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'network' => $network,
            // the other party of the transfer
            'address' => $deposit ? $transfer->from : $transfer->to,
            'addressFrom' => $transfer->from,
            'addressTo' => $transfer->to,
            'type' => $type,
            'amount' => null,
            'currency' => $currency,
            // tokentx only lists successful transactions
            'status' => 'ok',
            'updated' => null,
            'fee' => null,
        );
    }

    // integer token amount to decimal string
    protected function format_amount(string $value, int $decimals): string {
        if ($decimals === 0) {
            return $value;
        }
        $value = str_pad($value, $decimals + 1, '0', STR_PAD_LEFT);
        $fraction = rtrim(substr($value, -$decimals), '0');
        $whole = substr($value, 0, -$decimals);
        return ($fraction === '') ? $whole : $whole . '.' . $fraction;
    }
}
