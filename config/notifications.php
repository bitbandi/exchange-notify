<?php
use App\Support\Pushover;

return [
    'notify_at' => env('NOTIFY_DAILY_AT', '09:00'),

    'slack_url' => env('SLACK_URL'),
    'pushover_key' => env('PUSHOVER_KEY'),
    'pushover_token' => env('PUSHOVER_APP'),
    'pushover_tokens' => Pushover::getTokens(),
    'discord_user_id' => env('DISCORD_USER_ID'),
    'telegram_user_id' => env('TELEGRAM_USER_ID'),
];
