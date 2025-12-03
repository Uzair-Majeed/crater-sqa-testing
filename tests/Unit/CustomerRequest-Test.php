<?php

use Crater\Http\Requests\CustomerRequest;
use Crater\Models\Address;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Mockery as m;

beforeEach(function () {
    $this->customerRequest = m::mock(CustomerRequest::class)->makePartial();
    // Ensure ARBITRARY property setup does not trigger $request->$property access exceptions
    $this->customerRequest->shipping = [];
    $this->customerRequest->billing = [];
});

afterEach(function () {
    m::close();
});

test('authorize method always returns true', function () {
    $request = new CustomerRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns default validation rules for non-PUT requests including unique rule without ignore and correct company_id', function () {
    $this->customerRequest->shouldReceive('isMethod')->with('PUT')->andReturn(false);
    $this->customerRequest->shouldReceive('header')->with('company')->andReturn(1);
    $this->customerRequest->email = null;

    $rules = $this->customerRequest->rules();

    expect($rules)->toBeArray();
    expect($rules['name'])->toContain('required');
    expect($rules['email'])->toContain('email', 'nullable');
    expect($rules['password'])->toContain('nullable');
    expect($rules['enable_portal'])->toContain('boolean');

    $uniqueRule = Arr::first($rules['email'], fn ($rule) => $rule instanceof Unique);
    expect($uniqueRule)->toBeInstanceOf(Unique::class);

    $reflection = new ReflectionClass($uniqueRule);
    
    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    expect($tableProperty->getValue($uniqueRule))->toBe('customers');

    $ignoreProperty = $reflection->getProperty('ignore');
    $ignoreProperty->setAccessible(true);
    expect($ignoreProperty->getValue($uniqueRule))->toBeNull();

    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);
    $wheres = $wheresProperty->getValue($uniqueRule);
    expect($wheres)->toContain(['column' => 'company_id', 'value' => 1]);

    expect($rules)->toHaveKeys([
        'phone', 'company_name', 'contact_name', 'website', 'prefix', 'currency_id',
        'billing.name', 'billing.address_street_1', 'billing.city', 'billing.country_id',
        'shipping.name', 'shipping.address_street_1', 'shipping.city', 'shipping.country_id',
    ]);
});

test('rules method returns updated validation rules for PUT requests with email, including unique rule with ignore and correct company_id', function () {
    $this->customerRequest->shouldReceive('isMethod')->with('PUT')->andReturn(true);
    $this->customerRequest->shouldReceive('header')->with('company')->andReturn(2);

    $this->customerRequest->email = 'test@example.com';
    $customerMock = (object) ['id' => 10];
    $this->customerRequest->shouldReceive('route')->with('customer')->andReturn($customerMock);

    $rules = $this->customerRequest->rules();

    expect($rules)->toBeArray();
    expect($rules['email'])->toContain('email', 'nullable');

    $uniqueRule = Arr::first($rules['email'], fn ($rule) => $rule instanceof Unique);
    expect($uniqueRule)->toBeInstanceOf(Unique::class);

    $reflection = new ReflectionClass($uniqueRule);

    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    expect($tableProperty->getValue($uniqueRule))->toBe('customers');

    $ignoreProperty = $reflection->getProperty('ignore');
    $ignoreProperty->setAccessible(true);
    expect($ignoreProperty->getValue($uniqueRule))->toBe(10);

    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);
    $wheres = $wheresProperty->getValue($uniqueRule);
    expect($wheres)->toContain(['column' => 'company_id', 'value' => 2]);
});

test('rules method returns default validation rules for PUT requests without email, including unique rule without ignore and correct company_id', function () {
    $this->customerRequest->shouldReceive('isMethod')->with('PUT')->andReturn(true);
    $this->customerRequest->shouldReceive('header')->with('company')->andReturn(3);

    $this->customerRequest->email = null;

    $rules = $this->customerRequest->rules();

    expect($rules)->toBeArray();
    expect($rules['email'])->toContain('email', 'nullable');

    $uniqueRule = Arr::first($rules['email'], fn ($rule) => $rule instanceof Unique);
    expect($uniqueRule)->toBeInstanceOf(Unique::class);

    $reflection = new ReflectionClass($uniqueRule);

    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    expect($tableProperty->getValue($uniqueRule))->toBe('customers');

    $ignoreProperty = $reflection->getProperty('ignore');
    $ignoreProperty->setAccessible(true);
    expect($ignoreProperty->getValue($uniqueRule))->toBeNull();

    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);
    $wheres = $wheresProperty->getValue($uniqueRule);
    expect($wheres)->toContain(['column' => 'company_id', 'value' => 3]);
});

test('getCustomerPayload returns correct data when all fields are present', function () {
    $validatedData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'currency_id' => 1,
        'password' => 'secret',
        'phone' => '123-456-7890',
        'prefix' => 'CUST-',
        'company_name' => 'Acme Inc.',
        'contact_name' => 'Jane Smith',
        'website' => 'acme.com',
        'enable_portal' => true,
        'estimate_prefix' => 'EST-',
        'payment_prefix' => 'PAY-',
        'invoice_prefix' => 'INV-',
        'extra_field' => 'should_be_ignored',
    ];

    $userMock = (object) ['id' => 5];
    $this->customerRequest->shouldReceive('validated')->andReturn($validatedData);
    $this->customerRequest->shouldReceive('user')->andReturn($userMock);
    $this->customerRequest->shouldReceive('header')->with('company')->andReturn(100);

    $payload = $this->customerRequest->getCustomerPayload();

    expect($payload)->toEqual([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'currency_id' => 1,
        'password' => 'secret',
        'phone' => '123-456-7890',
        'prefix' => 'CUST-',
        'company_name' => 'Acme Inc.',
        'contact_name' => 'Jane Smith',
        'website' => 'acme.com',
        'enable_portal' => true,
        'estimate_prefix' => 'EST-',
        'payment_prefix' => 'PAY-',
        'invoice_prefix' => 'INV-',
        'creator_id' => 5,
        'company_id' => 100,
    ]);
});

test('getCustomerPayload returns correct data when only some fields are present', function () {
    $validatedData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'enable_portal' => false,
    ];

    $userMock = (object) ['id' => 5];
    $this->customerRequest->shouldReceive('validated')->andReturn($validatedData);
    $this->customerRequest->shouldReceive('user')->andReturn($userMock);
    $this->customerRequest->shouldReceive('header')->with('company')->andReturn(100);

    $payload = $this->customerRequest->getCustomerPayload();

    expect($payload)->toEqual([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'enable_portal' => false,
        'creator_id' => 5,
        'company_id' => 100,
    ]);

    expect($payload)->not->toHaveKey('password');
    expect($payload)->not->toHaveKey('phone');
});

test('getCustomerPayload handles empty validated data', function () {
    $validatedData = [];

    $userMock = (object) ['id' => 5];
    $this->customerRequest->shouldReceive('validated')->andReturn($validatedData);
    $this->customerRequest->shouldReceive('user')->andReturn($userMock);
    $this->customerRequest->shouldReceive('header')->with('company')->andReturn(100);

    $payload = $this->customerRequest->getCustomerPayload();

    expect($payload)->toEqual([
        'creator_id' => 5,
        'company_id' => 100,
    ]);
});

test('getShippingAddress returns correct data with type', function () {
    $shippingData = [
        'name' => 'Shipping Name',
        'address_street_1' => '123 Shipping St',
        'city' => 'Ship City',
    ];

    $this->customerRequest->shipping = $shippingData;

    $address = $this->customerRequest->getShippingAddress();

    expect($address)->toEqual([
        'name' => 'Shipping Name',
        'address_street_1' => '123 Shipping St',
        'city' => 'Ship City',
        'type' => Address::SHIPPING_TYPE,
    ]);
});

test('getShippingAddress returns only type when shipping data is empty', function () {
    $shippingData = [];

    $this->customerRequest->shipping = $shippingData;

    $address = $this->customerRequest->getShippingAddress();

    expect($address)->toEqual([
        'type' => Address::SHIPPING_TYPE,
    ]);
});

test('getBillingAddress returns correct data with type', function () {
    $billingData = [
        'name' => 'Billing Name',
        'address_street_1' => '456 Billing Ave',
        'state' => 'Bill State',
    ];

    $this->customerRequest->billing = $billingData;

    $address = $this->customerRequest->getBillingAddress();

    expect($address)->toEqual([
        'name' => 'Billing Name',
        'address_street_1' => '456 Billing Ave',
        'state' => 'Bill State',
        'type' => Address::BILLING_TYPE,
    ]);
});

test('getBillingAddress returns only type when billing data is empty', function () {
    $billingData = [];

    $this->customerRequest->billing = $billingData;

    $address = $this->customerRequest->getBillingAddress();

    expect($address)->toEqual([
        'type' => Address::BILLING_TYPE,
    ]);
});

test('hasAddress returns only non-null values from an array', function () {
    $addressData = [
        'name' => 'Test Name',
        'street' => '123 Main St',
        'city' => null,
        'state' => '',
        'zip' => 12345,
        'country_id' => null,
        'phone' => false,
        'fax' => 0,
    ];

    $result = $this->customerRequest->hasAddress($addressData);

    expect($result)->toEqual([
        'name' => 'Test Name',
        'street' => '123 Main St',
        'state' => '',
        'zip' => 12345,
        'phone' => false,
        'fax' => 0,
    ]);
    expect($result)->not->toHaveKey('city');
    expect($result)->not->toHaveKey('country_id');
});

test('hasAddress returns empty array if all values are null', function () {
    $addressData = [
        'name' => null,
        'street' => null,
        'city' => null,
    ];

    $result = $this->customerRequest->hasAddress($addressData);

    expect($result)->toEqual([]);
});

test('hasAddress returns the same array if no values are null', function () {
    $addressData = [
        'name' => 'Value',
        'street' => 'Another Value',
        'city' => 'Some City',
    ];

    $result = $this->customerRequest->hasAddress($addressData);

    expect($result)->toEqual($addressData);
});

test('hasAddress handles an empty input array', function () {
    $addressData = [];

    $result = $this->customerRequest->hasAddress($addressData);

    expect($result)->toEqual([]);
});