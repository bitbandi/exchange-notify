<?php

namespace App\Models;

use App\Notifications\Notifiable;

class Trade extends CompositeKeysModel
{
    use Notifiable;

    protected $table = 'trades';
    protected $guarded = [];
    public $timestamps = false;
    protected $primaryKey = ['exchange', 'account', 'tradeid'];
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
        'tradeid',
        'datetime',
        'primary_currency',
        'secondary_currency',
        'type',
        'tradeprice',
        'quantity',
        'total',
        'fee',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'tradeprice' => 'decimal:8',
        'quantity' => 'decimal:8',
        'total' => 'decimal:8',
        'fee' => 'decimal:8',
    ];
}
