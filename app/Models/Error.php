<?php

namespace App\Models;

use App\Notifications\Notifiable;
use App\Service\ExchangeConfig;

class Error  extends CompositeKeysModel
{
    use Notifiable;

    protected $table = 'errors';
    protected $guarded = [];
    protected $primaryKey = ['exchange', 'account'];
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'exchange',
        'account',
        'message',
    ];
}
