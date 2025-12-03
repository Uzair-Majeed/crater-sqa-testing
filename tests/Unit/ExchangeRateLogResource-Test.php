<?php

use Illuminate\Support\Arr;

test('exchange rate log resource transforms correctly to array', function () {
    // Arrange: Create a mock resource object with sample data
    $mockResource = (object) [
        'id' => 1,
        'company_id' => 101,
        'base_currency_id' => 201,
        'currency_id' => 301,
        'exchange_rate' => 1.2345,
    ];

    // Arrange: Instantiate the resource with the mock data
    $resource = new \Crater\Http\Resources\ExchangeRateLogResource($mockResource);

    // Arrange: Create a mock request object (it's not used by toArray, but required for the signature)
    $mockRequest = \Mockery::mock(\Illuminate\Http\Request::class);

    // Act: Call the toArray method
    $result = $resource->toArray($mockRequest);

    // Assert: Verify the returned array structure and values
    expect($result)->toBeArray()
        ->toHaveKeys([
            'id',
            'company_id',
            'base_currency_id',
            'currency_id',
            'exchange_rate',
        ])
        ->id->toBe($mockResource->id)
        ->company_id->toBe($mockResource->company_id)
        ->base_currency_id->toBe($mockResource->base_currency_id)
        ->currency_id->toBe($mockResource->currency_id)
        ->exchange_rate->toBe($mockResource->exchange_rate);
});

test('exchange rate log resource handles null properties gracefully', function () {
    // Arrange: Create a mock resource object with null values for all properties
    $mockResource = (object) [
        'id' => null,
        'company_id' => null,
        'base_currency_id' => null,
        'currency_id' => null,
        'exchange_rate' => null,
    ];

    // Arrange: Instantiate the resource with the null data
    $resource = new \Crater\Http\Resources\ExchangeRateLogResource($mockResource);
    $mockRequest = \Mockery::mock(\Illuminate\Http\Request::class);

    // Act: Call the toArray method
    $result = $resource->toArray($mockRequest);

    // Assert: Verify that null values are correctly transformed
    expect($result)->toBeArray()
        ->id->toBeNull()
        ->company_id->toBeNull()
        ->base_currency_id->toBeNull()
        ->currency_id->toBeNull()
        ->exchange_rate->toBeNull();
});

test('exchange rate log resource handles zero and negative exchange rates', function () {
    // Arrange: Test with a zero exchange rate
    $mockResourceZero = (object) [
        'id' => 2,
        'company_id' => 102,
        'base_currency_id' => 202,
        'currency_id' => 302,
        'exchange_rate' => 0.0,
    ];
    $resourceZero = new \Crater\Http\Resources\ExchangeRateLogResource($mockResourceZero);
    $mockRequest = \Mockery::mock(\Illuminate\Http\Request::class);

    // Act & Assert for zero
    $resultZero = $resourceZero->toArray($mockRequest);
    expect($resultZero)->exchange_rate->toBe(0.0);

    // Arrange: Test with a negative exchange rate
    $mockResourceNegative = (object) [
        'id' => 3,
        'company_id' => 103,
        'base_currency_id' => 203,
        'currency_id' => 303,
        'exchange_rate' => -0.54321,
    ];
    $resourceNegative = new \Crater\Http\Resources\ExchangeRateLogResource($mockResourceNegative);

    // Act & Assert for negative
    $resultNegative = $resourceNegative->toArray($mockRequest);
    expect($resultNegative)->exchange_rate->toBe(-0.54321);
});

test('exchange rate log resource handles non-numeric exchange rates if passed', function () {
    // While typically exchange_rate would be numeric, test for string input
    $mockResourceString = (object) [
        'id' => 4,
        'company_id' => 104,
        'base_currency_id' => 204,
        'currency_id' => 304,
        'exchange_rate' => '1.50',
    ];
    $resourceString = new \Crater\Http\Resources\ExchangeRateLogResource($mockResourceString);
    $mockRequest = \Mockery::mock(\Illuminate\Http\Request::class);

    $resultString = $resourceString->toArray($mockRequest);
    expect($resultString)->exchange_rate->toBe('1.50');
});

test('exchange rate log resource handles empty resource object', function () {
    // Arrange: Create an empty mock resource object (no properties)
    $mockResourceEmpty = (object) [];

    // Arrange: Instantiate the resource
    $resource = new \Crater\Http\Resources\ExchangeRateLogResource($mockResourceEmpty);
    $mockRequest = \Mockery::mock(\Illuminate\Http\Request::class);

    // Act: Call toArray
    // Instead of relying on resource property access, check array keys safely
    $result = $resource->toArray($mockRequest);

    // Assert: All keys should be present with null values
    expect($result)->toBeArray()
        ->toHaveKeys([
            'id',
            'company_id',
            'base_currency_id',
            'currency_id',
            'exchange_rate',
        ])
        ->id->toBe(Arr::get($result, 'id', null))
        ->company_id->toBe(Arr::get($result, 'company_id', null))
        ->base_currency_id->toBe(Arr::get($result, 'base_currency_id', null))
        ->currency_id->toBe(Arr::get($result, 'currency_id', null))
        ->exchange_rate->toBe(Arr::get($result, 'exchange_rate', null));
    // Now check that all are null
    expect($result['id'])->toBeNull();
    expect($result['company_id'])->toBeNull();
    expect($result['base_currency_id'])->toBeNull();
    expect($result['currency_id'])->toBeNull();
    expect($result['exchange_rate'])->toBeNull();
});

afterEach(function () {
    \Mockery::close();
});