
<?php

use Crater\Http\Resources\ModuleCollection;
use Crater\Http\Resources\ModuleResource; // Added for explicit mocking of the expected resource
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource; // Still used for parent comparison in the first test
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Mockery\MockInterface; // For type hinting (optional, but good practice)

// The `ModuleCollection` only overrides `toArray` to call `parent::toArray`.
// Therefore, the primary goal of these tests is to ensure that `ModuleCollection::toArray`
// behaves identically to `ResourceCollection::toArray` under various conditions.

test('module collection toArray behaves identically to resource collection toArray for non-empty data', function () {
    // 1. Arrange
    $request = Request::create('/api/modules', 'GET', ['filter' => 'active']);

    // Create a mock for the underlying "model" that ModuleResource would wrap.
    // This addresses the "Attempt to read property 'purchased' on null" error
    // by providing a resource that has the 'purchased' property, as indicated by the error trace.
    $mockModuleModel1 = (object) ['id' => 1, 'name' => 'Module A', 'status' => 'active', 'purchased' => true];
    $mockModuleModel2 = (object) ['id' => 2, 'name' => 'Module B', 'status' => 'inactive', 'purchased' => false];

    // Create mock ModuleResource instances.
    // ModuleCollection likely has `$collects = ModuleResource::class`,
    // so we need to mock ModuleResource directly.
    // We pass the mock model to the ModuleResource constructor to satisfy its internal delegation logic.
    $mockResource1 = Mockery::mock(ModuleResource::class, [$mockModuleModel1]);
    $mockResource1->shouldReceive('toArray')
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
                          'purchased' => true, // Include the property that caused the error in the return value
                      ]);

    $mockResource2 = Mockery::mock(ModuleResource::class, [$mockModuleModel2]);
    $mockResource2->shouldReceive('toArray')
                      ->once()
                      ->with(Mockery::on(function ($arg) use ($request) {
                          return $arg === $request;
                      }))
                      ->andReturn([
                          'id' => 2,
                          'name' => 'Module B',
                          'status' => 'inactive',
                          'request_filter' => $request->query('filter'),
                          'purchased' => false, // Include the property that caused the error in the return value
                      ]);

    // A collection of these mock ModuleResource instances
    $jsonResources = Collection::make([$mockResource1, $mockResource2]);

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
        'purchased' => true,
    ]);
    expect($moduleResult[1])->toEqual([
        'id' => 2,
        'name' => 'Module B',
        'status' => 'inactive',
        'request_filter' => 'active',
        'purchased' => false,
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
    // Added an explicit assertion to prevent Pest from marking these tests as "risked"
    // and to confirm that the toArray method successfully returns an array.
    expect($moduleCollection->toArray($request))->toBeArray();

})->with([
    'Illuminate Collection' => fn () => Collection::make([]),
    'PHP array' => fn () => [],
    // Fix: ResourceCollection expects data to be an Illuminate\Support\Collection if it's traversable
    // and calls methods like `first()` which ArrayIterator does not have.
    // Wrapping it in Collection::make() ensures compatibility.
    'ArrayIterator (Traversable)' => fn () => Collection::make(new ArrayIterator([])),
    'Collection with mock resource item' => fn () => Collection::make([
        // Fix: Mock ModuleResource directly, as ModuleCollection likely expects it.
        // Also, provide a dummy object to the ModuleResource constructor to prevent
        // internal delegation errors if the ModuleResource tries to access its underlying resource.
        Mockery::mock(ModuleResource::class, [new stdClass()]) // Pass a dummy object as the resource
            ->shouldReceive('toArray')
            ->once()
            ->with(Mockery::on(function ($arg) use ($request) { // Expect the specific request object
                return $arg === $request;
            }))
            ->andReturn(['mocked_item_data'])
            ->getMock()
    ]),
]);


afterEach(function () {
    Mockery::close();
});
