<?php


namespace App\Notifications;

use Illuminate\Notifications\Notifiable as NotifiableTrait;
use NotificationChannels\Discord\Discord;
use NotificationChannels\Pushover\PushoverReceiver;

trait Notifiable
{
    use NotifiableTrait;

    /**
     * Route notifications for slack.
     *
     * @return string
     */
    public function routeNotificationForSlack($notification)
    {
        return config('notifications.slack_url');
    }

    /**
     * Route notifications for the pushover channel.
     *
     * @return PushoverReceiver
     */
    public function routeNotificationForPushover()
    {
        $token = config('notifications.pushover_token');
        $token = config('notifications.pushover_tokens.' . $this->exchange, $token);
        return PushoverReceiver::withUserKey(config('notifications.pushover_key'))
            ->withApplicationToken($token);
    }

    /**
     * Route notifications for the Telegram channel.
     *
     * @return int
     */
    public function routeNotificationForTelegram()
    {
        return config('notifications.telegram_user_id');
    }

    /**
     * Route notifications for the Discord channel.
     *
     * @return int
     */
    public function routeNotificationForDiscord()
    {
        return app(Discord::class)->getPrivateChannel(config('notifications.discord_user_id'));
    }
}
