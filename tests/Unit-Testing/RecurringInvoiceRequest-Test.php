<?php

namespace Tests\Unit;

use Crater\Http\Requests\RecurringInvoiceRequest;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Crater\Models\RecurringInvoice;
uses(\Mockery::class);

beforeEach(function () {
    Mockery::close();
});

// Test `authorize` method
test('authorize method always returns true', function () {
    $request = new RecurringInvoiceRequest();
    expect($request->authorize())->toBeTrue();
});

// Test `rules` method
test('rules method returns base validation rules with nullable exchange_rate by default', function () {
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 'test_company_id')
        ->andReturn('USD')
        ->once();

    Mockery::mock('alias:'.Customer::class)
        ->shouldReceive('find')
        ->with('customer_id_1')
        ->andReturn(null) // No customer, so conditional rule is not applied
        ->once();

    $request = Mockery::mock(RecurringInvoiceRequest::class.'[header]');
    $request->shouldReceive('header')->with('company')->andReturn('test_company_id')->once();
    $request->merge(['customer_id' => 'customer_id_1']); // Simulate input for $this->customer_id

    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys([
            'starts_at', 'send_automatically', 'customer_id', 'discount', 'discount_val',
            'sub_total', 'total', 'tax', 'status', 'exchange_rate', 'frequency',
            'limit_by', 'limit_count', 'limit_date', 'items', 'items.*'
        ])
        ->and($rules['starts_at'])->toContain('required')
        ->and($rules['send_automatically'])->toContain('boolean')
        ->and($rules['limit_count'])->toContain('required_if:limit_by,COUNT')
        ->and($rules['limit_date'])->toContain('required_if:limit_by,DATE')
        ->and($rules['exchange_rate'])->toEqual(['nullable']); // Base rule is nullable
});

test('rules method adds required exchange_rate when customer currency differs from company currency', function () {
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 'test_company_id')
        ->andReturn('USD')
        ->once();

    $mockCustomer = (object)['currency_id' => 'EUR'];
    Mockery::mock('alias:'.Customer::class)
        ->shouldReceive('find')
        ->with('customer_id_1')
        ->andReturn($mockCustomer)
        ->once();

    $request = Mockery::mock(RecurringInvoiceRequest::class.'[header]');
    $request->shouldReceive('header')->with('company')->andReturn('test_company_id')->once();
    $request->merge(['customer_id' => 'customer_id_1']);

    $rules = $request->rules();

    expect($rules['exchange_rate'])->toEqual(['required']);
});

test('rules method keeps exchange_rate nullable when customer currency is same as company currency', function () {
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 'test_company_id')
        ->andReturn('USD')
        ->once();

    $mockCustomer = (object)['currency_id' => 'USD'];
    Mockery::mock('alias:'.Customer::class)
        ->shouldReceive('find')
        ->with('customer_id_1')
        ->andReturn($mockCustomer)
        ->once();

    $request = Mockery::mock(RecurringInvoiceRequest::class.'[header]');
    $request->shouldReceive('header')->with('company')->andReturn('test_company_id')->once();
    $request->merge(['customer_id' => 'customer_id_1']);

    $rules = $request->rules();

    expect($rules['exchange_rate'])->toEqual(['nullable']);
});

test('rules method keeps exchange_rate nullable if customer is not found', function () {
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 'test_company_id')
        ->andReturn('USD')
        ->once();

    Mockery::mock('alias:'.Customer::class)
        ->shouldReceive('find')
        ->with('customer_id_1')
        ->andReturn(null)
        ->once();

    $request = Mockery::mock(RecurringInvoiceRequest::class.'[header]');
    $request->shouldReceive('header')->with('company')->andReturn('test_company_id')->once();
    $request->merge(['customer_id' => 'customer_id_1']);

    $rules = $request->rules();

    expect($rules['exchange_rate'])->toEqual(['nullable']);
});

test('rules method keeps exchange_rate nullable if company currency setting is missing', function () {
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 'test_company_id')
        ->andReturn(null) // Company currency not found
        ->once();

    $mockCustomer = (object)['currency_id' => 'USD'];
    Mockery::mock('alias:'.Customer::class)
        ->shouldReceive('find')
        ->with('customer_id_1')
        ->andReturn($mockCustomer)
        ->once();

    $request = Mockery::mock(RecurringInvoiceRequest::class.'[header]');
    $request->shouldReceive('header')->with('company')->andReturn('test_company_id')->once();
    $request->merge(['customer_id' => 'customer_id_1']);

    $rules = $request->rules();

    expect($rules['exchange_rate'])->toEqual(['nullable']);
});

test('rules method requires limit_count when limit_by is COUNT', function () {
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 'test_company_id')
        ->andReturn('USD')
        ->once();

    Mockery::mock('alias:'.Customer::class)
        ->shouldReceive('find')
        ->with('customer_id_1')
        ->andReturn(null)
        ->once();

    $request = Mockery::mock(RecurringInvoiceRequest::class.'[header]');
    $request->shouldReceive('header')->with('company')->andReturn('test_company_id')->once();
    $request->merge(['customer_id' => 'customer_id_1', 'limit_by' => 'COUNT']);

    $rules = $request->rules();
    expect($rules['limit_count'])->toContain('required_if:limit_by,COUNT');
    expect($rules['limit_date'])->not->toContain('required_if:limit_by,DATE'); // Should not be required
});

test('rules method requires limit_date when limit_by is DATE', function () {
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 'test_company_id')
        ->andReturn('USD')
        ->once();

    Mockery::mock('alias:'.Customer::class)
        ->shouldReceive('find')
        ->with('customer_id_1')
        ->andReturn(null)
        ->once();

    $request = Mockery::mock(RecurringInvoiceRequest::class.'[header]');
    $request->shouldReceive('header')->with('company')->andReturn('test_company_id')->once();
    $request->merge(['customer_id' => 'customer_id_1', 'limit_by' => 'DATE']);

    $rules = $request->rules();
    expect($rules['limit_date'])->toContain('required_if:limit_by,DATE');
    expect($rules['limit_count'])->not->toContain('required_if:limit_by,COUNT'); // Should not be required
});

test('rules method requires items and items.*', function () {
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 'test_company_id')
        ->andReturn('USD')
        ->once();

    Mockery::mock('alias:'.Customer::class)
        ->shouldReceive('find')
        ->with('customer_id_1')
        ->andReturn(null)
        ->once();

    $request = Mockery::mock(RecurringInvoiceRequest::class.'[header]');
    $request->shouldReceive('header')->with('company')->andReturn('test_company_id')->once();
    $request->merge(['customer_id' => 'customer_id_1']);

    $rules = $request->rules();
    expect($rules['items'])->toContain('required');
    expect($rules['items.*'])->toContain('required');
});


// Test `getRecurringInvoicePayload` method
test('getRecurringInvoicePayload returns correct data with differing currencies and explicit exchange rate', function () {
    $companyId = 'test_company_id';
    $userId = 'user_id_123';
    $customerId = 'customer_id_1';
    $companyCurrency = 'USD';
    $customerCurrency = 'EUR';
    $providedExchangeRate = 1.25;
    $frequency = 'MONTHLY';
    $startsAt = '2023-01-01';
    $totalAmount = 100.00;
    $nextInvoiceDate = '2023-02-01';
    $taxPerItem = 'YES';
    $discountPerItem = 'NO';

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency)
        ->once()
        ->shouldReceive('getSetting')
        ->with('tax_per_item', $companyId)
        ->andReturn($taxPerItem)
        ->once()
        ->shouldReceive('getSetting')
        ->with('discount_per_item', $companyId)
        ->andReturn($discountPerItem)
        ->once();

    $mockCustomer = (object)['currency_id' => $customerCurrency];
    Mockery::mock('alias:'.Customer::class)
        ->shouldReceive('find')
        ->with($customerId)
        ->andReturn($mockCustomer)
        ->once();

    Mockery::mock('alias:'.RecurringInvoice::class)
        ->shouldReceive('getNextInvoiceDate')
        ->with($frequency, $startsAt)
        ->andReturn($nextInvoiceDate)
        ->once();

    $request = Mockery::mock(RecurringInvoiceRequest::class.'[header,user,except]');
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $mockUser = (object)['id' => $userId];
    $request->shouldReceive('user')->andReturn($mockUser)->once();
    $request->shouldReceive('except')->with('items', 'taxes')->andReturn(['some_other_field' => 'value', 'total' => $totalAmount])->once();

    // Simulate request input
    $request->merge([
        'customer_id' => $customerId,
        'currency_id' => $customerCurrency,
        'exchange_rate' => $providedExchangeRate,
        'frequency' => $frequency,
        'starts_at' => $startsAt,
        'total' => $totalAmount,
        'items' => [['id' => 1]], // Will be 'excepted'
        'taxes' => [['id' => 1]], // Will be 'excepted'
    ]);

    $payload = $request->getRecurringInvoicePayload();

    expect($payload)->toBeArray()
        ->and($payload)->toEqual([
            'some_other_field' => 'value',
            'total' => $totalAmount,
            'creator_id' => $userId,
            'company_id' => $companyId,
            'next_invoice_at' => $nextInvoiceDate,
            'tax_per_item' => $taxPerItem,
            'discount_per_item' => $discountPerItem,
            'due_amount' => $totalAmount,
            'exchange_rate' => $providedExchangeRate,
            'currency_id' => $customerCurrency,
        ]);
});

test('getRecurringInvoicePayload returns correct data with same currencies and default exchange rate', function () {
    $companyId = 'test_company_id';
    $userId = 'user_id_456';
    $customerId = 'customer_id_2';
    $currency = 'USD'; // Both company and customer currency
    $frequency = 'WEEKLY';
    $startsAt = '2023-03-01';
    $totalAmount = 50.00;
    $nextInvoiceDate = '2023-03-08';

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($currency)
        ->once()
        ->shouldReceive('getSetting')
        ->with('tax_per_item', $companyId)
        ->andReturn(null) // Test default value 'NO '
        ->once()
        ->shouldReceive('getSetting')
        ->with('discount_per_item', $companyId)
        ->andReturn(null) // Test default value 'NO'
        ->once();

    $mockCustomer = (object)['currency_id' => $currency];
    Mockery::mock('alias:'.Customer::class)
        ->shouldReceive('find')
        ->with($customerId)
        ->andReturn($mockCustomer)
        ->once();

    Mockery::mock('alias:'.RecurringInvoice::class)
        ->shouldReceive('getNextInvoiceDate')
        ->with($frequency, $startsAt)
        ->andReturn($nextInvoiceDate)
        ->once();

    $request = Mockery::mock(RecurringInvoiceRequest::class.'[header,user,except]');
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $mockUser = (object)['id' => $userId];
    $request->shouldReceive('user')->andReturn($mockUser)->once();
    $request->shouldReceive('except')->with('items', 'taxes')->andReturn(['total' => $totalAmount])->once();

    // Simulate request input
    $request->merge([
        'customer_id' => $customerId,
        'currency_id' => $currency,
        'exchange_rate' => 1.5, // This value should be ignored, and 1 used instead
        'frequency' => $frequency,
        'starts_at' => $startsAt,
        'total' => $totalAmount,
    ]);

    $payload = $request->getRecurringInvoicePayload();

    expect($payload)->toBeArray()
        ->and($payload)->toEqual([
            'total' => $totalAmount,
            'creator_id' => $userId,
            'company_id' => $companyId,
            'next_invoice_at' => $nextInvoiceDate,
            'tax_per_item' => 'NO ',
            'discount_per_item' => 'NO',
            'due_amount' => $totalAmount,
            'exchange_rate' => 1, // Assert default 1
            'currency_id' => $currency,
        ]);
});

test('getRecurringInvoicePayload handles missing company currency setting for exchange rate calculation', function () {
    $companyId = 'test_company_id';
    $userId = 'user_id_789';
    $customerId = 'customer_id_3';
    $companyCurrency = null; // Company currency setting is missing
    $customerCurrency = 'EUR';
    $providedExchangeRate = 1.25;
    $frequency = 'MONTHLY';
    $startsAt = '2023-01-01';
    $totalAmount = 100.00;
    $nextInvoiceDate = '2023-02-01';

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency) // Return null for company currency
        ->once()
        ->shouldReceive('getSetting')
        ->with('tax_per_item', $companyId)
        ->andReturn('NO')
        ->once()
        ->shouldReceive('getSetting')
        ->with('discount_per_item', $companyId)
        ->andReturn('NO')
        ->once();

    $mockCustomer = (object)['currency_id' => $customerCurrency];
    Mockery::mock('alias:'.Customer::class)
        ->shouldReceive('find')
        ->with($customerId)
        ->andReturn($mockCustomer)
        ->once();

    Mockery::mock('alias:'.RecurringInvoice::class)
        ->shouldReceive('getNextInvoiceDate')
        ->with($frequency, $startsAt)
        ->andReturn($nextInvoiceDate)
        ->once();

    $request = Mockery::mock(RecurringInvoiceRequest::class.'[header,user,except]');
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $mockUser = (object)['id' => $userId];
    $request->shouldReceive('user')->andReturn($mockUser)->once();
    $request->shouldReceive('except')->with('items', 'taxes')->andReturn(['total' => $totalAmount])->once();

    // Simulate request input
    $request->merge([
        'customer_id' => $customerId,
        'currency_id' => $customerCurrency,
        'exchange_rate' => $providedExchangeRate,
        'frequency' => $frequency,
        'starts_at' => $startsAt,
        'total' => $totalAmount,
    ]);

    $payload = $request->getRecurringInvoicePayload();

    // If $company_currency is null, then $company_currency != $current_currency will be true,
    // so $this->exchange_rate will be used.
    expect($payload['exchange_rate'])->toBe($providedExchangeRate);
});
