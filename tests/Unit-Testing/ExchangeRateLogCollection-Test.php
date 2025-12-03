<?php

use Crater\Http\Resources\ExchangeRateLogCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;

beforeEach(function () {
    // This ensures Mockery expectations are cleared before each test
    // and helps prevent issues if a test fails before Mockery::close()
    // is explicitly called within its own scope.
    Mockery::close();
});

test('toArray returns an empty array when initialized with an empty collection', function () {
    $request = Request::create('/test-uri', 'GET');
    $collection = new Collection([]);
    $resourceCollection = new ExchangeRateLogCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

test('toArray correctly transforms a collection of non-resource items', function () {
    $request = Request::create('/non-resource-uri', 'GET');
    $collection = new Collection(['string_item', 123, ['key' => 'value']]);
    $resourceCollection = new ExchangeRateLogCollection($collection);

    $result = $resourceCollection->toArray($request);

    // ResourceCollection::toArray returns the raw items if they are not JsonResource instances
    expect($result)->toEqual(['string_item', 123, ['key' => 'value']]);
});

test('toArray correctly transforms a collection of JsonResource items', function () {
    $request = Request::create('/resource-uri', 'POST');

    // Create mock JsonResource instances
    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->once()
                  ->with(Mockery::on(fn ($arg) => $arg instanceof Request && $arg->getPathInfo() === '/resource-uri' && $arg->method() === 'POST'))
                  ->andReturn(['id' => 1, 'name' => 'Exchange Rate A']);

    $mockResource2 = Mockery::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->once()
                  ->with(Mockery::on(fn ($arg) => $arg instanceof Request && $arg->getPathInfo() === '/resource-uri' && $arg->method() === 'POST'))
                  ->andReturn(['id' => 2, 'name' => 'Exchange Rate B']);

    $collection = new Collection([$mockResource1, $mockResource2]);
    $resourceCollection = new ExchangeRateLogCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toEqual([
        ['id' => 1, 'name' => 'Exchange Rate A'],
        ['id' => 2, 'name' => 'Exchange Rate B'],
    ]);
});

test('toArray correctly transforms a mixed collection of JsonResource and non-resource items', function () {
    $request = Request::create('/mixed-uri', 'PUT');

    // Create a mock JsonResource instance
    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->once()
                  ->with(Mockery::on(fn ($arg) => $arg instanceof Request && $arg->getPathInfo() === '/mixed-uri' && $arg->method() === 'PUT'))
                  ->andReturn(['id' => 10, 'type' => 'currency_resource']);

    $nonResourceItem = 'plain_text_item';
    $anotherNonResourceItem = ['data_array' => true];

    $collection = new Collection([$mockResource1, $nonResourceItem, $anotherNonResourceItem]);
    $resourceCollection = new ExchangeRateLogCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toEqual([
        ['id' => 10, 'type' => 'currency_resource'],
        'plain_text_item',
        ['data_array' => true],
    ]);
});

test('toArray passes the correct request object with different parameters to resource items', function () {
    $request = Request::create('/detail-uri?page=2&per_page=10', 'DELETE', ['filter' => 'active']);

    $mockResource = Mockery::mock(JsonResource::class);
    $mockResource->shouldReceive('toArray')
                  ->once()
                  ->with(Mockery::on(function ($arg) use ($request) {
                      return $arg instanceof Request &&
                             $arg->getPathInfo() === '/detail-uri' &&
                             $arg->method() === 'DELETE' &&
                             $arg->query('page') === '2' &&
                             $arg->input('filter') === 'active';
                  }))
                  ->andReturn(['processed_by_complex_request' => true]);

    $collection = new Collection([$mockResource]);
    $resourceCollection = new ExchangeRateLogCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toEqual([['processed_by_complex_request' => true]]);
});

test('toArray handles null items gracefully when not resources', function () {
    $request = Request::create('/null-uri', 'GET');
    $collection = new Collection(['item1', null, 'item3']);
    $resourceCollection = new ExchangeRateLogCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toEqual(['item1', null, 'item3']);
});

test('toArray handles JsonResource returning null or empty array', function () {
    $request = Request::create('/null-resource-uri', 'GET');

    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')->andReturn(null); // Resource returns null

    $mockResource2 = Mockery::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')->andReturn([]); // Resource returns empty array

    $collection = new Collection([$mockResource1, $mockResource2]);
    $resourceCollection = new ExchangeRateLogCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toEqual([null, []]);
});




afterEach(function () {
    Mockery::close();
});
