<?php

use Crater\Http\Requests\InvoicesRequest;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Crater\Models\Invoice;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Mockery\MockInterface;
use Illuminate\Validation\Rules\Unique;

// Helper function to extract properties from a Unique rule object for assertion.
// This allows white-box inspection of the generated Rule objects' configuration.
function getUniqueRuleProperties(Unique $rule): array
{
    $reflection = new ReflectionClass($rule);

    // Access private properties directly using reflection
    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    $table = $tableProperty->getValue($rule);

    $columnProperty = $reflection->getProperty('column');
    $columnProperty->setAccessible(true);
    $column = $columnProperty->getValue($rule);

    $ignoreProperty = $reflection->getProperty('ignore');
    $ignoreProperty->setAccessible(true);
    $ignore = $ignoreProperty->getValue($rule);

    $extraConditionsProperty = $reflection->getProperty('extraConditions');
    $extraConditionsProperty->setAccessible(true);
    $extraConditions = $extraConditionsProperty->getValue($rule);

    return compact('table', 'column', 'ignore', 'extraConditions');
}

// Ensure Mockery mocks are closed after each test to prevent side effects.
beforeEach(function () {
    Mockery::close();
});

// Test authorize method
test('authorize method always returns true', function () {
    $request = new InvoicesRequest();
    expect($request->authorize())->toBeTrue();
});

// Test rules method for validation logic
$companyId = 'test-company-id';
    $customerId = 'test-customer-id';
    $invoiceId = 'test-invoice-id';
    $companyCurrency = 'USD';
    $customerCurrency = 'EUR';

    // Test case: Basic rules structure when no special conditions are met (default state)
    test('rules method returns base validation rules with default unique invoice number', function () use ($companyId, $customerId) {
        // Mock static calls to CompanySetting and Customer to simulate default scenarios
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('currency', $companyId)
            ->andReturn(null); // No company currency set, so exchange_rate stays nullable
        Mockery::mock('alias:' . Customer::class)
            ->shouldReceive('find')
            ->with(Mockery::any())
            ->andReturn(null); // Customer not found, so exchange_rate stays nullable

        // Create a partial mock of InvoicesRequest to control its internal methods and properties
        $request = Mockery::mock(InvoicesRequest::class)->makePartial();
        $request->shouldReceive('header')
            ->with('company')
            ->andReturn($companyId);
        $request->shouldReceive('isMethod')
            ->with('PUT')
            ->andReturn(false); // Not a PUT request
        $request->customer_id = $customerId; // Simulate magic property access

        $rules = $request->rules();

        // Assert that core rules are present and correct
        expect($rules)->toBeArray()
            ->and($rules['invoice_date'])->toContain('required')
            ->and($rules['due_date'])->toContain('nullable')
            ->and($rules['customer_id'])->toContain('required')
            ->and($rules['discount'])->toContain('required')
            ->and($rules['discount_val'])->toContain('required')
            ->and($rules['sub_total'])->toContain('required')
            ->and($rules['total'])->toContain('required')
            ->and($rules['tax'])->toContain('required')
            ->and($rules['template_name'])->toContain('required')
            ->and($rules['items'])->toContain('required', 'array')
            ->and($rules['items.*'])->toContain('required', 'max:255')
            ->and($rules['items.*.description'])->toContain('nullable')
            ->and($rules['items.*.name'])->toContain('required')
            ->and($rules['items.*.quantity'])->toContain('required')
            ->and($rules['items.*.price'])->toContain('required')
            ->and($rules['exchange_rate'])->toContain('nullable'); // Should be nullable by default

        // Assert the default `unique` rule configuration for `invoice_number`
        $uniqueRule = collect($rules['invoice_number'])->first(fn ($rule) => $rule instanceof Unique);
        expect($uniqueRule)->toBeInstanceOf(Unique::class);
        $props = getUniqueRuleProperties($uniqueRule);
        expect($props['table'])->toBe('invoices')
            ->and($props['ignore'])->toBeNull() // Not ignoring any ID
            ->and($props['extraConditions'])->toEqual([['column' => 'company_id', 'value' => $companyId]]);
    });

    // Test case: `exchange_rate` becomes 'required' when customer currency differs from company currency
    test('rules method makes exchange_rate required when customer currency differs from company currency', function () use ($companyId, $customerId, $companyCurrency, $customerCurrency) {
        // Mock static calls to trigger the conditional logic
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('currency', $companyId)
            ->andReturn($companyCurrency); // Company currency is USD

        $mockCustomer = Mockery::mock(Customer::class);
        $mockCustomer->currency_id = $customerCurrency; // Customer currency is EUR, different from company
        Mockery::mock('alias:' . Customer::class)
            ->shouldReceive('find')
            ->with($customerId)
            ->andReturn($mockCustomer);

        // Create a partial mock of InvoicesRequest
        $request = Mockery::mock(InvoicesRequest::class)->makePartial();
        $request->shouldReceive('header')
            ->with('company')
            ->andReturn($companyId);
        $request->shouldReceive('isMethod')
            ->with('PUT')
            ->andReturn(false);
        $request->customer_id = $customerId;

        $rules = $request->rules();

        expect($rules['exchange_rate'])->toContain('required') // Should now be required
            ->and($rules['exchange_rate'])->not->toContain('nullable');
    });

    // Test case: `exchange_rate` remains 'nullable' when customer currency is the same as company currency
    test('rules method keeps exchange_rate nullable when customer currency is same as company currency', function () use ($companyId, $customerId, $companyCurrency) {
        // Mock static calls for this specific scenario
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('currency', $companyId)
            ->andReturn($companyCurrency); // Company currency is USD

        $mockCustomer = Mockery::mock(Customer::class);
        $mockCustomer->currency_id = $companyCurrency; // Customer currency is USD (same as company)
        Mockery::mock('alias:' . Customer::class)
            ->shouldReceive('find')
            ->with($customerId)
            ->andReturn($mockCustomer);

        // Create a partial mock of InvoicesRequest
        $request = Mockery::mock(InvoicesRequest::class)->makePartial();
        $request->shouldReceive('header')
            ->with('company')
            ->andReturn($companyId);
        $request->shouldReceive('isMethod')
            ->with('PUT')
            ->andReturn(false);
        $request->customer_id = $customerId;

        $rules = $request->rules();

        expect($rules['exchange_rate'])->toContain('nullable') // Should remain nullable
            ->and($rules['exchange_rate'])->not->toContain('required');
    });

    // Test case: `exchange_rate` remains 'nullable' if customer is not found
    test('rules method keeps exchange_rate nullable if customer not found', function () use ($companyId, $companyCurrency) {
        // Mock static calls
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('currency', $companyId)
            ->andReturn($companyCurrency);

        Mockery::mock('alias:' . Customer::class)
            ->shouldReceive('find')
            ->with(Mockery::any())
            ->andReturn(null); // Customer not found

        // Create a partial mock of InvoicesRequest
        $request = Mockery::mock(InvoicesRequest::class)->makePartial();
        $request->shouldReceive('header')
            ->with('company')
            ->andReturn($companyId);
        $request->shouldReceive('isMethod')
            ->with('PUT')
            ->andReturn(false);
        $request->customer_id = 'non-existent-customer';

        $rules = $request->rules();

        expect($rules['exchange_rate'])->toContain('nullable') // Should remain nullable
            ->and($rules['exchange_rate'])->not->toContain('required');
    });

    // Test case: `exchange_rate` remains 'nullable' if company currency setting is not found
    test('rules method keeps exchange_rate nullable if company currency setting not found', function () use ($companyId, $customerId, $customerCurrency) {
        // Mock static calls
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('currency', $companyId)
            ->andReturn(null); // Company currency not set

        $mockCustomer = Mockery::mock(Customer::class);
        $mockCustomer->currency_id = $customerCurrency;
        Mockery::mock('alias:' . Customer::class)
            ->shouldReceive('find')
            ->with($customerId)
            ->andReturn($mockCustomer);

        // Create a partial mock of InvoicesRequest
        $request = Mockery::mock(InvoicesRequest::class)->makePartial();
        $request->shouldReceive('header')
            ->with('company')
            ->andReturn($companyId);
        $request->shouldReceive('isMethod')
            ->with('PUT')
            ->andReturn(false);
        $request->customer_id = $customerId;

        $rules = $request->rules();

        expect($rules['exchange_rate'])->toContain('nullable') // Should remain nullable
            ->and($rules['exchange_rate'])->not->toContain('required');
    });

    // Test case: `invoice_number` rule is modified for PUT requests to ignore the current invoice ID
    test('rules method modifies invoice_number rule for PUT requests', function () use ($companyId, $invoiceId) {
        // Mock static calls (less relevant for this branch, but needed to avoid nulls/errors)
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->andReturn(null);
        Mockery::mock('alias:' . Customer::class)
            ->shouldReceive('find')
            ->andReturn(null);

        // Mock the `route()` method specifically for PUT requests to return an invoice object
        $mockInvoiceRoute = (object)['id' => $invoiceId]; // Simulate `route('invoice')` object structure
        $request = Mockery::mock(InvoicesRequest::class)->makePartial();
        $request->shouldReceive('header')
            ->with('company')
            ->andReturn($companyId);
        $request->shouldReceive('isMethod')
            ->with('PUT')
            ->andReturn(true); // Simulate a PUT request
        $request->shouldReceive('route')
            ->with('invoice')
            ->andReturn($mockInvoiceRoute);
        $request->customer_id = 'any-customer-id'; // Required for rules method execution

        $rules = $request->rules();

        // Assert the modified `unique` rule for `invoice_number` in a PUT request
        $uniqueRule = collect($rules['invoice_number'])->first(fn ($rule) => $rule instanceof Unique);
        expect($uniqueRule)->toBeInstanceOf(Unique::class);
        $props = getUniqueRuleProperties($uniqueRule);
        expect($props['table'])->toBe('invoices')
            ->and($props['ignore'])->toBe($invoiceId) // Should now ignore the specified invoice ID
            ->and($props['extraConditions'])->toEqual([['column' => 'company_id', 'value' => $companyId]]);
    });
    
    $companyId = 'test-company-id';
    $customerId = 'test-customer-id';
    $userId = 'test-user-id';
    $companyCurrencyId = 'USD';
    $customerCurrencyId = 'EUR';
    $currentCurrencyId = 'EUR'; // The currency of the invoice being created (from request)
    $exchangeRate = 1.2;
    $total = 100.00;
    $discountVal = 10.00;
    $subTotal = 90.00;
    $tax = 5.00;

    // Test case: Currencies differ, and `exchange_rate` from request is applied
    test('getInvoicePayload returns correct data with exchange rate applied when currencies differ', function () use (
        $companyId, $customerId, $userId, $companyCurrencyId, $customerCurrencyId, $currentCurrencyId,
        $exchangeRate, $total, $discountVal, $subTotal, $tax
    ) {
        // Mock CompanySetting static method calls
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('currency', $companyId)
            ->andReturn($companyCurrencyId); // Company currency is USD
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('tax_per_item', $companyId)
            ->andReturn('YES');
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('discount_per_item', $companyId)
            ->andReturn('NO');

        // Mock Customer::find static method call
        $mockCustomer = Mockery::mock(Customer::class);
        $mockCustomer->currency_id = $customerCurrencyId; // Customer currency is EUR, different from company
        Mockery::mock('alias:' . Customer::class)
            ->shouldReceive('find')
            ->with($customerId)
            ->andReturn($mockCustomer);

        // Mock a User for `creator_id`
        $mockUser = Mockery::mock(Authenticatable::class);
        $mockUser->id = $userId;

        // Create a partial mock of InvoicesRequest to control its internal methods and properties
        $request = Mockery::mock(InvoicesRequest::class)->makePartial();
        $request->shouldReceive('header')
            ->with('company')
            ->andReturn($companyId);
        $request->shouldReceive('except')
            ->with('items', 'taxes')
            ->andReturn(['invoice_date' => '2023-01-01', 'customer_id' => $customerId]); // Simulate base request data
        $request->shouldReceive('user')
            ->andReturn($mockUser);
        $request->shouldReceive('has')
            ->with('invoiceSend')
            ->andReturn(true); // Simulate `invoiceSend` flag is true

        // Set magic properties that would typically come from the request input
        $request->customer_id = $customerId;
        $request->currency_id = $currentCurrencyId; // Current invoice currency, different from company
        $request->exchange_rate = $exchangeRate; // Explicit exchange rate provided in request
        $request->total = $total;
        $request->discount_val = $discountVal;
        $request->sub_total = $subTotal;
        $request->tax = $tax;

        $payload = $request->getInvoicePayload();

        expect($payload)->toBeArray()
            ->and($payload['creator_id'])->toBe($userId)
            ->and($payload['status'])->toBe(Invoice::STATUS_SENT) // Based on `has('invoiceSend')` returning true
            ->and($payload['paid_status'])->toBe(Invoice::STATUS_UNPAID)
            ->and($payload['company_id'])->toBe($companyId)
            ->and($payload['tax_per_item'])->toBe('YES')
            ->and($payload['discount_per_item'])->toBe('NO')
            ->and($payload['due_amount'])->toBe($total)
            ->and($payload['exchange_rate'])->toBe($exchangeRate) // Should use the provided exchange rate
            ->and($payload['base_total'])->toBe($total * $exchangeRate)
            ->and($payload['base_discount_val'])->toBe($discountVal * $exchangeRate)
            ->and($payload['base_sub_total'])->toBe($subTotal * $exchangeRate)
            ->and($payload['base_tax'])->toBe($tax * $exchangeRate)
            ->and($payload['base_due_amount'])->toBe($total * $exchangeRate)
            ->and($payload['currency_id'])->toBe($customerCurrencyId); // Customer's currency (from Customer::find)
    });

    // Test case: Currencies are the same, `exchange_rate` defaults to 1
    test('getInvoicePayload returns correct data with exchange rate of 1 when currencies are same', function () use (
        $companyId, $customerId, $companyCurrencyId, $total, $discountVal, $subTotal, $tax
    ) {
        // Mock CompanySetting static method calls
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('currency', $companyId)
            ->andReturn($companyCurrencyId); // Company currency is USD
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('tax_per_item', $companyId)
            ->andReturn('NO '); // Simulating default if not set (matches code default)
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('discount_per_item', $companyId)
            ->andReturn('NO'); // Simulating default if not set (matches code default)

        // Mock Customer::find static method call
        $mockCustomer = Mockery::mock(Customer::class);
        $mockCustomer->currency_id = $companyCurrencyId; // Customer currency is USD (same as company)
        Mockery::mock('alias:' . Customer::class)
            ->shouldReceive('find')
            ->with($customerId)
            ->andReturn($mockCustomer);

        // Create a partial mock of InvoicesRequest
        $request = Mockery::mock(InvoicesRequest::class)->makePartial();
        $request->shouldReceive('header')
            ->with('company')
            ->andReturn($companyId);
        $request->shouldReceive('except')
            ->with('items', 'taxes')
            ->andReturn(['invoice_date' => '2023-01-01']);
        $request->shouldReceive('user')
            ->andReturn(null); // Simulate no user logged in
        $request->shouldReceive('has')
            ->with('invoiceSend')
            ->andReturn(false); // Simulate `invoiceSend` flag is false (draft)

        // Set magic properties
        $request->customer_id = $customerId;
        $request->currency_id = $companyCurrencyId; // Current invoice currency (same as company)
        $request->exchange_rate = 99.99; // This value should be ignored and overridden to 1
        $request->total = $total;
        $request->discount_val = $discountVal;
        $request->sub_total = $subTotal;
        $request->tax = $tax;

        $payload = $request->getInvoicePayload();

        expect($payload)->toBeArray()
            ->and($payload['creator_id'])->toBeNull() // No user
            ->and($payload['status'])->toBe(Invoice::STATUS_DRAFT) // Based on `has('invoiceSend')` returning false
            ->and($payload['exchange_rate'])->toBe(1) // Exchange rate should be 1
            ->and($payload['base_total'])->toBe($total * 1)
            ->and($payload['base_discount_val'])->toBe($discountVal * 1)
            ->and($payload['base_sub_total'])->toBe($subTotal * 1)
            ->and($payload['base_tax'])->toBe($tax * 1)
            ->and($payload['base_due_amount'])->toBe($total * 1)
            ->and($payload['currency_id'])->toBe($companyCurrencyId);
    });

    // Test case: Missing CompanySetting values, ensure defaults are applied (`tax_per_item` and `discount_per_item`)
    test('getInvoicePayload handles missing company settings with default values', function () use (
        $companyId, $customerId, $userId, $customerCurrencyId, $currentCurrencyId,
        $exchangeRate, $total, $discountVal, $subTotal, $tax
    ) {
        // Mock CompanySetting::getSetting to return null for various settings
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('currency', $companyId)
            ->andReturn(null); // No company currency set
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('tax_per_item', $companyId)
            ->andReturn(null); // Tax per item not set
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('discount_per_item', $companyId)
            ->andReturn(null); // Discount per item not set

        // Mock Customer::find static method call
        $mockCustomer = Mockery::mock(Customer::class);
        $mockCustomer->currency_id = $customerCurrencyId;
        Mockery::mock('alias:' . Customer::class)
            ->shouldReceive('find')
            ->with($customerId)
            ->andReturn($mockCustomer);

        // Mock a User
        $mockUser = Mockery::mock(Authenticatable::class);
        $mockUser->id = $userId;

        // Create a partial mock of InvoicesRequest
        $request = Mockery::mock(InvoicesRequest::class)->makePartial();
        $request->shouldReceive('header')
            ->with('company')
            ->andReturn($companyId);
        $request->shouldReceive('except')
            ->with('items', 'taxes')
            ->andReturn([]);
        $request->shouldReceive('user')
            ->andReturn($mockUser);
        $request->shouldReceive('has')
            ->with('invoiceSend')
            ->andReturn(true);

        // Set magic properties
        $request->customer_id = $customerId;
        $request->currency_id = $currentCurrencyId;
        $request->exchange_rate = $exchangeRate;
        $request->total = $total;
        $request->discount_val = $discountVal;
        $request->sub_total = $subTotal;
        $request->tax = $tax;

        $payload = $request->getInvoicePayload();

        // `company_currency` is null, so `null != $current_currency` (EUR) evaluates to true
        expect($payload['exchange_rate'])->toBe($exchangeRate)
            ->and($payload['tax_per_item'])->toBe('NO ') // Default specified in code: ?? 'NO '
            ->and($payload['discount_per_item'])->toBe('NO'); // Default specified in code: ?? 'NO'
    });

    // Test case: Customer not found for `currency_id` (this simulates a runtime error if validation fails upstream)
    test('getInvoicePayload throws TypeError if customer not found for currency_id', function () use (
        $companyId, $userId, $companyCurrencyId, $currentCurrencyId,
        $exchangeRate, $total, $discountVal, $subTotal, $tax
    ) {
        // Mock CompanySetting static method calls
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('currency', $companyId)
            ->andReturn($companyCurrencyId);
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('tax_per_item', $companyId)
            ->andReturn('YES');
        Mockery::mock('alias:' . CompanySetting::class)
            ->shouldReceive('getSetting')
            ->with('discount_per_item', $companyId)
            ->andReturn('NO');

        // Mock Customer::find static method call to return null, simulating customer not found
        Mockery::mock('alias:' . Customer::class)
            ->shouldReceive('find')
            ->with(Mockery::any())
            ->andReturn(null);

        // Mock a User
        $mockUser = Mockery::mock(Authenticatable::class);
        $mockUser->id = $userId;

        // Create a partial mock of InvoicesRequest
        $request = Mockery::mock(InvoicesRequest::class)->makePartial();
        $request->shouldReceive('header')
            ->with('company')
            ->andReturn($companyId);
        $request->shouldReceive('except')
            ->with('items', 'taxes')
            ->andReturn([]);
        $request->shouldReceive('user')
            ->andReturn($mockUser);
        $request->shouldReceive('has')
            ->with('invoiceSend')
            ->andReturn(true);

        // Set magic properties
        $request->customer_id = 'non-existent-customer';
        $request->currency_id = $currentCurrencyId;
        $request->exchange_rate = $exchangeRate;
        $request->total = $total;
        $request->discount_val = $discountVal;
        $request->sub_total = $subTotal;
        $request->tax = $tax;

        // The line `Customer::find($this->customer_id)->currency_id` will attempt to access `currency_id` on null.
        // This is a `TypeError` in PHP 8+, which is an important edge case to cover in white-box testing.
        expect(fn () => $request->getInvoicePayload())
            ->throws(TypeError::class);
    });
