<?php


namespace App\Notifications;

use Illuminate\Notifications\Messages\SlackMessage;
use NotificationChannels\Discord\DiscordMessage;
use NotificationChannels\Pushover\PushoverMessage;
use NotificationChannels\Telegram\TelegramMessage;

class TransactionComplete extends BaseNotification
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
                $attachment->title(sprintf("%s complete", $notifiable->type == "DEPOSIT" ? "Deposit" : "Withdrawal"))
                    ->content(sprintf("Amount: %.8f %s\nAddress: %s",
                        $notifiable->amount, $notifiable->currency, $notifiable->address))
                    ->fields([
                        'Exchange' => $notifiable->exchange,
                        'Account' => $notifiable->account,
                    ])
                    ->markdown(['text'])
                    ->timestamp($time)
//                    ->author('Author2', 'https://laravel.com/fake_author', 'https://laravel.com/fake_author.png')
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
        return PushoverMessage::create(sprintf("Account: %s\nAmount: %.8f %s\nAddress: %s",
            $notifiable->account, $notifiable->amount, $notifiable->currency, $notifiable->address))
            ->title(sprintf("%s complete", $notifiable->type == "DEPOSIT" ? "Deposit" : "Withdrawal"))
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
            ->view('notifications.telegram.transaction', [
                'exchange' => $notifiable->exchange,
                'account' => $notifiable->account,
                'type' => $notifiable->type == "DEPOSIT" ? "Deposit" : "Withdrawal",
                'timestamp' => $timestamp,
                'amount' => $notifiable->amount,
                'currency' => $notifiable->currency,
                'address' => $notifiable->address,
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
        $message = DiscordMessage::create(sprintf("%s complete", $notifiable->type == "DEPOSIT" ? "Deposit" : "Withdrawal"),
            [
                'type' => 'rich',
                'timestamp' => $notifiable->datetime,
                'title' => sprintf("%s @ %s", $notifiable->account, $notifiable->exchange),
                'color' => 0x5cb85c,
                'description' => sprintf("Amount: %.8f %s\nAddress: %s",
                    $notifiable->amount, $notifiable->currency, $notifiable->address),
//                'footer' => [
//                    'text' => 'ez a footer',
//                ],
            ]
        );
        dump($message);
        return $message;
    }
}
