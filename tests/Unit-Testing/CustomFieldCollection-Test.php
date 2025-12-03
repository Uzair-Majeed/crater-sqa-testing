<?php

use Illuminate\Support\Collection;
use Crater\Http\Resources\CustomFieldCollection;

test('custom field collection can be instantiated', function () {
    $collection = new Collection([]);
    $resourceCollection = new CustomFieldCollection($collection);

    expect($resourceCollection)->toBeInstanceOf(CustomFieldCollection::class);
    expect($resourceCollection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('custom field collection toArray delegates to parent and transforms items', function () {
    // Arrange
    $mockRequest = Mockery::mock(\Illuminate\Http\Request::class);

    // Mock individual resources that would typically be within the collection
    $mockResource1 = Mockery::mock(\Illuminate\Http\Resources\Json\JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->once()
                  ->with($mockRequest)
                  ->andReturn(['id' => 1, 'name' => 'Custom Field One']);

    $mockResource2 = Mockery::mock(\Illuminate\Http\Resources\Json\JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->once()
                  ->with($mockRequest)
                  ->andReturn(['id' => 2, 'name' => 'Custom Field Two']);

    $items = new Collection([$mockResource1, $mockResource2]);

    $collection = new CustomFieldCollection($items);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)->toBeArray()
                   ->toHaveKey('data')
                   ->and($result['data'])->toEqual([
                        ['id' => 1, 'name' => 'Custom Field One'],
                        ['id' => 2, 'name' => 'Custom Field Two'],
                   ]);

    Mockery::close(); // Clean up Mockery expectations
});

test('custom field collection toArray returns empty data for an empty collection', function () {
    // Arrange
    $mockRequest = Mockery::mock(\Illuminate\Http\Request::class);
    $items = new Collection([]);

    $collection = new CustomFieldCollection($items);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)->toBeArray()
                   ->toHaveKey('data')
                   ->and($result['data'])->toEqual([]);

    Mockery::close();
});

test('custom field collection toArray handles non-resource items by including them directly', function () {
    // Arrange
    $mockRequest = Mockery::mock(\Illuminate\Http\Request::class);

    // ResourceCollection's parent toArray method includes non-resource items directly
    $items = new Collection([
        ['id' => 3, 'value' => 'Plain Array Item'],
        (object)['id' => 4, 'value' => 'StdClass Object Item'],
    ]);

    $collection = new CustomFieldCollection($items);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)->toBeArray()
                   ->toHaveKey('data')
                   ->and($result['data'])->toEqual([
                        ['id' => 3, 'value' => 'Plain Array Item'],
                        (object)['id' => 4, 'value' => 'StdClass Object Item'],
                   ]);

    Mockery::close();
});

 

afterEach(function () {
    Mockery::close();
});
