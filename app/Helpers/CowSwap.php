<?php

namespace App\Helpers;

use \ccxt\Exchange;
use \ccxt\ArgumentsRequired;
use \ccxt\BadSymbol;
use \ccxt\ExchangeError;
use \ccxt\Precise;
use CowApi\Chain;
use CowApi\CowApiClient;
use CowApi\Exception\CowApiException;
use CowApi\Exception\InvalidArgumentException;
use CowApi\Http\CurlHttpClient;
use CowApi\Model\Order;
use CowApi\Model\OrderKind;
use CowApi\Token\RpcTokenResolver;
use CowApi\Token\Token;
use CowApi\Util\Amount;
use Illuminate\Support\Facades\Log;

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
        // the api returns at most MAX_PAGE_SIZE orders per request, page through larger limits
        $total = ($limit !== null) ? $limit : 100;
        $orders = array();
        $pageSize = min(CowApiClient::MAX_PAGE_SIZE, $total);
        for ($offset = 0; $offset < $total; $offset += $pageSize) {
            $page = $client->getOrdersByOwner($m[2], $pageSize, $offset);
            $orders = array_merge($orders, $page);
            if (count($page) < $pageSize) {
                break; // no more orders
            }
        }
        // the last page may go past the limit, don't parse (and bridge-lookup) the extra orders
        $orders = array_slice($orders, 0, $total);
        $result = array();
        foreach ($orders as $order) {
            // fulfilled, or partially filled and then cancelled/expired
            if (Precise::string_gt($order->executedSellAmount, '0')) {
                // a single bad order (unknown token, bridge lookup, rate limit) must not drop the others
                try {
                    $result[] = $this->parse_order_trade($order, $client);
                } catch (ExchangeError | CowApiException $e) {
                    Log::warning($this->id . ' skipped order: ' . $e->getMessage(), array('order' => $order->uid, 'network' => $chain->value, 'exception' => $e));
                }
            }
        }
        $result = $this->sort_by($result, 'timestamp');
        return $this->filter_by_since_limit($result, $since, $limit);
    }

    protected function parse_order_trade(Order $order, CowApiClient $client): array {
        $raw = $order->raw();
        $sellToken = $this->token($client, $order->sellToken);
        $sellSymbol = $sellToken->symbol;
        $sold = Amount::format($order->executedSellAmount, $sellToken->decimals);
        if ($order->isCrossChain()) {
            // swap & bridge: the order buys an intermediate token that is bridged, the real output is the
            // bridge leg on the destination chain; the input stays the order's own sell amount
            $bridge = $client->getBridgeStatus($order);
            $output = $bridge->output;
            $outputAmount = $output->amount ?? $output->expectedAmount;
            if ($output->symbol === null || $output->decimals === null || $outputAmount === null) {
                throw new ExchangeError($this->id . ' unknown bridge output of order ' . $order->uid . ' (' . $bridge->status->value . ($bridge->error !== null ? ': ' . $bridge->error : '') . ')');
            }
            // tag both sides with their chain, e.g. "USDC@Ethereum/EURe@Gnosis"
            $sellSymbol .= '@' . $client->chain()->displayName();
            $buySymbol = $output->symbol . '@' . ($output->chainName() ?? (string) $output->chainId);
            $bought = Amount::format($outputAmount, $output->decimals);
            $raw['bridge'] = $bridge->toArray();
        } else {
            $buyToken = $this->token($client, $order->buyToken);
            $buySymbol = $buyToken->symbol;
            $bought = Amount::format($order->executedBuyAmount, $buyToken->decimals);
        }
        // sell order: base is the sold token; buy order: base is the bought token
        if ($order->kind === OrderKind::Buy) {
            $side = 'buy';
            $symbol = $buySymbol . '/' . $sellSymbol;
            $amount = $bought;
            $cost = $sold;
        } else {
            $side = 'sell';
            $symbol = $sellSymbol . '/' . $buySymbol;
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
