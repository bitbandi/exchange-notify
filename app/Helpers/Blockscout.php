<?php

namespace App\Helpers;

use \ccxt\Exchange;
use \ccxt\ArgumentsRequired;
use \ccxt\BadSymbol;
use \ccxt\Precise;

// not a real ccxt exchange, only the erc-20 token transfers of a wallet address from the blockscout pro api
// (passed as symbol "network:address", optionally "network:address:tokencontract")
class Blockscout extends Exchange
{
    protected $transfers = array();
    protected $transfersFetchedAt = array();

    public function describe(): mixed {
        return $this->deep_extend(parent::describe(), array(
            'id' => 'blockscout',
            'name' => 'Blockscout',
            'has' => array(
                'fetchMyTrades' => false,
                'fetchBalance' => false,
                'fetchDeposits' => true,
                'fetchWithdrawals' => true,
            ),
            'urls' => array(
                'api' => array(
                    'rest' => 'https://api.blockscout.com',
                ),
            ),
            'api' => array(
                'rest' => array(
                    'get' => array(
                        '{chain_id}/api/v2/addresses/{address}/token-transfers' => 1,
                    ),
                ),
            ),
            'options' => array(
                'defaultNetwork' => 'ethereum',
                // milliseconds the transfers of an address are reused for deposits and withdrawals
                'transfersTtl' => 60000,
                // network name => [chain id, display name]
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

    public function sign($path, mixed $api = 'rest', $method = 'GET', $params = array(), mixed $headers = null, mixed $body = null) {
        $url = $this->urls['api'][$api] . '/' . $this->implode_params($path, $params);
        $query = $this->omit($params, $this->extract_params($path));
        $query['apikey'] = $this->apiKey;
        $url .= '?' . $this->urlencode($query);
        return array( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
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
        // deposits and withdrawals of the same address come from one request (first page, newest first)
        $key = $chainId . ':' . $address . ':' . $token;
        $now = $this->milliseconds();
        if (!array_key_exists($key, $this->transfers) || $now - $this->transfersFetchedAt[$key] >= $this->options['transfersTtl']) {
            $request = array(
                'chain_id' => $chainId,
                'address' => $address,
                'type' => 'ERC-20',
            );
            if ($token !== null) {
                $request['token'] = $token;
            }
            $response = $this->request('{chain_id}/api/v2/addresses/{address}/token-transfers', 'rest', 'GET', $request);
            $this->transfers[$key] = $this->safe_list($response, 'items', array());
            $this->transfersFetchedAt[$key] = $now;
        }
        // a transaction may move several transfers of the same token in the same direction: sum them
        $result = array();
        foreach ($this->transfers[$key] as $transfer) {
            $from = strtolower($transfer['from']['hash']);
            $to = strtolower($transfer['to']['hash']);
            $incoming = ($to === $address);
            if (($type === 'deposit') !== $incoming || ($from === $address && $incoming)) {
                // self transfers are skipped
                continue;
            }
            // zero value transfers are address poisoning spam
            if ($transfer['total']['value'] === '0') {
                continue;
            }
            // tokens or addresses flagged by blockscout
            if ($this->safe_string($transfer['token'], 'reputation') !== 'ok' || $this->safe_bool($transfer['from'], 'is_scam', false) || $this->safe_bool($transfer['to'], 'is_scam', false)) {
                continue;
            }
            $id = $transfer['transaction_hash'] . ':' . strtolower($transfer['token']['address_hash']) . ':' . ($incoming ? 'in' : 'out');
            if (array_key_exists($id, $result)) {
                $result[$id]['info']['total']['value'] = Precise::string_add($result[$id]['info']['total']['value'], $transfer['total']['value']);
                $result[$id]['info']['log_index'] = min($result[$id]['info']['log_index'], $transfer['log_index']);
            } else {
                $result[$id] = $this->parse_token_transfer($transfer, $type, $network, $chainName);
            }
        }
        $result = $this->merge_mirrored_transfers($result);
        foreach ($result as $id => $transaction) {
            $amount = $this->format_amount($transaction['info']['total']['value'], (int) $transaction['info']['total']['decimals']);
            $result[$id]['amount'] = $this->parse_number($amount);
        }
        $result = $this->sort_by(array_values($result), 'timestamp');
        return $this->filter_by_since_limit($result, $since, $limit);
    }

    // some tokens emit the same transfer on several contracts (e.g. monerium EURe v1 and v2):
    // keep one per transaction, direction, symbol and value, the one with the lowest log index
    protected function merge_mirrored_transfers(array $transactions): array {
        $kept = array();
        foreach ($transactions as $id => $transaction) {
            $info = $transaction['info'];
            $key = $info['transaction_hash'] . ':' . $transaction['type'] . ':' . strtoupper((string) $info['token']['symbol']) . ':' . $info['total']['value'];
            if (!array_key_exists($key, $kept) || $info['log_index'] < $transactions[$kept[$key]]['info']['log_index']) {
                $kept[$key] = $id;
            }
        }
        return array_intersect_key($transactions, array_flip($kept));
    }

    protected function parse_token_transfer(array $transfer, string $type, string $network, string $chainName): array {
        $info = $transfer;
        $info['network'] = $network;
        // "USDC@Ethereum", the column holds 32 chars
        $currency = substr(($transfer['token']['symbol'] ?? $transfer['token']['address_hash']) . '@' . $chainName, 0, 32);
        $deposit = ($type === 'deposit');
        $timestamp = $this->parse8601($transfer['timestamp']);
        return array(
            'info' => $info,
            'id' => $transfer['transaction_hash'],
            'txid' => $transfer['transaction_hash'],
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'network' => $network,
            // the other party of the transfer
            'address' => $deposit ? $transfer['from']['hash'] : $transfer['to']['hash'],
            'addressFrom' => $transfer['from']['hash'],
            'addressTo' => $transfer['to']['hash'],
            'type' => $type,
            'amount' => null,
            'currency' => $currency,
            // token transfers are only listed for successful transactions
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
