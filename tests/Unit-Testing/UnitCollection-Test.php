<?php

use Crater\Http\Resources\UnitCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

// Define a dummy class to represent a simple model for testing purposes
class TestModel extends stdClass
{
    public $id;
    public $name;

    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}

// Define a dummy JsonResource for testing transformation logic
class TestJsonResource extends JsonResource
{
    public function toArray($request)
    {
        // Simulate a transformation specific to this resource
        return [
            'transformed_id' => $this->id,
            'transformed_name' => strtoupper($this->name),
            'source_request' => $request->query('source') ?? 'default',
        ];
    }
}

test('toArray returns an empty array when the underlying collection is empty', function () {
    $request = Mockery::mock(Request::class);
    $collection = new UnitCollection(Collection::make([]));

    $result = $collection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toBeEmpty();
});

test('toArray correctly processes a collection of simple objects via default JsonResource behavior', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('query')->with('source')->andReturn(null); // Mock request for resource transformation

    $data = [
        (object)['id' => 1, 'name' => 'Item A'],
        (object)['id' => 2, 'name' => 'Item B'],
    ];

    $collection = new UnitCollection(Collection::make($data));

    $result = $collection->toArray($request);

    // By default, JsonResource converts public properties of an object to an array
    expect($result)
        ->toBeArray()
        ->toEqual([
            ['id' => 1, 'name' => 'Item A'],
            ['id' => 2, 'name' => 'Item B'],
        ]);
});

test('toArray correctly processes a collection of explicitly created JsonResource instances', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('query')->with('source')->andReturn('test_source');

    $model1 = new TestModel(101, 'Alpha');
    $model2 = new TestModel(102, 'Beta');

    // Wrap models in our custom TestJsonResource
    $resource1 = new TestJsonResource($model1);
    $resource2 = new TestJsonResource($model2);

    $collection = new UnitCollection(Collection::make([$resource1, $resource2]));

    $result = $collection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toEqual([
            [
                'transformed_id' => 101,
                'transformed_name' => 'ALPHA',
                'source_request' => 'test_source',
            ],
            [
                'transformed_id' => 102,
                'transformed_name' => 'BETA',
                'source_request' => 'test_source',
            ],
        ]);
});

test('toArray handles null items by filtering them out by default', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('query')->with('source')->andReturn(null);

    $data = [
        (object)['id' => 1, 'name' => 'Valid Item'],
        null, // This null item should be filtered out by ResourceCollection
        (object)['id' => 2, 'name' => 'Another Valid Item'],
    ];

    $collection = new UnitCollection(Collection::make($data));

    $result = $collection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toEqual([
            ['id' => 1, 'name' => 'Valid Item'],
            ['id' => 2, 'name' => 'Another Valid Item'],
        ]);
});

test('toArray passes the request object to each resource item for transformation', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('query')->with('param')->andReturn('value'); // Mock a specific request method
    $mockRequest->shouldReceive('query')->with('source')->andReturn(null);

    // Create a mock resource that asserts the request is passed correctly
    $mockResource = Mockery::mock(JsonResource::class, [(object)['id' => 1]])->makePartial();
    $mockResource->shouldReceive('toArray')
        ->once()
        ->with($mockRequest) // Assert that the mockRequest is passed
        ->andReturn(['processed_by_mock_resource' => true]);

    $collection = new UnitCollection(Collection::make([$mockResource]));

    $result = $collection->toArray($mockRequest);

    expect($result)
        ->toBeArray()
        ->toEqual([
            ['processed_by_mock_resource' => true],
        ]);
    Mockery::close(); // Clean up mock after the test
});

test('toArray correctly handles a mix of scalar and object items', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('query')->with('source')->andReturn(null);

    $data = [
        123, // Scalar integer
        'some_string', // Scalar string
        (object)['id' => 3, 'data' => 'object_data'], // Simple object
        45.67, // Scalar float
    ];

    $collection = new UnitCollection(Collection::make($data));

    $result = $collection->toArray($request);

    // JsonResource::toArray will pass scalars directly and convert objects
    expect($result)
        ->toBeArray()
        ->toEqual([
            123,
            'some_string',
            ['id' => 3, 'data' => 'object_data'],
            45.67,
        ]);
});

test('toArray with a complex nested collection ensures all items are processed', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('query')->with('source')->andReturn('nested_test');

    $model1 = new TestModel(201, 'Nested Alpha');
    $model2 = new TestModel(202, 'Nested Beta');

    $resource1 = new TestJsonResource($model1);
    $resource2 = new TestJsonResource($model2);

    // Create another resource instance
    $model3 = new TestModel(203, 'Plain Gamma');
    $plainObject = (object)['id' => $model3->id, 'name' => $model3->name];

    $data = [
        $resource1,
        $plainObject, // Will be processed by default JsonResource behavior
        $resource2,
    ];

    $collection = new UnitCollection(Collection::make($data));
    $result = $collection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toEqual([
            [
                'transformed_id' => 201,
                'transformed_name' => 'NESTED ALPHA',
                'source_request' => 'nested_test',
            ],
            [ // Plain object converted by default JsonResource
                'id' => 203,
                'name' => 'Plain Gamma',
            ],
            [
                'transformed_id' => 202,
                'transformed_name' => 'NESTED BETA',
                'source_request' => 'nested_test',
            ],
        ]);
});




afterEach(function () {
    Mockery::close();
});
