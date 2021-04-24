<?php

namespace App\Service;

use App\Models\Balance;
use App\Models\Trade;
use App\Models\Transaction;
use App\Notifications\BaseNotification;
use App\Notifications\TradeComplete;
use App\Notifications\TransactionComplete;
use Illuminate\Support\Facades\Notification;

class ExchangeService
{
    public function Query($exchangeConfig)
    {
        $exchange_name = '\\ccxt\\' . $exchangeConfig["exchange"];
        $exchange = new $exchange_name(array(
            'apiKey' => $exchangeConfig["apikey"],
            'secret' => $exchangeConfig["apisecret"],
        ));
        $balances = $exchange->fetch_balance();
//        var_dump($balances["total"]);
        $this->updateBalances($exchangeConfig, $balances);
        $trades = $exchange->fetchMyTrades();
//        var_dump($trades);
        $this->updateTrades($exchangeConfig, $trades);
        $deposits = $exchange->fetchDeposits();
//        var_dump($deposits);
        $this->updateTransactions($exchangeConfig, $deposits);
        $withdrawal = $exchange->fetchWithdrawals();
//        var_dump($withdrawal);
        $this->updateTransactions($exchangeConfig, $withdrawal);
    }

    protected function updateTrades($exchangeConfig, $trades)
    {
        $notify_via = isset($exchangeConfig['notify']) ? explode(',', $exchangeConfig['notify']) : null;
        foreach ($trades as $trade) {
            //          var_dump($trade);
            list($primary_currency, $secondary_currency) = explode("/", $trade["symbol"], 2);
            $tradeModel = Trade::updateOrCreate(
                [
                    'exchange' => $exchangeConfig["exchange"],
                    'account' => $exchangeConfig["account"],
                    'tradeid' => $trade["id"],
                ],
                [
                    'datetime' => $trade["datetime"],
                    'primary_currency' => $primary_currency,
                    'secondary_currency' => $secondary_currency,
                    'type' => strtoupper($trade["side"]),
                    'tradeprice' => $trade["price"],
                    'quantity' => $trade["amount"],
                    'total' => $trade["cost"],
                    'fee' => isset($trade["fee"]) ? $trade["fee"]['cost'] : 0,
                ]);
//            dump($tradeModel->wasChanged()); // wasRecentlyCreated
            if ($tradeModel->wasChanged()) {
                $tradeModel->notify(new TradeComplete($notify_via));
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
                        'exchange' => $exchangeConfig["exchange"],
                        'account' => $exchangeConfig["account"],
                        'trxid' => $transaction["txid"],
                    ],
                    [
                        'currency' => $transaction["currency"],
                        'datetime' => $transaction["datetime"],
                        'type' => strtoupper($transaction["type"]),
                        'address' => $transaction["address"],
                        'amount' => $transaction["amount"],
                        'fee' => isset($transaction["fee"]) ? $transaction["fee"]['cost'] : 0,
                    ]);
//                dump($transactionModel->wasChanged()); // wasRecentlyCreated
                if ($transactionModel->wasChanged()) {
                    $transactionModel->notify(new TransactionComplete($notify_via));
                }
            }
        }
    }

    protected function updateBalances($exchangeConfig, $balances)
    {
        $balance_list = Balance::where([
            'exchange' => $exchangeConfig["exchange"],
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
                        'exchange' => $exchangeConfig["exchange"],
                        'account' => $exchangeConfig["account"],
                        'currency' => $currency,
                    ],
                    ['amount' => $value]
                );
            }
        }
    }
}
