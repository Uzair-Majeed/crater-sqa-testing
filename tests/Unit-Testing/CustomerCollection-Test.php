<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Collection; // To simulate Eloquent collections
uses(\Mockery::class); // For mocking

// Set up for Mockery
beforeEach(function () {
    Mockery::close(); // Ensure no mocks from previous tests are lingering
});

afterEach(function () {
    Mockery::close(); // Clean up mocks after each test
});

test('toArray returns an empty array when initialized with an empty collection', function () {
    $request = Mockery::mock(Request::class);
    $collection = new Collection(); // Represents an empty Eloquent collection
    $customerCollection = new \Crater\Http\Resources\CustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

test('toArray correctly transforms a collection of simple objects via default JsonResource behavior', function () {
    $request = Mockery::mock(Request::class);

    // Mock two stdClass objects that represent database models.
    // When ResourceCollection internally calls JsonResource::make($item)->toArray($request),
    // JsonResource will then call ->toArray() on the $item itself if it's an object.
    $mockModel1 = Mockery::mock(stdClass::class);
    $mockModel1->id = 1;
    $mockModel1->name = 'Customer A';
    $mockModel1->shouldReceive('toArray')
               ->once()
               ->withNoArgs() // The default JsonResource behavior for underlying models
               ->andReturn(['id' => 1, 'name' => 'Customer A', 'status' => 'active']);

    $mockModel2 = Mockery::mock(stdClass::class);
    $mockModel2->id = 2;
    $mockModel2->name = 'Customer B';
    $mockModel2->shouldReceive('toArray')
               ->once()
               ->withNoArgs() // The default JsonResource behavior for underlying models
               ->andReturn(['id' => 2, 'name' => 'Customer B', 'status' => 'inactive']);

    $collection = new Collection([$mockModel1, $mockModel2]);

    $customerCollection = new \Crater\Http\Resources\CustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    $expected = [
        ['id' => 1, 'name' => 'Customer A', 'status' => 'active'],
        ['id' => 2, 'name' => 'Customer B', 'status' => 'inactive'],
    ];

    expect($result)->toBe($expected);
});

test('toArray correctly processes a collection of pre-existing JsonResource instances', function () {
    $request = Mockery::mock(Request::class);

    // Mock JsonResource instances themselves.
    // ResourceCollection will directly call toArray($request) on these.
    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->once()
                  ->with($request)
                  ->andReturn(['resource_id' => 101, 'resource_name' => 'Alpha Customer']);

    $mockResource2 = Mockery::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->once()
                  ->with($request)
                  ->andReturn(['resource_id' => 102, 'resource_name' => 'Beta Customer']);

    $collection = new Collection([$mockResource1, $mockResource2]);

    $customerCollection = new \Crater\Http\Resources\CustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    $expected = [
        ['resource_id' => 101, 'resource_name' => 'Alpha Customer'],
        ['resource_id' => 102, 'resource_name' => 'Beta Customer'],
    ];

    expect($result)->toBe($expected);
});

test('toArray correctly passes the request object to nested transformations when a custom resource type is used in parent', function () {
    // This scenario tests if `CustomerCollection` would correctly handle if its parent `ResourceCollection`
    // (or a custom intermediate parent) had a `collects` property set, leading to specific resource types.
    // Since `CustomerCollection` itself doesn't set `collects`, this test verifies its delegation works
    // even if the parent's resolution logic is more complex than simple `JsonResource::make`.

    // Create a mock resource that explicitly uses the request in its toArray method.
    $customRequest = Mockery::mock(Request::class);
    $customRequest->shouldReceive('has')->with('append_param')->andReturn(true);
    $customRequest->shouldReceive('get')->with('append_param')->andReturn('extra_data');

    $mockResource = Mockery::mock(JsonResource::class);
    $mockResource->shouldReceive('toArray')
                 ->once()
                 ->with($customRequest) // Crucially, expect the custom request
                 ->andReturnUsing(function ($request) {
                     $base = ['id' => 1, 'name' => 'Custom Customer'];
                     if ($request->has('append_param')) {
                         $base['appended'] = $request->get('append_param');
                     }
                     return $base;
                 });

    $collection = new Collection([$mockResource]);

    $customerCollection = new \Crater\Http\Resources\CustomerCollection($collection);

    $result = $customerCollection->toArray($customRequest);

    $expected = [
        ['id' => 1, 'name' => 'Custom Customer', 'appended' => 'extra_data'],
    ];

    expect($result)->toBe($expected);
});

test('toArray handles an empty but valid request object', function () {
    $emptyRequest = Mockery::mock(Request::class);
    $emptyRequest->shouldReceive('all')->andReturn([]); // Simulate an empty request

    $mockModel = Mockery::mock(stdClass::class);
    $mockModel->id = 3;
    $mockModel->name = 'Customer C';
    $mockModel->shouldReceive('toArray')->once()->withNoArgs()->andReturn(['id' => 3, 'name' => 'Customer C']);

    $collection = new Collection([$mockModel]);
    $customerCollection = new \Crater\Http\Resources\CustomerCollection($collection);

    $result = $customerCollection->toArray($emptyRequest);

    $expected = [
        ['id' => 3, 'name' => 'Customer C'],
    ];

    expect($result)->toBe($expected);
});

// No other public, protected, or private methods exist in CustomerCollection.
// The constructor is implicitly tested by successful instantiation in other tests.
