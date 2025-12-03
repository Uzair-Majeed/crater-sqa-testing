<?php

use Crater\Http\Controllers\V1\Admin\General\CurrenciesController;
use Crater\Http\Resources\CurrencyResource;
use Crater\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

beforeEach(function () {
    // Ensure mocks are cleared before each test to prevent interference
    Mockery::close();
});

test('it returns an empty collection of currency resources when no currencies exist', function () {
    // Arrange: Mock the Currency model's static methods for fluent chain
    $mockQueryBuilder = Mockery::mock();
    // Expect `get()` to be called once and return an empty Eloquent Collection
    $mockQueryBuilder->shouldReceive('get')->once()->andReturn(new Collection());

    // Mock the static `latest()` method on the Currency model alias
    Mockery::mock('overload:' . Currency::class)
        ->shouldReceive('latest')
        ->once()
        ->andReturn($mockQueryBuilder); // Return our mock query builder

    // Mock the static `collection()` method on the CurrencyResource alias
    // We expect it to be called with an empty collection and return a mock AnonymousResourceCollection
    $mockAnonymousResourceCollection = Mockery::mock(AnonymousResourceCollection::class);
    $mockAnonymousResourceCollection->shouldReceive('jsonSerialize')->andReturn([]); // Simulate serialization

    Mockery::mock('alias:' . CurrencyResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($collection) {
            // Assert that the collection passed to `collection()` is empty
            return $collection instanceof Collection && $collection->isEmpty();
        })
        ->andReturn($mockAnonymousResourceCollection); // Return our mock collection resource

    $controller = new CurrenciesController();
    $request = new Request(); // Request is not used, so a basic instance is sufficient

    // Act: Invoke the controller
    $response = $controller($request);

    // Assert: Verify the response
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->jsonSerialize())->toBe([]);
});

test('it returns a collection of currency resources when currencies exist', function () {
    // Arrange: Create dummy Currency models
    $currency1 = (object)['id' => 1, 'name' => 'USD', 'code' => 'USD', 'symbol' => '$', 'precision' => 2, 'created_at' => now(), 'updated_at' => now()];
    $currency2 = (object)['id' => 2, 'name' => 'EUR', 'code' => 'EUR', 'symbol' => '€', 'precision' => 2, 'created_at' => now(), 'updated_at' => now()];
    $mockCurrencies = new Collection([$currency1, $currency2]);

    // Expected data structure after being passed through CurrencyResource (simplified for test)
    $expectedResourceData = [
        ['id' => 1, 'name' => 'USD', 'code' => 'USD'],
        ['id' => 2, 'name' => 'EUR', 'code' => 'EUR'],
    ];

    // Mock the Currency model's static methods for fluent chain
    $mockQueryBuilder = Mockery::mock();
    // Expect `get()` to be called once and return our mock currencies
    $mockQueryBuilder->shouldReceive('get')->once()->andReturn($mockCurrencies);

    // Mock the static `latest()` method on the Currency model alias
    Mockery::mock('overload:' . Currency::class)
        ->shouldReceive('latest')
        ->once()
        ->andReturn($mockQueryBuilder); // Return our mock query builder

    // Mock the static `collection()` method on the CurrencyResource alias
    // We expect it to be called with our $mockCurrencies collection
    $mockAnonymousResourceCollection = Mockery::mock(AnonymousResourceCollection::class);
    $mockAnonymousResourceCollection->shouldReceive('jsonSerialize')->andReturn($expectedResourceData); // Simulate serialization

    Mockery::mock('alias:' . CurrencyResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($collection) use ($mockCurrencies) {
            // Assert that the collection passed to `collection()` matches our mock currencies
            return $collection instanceof Collection && $collection->toArray() === $mockCurrencies->toArray();
        })
        ->andReturn($mockAnonymousResourceCollection); // Return our mock collection resource

    $controller = new CurrenciesController();
    $request = new Request(); // Request is not used, so a basic instance is sufficient

    // Act: Invoke the controller
    $response = $controller($request);

    // Assert: Verify the response
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->jsonSerialize())->toBe($expectedResourceData);
});
 

afterEach(function () {
    Mockery::close();
});
