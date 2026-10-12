<?php

namespace App\Helpers;

use \ccxt\Exchange;
use \ccxt\ArgumentsRequired;
use \ccxt\BadSymbol;
use \ccxt\ExchangeError;
use \ccxt\Precise;
use CowApi\Chain;
use CowApi\CowApiClient;
use CowApi\Exception\InvalidArgumentException;
use CowApi\Http\CurlHttpClient;
use CowApi\Model\Order;
use CowApi\Model\OrderKind;
use CowApi\Token\RpcTokenResolver;
use CowApi\Token\Token;
use CowApi\Util\Amount;

// not a real ccxt exchange, only fetchMyTrades for a wallet address (passed as symbol, optionally "network:address")
class CowSwap extends Exchange
{
    protected $client = null;

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
            'options' => array(
                'defaultNetwork' => 'mainnet',
                // chain id => json-rpc url, overrides the cow-api defaults
                'rpcUrls' => array(),
            ),
        ));
    }

    protected function client(Chain $chain): CowApiClient {
        if ($this->client === null) {
            $http = new CurlHttpClient();
            $this->client = new CowApiClient($chain, $http, null, new RpcTokenResolver($http, $this->options['rpcUrls']));
        }
        return $this->client->withChain($chain);
    }

    public function fetch_my_trades(?string $symbol = null, ?int $since = null, ?int $limit = null, $params = array ()) {
        if ($symbol === null || !preg_match('/^(?:([a-z0-9_]+):)?(0x[0-9a-fA-F]{40})$/', $symbol, $m)) {
            throw new ArgumentsRequired($this->id . ' fetchMyTrades() requires a wallet address (optionally network:address) as symbol argument');
        }
        $network = ($m[1] !== '') ? $m[1] : $this->options['defaultNetwork'];
        try {
            $chain = Chain::fromString($network);
        } catch (InvalidArgumentException $e) {
            throw new BadSymbol($this->id . ' unknown network ' . $network . ', supported: ' . implode(', ', array_column(Chain::cases(), 'value')));
        }
        $client = $this->client($chain);
        $orders = $client->getOrdersByOwner($m[2], ($limit !== null) ? $limit : 100);
        $result = array();
        foreach ($orders as $order) {
            // fulfilled, or partially filled and then cancelled/expired
            if (Precise::string_gt($order->executedSellAmount, '0')) {
                $result[] = $this->parse_order_trade($order, $client);
            }
        }
        $result = $this->sort_by($result, 'timestamp');
        return $this->filter_by_since_limit($result, $since, $limit);
    }

    protected function parse_order_trade(Order $order, CowApiClient $client): array {
        $raw = $order->raw();
        $sellToken = $this->token($client, $order->sellToken);
        $buyToken = $this->token($client, $order->buyToken);
        $sold = Amount::format($order->executedSellAmount, $sellToken->decimals);
        $bought = Amount::format($order->executedBuyAmount, $buyToken->decimals);
        // sell order: base is the sold token; buy order: base is the bought token
        if ($order->kind === OrderKind::Buy) {
            $side = 'buy';
            $symbol = $buyToken->symbol . '/' . $sellToken->symbol;
            $amount = $bought;
            $cost = $sold;
        } else {
            $side = 'sell';
            $symbol = $sellToken->symbol . '/' . $buyToken->symbol;
            $amount = $sold;
            $cost = $bought;
        }
        $fee = null;
        $feeAmount = $this->safe_string($raw, 'executedFee');
        if ($feeAmount !== null) {
            $feeToken = $this->token($client, $this->safe_string($raw, 'executedFeeToken', $order->sellToken));
            $fee = array(
                'cost' => $this->parse_number(Amount::format($feeAmount, $feeToken->decimals)),
                'currency' => $feeToken->symbol,
            );
        }
        $raw['network'] = $client->chain()->value;
        $timestamp = $order->creationDate->getTimestamp() * 1000 + intdiv((int) $order->creationDate->format('u'), 1000);
        return array(
            'info' => $raw,
            'id' => $order->uid,
            'order' => $order->uid,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'symbol' => $symbol,
            'type' => $order->orderClass,
            'side' => $side,
            'takerOrMaker' => null,
            'price' => $this->parse_number(Precise::string_div($cost, $amount)),
            'amount' => $this->parse_number($amount),
            'cost' => $this->parse_number($cost),
            'fee' => $fee,
        );
    }

    protected function token(CowApiClient $client, string $address): Token {
        $token = $client->token($address);
        if ($token === null) {
            throw new ExchangeError($this->id . ' unknown token ' . $address . ' on ' . $client->chain()->value);
        }
        return $token;
    }
}
