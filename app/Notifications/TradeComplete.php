<?php


namespace App\Notifications;


use Illuminate\Notifications\Messages\SlackMessage;
use NotificationChannels\Discord\DiscordMessage;
use NotificationChannels\Pushover\PushoverMessage;
use NotificationChannels\Telegram\TelegramMessage;

class TradeComplete extends BaseNotification
{
    /**
     * Get the Slack representation of the notification.
     *
     * @param mixed $notifiable
     * @return SlackMessage
     */
    public function toSlack($notifiable)
    {
        dump($notifiable);
        return (new SlackMessage())
            ->success()
            ->from("exchange-notify")
//            ->content(sprintf("Trade %s/%s", $notifiable->primary_currency, $notifiable->secondary_currency))
            ->attachment(function ($attachment) use ($notifiable) {
                $time = strtotime($notifiable->datetime);
                $attachment->title(sprintf("%s complete", $notifiable->type == "BUY" ? "Buy" : "Sell"))
                    ->content(sprintf("Price: %.8f\nAmount: %.8f %s\nTotal: %.8f %s",
                        $notifiable->tradeprice, $notifiable->quantity, $notifiable->secondary_currency, $notifiable->total, $notifiable->primary_currency))
                    ->fields([
                        'Exchange' => $notifiable->exchange,
                        'Account' => $notifiable->account,
                    ])
                    ->markdown(['text'])
                    ->timestamp($time)
                //    ->author('Author2', 'https://laravel.com/fake_author', 'https://laravel.com/fake_author.png')
                ;
            });
    }

    /**
     * Get the Pushover representation of the notification.
     *
     * @param mixed $notifiable
     * @return PushoverMessage
     */
    public function toPushover($notifiable)
    {
        $timestamp = strtotime($notifiable->datetime);
        return PushoverMessage::create(sprintf("Price: %.8f\nAmount: %.8f %s\nTotal: %.8f %s",
            $notifiable->tradeprice, $notifiable->quantity, $notifiable->secondary_currency, $notifiable->total, $notifiable->primary_currency))
            ->title(sprintf("%s complete", $notifiable->type == "BUY" ? "Buy" : "Sell"))
            ->time($timestamp);
    }

    /**
     * Get the Pushover representation of the notification.
     *
     * @param mixed $notifiable
     * @return TelegramMessage
     */
    public function toTelegram($notifiable)
    {
        $timestamp = strtotime($notifiable->datetime);
        return TelegramMessage::create()
            ->view('notifications.telegram.trade', [
                'exchange' => $notifiable->exchange,
                'account' => $notifiable->account,
                'tradeid' => $notifiable->tradeid,
                'timestamp' => $timestamp,
                'type' => $notifiable->type == "BUY" ? "Buy" : "Sell",
                'primary_currency' => $notifiable->primary_currency,
                'secondary_currency' => $notifiable->secondary_currency,
                'tradeprice' => $notifiable->tradeprice,
                'quantity' => $notifiable->quantity,
                'total' => $notifiable->total,
                'fee' => $notifiable->fee,
            ]);
    }

    /**
     * Get the Discord representation of the notification.
     *
     * @param mixed $notifiable
     * @return DiscordMessage
     */
    public function toDiscord($notifiable)
    {
        $message = DiscordMessage::create(sprintf("%s complete", $notifiable->type == "BUY" ? "Buy" : "Sell"),
            [
                'type' => 'rich',
                'timestamp' => $notifiable->datetime,
                'title' => sprintf("%s @ %s", $notifiable->account, $notifiable->exchange),
                'color' => 0x5cb85c,
                'description' => sprintf("Price: %.8f\nAmount: %.8f %s\nTotal: %.8f %s",
                    $notifiable->tradeprice, $notifiable->quantity, $notifiable->secondary_currency, $notifiable->total, $notifiable->primary_currency),
//                'footer' => [
//                    'text' => 'ez a footer',
//                ],
            ]
        );
        dump($message);
        return $message;
    }
}
