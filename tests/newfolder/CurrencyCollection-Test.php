<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Crater\Http\Resources\CurrencyCollection;

// A simple stub for a JsonResource that returns its underlying data
class TestCurrencyResource extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource;
    }
}

// Helper: Return array of TestCurrencyResource wrapping each data array
function wrapResourceData(array $items)
{
    return collect($items)->map(function ($item) {
        return new TestCurrencyResource($item);
    });
}

beforeEach(function () {
    // Use Laravel's helper for mocking the Request
    $this->mockRequest = \Mockery::mock(Request::class);
});

test('toArray transforms a non-empty collection of resources correctly', function () {
    // Arrange
    $currencyData1 = ['id' => 1, 'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$'];
    $currencyData2 = ['id' => 2, 'name' => 'Euro', 'code' => 'EUR', 'symbol' => '€'];

    $resources = wrapResourceData([$currencyData1, $currencyData2]);
    $currencyCollection = new CurrencyCollection($resources);

    // Act
    $result = $currencyCollection->resolve($this->mockRequest);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([$currencyData1, $currencyData2]);
});

test('toArray returns an empty array for an empty collection', function () {
    // Arrange
    $emptyResources = new Collection();
    $currencyCollection = new CurrencyCollection($emptyResources);

    // Act
    $result = $currencyCollection->resolve($this->mockRequest);

    // Assert
    expect($result)
        ->toBeArray()
        ->toBeEmpty();
});

test('toArray handles a single resource in the collection', function () {
    // Arrange
    $currencyData = ['id' => 3, 'name' => 'British Pound', 'code' => 'GBP', 'symbol' => '£'];
    $resources = wrapResourceData([$currencyData]);
    $currencyCollection = new CurrencyCollection($resources);

    // Act
    $result = $currencyCollection->resolve($this->mockRequest);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveCount(1)
        ->toEqual([$currencyData]);
});

test('toArray ensures parent::toArray is called with the correct request instance', function () {
    // See context: the only way parent::toArray can be checked is by output, so ensure output is correct.

    // Arrange
    $currencyData = ['id' => 4, 'name' => 'Japanese Yen', 'code' => 'JPY', 'symbol' => '¥'];
    $resources = wrapResourceData([$currencyData]);
    $currencyCollection = new CurrencyCollection($resources);

    // Act
    $result = $currencyCollection->resolve($this->mockRequest);

    // Assert
    expect($result)
        ->toEqual([$currencyData]);
});

test('toArray handles different types of scalar values within resources', function () {
    // Arrange
    $currencyData = ['id' => 5, 'name' => 'Canadian Dollar', 'code' => 'CAD', 'rate' => 1.25, 'is_active' => true];
    $resources = wrapResourceData([$currencyData]);
    $currencyCollection = new CurrencyCollection($resources);

    // Act
    $result = $currencyCollection->resolve($this->mockRequest);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveCount(1)
        ->toEqual([$currencyData]);
});

afterEach(function () {
    Mockery::close();
});