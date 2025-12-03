```php
<?php

use Crater\Http\Requests\PaymentRequest;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rule;

// Removed the redundant beforeEach(Mockery::close()) as it can interfere with alias mocks.
// The primary afterEach(Mockery::close()) at the end of the file is sufficient for cleanup.

test('authorize method always returns true', function () {
    $request = new PaymentRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns default rules for non-PUT request with matching currencies', function () {
    $companyId = 1;
    $companyCurrency = 'USD';
    $customerCurrency = 'USD';

    $request = Mockery::mock(PaymentRequest::class)->makePartial();
    $request->shouldReceive('isMethod')->with('PUT')->andReturn(false)->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->customer_id = 5; // Simulate customer_id being present in request

    // Fix: Replaced 'alias:' mock with 'spy' for static methods.
    // 'alias:' mocks can cause "class already exists" RuntimeExceptions if not perfectly
    // cleaned up between tests, which can be tricky in some test runners.
    // 'spy' allows mocking static methods and returning values without creating the persistent alias class conflict.
    Mockery::spy(CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency)
        ->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    $mockCustomer = (object)['id' => 5, 'currency_id' => $customerCurrency];
    Mockery::spy(Customer::class)
        ->shouldReceive('find')
        ->with(5)
        ->andReturn($mockCustomer)
        ->once();

    $rules = $request->rules();

    // Assert base rules
    expect($rules)->toHaveKeys([
        'payment_date', 'customer_id', 'exchange_rate', 'amount',
        'payment_number', 'invoice_id', 'payment_method_id', 'notes'
    ]);

    expect($rules['payment_date'])->toContain('required');
    expect($rules['customer_id'])->toContain('required');
    expect($rules['amount'])->toContain('required');
    expect($rules['invoice_id'])->toContain('nullable');
    expect($rules['payment_method_id'])->toContain('nullable');
    expect($rules['notes'])->toContain('nullable');

    // Assert default payment_number unique rule (without ignore)
    expect($rules['payment_number'])->toContain('required');
    $uniqueRule = collect($rules['payment_number'])->first(fn($rule) => $rule instanceof Rule && str_contains((string)$rule, 'unique:payments'));
    expect($uniqueRule)->not->toBeNull();
    expect((string)$uniqueRule)->not->toContain('ignore'); // Should not contain ignore for non-PUT

    // Currencies match, so exchange_rate should be nullable
    expect($rules['exchange_rate'])->toContain('nullable');
    expect($rules['exchange_rate'])->not->toContain('required');
});

test('rules method returns updated payment_number rule for PUT request', function () {
    $companyId = 1;
    $paymentId = 123;
    $companyCurrency = 'USD';
    $customerCurrency = 'USD';

    $request = Mockery::mock(PaymentRequest::class)->makePartial();
    $request->shouldReceive('isMethod')->with('PUT')->andReturn(true)->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->customer_id = 5;

    // Mock the route to get 'payment' id for the ignore clause
    // The original `$mockRoute->id = $paymentId;` did not cause a failure in debug output for Route.
    // Preserving original logic as it seems to be handled by the Route mock's internal behavior.
    $mockRoute = Mockery::mock(Route::class);
    $mockRoute->id = $paymentId; 
    $request->shouldReceive('route')->with('payment')->andReturn($mockRoute)->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    Mockery::spy(CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency)
        ->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    $mockCustomer = (object)['id' => 5, 'currency_id' => $customerCurrency];
    Mockery::spy(Customer::class)
        ->shouldReceive('find')
        ->with(5)
        ->andReturn($mockCustomer)
        ->once();

    $rules = $request->rules();

    // Assert payment_number unique rule with ignore
    expect($rules['payment_number'])->toContain('required');
    $uniqueRule = collect($rules['payment_number'])->first(fn($rule) => $rule instanceof Rule && str_contains((string)$rule, 'unique:payments'));
    expect($uniqueRule)->not->toBeNull();
    expect((string)$uniqueRule)->toContain('ignore', 'The unique rule for PUT method should contain ignore clause.');

    // Exchange rate still nullable as currencies match
    expect($rules['exchange_rate'])->toContain('nullable');
    expect($rules['exchange_rate'])->not->toContain('required');
});

test('rules method makes exchange_rate required when customer and company currencies mismatch', function () {
    $companyId = 1;
    $companyCurrency = 'USD';
    $customerCurrency = 'EUR'; // Mismatch

    $request = Mockery::mock(PaymentRequest::class)->makePartial();
    $request->shouldReceive('isMethod')->with('PUT')->andReturn(false)->once(); // Not PUT, for simpler test
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->customer_id = 5; // Set customer_id on the request

    // Fix: Replaced 'alias:' mock with 'spy'.
    Mockery::spy(CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency)
        ->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    $mockCustomer = (object)['id' => 5, 'currency_id' => $customerCurrency];
    Mockery::spy(Customer::class)
        ->shouldReceive('find')
        ->with(5)
        ->andReturn($mockCustomer)
        ->once();

    $rules = $request->rules();

    // Assert exchange_rate is required
    expect($rules['exchange_rate'])->toContain('required');
    expect($rules['exchange_rate'])->not->toContain('nullable');
});

test('rules method keeps exchange_rate nullable when no customer is found for a provided customer_id', function () {
    $companyId = 1;
    $companyCurrency = 'USD';
    $nonExistentCustomerId = 99;

    $request = Mockery::mock(PaymentRequest::class)->makePartial();
    $request->shouldReceive('isMethod')->with('PUT')->andReturn(false)->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->customer_id = $nonExistentCustomerId; // Non-existent customer

    // Fix: Replaced 'alias:' mock with 'spy'.
    Mockery::spy(CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency)
        ->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    // Mock Customer::find to return null (no customer found)
    Mockery::spy(Customer::class)
        ->shouldReceive('find')
        ->with($nonExistentCustomerId)
        ->andReturn(null)
        ->once();

    $rules = $request->rules();

    // Assert exchange_rate is nullable
    expect($rules['exchange_rate'])->toContain('nullable');
    expect($rules['exchange_rate'])->not->toContain('required');
});

test('rules method keeps exchange_rate nullable when no customer_id is provided in the request', function () {
    $companyId = 1;
    $companyCurrency = 'USD';

    $request = Mockery::mock(PaymentRequest::class)->makePartial();
    $request->shouldReceive('isMethod')->with('PUT')->andReturn(false)->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    // Simulate customer_id not being present in the request data, thus $this->customer_id would be null.

    // Fix: Replaced 'alias:' mock with 'spy'.
    Mockery::spy(CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency)
        ->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    // Mock Customer::find to return null when customer_id is null
    Mockery::spy(Customer::class)
        ->shouldReceive('find')
        ->with(null)
        ->andReturn(null)
        ->once();

    $rules = $request->rules();

    expect($rules['exchange_rate'])->toContain('nullable');
    expect($rules['exchange_rate'])->not->toContain('required');
});

test('rules method keeps exchange_rate nullable when no company currency is found', function () {
    $companyId = 1;
    $customerCurrency = 'EUR';

    $request = Mockery::mock(PaymentRequest::class)->makePartial();
    $request->shouldReceive('isMethod')->with('PUT')->andReturn(false)->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->customer_id = 5;

    // Fix: Replaced 'alias:' mock with 'spy'.
    // Mock CompanySetting::getSetting to return null (no currency setting)
    Mockery::spy(CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn(null)
        ->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    // Mock Customer::find
    $mockCustomer = (object)['id' => 5, 'currency_id' => $customerCurrency];
    Mockery::spy(Customer::class)
        ->shouldReceive('find')
        ->with(5)
        ->andReturn($mockCustomer)
        ->once();

    $rules = $request->rules();

    // Assert exchange_rate is nullable because the condition `$companyCurrency` is false
    expect($rules['exchange_rate'])->toContain('nullable');
    expect($rules['exchange_rate'])->not->toContain('required');
});


test('getPaymentPayload returns correct data when company and current currencies match', function () {
    $companyId = 1;
    $userId = 10;
    $customerId = 20;
    $amount = 100.00;
    $companyCurrency = 'USD';
    $customerCurrency = 'USD'; // Match

    $request = Mockery::mock(PaymentRequest::class)->makePartial();
    $request->currency_id = $customerCurrency; // Represents the currency selected in the form/request
    $request->customer_id = $customerId;
    $request->amount = $amount; // Set amount for base_amount calculation

    $mockUser = Mockery::mock(User::class);
    // Fix: Mockery throws BadMethodCallException when setting properties on a strict mock
    // (like User, which is an Eloquent Model) because it intercepts `__set()` calls that delegate to `setAttribute()`.
    // To resolve, mock the `getAttribute('id')` call, which is what `->id` accessor internally uses for reading.
    $mockUser->shouldReceive('getAttribute')->with('id')->andReturn($userId)->once();

    $request->shouldReceive('validated')->andReturn([
        'payment_date' => '2023-01-01',
        'customer_id' => $customerId,
        'amount' => $amount,
        'payment_number' => 'PAY-001',
        // exchange_rate would not be in validated if nullable and not provided
    ])->once();
    $request->shouldReceive('user')->andReturn($mockUser)->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    Mockery::spy(CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency)
        ->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    $mockCustomer = (object)['id' => $customerId, 'currency_id' => $customerCurrency];
    Mockery::spy(Customer::class)
        ->shouldReceive('find')
        ->with($customerId)
        ->andReturn($mockCustomer)
        ->once();

    $payload = $request->getPaymentPayload();

    expect($payload)->toBeArray();
    expect($payload)->toHaveKeys([
        'payment_date', 'customer_id', 'amount', 'payment_number',
        'creator_id', 'company_id', 'exchange_rate', 'base_amount', 'currency_id'
    ]);

    expect($payload['creator_id'])->toBe($userId);
    expect($payload['company_id'])->toBe($companyId);
    expect($payload['currency_id'])->toBe($customerCurrency);
    expect($payload['exchange_rate'])->toBe(1); // Currencies match, so exchange_rate is 1
    expect($payload['base_amount'])->toBe($amount * 1); // amount * 1
});

test('getPaymentPayload returns correct data when company and current currencies mismatch', function () {
    $companyId = 1;
    $userId = 10;
    $customerId = 20;
    $amount = 100.00;
    $exchangeRate = 1.25;
    $companyCurrency = 'USD';
    $customerCurrency = 'EUR'; // Mismatch

    $request = Mockery::mock(PaymentRequest::class)->makePartial();
    $request->currency_id = $customerCurrency; // Represents the currency selected in the form/request
    $request->customer_id = $customerId;
    $request->exchange_rate = $exchangeRate; // This would be present if 'required' and provided
    $request->amount = $amount; // Set amount for base_amount calculation

    $mockUser = Mockery::mock(User::class);
    // Fix: Mock the getter for 'id'
    $mockUser->shouldReceive('getAttribute')->with('id')->andReturn($userId)->once();

    $request->shouldReceive('validated')->andReturn([
        'payment_date' => '2023-01-01',
        'customer_id' => $customerId,
        'amount' => $amount,
        'payment_number' => 'PAY-001',
        'exchange_rate' => $exchangeRate, // Would be present if required and provided
    ])->once();
    $request->shouldReceive('user')->andReturn($mockUser)->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    Mockery::spy(CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency)
        ->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    $mockCustomer = (object)['id' => $customerId, 'currency_id' => $customerCurrency];
    Mockery::spy(Customer::class)
        ->shouldReceive('find')
        ->with($customerId)
        ->andReturn($mockCustomer)
        ->once();

    $payload = $request->getPaymentPayload();

    expect($payload)->toBeArray();
    expect($payload['creator_id'])->toBe($userId);
    expect($payload['company_id'])->toBe($companyId);
    expect($payload['currency_id'])->toBe($customerCurrency);
    expect($payload['exchange_rate'])->toBe($exchangeRate); // Currencies mismatch, so provided exchange_rate is used
    expect($payload['base_amount'])->toBe($amount * $exchangeRate); // amount * exchange_rate
});

test('getPaymentPayload handles optional fields not being validated', function () {
    $companyId = 1;
    $userId = 10;
    $customerId = 20;
    $amount = 50.00;
    $companyCurrency = 'USD';
    $customerCurrency = 'USD';

    $request = Mockery::mock(PaymentRequest::class)->makePartial();
    $request->currency_id = $customerCurrency;
    $request->customer_id = $customerId;
    $request->amount = $amount; // Set amount for base_amount calculation

    $mockUser = Mockery::mock(User::class);
    // Fix: Mock the getter for 'id'
    $mockUser->shouldReceive('getAttribute')->with('id')->andReturn($userId)->once();

    $request->shouldReceive('validated')->andReturn([
        'payment_date' => '2023-01-01',
        'customer_id' => $customerId,
        'amount' => $amount,
        'payment_number' => 'PAY-002',
        // invoice_id, payment_method_id, notes are omitted as optional and not provided
    ])->once();
    $request->shouldReceive('user')->andReturn($mockUser)->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    Mockery::spy(CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency)
        ->once();

    $mockCustomer = (object)['id' => $customerId, 'currency_id' => $customerCurrency];
    // Fix: Replaced 'alias:' mock with 'spy'.
    Mockery::spy(Customer::class)
        ->shouldReceive('find')
        ->with($customerId)
        ->andReturn($mockCustomer)
        ->once();

    $payload = $request->getPaymentPayload();

    expect($payload)->toBeArray();
    expect($payload)->not->toHaveKey('invoice_id');
    expect($payload)->not->toHaveKey('payment_method_id');
    expect($payload)->not->toHaveKey('notes');
    expect($payload['amount'])->toBe($amount);
    expect($payload['base_amount'])->toBe($amount * 1); // Currency match, so exchange_rate is 1
});

test('getPaymentPayload throws TypeError if customer not found during payload construction', function () {
    $companyId = 1;
    $userId = 10;
    $customerId = 99; // Non-existent customer
    $amount = 100.00;
    $companyCurrency = 'USD';
    $customerCurrency = 'EUR';

    $request = Mockery::mock(PaymentRequest::class)->makePartial();
    $request->currency_id = $customerCurrency;
    $request->customer_id = $customerId;
    $request->exchange_rate = 1.2;
    $request->amount = $amount;

    $mockUser = Mockery::mock(User::class);
    // Fix: Mock the getter for 'id'
    $mockUser->shouldReceive('getAttribute')->with('id')->andReturn($userId)->once();

    $request->shouldReceive('validated')->andReturn([
        'payment_date' => '2023-01-01',
        'customer_id' => $customerId,
        'amount' => $amount,
        'payment_number' => 'PAY-001',
        'exchange_rate' => 1.2,
    ])->once();
    $request->shouldReceive('user')->andReturn($mockUser)->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    Mockery::spy(CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency)
        ->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    // Mock Customer::find to return null
    Mockery::spy(Customer::class)
        ->shouldReceive('find')
        ->with($customerId)
        ->andReturn(null)
        ->once();

    // Expect an exception because Customer::find(null)->currency_id would be called
    expect(fn() => $request->getPaymentPayload())
        ->toThrow(TypeError::class, "Attempt to read property \"currency_id\" on null");
});

test('getPaymentPayload ensures currency_id is from the customer model, not request input', function () {
    $companyId = 1;
    $userId = 10;
    $customerId = 20;
    $amount = 100.00;
    $companyCurrency = 'USD';
    $customerModelCurrency = 'GBP'; // Customer model's actual currency
    $requestInputCurrency = 'CAD'; // What might be in $this->currency_id from request input

    $request = Mockery::mock(PaymentRequest::class)->makePartial();
    $request->currency_id = $requestInputCurrency; // Simulate currency_id from request input
    $request->customer_id = $customerId;
    $request->exchange_rate = 1.5;
    $request->amount = $amount;

    $mockUser = Mockery::mock(User::class);
    // Fix: Mock the getter for 'id'
    $mockUser->shouldReceive('getAttribute')->with('id')->andReturn($userId)->once();

    $request->shouldReceive('validated')->andReturn([
        'payment_date' => '2023-01-01',
        'customer_id' => $customerId,
        'amount' => $amount,
        'payment_number' => 'PAY-001',
        'exchange_rate' => 1.5,
        'currency_id' => $requestInputCurrency, // Even if currency_id is in validated payload, it should be overwritten
    ])->once();
    $request->shouldReceive('user')->andReturn($mockUser)->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    // Fix: Replaced 'alias:' mock with 'spy'.
    Mockery::spy(CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrency)
        ->once();

    $mockCustomer = (object)['id' => $customerId, 'currency_id' => $customerModelCurrency]; // This is the source of truth
    // Fix: Replaced 'alias:' mock with 'spy'.
    Mockery::spy(Customer::class)
        ->shouldReceive('find')
        ->with($customerId)
        ->andReturn($mockCustomer)
        ->once();

    $payload = $request->getPaymentPayload();

    expect($payload['currency_id'])->toBe($customerModelCurrency); // Should take from Customer::find
    expect($payload['exchange_rate'])->toBe(1.5); // Derived from comparison of $companyCurrency and $requestInputCurrency
});

// Removed the afterAll(Mockery::close()) as afterEach is preferred for isolating tests.
// afterAll(function () {
//     Mockery::close();
// });


// This afterEach hook is crucial for ensuring Mockery cleans up mocks after each test,
// preventing interference and "class already exists" errors between tests.
afterEach(function () {
    Mockery::close();
});
```