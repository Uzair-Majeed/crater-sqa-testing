<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Mockery as m;
use Illuminate\Http\Resources\Json\JsonResource;
use Crater\Http\Resources\AddressCollection; // Assuming the class is in this namespace for the test context

// Define a simple dummy resource class for testing purposes
// This allows us to control the transformation of individual items
// and verify that AddressCollection correctly delegates to their toArray method.
class TestAddressResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'transformed_street' => strtoupper($this->resource->street),
        ];
    }
}

test('AddressCollection toArray delegates to parent ResourceCollection and transforms items', function () {
    // Prepare some dummy underlying data (e.g., Eloquent models or simple objects)
    $address1 = (object)['id' => 1, 'name' => 'Home', 'street' => '123 main st', 'city' => 'Anytown'];
    $address2 = (object)['id' => 2, 'name' => 'Work', 'street' => '456 oak ave', 'city' => 'Otherville'];

    // Wrap the dummy data in our custom test resource.
    // This allows us to verify that ResourceCollection calls toArray on its items.
    $resource1 = new TestAddressResource($address1);
    $resource2 = new TestAddressResource($address2);

    $dummyResources = new Collection([$resource1, $resource2]);

    // Create a mock request object
    $mockRequest = m::mock(Request::class);
    // ResourceCollection might try to access methods on the request, mock them to prevent errors.
    $mockRequest->shouldReceive('json')->andReturn(null);
    $mockRequest->shouldReceive('query')->andReturn([]);

    // Instantiate the AddressCollection with the collection of resources
    $collection = new AddressCollection($dummyResources);

    // Call the toArray method of AddressCollection
    $result = $collection->toArray($mockRequest);

    // Assertions for a collection with multiple items:
    // The parent ResourceCollection::toArray method wraps the items in a 'data' array.
    // Each item in 'data' should be the transformed output of TestAddressResource::toArray.
    expect($result)
        ->toBeArray()
        ->toHaveKey('data')
        ->and($result['data'])
        ->toBeArray()
        ->toHaveCount(2);

    expect($result['data'][0])
        ->toEqual([
            'id' => 1,
            'name' => 'Home',
            'transformed_street' => '123 MAIN ST',
        ]);

    expect($result['data'][1])
        ->toEqual([
            'id' => 2,
            'name' => 'Work',
            'transformed_street' => '456 OAK AVE',
        ]);

    // Edge Case: Empty collection of resources
    $emptyResources = new Collection([]);
    $emptyCollection = new AddressCollection($emptyResources);
    $emptyResult = $emptyCollection->toArray($mockRequest);

    expect($emptyResult)
        ->toBeArray()
        ->toHaveKey('data')
        ->and($emptyResult['data'])
        ->toBeArray()
        ->toBeEmpty();

    // Edge Case: Single resource collection
    $singleAddress = (object)['id' => 3, 'name' => 'Vacation', 'street' => '789 pine rd', 'city' => 'Seaside'];
    $singleResource = new TestAddressResource($singleAddress);
    $singleResources = new Collection([$singleResource]);
    $singleCollection = new AddressCollection($singleResources);
    $singleResult = $singleCollection->toArray($mockRequest);

    expect($singleResult)
        ->toBeArray()
        ->toHaveKey('data')
        ->and($singleResult['data'])
        ->toBeArray()
        ->toHaveCount(1);
    expect($singleResult['data'][0])
        ->toEqual([
            'id' => 3,
            'name' => 'Vacation',
            'transformed_street' => '789 PINE RD',
        ]);

    // Clean up Mockery expectations
    m::close();
});
