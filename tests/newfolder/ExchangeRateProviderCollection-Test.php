<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Crater\Http\Resources\ExchangeRateProviderCollection;
use Mockery;

// Helper: Make sure we always provide resources (not mocks or arrays/objects/etc) to the collection when required
function makeResourceCollection(array $resources)
{
    return new ExchangeRateProviderCollection(new Collection($resources));
}

beforeEach(function () {
    Mockery::close();
});

test('toArray returns an empty array when the underlying collection is empty', function () {
    $request = new Request();
    $collection = new Collection([]);
    $resourceCollection = new ExchangeRateProviderCollection($collection);
    $result = $resourceCollection->toArray($request);
    expect($result)->toBeArray()->toBeEmpty();
});

test('toArray correctly transforms items that are instances of JsonResource', function () {
    $request = new Request();

    // ExchangeRateProviderResource expects $resource to have ->id and ->name
    $base1 = (object) ['id' => 1, 'name' => 'Provider A'];
    $base2 = (object) ['id' => 2, 'name' => 'Provider B'];

    $mockResource1 = Mockery::mock(JsonResource::class)->makePartial();
    $mockResource1->resource = $base1;
    $mockResource1->shouldReceive('toArray')
        ->once()
        ->with($request)
        ->andReturn(['id' => 1, 'name' => 'Transformed Provider A']);

    $mockResource2 = Mockery::mock(JsonResource::class)->makePartial();
    $mockResource2->resource = $base2;
    $mockResource2->shouldReceive('toArray')
        ->once()
        ->with($request)
        ->andReturn(['id' => 2, 'name' => 'Transformed Provider B']);

    $resourceCollection = makeResourceCollection([$mockResource1, $mockResource2]);
    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()->toEqual([
        ['id' => 1, 'name' => 'Transformed Provider A'],
        ['id' => 2, 'name' => 'Transformed Provider B'],
    ]);
});

test('toArray returns non-JsonResource items directly without transformation', function () {
    $request = new Request();

    // Important: ExchangeRateProviderResource expects resource to have ->id property; do not use that class here.
    $item1 = ['code' => 'USD', 'rate' => 1.0];
    $item2 = (object)['code' => 'EUR', 'rate' => 0.85];
    $item3 = 'simple string';
    $item4 = 123;

    $resourceCollection = makeResourceCollection([$item1, $item2, $item3, $item4]);
    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()->toEqual([
        $item1,
        $item2,
        $item3,
        $item4,
    ]);
});

test('toArray handles a mixed collection of JsonResource and non-JsonResource items correctly', function () {
    $request = new Request();

    // JsonResource expects $resource to have ->id, ->currency, ->rate
    $mockBase = (object)['id' => 10, 'currency' => 'JPY', 'rate' => 110.5];
    $mockResource = Mockery::mock(JsonResource::class)->makePartial();
    $mockResource->resource = $mockBase;
    $mockResource->shouldReceive('toArray')
        ->once()
        ->with($request)
        ->andReturn(['id' => 10, 'currency' => 'JPY', 'rate' => 110.5]);

    $simpleItem1 = ['provider' => 'Bank A', 'last_updated' => '2023-01-01'];
    $simpleItem2 = (object)['provider' => 'Bank B', 'status' => 'active'];
    $simpleItem3 = null; // Edge case: null item

    $resourceCollection = makeResourceCollection([$mockResource, $simpleItem1, $simpleItem2, $simpleItem3]);
    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()->toEqual([
        ['id' => 10, 'currency' => 'JPY', 'rate' => 110.5],
        $simpleItem1,
        $simpleItem2,
        $simpleItem3,
    ]);
});

test('toArray method propagates the request object to child JsonResources', function () {
    $request = new Request();
    $request->someIdentifier = uniqid();

    $mockBase = (object)['dummy' => 1];
    $mockResource = Mockery::mock(JsonResource::class)->makePartial();
    $mockResource->resource = $mockBase;
    $mockResource->shouldReceive('toArray')
        ->once()
        ->withArgs(function ($receivedRequest) use ($request) {
            expect($receivedRequest)->toBe($request);
            expect($receivedRequest->someIdentifier)->toBe($request->someIdentifier);
            return true;
        })
        ->andReturn(['verified' => true]);

    $resourceCollection = makeResourceCollection([$mockResource]);
    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()->toEqual([['verified' => true]]);
});

afterEach(function () {
    Mockery::close();
});