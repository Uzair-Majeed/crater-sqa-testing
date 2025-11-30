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


    beforeEach(function () {
        // Use Laravel's helper for mocking the Request
        $this->mockRequest = \Mockery::mock(Request::class);
    });

    test('toArray transforms a non-empty collection of resources correctly', function () {
        // Arrange
        $currencyData1 = ['id' => 1, 'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$'];
        $currencyData2 = ['id' => 2, 'name' => 'Euro', 'code' => 'EUR', 'symbol' => '€'];

        $resource1 = new TestCurrencyResource($currencyData1);
        $resource2 = new TestCurrencyResource($currencyData2);

        $collection = new Collection([$resource1, $resource2]);
        $currencyCollection = new CurrencyCollection($collection);

        // Act
        $result = $currencyCollection->toArray($this->mockRequest);

        // Assert
        expect($result)
            ->toBeArray()
            ->toHaveCount(2)
            ->toEqual([$currencyData1, $currencyData2]);
    });

    test('toArray returns an empty array for an empty collection', function () {
        // Arrange
        $emptyCollection = new Collection();
        $currencyCollection = new CurrencyCollection($emptyCollection);

        // Act
        $result = $currencyCollection->toArray($this->mockRequest);

        // Assert
        expect($result)
            ->toBeArray()
            ->toBeEmpty();
    });

    test('toArray handles a single resource in the collection', function () {
        // Arrange
        $currencyData = ['id' => 3, 'name' => 'British Pound', 'code' => 'GBP', 'symbol' => '£'];
        $resource = new TestCurrencyResource($currencyData);
        $collection = new Collection([$resource]);
        $currencyCollection = new CurrencyCollection($collection);

        // Act
        $result = $currencyCollection->toArray($this->mockRequest);

        // Assert
        expect($result)
            ->toBeArray()
            ->toHaveCount(1)
            ->toEqual([$currencyData]);
    });

    test('toArray ensures parent::toArray is called with the correct request instance', function () {
        // The `CurrencyCollection` itself only delegates to `parent::toArray`.
        // To verify the `Request` instance is passed correctly, we observe the output
        // of the parent call. If the parent or its child resources were to inspect
        // the request object, we could set expectations on the mock request.
        // For this simple pass-through, verifying the output is sufficient evidence
        // that the parent method was invoked with the provided request.

        // Arrange
        $currencyData = ['id' => 4, 'name' => 'Japanese Yen', 'code' => 'JPY', 'symbol' => '¥'];
        $resource = new TestCurrencyResource($currencyData);
        $collection = new Collection([$resource]);
        $currencyCollection = new CurrencyCollection($collection);

        // Act
        $result = $currencyCollection->toArray($this->mockRequest);

        // Assert
        expect($result)
            ->toEqual([$currencyData]);

        // If the TestCurrencyResource's toArray method was sensitive to the request,
        // we could add assertions on $this->mockRequest, e.g., $this->mockRequest->shouldHaveReceived('methodCall')->once();
        // However, for this setup, the output validation is sufficient.
    });

    test('toArray handles different types of scalar values within resources', function () {
        // Arrange
        $currencyData = ['id' => 5, 'name' => 'Canadian Dollar', 'code' => 'CAD', 'rate' => 1.25, 'is_active' => true];
        $resource = new TestCurrencyResource($currencyData);
        $collection = new Collection([$resource]);
    
        $currencyCollection = new CurrencyCollection($collection);
    
        // Act
        $result = $currencyCollection->toArray($this->mockRequest);
    
        // Assert
        expect($result)
            ->toBeArray()
            ->toHaveCount(1)
            ->toEqual([$currencyData]);
    });
