<?php

namespace App\Helpers;

use \ccxt\kraken as BaseKraken;

class CcxtKraken extends BaseKraken
{
    public function fetch_my_trades(?string $symbol = null, ?int $since = null, ?int $limit = null, $params = array ())
    {
        $orders =  parent::fetch_closed_orders($symbol, $since, $limit, $params);
        foreach($orders as &$o) {
            // swap currency symbols
            if ($o["symbol"] == "BTC/EUR") {
                $o["symbol"] = "EUR/BTC";
            }
        }
        return $orders;
    }
}
