<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Crater\Http\Resources\ExchangeRateProviderCollection;
uses(\Mockery::class);

beforeEach(function () {
    // Clear Mockery expectations before each test to prevent conflicts
    Mockery::close();
});

test('toArray returns an empty array when the underlying collection is empty', function () {
    $request = Mockery::mock(Request::class);
    $collection = new Collection([]);
    $resourceCollection = new ExchangeRateProviderCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

test('toArray correctly transforms items that are instances of JsonResource', function () {
    $request = Mockery::mock(Request::class);

    // Mock individual JsonResource items to control their `toArray` output
    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->once()
                  ->with($request)
                  ->andReturn(['id' => 1, 'name' => 'Transformed Provider A']);

    $mockResource2 = Mockery::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->once()
                  ->with($request)
                  ->andReturn(['id' => 2, 'name' => 'Transformed Provider B']);

    $collection = new Collection([$mockResource1, $mockResource2]);
    $resourceCollection = new ExchangeRateProviderCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()->toEqual([
        ['id' => 1, 'name' => 'Transformed Provider A'],
        ['id' => 2, 'name' => 'Transformed Provider B'],
    ]);
});

test('toArray returns non-JsonResource items directly without transformation', function () {
    $request = Mockery::mock(Request::class);

    $item1 = ['code' => 'USD', 'rate' => 1.0];
    $item2 = (object)['code' => 'EUR', 'rate' => 0.85];
    $item3 = 'simple string';
    $item4 = 123;

    $collection = new Collection([$item1, $item2, $item3, $item4]);
    $resourceCollection = new ExchangeRateProviderCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()->toEqual([
        $item1,
        $item2,
        $item3,
        $item4,
    ]);
});

test('toArray handles a mixed collection of JsonResource and non-JsonResource items correctly', function () {
    $request = Mockery::mock(Request::class);

    // Mock a JsonResource item
    $mockResource = Mockery::mock(JsonResource::class);
    $mockResource->shouldReceive('toArray')
                 ->once()
                 ->with($request)
                 ->andReturn(['id' => 10, 'currency' => 'JPY', 'rate' => 110.5]);

    // Simple data items
    $simpleItem1 = ['provider' => 'Bank A', 'last_updated' => '2023-01-01'];
    $simpleItem2 = (object)['provider' => 'Bank B', 'status' => 'active'];
    $simpleItem3 = null; // Edge case: null item

    $collection = new Collection([$mockResource, $simpleItem1, $simpleItem2, $simpleItem3]);
    $resourceCollection = new ExchangeRateProviderCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()->toEqual([
        ['id' => 10, 'currency' => 'JPY', 'rate' => 110.5],
        $simpleItem1,
        $simpleItem2,
        $simpleItem3,
    ]);
});

test('toArray method propagates the request object to child JsonResources', function () {
    $request = Mockery::mock(Request::class);
    // Add a unique property to the request to verify it's the exact same instance
    $request->someIdentifier = uniqid();

    $mockResource = Mockery::mock(JsonResource::class);
    $mockResource->shouldReceive('toArray')
                 ->once()
                 ->andReturnUsing(function ($receivedRequest) use ($request) {
                     // Assert that the received request is the same instance
                     expect($receivedRequest)->toBe($request);
                     expect($receivedRequest->someIdentifier)->toBe($request->someIdentifier);
                     return ['verified' => true];
                 });

    $collection = new Collection([$mockResource]);
    $resourceCollection = new ExchangeRateProviderCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()->toEqual([['verified' => true]]);
});
