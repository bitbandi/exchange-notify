<?php

namespace App\Models;

use App\Notifications\Notifiable;

class Balance  extends CompositeKeysModel
{
    use Notifiable;

    protected $table = 'balances';
    protected $guarded = [];
    protected $primaryKey = ['exchange', 'account', 'currency'];
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
        'currency',
        'amount',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'double',
    ];
}
