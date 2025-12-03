<?php

use Crater\Http\Resources\PaymentMethodCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Mockery; // Fix: Import Mockery

// Define a dummy resource class for testing purposes.
// This simulates the actual PaymentMethodResource that this collection would typically wrap.
class TestPaymentMethodResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => property_exists($this, 'type') ? $this->type : null, // Handle optional property
        ];
    }
}

test('toArray transforms a collection of items into an array of resources', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class); // Fix: Use Mockery::mock()

    $items = new Collection([
        (object)['id' => 1, 'name' => 'Credit Card', 'type' => 'card'],
        (object)['id' => 2, 'name' => 'PayPal', 'type' => 'paypal'],
    ]);

    // Create the collection, explicitly telling it what resource class to use for its items.
    // This is necessary because PaymentMethodCollection itself does not define a $collects property.
    $collection = PaymentMethodCollection::make($items)
        ->collects(TestPaymentMethodResource::class);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0])->toEqual([
            'id' => 1,
            'name' => 'Credit Card',
            'type' => 'card',
        ])
        ->and($result[1])->toEqual([
            'id' => 2,
            'name' => 'PayPal',
            'type' => 'paypal',
        ]);
});

test('toArray returns an empty array when the underlying collection is empty', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class); // Fix: Use Mockery::mock()
    $items = new Collection([]);

    $collection = PaymentMethodCollection::make($items)
        ->collects(TestPaymentMethodResource::class);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('toArray handles plain array input for the collection', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class); // Fix: Use Mockery::mock()
    $items = [
        (object)['id' => 3, 'name' => 'Cash', 'type' => 'cash'],
    ];

    $collection = PaymentMethodCollection::make($items)
        ->collects(TestPaymentMethodResource::class);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0])->toEqual([
            'id' => 3,
            'name' => 'Cash',
            'type' => 'cash',
        ]);
});

test('toArray correctly handles items with missing properties that the resource might expect', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class); // Fix: Use Mockery::mock()

    // Item without the 'type' property
    $items = new Collection([
        (object)['id' => 4, 'name' => 'Bank Transfer'],
    ]);

    $collection = PaymentMethodCollection::make($items)
        ->collects(TestPaymentMethodResource::class);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0])->toEqual([
            'id' => 4,
            'name' => 'Bank Transfer',
            'type' => null, // Should default to null as handled by TestPaymentMethodResource
        ]);
});

test('toArray passes the request instance to the underlying resource transformations', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class); // Fix: Use Mockery::mock()
    $mockRequest->shouldReceive('input')
        ->with('include_details')
        ->andReturn(true);
    // Removed ->getMock() as Mockery::mock() already returns a mock object that can be chained.

    // Define a resource that uses the request
    class RequestAwareTestPaymentMethodResource extends JsonResource
    {
        public function toArray($request)
        {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'details_included' => $request->input('include_details'),
            ];
        }
    }

    $items = new Collection([
        (object)['id' => 5, 'name' => 'Cheque'],
    ]);

    $collection = PaymentMethodCollection::make($items)
        ->collects(RequestAwareTestPaymentMethodResource::class);

    // Act
    $result = $collection->toArray($mockRequest);

    // Assert
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0])->toEqual([
            'id' => 5,
            'name' => 'Cheque',
            'details_included' => true,
        ]);
});

// Clean up the dynamically defined test class to avoid conflicts in other tests if this were part of a larger suite
afterEach(function () {
    if (class_exists('TestPaymentMethodResource')) {
        // Not strictly necessary as they are scoped to the test file for Pest.
        // But good practice if dynamically defining classes.
    }
    if (class_exists('RequestAwareTestPaymentMethodResource')) {
        // Same as above.
    }
});