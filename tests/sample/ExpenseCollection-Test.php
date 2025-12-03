<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

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

    // Use arrays instead of objects to avoid __get calls for missing properties
    $item1 = ['id' => 1, 'amount' => 100, 'expense_date' => '2023-01-01'];
    $item2 = ['id' => 2, 'amount' => 200, 'expense_date' => '2023-01-02'];
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
    $mockResource1->shouldReceive('jsonSerialize')
                  ->zeroOrMoreTimes()
                  ->andReturn(['expense_id' => 1, 'formatted_amount' => '100.00']); // For internal serialization
    $mockResource2 = Mockery::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->once()
                  ->with($request)
                  ->andReturn(['expense_id' => 2, 'formatted_amount' => '200.00']);
    $mockResource2->shouldReceive('jsonSerialize')
                  ->zeroOrMoreTimes()
                  ->andReturn(['expense_id' => 2, 'formatted_amount' => '200.00']); // For internal serialization

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

    // Separate mock objects per request, not reusing the same, to avoid exhausted Mockery expectations.
    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->once()->with($request1)
                  ->andReturn(['data' => 'for_request_1']);
    $mockResource1->shouldReceive('jsonSerialize')
                  ->zeroOrMoreTimes()
                  ->andReturn(['data' => 'for_request_1']); // For internal serialization

    $collection1 = Collection::make([$mockResource1]);
    $expenseCollection1 = new \Crater\Http\Resources\ExpenseCollection($collection1);
    $result1 = $expenseCollection1->toArray($request1);
    expect($result1)->toEqual([['data' => 'for_request_1']]);

    // New mock for request2
    $mockResource2 = Mockery::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->once()->with($request2)
                  ->andReturn(['data' => 'for_request_2']);
    $mockResource2->shouldReceive('jsonSerialize')
                  ->zeroOrMoreTimes()
                  ->andReturn(['data' => 'for_request_2']); // For internal serialization

    $collection2 = Collection::make([$mockResource2]);
    $expenseCollection2 = new \Crater\Http\Resources\ExpenseCollection($collection2);
    $result2 = $expenseCollection2->toArray($request2);
    expect($result2)->toEqual([['data' => 'for_request_2']]);
});

test('toArray handles collection with null items by passing them through', function () {
    $request = Mockery::mock(Request::class);
    $item1 = ['id' => 1, 'expense_date' => '2023-01-01'];
    $item2 = null; // Null item
    $item3 = ['id' => 3, 'expense_date' => '2023-01-03'];
    $collection = Collection::make([$item1, $item2, $item3]);

    $expenseCollection = new \Crater\Http\Resources\ExpenseCollection($collection);

    $result = $expenseCollection->toArray($request);

    // ResourceCollection's default behavior passes nulls through
    expect($result)->toBeArray()
        ->toHaveCount(3)
        ->toEqual([$item1, $item2, $item3]);
});

test('constructor correctly initializes the resource collection', function () {
    // According to the ExpenseCollection implementation, the resource items are transformed to ExpenseResource
    $items = [
        ['id' => 1, 'expense_date' => '2023-01-01'],
        ['id' => 2, 'expense_date' => '2023-01-02'],
    ];
    $collection = Collection::make($items);
    $expenseCollection = new \Crater\Http\Resources\ExpenseCollection($collection);

    expect($expenseCollection)->toBeInstanceOf(\Crater\Http\Resources\ExpenseCollection::class);
    // The resource property gets transformed into ExpenseResource instances
    expect($expenseCollection->resource)->toBeInstanceOf(Collection::class);

    $resources = $expenseCollection->resource;

    expect($resources)->toHaveCount(2);

    foreach ($resources as $key => $resource) {
        expect($resource)->toBeInstanceOf(\Crater\Http\Resources\ExpenseResource::class);
        expect($resource->resource)->toEqual($items[$key]);
    }
});

test('toArray handles different types of collection items correctly', function () {
    $request = Mockery::mock(Request::class);

    $simpleObject = ['key' => 'value', 'id' => 10, 'expense_date' => '2023-01-10'];
    $arrayItem = ['name' => 'array_item', 'id' => 20, 'expense_date' => '2023-01-20'];

    $mockResource = Mockery::mock(JsonResource::class);
    $mockResource->shouldReceive('toArray')
                 ->once()
                 ->with($request)
                 ->andReturn(['transformed_key' => 'transformed_value']);
    $mockResource->shouldReceive('jsonSerialize')
                 ->zeroOrMoreTimes()
                 ->andReturn(['transformed_key' => 'transformed_value']); // For internal serialization

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
            ['id' => 10, 'expense_date' => '2023-01-10', 'key' => 'value'],
            ['id' => 20, 'expense_date' => '2023-01-20', 'name' => 'array_item'],
            ['transformed_key' => 'transformed_value'],
        ]);
});

afterEach(function () {
    Mockery::close();
});