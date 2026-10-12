<?php

namespace App\Helpers;

use \ccxt\Exchange;
use \ccxt\ArgumentsRequired;
use \ccxt\ExchangeError;
use \ccxt\Precise;

// not a real ccxt exchange, only fetchMyTrades for a wallet address (passed as symbol)
class CowSwap extends Exchange
{
    protected $tokens = null;

    public function describe(): mixed {
        return $this->deep_extend(parent::describe(), array(
            'id' => 'cowswap',
            'name' => 'CoW Swap',
            'has' => array(
                'fetchMyTrades' => true,
                'fetchBalance' => false,
                'fetchDeposits' => false,
                'fetchWithdrawals' => false,
            ),
            'urls' => array(
                'api' => 'https://api.cow.fi',
                'tokens' => 'https://files.cow.fi/tokens/CowSwap.json',
            ),
            'options' => array(
                'network' => 'mainnet',
                'chainId' => 1,
                'rpcUrl' => 'https://ethereum-rpc.publicnode.com',
            ),
        ));
    }

    public function fetch_my_trades(?string $symbol = null, ?int $since = null, ?int $limit = null, $params = array ()) {
        if ($symbol === null || !preg_match('/^0x[0-9a-fA-F]{40}$/', $symbol)) {
            throw new ArgumentsRequired($this->id . ' fetchMyTrades() requires a wallet address as symbol argument');
        }
        $url = $this->urls['api'] . '/' . $this->options['network'] . '/api/v1/account/' . strtolower($symbol) . '/orders?' . $this->urlencode(array(
            'offset' => 0,
            'limit' => ($limit !== null) ? $limit : 100,
        ));
        $orders = $this->fetch($url);
        $result = array();
        foreach ($orders as $order) {
            // fulfilled, or partially filled and then cancelled/expired
            if (Precise::string_gt($this->safe_string($order, 'executedSellAmount', '0'), '0')) {
                $result[] = $this->parse_order_trade($order);
            }
        }
        $result = $this->sort_by($result, 'timestamp');
        return $this->filter_by_since_limit($result, $since, $limit);
    }

    protected function parse_order_trade(array $order): array {
        $sellToken = $this->token($order['sellToken']);
        $buyToken = $this->token($order['buyToken']);
        $sold = $this->from_wei($order['executedSellAmount'], $sellToken['decimals']);
        $bought = $this->from_wei($order['executedBuyAmount'], $buyToken['decimals']);
        // sell order: base is the sold token; buy order: base is the bought token
        if ($order['kind'] === 'buy') {
            $side = 'buy';
            $symbol = $buyToken['symbol'] . '/' . $sellToken['symbol'];
            $amount = $bought;
            $cost = $sold;
        } else {
            $side = 'sell';
            $symbol = $sellToken['symbol'] . '/' . $buyToken['symbol'];
            $amount = $sold;
            $cost = $bought;
        }
        $fee = null;
        $feeAmount = $this->safe_string($order, 'executedFee');
        if ($feeAmount !== null) {
            $feeToken = $this->token($this->safe_string($order, 'executedFeeToken', $order['sellToken']));
            $fee = array(
                'cost' => $this->parse_number($this->from_wei($feeAmount, $feeToken['decimals'])),
                'currency' => $feeToken['symbol'],
            );
        }
        $timestamp = $this->parse8601($order['creationDate']);
        return array(
            'info' => $order,
            'id' => $order['uid'],
            'order' => $order['uid'],
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'symbol' => $symbol,
            'type' => $this->safe_string($order, 'class'),
            'side' => $side,
            'takerOrMaker' => null,
            'price' => $this->parse_number(Precise::string_div($cost, $amount)),
            'amount' => $this->parse_number($amount),
            'cost' => $this->parse_number($cost),
            'fee' => $fee,
        );
    }

    protected function from_wei(string $amount, int $decimals): string {
        return Precise::string_div($amount, '1' . str_repeat('0', $decimals), $decimals);
    }

    protected function token(string $address): array {
        $address = strtolower($address);
        if ($this->tokens === null) {
            $this->tokens = array();
            $list = $this->fetch($this->urls['tokens']);
            foreach ($this->safe_list($list, 'tokens', array()) as $t) {
                if ($t['chainId'] == $this->options['chainId']) {
                    $this->tokens[strtolower($t['address'])] = array('symbol' => $t['symbol'], 'decimals' => $t['decimals']);
                }
            }
        }
        if (!isset($this->tokens[$address])) {
            // not in the token list, ask the token contract
            $this->tokens[$address] = array(
                'symbol' => $this->decode_symbol($this->eth_call($address, '0x95d89b41')),
                'decimals' => intval(hexdec(substr($this->eth_call($address, '0x313ce567'), -16))),
            );
        }
        return $this->tokens[$address];
    }

    protected function eth_call(string $to, string $data): string {
        $response = $this->fetch($this->options['rpcUrl'], 'POST', array('Content-Type' => 'application/json'), $this->json(array(
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'eth_call',
            'params' => array(array('to' => $to, 'data' => $data), 'latest'),
        )));
        if (!isset($response['result']) || strlen($response['result']) <= 2) {
            throw new ExchangeError($this->id . ' eth_call ' . $data . ' failed on token ' . $to);
        }
        return $response['result'];
    }

    protected function decode_symbol(string $hex): string {
        $bin = hex2bin(substr($hex, 2));
        if (strlen($bin) > 64) {
            // abi encoded string: offset, length, data
            $len = intval(hexdec(bin2hex(substr($bin, 56, 8))));
            return substr($bin, 64, $len);
        }
        // bytes32 (e.g. MKR)
        return rtrim($bin, "\0");
    }
}
