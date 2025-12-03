<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;

// Ensure Mockery mocks are closed after each test to prevent test pollution
beforeEach(function () {
    Mockery::close();
});

test('toArray returns an empty array when initialized with an empty collection', function () {
    $request = Mockery::mock(Request::class);
    $collection = Collection::make([]);

    $expenseCollection = new \Crater\Http\Resources\ExpenseCollection($collection);

    $result = $expenseCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

test('toArray correctly delegates to parent for a collection of simple objects', function () {
    $request = Mockery::mock(Request::class);
    $item1 = (object)['id' => 1, 'amount' => 100];
    $item2 = (object)['id' => 2, 'amount' => 200];
    $collection = Collection::make([$item1, $item2]);

    $expenseCollection = new \Crater\Http\Resources\ExpenseCollection($collection);

    $result = $expenseCollection->toArray($request);

    // ResourceCollection's default behavior for non-JsonResource items is to return them as-is.
    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toEqual([$item1, $item2]);
});

test('toArray correctly delegates to parent and passes request to JsonResource items', function () {
    $request = Mockery::mock(Request::class);

    // Mock JsonResource items, expecting their toArray method to be called with the specific request
    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->once()
                  ->with($request)
                  ->andReturn(['expense_id' => 1, 'formatted_amount' => '100.00']);

    $mockResource2 = Mockery::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->once()
                  ->with($request)
                  ->andReturn(['expense_id' => 2, 'formatted_amount' => '200.00']);

    $collection = Collection::make([$mockResource1, $mockResource2]);

    $expenseCollection = new \Crater\Http\Resources\ExpenseCollection($collection);

    $result = $expenseCollection->toArray($request);

    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toEqual([
            ['expense_id' => 1, 'formatted_amount' => '100.00'],
            ['expense_id' => 2, 'formatted_amount' => '200.00'],
        ]);
});

test('toArray handles different request instances when delegating', function () {
    $request1 = Mockery::mock(Request::class);
    $request2 = Mockery::mock(Request::class);

    // Mock a JsonResource item that expects different requests
    $mockResource = Mockery::mock(JsonResource::class);
    $mockResource->shouldReceive('toArray')
                  ->once()->with($request1)
                  ->andReturn(['data' => 'for_request_1']);
    $mockResource->shouldReceive('toArray')
                  ->once()->with($request2)
                  ->andReturn(['data' => 'for_request_2']);

    $collection = Collection::make([$mockResource]);

    // Test with request1
    $expenseCollection1 = new \Crater\Http\Resources\ExpenseCollection($collection);
    $result1 = $expenseCollection1->toArray($request1);
    expect($result1)->toEqual([['data' => 'for_request_1']]);

    // Test with request2 (requires a new collection instance for fresh mock expectations)
    $expenseCollection2 = new \Crater\Http\Resources\ExpenseCollection($collection);
    $result2 = $expenseCollection2->toArray($request2);
    expect($result2)->toEqual([['data' => 'for_request_2']]);
});

test('toArray handles collection with null items by passing them through', function () {
    $request = Mockery::mock(Request::class);
    $item1 = (object)['id' => 1];
    $item2 = null; // Null item
    $item3 = (object)['id' => 3];
    $collection = Collection::make([$item1, $item2, $item3]);

    $expenseCollection = new \Crater\Http\Resources\ExpenseCollection($collection);

    $result = $expenseCollection->toArray($request);

    // ResourceCollection's default behavior passes nulls through
    expect($result)->toBeArray()
        ->toHaveCount(3)
        ->toEqual([$item1, $item2, $item3]);
});

test('constructor correctly initializes the resource collection', function () {
    $collection = Collection::make(['item1', 'item2']);
    $expenseCollection = new \Crater\Http\Resources\ExpenseCollection($collection);

    expect($expenseCollection)->toBeInstanceOf(\Crater\Http\Resources\ExpenseCollection::class);
    // Directly access the protected 'resource' property to confirm it was set by the parent constructor
    expect($expenseCollection->resource)->toEqual($collection);
});

test('toArray handles different types of collection items correctly', function () {
    $request = Mockery::mock(Request::class);

    $simpleObject = (object)['key' => 'value'];
    $arrayItem = ['name' => 'array_item'];
    
    $mockResource = Mockery::mock(JsonResource::class);
    $mockResource->shouldReceive('toArray')
                 ->once()
                 ->with($request)
                 ->andReturn(['transformed_key' => 'transformed_value']);

    $collection = Collection::make([
        $simpleObject,
        $arrayItem,
        $mockResource,
    ]);

    $expenseCollection = new \Crater\Http\Resources\ExpenseCollection($collection);
    $result = $expenseCollection->toArray($request);

    expect($result)->toBeArray()
        ->toHaveCount(3)
        ->toEqual([
            $simpleObject,
            $arrayItem,
            ['transformed_key' => 'transformed_value'],
        ]);
});




afterEach(function () {
    Mockery::close();
});
