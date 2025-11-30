<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Crater\Http\Resources\TransactionCollection;
uses(\Mockery::class);

test('toArray delegates to parent and transforms an empty collection correctly', function () {
    $request = Mockery::mock(Request::class);
    $collection = new Collection([]); // Empty collection

    $transactionCollection = new TransactionCollection($collection);

    $result = $transactionCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toBeEmpty();
    Mockery::close();
});

test('toArray delegates to parent and transforms a collection of resources correctly', function () {
    $request = Mockery::mock(Request::class);

    // Mock individual resources within the collection
    $resource1Data = ['id' => 1, 'amount' => 100, 'status' => 'paid'];
    $resource2Data = ['id' => 2, 'amount' => 200, 'status' => 'pending'];

    $resource1 = Mockery::mock(JsonResource::class);
    $resource1->shouldReceive('toArray')
              ->with($request)
              ->once()
              ->andReturn($resource1Data);

    $resource2 = Mockery::mock(JsonResource::class);
    $resource2->shouldReceive('toArray')
              ->with($request)
              ->once()
              ->andReturn($resource2Data);

    $collection = new Collection([$resource1, $resource2]);

    // Instantiate the TransactionCollection with our mocked resources
    $transactionCollection = new TransactionCollection($collection);

    $result = $transactionCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toEqual([$resource1Data, $resource2Data]);

    Mockery::close(); // Verify mocks and clean up
});

test('toArray handles a collection with non-resource items as expected by parent', function () {
    $request = Mockery::mock(Request::class);

    // ResourceCollection should just include non-resource items as they are if they are not Resource instances
    $item1 = ['foo' => 'bar', 'type' => 'data'];
    $item2 = (object) ['baz' => 'qux', 'value' => 123];

    $collection = new Collection([$item1, $item2]);

    $transactionCollection = new TransactionCollection($collection);

    $result = $transactionCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toEqual([$item1, $item2]);
    Mockery::close();
});

test('toArray handles an empty request object correctly when transforming resources', function () {
    $request = Mockery::mock(Request::class);
    // Even an "empty" request object should still be passed to resource->toArray()
    // We can simulate an empty request by not setting any specific expectations for its methods
    // unless they are explicitly called by the resource.

    $resourceData = ['id' => 1, 'name' => 'Test Transaction', 'total' => 500];
    $resource = Mockery::mock(JsonResource::class);
    $resource->shouldReceive('toArray')
             ->with($request) // Expect it to be called with the (empty) mock request
             ->once()
             ->andReturn($resourceData);

    $collection = new Collection([$resource]);
    $transactionCollection = new TransactionCollection($collection);

    $result = $transactionCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toEqual([$resourceData]);
    Mockery::close();
});

test('toArray handles a collection with a single item', function () {
    $request = Mockery::mock(Request::class);

    $singleResourceData = ['id' => 10, 'description' => 'Single item transaction'];
    $singleResource = Mockery::mock(JsonResource::class);
    $singleResource->shouldReceive('toArray')
                   ->with($request)
                   ->once()
                   ->andReturn($singleResourceData);

    $collection = new Collection([$singleResource]);
    $transactionCollection = new TransactionCollection($collection);

    $result = $transactionCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toEqual([$singleResourceData]);
    Mockery::close();
});
