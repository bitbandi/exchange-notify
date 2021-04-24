<?php


namespace App\Notifications;


use Illuminate\Notifications\Messages\SlackMessage;

class TradeComplete extends BaseNotification
{
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
}
