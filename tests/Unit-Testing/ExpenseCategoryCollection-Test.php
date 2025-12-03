<?php

test('toArray correctly transforms a collection of resources', function () {
    // 1. Mock Request
    $request = mock(\Illuminate\Http\Request::class);

    // 2. Create dummy data items (what a real Eloquent model might look like)
    $item1 = (object) ['id' => 1, 'name' => 'Travel', 'description' => 'Business travel expenses'];
    $item2 = (object) ['id' => 2, 'name' => 'Supplies', 'description' => 'Office supplies'];

    // 3. Create mock resource instances for the collection.
    // ResourceCollection expects its items to be instances of JsonResource or a child.
    // We'll mock the toArray method of these resources.
    $mockResource1 = mock(\Illuminate\Http\Resources\Json\JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->once()
                  ->with($request)
                  ->andReturn([
                      'id' => 1,
                      'name' => 'Travel',
                      'description' => 'Business travel expenses',
                      // Include other typical resource fields if they were real
                      'created_at' => now()->subDay()->toJson(),
                      'updated_at' => now()->toJson(),
                  ]);

    $mockResource2 = mock(\Illuminate\Http\Resources\Json\JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->once()
                  ->with($request)
                  ->andReturn([
                      'id' => 2,
                      'name' => 'Supplies',
                      'description' => 'Office supplies',
                      'created_at' => now()->subWeek()->toJson(),
                      'updated_at' => now()->subDay()->toJson(),
                  ]);

    // 4. Create an Illuminate Collection of these mock resources
    $resourceCollection = new \Illuminate\Support\Collection([$mockResource1, $mockResource2]);

    // 5. Instantiate the class under test with the collection
    $collection = new \Crater\Http\Resources\ExpenseCategoryCollection($resourceCollection);

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
            'created_at' => $mockResource1->toArray($request)['created_at'],
            'updated_at' => $mockResource1->toArray($request)['updated_at'],
        ]);

    expect($result[1])->toBeArray()
        ->toEqual([
            'id' => 2,
            'name' => 'Supplies',
            'description' => 'Office supplies',
            'created_at' => $mockResource2->toArray($request)['created_at'],
            'updated_at' => $mockResource2->toArray($request)['updated_at'],
        ]);
});

test('toArray returns an empty array for an empty collection', function () {
    // Mock Request
    $request = mock(\Illuminate\Http\Request::class);

    // Create an empty Illuminate Collection
    $emptyCollection = new \Illuminate\Support\Collection([]);

    // Instantiate the class under test with the empty collection
    $collection = new \Crater\Http\Resources\ExpenseCategoryCollection($emptyCollection);

    // Call the toArray method
    $result = $collection->toArray($request);

    // Assertions
    expect($result)->toBeArray()
        ->toBeEmpty();
});

test('toArray handles a collection with a single resource', function () {
    $request = mock(\Illuminate\Http\Request::class);

    $mockResource = mock(\Illuminate\Http\Resources\Json\JsonResource::class);
    $mockResource->shouldReceive('toArray')
                 ->once()
                 ->with($request)
                 ->andReturn([
                     'id' => 3,
                     'name' => 'Utilities',
                     'description' => 'Monthly utility bills',
                     'created_at' => now()->subMonth()->toJson(),
                     'updated_at' => now()->toJson(),
                 ]);

    $singleResourceCollection = new \Illuminate\Support\Collection([$mockResource]);

    $collection = new \Crater\Http\Resources\ExpenseCategoryCollection($singleResourceCollection);

    $result = $collection->toArray($request);

    expect($result)->toBeArray()
        ->toHaveCount(1);

    expect($result[0])->toBeArray()
        ->toEqual([
            'id' => 3,
            'name' => 'Utilities',
            'description' => 'Monthly utility bills',
            'created_at' => $mockResource->toArray($request)['created_at'],
            'updated_at' => $mockResource->toArray($request)['updated_at'],
        ]);
});

test('toArray method does not add extra data beyond parent ResourceCollection', function () {
    // This test ensures that the overridden toArray method doesn't inadvertently add
    // additional data or modify the structure beyond what parent::toArray would do.
    $request = mock(\Illuminate\Http\Request::class);

    $mockResource = mock(\Illuminate\Http\Resources\Json\JsonResource::class);
    $mockResource->shouldReceive('toArray')
                 ->once()
                 ->with($request)
                 ->andReturn([
                     'id' => 4,
                     'name' => 'Marketing',
                 ]);

    $resourceCollection = new \Illuminate\Support\Collection([$mockResource]);

    $collection = new \Crater\Http\Resources\ExpenseCategoryCollection($resourceCollection);

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
