<?php

namespace Tests\Unit;

use Crater\Http\Resources\FileDiskCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
uses(\Mockery::class);
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

// Integrate Mockery cleanup with Pest.
uses(MockeryPHPUnitIntegration::class)->in(__DIR__);

// Define a dummy JsonResource for testing purposes.
// This resource applies a simple transformation and captures the last received request.
class DummyResource extends JsonResource
{
    public static ?Request $lastReceivedRequest = null;

    public function toArray($request)
    {
        self::$lastReceivedRequest = $request;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'transformed' => true,
        ];
    }
}

test('toArray delegates to parent with a simple collection of items', function () {
    $item1 = (object) ['id' => 1, 'name' => 'File A'];
    $item2 = (object) ['id' => 2, 'name' => 'File B'];
    $data = Collection::make([$item1, $item2]);

    $request = Request::create('/');

    // Create an anonymous class extending FileDiskCollection to set the 'collects' property for transformation.
    $collectionWithCollects = new class($data) extends FileDiskCollection {
        public $collects = DummyResource::class;
    };

    $result = $collectionWithCollects->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(2);

    expect($result[0])->toEqual([
        'id' => 1,
        'name' => 'File A',
        'transformed' => true,
    ]);

    expect($result[1])->toEqual([
        'id' => 2,
        'name' => 'File B',
        'transformed' => true,
    ]);
});

test('toArray returns an empty array for an empty collection', function () {
    $data = Collection::make([]);
    $request = Request::create('/');

    $collectionWithCollects = new class($data) extends FileDiskCollection {
        public $collects = DummyResource::class;
    };

    $result = $collectionWithCollects->toArray($request);

    expect($result)
        ->toBeArray()
        ->toBeEmpty();
});

test('toArray handles a paginated collection and includes pagination meta and links', function () {
    $item1 = (object) ['id' => 10, 'name' => 'Paginated File X'];
    $item2 = (object) ['id' => 11, 'name' => 'Paginated File Y'];
    $perPage = 2;
    $currentPage = 1;
    $total = 2;

    $paginator = new LengthAwarePaginator(
        Collection::make([$item1, $item2]),
        $total,
        $perPage,
        $currentPage,
        ['path' => '/test-path']
    );

    $request = Request::create('/test-path?page=1');

    $collectionWithCollects = new class($paginator) extends FileDiskCollection {
        public $collects = DummyResource::class;
    };

    $result = $collectionWithCollects->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['data', 'links', 'meta']);

    expect($result['data'])
        ->toBeArray()
        ->toHaveCount(2);

    expect($result['data'][0])->toEqual([
        'id' => 10,
        'name' => 'Paginated File X',
        'transformed' => true,
    ]);

    expect($result['data'][1])->toEqual([
        'id' => 11,
        'name' => 'Paginated File Y',
        'transformed' => true,
    ]);

    // Check pagination meta data
    expect($result['meta']['current_page'])->toBe($currentPage);
    expect($result['meta']['per_page'])->toBe($perPage);
    expect($result['meta']['total'])->toBe($total);
    expect($result['links']['first'])->toContain('/test-path');
});

test('toArray handles items that are already JsonResource instances without explicit collects property', function () {
    $item1 = new DummyResource((object) ['id' => 20, 'name' => 'Direct Resource 1']);
    $item2 = new DummyResource((object) ['id' => 21, 'name' => 'Direct Resource 2']);
    $data = Collection::make([$item1, $item2]);

    $request = Request::create('/');

    // Create the FileDiskCollection without setting 'collects' explicitly.
    // When items are already JsonResource instances, ResourceCollection will call toArray on them directly.
    $collection = new FileDiskCollection($data);

    $result = $collection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(2);

    expect($result[0])->toEqual([
        'id' => 20,
        'name' => 'Direct Resource 1',
        'transformed' => true,
    ]);

    expect($result[1])->toEqual([
        'id' => 21,
        'name' => 'Direct Resource 2',
        'transformed' => true,
    ]);
});

test('toArray passes the request instance to nested resources', function () {
    $item = (object) ['id' => 30, 'name' => 'Request Test Item'];
    $data = Collection::make([$item]);

    // Mock the request to have a specific property or method call we can verify.
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('bearerToken')->andReturn('test-token')->once();
    // Stub other methods that ResourceCollection or JsonResource might call.
    $mockRequest->shouldReceive('query')->andReturn([])->zeroOrMoreTimes();
    $mockRequest->shouldReceive('segment')->andReturn(null)->zeroOrMoreTimes();
    $mockRequest->shouldReceive('route')->andReturn(null)->zeroOrMoreTimes();
    $mockRequest->shouldReceive('get')->andReturn(null)->zeroOrMoreTimes();
    $mockRequest->shouldReceive('isMethod')->andReturn(false)->zeroOrMoreTimes();
    $mockRequest->shouldReceive('url')->andReturn('http://localhost')->zeroOrMoreTimes();
    $mockRequest->shouldReceive('fullUrlWithQuery')->andReturn('http://localhost')->zeroOrMoreTimes();

    // Reset the static property on DummyResource before the test to capture the current request.
    DummyResource::$lastReceivedRequest = null;

    $collectionWithCollects = new class($data) extends FileDiskCollection {
        public $collects = DummyResource::class;
    };

    $collectionWithCollects->toArray($mockRequest);

    // Assert that our spy resource (DummyResource) received the exact mock request.
    expect(DummyResource::$lastReceivedRequest)->toBeInstanceOf(Request::class);
    expect(DummyResource::$lastReceivedRequest)->toBe($mockRequest); // Check for exact instance.
    expect(DummyResource::$lastReceivedRequest->bearerToken())->toBe('test-token');
});

test('toArray handles null collection gracefully returning an empty array', function () {
    $request = Request::create('/');
    $collection = new FileDiskCollection(null);

    $result = $collection->toArray($request);

    // ResourceCollection::toArray for a null resource returns an empty array.
    expect($result)
        ->toBeArray()
        ->toBeEmpty();
});

test('toArray handles non-collection iterable input (e.g., simple array)', function () {
    $item1 = (object) ['id' => 40, 'name' => 'Array Item 1'];
    $item2 = (object) ['id' => 41, 'name' => 'Array Item 2'];
    $data = [$item1, $item2]; // Simple PHP array.

    $request = Request::create('/');

    $collectionWithCollects = new class($data) extends FileDiskCollection {
        public $collects = DummyResource::class;
    };

    $result = $collectionWithCollects->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(2);

    expect($result[0])->toEqual([
        'id' => 40,
        'name' => 'Array Item 1',
        'transformed' => true,
    ]);

    expect($result[1])->toEqual([
        'id' => 41,
        'name' => 'Array Item 2',
        'transformed' => true,
    ]);
});

test('FileDiskCollection correctly applies collects property set via reflection', function () {
    $item = (object) ['id' => 50, 'name' => 'Dynamic Collects Item'];
    $data = Collection::make([$item]);
    $request = Request::create('/');

    $collection = new FileDiskCollection($data);

    // Use reflection to set the protected `collects` property, covering internal state.
    $reflectionProperty = new \ReflectionProperty($collection, 'collects');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($collection, DummyResource::class);

    $result = $collection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(1);

    expect($result[0])->toEqual([
        'id' => 50,
        'name' => 'Dynamic Collects Item',
        'transformed' => true,
    ]);
});

test('FileDiskCollection::collection static method creates an instance of FileDiskCollection', function () {
    $items = collect([(object)['id' => 100, 'name' => 'Static Collection Item']]);

    // When calling `::collection` on `FileDiskCollection`, it should return an instance
    // of `FileDiskCollection` itself.
    $collectionInstance = FileDiskCollection::collection($items);

    expect($collectionInstance)->toBeInstanceOf(FileDiskCollection::class);

    // Verify the 'collects' property value within the created instance.
    // ResourceCollection::collection method sets the 'collects' property of the *new* collection instance
    // to the class name of the resource *on which collection() was called*.
    $reflectionProperty = new \ReflectionProperty($collectionInstance, 'collects');
    $reflectionProperty->setAccessible(true);
    expect($reflectionProperty->getValue($collectionInstance))->toBe(FileDiskCollection::class);
});

test('FileDiskCollection::collection static method with wrap property correctly wraps the data', function () {
    $items = collect([(object)['id' => 101, 'name' => 'Wrapped Item']]);

    // Create an anonymous class that explicitly sets a static `$wrap` property and extends FileDiskCollection.
    $wrappedCollectionClass = new class($items) extends FileDiskCollection {
        public static $wrap = 'wrapped_items';
        public $collects = DummyResource::class; // This ensures items are transformed by DummyResource.
    };

    $collectionInstance = $wrappedCollectionClass::collection($items);

    expect($collectionInstance)->toBeInstanceOf($wrappedCollectionClass::class);

    $request = Request::create('/');
    $result = $collectionInstance->toArray($request);

    // Now, ResourceCollection will apply the 'wrap'.
    expect($result)
        ->toBeArray()
        ->toHaveKey('wrapped_items');

    expect($result['wrapped_items'])
        ->toBeArray()
        ->toHaveCount(1);

    expect($result['wrapped_items'][0])->toEqual([
        'id' => 101,
        'name' => 'Wrapped Item',
        'transformed' => true,
    ]);
});

test('constructor accepts null resource', function () {
    $collection = new FileDiskCollection(null);
    expect($collection)->toBeInstanceOf(FileDiskCollection::class);
    expect($collection->toArray(Request::create('/')))->toBeArray()->toBeEmpty();
});

test('constructor accepts an empty collection', function () {
    $collection = new FileDiskCollection(Collection::make([]));
    expect($collection)->toBeInstanceOf(FileDiskCollection::class);
    expect($collection->toArray(Request::create('/')))->toBeArray()->toBeEmpty();
});

test('constructor accepts a non-collection array', function () {
    $collection = new FileDiskCollection([(object)['id' => 102]]);
    expect($collection)->toBeInstanceOf(FileDiskCollection::class);
    // When no 'collects' is specified and items are simple objects, ResourceCollection's default behavior
    // is often to return the items as is, or an empty array if not resolvable.
    // For this test, we verify it doesn't crash and returns an array.
    // The exact content depends on the ResourceCollection's internal defaults when `collects` is null.
    // Here, it would be an array of the original objects as it defaults to `new JsonResource` wrapping if `collects` is not set.
    $result = $collection->toArray(Request::create('/'));
    expect($result)
        ->toBeArray()
        ->toHaveCount(1)
        ->and($result[0])->toEqual((object)['id' => 102]); // No transformation applied without `collects`.
});
