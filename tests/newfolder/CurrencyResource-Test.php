<?php

use Crater\Http\Resources\CurrencyResource;
use Illuminate\Http\Request;

test('it transforms currency model into an array with all expected attributes', function () {
    // Arrange
    $mockCurrency = (object) [
        'id' => 1,
        'name' => 'US Dollar',
        'code' => 'USD',
        'symbol' => '$',
        'precision' => 2,
        'thousand_separator' => ',',
        'decimal_separator' => '.',
        'swap_currency_symbol' => false,
        'exchange_rate' => 1.0
    ];

    $resource = new CurrencyResource($mockCurrency);
    $request = Mockery::mock(Request::class);

    // Act
    $result = $resource->toArray($request);

    // Assert
    expect($result)->toBeArray()->toHaveCount(9);
    expect($result['id'])->toBe(1);
    expect($result['name'])->toBe('US Dollar');
    expect($result['code'])->toBe('USD');
    expect($result['symbol'])->toBe('$');
    expect($result['precision'])->toBe(2);
    expect($result['thousand_separator'])->toBe(',');
    expect($result['decimal_separator'])->toBe('.');
    expect($result['swap_currency_symbol'])->toBeFalse();
    expect($result['exchange_rate'])->toBe(1.0);
});

test('it handles null and empty values for currency attributes correctly', function () {
    // Arrange
    $mockCurrency = (object) [
        'id' => null,
        'name' => '',
        'code' => 'EUR',
        'symbol' => '€',
        'precision' => 2,
        'thousand_separator' => null,
        'decimal_separator' => null,
        'swap_currency_symbol' => true,
        'exchange_rate' => 0.85
    ];

    $resource = new CurrencyResource($mockCurrency);
    $request = Mockery::mock(Request::class);

    // Act
    $result = $resource->toArray($request);

    // Assert
    expect($result)->toBeArray()->toHaveCount(9);
    expect($result['id'])->toBeNull();
    expect($result['name'])->toBe('');
    expect($result['code'])->toBe('EUR');
    expect($result['symbol'])->toBe('€');
    expect($result['precision'])->toBe(2);
    expect($result['thousand_separator'])->toBeNull();
    expect($result['decimal_separator'])->toBeNull();
    expect($result['swap_currency_symbol'])->toBeTrue();
    expect($result['exchange_rate'])->toBe(0.85);
});

test('it ensures all expected keys are present in the output array', function () {
    // Arrange
    $mockCurrency = (object) [
        'id' => 1,
        'name' => 'Test Currency',
        'code' => 'TST',
        'symbol' => 'T',
        'precision' => 2,
        'thousand_separator' => ',',
        'decimal_separator' => '.',
        'swap_currency_symbol' => false,
        'exchange_rate' => 1.0
    ];

    $resource = new CurrencyResource($mockCurrency);
    $request = Mockery::mock(Request::class);

    // Act
    $result = $resource->toArray($request);

    // Assert
    $expectedKeys = [
        'id',
        'name',
        'code',
        'symbol',
        'precision',
        'thousand_separator',
        'decimal_separator',
        'swap_currency_symbol',
        'exchange_rate'
    ];

    foreach ($expectedKeys as $key) {
        expect($result)->toHaveKey($key);
    }
});

afterEach(function () {
    Mockery::close();
});