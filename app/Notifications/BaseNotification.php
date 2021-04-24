<?php


namespace App\Notifications;


use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification
{
    protected $via;
    public function __construct($via)
    {
        $this->via = $via ?? ['slack'];
    }

    public function via()
    {
        return $this->via;
    }

}
