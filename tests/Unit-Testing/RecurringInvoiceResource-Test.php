<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

// The resource class under test
use Crater\Http\Resources\RecurringInvoiceResource;

// All specific resource classes that might be instantiated or collected
use Crater\Http\Resources\CustomFieldValueResource;
use Crater\Http\Resources\InvoiceItemResource;
use Crater\Http\Resources\CustomerResource;
use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\InvoiceResource;
use Crater\Http\Resources\TaxResource;
use Crater\Http\Resources\UserResource;
use Crater\Http\Resources\CurrencyResource;

// Helper function to create a mock RecurringInvoice model for tests
function createMockRecurringInvoice(array $withRelations = []): Mockery\MockInterface
{
    $model = Mockery::mock(\stdClass::class);

    // Set all direct properties with dummy data. These values can be adjusted for specific test cases.
    $model->id = 1;
    $model->starts_at = '2023-01-01';
    $model->formattedStartsAt = 'Jan 01, 2023';
    $model->formattedCreatedAt = 'Dec 31, 2022';
    $model->formattedNextInvoiceAt = 'Feb 01, 2023';
    $model->formattedLimitDate = 'Jan 01, 2024';
    $model->send_automatically = true;
    $model->customer_id = 10;
    $model->company_id = 20;
    $model->creator_id = 30;
    $model->status = 'active';
    $model->next_invoice_at = '2023-02-01';
    $model->frequency = 'monthly';
    $model->limit_by = 'date';
    $model->limit_count = 12;
    $model->limit_date = '2024-01-01';
    $model->exchange_rate = 1.0;
    $model->tax_per_item = false;
    $model->discount_per_item = false;
    $model->notes = 'Some notes';
    $model->discount_type = 'percentage';
    $model->discount = 10.0;
    $model->discount_val = 100.0;
    $model->sub_total = 1000.0;
    $model->total = 900.0;
    $model->tax = 0.0;
    $model->due_amount = 900.0;
    $model->template_name = 'Standard';
    $model->sales_tax_type = 'none';
    $model->sales_tax_address_type = 'shipping';

    // Define relation types (collection vs single resource)
    $relationDefinitions = [
        'fields' => 'collection',
        'items' => 'collection',
        'customer' => 'single',
        'company' => 'single',
        'invoices' => 'collection',
        'taxes' => 'collection',
        'creator' => 'single',
        'currency' => 'single',
    ];

    foreach ($relationDefinitions as $relationName => $type) {
        $relationBuilder = Mockery::mock(Relation::class);
        $exists = in_array($relationName, $withRelations);

        // Configure the exists() method on the relation builder mock
        $relationBuilder->shouldReceive('exists')->andReturn($exists);
        // Configure the relation method on the model mock to return the builder
        $model->shouldReceive($relationName)->andReturn($relationBuilder);

        if ($exists) {
            // If the relation exists, ensure the corresponding property on the model is populated
            if ($type === 'collection') {
                // For collections, provide a Collection of mocked related models
                $model->$relationName = new Collection([Mockery::mock(\stdClass::class)]);
            } else {
                // For single relations, provide a single mocked related model
                $model->$relationName = Mockery::mock(\stdClass::class);
            }
        } else {
            // If the relation does not exist, set the property to null to reflect an unloaded relation
            $model->$relationName = null;
        }
    }

    return $model;
}

test('toArray includes all direct properties when no relations exist', function () {
    $request = Request::create('/');
    $model = createMockRecurringInvoice([]); // No relations should exist

    $resource = new RecurringInvoiceResource($model);
    $result = $resource->toArray($request);

    // Define all expected keys for direct properties
    $expectedDirectKeys = [
        'id', 'starts_at', 'formatted_starts_at', 'formatted_created_at',
        'formatted_next_invoice_at', 'formatted_limit_date', 'send_automatically',
        'customer_id', 'company_id', 'creator_id', 'status', 'next_invoice_at',
        'frequency', 'limit_by', 'limit_count', 'limit_date', 'exchange_rate',
        'tax_per_item', 'discount_per_item', 'notes', 'discount_type', 'discount',
        'discount_val', 'sub_total', 'total', 'tax', 'due_amount',
        'template_name', 'sales_tax_type', 'sales_tax_address_type',
    ];

    // Assert that all direct properties are present and their values match the mocked model
    foreach ($expectedDirectKeys as $key) {
        expect($result)->toHaveKey($key);
        expect($result[$key])->toBe($model->$key);
    }

    // Assert that no relation keys are present in the output array
    $relationKeys = [
        'fields', 'items', 'customer', 'company', 'invoices', 'taxes', 'creator', 'currency'
    ];
    foreach ($relationKeys as $key) {
        expect($result)->not->toHaveKey($key);
    }
});

test('toArray includes all direct properties and all relations when they exist', function () {
    $request = Request::create('/');
    $allRelations = [
        'fields', 'items', 'customer', 'company', 'invoices', 'taxes', 'creator', 'currency'
    ];
    $model = createMockRecurringInvoice($allRelations); // All relations should exist

    $resource = new RecurringInvoiceResource($model);
    $result = $resource->toArray($request);

    // Assert that all direct properties are present and correct
    $expectedDirectKeys = [
        'id', 'starts_at', 'formatted_starts_at', 'formatted_created_at',
        'formatted_next_invoice_at', 'formatted_limit_date', 'send_automatically',
        'customer_id', 'company_id', 'creator_id', 'status', 'next_invoice_at',
        'frequency', 'limit_by', 'limit_count', 'limit_date', 'exchange_rate',
        'tax_per_item', 'discount_per_item', 'notes', 'discount_type', 'discount',
        'discount_val', 'sub_total', 'total', 'tax', 'due_amount',
        'template_name', 'sales_tax_type', 'sales_tax_address_type',
    ];
    foreach ($expectedDirectKeys as $key) {
        expect($result)->toHaveKey($key);
        expect($result[$key])->toBe($model->$key);
    }

    // Assert that all relation keys are present and their values are of the correct resource type
    expect($result)->toHaveKey('fields');
    expect($result['fields'])->toBeInstanceOf(AnonymousResourceCollection::class);

    expect($result)->toHaveKey('items');
    expect($result['items'])->toBeInstanceOf(AnonymousResourceCollection::class);

    expect($result)->toHaveKey('customer');
    expect($result['customer'])->toBeInstanceOf(CustomerResource::class);

    expect($result)->toHaveKey('company');
    expect($result['company'])->toBeInstanceOf(CompanyResource::class);

    expect($result)->toHaveKey('invoices');
    expect($result['invoices'])->toBeInstanceOf(AnonymousResourceCollection::class);

    expect($result)->toHaveKey('taxes');
    expect($result['taxes'])->toBeInstanceOf(AnonymousResourceCollection::class);

    expect($result)->toHaveKey('creator');
    expect($result['creator'])->toBeInstanceOf(UserResource::class);

    expect($result)->toHaveKey('currency');
    expect($result['currency'])->toBeInstanceOf(CurrencyResource::class);
});

test('toArray conditionally includes relations based on their existence', function () {
    $request = Request::create('/');
    // Select a subset of relations to exist
    $existingRelations = ['customer', 'items', 'creator'];
    $nonExistingRelations = ['fields', 'company', 'invoices', 'taxes', 'currency'];

    $model = createMockRecurringInvoice($existingRelations);

    $resource = new RecurringInvoiceResource($model);
    $result = $resource->toArray($request);

    // Assert that the specified existing relations are present and of the correct type
    expect($result)->toHaveKey('customer');
    expect($result['customer'])->toBeInstanceOf(CustomerResource::class);

    expect($result)->toHaveKey('items');
    expect($result['items'])->toBeInstanceOf(AnonymousResourceCollection::class);

    expect($result)->toHaveKey('creator');
    expect($result['creator'])->toBeInstanceOf(UserResource::class);

    // Assert that the specified non-existing relations are NOT present
    foreach ($nonExistingRelations as $key) {
        expect($result)->not->toHaveKey($key);
    }

    // Verify a couple of direct properties are still there
    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe($model->id);
    expect($result)->toHaveKey('total');
    expect($result['total'])->toBe($model->total);
});

test('toArray handles null or empty property values correctly for various data types', function () {
    $request = Request::create('/');
    $model = createMockRecurringInvoice([]); // No relations for this test

    // Override some properties to be null or empty to test edge cases
    $model->starts_at = null;
    $model->formattedStartsAt = null;
    $model->notes = ''; // Empty string
    $model->limit_count = null;
    $model->exchange_rate = 0.0; // Zero numeric value
    $model->discount = 0.0;
    $model->total = 0.0;

    $resource = new RecurringInvoiceResource($model);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('starts_at');
    expect($result['starts_at'])->toBeNull();

    expect($result)->toHaveKey('formatted_starts_at');
    expect($result['formatted_starts_at'])->toBeNull();

    expect($result)->toHaveKey('notes');
    expect($result['notes'])->toBe('');

    expect($result)->toHaveKey('limit_count');
    expect($result['limit_count'])->toBeNull();

    expect($result)->toHaveKey('exchange_rate');
    expect($result['exchange_rate'])->toBe(0.0);

    expect($result)->toHaveKey('discount');
    expect($result['discount'])->toBe(0.0);

    expect($result)->toHaveKey('total');
    expect($result['total'])->toBe(0.0);
});

test('toArray handles boolean flags correctly for true and false values', function () {
    $request = Request::create('/');

    // Test with true boolean values
    $modelTrue = createMockRecurringInvoice([]);
    $modelTrue->send_automatically = true;
    $modelTrue->tax_per_item = true;
    $modelTrue->discount_per_item = true;

    $resourceTrue = new RecurringInvoiceResource($modelTrue);
    $resultTrue = $resourceTrue->toArray($request);

    expect($resultTrue)->toHaveKey('send_automatically');
    expect($resultTrue['send_automatically'])->toBeTrue();

    expect($resultTrue)->toHaveKey('tax_per_item');
    expect($resultTrue['tax_per_item'])->toBeTrue();

    expect($resultTrue)->toHaveKey('discount_per_item');
    expect($resultTrue['discount_per_item'])->toBeTrue();

    // Test with false boolean values
    $modelFalse = createMockRecurringInvoice([]);
    $modelFalse->send_automatically = false;
    $modelFalse->tax_per_item = false;
    $modelFalse->discount_per_item = false;

    $resourceFalse = new RecurringInvoiceResource($modelFalse);
    $resultFalse = $resourceFalse->toArray($request);

    expect($resultFalse)->toHaveKey('send_automatically');
    expect($resultFalse['send_automatically'])->toBeFalse();

    expect($resultFalse)->toHaveKey('tax_per_item');
    expect($resultFalse['tax_per_item'])->toBeFalse();

    expect($resultFalse)->toHaveKey('discount_per_item');
    expect($resultFalse['discount_per_item'])->toBeFalse();
});

// Clean up Mockery mocks after each test to prevent test pollution




afterEach(function () {
    Mockery::close();
});
