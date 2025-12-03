<?php

use Crater\Http\Resources\InvoiceCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

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
    $item = (object) ['id' => 1, 'name' => 'Invoice 1'];
    $collection = new Collection([$item]);
    $invoiceCollection = new InvoiceCollection($collection);
    $request = Request::create('/');

    $result = $invoiceCollection->toArray($request);

    // When ResourceCollection wraps a plain object in JsonResource (default behavior),
    // and JsonResource processes a simple object, it converts its public properties to an array.
    expect($result)->toBeArray()->toHaveCount(1);
    expect($result[0])->toEqual([
        'id' => 1,
        'name' => 'Invoice 1',
    ]);
});

test('toArray correctly transforms a collection with multiple plain objects', function () {
    $item1 = (object) ['id' => 1, 'name' => 'Invoice Alpha'];
    $item2 = (object) ['id' => 2, 'name' => 'Invoice Beta'];
    $collection = new Collection([$item1, $item2]);
    $invoiceCollection = new InvoiceCollection($collection);
    $request = Request::create('/');

    $result = $invoiceCollection->toArray($request);

    expect($result)->toBeArray()->toHaveCount(2);
    expect($result[0])->toEqual([
        'id' => 1,
        'name' => 'Invoice Alpha',
    ]);
    expect($result[1])->toEqual([
        'id' => 2,
        'name' => 'Invoice Beta',
    ]);
});

test('toArray correctly transforms a collection with items that are already JsonResource instances', function () {
    // Define a simple resource for testing within the test scope
    $resourceClass = new class((object)['dummy' => true]) extends JsonResource
    {
        public function toArray($request)
        {
            return [
                'custom_id' => $this->id,
                'custom_name' => $this->name,
                'request_present' => $request instanceof Request,
                'request_uri' => $request->path(),
            ];
        }
    };

    $item1 = $resourceClass->make((object) ['id' => 10, 'name' => 'Custom Invoice A']);
    $item2 = $resourceClass->make((object) ['id' => 20, 'name' => 'Custom Invoice B']);
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
    ]);
    expect($result[1])->toEqual([
        'custom_id' => 20,
        'custom_name' => 'Custom Invoice B',
        'request_present' => true,
        'request_uri' => 'api/invoices',
    ]);
});

test('toArray passes the request object to nested resources correctly', function () {
    // Define a resource that checks the request object
    $resourceClass = new class((object)['dummy' => true]) extends JsonResource
    {
        public function toArray($request)
        {
            return [
                'id' => $this->id,
                'request_hash' => spl_object_hash($request), // Use hash to verify it's the *same* request object
                'has_header' => $request->hasHeader('X-Test-Header'),
            ];
        }
    };

    $originalRequest = Request::create('/test-url');
    $originalRequest->headers->set('X-Test-Header', 'Value');

    $item = $resourceClass->make((object) ['id' => 5]);
    $collection = new Collection([$item]);
    $invoiceCollection = new InvoiceCollection($collection);

    $result = $invoiceCollection->toArray($originalRequest);

    expect($result)->toBeArray()->toHaveCount(1);
    expect($result[0]['id'])->toBe(5);
    expect($result[0]['request_hash'])->toBe(spl_object_hash($originalRequest));
    expect($result[0]['has_header'])->toBeTrue();
});

test('toArray handles different data types in the collection', function () {
    $item1 = (object) ['id' => 1, 'type' => 'object'];
    $item2 = ['id' => 2, 'type' => 'array_data'];
    $item3 = new class((object)['id' => 3, 'type' => 'custom_resource_instance']) extends JsonResource {
        public function toArray($request)
        {
            return ['resource_id' => $this->id, 'resource_type' => $this->type, 'request_uri' => $request->path()];
        }
    };
    $collection = new Collection([$item1, $item2, $item3->make((object)['id' => 3, 'type' => 'custom_resource_instance'])]);
    $invoiceCollection = new InvoiceCollection($collection);
    $request = Request::create('/any-path');

    $result = $invoiceCollection->toArray($request);

    expect($result)->toBeArray()->toHaveCount(3);

    // Item 1: Plain object wrapped by JsonResource default behavior
    expect($result[0])->toEqual([
        'id' => 1,
        'type' => 'object',
    ]);

    // Item 2: Array data wrapped by JsonResource default behavior
    expect($result[1])->toEqual([
        'id' => 2,
        'type' => 'array_data',
    ]);

    // Item 3: Custom JsonResource instance
    expect($result[2])->toEqual([
        'resource_id' => 3,
        'resource_type' => 'custom_resource_instance',
        'request_uri' => 'any-path',
    ]);
});

test('toArray always returns an array, even if the collection is null or invalid (handled by ResourceCollection)', function () {
    // ResourceCollection's constructor expects an `Arrayable` or array.
    // If the collection is explicitly set to null after construction,
    // or if parent::toArray has unusual behavior, we should still get an array.
    // This mostly tests ResourceCollection's robustness.
    $invoiceCollection = new InvoiceCollection(new Collection());
    $invoiceCollection->collection = null; // Simulate internal state change if it could happen
    $request = Request::create('/');

    $result = $invoiceCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty(); // ResourceCollection typically defaults to empty array for null collection
});




afterEach(function () {
    Mockery::close();
});
