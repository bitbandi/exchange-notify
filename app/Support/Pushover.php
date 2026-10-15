<?php

namespace App\Support;

class Pushover
{
    public static function getTokens(): array
    {
        $result = [];
        foreach (\ccxt\Exchange::$exchanges as $exchange) {
            $exchange = strtoupper($exchange);
            $app_token = env('PUSHOVER_APP_' . $exchange);
            if (!empty($app_token)) $result[$exchange] = $app_token;
        }
        return $result;
    }
}
