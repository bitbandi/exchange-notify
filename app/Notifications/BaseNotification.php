<?php


namespace App\Notifications;


use Illuminate\Notifications\Channels\SlackWebhookChannel;
use Illuminate\Notifications\Notification;
use NotificationChannels\Discord\DiscordChannel;
use NotificationChannels\Pushover\PushoverChannel;
use NotificationChannels\Telegram\TelegramChannel;

abstract class BaseNotification extends Notification
{
    protected $via;

    public function __construct($via)
    {
        $this->via = $via ?? ['slack'];
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $via = [];
        if (in_array('discord', $this->via)) $via[] = DiscordChannel::class;
        if (in_array('pushover', $this->via)) $via[] = PushoverChannel::class;
        if (in_array('telegram', $this->via)) $via[] = TelegramChannel::class;
        if (in_array('slack', $this->via)) $via[] = SlackWebhookChannel::class;
        return $via;
    }
}
