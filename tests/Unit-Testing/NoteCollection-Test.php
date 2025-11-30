<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection as BaseCollection;
uses(\Mockery::class);
use Crater\Http\Resources\NoteCollection;

beforeEach(function () {
    Mockery::close();
});

test('it transforms an empty collection correctly by delegating to parent', function () {
    $request = Mockery::mock(Request::class);
    // Minimal mocking for the request, as its actual content isn't critical for an empty collection.
    $request->shouldReceive('json')->andReturn(null)->byDefault();

    // NoteCollection simply extends ResourceCollection and doesn't define its own $collects property.
    // ResourceCollection will then iterate over items. If items are already JsonResource instances,
    // it calls toArray on them directly. If not, it wraps them in a default JsonResource.
    // For white-box testing NoteCollection's delegation, we'll provide mock JsonResource instances.
    $noteCollection = new NoteCollection(BaseCollection::make([]));

    $result = $noteCollection->toArray($request);

    expect($result)->toBeArray();
    expect($result)->toBeEmpty();
});

test('it transforms a collection with a single item correctly by delegating to parent', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('json')->andReturn(null)->byDefault();

    // Create a mock JsonResource instance for the single item
    // This simulates an item that has already been converted into a resource object.
    $mockResourceInstance = Mockery::mock(JsonResource::class);
    $mockResourceInstance->shouldReceive('toArray')
                         ->with($request)
                         ->andReturn(['transformed_id' => 1, 'transformed_name' => 'FIRST NOTE'])
                         ->once(); // Expect toArray to be called once with the request

    // Create the NoteCollection with our mock resource instance
    $noteCollection = new NoteCollection(BaseCollection::make([$mockResourceInstance]));

    $result = $noteCollection->toArray($request);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toMatchArray([
        'transformed_id' => 1,
        'transformed_name' => 'FIRST NOTE',
    ]);
});

test('it transforms a collection with multiple items correctly by delegating to parent', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('json')->andReturn(null)->byDefault();

    // Create multiple mock JsonResource instances
    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->with($request)
                  ->andReturn(['transformed_id' => 10, 'transformed_name' => 'ALPHA'])
                  ->once();

    $mockResource2 = Mockery::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->with($request)
                  ->andReturn(['transformed_id' => 20, 'transformed_name' => 'BETA'])
                  ->once();

    $mockResource3 = Mockery::mock(JsonResource::class);
    $mockResource3->shouldReceive('toArray')
                  ->with($request)
                  ->andReturn(['transformed_id' => 30, 'transformed_name' => 'GAMMA'])
                  ->once();

    // Create the NoteCollection with our mock resource instances
    $noteCollection = new NoteCollection(BaseCollection::make([
        $mockResource1,
        $mockResource2,
        $mockResource3,
    ]));

    $result = $noteCollection->toArray($request);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(3);
    expect($result[0])->toMatchArray(['transformed_id' => 10, 'transformed_name' => 'ALPHA']);
    expect($result[1])->toMatchArray(['transformed_id' => 20, 'transformed_name' => 'BETA']);
    expect($result[2])->toMatchArray(['transformed_id' => 30, 'transformed_name' => 'GAMMA']);
});

test('it ensures the request instance is passed to each underlying resource transformation', function () {
    $mockRequest = Mockery::mock(Request::class);
    // Configure the mock request to return a specific value when 'some_param' is requested
    $mockRequest->shouldReceive('get')->with('some_param')->andReturn('request_specific_value')->once();
    $mockRequest->shouldReceive('json')->andReturn(null)->byDefault(); // Default for other calls

    // Create a mock JsonResource that will internally verify the request instance
    $mockResource = Mockery::mock(JsonResource::class);
    $mockResource->shouldReceive('toArray')
                  ->withArgs(function ($request) use ($mockRequest) {
                      // Assert that the request passed to toArray is the exact mock instance
                      expect($request)->toBe($mockRequest);
                      // Further assert that the resource can correctly interact with the request
                      return $request->get('some_param') === 'request_specific_value';
                  })
                  ->andReturn(['item_id' => 100, 'param_used' => 'request_specific_value'])
                  ->once();

    $noteCollection = new NoteCollection(BaseCollection::make([$mockResource]));

    $result = $noteCollection->toArray($mockRequest);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toMatchArray([
        'item_id' => 100,
        'param_used' => 'request_specific_value',
    ]);
});
