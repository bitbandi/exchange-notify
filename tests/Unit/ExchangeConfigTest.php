<?php

use App\Service\ExchangeConfig;

function makeExchangeConfig(array $overrides = []): ExchangeConfig
{
    return new ExchangeConfig(array_merge([
        'exchange' => 'bittrex',
        'account' => 'myaccount',
        'apikey' => 'key123',
        'apisecret' => 'secret123',
    ], $overrides));
}

test('exposes basic exchange credentials', function () {
    $config = makeExchangeConfig();

    expect($config->getName())->toBe('bittrex');
    expect($config->getAccount())->toBe('myaccount');
    expect($config->getApiKey())->toBe('key123');
    expect($config->getApiSecret())->toBe('secret123');
});

test('api password is null when not configured', function () {
    $config = makeExchangeConfig();

    expect($config->getApiPassword())->toBeNull();
});

test('api password is returned when configured', function () {
    $config = makeExchangeConfig(['password' => 'pw']);

    expect($config->getApiPassword())->toBe('pw');
});

test('custom login defaults to an empty array', function () {
    $config = makeExchangeConfig();

    expect($config->getCustomLogin())->toBe([]);
});

test('custom login is returned when configured', function () {
    $config = makeExchangeConfig(['customlogin' => ['uid' => '123']]);

    expect($config->getCustomLogin())->toBe(['uid' => '123']);
});

test('all queries are allowed by default', function () {
    $config = makeExchangeConfig();

    expect($config->AllowQueryBalance())->toBeTrue();
    expect($config->AllowQueryTrades())->toBeTrue();
    expect($config->AllowQueryDeposits())->toBeTrue();
    expect($config->AllowQueryWithdrawals())->toBeTrue();
});

test('queries listed in nofetch are disallowed', function () {
    $config = makeExchangeConfig(['nofetch' => ['trades', 'withdrawals']]);

    expect($config->AllowQueryTrades())->toBeFalse();
    expect($config->AllowQueryWithdrawals())->toBeFalse();
    expect($config->AllowQueryBalance())->toBeTrue();
    expect($config->AllowQueryDeposits())->toBeTrue();
});

test('symbol lists default to a single null entry', function () {
    $config = makeExchangeConfig();

    expect($config->getTradeSymbols())->toBe([null]);
    expect($config->getDepositSymbols())->toBe([null]);
    expect($config->getWithdrawalSymbols())->toBe([null]);
});

test('symbol lists are returned when configured', function () {
    $config = makeExchangeConfig([
        'tradesymbols' => ['BTC/USD', 'ETH/USD'],
    ]);

    expect($config->getTradeSymbols())->toBe(['BTC/USD', 'ETH/USD']);
});

test('notify via is null when not configured', function () {
    $config = makeExchangeConfig();

    expect($config->getNotifyVia())->toBeNull();
});

test('notify via splits a comma separated list', function () {
    $config = makeExchangeConfig(['notify' => 'slack,telegram']);

    expect($config->getNotifyVia())->toBe(['slack', 'telegram']);
});
