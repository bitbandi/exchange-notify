<?php

namespace App\Models;

use App\Notifications\Notifiable;

class Transaction extends CompositeKeysModel
{
    use Notifiable;

    protected $table = 'transactions';
    protected $guarded = [];
    public $timestamps = false;
    protected $primaryKey = ['exchange', 'account', 'trxid'];
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
        'trxid',
        'currency',
        'datetime',
        'type',
        'address',
        'amount',
        'fee',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'double',
        'fee' => 'double',
    ];
}
