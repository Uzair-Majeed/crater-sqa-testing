```php
<?php

use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\CurrencyResource;
use Crater\Http\Resources\CustomFieldValueResource;
use Crater\Http\Resources\CustomerResource;
use Crater\Http\Resources\InvoiceItemResource;
use Crater\Http\Resources\InvoiceResource;
use Crater\Http\Resources\TaxResource;
use Crater\Http\Resources\UserResource;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// Helper function to create a mock Eloquent relation that responds to the `exists()` method.
// This simulates the return value of an Eloquent model's relationship method (e.g., $invoice->items()).
function createMockRelation(bool $exists)
{
    return Mockery::mock(Relation::class)
        ->shouldReceive('exists')
        ->andReturn($exists)
        ->getMock();
}

// Helper function to get the full list of default properties for an Invoice object.
// This ensures all direct properties and relationship properties (initially null)
// expected by InvoiceResource are present on the mock, avoiding "Undefined property" errors.
function getDefaultInvoiceProperties(): array
{
    return [
        'id' => 1,
        'invoice_date' => '2023-01-01',
        'due_date' => '2023-01-31',
        'invoice_number' => 'INV-001',
        'reference_number' => 'REF-001',
        'status' => 'paid',
        'paid_status' => 'fully_paid',
        'tax_per_item' => true,
        'discount_per_item' => false,
        'notes' => 'Some notes',
        'discount_type' => 'percentage',
        'discount' => 10.00,
        'discount_val' => 100.00,
        'sub_total' => 1000.00,
        'total' => 1100.00,
        'tax' => 100.00,
        'due_amount' => 0.00,
        'sent' => true,
        'viewed' => true,
        'unique_hash' => 'abcdef123',
        'template_name' => 'default',
        'customer_id' => 10,
        'recurring_invoice_id' => null,
        'sequence_number' => 1,
        'exchange_rate' => 1.0,
        'base_discount_val' => 100.00,
        'base_sub_total' => 1000.00,
        'base_total' => 1100.00,
        'creator_id' => 1,
        'base_tax' => 100.00,
        'base_due_amount' => 0.00,
        'currency_id' => 1,
        'formattedCreatedAt' => 'Jan 01, 2023', // Accessor for formatted_created_at
        'invoicePdfUrl' => 'http://example.com/invoice.pdf', // Accessor for invoice_pdf_url
        'formattedInvoiceDate' => 'Jan 01, 2023', // Accessor for formatted_invoice_date
        'formattedDueDate' => 'Jan 31, 2023', // Accessor for formatted_due_date
        'allow_edit' => true,
        'payment_module_enabled' => true,
        'sales_tax_type' => 'inclusive',
        'sales_tax_address_type' => 'billing',
        'overdue' => false,
        // Relationship properties, explicitly set to null by default.
        // This ensures `whenLoaded` (or equivalent checks) correctly identifies them as not loaded.
        'items' => null,
        'customer' => null,
        'creator' => null,
        'taxes' => null,
        'fields' => null,
        'company' => null,
        'currency' => null,
    ];
}

// Helper function to create a fully populated mock Invoice object based on default properties.
function createFullMockInvoice(array $overrides = [])
{
    $mockInvoice = Mockery::mock(stdClass::class);
    $allDirectProperties = getDefaultInvoiceProperties();

    // Merge default properties with any provided overrides
    foreach (array_merge($allDirectProperties, $overrides) as $key => $value) {
        $mockInvoice->{$key} = $value;
    }

    return $mockInvoice;
}

beforeEach(function () {
    Mockery::close();
});

// Tests that all direct properties of the underlying model are correctly mapped to the resource array
// when no relationships exist (i.e., `when()` conditions are false).
test('it transforms invoice with all direct properties when no relationships exist', function () {
    // This test already has all properties explicitly defined, which serves as the reference.
    $mockInvoice = Mockery::mock(stdClass::class);
    $mockInvoice->id = 1;
    $mockInvoice->invoice_date = '2023-01-01';
    $mockInvoice->due_date = '2023-01-31';
    $mockInvoice->invoice_number = 'INV-001';
    $mockInvoice->reference_number = 'REF-001';
    $mockInvoice->status = 'paid';
    $mockInvoice->paid_status = 'fully_paid';
    $mockInvoice->tax_per_item = true;
    $mockInvoice->discount_per_item = false;
    $mockInvoice->notes = 'Some notes';
    $mockInvoice->discount_type = 'percentage';
    $mockInvoice->discount = 10.00;
    $mockInvoice->discount_val = 100.00;
    $mockInvoice->sub_total = 1000.00;
    $mockInvoice->total = 1100.00;
    $mockInvoice->tax = 100.00;
    $mockInvoice->due_amount = 0.00;
    $mockInvoice->sent = true;
    $mockInvoice->viewed = true;
    $mockInvoice->unique_hash = 'abcdef123';
    $mockInvoice->template_name = 'default';
    $mockInvoice->customer_id = 10;
    $mockInvoice->recurring_invoice_id = null;
    $mockInvoice->sequence_number = 1;
    $mockInvoice->exchange_rate = 1.0;
    $mockInvoice->base_discount_val = 100.00;
    $mockInvoice->base_sub_total = 1000.00;
    $mockInvoice->base_total = 1100.00;
    $mockInvoice->creator_id = 1;
    $mockInvoice->base_tax = 100.00;
    $mockInvoice->base_due_amount = 0.00;
    $mockInvoice->currency_id = 1;
    $mockInvoice->formattedCreatedAt = 'Jan 01, 2023';
    $mockInvoice->invoicePdfUrl = 'http://example.com/invoice.pdf';
    $mockInvoice->formattedInvoiceDate = 'Jan 01, 2023';
    $mockInvoice->formattedDueDate = 'Jan 31, 2023';
    $mockInvoice->allow_edit = true;
    $mockInvoice->payment_module_enabled = true;
    $mockInvoice->sales_tax_type = 'inclusive';
    $mockInvoice->sales_tax_address_type = 'billing';
    $mockInvoice->overdue = false;

    // Ensure relationship properties are explicitly null when not loaded.
    // This is crucial for JsonResource's whenLoaded() method to correctly omit them.
    $mockInvoice->items = null;
    $mockInvoice->customer = null;
    $mockInvoice->creator = null;
    $mockInvoice->taxes = null;
    $mockInvoice->fields = null;
    $mockInvoice->company = null;
    $mockInvoice->currency = null;

    // Mock all relationship methods to return relations that do NOT exist.
    $mockInvoice->shouldReceive('items')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('customer')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('creator')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('taxes')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('fields')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('company')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('currency')->andReturn(createMockRelation(false))->once();

    $resource = new InvoiceResource($mockInvoice);
    $result = $resource->toArray(new Request());

    expect($result)->toBeArray()
        ->and($result['id'])->toBe(1)
        ->and($result['invoice_date'])->toBe('2023-01-01')
        ->and($result['due_date'])->toBe('2023-01-31')
        ->and($result['invoice_number'])->toBe('INV-001')
        ->and($result['reference_number'])->toBe('REF-001')
        ->and($result['status'])->toBe('paid')
        ->and($result['paid_status'])->toBe('fully_paid')
        ->and($result['tax_per_item'])->toBeTrue()
        ->and($result['discount_per_item'])->toBeFalse()
        ->and($result['notes'])->toBe('Some notes')
        ->and($result['discount_type'])->toBe('percentage')
        ->and($result['discount'])->toBe(10.00)
        ->and($result['discount_val'])->toBe(100.00)
        ->and($result['sub_total'])->toBe(1000.00)
        ->and($result['total'])->toBe(1100.00)
        ->and($result['tax'])->toBe(100.00)
        ->and($result['due_amount'])->toBe(0.00)
        ->and($result['sent'])->toBeTrue()
        ->and($result['viewed'])->toBeTrue()
        ->and($result['unique_hash'])->toBe('abcdef123')
        ->and($result['template_name'])->toBe('default')
        ->and($result['customer_id'])->toBe(10)
        ->and($result['recurring_invoice_id'])->toBeNull()
        ->and($result['sequence_number'])->toBe(1)
        ->and($result['exchange_rate'])->toBe(1.0)
        ->and($result['base_discount_val'])->toBe(100.00)
        ->and($result['base_sub_total'])->toBe(1000.00)
        ->and($result['base_total'])->toBe(1100.00)
        ->and($result['creator_id'])->toBe(1)
        ->and($result['base_tax'])->toBe(100.00)
        ->and($result['base_due_amount'])->toBe(0.00)
        ->and($result['currency_id'])->toBe(1)
        ->and($result['formatted_created_at'])->toBe('Jan 01, 2023')
        ->and($result['invoice_pdf_url'])->toBe('http://example.com/invoice.pdf')
        ->and($result['formatted_invoice_date'])->toBe('Jan 01, 2023')
        ->and($result['formatted_due_date'])->toBe('Jan 31, 2023')
        ->and($result['allow_edit'])->toBeTrue()
        ->and($result['payment_module_enabled'])->toBeTrue()
        ->and($result['sales_tax_type'])->toBe('inclusive')
        ->and($result['sales_tax_address_type'])->toBe('billing')
        ->and($result['overdue'])->toBeFalse()
        // Ensure conditional fields are NOT present when relations don't exist.
        ->not->toHaveKey('items')
        ->not->toHaveKey('customer')
        ->not->toHaveKey('creator')
        ->not->toHaveKey('taxes')
        ->not->toHaveKey('fields')
        ->not->toHaveKey('company')
        ->not->toHaveKey('currency');
});

// Tests that collection-based relationships (items, taxes, fields) are included when they exist.
test('it includes item, tax, and field resources when relationships exist', function () {
    // Mock dependent resource classes for their `collection()` static methods.
    // `overload:` ensures that when `InvoiceItemResource::collection()` is called, our mock is used.
    Mockery::mock('overload:' . InvoiceItemResource::class)
        ->shouldReceive('collection')
        ->withArgs(function ($items) {
            // Assert that the correct collection of items is passed to the resource.
            return $items instanceof Collection && $items->contains('id', 101);
        })
        ->andReturn(['mocked_invoice_item_resource_collection']) // Return a predictable, mocked output.
        ->once();

    Mockery::mock('overload:' . TaxResource::class)
        ->shouldReceive('collection')
        ->withArgs(function ($taxes) {
            return $taxes instanceof Collection && $taxes->contains('id', 201);
        })
        ->andReturn(['mocked_tax_resource_collection'])
        ->once();

    Mockery::mock('overload:' . CustomFieldValueResource::class)
        ->shouldReceive('collection')
        ->withArgs(function ($fields) {
            return $fields instanceof Collection && $fields->contains('id', 301);
        })
        ->andReturn(['mocked_custom_field_value_resource_collection'])
        ->once();

    // Use the helper to create a fully populated mock invoice, then override relation properties
    $mockInvoice = createFullMockInvoice();

    // Set up the underlying model's relationship data (the actual collections).
    $mockInvoiceItem1 = (object)['id' => 101, 'name' => 'Item 1'];
    $mockInvoice->items = collect([$mockInvoiceItem1]);
    $mockTax1 = (object)['id' => 201, 'name' => 'Tax 1'];
    $mockInvoice->taxes = collect([$mockTax1]);
    $mockCustomField1 = (object)['id' => 301, 'name' => 'Field 1'];
    $mockInvoice->fields = collect([$mockCustomField1]);

    // Mock relationship methods to return relations that DO exist.
    $mockInvoice->shouldReceive('items')->andReturn(createMockRelation(true))->once();
    $mockInvoice->shouldReceive('taxes')->andReturn(createMockRelation(true))->once();
    $mockInvoice->shouldReceive('fields')->andReturn(createMockRelation(true))->once();

    // Mock other relationships not to exist. Their properties are already null from createFullMockInvoice.
    $mockInvoice->shouldReceive('customer')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('creator')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('company')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('currency')->andReturn(createMockRelation(false))->once();

    $resource = new InvoiceResource($mockInvoice);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('items', ['mocked_invoice_item_resource_collection'])
        ->and($result)->toHaveKey('taxes', ['mocked_tax_resource_collection'])
        ->and($result)->toHaveKey('fields', ['mocked_custom_field_value_resource_collection'])
        // Ensure other conditional fields are NOT present.
        ->not->toHaveKey('customer')
        ->not->toHaveKey('creator')
        ->not->toHaveKey('company')
        ->not->toHaveKey('currency');
});

// Tests that single-model relationships (customer, creator, company, currency) are included when they exist.
test('it includes customer, creator, company, and currency resources when relationships exist', function () {
    // Mock dependent resource classes for their constructor and `toArray()` method.
    // The `andReturnUsing` callback creates a mock instance that responds to `toArray()`.
    $mockCustomerModel = (object)['id' => 401, 'name' => 'Mock Customer'];
    Mockery::mock('overload:' . CustomerResource::class)
        ->shouldReceive('__construct')
        ->with(Mockery::on(function ($arg) use ($mockCustomerModel) {
            return $arg === $mockCustomerModel; // Ensure the correct model is passed.
        }))
        ->andReturnUsing(function ($model) {
            $mockResource = Mockery::mock(CustomerResource::class);
            $mockResource->resource = $model; // JsonResource sets the 'resource' property internally.
            $mockResource->shouldReceive('toArray')->andReturn(['mocked_customer_resource'])->once();
            return $mockResource;
        })
        ->once();

    $mockCreatorModel = (object)['id' => 501, 'name' => 'Mock Creator'];
    Mockery::mock('overload:' . UserResource::class)
        ->shouldReceive('__construct')
        ->with(Mockery::on(function ($arg) use ($mockCreatorModel) {
            return $arg === $mockCreatorModel;
        }))
        ->andReturnUsing(function ($model) {
            $mockResource = Mockery::mock(UserResource::class);
            $mockResource->resource = $model;
            $mockResource->shouldReceive('toArray')->andReturn(['mocked_user_resource'])->once();
            return $mockResource;
        })
        ->once();

    $mockCompanyModel = (object)['id' => 601, 'name' => 'Mock Company'];
    Mockery::mock('overload:' . CompanyResource::class)
        ->shouldReceive('__construct')
        ->with(Mockery::on(function ($arg) use ($mockCompanyModel) {
            return $arg === $mockCompanyModel;
        }))
        ->andReturnUsing(function ($model) {
            $mockResource = Mockery::mock(CompanyResource::class);
            $mockResource->resource = $model;
            $mockResource->shouldReceive('toArray')->andReturn(['mocked_company_resource'])->once();
            return $mockResource;
        })
        ->once();

    $mockCurrencyModel = (object)['id' => 701, 'code' => 'USD'];
    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldReceive('__construct')
        ->with(Mockery::on(function ($arg) use ($mockCurrencyModel) {
            return $arg === $mockCurrencyModel;
        }))
        ->andReturnUsing(function ($model) {
            $mockResource = Mockery::mock(CurrencyResource::class);
            $mockResource->resource = $model;
            $mockResource->shouldReceive('toArray')->andReturn(['mocked_currency_resource'])->once();
            return $mockResource;
        })
        ->once();

    // Use the helper to create a fully populated mock invoice, then override relation properties
    $mockInvoice = createFullMockInvoice();

    // Set up the underlying model's relationship data (the actual models).
    $mockInvoice->customer = $mockCustomerModel;
    $mockInvoice->creator = $mockCreatorModel;
    $mockInvoice->company = $mockCompanyModel;
    $mockInvoice->currency = $mockCurrencyModel;

    // Mock relationship methods to return relations that DO exist.
    $mockInvoice->shouldReceive('customer')->andReturn(createMockRelation(true))->once();
    $mockInvoice->shouldReceive('creator')->andReturn(createMockRelation(true))->once();
    $mockInvoice->shouldReceive('company')->andReturn(createMockRelation(true))->once();
    $mockInvoice->shouldReceive('currency')->andReturn(createMockRelation(true))->once();

    // Mock other relationships not to exist. Their properties are already null from createFullMockInvoice.
    $mockInvoice->shouldReceive('items')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('taxes')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('fields')->andReturn(createMockRelation(false))->once();

    $resource = new InvoiceResource($mockInvoice);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('customer', ['mocked_customer_resource'])
        ->and($result)->toHaveKey('creator', ['mocked_user_resource'])
        ->and($result)->toHaveKey('company', ['mocked_company_resource'])
        ->and($result)->toHaveKey('currency', ['mocked_currency_resource'])
        // Ensure other conditional fields are NOT present.
        ->not->toHaveKey('items')
        ->not->toHaveKey('taxes')
        ->not->toHaveKey('fields');
});

// Tests a scenario where a mix of relationships exist and don't exist.
test('it handles mixed existence of relationships correctly', function () {
    // Mock specific resources that will be called.
    // InvoiceItemResource (collection)
    Mockery::mock('overload:' . InvoiceItemResource::class)
        ->shouldReceive('collection')
        ->andReturn(['mocked_invoice_item_collection'])
        ->once();

    // CustomerResource (single)
    $mockCustomerModel = (object)['id' => 401, 'name' => 'Mock Customer'];
    Mockery::mock('overload:' . CustomerResource::class)
        ->shouldReceive('__construct')
        ->with(Mockery::on(function ($arg) use ($mockCustomerModel) {
            return $arg === $mockCustomerModel;
        }))
        ->andReturnUsing(function ($model) {
            $mockResource = Mockery::mock(CustomerResource::class);
            $mockResource->resource = $model;
            $mockResource->shouldReceive('toArray')->andReturn(['mocked_customer_resource'])->once();
            return $mockResource;
        })
        ->once();

    // Use the helper to create a fully populated mock invoice, then override only existing relation properties
    $mockInvoice = createFullMockInvoice();

    // Setup existing relationship data.
    $mockInvoice->items = collect([(object)['id' => 101]]);
    $mockInvoice->customer = $mockCustomerModel;

    // Mock relationships that DO exist.
    $mockInvoice->shouldReceive('items')->andReturn(createMockRelation(true))->once();
    $mockInvoice->shouldReceive('customer')->andReturn(createMockRelation(true))->once();

    // Mock relationships that do NOT exist. Their properties are already null from createFullMockInvoice.
    $mockInvoice->shouldReceive('creator')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('taxes')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('fields')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('company')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('currency')->andReturn(createMockRelation(false))->once();

    $resource = new InvoiceResource($mockInvoice);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('items', ['mocked_invoice_item_collection'])
        ->and($result)->toHaveKey('customer', ['mocked_customer_resource'])
        // Ensure non-existing relations are not included.
        ->not->toHaveKey('creator')
        ->not->toHaveKey('taxes')
        ->not->toHaveKey('fields')
        ->not->toHaveKey('company')
        ->not->toHaveKey('currency');
});

// Tests how the resource handles properties with null values.
test('it handles null values for direct properties gracefully', function () {
    // Create mock invoice with all properties set to null, including relation properties.
    // `getDefaultInvoiceProperties()` provides the array of all keys to nullify.
    $mockInvoice = createFullMockInvoice(array_fill_keys(array_keys(getDefaultInvoiceProperties()), null));

    // Mock all relationships not to exist. This will be used by any `->relation()->exists()` checks.
    // The properties themselves are already null due to createFullMockInvoice setup.
    $mockInvoice->shouldReceive('items')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('customer')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('creator')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('taxes')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('fields')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('company')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('currency')->andReturn(createMockRelation(false))->once();

    $resource = new InvoiceResource($mockInvoice);
    $result = $resource->toArray(new Request());

    expect($result)->toBeArray();
    $directProperties = [
        'id', 'invoice_date', 'due_date', 'invoice_number', 'reference_number', 'status', 'paid_status',
        'tax_per_item', 'discount_per_item', 'notes', 'discount_type', 'discount', 'discount_val',
        'sub_total', 'total', 'tax', 'due_amount', 'sent', 'viewed', 'unique_hash', 'template_name',
        'customer_id', 'recurring_invoice_id', 'sequence_number', 'exchange_rate', 'base_discount_val',
        'base_sub_total', 'base_total', 'creator_id', 'base_tax', 'base_due_amount', 'currency_id',
        'formatted_created_at', 'invoice_pdf_url', 'formatted_invoice_date', 'formatted_due_date',
        'allow_edit', 'payment_module_enabled', 'sales_tax_type', 'sales_tax_address_type', 'overdue'
    ];
    foreach ($directProperties as $key) {
        expect($result)->toHaveKey($key);
        expect($result[$key])->toBeNull("Property '{$key}' should be null");
    }

    // Ensure conditional fields are NOT present.
    expect($result)->not->toHaveKey('items')
        ->not->toHaveKey('customer')
        ->not->toHaveKey('creator')
        ->not->toHaveKey('taxes')
        ->not->toHaveKey('fields')
        ->not->toHaveKey('company')
        ->not->toHaveKey('currency');
});

// Tests that relationships returning empty collections are included as empty collections,
// not omitted, when the relationship itself is considered to exist.
test('it includes empty collections for collection relationships that exist but are empty', function () {
    // Mock `collection()` calls to return an empty collection.
    Mockery::mock('overload:' . InvoiceItemResource::class)
        ->shouldReceive('collection')
        ->withArgs(function ($items) {
            return $items instanceof Collection && $items->isEmpty();
        })
        ->andReturn(collect([])) // Return an empty Collection to simulate the resource's output
        ->once();
    Mockery::mock('overload:' . TaxResource::class)
        ->shouldReceive('collection')
        ->withArgs(function ($taxes) {
            return $taxes instanceof Collection && $taxes->isEmpty();
        })
        ->andReturn(collect([]))
        ->once();
    Mockery::mock('overload:' . CustomFieldValueResource::class)
        ->shouldReceive('collection')
        ->withArgs(function ($fields) {
            return $fields instanceof Collection && $fields->isEmpty();
        })
        ->andReturn(collect([]))
        ->once();

    // Use the helper to create a fully populated mock invoice.
    $mockInvoice = createFullMockInvoice();

    // Set empty collections for the underlying model's relationship properties.
    // This makes `whenLoaded()` evaluate to true.
    $mockInvoice->items = collect([]);
    $mockInvoice->taxes = collect([]);
    $mockInvoice->fields = collect([]);

    // Mock these relationship methods to *exist* (return true for `exists()`).
    $mockInvoice->shouldReceive('items')->andReturn(createMockRelation(true))->once();
    $mockInvoice->shouldReceive('taxes')->andReturn(createMockRelation(true))->once();
    $mockInvoice->shouldReceive('fields')->andReturn(createMockRelation(true))->once();

    // Mock other relationships not to exist. Their properties are already null from createFullMockInvoice.
    $mockInvoice->shouldReceive('customer')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('creator')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('company')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('currency')->andReturn(createMockRelation(false))->once();

    $resource = new InvoiceResource($mockInvoice);
    $result = $resource->toArray(new Request());

    // Assert that these keys exist and contain empty arrays (JsonResource::collection
    // often converts collections to arrays when serializing to JSON).
    expect($result)->toHaveKey('items', [])
        ->and($result)->toHaveKey('taxes', [])
        ->and($result)->toHaveKey('fields', []);
});

// Tests that the `Request` object passed to `toArray` does not affect the basic transformation
// of an InvoiceResource, as its logic does not depend on request parameters.
test('it processes with a different request object without affecting output', function () {
    // Use the helper to create a fully populated mock invoice.
    $mockInvoice = createFullMockInvoice();

    // Mock all relationship methods to return relations that don't exist for this test.
    // Their properties are already null from createFullMockInvoice.
    $mockInvoice->shouldReceive('items')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('customer')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('creator')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('taxes')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('fields')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('company')->andReturn(createMockRelation(false))->once();
    $mockInvoice->shouldReceive('currency')->andReturn(createMockRelation(false))->once();

    $differentRequest = new Request(['param' => 'value', 'another' => 'test']); // A request with some data.
    $resource = new InvoiceResource($mockInvoice);
    $result = $resource->toArray($differentRequest);

    // Assert some key properties to ensure transformation happened as expected, independent of request content.
    expect($result['id'])->toBe(1)
        ->and($result['invoice_number'])->toBe('INV-001')
        ->not->toHaveKey('items'); // Confirm conditionals still off.
});

afterEach(function () {
    Mockery::close();
});
```