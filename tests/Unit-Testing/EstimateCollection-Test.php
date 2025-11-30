<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Crater\Http\Resources\EstimateCollection;
use Mockery\MockInterface;

it('transforms the resource collection into an array by delegating to its parent `ResourceCollection`', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);

    // Create mock resources that would typically be contained within the collection.
    // These mocks simulate the behavior of individual JsonResource items.
    $mockResource1 = Mockery::mock(JsonResource::class, function (MockInterface $mock) use ($mockRequest) {
        $mock->shouldReceive('toArray')
            ->once()
            ->with($mockRequest)
            ->andReturn(['id' => 1, 'name' => 'Estimate 1', 'amount' => 100]);
    });

    $mockResource2 = Mockery::mock(JsonResource::class, function (MockInterface $mock) use ($mockRequest) {
        $mock->shouldReceive('toArray')
            ->once()
            ->with($mockRequest)
            ->andReturn(['id' => 2, 'name' => 'Estimate 2', 'amount' => 200]);
    });

    // Create the underlying collection of resources that EstimateCollection will wrap.
    $resources = new Collection([$mockResource1, $mockResource2]);

    // Instantiate the EstimateCollection with the prepared resources.
    $collection = new EstimateCollection($resources);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    // Verify that the EstimateCollection's toArray method correctly processed
    // the underlying resources by delegating to its parent, which in turn
    // calls toArray on each individual resource.
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
    // Expect an empty array when the underlying resource collection is empty.
    expect($result)->toBeArray()->toBeEmpty();
});

it('handles a collection with a single resource correctly', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);

    // Create a single mock resource.
    $mockResource = Mockery::mock(JsonResource::class, function (MockInterface $mock) use ($mockRequest) {
        $mock->shouldReceive('toArray')
            ->once()
            ->with($mockRequest)
            ->andReturn(['id' => 3, 'name' => 'Single Estimate', 'amount' => 300]);
    });

    // Create a collection with the single mock resource.
    $resources = new Collection([$mockResource]);

    // Instantiate the EstimateCollection.
    $collection = new EstimateCollection($resources);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    // Expect an array containing the single transformed resource.
    expect($result)
        ->toBeArray()
        ->toHaveCount(1)
        ->toEqual([
            ['id' => 3, 'name' => 'Single Estimate', 'amount' => 300],
        ]);
});
