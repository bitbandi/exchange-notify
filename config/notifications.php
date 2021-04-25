<?php

function getPushoverTokens(): array
{
    $result = [];
    foreach (\ccxt\Exchange::$exchanges as $exchange) {
        $app_token = env('PUSHOVER_APP_' . strtoupper($exchange));
        if (!empty($app_token)) $result[$exchange] = $app_token;
    }
    return $result;
}

return [
    'notify_at' => env('NOTIFY_DAILY_AT', '09:00'),

    'slack_url' => env('SLACK_URL'),
    'pushover_key' => env('PUSHOVER_KEY'),
    'pushover_token' => env('PUSHOVER_APP'),
    'pushover_tokens' => getPushoverTokens(),
    'telegram_user_id' => env('TELEGRAM_USER_ID'),
];
