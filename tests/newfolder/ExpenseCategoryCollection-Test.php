<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Crater\Http\Resources\ExpenseCategoryCollection;
use Mockery;

test('toArray correctly transforms a collection of resources', function () {
    // 1. Mock Request
    $request = Mockery::mock(Request::class);

    // 2. Create dummy data items (what a real Eloquent model might look like)
    $item1 = (object) ['id' => 1, 'name' => 'Travel', 'description' => 'Business travel expenses'];
    $item2 = (object) ['id' => 2, 'name' => 'Supplies', 'description' => 'Office supplies'];

    // 3. Create mock resource instances for the collection.
    // ResourceCollection expects its items to be instances of JsonResource or a child.
    // We'll mock the toArray method of these resources.
    $mockResource1 = Mockery::mock(JsonResource::class);
    $createdAt1 = now()->subDay()->toJson();
    $updatedAt1 = now()->toJson();
    $mockResource1->shouldReceive('toArray')
                  ->twice()
                  ->with($request)
                  ->andReturn([
                      'id' => 1,
                      'name' => 'Travel',
                      'description' => 'Business travel expenses',
                      // Include other typical resource fields if they were real
                      'created_at' => $createdAt1,
                      'updated_at' => $updatedAt1,
                  ]);

    $mockResource2 = Mockery::mock(JsonResource::class);
    $createdAt2 = now()->subWeek()->toJson();
    $updatedAt2 = now()->subDay()->toJson();
    $mockResource2->shouldReceive('toArray')
                  ->twice()
                  ->with($request)
                  ->andReturn([
                      'id' => 2,
                      'name' => 'Supplies',
                      'description' => 'Office supplies',
                      'created_at' => $createdAt2,
                      'updated_at' => $updatedAt2,
                  ]);

    // 4. Create an Illuminate Collection of these mock resources
    $resourceCollection = new Collection([$mockResource1, $mockResource2]);

    // 5. Instantiate the class under test with the collection
    $collection = new ExpenseCategoryCollection($resourceCollection);

    // 6. Call the toArray method
    $result = $collection->toArray($request);

    // 7. Assertions
    expect($result)->toBeArray()
        ->toHaveCount(2);

    expect($result[0])->toBeArray()
        ->toEqual([
            'id' => 1,
            'name' => 'Travel',
            'description' => 'Business travel expenses',
            'created_at' => $createdAt1,
            'updated_at' => $updatedAt1,
        ]);

    expect($result[1])->toBeArray()
        ->toEqual([
            'id' => 2,
            'name' => 'Supplies',
            'description' => 'Office supplies',
            'created_at' => $createdAt2,
            'updated_at' => $updatedAt2,
        ]);
});

test('toArray returns an empty array for an empty collection', function () {
    // Mock Request
    $request = Mockery::mock(Request::class);

    // Create an empty Illuminate Collection
    $emptyCollection = new Collection([]);

    // Instantiate the class under test with the empty collection
    $collection = new ExpenseCategoryCollection($emptyCollection);

    // Call the toArray method
    $result = $collection->toArray($request);

    // Assertions
    expect($result)->toBeArray()
        ->toBeEmpty();
});

test('toArray handles a collection with a single resource', function () {
    $request = Mockery::mock(Request::class);

    $mockResource = Mockery::mock(JsonResource::class);
    $createdAt = now()->subMonth()->toJson();
    $updatedAt = now()->toJson();
    $mockResource->shouldReceive('toArray')
                 ->twice()
                 ->with($request)
                 ->andReturn([
                     'id' => 3,
                     'name' => 'Utilities',
                     'description' => 'Monthly utility bills',
                     'created_at' => $createdAt,
                     'updated_at' => $updatedAt,
                 ]);

    $singleResourceCollection = new Collection([$mockResource]);

    $collection = new ExpenseCategoryCollection($singleResourceCollection);

    $result = $collection->toArray($request);

    expect($result)->toBeArray()
        ->toHaveCount(1);

    expect($result[0])->toBeArray()
        ->toEqual([
            'id' => 3,
            'name' => 'Utilities',
            'description' => 'Monthly utility bills',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
});

test('toArray method does not add extra data beyond parent ResourceCollection', function () {
    // This test ensures that the overridden toArray method doesn't inadvertently add
    // additional data or modify the structure beyond what parent::toArray would do.
    $request = Mockery::mock(Request::class);

    $mockResource = Mockery::mock(JsonResource::class);
    $mockResource->shouldReceive('toArray')
                 ->twice()
                 ->with($request)
                 ->andReturn([
                     'id' => 4,
                     'name' => 'Marketing',
                 ]);

    $resourceCollection = new Collection([$mockResource]);

    $collection = new ExpenseCategoryCollection($resourceCollection);

    $result = $collection->toArray($request);

    // We expect the result to be exactly what the mock resource returned, wrapped in an array.
    expect($result)->toBeArray()
        ->toHaveCount(1);

    expect($result[0])->toBeArray()
        ->toEqual([
            'id' => 4,
            'name' => 'Marketing',
        ]);
});


afterEach(function () {
    Mockery::close();
});