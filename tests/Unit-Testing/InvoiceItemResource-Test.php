<?php

use Crater\Http\Resources\CustomFieldValueResource;
use Crater\Http\Resources\InvoiceItemResource;
use Crater\Http\Resources\TaxResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
uses(\Mockery::class);

test('toArray transforms all direct properties and includes existing relations', function () {
    // 1. Arrange - Mock the underlying model instance
    $mockModel = Mockery::mock(new \stdClass());
    $mockModel->id = 1;
    $mockModel->name = 'Service Item';
    $mockModel->description = 'Maintenance service for a product.';
    $mockModel->discount_type = 'percentage';
    $mockModel->price = 150.00;
    $mockModel->quantity = 2;
    $mockModel->unit_name = 'hours';
    $mockModel->discount = 10.00; // 10% discount
    $mockModel->discount_val = 30.00; // 150*2 * 0.10 = 30
    $mockModel->tax = 27.00; // (300 - 30) * 0.10 = 27 (assuming 10% tax)
    $mockModel->total = 297.00; // 300 - 30 + 27 = 297
    $mockModel->invoice_id = 101;
    $mockModel->item_id = 5;
    $mockModel->company_id = 1;
    $mockModel->base_price = 135.00;
    $mockModel->exchange_rate = 1.1;
    $mockModel->base_discount_val = 27.00;
    $mockModel->base_tax = 24.30;
    $mockModel->base_total = 267.30;
    $mockModel->recurring_invoice_id = null;

    $mockTaxCollection = collect([
        (object)['id' => 1, 'name' => 'VAT', 'rate' => 10, 'type' => 'percentage'],
        (object)['id' => 2, 'name' => 'Service Tax', 'rate' => 5, 'type' => 'percentage'],
    ]);
    $mockFieldCollection = collect([
        (object)['id' => 10, 'label' => 'Service Date', 'value' => '2023-10-26'],
        (object)['id' => 11, 'label' => 'Technician', 'value' => 'John Doe'],
    ]);

    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = $mockFieldCollection;

    $mockModel->shouldReceive('taxes')
              ->andReturnSelf()
              ->shouldReceive('exists')
              ->andReturn(true);

    $mockModel->shouldReceive('fields')
              ->andReturnSelf()
              ->shouldReceive('exists')
              ->andReturn(true);

    $request = Mockery::mock(Request::class);
    $resource = new InvoiceItemResource($mockModel);

    // 2. Act
    $result = $resource->toArray($request);

    // 3. Assert
    expect($result)->toBeArray();
    expect($result)->toMatchArray([
        'id' => 1,
        'name' => 'Service Item',
        'description' => 'Maintenance service for a product.',
        'discount_type' => 'percentage',
        'price' => 150.00,
        'quantity' => 2,
        'unit_name' => 'hours',
        'discount' => 10.00,
        'discount_val' => 30.00,
        'tax' => 27.00,
        'total' => 297.00,
        'invoice_id' => 101,
        'item_id' => 5,
        'company_id' => 1,
        'base_price' => 135.00,
        'exchange_rate' => 1.1,
        'base_discount_val' => 27.00,
        'base_tax' => 24.30,
        'base_total' => 267.30,
        'recurring_invoice_id' => null,
    ]);

    expect($result['taxes'])->toBeInstanceOf(ResourceCollection::class);
    expect($result['taxes'])->toHaveCount(2);
    expect($result['taxes']->first())->toBeInstanceOf(TaxResource::class);
    expect($result['taxes']->first()->resource)->toEqual($mockTaxCollection->first());

    expect($result['fields'])->toBeInstanceOf(ResourceCollection::class);
    expect($result['fields'])->toHaveCount(2);
    expect($result['fields']->first())->toBeInstanceOf(CustomFieldValueResource::class);
    expect($result['fields']->first()->resource)->toEqual($mockFieldCollection->first());
});

test('toArray does not include relations if they do not exist', function () {
    // 1. Arrange
    $mockModel = Mockery::mock(new \stdClass());
    $mockModel->id = 2;
    $mockModel->name = 'Product A';
    $mockModel->description = null;
    $mockModel->discount_type = null;
    $mockModel->price = 50.00;
    $mockModel->quantity = 1;
    $mockModel->unit_name = 'unit';
    $mockModel->discount = 0;
    $mockModel->discount_val = 0;
    $mockModel->tax = 0;
    $mockModel->total = 50.00;
    $mockModel->invoice_id = 102;
    $mockModel->item_id = 6;
    $mockModel->company_id = 1;
    $mockModel->base_price = 50.00;
    $mockModel->exchange_rate = 1.0;
    $mockModel->base_discount_val = 0;
    $mockModel->base_tax = 0;
    $mockModel->base_total = 50.00;
    $mockModel->recurring_invoice_id = null;

    $mockModel->taxes = collect([]); // Ensure property is set, even if empty
    $mockModel->fields = collect([]);

    $mockModel->shouldReceive('taxes')
              ->andReturnSelf()
              ->shouldReceive('exists')
              ->andReturn(false);

    $mockModel->shouldReceive('fields')
              ->andReturnSelf()
              ->shouldReceive('exists')
              ->andReturn(false);

    $request = Mockery::mock(Request::class);
    $resource = new InvoiceItemResource($mockModel);

    // 2. Act
    $result = $resource->toArray($request);

    // 3. Assert
    expect($result)->toBeArray();
    expect($result)->toMatchArray([
        'id' => 2,
        'name' => 'Product A',
        'description' => null,
        'discount_type' => null,
        'price' => 50.00,
        'quantity' => 1,
        'unit_name' => 'unit',
        'discount' => 0,
        'discount_val' => 0,
        'tax' => 0,
        'total' => 50.00,
        'invoice_id' => 102,
        'item_id' => 6,
        'company_id' => 1,
        'base_price' => 50.00,
        'exchange_rate' => 1.0,
        'base_discount_val' => 0,
        'base_tax' => 0,
        'base_total' => 50.00,
        'recurring_invoice_id' => null,
    ]);
    expect($result)->not->toHaveKey('taxes');
    expect($result)->not->toHaveKey('fields');
});

test('toArray handles null or missing properties gracefully', function () {
    // Arrange - Mock an instance with all properties explicitly null
    $mockModel = Mockery::mock(new \stdClass());
    $mockModel->id = null;
    $mockModel->name = null;
    $mockModel->description = null;
    $mockModel->discount_type = null;
    $mockModel->price = null;
    $mockModel->quantity = null;
    $mockModel->unit_name = null;
    $mockModel->discount = null;
    $mockModel->discount_val = null;
    $mockModel->tax = null;
    $mockModel->total = null;
    $mockModel->invoice_id = null;
    $mockModel->item_id = null;
    $mockModel->company_id = null;
    $mockModel->base_price = null;
    $mockModel->exchange_rate = null;
    $mockModel->base_discount_val = null;
    $mockModel->base_tax = null;
    $mockModel->base_total = null;
    $mockModel->recurring_invoice_id = null;

    $mockModel->taxes = collect([]);
    $mockModel->fields = collect([]);

    $mockModel->shouldReceive('taxes')
              ->andReturnSelf()
              ->shouldReceive('exists')
              ->andReturn(false);

    $mockModel->shouldReceive('fields')
              ->andReturnSelf()
              ->shouldReceive('exists')
              ->andReturn(false);

    $request = Mockery::mock(Request::class);
    $resource = new InvoiceItemResource($mockModel);

    // Act
    $result = $resource->toArray($request);

    // Assert
    expect($result)->toBeArray();
    expect($result)->toMatchArray([
        'id' => null,
        'name' => null,
        'description' => null,
        'discount_type' => null,
        'price' => null,
        'quantity' => null,
        'unit_name' => null,
        'discount' => null,
        'discount_val' => null,
        'tax' => null,
        'total' => null,
        'invoice_id' => null,
        'item_id' => null,
        'company_id' => null,
        'base_price' => null,
        'exchange_rate' => null,
        'base_discount_val' => null,
        'base_tax' => null,
        'base_total' => null,
        'recurring_invoice_id' => null,
    ]);
    expect($result)->not->toHaveKey('taxes');
    expect($result)->not->toHaveKey('fields');
});

test('toArray handles a mix of existing and non-existing relations', function () {
    // Arrange
    $mockModel = Mockery::mock(new \stdClass());
    $mockModel->id = 3;
    $mockModel->name = 'Mixed Item';
    $mockModel->description = 'Item with only taxes.';
    $mockModel->discount_type = 'fixed';
    $mockModel->price = 100.00;
    $mockModel->quantity = 1;
    $mockModel->unit_name = 'item';
    $mockModel->discount = 10.00;
    $mockModel->discount_val = 10.00;
    $mockModel->tax = 9.00;
    $mockModel->total = 99.00;
    $mockModel->invoice_id = 103;
    $mockModel->item_id = 7;
    $mockModel->company_id = 2;
    $mockModel->base_price = 90.00;
    $mockModel->exchange_rate = 1.0;
    $mockModel->base_discount_val = 10.00;
    $mockModel->base_tax = 9.00;
    $mockModel->base_total = 99.00;
    $mockModel->recurring_invoice_id = null;

    $mockTaxCollection = collect([(object)['id' => 3, 'name' => 'Environmental Tax', 'rate' => 5, 'type' => 'percentage']]);
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->shouldReceive('taxes')
              ->andReturnSelf()
              ->shouldReceive('exists')
              ->andReturn(true);

    $mockModel->fields = collect([]);
    $mockModel->shouldReceive('fields')
              ->andReturnSelf()
              ->shouldReceive('exists')
              ->andReturn(false);

    $request = Mockery::mock(Request::class);
    $resource = new InvoiceItemResource($mockModel);

    // Act
    $result = $resource->toArray($request);

    // Assert
    expect($result)->toBeArray();
    expect($result)->toHaveKey('taxes');
    expect($result['taxes'])->toBeInstanceOf(ResourceCollection::class);
    expect($result['taxes'])->toHaveCount(1);
    expect($result['taxes']->first()->resource)->toEqual($mockTaxCollection->first());

    expect($result)->not->toHaveKey('fields');
});

test('toArray includes empty collection for relation if it exists but the collection is empty', function () {
    // Arrange
    $mockModel = Mockery::mock(new \stdClass());
    $mockModel->id = 4;
    $mockModel->name = 'Item With Empty Relations';
    $mockModel->description = 'Description here';
    $mockModel->discount_type = null;
    $mockModel->price = 200.00;
    $mockModel->quantity = 1;
    $mockModel->unit_name = 'unit';
    $mockModel->discount = 0;
    $mockModel->discount_val = 0;
    $mockModel->tax = 0;
    $mockModel->total = 200.00;
    $mockModel->invoice_id = 104;
    $mockModel->item_id = 8;
    $mockModel->company_id = 3;
    $mockModel->base_price = 200.00;
    $mockModel->exchange_rate = 1.0;
    $mockModel->base_discount_val = 0;
    $mockModel->base_tax = 0;
    $mockModel->base_total = 200.00;
    $mockModel->recurring_invoice_id = null;

    $mockModel->taxes = collect([]);
    $mockModel->shouldReceive('taxes')
              ->andReturnSelf()
              ->shouldReceive('exists')
              ->andReturn(true); // Taxes relation exists, but has no items

    $mockModel->fields = collect([]);
    $mockModel->shouldReceive('fields')
              ->andReturnSelf()
              ->shouldReceive('exists')
              ->andReturn(true); // Fields relation exists, but has no items

    $request = Mockery::mock(Request::class);
    $resource = new InvoiceItemResource($mockModel);

    // Act
    $result = $resource->toArray($request);

    // Assert
    expect($result)->toBeArray();
    expect($result)->toHaveKey('taxes');
    expect($result['taxes'])->toBeInstanceOf(ResourceCollection::class);
    expect($result['taxes'])->toHaveCount(0);

    expect($result)->toHaveKey('fields');
    expect($result['fields'])->toBeInstanceOf(ResourceCollection::class);
    expect($result['fields'])->toHaveCount(0);
});
