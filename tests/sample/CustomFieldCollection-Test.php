```php
<?php

use Illuminate\Support\Collection;
use Crater\Http\Resources\CustomFieldCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

test('custom field collection can be instantiated', function () {
    $collection = new Collection([]);
    $resourceCollection = new CustomFieldCollection($collection);

    expect($resourceCollection)->toBeInstanceOf(CustomFieldCollection::class);
    expect($resourceCollection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('custom field collection toArray delegates to parent and transforms items', function () {
    // Arrange
    $mockRequest = new Request();

    // Mock individual resources that would typically be within the collection
    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->once()
                  ->with($mockRequest)
                  ->andReturn(['id' => 1, 'name' => 'Custom Field One']);

    $mockResource2 = Mockery::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->once()
                  ->with($mockRequest)
                  ->andReturn(['id' => 2, 'name' => 'Custom Field Two']);

    // For the Laravel resource collection to work, items must be instances of JsonResource or model objects.
    // The CustomFieldCollection tries to wrap each item in CustomFieldResource, so we need to ensure this works.
    // Therefore, we mock the resource collection by overriding collection property directly.
    $collection = new CustomFieldCollection(new Collection());
    $collection->collection = new Collection([$mockResource1, $mockResource2]);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)->toBeArray()
                   ->toHaveKey('data')
                   ->and($result['data'])->toEqual([
                        ['id' => 1, 'name' => 'Custom Field One'],
                        ['id' => 2, 'name' => 'Custom Field Two'],
                   ]);
});

test('custom field collection toArray returns empty data for an empty collection', function () {
    // Arrange
    $mockRequest = new Request();
    $collection = new CustomFieldCollection(new Collection([]));

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)->toBeArray()
                   ->toHaveKey('data')
                   ->and($result['data'])->toEqual([]);
});

test('custom field collection toArray handles non-resource items by including them directly', function () {
    // Arrange
    $mockRequest = new Request();
    $items = new Collection([
        // These plain items will be wrapped in CustomFieldResource by the collection
        ['id' => 3, 'value' => 'Plain Array Item'],
        (object)['id' => 4, 'value' => 'StdClass Object Item'],
    ]);

    $collection = new CustomFieldCollection($items);

    // Act
    $result = $collection->toArray($mockRequest);

    // For coverage, since collection items are always wrapped in CustomFieldResource, 
    // let's ensure that the output is arrays with id/value properties same as input.
    expect($result)->toBeArray()
                   ->toHaveKey('data')
                   ->and($result['data'][0]['id'])->toEqual(3)
                   ->and($result['data'][0]['value'])->toEqual('Plain Array Item')
                   ->and($result['data'][1]['id'])->toEqual(4)
                   ->and($result['data'][1]['value'])->toEqual('StdClass Object Item');
});

afterEach(function () {
    Mockery::close();
});
```