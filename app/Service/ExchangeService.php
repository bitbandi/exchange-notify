<?php

namespace App\Service;

use App\Models\Balance;
use App\Models\Trade;
use App\Models\Transaction;
use App\Notifications\TradeComplete;
use App\Notifications\TransactionComplete;
use ccxt\Exchange;

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
    public function Query($exchangeConfig)
    {
        if (!in_array($exchangeConfig["exchange"], Exchange::$exchanges)) {
            return;
        }
        $exchange_name = '\\ccxt\\' . $exchangeConfig["exchange"];
        $exchange = new $exchange_name(array(
            'apiKey' => $exchangeConfig["apikey"],
            'secret' => $exchangeConfig["apisecret"],
        ));
        if ($exchange->has['fetchBalance']) {
            $balances = $exchange->fetchBalance();
            $this->updateBalances($exchangeConfig, $balances);
        }
        if ($exchange->has['fetchMyTrades']) {
            $trades = $exchange->fetchMyTrades();
            $this->updateTrades($exchangeConfig, $trades);
        }
        if ($exchange->has['fetchDeposits']) {
            $deposits = $exchange->fetchDeposits();
            $this->updateTransactions($exchangeConfig, $deposits);
        }
        if ($exchange->has['fetchWithdrawals']) {
            $withdrawal = $exchange->fetchWithdrawals();
            $this->updateTransactions($exchangeConfig, $withdrawal);
        }
    }

    protected function updateTrades($exchangeConfig, $trades)
    {
        $notify_via = isset($exchangeConfig['notify']) ? explode(',', $exchangeConfig['notify']) : null;
        foreach ($trades as $trade) {
            //          var_dump($trade);
            list($primary_currency, $secondary_currency) = explode("/", $trade["symbol"], 2);
            $tradeModel = Trade::updateOrCreate(
                [
                    'exchange' => strtoupper($exchangeConfig["exchange"]),
                    'account' => $exchangeConfig["account"],
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

    protected function updateTransactions($exchangeConfig, $transactions)
    {
        $notify_via = isset($exchangeConfig['notify']) ? explode(',', $exchangeConfig['notify']) : null;
        foreach ($transactions as $transaction) {
//            var_dump($transaction);
            if ($transaction["status"] == "ok") {
                $transactionModel = Transaction::updateOrCreate(
                    [
                        'exchange' => strtoupper($exchangeConfig["exchange"]),
                        'account' => $exchangeConfig["account"],
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

    protected function updateBalances($exchangeConfig, $balances)
    {
        $balance_list = Balance::where([
            'exchange' => strtoupper($exchangeConfig["exchange"]),
            'account' => $exchangeConfig["account"],
        ]);
        /*        $torolni = $balance_list->get()->reject(function ($balance) use ($balances) {
                    return array_key_exists($balance->currency, $balances) && $balance->amount > 0;
                });*/
//        dd($torolni);
        $balance_list->get()->each(function ($balance) use ($balances) {
            if (!(array_key_exists($balance->currency, $balances) && ($balance->amount > 0))) {
                $balance->delete();
            }
        });
        foreach ($balances["total"] as $currency => $value) {
            // var_dump($currency, $value);
            if ($value > 0) {
                Balance::updateOrCreate(
                    [
                        'exchange' => strtoupper($exchangeConfig["exchange"]),
                        'account' => $exchangeConfig["account"],
                        'currency' => $currency,
                    ],
                    ['amount' => $value]
                );
            }
        }
    }
}
