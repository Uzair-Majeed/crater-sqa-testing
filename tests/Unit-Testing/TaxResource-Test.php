<?php

uses(\Mockery::class);
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\Relation;
use Crater\Http\Resources\TaxResource;
use Crater\Http\Resources\TaxTypeResource;
use Crater\Http\Resources\CurrencyResource;

// Clean up Mockery mocks after each test to prevent interference
beforeEach(function () {
    Mockery::close();
});

test('tax resource transforms data correctly when all relationships exist', function () {
    // 1. Mock the underlying model instances for relationships
    $mockTaxTypeModel = (object) ['id' => 101, 'type' => 'VAT'];
    $mockCurrencyModel = (object) ['id' => 201, 'code' => 'USD', 'name' => 'US Dollar'];

    // 2. Mock the main Tax model instance
    $mockTaxModel = Mockery::mock();
    $mockTaxModel->id = 1;
    $mockTaxModel->tax_type_id = 10;
    $mockTaxModel->invoice_id = 100;
    $mockTaxModel->estimate_id = 200;
    $mockTaxModel->invoice_item_id = 300;
    $mockTaxModel->estimate_item_id = 400;
    $mockTaxModel->item_id = 500;
    $mockTaxModel->company_id = 600;
    $mockTaxModel->name = 'Sales Tax';
    $mockTaxModel->amount = 10.50;
    $mockTaxModel->percent = 0.05;
    $mockTaxModel->compound_tax = false;
    $mockTaxModel->base_amount = 200.00;
    $mockTaxModel->currency_id = 1;
    $mockTaxModel->recurring_invoice_id = 700;

    // Mock the taxType relationship method and property
    $mockTaxTypeRelation = Mockery::mock(Relation::class);
    $mockTaxTypeRelation->shouldReceive('exists')->andReturn(true)->once();
    $mockTaxModel->shouldReceive('taxType')->andReturn($mockTaxTypeRelation)->once();
    $mockTaxModel->taxType = $mockTaxTypeModel; // Direct property access for $this->taxType->type and resource construction

    // Mock the currency relationship method and property
    $mockCurrencyRelation = Mockery::mock(Relation::class);
    $mockCurrencyRelation->shouldReceive('exists')->andReturn(true)->once();
    $mockTaxModel->shouldReceive('currency')->andReturn($mockCurrencyRelation)->once();
    $mockTaxModel->currency = $mockCurrencyModel; // Direct property access for resource construction

    // Mock the Http request (it's passed but not directly used by this resource's logic)
    $mockRequest = Mockery::mock(Request::class);

    // Mock the dependent resources (TaxTypeResource, CurrencyResource) using Mockery's overload feature
    // This allows us to intercept their construction and `toArray` calls.
    Mockery::mock('overload:' . TaxTypeResource::class)
        ->shouldReceive('__construct')
        ->with($mockTaxTypeModel)
        ->once()
        ->andReturnSelf()
        ->shouldReceive('toArray')
        ->once()
        ->andReturn(['id' => 101, 'type' => 'VAT_transformed_from_resource']);

    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldReceive('__construct')
        ->with($mockCurrencyModel)
        ->once()
        ->andReturnSelf()
        ->shouldReceive('toArray')
        ->once()
        ->andReturn(['id' => 201, 'code' => 'USD_transformed_from_resource']);

    // Instantiate the resource with the mocked model
    $resource = new TaxResource($mockTaxModel);

    // Transform the resource to an array
    $result = $resource->toArray($mockRequest);

    // Assertions for direct properties
    expect($result)->toBeArray()
        ->toHaveKeys([
            'id', 'tax_type_id', 'invoice_id', 'estimate_id', 'invoice_item_id',
            'estimate_item_id', 'item_id', 'company_id', 'name', 'amount',
            'percent', 'compound_tax', 'base_amount', 'currency_id', 'type',
            'recurring_invoice_id', 'tax_type', 'currency',
        ]);

    expect($result['id'])->toBe(1);
    expect($result['tax_type_id'])->toBe(10);
    expect($result['invoice_id'])->toBe(100);
    expect($result['estimate_id'])->toBe(200);
    expect($result['invoice_item_id'])->toBe(300);
    expect($result['estimate_item_id'])->toBe(400);
    expect($result['item_id'])->toBe(500);
    expect($result['company_id'])->toBe(600);
    expect($result['name'])->toBe('Sales Tax');
    expect($result['amount'])->toBe(10.50);
    expect($result['percent'])->toBe(0.05);
    expect($result['compound_tax'])->toBeFalse();
    expect($result['base_amount'])->toBe(200.00);
    expect($result['currency_id'])->toBe(1);
    expect($result['recurring_invoice_id'])->toBe(700);

    // Assertions for relationship-derived properties and conditional resources
    expect($result['type'])->toBe('VAT'); // From $this->taxType->type
    expect($result['tax_type'])->toBe(['id' => 101, 'type' => 'VAT_transformed_from_resource']);
    expect($result['currency'])->toBe(['id' => 201, 'code' => 'USD_transformed_from_resource']);
});

test('tax resource transforms data correctly when taxType relationship does not exist', function () {
    // 1. Mock the underlying model instance for Currency
    $mockCurrencyModel = (object) ['id' => 201, 'code' => 'USD', 'name' => 'US Dollar'];

    // 2. Mock the main Tax model instance
    $mockTaxModel = Mockery::mock();
    $mockTaxModel->id = 1;
    $mockTaxModel->tax_type_id = null; // No tax type
    $mockTaxModel->invoice_id = 100;
    $mockTaxModel->estimate_id = 200;
    $mockTaxModel->invoice_item_id = 300;
    $mockTaxModel->estimate_item_id = 400;
    $mockTaxModel->item_id = 500;
    $mockTaxModel->company_id = 600;
    $mockTaxModel->name = 'Sales Tax';
    $mockTaxModel->amount = 10.50;
    $mockTaxModel->percent = 0.05;
    $mockTaxModel->compound_tax = false;
    $mockTaxModel->base_amount = 200.00;
    $mockTaxModel->currency_id = 1;
    $mockTaxModel->recurring_invoice_id = 700;

    // Mock the taxType relationship to not exist
    $mockTaxTypeRelation = Mockery::mock(Relation::class);
    $mockTaxTypeRelation->shouldReceive('exists')->andReturn(false)->once();
    $mockTaxModel->shouldReceive('taxType')->andReturn($mockTaxTypeRelation)->once();
    $mockTaxModel->taxType = null; // Explicitly null for $this->taxType->type to avoid errors

    // Mock the currency relationship to exist
    $mockCurrencyRelation = Mockery::mock(Relation::class);
    $mockCurrencyRelation->shouldReceive('exists')->andReturn(true)->once();
    $mockTaxModel->shouldReceive('currency')->andReturn($mockCurrencyRelation)->once();
    $mockTaxModel->currency = $mockCurrencyModel;

    $mockRequest = Mockery::mock(Request::class);

    // TaxTypeResource should NOT be instantiated
    Mockery::mock('overload:' . TaxTypeResource::class)
        ->shouldNotReceive('__construct');

    // CurrencyResource should be instantiated
    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldReceive('__construct')
        ->with($mockCurrencyModel)
        ->once()
        ->andReturnSelf()
        ->shouldReceive('toArray')
        ->once()
        ->andReturn(['id' => 201, 'code' => 'USD_transformed_from_resource']);

    $resource = new TaxResource($mockTaxModel);
    $result = $resource->toArray($mockRequest);

    // Assertions
    expect($result)->toBeArray();
    expect($result['type'])->toBeNull(); // Should be null because $this->taxType is null
    expect($result['tax_type'])->toBeNull(); // Should be null when relationship doesn't exist
    expect($result['currency'])->toBe(['id' => 201, 'code' => 'USD_transformed_from_resource']);
});

test('tax resource transforms data correctly when currency relationship does not exist', function () {
    // 1. Mock the underlying model instance for TaxType
    $mockTaxTypeModel = (object) ['id' => 101, 'type' => 'VAT'];

    // 2. Mock the main Tax model instance
    $mockTaxModel = Mockery::mock();
    $mockTaxModel->id = 1;
    $mockTaxModel->tax_type_id = 10;
    $mockTaxModel->invoice_id = 100;
    $mockTaxModel->estimate_id = 200;
    $mockTaxModel->invoice_item_id = 300;
    $mockTaxModel->estimate_item_id = 400;
    $mockTaxModel->item_id = 500;
    $mockTaxModel->company_id = 600;
    $mockTaxModel->name = 'Sales Tax';
    $mockTaxModel->amount = 10.50;
    $mockTaxModel->percent = 0.05;
    $mockTaxModel->compound_tax = false;
    $mockTaxModel->base_amount = 200.00;
    $mockTaxModel->currency_id = null; // No currency
    $mockTaxModel->recurring_invoice_id = 700;

    // Mock the taxType relationship to exist
    $mockTaxTypeRelation = Mockery::mock(Relation::class);
    $mockTaxTypeRelation->shouldReceive('exists')->andReturn(true)->once();
    $mockTaxModel->shouldReceive('taxType')->andReturn($mockTaxTypeRelation)->once();
    $mockTaxModel->taxType = $mockTaxTypeModel;

    // Mock the currency relationship to not exist
    $mockCurrencyRelation = Mockery::mock(Relation::class);
    $mockCurrencyRelation->shouldReceive('exists')->andReturn(false)->once();
    $mockTaxModel->shouldReceive('currency')->andReturn($mockCurrencyRelation)->once();
    $mockTaxModel->currency = null; // Explicitly null for resource construction

    $mockRequest = Mockery::mock(Request::class);

    // TaxTypeResource should be instantiated
    Mockery::mock('overload:' . TaxTypeResource::class)
        ->shouldReceive('__construct')
        ->with($mockTaxTypeModel)
        ->once()
        ->andReturnSelf()
        ->shouldReceive('toArray')
        ->once()
        ->andReturn(['id' => 101, 'type' => 'VAT_transformed_from_resource']);

    // CurrencyResource should NOT be instantiated
    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldNotReceive('__construct');

    $resource = new TaxResource($mockTaxModel);
    $result = $resource->toArray($mockRequest);

    // Assertions
    expect($result)->toBeArray();
    expect($result['type'])->toBe('VAT'); // Should still be present
    expect($result['tax_type'])->toBe(['id' => 101, 'type' => 'VAT_transformed_from_resource']);
    expect($result['currency'])->toBeNull(); // Should be null when relationship doesn't exist
});

test('tax resource transforms data correctly when both taxType and currency relationships do not exist', function () {
    // 1. Mock the main Tax model instance
    $mockTaxModel = Mockery::mock();
    $mockTaxModel->id = 1;
    $mockTaxModel->tax_type_id = null;
    $mockTaxModel->invoice_id = 100;
    $mockTaxModel->estimate_id = 200;
    $mockTaxModel->invoice_item_id = 300;
    $mockTaxModel->estimate_item_id = 400;
    $mockTaxModel->item_id = 500;
    $mockTaxModel->company_id = 600;
    $mockTaxModel->name = 'Sales Tax';
    $mockTaxModel->amount = 10.50;
    $mockTaxModel->percent = 0.05;
    $mockTaxModel->compound_tax = false;
    $mockTaxModel->base_amount = 200.00;
    $mockTaxModel->currency_id = null;
    $mockTaxModel->recurring_invoice_id = 700;

    // Mock the taxType relationship to not exist
    $mockTaxTypeRelation = Mockery::mock(Relation::class);
    $mockTaxTypeRelation->shouldReceive('exists')->andReturn(false)->once();
    $mockTaxModel->shouldReceive('taxType')->andReturn($mockTaxTypeRelation)->once();
    $mockTaxModel->taxType = null;

    // Mock the currency relationship to not exist
    $mockCurrencyRelation = Mockery::mock(Relation::class);
    $mockCurrencyRelation->shouldReceive('exists')->andReturn(false)->once();
    $mockTaxModel->shouldReceive('currency')->andReturn($mockCurrencyRelation)->once();
    $mockTaxModel->currency = null;

    $mockRequest = Mockery::mock(Request::class);

    // Expect neither TaxTypeResource nor CurrencyResource to be instantiated
    Mockery::mock('overload:' . TaxTypeResource::class)
        ->shouldNotReceive('__construct');

    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldNotReceive('__construct');

    $resource = new TaxResource($mockTaxModel);
    $result = $resource->toArray($mockRequest);

    // Assertions
    expect($result)->toBeArray();
    expect($result['type'])->toBeNull(); // Should be null if taxType is null
    expect($result['tax_type'])->toBeNull();
    expect($result['currency'])->toBeNull();
});

test('tax resource handles missing model properties gracefully', function () {
    // Mock models for existing relationships
    $mockTaxTypeModel = (object) ['id' => 101, 'type' => 'VAT'];
    $mockCurrencyModel = (object) ['id' => 201, 'code' => 'USD', 'name' => 'US Dollar'];

    // Create a minimal mock model, purposely omitting many properties
    $mockTaxModel = Mockery::mock();
    $mockTaxModel->id = 1;
    $mockTaxModel->name = 'Minimal Tax';
    $mockTaxModel->amount = 5.00;
    // Missing properties like tax_type_id, invoice_id, etc., will implicitly return null by PHP/Laravel.

    // Ensure taxType and currency relationships exist for testing conditional logic
    $mockTaxTypeRelation = Mockery::mock(Relation::class);
    $mockTaxTypeRelation->shouldReceive('exists')->andReturn(true)->once();
    $mockTaxModel->shouldReceive('taxType')->andReturn($mockTaxTypeRelation)->once();
    $mockTaxModel->taxType = $mockTaxTypeModel;

    $mockCurrencyRelation = Mockery::mock(Relation::class);
    $mockCurrencyRelation->shouldReceive('exists')->andReturn(true)->once();
    $mockTaxModel->shouldReceive('currency')->andReturn($mockCurrencyRelation)->once();
    $mockTaxModel->currency = $mockCurrencyModel;

    $mockRequest = Mockery::mock(Request::class);

    // Mock dependent resources
    Mockery::mock('overload:' . TaxTypeResource::class)
        ->shouldReceive('__construct')
        ->with($mockTaxTypeModel)
        ->once()
        ->andReturnSelf()
        ->shouldReceive('toArray')
        ->once()
        ->andReturn(['id' => 101, 'type' => 'VAT_transformed_from_resource']);

    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldReceive('__construct')
        ->with($mockCurrencyModel)
        ->once()
        ->andReturnSelf()
        ->shouldReceive('toArray')
        ->once()
        ->andReturn(['id' => 201, 'code' => 'USD_transformed_from_resource']);

    $resource = new TaxResource($mockTaxModel);
    $result = $resource->toArray($mockRequest);

    // Assertions for explicitly set properties
    expect($result['id'])->toBe(1);
    expect($result['name'])->toBe('Minimal Tax');
    expect($result['amount'])->toBe(5.00);
    expect($result['type'])->toBe('VAT');
    expect($result['tax_type'])->toBe(['id' => 101, 'type' => 'VAT_transformed_from_resource']);
    expect($result['currency'])->toBe(['id' => 201, 'code' => 'USD_transformed_from_resource']);

    // Assertions for properties that were NOT set on the mock model (should be null)
    expect($result['tax_type_id'])->toBeNull();
    expect($result['invoice_id'])->toBeNull();
    expect($result['estimate_id'])->toBeNull();
    expect($result['invoice_item_id'])->toBeNull();
    expect($result['estimate_item_id'])->toBeNull();
    expect($result['item_id'])->toBeNull();
    expect($result['company_id'])->toBeNull();
    expect($result['percent'])->toBeNull();
    expect($result['compound_tax'])->toBeNull();
    expect($result['base_amount'])->toBeNull();
    expect($result['currency_id'])->toBeNull();
    expect($result['recurring_invoice_id'])->toBeNull();
});
