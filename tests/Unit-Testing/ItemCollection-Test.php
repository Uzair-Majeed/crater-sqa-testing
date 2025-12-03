<?php

namespace Tests\Unit\Http\Resources;

use Crater\Http\Resources\ItemCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

// Helper class to mock individual item resources for testing ItemCollection
class TestItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Simulate a transformation specific to a concrete item resource
        return [
            'id' => $this->resource['id'],
            'name' => strtoupper($this->resource['name']),
            'custom_field' => 'processed',
        ];
    }
}

test('toArray returns an empty data array when the underlying collection is empty', function () {
    $request = new Request();
    $emptyCollection = new Collection();
    $itemCollection = new ItemCollection($emptyCollection);

    $result = $itemCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toEqual(['data' => []]);
});

test('toArray correctly transforms a collection with a single item', function () {
    $request = new Request();
    $mockItemData = ['id' => 1, 'name' => 'Widget A'];
    $mockItemResource = new TestItemResource((object)$mockItemData); // JsonResource expects an object or array

    $collectionOfResources = new Collection([$mockItemResource]);
    $itemCollection = new ItemCollection($collectionOfResources);

    $expectedOutput = [
        'data' => [
            [
                'id' => 1,
                'name' => 'WIDGET A',
                'custom_field' => 'processed',
            ],
        ],
    ];

    $result = $itemCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toEqual($expectedOutput);
});

test('toArray correctly transforms a collection with multiple items', function () {
    $request = new Request();
    $mockItemData1 = ['id' => 1, 'name' => 'Widget A'];
    $mockItemData2 = ['id' => 2, 'name' => 'Gadget B'];

    $mockItemResource1 = new TestItemResource((object)$mockItemData1);
    $mockItemResource2 = new TestItemResource((object)$mockItemData2);

    $collectionOfResources = new Collection([$mockItemResource1, $mockItemResource2]);
    $itemCollection = new ItemCollection($collectionOfResources);

    $expectedOutput = [
        'data' => [
            [
                'id' => 1,
                'name' => 'WIDGET A',
                'custom_field' => 'processed',
            ],
            [
                'id' => 2,
                'name' => 'GADGET B',
                'custom_field' => 'processed',
            ],
        ],
    ];

    $result = $itemCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toEqual($expectedOutput);
});

test('toArray passes the request instance directly to the parent method', function () {
    // This test ensures that the `toArray` method in ItemCollection does not modify
    // or intercept the `$request` object before passing it to `parent::toArray()`.
    // Since ItemCollection itself has no logic other than delegating, we verify
    // the delegation by checking the outcome, assuming parent correctly uses the request.

    // Create a mock request with a specific attribute to differentiate it
    $mockRequest = new Request();
    $mockRequest->attributes->set('test_attribute', 'value_from_request');

    $mockItemData = ['id' => 1, 'name' => 'Test Item'];
    $mockItemResource = new TestItemResource((object)$mockItemData);
    $collectionOfResources = new Collection([$mockItemResource]);

    $itemCollection = new ItemCollection($collectionOfResources);

    // If TestItemResource were to inspect the request, this would be valuable.
    // For this specific setup, TestItemResource doesn't use the request,
    // so we're primarily testing the "pass-through" nature of ItemCollection::toArray().
    $result = $itemCollection->toArray($mockRequest);

    $expectedOutput = [
        'data' => [
            [
                'id' => 1,
                'name' => 'TEST ITEM',
                'custom_field' => 'processed',
            ],
        ],
    ];

    expect($result)->toEqual($expectedOutput);
    // The key takeaway here is that `ItemCollection` itself doesn't alter the request.
    // Any request-dependent behavior would stem from the individual resources' `toArray` methods,
    // which correctly receive the `$mockRequest` via the parent's delegation.
});

// Since ItemCollection only overrides `toArray` and simply calls `parent::toArray`,
// there are no additional branches, conditions, or private/protected methods
// introduced by `ItemCollection` itself to test. All complex logic is inherited
// from `ResourceCollection` and the individual resources, which our tests
// implicitly cover by verifying the correct delegation.




afterEach(function () {
    Mockery::close();
});
