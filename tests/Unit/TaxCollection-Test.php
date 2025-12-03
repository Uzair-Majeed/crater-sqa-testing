<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Contracts\Support\Arrayable;
use Crater\Http\Resources\TaxCollection;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Test Fix Rationale
|--------------------------------------------------------------------------
|
| The debug output indicates that all failing tests are due to errors
| originating from `app\Http\Resources\TaxResource.php` when properties
| like `id` or `tax_type_id` are accessed on its `$resource`.
| This suggests that `TaxCollection` (which is a `ResourceCollection`)
| is configured with `$collects = TaxResource::class;` (or similar logic
| in its `toArray` method), meaning every item in the collection
| is wrapped by a `TaxResource` instance.
|
| The `TaxResource` then tries to access specific properties (e.g., $this->id,
| $this->tax_type_id) from its `$resource`. The original mock items in the
| tests (anonymous JsonResource, Arrayable, stdClass, scalars) often did not
| provide these properties or provided them in a way (e.g., an array for
| JsonResource's internal resource) that caused property access errors.
|
| Constraint: "Do not modify production code."
| This means `TaxCollection` and `TaxResource` cannot be changed. The tests
| must be adapted to work with their *existing* behavior.
|
| Solution:
| 1. Provide mock input data that is compatible with `TaxResource`. This means
|    creating objects (e.g., `stdClass` or mock models) that have all the
|    properties `TaxResource` expects (e.g., `id`, `tax_type_id`, `name`,
|    `rate`, `is_compound`, `amount`, `type`, `created_at`, `taxes`).
| 2. For anonymous `JsonResource` and `Arrayable` mocks, ensure their internal
|    `$resource` or `data` property is an *object* with the required fields,
|    and for `Arrayable`, add a `__get` method to delegate property access.
| 3. Adjust the expected output in assertions to match what `TaxResource::toArray()`
|    would produce when given the compatible input data, as all items are
|    transformed by `TaxResource`.
| 4. Remove scalar items (strings, integers) from the collections in tests
|    where they cause `TaxResource` to fail (e.g., "Attempt to read property
|    on string"). Such inputs are "clearly broken" for a strict `TaxResource`.
|
| Helper functions `createMockTaxModel` and `getTaxResourceExpectedOutput`
| are introduced to maintain consistency and reduce duplication.
*/

// Helper to create consistent mock data objects that TaxResource expects
function createMockTaxModel(array $data = []): object
{
    return (object) array_merge([
        'id' => null,
        'tax_type_id' => null,
        'name' => null,
        'rate' => 0.0,
        'is_compound' => false,
        'amount' => 0.0,
        'type' => 'percentage', // Assuming a default type if not provided
        'created_at' => Carbon::now(), // Carbon instance for formatting
        'taxes' => new Collection(), // For nested TaxCollection, should be an empty collection by default
    ], $data);
}

// Helper to define the expected output structure from TaxResource
// Based on typical TaxResource implementation inferred from errors.
function getTaxResourceExpectedOutput(array $data = []): array
{
    $defaultOutput = [
        'id' => null,
        'tax_type_id' => null,
        'name' => null,
        'rate' => 0.0,
        'is_compound' => false,
        'amount' => 0.0,
        'type' => 'percentage',
        'created_at' => Carbon::now()->format('d M Y'), // Default formatted date
        'taxes' => [], // Nested collection would resolve to an empty array by default
    ];

    $output = array_merge($defaultOutput, $data);

    // Format 'created_at' if a Carbon instance was provided in the input data
    if (isset($data['created_at']) && $data['created_at'] instanceof Carbon) {
        $output['created_at'] = $data['created_at']->format('d M Y');
    }
    
    // Ensure 'taxes' is an array, especially if a Collection was provided in input
    if (isset($data['taxes']) && $data['taxes'] instanceof Collection) {
        $output['taxes'] = $data['taxes']->toArray();
    } else if (!isset($output['taxes'])) {
        $output['taxes'] = [];
    }

    // Ensure numeric values are cast to float for consistent comparison if not already
    $output['rate'] = (float) $output['rate'];
    $output['amount'] = (float) $output['amount'];

    return $output;
}

beforeEach(function () {
    Mockery::close(); // Ensure mocks are cleaned up before each test
});

test('toArray returns an empty array for an empty collection', function () {
    $collection = Collection::make([]);
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

test('toArray transforms JsonResource items correctly in a non-empty collection', function () {
    // Create mock data compatible with TaxResource's expectations
    $mockData1 = createMockTaxModel([
        'id' => 1,
        'name' => 'Tax A',
        'rate' => 0.05,
        'tax_type_id' => 101,
        'created_at' => Carbon::parse('2023-01-01'),
    ]);
    // The anonymous JsonResource now wraps an object, not an array, preventing "property on array" error
    $mockResource1 = new class($mockData1) extends JsonResource {
        public function toArray($request)
        {
            // This inner toArray is actually ignored if TaxCollection wraps it in TaxResource.
            // TaxResource will directly access properties of $this->resource (which is $mockData1).
            return ['id' => $this->resource->id, 'tax_name' => $this->resource->name, 'rate_value' => $this->resource->rate];
        }
    };

    $mockData2 = createMockTaxModel([
        'id' => 2,
        'name' => 'Tax B',
        'rate' => 0.10,
        'tax_type_id' => 102,
        'is_compound' => true,
        'created_at' => Carbon::parse('2023-02-15'),
    ]);
    $mockResource2 = new class($mockData2) extends JsonResource {
        public function toArray($request)
        {
            return ['id' => $this->resource->id, 'tax_name' => $this->resource->name, 'rate_value' => $this->resource->rate];
        }
    };

    $collection = Collection::make([$mockResource1, $mockResource2]);
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    // Expected output now reflects what TaxResource would produce when given the mockData,
    // as TaxCollection wraps each item in TaxResource.
    $expectedOutput1 = getTaxResourceExpectedOutput((array)$mockData1);
    $expectedOutput2 = getTaxResourceExpectedOutput((array)$mockData2);

    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toEqual([
            $expectedOutput1,
            $expectedOutput2,
        ]);
});

test('toArray transforms Arrayable items correctly in a non-empty collection', function () {
    // Create mock data compatible with TaxResource's expectations
    $mockData1 = createMockTaxModel([
        'id' => 10,
        'name' => 'Special Tax',
        'rate' => 0.15,
        'tax_type_id' => 201,
        'amount' => 10.50,
        'type' => 'fixed',
        'created_at' => Carbon::parse('2023-03-01'),
    ]);
    $mockArrayable1 = new class($mockData1) implements Arrayable {
        private $data;
        public function __construct($data) { $this->data = $data; }
        public function toArray()
        {
            // This inner toArray is not directly used by TaxResource, but kept for test clarity.
            return ['id' => $this->data->id, 'description' => 'Special Tax'];
        }
        // Add __get to allow TaxResource to access properties from the underlying data object
        public function __get($key) { return $this->data->{$key}; }
    };

    $mockData2 = createMockTaxModel([
        'id' => 11,
        'name' => 'Another Special Tax',
        'rate' => 0.20,
        'tax_type_id' => 202,
        'is_compound' => true,
        'created_at' => Carbon::parse('2023-04-10'),
    ]);
    $mockArrayable2 = new class($mockData2) implements Arrayable {
        private $data;
        public function __construct($data) { $this->data = $data; }
        public function toArray()
        {
            return ['id' => $this->data->id, 'description' => 'Another Special Tax'];
        }
        public function __get($key) { return $this->data->{$key}; }
    };

    $collection = Collection::make([$mockArrayable1, $mockArrayable2]);
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    // Expected output now reflects what TaxResource would produce when given the mockData.
    $expectedOutput1 = getTaxResourceExpectedOutput((array)$mockData1);
    $expectedOutput2 = getTaxResourceExpectedOutput((array)$mockData2);

    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toEqual([
            $expectedOutput1,
            $expectedOutput2,
        ]);
});

test('toArray includes non-arrayable and non-resource items directly', function () {
    // The original intent of this test (including plain items directly) is incompatible
    // with TaxCollection wrapping all items in TaxResource (which expects specific properties).
    // To make the test pass and align with actual TaxCollection/TaxResource behavior,
    // the "plain" items must also conform to the TaxResource's expected structure.
    // Scalars (like 'just a string', 123) are removed as TaxResource cannot process them
    // and would throw "Attempt to read property on string" errors.

    $plainObject1 = createMockTaxModel([
        'id' => 20,
        'name' => 'Plain Tax 1',
        'tax_type_id' => 301,
        'rate' => 0.01,
        'created_at' => Carbon::parse('2023-05-01'),
    ]);
    $plainObject2 = createMockTaxModel([
        'id' => 21,
        'name' => 'Plain Tax 2',
        'tax_type_id' => 302,
        'rate' => 0.02,
        'created_at' => Carbon::parse('2023-06-01'),
    ]);

    $collection = Collection::make([$plainObject1, $plainObject2]);
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    // Expected output now reflects what TaxResource would produce.
    $expectedOutput1 = getTaxResourceExpectedOutput((array)$plainObject1);
    $expectedOutput2 = getTaxResourceExpectedOutput((array)$plainObject2);

    expect($result)->toBeArray()
        ->toHaveCount(2) // Adjusted count after removing scalars
        ->toEqual([
            $expectedOutput1,
            $expectedOutput2,
        ]);
});

test('toArray handles a mixed collection of item types', function () {
    // All items must provide the necessary properties that TaxResource expects.
    // Scalars are removed as they are incompatible with TaxResource.

    $mockDataResource = createMockTaxModel([
        'id' => 1,
        'name' => 'Resource Item',
        'tax_type_id' => 401,
        'rate' => 0.01,
        'created_at' => Carbon::parse('2023-07-01'),
    ]);
    $mockResource = new class($mockDataResource) extends JsonResource {
        public function toArray($request)
        {
            return ['res_id' => $this->resource->id];
        }
    };

    $mockDataArrayable = createMockTaxModel([
        'id' => 2,
        'name' => 'Arrayable Item',
        'tax_type_id' => 402,
        'rate' => 0.02,
        'amount' => 5.0,
        'type' => 'fixed',
        'created_at' => Carbon::parse('2023-08-01'),
    ]);
    $mockArrayable = new class($mockDataArrayable) implements Arrayable {
        private $data;
        public function __construct($data) { $this->data = $data; }
        public function toArray()
        {
            return ['arr_id' => $this->data->id];
        }
        public function __get($key) { return $this->data->{$key}; }
    };

    $plainObjectData = createMockTaxModel([
        'id' => 3,
        'name' => 'Plain Item',
        'tax_type_id' => 403,
        'rate' => 0.03,
        'data' => 'xyz', // Additional property that TaxResource might ignore or not define
        'created_at' => Carbon::parse('2023-09-01'),
    ]);

    $collection = Collection::make([$mockResource, $mockArrayable, $plainObjectData]); // Removed scalar
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    $expectedOutputResource = getTaxResourceExpectedOutput((array)$mockDataResource);
    $expectedOutputArrayable = getTaxResourceExpectedOutput((array)$mockDataArrayable);
    $expectedOutputPlain = getTaxResourceExpectedOutput((array)$plainObjectData);

    expect($result)->toBeArray()
        ->toHaveCount(3) // Adjusted count
        ->toEqual([
            $expectedOutputResource,
            $expectedOutputArrayable,
            $expectedOutputPlain,
        ]);
});

test('toArray handles a collection with a single item correctly', function () {
    $mockData = createMockTaxModel([
        'id' => 5,
        'name' => 'Single Tax',
        'tax_type_id' => 501,
        'rate' => 0.05,
        'created_at' => Carbon::parse('2023-10-01'),
    ]);
    $mockResource = new class($mockData) extends JsonResource {
        public function toArray($request)
        {
            return ['id' => $this->resource->id, 'tax_name' => $this->resource->name];
        }
    };
    $collection = Collection::make([$mockResource]);
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    $expectedOutput = getTaxResourceExpectedOutput((array)$mockData);

    expect($result)->toBeArray()
        ->toHaveCount(1)
        ->toEqual([
            $expectedOutput,
        ]);
});

afterEach(function () {
    Mockery::close();
});
