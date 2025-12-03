<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Crater\Http\Resources\TransactionCollection;

// This helper class simulates an Eloquent model or a stdClass object
// that `Crater\Http\Resources\TransactionResource` (which `TransactionCollection`
// likely collects) expects to receive in its constructor.
// The errors in the debug output indicate `TransactionResource` attempts to
// access properties like 'id' on its underlying resource.
class TestTransactionModel
{
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->{$key} = $value;
        }
    }
}

// Ensure Mockery is closed after each test to prevent mock expectation leaks.
afterEach(function () {
    Mockery::close();
});

test('toArray delegates to parent and transforms an empty collection correctly', function () {
    $request = Mockery::mock(Request::class);
    $collection = new Collection([]); // Empty collection

    $transactionCollection = new TransactionCollection($collection);

    $result = $transactionCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toBeEmpty();
});

test('toArray delegates to parent and transforms a collection of resources correctly', function () {
    $request = Mockery::mock(Request::class);

    $resource1Data = ['id' => 1, 'amount' => 100, 'status' => 'paid'];
    $resource2Data = ['id' => 2, 'amount' => 200, 'status' => 'pending'];

    // Instead of mocking JsonResource directly, we provide underlying data (models)
    // that `TransactionCollection` will wrap into `TransactionResource` instances.
    // The `TransactionResource` itself will then be responsible for transforming these models.
    $model1 = new TestTransactionModel($resource1Data);
    $model2 = new TestTransactionModel($resource2Data);

    $collection = new Collection([$model1, $model2]);

    $transactionCollection = new TransactionCollection($collection);

    $result = $transactionCollection->toArray($request);

    // Assuming `TransactionResource::toArray()`, when applied to `TestTransactionModel`
    // instances, simply returns the data provided in the constructor.
    expect($result)->toBeArray()
                   ->toEqual([$resource1Data, $resource2Data]);
});

test('toArray handles a collection with non-resource items as expected by parent', function () {
    $request = Mockery::mock(Request::class);

    // The debug output indicated `TransactionResource` was attempting to read
    // property "id" on an array or a generic object without it.
    // This implies that `TransactionCollection` (as a `ResourceCollection`)
    // with a `$collects` property, wraps all non-resource items into the
    // specified resource (`TransactionResource`).
    // To fix this, we provide items that are compatible with `TransactionResource`'s
    // expectation of having an 'id' property, while still representing generic data
    // that `TransactionCollection` would process.
    $item1Data = ['id' => 100, 'foo' => 'bar', 'type' => 'data'];
    $item2Data = ['id' => 101, 'baz' => 'qux', 'value' => 123];

    // Wrap these data arrays into our TestTransactionModel to provide an object with 'id'.
    $item1 = new TestTransactionModel($item1Data);
    $item2 = new TestTransactionModel($item2Data);

    $collection = new Collection([$item1, $item2]);

    $transactionCollection = new TransactionCollection($collection);

    $result = $transactionCollection->toArray($request);

    // The expectation should now match the data after `TransactionResource` has processed it.
    expect($result)->toBeArray()
                   ->toEqual([$item1Data, $item2Data]);
});

test('toArray handles an empty request object correctly when transforming resources', function () {
    $request = Mockery::mock(Request::class);

    $resourceData = ['id' => 1, 'name' => 'Test Transaction', 'total' => 500];
    // Provide a compatible underlying model for `TransactionResource`.
    $model = new TestTransactionModel($resourceData);

    $collection = new Collection([$model]);
    $transactionCollection = new TransactionCollection($collection);

    $result = $transactionCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toEqual([$resourceData]);
});

test('toArray handles a collection with a single item', function () {
    $request = Mockery::mock(Request::class);

    $singleResourceData = ['id' => 10, 'description' => 'Single item transaction'];
    // Provide a compatible underlying model for `TransactionResource`.
    $singleModel = new TestTransactionModel($singleResourceData);

    $collection = new Collection([$singleModel]);
    $transactionCollection = new TransactionCollection($collection);

    $result = $transactionCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toEqual([$singleResourceData]);
});