<?php

namespace App\Helpers;

use \ccxt\kucoin as BaseKucoin;

class CcxtKucoin extends BaseKucoin
{
    public function fetch_balance($params = array()) : array
    {
        $params['type'] = "main";
        return parent::fetch_balance($params);
    }
}
