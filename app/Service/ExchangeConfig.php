<?php

namespace App\Service;

class ExchangeConfig
{
    private array $config;


    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getName(): string
    {
        return $this->config["exchange"];
    }

    public function getAccount(): string
    {
        return $this->config["account"];
    }

    public function getApiKey(): string
    {
        return $this->config["apikey"];
    }

    public function getApiSecret(): string
    {
        return $this->config["apisecret"];
    }

    public function getApiPassword(): ?string
    {
        if (array_key_exists('password', $this->config)) {
            return $this->config["password"];
        }
        return null;
    }

    public function AllowQuery(string $query): bool
    {
        if (array_key_exists("nofetch", $this->config)) {
            return !in_array($query, $this->config["nofetch"]);
        }
        return true;
    }

    public function AllowQueryBalance(): bool
    {
        return $this->AllowQuery("balance");
    }

    public function AllowQueryTrades(): bool
    {
        return $this->AllowQuery("trades");
    }

    public function AllowQueryDeposits(): bool
    {
        return $this->AllowQuery("deposits");
    }

    public function AllowQueryWithdrawals(): bool
    {
        return $this->AllowQuery("withdrawals");
    }

    public function getSymbols(string $query): array
    {
        if (array_key_exists($query, $this->config) && is_array($this->config[$query])) {
            return $this->config[$query];
        }
        return array(null);
    }

    public function getTradeSymbols(): array
    {
        return $this->getSymbols("tradesymbols");
    }

    public function getDepositSymbols(): array
    {
        return $this->getSymbols("depositsymbols");
    }

    public function getWithdrawalSymbols(): array
    {
        return $this->getSymbols("withdrawalsymbols");
    }

    public function getNotifyVia()
    {
        if (array_key_exists("notify", $this->config)) {
            return explode(',', $this->config["notify"]);
        }
        return null;
    }
}
