<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Crater\Http\Resources\EstimateCollection;
use Crater\Http\Resources\EstimateResource;
use Mockery\MockInterface;

it('transforms the resource collection into an array by delegating to its parent `ResourceCollection`', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);

    // Create fake resource data (as models/arrays)
    $estimate1 = (object) ['id' => 1, 'name' => 'Estimate 1', 'amount' => 100];
    $estimate2 = (object) ['id' => 2, 'name' => 'Estimate 2', 'amount' => 200];

    // Wrap fake resource data in EstimateResource
    $resource1 = new EstimateResource($estimate1);
    $resource2 = new EstimateResource($estimate2);

    $resources = new Collection([$resource1, $resource2]);
    $collection = new EstimateCollection($resources);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([
            ['id' => 1, 'name' => 'Estimate 1', 'amount' => 100],
            ['id' => 2, 'name' => 'Estimate 2', 'amount' => 200],
        ]);
});

it('handles an empty resource collection gracefully, returning an empty array', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);

    // An empty collection of resources.
    $resources = new Collection([]);

    // Instantiate the EstimateCollection with an empty collection.
    $collection = new EstimateCollection($resources);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)->toBeArray()->toBeEmpty();
});

it('handles a collection with a single resource correctly', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);

    // Fake estimate resource
    $estimate = (object) ['id' => 3, 'name' => 'Single Estimate', 'amount' => 300];

    // Wrap in EstimateResource
    $resource = new EstimateResource($estimate);

    // Create a collection with the single resource
    $resources = new Collection([$resource]);

    // Instantiate the EstimateCollection.
    $collection = new EstimateCollection($resources);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveCount(1)
        ->toEqual([
            ['id' => 3, 'name' => 'Single Estimate', 'amount' => 300],
        ]);
});

afterEach(function () {
    Mockery::close();
});