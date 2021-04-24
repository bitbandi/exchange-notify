<?php


namespace App\Notifications;


use Illuminate\Notifications\Messages\SlackMessage;

class TransactionComplete extends BaseNotification
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
                $attachment->title(sprintf("%s complete", $notifiable->type == "DEPOSIT" ? "Deposit" : "Withdrawal"))
                    ->content(sprintf("Amount: %.8f %s\nAddress: %s",
                        $notifiable->amount, $notifiable->currency, $notifiable->address))
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
