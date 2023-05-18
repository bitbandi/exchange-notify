<?php

namespace App\Service;

class ExchangeProcess
{

    public static function mergeBitgetTrade($carry, $item)
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

    public static function mergeBitgetTrades(array $trades): array
    {
        $arr = array();
        foreach ($trades as $item) {
            $arr[$item['order']][] = $item;
        }
        foreach ($arr as $orderid => $item) {
            $arr[$orderid] = array_reduce($item, "self::mergeBitgetTrade");
        }
        return $arr;
    }
}
