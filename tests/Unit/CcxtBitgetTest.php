<?php

use App\Helpers\CcxtBitget;

function newCcxtBitget(): CcxtBitget
{
    return new CcxtBitget([]);
}

function callMergeTrades(CcxtBitget $exchange, array $trades): array
{
    $method = new ReflectionMethod(CcxtBitget::class, 'mergeTrades');
    $method->setAccessible(true);

    return $method->invoke($exchange, $trades);
}

test('a single fill is passed through with info and fees stripped', function () {
    $trade = [
        'order' => 'o1',
        'amount' => 1.0,
        'cost' => 100.0,
        'price' => 100.0,
        'fee' => ['currency' => 'USDT', 'cost' => 0.1],
        'info' => ['raw' => true],
        'fees' => [['currency' => 'USDT', 'cost' => 0.1]],
    ];

    $result = callMergeTrades(newCcxtBitget(), [$trade]);

    expect($result)->toHaveKey('o1');
    expect($result['o1']['id'])->toBe('o1');
    expect($result['o1'])->not->toHaveKey('info');
    expect($result['o1'])->not->toHaveKey('fees');
    expect($result['o1']['amount'])->toBe(1.0);
});

test('multiple fills of the same order are merged with a volume weighted price', function () {
    $trades = [
        [
            'order' => 'o1',
            'amount' => 1.0,
            'cost' => 100.0,
            'price' => 100.0,
            'fee' => ['currency' => 'USDT', 'cost' => 0.1],
            'info' => ['raw' => true],
        ],
        [
            'order' => 'o1',
            'amount' => 1.0,
            'cost' => 200.0,
            'price' => 200.0,
            'fee' => ['currency' => 'USDT', 'cost' => 0.2],
            'info' => ['raw' => true],
        ],
    ];

    $result = callMergeTrades(newCcxtBitget(), $trades);
    $merged = $result['o1'];

    expect($merged['amount'])->toBe(2.0);
    expect($merged['cost'])->toBe(300.0);
    expect(round($merged['fee']['cost'], 8))->toBe(0.3);
    expect($merged['price'])->toBe(150.0);
});

test('merging fills with mismatched fee currencies throws instead of silently summing', function () {
    $trades = [
        [
            'order' => 'o1',
            'amount' => 1.0,
            'cost' => 100.0,
            'fee' => ['currency' => 'USDT', 'cost' => 0.1],
        ],
        [
            'order' => 'o1',
            'amount' => 1.0,
            'cost' => 200.0,
            'fee' => ['currency' => 'USDC', 'cost' => 0.2],
        ],
    ];

    expect(fn () => callMergeTrades(newCcxtBitget(), $trades))
        ->toThrow(RuntimeException::class, 'o1');
});

test('a missing fee cost on a later fill is treated as zero', function () {
    $trades = [
        [
            'order' => 'o1',
            'amount' => 1.0,
            'cost' => 100.0,
            'fee' => ['currency' => 'USDT', 'cost' => 0.1],
        ],
        [
            'order' => 'o1',
            'amount' => 1.0,
            'cost' => 200.0,
            'fee' => ['currency' => 'USDT'],
        ],
    ];

    $result = callMergeTrades(newCcxtBitget(), $trades);

    expect($result['o1']['fee']['cost'])->toBe(0.1);
});

test('fixTransactions renames withdraw to withdrawal and nets the fee out of the amount', function () {
    $exchange = newCcxtBitget();

    $transactions = [
        [
            'type' => 'withdraw',
            'info' => ['fee' => '-0.5'],
            'amount' => 100.0,
            'currency' => 'BTC',
        ],
        [
            'type' => 'deposit',
            'info' => ['fee' => '0'],
            'amount' => 50.0,
            'currency' => 'ETH',
        ],
    ];

    $result = $exchange->fixTransactions($transactions);

    expect($result[0]['type'])->toBe('withdrawal');
    expect($result[0]['amount'])->toBe(99.5);
    expect($result[0]['fee'])->toBe(['currency' => 'BTC', 'cost' => 0.5]);

    expect($result[1]['type'])->toBe('deposit');
    expect($result[1]['amount'])->toBe(50.0);
    expect($result[1]['fee'])->toBe(['currency' => 'ETH', 'cost' => 0.0]);
});
