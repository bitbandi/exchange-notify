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

    /**
     * Delete records from the database.
     *
     * @return mixed
     */
    public static function DeleteByExchange(ExchangeConfig $exchangeConfig)
    {
        return self::where([
            'exchange' => strtoupper($exchangeConfig->getName()),
            'account' => $exchangeConfig->getAccount(),
        ])->delete();
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Illuminate\Database\Eloquent\Model|static
     */
    public static function updateOrCreateByExchange(ExchangeConfig $exchangeConfig, string $message)
    {
        return self::updateOrCreate(
            [
                'exchange' => strtoupper($exchangeConfig->getName()),
                'account' => $exchangeConfig->getAccount(),
            ],
            [
                'message' => $message,
            ]
        );
    }

    /**
     * Delete records from the database.
     *
     * @return mixed
     */
    public static function DeleteGlobal()
    {
        return self::where([
            'exchange' => 'MAIN',
            'account' => 'N/A',
        ])->delete();
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Illuminate\Database\Eloquent\Model|static
     */
    public static function updateOrCreateGlobal(string $message)
    {
        return self::updateOrCreate(
            [
                'exchange' => 'MAIN',
                'account' => 'N/A',
            ],
            [
                'message' => $message,
            ]
        );
    }
}
