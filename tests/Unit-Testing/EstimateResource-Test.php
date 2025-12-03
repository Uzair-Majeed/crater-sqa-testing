<?php

use Mockery as m;
use Crater\Http\Resources\EstimateResource;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Resources\Json\JsonResource;

// Mock child resource classes (these are dependencies of EstimateResource)
use Crater\Http\Resources\EstimateItemResource;
use Crater\Http\Resources\CustomerResource;
use Crater\Http\Resources\UserResource;
use Crater\Http\Resources\TaxResource;
use Crater\Http\Resources\CustomFieldValueResource;
use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\CurrencyResource;

// Helper to mock the constructor of JsonResource instances for `new Resource($model)` calls
// This allows us to inspect the `resource` property that JsonResource internally holds.
function mockJsonResourceConstructorInstance($resourceData) {
    // Create a generic mock that *behaves* like a JsonResource instance
    $mock = m::mock(JsonResource::class);

    // Use reflection to set the protected `resource` property, mimicking JsonResource's internal state
    $reflection = new \ReflectionClass($mock);
    $property = $reflection->getProperty('resource');
    $property->setAccessible(true);
    $property->setValue($mock, $resourceData);

    return $mock;
}

// Global Mockery setup/teardown to ensure mocks are cleaned up between tests
beforeEach(function () {
    m::close(); // Ensure a clean slate for mocks for each test
});

afterEach(function () {
    m::close();
});

test('toArray includes all direct properties and omits relationships when they do not exist', function () {
    // 1. Mock the underlying Eloquent model that EstimateResource wraps
    $model = m::mock(\stdClass::class);

    // Set all direct properties with sample values
    $model->id = 1;
    $model->estimate_date = '2023-01-01';
    $model->expiry_date = '2023-01-31';
    $model->estimate_number = 'EST-001';
    $model->status = 'sent';
    $model->reference_number = 'REF-123';
    $model->tax_per_item = true;
    $model->discount_per_item = false;
    $model->notes = 'Some test notes.';
    $model->discount = 10.50;
    $model->discount_type = 'percentage';
    $model->discount_val = 5.00;
    $model->sub_total = 100.00;
    $model->total = 95.00;
    $model->tax = 5.00;
    $model->unique_hash = 'abcdef12345';
    $model->creator_id = 10;
    $model->template_name = 'default';
    $model->customer_id = 20;
    $model->exchange_rate = 1.0;
    $model->base_discount_val = 5.00;
    $model->base_sub_total = 100.00;
    $model->base_total = 95.00;
    $model->base_tax = 5.00;
    $model->sequence_number = 1;
    $model->currency_id = 1;
    $model->formattedExpiryDate = 'Jan 31, 2023';
    $model->formattedEstimateDate = 'Jan 01, 2023';
    $model->estimatePdfUrl = 'http://example.com/estimate.pdf';
    $model->sales_tax_type = 'exclusive';
    $model->sales_tax_address_type = 'billing';

    // Mock the `getNotes` method on the model, as it's called explicitly
    $model->shouldReceive('getNotes')->once()->andReturn($model->notes);

    // Mock all relationship methods to return a generic relation object where `exists()` returns false
    $mockRelationNotExists = m::mock(BelongsTo::class); // BelongsTo used as a generic relation type
    $mockRelationNotExists->shouldReceive('exists')->andReturn(false);

    $model->shouldReceive('items')->andReturn($mockRelationNotExists);
    $model->shouldReceive('customer')->andReturn($mockRelationNotExists);
    $model->shouldReceive('creator')->andReturn($mockRelationNotExists);
    $model->shouldReceive('taxes')->andReturn($mockRelationNotExists);
    $model->shouldReceive('fields')->andReturn($mockRelationNotExists);
    $model->shouldReceive('company')->andReturn($mockRelationNotExists);
    $model->shouldReceive('currency')->andReturn($mockRelationNotExists);

    // 2. Instantiate the resource with the mock model
    $resource = new EstimateResource($model);

    // 3. Create a mock request (not directly used by properties, so a simple mock suffices)
    $request = m::mock(Request::class);

    // 4. Call the toArray method
    $result = $resource->toArray($request);

    // 5. Assert the structure and values of the output array
    expect($result)->toBeArray();
    expect($result)->toHaveKeys([
        'id', 'estimate_date', 'expiry_date', 'estimate_number', 'status',
        'reference_number', 'tax_per_item', 'discount_per_item', 'notes',
        'discount', 'discount_type', 'discount_val', 'sub_total', 'total',
        'tax', 'unique_hash', 'creator_id', 'template_name', 'customer_id',
        'exchange_rate', 'base_discount_val', 'base_sub_total', 'base_total',
        'base_tax', 'sequence_number', 'currency_id', 'formatted_expiry_date',
        'formatted_estimate_date', 'estimate_pdf_url', 'sales_tax_type',
        'sales_tax_address_type',
    ]);

    // Assert specific values of direct properties
    expect($result['id'])->toBe(1);
    expect($result['estimate_date'])->toBe('2023-01-01');
    expect($result['expiry_date'])->toBe('2023-01-31');
    expect($result['estimate_number'])->toBe('EST-001');
    expect($result['status'])->toBe('sent');
    expect($result['reference_number'])->toBe('REF-123');
    expect($result['tax_per_item'])->toBeTrue();
    expect($result['discount_per_item'])->toBeFalse();
    expect($result['notes'])->toBe('Some test notes.');
    expect($result['discount'])->toBe(10.50);
    expect($result['discount_type'])->toBe('percentage');
    expect($result['discount_val'])->toBe(5.00);
    expect($result['sub_total'])->toBe(100.00);
    expect($result['total'])->toBe(95.00);
    expect($result['tax'])->toBe(5.00);
    expect($result['unique_hash'])->toBe('abcdef12345');
    expect($result['creator_id'])->toBe(10);
    expect($result['template_name'])->toBe('default');
    expect($result['customer_id'])->toBe(20);
    expect($result['exchange_rate'])->toBe(1.0);
    expect($result['base_discount_val'])->toBe(5.00);
    expect($result['base_sub_total'])->toBe(100.00);
    expect($result['base_total'])->toBe(95.00);
    expect($result['base_tax'])->toBe(5.00);
    expect($result['sequence_number'])->toBe(1);
    expect($result['currency_id'])->toBe(1);
    expect($result['formatted_expiry_date'])->toBe('Jan 31, 2023');
    expect($result['formatted_estimate_date'])->toBe('Jan 01, 2023');
    expect($result['estimatePdfUrl'])->toBe('http://example.com/estimate.pdf');
    expect($result['sales_tax_type'])->toBe('exclusive');
    expect($result['sales_tax_address_type'])->toBe('billing');

    // Assert that conditional relationship keys are NOT present when `exists()` returns false
    expect($result)->not->toHaveKey('items');
    expect($result)->not->toHaveKey('customer');
    expect($result)->not->toHaveKey('creator');
    expect($result)->not->toHaveKey('taxes');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('company');
    expect($result)->not->toHaveKey('currency');
});

test('toArray includes all relationships when they exist and are loaded', function () {
    // 1. Mock the underlying Eloquent model
    $model = m::mock(\stdClass::class);

    // Set minimal direct properties to satisfy requirements (full coverage in previous test)
    $model->id = 1;
    $model->estimate_date = '2023-01-01'; // Non-conditional properties must always be present
    $model->expiry_date = '2023-01-31';
    $model->estimate_number = 'EST-001';
    $model->status = 'sent';
    $model->reference_number = 'REF-123';
    $model->tax_per_item = true;
    $model->discount_per_item = false;
    $model->notes = 'Notes for relationships test.';
    $model->discount = 10.0; $model->discount_type = 'fixed'; $model->discount_val = 10.0;
    $model->sub_total = 100.0; $model->total = 90.0; $model->tax = 0.0;
    $model->unique_hash = 'hash123'; $model->creator_id = 1; $model->template_name = 'basic';
    $model->customer_id = 2; $model->exchange_rate = 1.0;
    $model->base_discount_val = 10.0; $model->base_sub_total = 100.0; $model->base_total = 90.0;
    $model->base_tax = 0.0; $model->sequence_number = 1; $model->currency_id = 1;
    $model->formattedExpiryDate = 'Jan 31, 2023'; $model->formattedEstimateDate = 'Jan 01, 2023';
    $model->estimatePdfUrl = 'http://example.com/pdf/1';
    $model->sales_tax_type = 'exclusive'; $model->sales_tax_address_type = 'billing';

    $model->shouldReceive('getNotes')->once()->andReturn($model->notes);

    // Prepare mock data for each relationship. These will be "loaded" onto the model.
    $mockItemsData = new Collection([m::mock(\stdClass::class, ['id' => 101, 'name' => 'Item A'])]);
    $mockCustomerData = m::mock(\stdClass::class, ['id' => 201, 'name' => 'Test Customer']);
    $mockCreatorData = m::mock(\stdClass::class, ['id' => 301, 'name' => 'Test Creator']);
    $mockTaxesData = new Collection([m::mock(\stdClass::class, ['id' => 401, 'name' => 'Sales Tax'])]);
    $mockFieldsData = new Collection([m::mock(\stdClass::class, ['id' => 501, 'label' => 'Custom Field 1'])]);
    $mockCompanyData = m::mock(\stdClass::class, ['id' => 601, 'name' => 'Test Company']);
    $mockCurrencyData = m::mock(\stdClass::class, ['id' => 701, 'code' => 'USD']);

    // Set these mock data as properties on the model, as JsonResource accesses them directly (e.g., `$this->items`)
    $model->items = $mockItemsData;
    $model->customer = $mockCustomerData;
    $model->creator = $mockCreatorData;
    $model->taxes = $mockTaxesData;
    $model->fields = $mockFieldsData;
    $model->company = $mockCompanyData;
    $model->currency = $mockCurrencyData;

    // Mock relationship methods (`items()`, `customer()`, etc.) to return a relation object
    // where `exists()` returns true.
    $mockRelationExists = function() {
        $mock = m::mock(BelongsTo::class); // Generic relation mock
        $mock->shouldReceive('exists')->andReturn(true);
        return $mock;
    };
    $model->shouldReceive('items')->andReturn($mockRelationExists());
    $model->shouldReceive('customer')->andReturn($mockRelationExists());
    $model->shouldReceive('creator')->andReturn($mockRelationExists());
    $model->shouldReceive('taxes')->andReturn($mockRelationExists());
    $model->shouldReceive('fields')->andReturn($mockRelationExists());
    $model->shouldReceive('company')->andReturn($mockRelationExists());
    $model->shouldReceive('currency')->andReturn($mockRelationExists());

    // Mock static `::collection()` calls for collection resources (e.g., `EstimateItemResource::collection()`)
    // Mockery's `alias` allows mocking static methods on classes.
    $mockedItemCollectionOutput = ['item_resource_output_1', 'item_resource_output_2'];
    m::mock('alias:'.EstimateItemResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with(m::on(fn($arg) => $arg === $mockItemsData)) // Ensure the correct collection is passed
        ->andReturn($mockedItemCollectionOutput);

    $mockedTaxCollectionOutput = ['tax_resource_output_1'];
    m::mock('alias:'.TaxResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with(m::on(fn($arg) => $arg === $mockTaxesData))
        ->andReturn($mockedTaxCollectionOutput);

    $mockedFieldsCollectionOutput = ['field_resource_output_1'];
    m::mock('alias:'.CustomFieldValueResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with(m::on(fn($arg) => $arg === $mockFieldsData))
        ->andReturn($mockedFieldsCollectionOutput);

    // Mock single resource instantiation using Mockery's `overload` feature.
    // This allows us to intercept `new Class(...)` calls and return our controlled mock.
    m::mock('overload:'.CustomerResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with(m::on(fn($arg) => $arg === $mockCustomerData))
        ->andReturnUsing(fn($resource) => mockJsonResourceConstructorInstance($resource)); // Returns our custom mock instance

    m::mock('overload:'.UserResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with(m::on(fn($arg) => $arg === $mockCreatorData))
        ->andReturnUsing(fn($resource) => mockJsonResourceConstructorInstance($resource));

    m::mock('overload:'.CompanyResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with(m::on(fn($arg) => $arg === $mockCompanyData))
        ->andReturnUsing(fn($resource) => mockJsonResourceConstructorInstance($resource));

    m::mock('overload:'.CurrencyResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with(m::on(fn($arg) => $arg === $mockCurrencyData))
        ->andReturnUsing(fn($resource) => mockJsonResourceConstructorInstance($resource));

    // 2. Instantiate the resource under test
    $resource = new EstimateResource($model);
    $request = m::mock(Request::class);

    // 3. Call the toArray method
    $result = $resource->toArray($request);

    // 4. Assert direct properties (a quick check to ensure they are present)
    expect($result)->toBeArray();
    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe(1);

    // 5. Assert that all conditional relationship keys are present
    expect($result)->toHaveKeys([
        'items', 'customer', 'creator', 'taxes', 'fields', 'company', 'currency',
    ]);

    // Assert collection resources return the mocked output from their `::collection()` calls
    expect($result['items'])->toBe($mockedItemCollectionOutput);
    expect($result['taxes'])->toBe($mockedTaxCollectionOutput);
    expect($result['fields'])->toBe($mockedFieldsCollectionOutput);

    // Assert single resources are instances of their mocked types and that their
    // internal `resource` property (which JsonResource uses) holds the correct model data.
    expect($result['customer'])->toBeInstanceOf(CustomerResource::class);
    expect($result['customer']->resource)->toBe($mockCustomerData);

    expect($result['creator'])->toBeInstanceOf(UserResource::class);
    expect($result['creator']->resource)->toBe($mockCreatorData);

    expect($result['company'])->toBeInstanceOf(CompanyResource::class);
    expect($result['company']->resource)->toBe($mockCompanyData);

    expect($result['currency'])->toBeInstanceOf(CurrencyResource::class);
    expect($result['currency']->resource)->toBe($mockCurrencyData);
});

test('toArray handles null and empty values for all properties and omitted relationships', function () {
    $model = m::mock(\stdClass::class);

    // Set all properties to null or empty equivalent values
    $model->id = null; // ID can be null before persistence
    $model->estimate_date = null;
    $model->expiry_date = null;
    $model->estimate_number = ''; // Empty string
    $model->status = '';
    $model->reference_number = null;
    $model->tax_per_item = false;
    $model->discount_per_item = false;
    $model->notes = ''; // Empty notes
    $model->discount = 0.00;
    $model->discount_type = null;
    $model->discount_val = 0.00;
    $model->sub_total = 0.00;
    $model->total = 0.00;
    $model->tax = 0.00;
    $model->unique_hash = '';
    $model->creator_id = null;
    $model->template_name = null;
    $model->customer_id = null;
    $model->exchange_rate = 0.0; // Edge case for numeric
    $model->base_discount_val = 0.00;
    $model->base_sub_total = 0.00;
    $model->base_total = 0.00;
    $model->base_tax = 0.00;
    $model->sequence_number = 0;
    $model->currency_id = null;
    $model->formattedExpiryDate = null;
    $model->formattedEstimateDate = null;
    $model->estimatePdfUrl = null;
    $model->sales_tax_type = null;
    $model->sales_tax_address_type = null;

    // Mock `getNotes` to return an empty string
    $model->shouldReceive('getNotes')->once()->andReturn($model->notes);

    // Mock all relationship methods to return a relation object where `exists()` returns false
    $mockRelationNotExists = m::mock(BelongsTo::class);
    $mockRelationNotExists->shouldReceive('exists')->andReturn(false);
    $model->shouldReceive('items')->andReturn($mockRelationNotExists);
    $model->shouldReceive('customer')->andReturn($mockRelationNotExists);
    $model->shouldReceive('creator')->andReturn($mockRelationNotExists);
    $model->shouldReceive('taxes')->andReturn($mockRelationNotExists);
    $model->shouldReceive('fields')->andReturn($mockRelationNotExists);
    $model->shouldReceive('company')->andReturn($mockRelationNotExists);
    $model->shouldReceive('currency')->andReturn($mockRelationNotExists);

    $resource = new EstimateResource($model);
    $request = m::mock(Request::class);
    $result = $resource->toArray($request);

    expect($result)->toBeArray();
    expect($result['id'])->toBeNull();
    expect($result['estimate_date'])->toBeNull();
    expect($result['expiry_date'])->toBeNull();
    expect($result['estimate_number'])->toBe('');
    expect($result['status'])->toBe('');
    expect($result['reference_number'])->toBeNull();
    expect($result['tax_per_item'])->toBeFalse();
    expect($result['discount_per_item'])->toBeFalse();
    expect($result['notes'])->toBe('');
    expect($result['discount'])->toBe(0.00);
    expect($result['discount_type'])->toBeNull();
    expect($result['discount_val'])->toBe(0.00);
    expect($result['sub_total'])->toBe(0.00);
    expect($result['total'])->toBe(0.00);
    expect($result['tax'])->toBe(0.00);
    expect($result['unique_hash'])->toBe('');
    expect($result['creator_id'])->toBeNull();
    expect($result['template_name'])->toBeNull();
    expect($result['customer_id'])->toBeNull();
    expect($result['exchange_rate'])->toBe(0.0);
    expect($result['base_discount_val'])->toBe(0.00);
    expect($result['base_sub_total'])->toBe(0.00);
    expect($result['base_total'])->toBe(0.00);
    expect($result['base_tax'])->toBe(0.00);
    expect($result['sequence_number'])->toBe(0);
    expect($result['currency_id'])->toBeNull();
    expect($result['formatted_expiry_date'])->toBeNull();
    expect($result['formatted_estimate_date'])->toBeNull();
    expect($result['estimatePdfUrl'])->toBeNull();
    expect($result['sales_tax_type'])->toBeNull();
    expect($result['sales_tax_address_type'])->toBeNull();

    // Assert that conditional relationship keys are not present
    expect($result)->not->toHaveKey('items');
    expect($result)->not->toHaveKey('customer');
});



