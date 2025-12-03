<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Crater\Http\Resources\InvoiceItemCollection;
use Mockery as m;

// Helper class for data items, simulating Eloquent models or simple DTOs
class TestInvoiceItemData
{
    public int $id;
    public string $name;
    public float $price;

    public function __construct(int $id, string $name, float $price)
    {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
    }
}

// Ensure Mockery is closed after each test
afterEach(function () {
    m::close();
});

test('toArray correctly delegates to parent::toArray and transforms items with default JsonResource behavior', function () {
    // Arrange
    $request = m::mock(Request::class);
    $request->shouldReceive('json')->andReturn(null)->byDefault(); // Default mock behavior

    $item1 = new TestInvoiceItemData(1, 'Service A', 100.00);
    $item2 = new TestInvoiceItemData(2, 'Product B', 25.50);

    // The collection provided to the InvoiceItemCollection constructor
    $dataCollection = new Collection([$item1, $item2]);

    // Instantiate the collection under test
    $invoiceItemCollection = new InvoiceItemCollection($dataCollection);

    // Expected output: ResourceCollection with no `collects` property set
    // will wrap each item with a default `JsonResource`.
    // `JsonResource`'s `toArray` method will cast public properties of the resource object to an array.
    $expectedArray = [
        (array) $item1,
        (array) $item2,
    ];

    // Act
    $result = $invoiceItemCollection->toArray($request);

    // Assert
    expect($result)->toEqual($expectedArray);
    expect($result)->toBeArray();
    expect(count($result))->toBe(2);
});

test('toArray handles an empty collection', function () {
    // Arrange
    $request = m::mock(Request::class);
    $request->shouldReceive('json')->andReturn(null)->byDefault();

    $emptyDataCollection = new Collection([]);

    $invoiceItemCollection = new InvoiceItemCollection($emptyDataCollection);

    // Act
    $result = $invoiceItemCollection->toArray($request);

    // Assert
    expect($result)->toEqual([]);
    expect($result)->toBeArray();
    expect(count($result))->toBe(0);
});

test('toArray handles collection with mixed data types (objects and arrays)', function () {
    // Arrange
    $request = m::mock(Request::class);
    $request->shouldReceive('json')->andReturn(null)->byDefault();

    $item1 = new TestInvoiceItemData(3, 'License C', 500.00);
    $item2 = ['id' => 4, 'name' => 'Support D', 'price' => 75.00]; // An array item
    $item3 = (object)['id' => 5, 'name' => 'Consulting E', 'price' => 200.00]; // An stdClass item

    $dataCollection = new Collection([$item1, $item2, $item3]);

    $invoiceItemCollection = new InvoiceItemCollection($dataCollection);

    // Expected output based on default JsonResource wrapping:
    // - TestInvoiceItemData object: public properties cast to array
    // - Array: returned as is
    // - stdClass object: public properties cast to array
    $expectedArray = [
        (array) $item1,
        $item2,
        (array) $item3,
    ];

    // Act
    $result = $invoiceItemCollection->toArray($request);

    // Assert
    expect($result)->toEqual($expectedArray);
    expect($result)->toBeArray();
    expect(count($result))->toBe(3);
});

test('toArray passes through the request object to parent for resource transformation', function () {
    // Arrange
    $mockedRequest = m::mock(Request::class);
    // Explicitly make the request respond to a method call to confirm it's passed
    $mockedRequest->shouldReceive('method')->andReturn('GET')->once();
    $mockedRequest->shouldReceive('json')->andReturn(null)->byDefault();

    $itemData = new TestInvoiceItemData(1, 'Test Item', 99.99);
    $dataCollection = new Collection([$itemData]);

    $invoiceItemCollection = new InvoiceItemCollection($dataCollection);

    // Act
    $result = $invoiceItemCollection->toArray($mockedRequest);

    // Assert
    // The observable outcome verifies the parent logic (which uses the request) was applied.
    $expectedArray = [(array) $itemData];
    expect($result)->toEqual($expectedArray);

    // Implicitly verifies `parent::toArray` was called with `$mockedRequest`
    // because `ResourceCollection` iterates and calls `toArray($request)` on internal resources.
    // If the mock `Request` method was not called, this test would fail due to Mockery's expectation.
});

test('toArray handles null items in the collection gracefully by filtering them out', function () {
    // Arrange
    $request = m::mock(Request::class);
    $request->shouldReceive('json')->andReturn(null)->byDefault();

    $item1 = new TestInvoiceItemData(1, 'Valid Item', 10.00);
    $item2 = null; // A null item
    $item3 = new TestInvoiceItemData(3, 'Another Valid Item', 20.00);

    $dataCollection = new Collection([$item1, $item2, $item3]);

    $invoiceItemCollection = new InvoiceItemCollection($dataCollection);

    // Expected output: JsonResource::toArray(null) returns null.
    // ResourceCollection::toArray filters out null results from its mapping.
    $expectedArray = [
        (array) $item1,
        (array) $item3,
    ];

    // Act
    $result = $invoiceItemCollection->toArray($request);

    // Assert
    expect($result)->toEqual($expectedArray);
    expect(count($result))->toBe(2); // Null item should be filtered out
});

test('toArray handles non-Request object as parameter gracefully', function () {
    // Arrange
    // Although typically a Request object is expected, `ResourceCollection::toArray`
    // will just pass whatever it receives to the underlying resource's toArray.
    // JsonResource::toArray itself can handle non-Request inputs or nulls.
    $nonRequest = new stdClass(); // Or null, or a string, etc.

    $itemData = new TestInvoiceItemData(1, 'Test Item', 99.99);
    $dataCollection = new Collection([$itemData]);

    $invoiceItemCollection = new InvoiceItemCollection($dataCollection);

    // Expected output: The item should still be transformed as the parent method
    // (and default JsonResource) can handle a non-Request object without error,
    // as it usually only uses the request for context, not for critical operations
    // that would fail if it's not a Request instance.
    $expectedArray = [(array) $itemData];

    // Act
    $result = $invoiceItemCollection->toArray($nonRequest);

    // Assert
    expect($result)->toEqual($expectedArray);
    expect(count($result))->toBe(1);
});



