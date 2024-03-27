<?php
namespace App\Interfaces;

use App\Service\ExchangeConfig;

interface ErrorRepositoryInterface
{
    public function updateOrCreateByExchange(ExchangeConfig $exchangeConfig, string $message);
    public function deleteErrorByExchange(ExchangeConfig $exchangeConfig);
    public function updateOrCreateError(string $message);
    public function deleteError();
}
