<?php

namespace App\Repositories;

use App\Interfaces\ErrorRepositoryInterface;
use App\Models\Error;
use App\Service\ExchangeConfig;

class ErrorRepository implements ErrorRepositoryInterface
{
    public function updateOrCreateByExchange(ExchangeConfig $exchangeConfig, string $message)
    {
        return Error::updateOrCreate(
            [
                'exchange' => strtoupper($exchangeConfig->getName()),
                'account' => $exchangeConfig->getAccount(),
            ],
            [
                'message' => $message,
            ]
        );
    }
    public function deleteErrorByExchange(ExchangeConfig $exchangeConfig)
    {
        return Error::where([
            'exchange' => strtoupper($exchangeConfig->getName()),
            'account' => $exchangeConfig->getAccount(),
        ])->delete();
    }
    public function updateOrCreateError(string $message)
    {
        return Error::updateOrCreate(
            [
                'exchange' => 'MAIN',
                'account' => 'N/A',
            ],
            [
                'message' => $message,
            ]
        );
    }
    public function deleteError()
    {
        return Error::where([
            'exchange' => 'MAIN',
            'account' => 'N/A',
        ])->delete();
    }
}
