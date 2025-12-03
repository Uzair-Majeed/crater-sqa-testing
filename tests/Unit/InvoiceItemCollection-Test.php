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
    public ?string $description; // Added description property to match InvoiceItemResource expectations

    public function __construct(int $id, string $name, float $price, ?string $description = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->description = $description; // Set the new property
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

    // Instantiate TestInvoiceItemData with a description
    $item1 = new TestInvoiceItemData(1, 'Service A', 100.00, 'Detailed description for Service A');
    $item2 = new TestInvoiceItemData(2, 'Product B', 25.50, 'Details about Product B');

    // The collection provided to the InvoiceItemCollection constructor
    $dataCollection = new Collection([$item1, $item2]);

    // Instantiate the collection under test
    $invoiceItemCollection = new InvoiceItemCollection($dataCollection);

    // Expected output: Each item is transformed by InvoiceItemResource.
    // Based on the error trace, InvoiceItemResource expects and outputs 'id', 'name', 'description', 'price'.
    $expectedArray = [
        [
            'id' => $item1->id,
            'name' => $item1->name,
            'description' => $item1->description,
            'price' => $item1->price,
        ],
        [
            'id' => $item2->id,
            'name' => $item2->name,
            'description' => $item2->description,
            'price' => $item2->price,
        ],
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

    // TestInvoiceItemData object with description
    $item1 = new TestInvoiceItemData(3, 'License C', 500.00, 'Software License Details');
    
    // An array item: Must also include 'description' to be processed consistently by InvoiceItemResource.
    // If InvoiceItemResource uses properties directly, this would fail without robust handling,
    // but the error message specifically pointed to TestInvoiceItemData. Assuming array is handled as an object.
    $item2 = ['id' => 4, 'name' => 'Support D', 'price' => 75.00, 'description' => 'Annual Support Package']; 
    
    // An stdClass item: Must also include 'description' property to be processed by InvoiceItemResource.
    $item3 = (object)['id' => 5, 'name' => 'Consulting E', 'price' => 200.00, 'description' => 'Consulting Hours']; 

    $dataCollection = new Collection([$item1, $item2, $item3]);

    $invoiceItemCollection = new InvoiceItemCollection($dataCollection);

    // Expected output based on InvoiceItemResource transforming all items to a consistent structure:
    $expectedArray = [
        [
            'id' => $item1->id,
            'name' => $item1->name,
            'description' => $item1->description,
            'price' => $item1->price,
        ],
        // Item2 (array) is processed by InvoiceItemResource. Assuming it produces the same structure.
        [
            'id' => $item2['id'],
            'name' => $item2['name'],
            'description' => $item2['description'],
            'price' => $item2['price'],
        ],
        // Item3 (stdClass) is processed by InvoiceItemResource.
        [
            'id' => $item3->id,
            'name' => $item3->name,
            'description' => $item3->description,
            'price' => $item3->price,
        ],
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

    // Instantiate TestInvoiceItemData with a description
    $itemData = new TestInvoiceItemData(1, 'Test Item', 99.99, 'A detailed description');
    $dataCollection = new Collection([$itemData]);

    $invoiceItemCollection = new InvoiceItemCollection($dataCollection);

    // Act
    $result = $invoiceItemCollection->toArray($mockedRequest);

    // Assert
    // The observable outcome verifies the parent logic (which uses the request) was applied.
    $expectedArray = [
        [
            'id' => $itemData->id,
            'name' => $itemData->name,
            'description' => $itemData->description,
            'price' => $itemData->price,
        ],
    ];
    expect($result)->toEqual($expectedArray);

    // Implicitly verifies `parent::toArray` was called with `$mockedRequest`
    // because `ResourceCollection` iterates and calls `toArray($request)` on internal resources.
    // If the mock `Request` method was not called, this test would fail due to Mockery's expectation.
});

test('toArray handles null items in the collection gracefully by filtering them out', function () {
    // Arrange
    $request = m::mock(Request::class);
    $request->shouldReceive('json')->andReturn(null)->byDefault();

    // Instantiate TestInvoiceItemData with a description
    $item1 = new TestInvoiceItemData(1, 'Valid Item', 10.00, 'Description for item 1');
    $item2 = null; // A null item
    $item3 = new TestInvoiceItemData(3, 'Another Valid Item', 20.00, 'Description for item 3');

    $dataCollection = new Collection([$item1, $item2, $item3]);

    $invoiceItemCollection = new InvoiceItemCollection($dataCollection);

    // Expected output: InvoiceItemResource transforms valid items, null items are filtered out.
    $expectedArray = [
        [
            'id' => $item1->id,
            'name' => $item1->name,
            'description' => $item1->description,
            'price' => $item1->price,
        ],
        [
            'id' => $item3->id,
            'name' => $item3->name,
            'description' => $item3->description,
            'price' => $item3->price,
        ],
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
    // InvoiceItemResource::toArray itself should handle non-Request inputs or nulls.
    $nonRequest = new stdClass(); // Or null, or a string, etc.

    // Instantiate TestInvoiceItemData with a description
    $itemData = new TestInvoiceItemData(1, 'Test Item', 99.99, 'Description for non-request test');
    $dataCollection = new Collection([$itemData]);

    $invoiceItemCollection = new InvoiceItemCollection($dataCollection);

    // Expected output: The item should still be transformed as the InvoiceItemResource
    // can handle a non-Request object without error, as it usually only uses the request
    // for context, not for critical operations that would fail if it's not a Request instance.
    $expectedArray = [
        [
            'id' => $itemData->id,
            'name' => $itemData->name,
            'description' => $itemData->description,
            'price' => $itemData->price,
        ],
    ];

    // Act
    $result = $invoiceItemCollection->toArray($nonRequest);

    // Assert
    expect($result)->toEqual($expectedArray);
    expect(count($result))->toBe(1);
});