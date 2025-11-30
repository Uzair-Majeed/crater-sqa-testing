<?php

use Crater\Http\Resources\ModuleCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
uses(\Mockery::class);

// The `ModuleCollection` only overrides `toArray` to call `parent::toArray`.
// Therefore, the primary goal of these tests is to ensure that `ModuleCollection::toArray`
// behaves identically to `ResourceCollection::toArray` under various conditions.

test('module collection toArray behaves identically to resource collection toArray for non-empty data', function () {
    // 1. Arrange
    $request = Request::create('/api/modules', 'GET', ['filter' => 'active']);

    // Create mock JsonResource instances that will return predictable data
    // when their `toArray` method is called. These simulate the individual
    // resources that the collection will iterate over.
    $mockJsonResource1 = Mockery::mock(JsonResource::class);
    $mockJsonResource1->shouldReceive('toArray')
                      ->once()
                      ->with(Mockery::on(function ($arg) use ($request) {
                          // Verify that the exact Request instance is passed
                          return $arg === $request;
                      }))
                      ->andReturn([
                          'id' => 1,
                          'name' => 'Module A',
                          'status' => 'active',
                          'request_filter' => $request->query('filter'),
                      ]);

    $mockJsonResource2 = Mockery::mock(JsonResource::class);
    $mockJsonResource2->shouldReceive('toArray')
                      ->once()
                      ->with(Mockery::on(function ($arg) use ($request) {
                          return $arg === $request;
                      }))
                      ->andReturn([
                          'id' => 2,
                          'name' => 'Module B',
                          'status' => 'inactive',
                          'request_filter' => $request->query('filter'),
                      ]);

    // A collection of these mock JsonResource instances
    $jsonResources = Collection::make([$mockJsonResource1, $mockJsonResource2]);

    // 2. Act: Instantiate `ModuleCollection` and call its `toArray` method
    $moduleCollection = new ModuleCollection($jsonResources);
    $moduleResult = $moduleCollection->toArray($request);

    // 3. Act: Instantiate the parent `ResourceCollection` with the exact same data
    //         and call its `toArray` method for comparison
    $resourceCollection = new ResourceCollection($jsonResources);
    $parentResult = $resourceCollection->toArray($request);

    // 4. Assert: The results from both collection types should be identical
    expect($moduleResult)->toEqual($parentResult);
    expect($moduleResult)->toBeArray()
                         ->toHaveCount(2);
    expect($moduleResult[0])->toEqual([
        'id' => 1,
        'name' => 'Module A',
        'status' => 'active',
        'request_filter' => 'active',
    ]);
    expect($moduleResult[1])->toEqual([
        'id' => 2,
        'name' => 'Module B',
        'status' => 'inactive',
        'request_filter' => 'active',
    ]);
});

test('module collection toArray behaves identically to resource collection toArray for empty data', function () {
    // 1. Arrange
    $request = Request::create('/api/modules');
    $emptyJsonResources = Collection::make([]);

    // 2. Act: Instantiate `ModuleCollection` with empty data and call `toArray`
    $moduleCollection = new ModuleCollection($emptyJsonResources);
    $moduleResult = $moduleCollection->toArray($request);

    // 3. Act: Instantiate parent `ResourceCollection` with empty data and call `toArray`
    $resourceCollection = new ResourceCollection($emptyJsonResources);
    $parentResult = $resourceCollection->toArray($request);

    // 4. Assert: Results should be identical and empty arrays
    expect($moduleResult)->toEqual($parentResult);
    expect($moduleResult)->toBeArray()
                         ->toBeEmpty();
});

test('module collection constructor accepts various traversable collections', function ($collection) {
    // This test ensures that the constructor of `ModuleCollection` (which delegates
    // to `ResourceCollection`) correctly handles different types of traversable data
    // for its internal collection. We expect it not to throw an error on instantiation
    // or when `toArray` is called (assuming items are valid for `ResourceCollection`).
    $request = Request::create('/');

    $moduleCollection = new ModuleCollection($collection);

    // The actual transformation logic resides in `parent::toArray` and the
    // `toArray` methods of the individual resource items.
    // If the items are valid (e.g., objects with a `toArray` method), it should not throw.
    expect(fn() => $moduleCollection->toArray($request))->not->toThrow(TypeError::class);

})->with([
    'Illuminate Collection' => fn () => Collection::make([]),
    'PHP array' => fn () => [],
    'ArrayIterator (Traversable)' => fn () => new ArrayIterator([]),
    'Collection with mock resource item' => fn () => Collection::make([
        Mockery::mock(JsonResource::class)
            ->shouldReceive('toArray')
            ->once()
            ->andReturn(['mocked_item_data'])
            ->getMock()
    ]),
]);

// Clean up Mockery expectations after each test to prevent interfering with subsequent tests.
afterEach(function () {
    Mockery::close();
});
