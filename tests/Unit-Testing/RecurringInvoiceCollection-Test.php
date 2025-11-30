<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
uses(\Mockery::class);
use Crater\Http\Resources\RecurringInvoiceCollection;

beforeEach(function () {
    // Ensure Mockery is closed and cleaned up before each test
    Mockery::close();
});

test('toArray delegates to parent toArray and transforms multiple items correctly', function () {
    // Create mock JsonResource instances. These represent the individual items
    // within the collection that will have their `toArray` method called by the parent collection.
    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->once()
                  ->with(Mockery::type(Request::class))
                  ->andReturn(['id' => 1, 'name' => 'Invoice A', 'transformed' => true]);

    $mockResource2 = Mockery::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->once()
                  ->with(Mockery::type(Request::class))
                  ->andReturn(['id' => 2, 'name' => 'Invoice B', 'transformed' => true]);

    // Wrap the mock resources in an Illuminate\Support\Collection as expected by ResourceCollection
    $resources = new Collection([$mockResource1, $mockResource2]);

    // Create a mock Request object
    $request = Mockery::mock(Request::class);

    // Instantiate the collection under test
    $collection = new RecurringInvoiceCollection($resources);

    // Call the toArray method of the collection
    $result = $collection->toArray($request);

    // Assertions to verify the output structure and content match what
    // Illuminate\Http\Resources\Json\ResourceCollection::toArray() would produce
    $this->assertIsArray($result);
    $this->assertArrayHasKey('data', $result);
    $this->assertCount(2, $result['data']);

    $this->assertEquals([
        'id' => 1, 'name' => 'Invoice A', 'transformed' => true
    ], $result['data'][0]);

    $this->assertEquals([
        'id' => 2, 'name' => 'Invoice B', 'transformed' => true
    ], $result['data'][1]);
});

test('toArray handles an empty collection by returning an empty data array', function () {
    // Create an empty collection of mock resources
    $resources = new Collection([]);

    // Create a mock Request object
    $request = Mockery::mock(Request::class);

    // Instantiate the collection under test with an empty collection
    $collection = new RecurringInvoiceCollection($resources);

    // Call the toArray method
    $result = $collection->toArray($request);

    // Assertions: The output should be an array with an empty 'data' key, matching ResourceCollection's behavior
    $this->assertIsArray($result);
    $this->assertArrayHasKey('data', $result);
    $this->assertCount(0, $result['data']);
    $this->assertEquals(['data' => []], $result);
});

test('toArray ensures the exact request object instance is passed through to child resources', function () {
    // Create a distinct Request object to verify it's passed by reference
    $expectedRequest = Mockery::mock(Request::class);
    $expectedRequest->uuid = 'a-unique-request-identifier'; // Add a property for easier distinction

    $mockResource = Mockery::mock(JsonResource::class);
    // Expect 'toArray' to be called once, and crucially, with the *exact same* Request object instance
    $mockResource->shouldReceive('toArray')
                  ->once()
                  ->with(Mockery::on(function ($argument) use ($expectedRequest) {
                      // Check if the argument is the identical object instance
                      return $argument === $expectedRequest;
                  }))
                  ->andReturn(['item_status' => 'processed']);

    $resources = new Collection([$mockResource]);
    $collection = new RecurringInvoiceCollection($resources);

    // Call toArray with our specific Request object
    $result = $collection->toArray($expectedRequest);

    // Assertions
    $this->assertIsArray($result);
    $this->assertArrayHasKey('data', $result);
    $this->assertCount(1, $result['data']);
    $this->assertEquals(['item_status' => 'processed'], $result['data'][0]);
});

test('toArray correctly handles constructor input provided as a plain array of resources', function () {
    // Mock individual JsonResource instances
    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->once()
                  ->with(Mockery::type(Request::class))
                  ->andReturn(['product_id' => 101, 'quantity' => 2]);

    $mockResource2 = Mockery::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->once()
                  ->with(Mockery::type(Request::class))
                  ->andReturn(['product_id' => 102, 'quantity' => 1]);

    // Pass a plain array of mock JsonResource instances to the constructor.
    // ResourceCollection's constructor will internally convert this array to an Illuminate\Support\Collection.
    $resourcesArray = [$mockResource1, $mockResource2];

    $request = Mockery::mock(Request::class);

    // Instantiate the collection under test with the array of resources
    $collection = new RecurringInvoiceCollection($resourcesArray);

    $result = $collection->toArray($request);

    // Assertions
    $this->assertIsArray($result);
    $this->assertArrayHasKey('data', $result);
    $this->assertCount(2, $result['data']);

    $this->assertEquals(['product_id' => 101, 'quantity' => 2], $result['data'][0]);
    $this->assertEquals(['product_id' => 102, 'quantity' => 1], $result['data'][1]);
});
