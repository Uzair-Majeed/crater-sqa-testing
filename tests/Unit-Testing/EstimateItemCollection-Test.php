<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Crater\Http\Resources\EstimateItemCollection;

/**
 * A mock resource class used to test the transformation logic of EstimateItemCollection.
 */
class MockEstimateItemResource extends JsonResource
{
    public function toArray($request) // <- no type hint
    {
        return array_merge($this->resource, ['transformed_by_mock' => true]);
    }
}


test('toArray returns an empty array when the underlying collection is empty', function () {
    $request = Request::create('/');
    $collection = Collection::make([]);

    // Create the collection resource instance, specifying our mock resource type
    $estimateItemCollection = EstimateItemCollection::make($collection, MockEstimateItemResource::class);

    $result = $estimateItemCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

test('toArray correctly transforms a collection with a single item', function () {
    $request = Request::create('/');
    $itemData = ['id' => 1, 'name' => 'Test Item 1', 'price' => 100];
    $collection = Collection::make([$itemData]);

    $estimateItemCollection = EstimateItemCollection::make($collection, MockEstimateItemResource::class);

    $result = $estimateItemCollection->toArray($request);

    $expectedTransformedItem = array_merge($itemData, ['transformed_by_mock' => true]);

    expect($result)->toBeArray()->toHaveCount(1)
        ->and($result[0])->toEqual($expectedTransformedItem);
});

test('toArray correctly transforms a collection with multiple items', function () {
    $request = Request::create('/');
    $itemData1 = ['id' => 1, 'name' => 'Test Item 1', 'price' => 100];
    $itemData2 = ['id' => 2, 'name' => 'Test Item 2', 'price' => 200];
    $collection = Collection::make([$itemData1, $itemData2]);

    $estimateItemCollection = EstimateItemCollection::make($collection, MockEstimateItemResource::class);

    $result = $estimateItemCollection->toArray($request);

    $expectedTransformedItem1 = array_merge($itemData1, ['transformed_by_mock' => true]);
    $expectedTransformedItem2 = array_merge($itemData2, ['transformed_by_mock' => true]);

    expect($result)->toBeArray()->toHaveCount(2)
        ->and($result[0])->toEqual($expectedTransformedItem1)
        ->and($result[1])->toEqual($expectedTransformedItem2);
});

test('toArray handles different request instances without breaking transformations', function () {
    // Create two distinct request instances
    $request1 = Request::create('/api/estimates', 'GET', ['filter' => 'active']);
    $request2 = Request::create('/api/invoices', 'POST', ['user_id' => 5]);

    $itemData = ['id' => 1, 'description' => 'A unique item'];
    $collection = Collection::make([$itemData]);

    // Create two collection instances, each will be processed with a different request
    $estimateItemCollection1 = EstimateItemCollection::make($collection, MockEstimateItemResource::class);
    $estimateItemCollection2 = EstimateItemCollection::make($collection, MockEstimateItemResource::class);

    $result1 = $estimateItemCollection1->toArray($request1);
    $result2 = $estimateItemCollection2->toArray($request2);

    $expectedTransformedItem = array_merge($itemData, ['transformed_by_mock' => true]);

    // Assert that both calls yielded the same transformed result, confirming request parameter doesn't
    // interfere with the basic transformation logic (as implemented in our mock) and doesn't cause errors.
    expect($result1)->toBeArray()->toHaveCount(1)
        ->and($result1[0])->toEqual($expectedTransformedItem);

    expect($result2)->toBeArray()->toHaveCount(1)
        ->and($result2[0])->toEqual($expectedTransformedItem);
});

test('toArray throws a TypeError if a non-Request object is passed as request parameter', function () {
    $itemData = ['id' => 1, 'value' => 'some data'];
    $collection = Collection::make([$itemData]);
    $estimateItemCollection = EstimateItemCollection::make($collection, MockEstimateItemResource::class);

    // Expect a TypeError because the `toArray` method (both in the current class and parent)
    // type-hints `Illuminate\Http\Request $request`.
    $this->expectException(TypeError::class);
    $estimateItemCollection->toArray('this is a string, not a request');
})->throws(TypeError::class);




afterEach(function () {
    Mockery::close();
});
