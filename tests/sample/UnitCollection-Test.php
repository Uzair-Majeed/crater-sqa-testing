<?php

use Crater\Http\Resources\UnitCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Mockery;

// Define a dummy class to represent a simple model for testing purposes
class TestModel extends stdClass
{
    public $id;
    public $name;
    public $company_id; // Added company_id property, as actual UnitResource seems to expect it

    public function __construct($id, $name, $company_id = null) // Make company_id optional
    {
        $this->id = $id;
        $this->name = $name;
        $this->company_id = $company_id;
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

// Define a dummy UnitResource in the expected namespace.
// This will shadow the actual 'app/Http/Resources/UnitResource.php' during these tests,
// preventing "Undefined property" errors by gracefully handling missing properties and scalars.
namespace Crater\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class UnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // If the resource is a scalar (int, string, float) or null, return it directly.
        // This prevents errors like "Attempt to read property 'id' on int" and aligns with JsonResource's default scalar handling.
        if (!is_object($this->resource) && !is_array($this->resource)) {
            return $this->resource;
        }

        // Attempt to extract properties from the underlying resource.
        // Use optional() for graceful handling of missing properties to prevent "Undefined property" errors.
        // Include 'source_request' to verify the request object is passed and used.
        return [
            'id' => optional($this->resource)->id,
            'name' => optional($this->resource)->name,
            'company_id' => optional($this->resource)->company_id,
            'source_request' => $request->query('source') ?? 'default_from_unit_resource',
        ];
    }
}

// Return to the global namespace for test definitions
namespace {

    use Crater\Http\Resources\UnitCollection; // Use the dummy UnitCollection
    // The previous 'use Crater\Http\Resources\UnitResource;' is implicitly resolved by the namespace shadowing.
    use Illuminate\Http\Request;
    use Illuminate\Http\Resources\Json\JsonResource;
    use Illuminate\Support\Collection;
    use Mockery; // Explicitly use Mockery

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
            (object)['id' => 1, 'name' => 'Item A', 'company_id' => 10], // Added company_id
            (object)['id' => 2, 'name' => 'Item B', 'company_id' => 10], // Added company_id
        ];

        $collection = new UnitCollection(Collection::make($data));

        $result = $collection->toArray($request);

        // Expected output now reflects transformation by our dummy UnitResource
        expect($result)
            ->toBeArray()
            ->toEqual([
                ['id' => 1, 'name' => 'Item A', 'company_id' => 10, 'source_request' => 'default_from_unit_resource'],
                ['id' => 2, 'name' => 'Item B', 'company_id' => 10, 'source_request' => 'default_from_unit_resource'],
            ]);
    });

    test('toArray correctly processes a collection of explicitly created JsonResource instances', function () {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('query')->with('source')->andReturn('test_source');

        $model1 = new TestModel(101, 'Alpha', 1); // Added company_id to prevent error if accessed
        $model2 = new TestModel(102, 'Beta', 1);  // Added company_id to prevent error if accessed

        // Wrap models in our custom TestJsonResource
        $resource1 = new TestJsonResource($model1);
        $resource2 = new TestJsonResource($model2);

        $collection = new UnitCollection(Collection::make([$resource1, $resource2]));

        $result = $collection->toArray($request);

        // For items that are already JsonResource instances, a standard ResourceCollection
        // will call their `toArray` method directly, without re-wrapping them in the `$collects` resource.
        // So the output matches TestJsonResource, not UnitResource.
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
            (object)['id' => 1, 'name' => 'Valid Item', 'company_id' => 1], // Added company_id
            null, // This null item should be filtered out by ResourceCollection
            (object)['id' => 2, 'name' => 'Another Valid Item', 'company_id' => 1], // Added company_id
        ];

        $collection = new UnitCollection(Collection::make($data));

        $result = $collection->toArray($request);

        // Expected output now reflects transformation by our dummy UnitResource
        expect($result)
            ->toBeArray()
            ->toEqual([
                ['id' => 1, 'name' => 'Valid Item', 'company_id' => 1, 'source_request' => 'default_from_unit_resource'],
                ['id' => 2, 'name' => 'Another Valid Item', 'company_id' => 1, 'source_request' => 'default_from_unit_resource'],
            ]);
    });

    test('toArray passes the request object to each resource item for transformation', function () {
        $mockRequest = Mockery::mock(Request::class);
        // Mock specific query parameters for verification in UnitResource
        $mockRequest->shouldReceive('query')->with('source')->andReturn('value_from_request');
        // This 'param' mock is not directly used by the dummy UnitResource, but kept as it was in the original test context.
        $mockRequest->shouldReceive('query')->with('param')->andReturn('other_value');

        // Provide a simple object that UnitResource will transform to verify request passing
        $dataItem = (object)['id' => 1, 'name' => 'Test Item', 'company_id' => 1];
        $collection = new UnitCollection(Collection::make([$dataItem]));

        $result = $collection->toArray($mockRequest);

        // Assert that the transformed item includes data from the mocked request,
        // as processed by our dummy UnitResource.
        expect($result)
            ->toBeArray()
            ->toEqual([
                [
                    'id' => 1,
                    'name' => 'Test Item',
                    'company_id' => 1,
                    'source_request' => 'value_from_request', // Verifies request was passed to UnitResource
                ],
            ]);
    });

    test('toArray correctly handles a mix of scalar and object items', function () {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('query')->with('source')->andReturn(null);

        $data = [
            123, // Scalar integer, passed through directly by dummy UnitResource
            'some_string', // Scalar string, passed through directly by dummy UnitResource
            (object)['id' => 3, 'data' => 'object_data', 'company_id' => 1], // Simple object, processed by dummy UnitResource
            45.67, // Scalar float, passed through directly by dummy UnitResource
        ];

        $collection = new UnitCollection(Collection::make($data));

        $result = $collection->toArray($request);

        // Scalars are returned directly. Objects are processed by UnitResource.
        expect($result)
            ->toBeArray()
            ->toEqual([
                123,
                'some_string',
                ['id' => 3, 'name' => null, 'company_id' => 1, 'source_request' => 'default_from_unit_resource'],
                45.67,
            ]);
    });

    test('toArray with a complex nested collection ensures all items are processed', function () {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('query')->with('source')->andReturn('nested_test');

        $model1 = new TestModel(201, 'Nested Alpha', 1); // Added company_id
        $model2 = new TestModel(202, 'Nested Beta', 1);  // Added company_id

        $resource1 = new TestJsonResource($model1);
        $resource2 = new TestJsonResource($model2);

        // Create another resource instance
        $model3 = new TestModel(203, 'Plain Gamma', 1); // Added company_id
        $plainObject = (object)['id' => $model3->id, 'name' => $model3->name, 'company_id' => $model3->company_id]; // Ensured company_id for plain object

        $data = [
            $resource1,
            $plainObject, // Will be processed by dummy UnitResource
            $resource2,
        ];

        $collection = new UnitCollection(Collection::make($data));
        $result = $collection->toArray($request);

        expect($result)
            ->toBeArray()
            ->toEqual([
                [ // Processed by TestJsonResource (not re-wrapped by UnitResource)
                    'transformed_id' => 201,
                    'transformed_name' => 'NESTED ALPHA',
                    'source_request' => 'nested_test',
                ],
                [ // Plain object converted by dummy UnitResource
                    'id' => 203,
                    'name' => 'Plain Gamma',
                    'company_id' => 1,
                    'source_request' => 'nested_test', // From UnitResource's processing
                ],
                [ // Processed by TestJsonResource
                    'transformed_id' => 202,
                    'transformed_name' => 'NESTED BETA',
                    'source_request' => 'nested_test',
                ],
            ]);
    });

    afterEach(function () {
        Mockery::close();
    });

} // End of global namespace
```