<?php

namespace App\Helpers;

use ccxt\ArgumentsRequired;
use ccxt\BadSymbol;
use ccxt\bitget as BaseBitget;

class CcxtBitget extends BaseBitget
{
    private static function mergeTrade($carry, $item)
    {
        if (is_null($carry)) {
            $item["id"] = $item["order"];
            unset($item["info"]);
            unset($item["fees"]);
            return $item;
        }
        $carry["amount"] += $item["amount"];
        $carry["cost"] += $item["cost"];
        $carry["fee"]["cost"] += $item["fee"]["cost"];
        $carry["price"] = $carry["cost"] / $carry["amount"];
        return $carry;
    }

    private function mergeTrades(array $trades): array
    {
        $arr = array();
        foreach ($trades as $item) {
            $arr[$item['order']][] = $item;
        }
        foreach ($arr as $orderid => $item) {
            $arr[$orderid] = array_reduce($item, "self::mergeTrade");
        }
        return $arr;
    }

    public function fixTransactions(array $transactions): array
    {
        foreach ($transactions as &$t) {
            $t["type"] = $t["type"] == "withdraw" ? "withdrawal" : $t['type'];
            $fee = abs(floatval($t["info"]["fee"]));
            $t["amount"] -= $fee;
            $t["fee"] = array(
                "currency" => $t["currency"],
                "cost" => $fee,
            );
        }
        return $transactions;
    }

    /**
     * @throws ArgumentsRequired
     * @throws BadSymbol
     */
    function fetch_my_trades(?string $symbol = null, ?int $since = null, ?int $limit = null, $params = array()): array
    {
        $trades = parent::fetch_my_trades($symbol, $since, $limit, $params);
        return $this->mergeTrades($trades);
    }

    /**
     * @throws ArgumentsRequired
     */
    function fetch_withdrawals(?string $code = null, ?int $since = null, ?int $limit = null, $params = array()): array
    {
        $withdrawals = parent::fetch_withdrawals($code, $since, $limit, $params);
        return $this->fixTransactions($withdrawals);
    }
}
