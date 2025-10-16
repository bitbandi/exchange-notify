<?php

namespace App\Service;

use App\Models\Balance;
use App\Models\Trade;
use App\Models\Transaction;
use App\Notifications\TradeComplete;
use App\Notifications\TransactionComplete;
use ccxt\Exchange;
use Henzeb\Console\Facades\Console;
use Illuminate\Support\Facades\App;

class ExchangeService
{
    private $notify = true;

    /**
     * @param bool $notify
     */
    public function setNotify(bool $notify)
    {
        $this->notify = $notify;
    }

    /**
     * @param $exchangeConfig
     */
    public function Query(ExchangeConfig $exchangeConfig)
    {
        if (!in_array($exchangeConfig->getName(), Exchange::$exchanges)) {
            return;
        }
        Console::info("Query ".$exchangeConfig->getName(). " exchange");
        $exchange_name = "\\exchangenotify\\ccxt\\" . $exchangeConfig->getName();
        $exchange = new $exchange_name(array_merge(array(
            'verbose' => App::hasDebugModeEnabled(),
            'apiKey' => $exchangeConfig->getApiKey(),
            'secret' => $exchangeConfig->getApiSecret(),
            'password' => $exchangeConfig->getApiPassword(),
        ), $exchangeConfig->getCustomLogin()));
        if ($exchange->has['fetchBalance'] && $exchangeConfig->AllowQueryBalance()) {
            $balances = $exchange->fetchBalance();
            $this->updateBalances($exchangeConfig, $balances);
        }
        if ($exchange->has['fetchMyTrades'] && $exchangeConfig->AllowQueryTrades()) {
            foreach ($exchangeConfig->getTradeSymbols() as $tradesymbol) {
                $trades = $exchange->fetchMyTrades($tradesymbol);
                $this->updateTrades($exchangeConfig, $trades);
            }
        }
        if ($exchange->has['fetchDeposits'] && $exchangeConfig->AllowQueryDeposits()) {
            foreach ($exchangeConfig->getDepositSymbols() as $depositsymbol) {
                $deposits = $exchange->fetchDeposits($depositsymbol);
                $this->updateTransactions($exchangeConfig, $deposits);
            }
        }
        if ($exchange->has['fetchWithdrawals'] && $exchangeConfig->AllowQueryWithdrawals()) {
            foreach ($exchangeConfig->getWithdrawalSymbols() as $withdrawalsymbol) {
                $withdrawals = $exchange->fetchWithdrawals($withdrawalsymbol);
                $this->updateTransactions($exchangeConfig, $withdrawals);
            }
        }
    }

    protected function updateTrades(ExchangeConfig $exchangeConfig, $trades)
    {
        $notify_via = $exchangeConfig->getNotifyVia();
        foreach ($trades as $trade) {
            //          var_dump($trade);
            list($primary_currency, $secondary_currency) = explode("/", $trade["symbol"], 2);
            $tradeModel = Trade::updateOrCreate(
                [
                    'exchange' => strtoupper($exchangeConfig->getName()),
                    'account' => $exchangeConfig->getAccount(),
                    'tradeid' => $trade["id"],
                ],
                [
                    'datetime' => intval($trade["timestamp"] / 1000),
                    'primary_currency' => $primary_currency,
                    'secondary_currency' => $secondary_currency,
                    'type' => strtoupper($trade["side"]),
                    'tradeprice' => round($trade["price"], 8),
                    'quantity' => $trade["amount"],
                    'total' => $trade["cost"],
                    'fee' => isset($trade["fee"]) ? $trade["fee"]['cost'] : 0,
                ]);
//            dump($tradeModel);
            if ($this->notify) {
                if ($tradeModel->wasRecentlyCreated || $tradeModel->wasChanged()) {
                    $tradeModel->notify(new TradeComplete($notify_via));
                }
            }
        }
    }

    protected function updateTransactions(ExchangeConfig $exchangeConfig, $transactions)
    {
        $notify_via = $exchangeConfig->getNotifyVia();
        foreach ($transactions as $transaction) {
//            var_dump($transaction);
            if ($transaction["status"] == "ok") {
                $transactionModel = Transaction::updateOrCreate(
                    [
                        'exchange' => strtoupper($exchangeConfig->getName()),
                        'account' => $exchangeConfig->getAccount(),
                        'trxid' => $transaction["txid"],
                    ],
                    [
                        'currency' => $transaction["currency"],
                        'datetime' => intval($transaction["timestamp"] / 1000),
                        'type' => strtoupper($transaction["type"]),
                        'address' => $transaction["address"],
                        'amount' => round($transaction["amount"], 8),
                        'fee' => isset($transaction["fee"]) ? $transaction["fee"]['cost'] : 0,
                    ]);
//                dump($transactionModel);
                if ($this->notify) {
                    if ($transactionModel->wasRecentlyCreated || $transactionModel->wasChanged()) {
                        $transactionModel->notify(new TransactionComplete($notify_via));
                    }
                }
            }
        }
    }

    protected function updateBalances(ExchangeConfig $exchangeConfig, $balances)
    {
        $balance_list = Balance::where([
            'exchange' => strtoupper($exchangeConfig->getName()),
            'account' => $exchangeConfig->getAccount(),
        ]);
        /*        $torolni = $balance_list->get()->reject(function ($balance) use ($balances) {
                    return array_key_exists($balance->currency, $balances) && $balance->amount > 0;
                });*/
//        dd($torolni);
        $balance_list->get()->each(function ($balance) use ($balances) {
            if (!(array_key_exists($balance->currency, $balances["total"]) && ($balances["total"][$balance->currency] > 0))) {
                $balance->delete();
            }
        });
        foreach ($balances["total"] as $currency => $value) {
            // var_dump($currency, $value);
            if ($value > 0) {
                $balance = Balance::updateOrCreate(
                    [
                        'exchange' => strtoupper($exchangeConfig->getName()),
                        'account' => $exchangeConfig->getAccount(),
                        'currency' => $currency,
                    ],
                    ['amount' => $value]
                );
                $balance->touch();
            }
        }
    }
}
