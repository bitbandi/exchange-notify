<?php


namespace App\Notifications;

use Illuminate\Notifications\Notifiable as NotifiableTrait;

trait Notifiable
{
    use NotifiableTrait;

    public function routeNotificationForSlack($notification)
    {
        return config('notifications.slack_url');
    }

}
