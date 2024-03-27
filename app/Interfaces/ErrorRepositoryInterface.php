<?php
namespace App\Interfaces;

use App\Service\ExchangeConfig;

interface ErrorRepositoryInterface
{
    public function updateOrCreateByExchange(ExchangeConfig $exchangeConfig, string $message);
    public function deleteByExchange(ExchangeConfig $exchangeConfig);
    public function updateOrCreate(string $message);
    public function delete();
}
