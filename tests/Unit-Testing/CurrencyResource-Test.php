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
    $this->assertIsArray($result);
    $this->assertCount(9, $result);

    $this->assertEquals(1, $result['id']);
    $this->assertEquals('US Dollar', $result['name']);
    $this->assertEquals('USD', $result['code']);
    $this->assertEquals('$', $result['symbol']);
    $this->assertEquals(2, $result['precision']);
    $this->assertEquals(',', $result['thousand_separator']);
    $this->assertEquals('.', $result['decimal_separator']);
    $this->assertFalse($result['swap_currency_symbol']);
    $this->assertEquals(1.0, $result['exchange_rate']);
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
    $this->assertIsArray($result);
    $this->assertCount(9, $result);

    $this->assertNull($result['id']);
    $this->assertEquals('', $result['name']);
    $this->assertEquals('EUR', $result['code']);
    $this->assertEquals('€', $result['symbol']);
    $this->assertEquals(2, $result['precision']);
    $this->assertNull($result['thousand_separator']);
    $this->assertNull($result['decimal_separator']);
    $this->assertTrue($result['swap_currency_symbol']);
    $this->assertEquals(0.85, $result['exchange_rate']);
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
        $this->assertArrayHasKey($key, $result);
    }
});

 

afterEach(function () {
    Mockery::close();
});
