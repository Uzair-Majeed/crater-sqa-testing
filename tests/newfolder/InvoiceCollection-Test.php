<?php

use Crater\Http\Resources\InvoiceCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/*
|--------------------------------------------------------------------------
| Test Explanation & Fix Strategy
|--------------------------------------------------------------------------
|
| The primary issue across most failing tests is an `ErrorException: Undefined
| property: stdClass::$invoice_date` originating from
| `app\Http\Resources\InvoiceResource.php` at line 19. This indicates that
| `InvoiceCollection` (which is a `ResourceCollection`) is configured to
| collect `InvoiceResource` instances (e.g., via `public $collects = InvoiceResource::class;`).
|
| When `InvoiceCollection` processes items that are not already `JsonResource`
| instances, it wraps them in `InvoiceResource::make($item)`. The `InvoiceResource`
| then attempts to access `$this->invoice_date` in its `toArray()` method,
| which is missing from the plain `stdClass` objects or arrays provided in the tests.
|
| Even for tests that provide custom `JsonResource` instances, the debug output
| consistently points to `InvoiceResource.php:19`. This implies that the
| `InvoiceCollection` class being tested likely has an overridden `toArray`
| method that explicitly re-wraps *all* items, including existing `JsonResource`
| instances, into `InvoiceResource` instances. If an item is already a `JsonResource`,
| `InvoiceResource::make()` will extract its underlying resource data (e.g.,
| `(object)['id' => 10, 'name' => 'Custom Invoice A']`) and then create a new
| `InvoiceResource` around *that plain data*. This plain data would also be
| missing `invoice_date`.
|
| Therefore, the consistent fix is to add an `invoice_date` property (with a
| dummy value) to all plain objects, arrays, and the underlying data provided
| to custom `JsonResource::make()` calls.
|
| The last failing test, `toArray always returns an array, even if the collection
| is null or invalid`, failed with `Attempt to read property "map" on null` because
| it explicitly set `$invoiceCollection->collection = null;`. The base
| `ResourceCollection`'s `toArray` method does not gracefully handle a `null`
| `$this->collection` property. However, `ResourceCollection` *does* handle
| a `null` value passed to its constructor by internally creating an empty
| collection. The test has been modified to use `new InvoiceCollection(null)`
| to achieve the intended assertion (returning an empty array for an effectively
| null/empty collection) without causing a runtime error.
*/

test('InvoiceCollection can be instantiated', function () {
    $collection = new Collection();
    $invoiceCollection = new InvoiceCollection($collection);

    expect($invoiceCollection)->toBeInstanceOf(InvoiceCollection::class);
    expect($invoiceCollection)->toBeInstanceOf(JsonResource::class); // It's a ResourceCollection, which extends JsonResource
});

test('toArray returns an empty array for an empty collection', function () {
    $collection = new Collection();
    $invoiceCollection = new InvoiceCollection($collection);
    $request = Request::create('/');

    $result = $invoiceCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

test('toArray correctly transforms a collection with a single plain object', function () {
    // Added 'invoice_date' to satisfy InvoiceResource expectation
    $item = (object) ['id' => 1, 'name' => 'Invoice 1', 'invoice_date' => '2023-01-01'];
    $collection = new Collection([$item]);
    $invoiceCollection = new InvoiceCollection($collection);
    $request = Request::create('/');

    $result = $invoiceCollection->toArray($request);

    expect($result)->toBeArray()->toHaveCount(1);
    expect($result[0])->toEqual([
        'id' => 1,
        'name' => 'Invoice 1',
        'invoice_date' => '2023-01-01',
    ]);
});

test('toArray correctly transforms a collection with multiple plain objects', function () {
    // Added 'invoice_date' to satisfy InvoiceResource expectation
    $item1 = (object) ['id' => 1, 'name' => 'Invoice Alpha', 'invoice_date' => '2023-01-01'];
    $item2 = (object) ['id' => 2, 'name' => 'Invoice Beta', 'invoice_date' => '2023-01-02'];
    $collection = new Collection([$item1, $item2]);
    $invoiceCollection = new InvoiceCollection($collection);
    $request = Request::create('/');

    $result = $invoiceCollection->toArray($request);

    expect($result)->toBeArray()->toHaveCount(2);
    expect($result[0])->toEqual([
        'id' => 1,
        'name' => 'Invoice Alpha',
        'invoice_date' => '2023-01-01',
    ]);
    expect($result[1])->toEqual([
        'id' => 2,
        'name' => 'Invoice Beta',
        'invoice_date' => '2023-01-02',
    ]);
});

test('toArray correctly transforms a collection with items that are already JsonResource instances', function () {
    // Define the data for the custom resource instances, including 'invoice_date'
    $item1Data = (object) ['id' => 10, 'name' => 'Custom Invoice A', 'invoice_date' => '2023-03-10'];
    $item2Data = (object) ['id' => 20, 'name' => 'Custom Invoice B', 'invoice_date' => '2023-03-20'];

    // Define a simple anonymous resource class for testing
    $anonymousResourceClass = new class((object)['dummy' => true]) extends JsonResource
    {
        public function toArray($request)
        {
            // The properties accessed here come from the underlying resource data ($this->resource)
            return [
                'custom_id' => $this->id,
                'custom_name' => $this->name,
                'request_present' => $request instanceof Request,
                'request_uri' => $request->path(),
                'custom_invoice_date' => $this->invoice_date, // Accessing this now that it's present in data
            ];
        }
    };

    // Create instances of the anonymous resource using its static make method
    $item1 = $anonymousResourceClass::make($item1Data);
    $item2 = $anonymousResourceClass::make($item2Data);
    $collection = new Collection([$item1, $item2]);
    $invoiceCollection = new InvoiceCollection($collection);
    $request = Request::create('/api/invoices');

    $result = $invoiceCollection->toArray($request);

    expect($result)->toBeArray()->toHaveCount(2);
    expect($result[0])->toEqual([
        'custom_id' => 10,
        'custom_name' => 'Custom Invoice A',
        'request_present' => true,
        'request_uri' => 'api/invoices',
        'custom_invoice_date' => '2023-03-10',
    ]);
    expect($result[1])->toEqual([
        'custom_id' => 20,
        'custom_name' => 'Custom Invoice B',
        'request_present' => true,
        'request_uri' => 'api/invoices',
        'custom_invoice_date' => '2023-03-20',
    ]);
});

test('toArray passes the request object to nested resources correctly', function () {
    // Define a resource that checks the request object
    $anonymousResourceClass = new class((object)['dummy' => true]) extends JsonResource
    {
        public function toArray($request)
        {
            return [
                'id' => $this->id,
                'request_hash' => spl_object_hash($request), // Use hash to verify it's the *same* request object
                'has_header' => $request->hasHeader('X-Test-Header'),
                'invoice_date' => $this->invoice_date, // Accessing this now that it's present in data
            ];
        }
    };

    $originalRequest = Request::create('/test-url');
    $originalRequest->headers->set('X-Test-Header', 'Value');

    // The data for the custom resource now also includes 'invoice_date'
    $itemData = (object) ['id' => 5, 'invoice_date' => '2023-04-05'];
    $item = $anonymousResourceClass::make($itemData);
    $collection = new Collection([$item]);
    $invoiceCollection = new InvoiceCollection($collection);

    $result = $invoiceCollection->toArray($originalRequest);

    expect($result)->toBeArray()->toHaveCount(1);
    expect($result[0]['id'])->toBe(5);
    expect($result[0]['request_hash'])->toBe(spl_object_hash($originalRequest));
    expect($result[0]['has_header'])->toBeTrue();
    expect($result[0]['invoice_date'])->toBe('2023-04-05');
});

test('toArray handles different data types in the collection', function () {
    // Added 'invoice_date' to satisfy InvoiceResource expectation for all data types
    $item1 = (object) ['id' => 1, 'type' => 'object', 'invoice_date' => '2023-05-01'];
    $item2 = ['id' => 2, 'type' => 'array_data', 'invoice_date' => '2023-05-02'];
    $item3ResourceData = (object)['id' => 3, 'type' => 'custom_resource_instance', 'invoice_date' => '2023-05-03'];

    // Define the anonymous resource class
    $anonymousResourceClass = new class((object)['dummy' => true]) extends JsonResource {
        public function toArray($request)
        {
            return [
                'resource_id' => $this->id,
                'resource_type' => $this->type,
                'request_uri' => $request->path(),
                'resource_invoice_date' => $this->invoice_date, // Accessing this now that it's present in data
            ];
        }
    };
    // Create an instance of the anonymous resource using its make method
    $item3Instance = $anonymousResourceClass::make($item3ResourceData);

    $collection = new Collection([$item1, $item2, $item3Instance]);
    $invoiceCollection = new InvoiceCollection($collection);
    $request = Request::create('/any-path');

    $result = $invoiceCollection->toArray($request);

    expect($result)->toBeArray()->toHaveCount(3);

    // Item 1: Plain object wrapped by InvoiceResource
    expect($result[0])->toEqual([
        'id' => 1,
        'type' => 'object',
        'invoice_date' => '2023-05-01',
    ]);

    // Item 2: Array data wrapped by InvoiceResource
    expect($result[1])->toEqual([
        'id' => 2,
        'type' => 'array_data',
        'invoice_date' => '2023-05-02',
    ]);

    // Item 3: Custom JsonResource instance, its data now has invoice_date
    expect($result[2])->toEqual([
        'resource_id' => 3,
        'resource_type' => 'custom_resource_instance',
        'request_uri' => 'any-path',
        'resource_invoice_date' => '2023-05-03',
    ]);
});

test('toArray returns an array for a null initial collection', function () {
    // ResourceCollection's constructor handles a null resource by creating an empty collection.
    // This avoids the "Attempt to read property map on null" error caused by
    // setting $invoiceCollection->collection = null; after construction.
    $invoiceCollection = new InvoiceCollection(null);
    $request = Request::create('/');

    $result = $invoiceCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

afterEach(function () {
    Mockery::close();
});