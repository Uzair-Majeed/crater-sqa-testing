<?php

use Crater\Http\Requests\Customer\CustomerProfileRequest;
use Crater\Models\Address;
use Illuminate\Support\Facades\Validator;

// Test authorize method
test('authorize method returns true', function () {
    $request = new CustomerProfileRequest();
    
    expect($request->authorize())->toBeTrue();
});

// Test rules method returns correct structure
test('rules method returns correct validation rules structure', function () {
    $request = new CustomerProfileRequest();
    
    $rules = $request->rules();
    
    // Verify it's an array
    expect($rules)->toBeArray();
    
    // Verify all expected keys exist
    expect($rules)->toHaveKeys([
        'name', 
        'password', 
        'email',
        'billing.name', 
        'billing.address_street_1', 
        'billing.address_street_2', 
        'billing.city',
        'billing.state', 
        'billing.country_id', 
        'billing.zip', 
        'billing.phone', 
        'billing.fax',
        'shipping.name', 
        'shipping.address_street_1', 
        'shipping.address_street_2', 
        'shipping.city',
        'shipping.state', 
        'shipping.country_id', 
        'shipping.zip', 
        'shipping.phone', 
        'shipping.fax',
        'customer_avatar',
    ]);
});

// Test specific validation rules
test('name field has nullable validation', function () {
    $request = new CustomerProfileRequest();
    $rules = $request->rules();
    
    expect($rules['name'])->toContain('nullable');
});

test('password field has nullable and min:8 validation', function () {
    $request = new CustomerProfileRequest();
    $rules = $request->rules();
    
    expect($rules['password'])->toContain('nullable')
        ->and($rules['password'])->toContain('min:8');
});

test('email field has nullable and email validation', function () {
    $request = new CustomerProfileRequest();
    $rules = $request->rules();
    
    expect($rules['email'])->toContain('nullable')
        ->and($rules['email'])->toContain('email');
});

test('customer_avatar has correct validation rules', function () {
    $request = new CustomerProfileRequest();
    $rules = $request->rules();
    
    expect($rules['customer_avatar'])->toContain('nullable')
        ->and($rules['customer_avatar'])->toContain('file')
        ->and($rules['customer_avatar'])->toContain('mimes:gif,jpg,png')
        ->and($rules['customer_avatar'])->toContain('max:20000');
});

// Test all billing fields are nullable
test('all billing fields have nullable validation', function () {
    $request = new CustomerProfileRequest();
    $rules = $request->rules();
    
    $billingFields = [
        'billing.name',
        'billing.address_street_1',
        'billing.address_street_2',
        'billing.city',
        'billing.state',
        'billing.country_id',
        'billing.zip',
        'billing.phone',
        'billing.fax',
    ];
    
    foreach ($billingFields as $field) {
        expect($rules[$field])->toContain('nullable');
    }
});

// Test all shipping fields are nullable
test('all shipping fields have nullable validation', function () {
    $request = new CustomerProfileRequest();
    $rules = $request->rules();
    
    $shippingFields = [
        'shipping.name',
        'shipping.address_street_1',
        'shipping.address_street_2',
        'shipping.city',
        'shipping.state',
        'shipping.country_id',
        'shipping.zip',
        'shipping.phone',
        'shipping.fax',
    ];
    
    foreach ($shippingFields as $field) {
        expect($rules[$field])->toContain('nullable');
    }
});

// Test validation passes with valid data
test('validation passes with valid complete data', function () {
    $request = new CustomerProfileRequest();
    
    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'billing' => [
            'name' => 'John Doe',
            'address_street_1' => '123 Main St',
            'address_street_2' => 'Apt 4',
            'city' => 'New York',
            'state' => 'NY',
            'country_id' => 1,
            'zip' => '10001',
            'phone' => '555-1234',
            'fax' => '555-5678',
        ],
        'shipping' => [
            'name' => 'John Doe',
            'address_street_1' => '456 Oak Ave',
            'address_street_2' => 'Suite 200',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'country_id' => 1,
            'zip' => '90001',
            'phone' => '555-9999',
            'fax' => '555-8888',
        ],
    ];
    
    // Get rules but exclude the unique rule for this test
    $rules = $request->rules();
    $simpleRules = $rules;
    $simpleRules['email'] = ['nullable', 'email'];
    
    $validator = Validator::make($data, $simpleRules);
    
    expect($validator->passes())->toBeTrue();
});

// Test validation passes with minimal data (all nullable)
test('validation passes with minimal data since all fields are nullable', function () {
    $request = new CustomerProfileRequest();
    
    $data = [];
    
    $rules = $request->rules();
    $simpleRules = $rules;
    $simpleRules['email'] = ['nullable', 'email'];
    
    $validator = Validator::make($data, $simpleRules);
    
    expect($validator->passes())->toBeTrue();
});

// Test validation fails with invalid email
test('validation fails with invalid email format', function () {
    $request = new CustomerProfileRequest();
    
    $data = [
        'email' => 'not-an-email',
    ];
    
    $rules = $request->rules();
    $simpleRules = $rules;
    $simpleRules['email'] = ['nullable', 'email'];
    
    $validator = Validator::make($data, $simpleRules);
    
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('email'))->toBeTrue();
});

// Test validation fails with short password
test('validation fails with password shorter than 8 characters', function () {
    $request = new CustomerProfileRequest();
    
    $data = [
        'password' => 'short',
    ];
    
    $validator = Validator::make($data, $request->rules());
    
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('password'))->toBeTrue();
});

// Test getShippingAddress method
test('getShippingAddress returns correct array with shipping data and type', function () {
    $shippingData = [
        'name' => 'Shipping Name',
        'address_street_1' => '123 Shipping St',
        'city' => 'Shipping City',
        'country_id' => 1,
    ];
    
    $request = CustomerProfileRequest::create('/test', 'POST', ['shipping' => $shippingData]);
    
    $result = $request->getShippingAddress();
    
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('type')
        ->and($result['type'])->toBe(Address::SHIPPING_TYPE)
        ->and($result)->toHaveKey('name')
        ->and($result['name'])->toBe('Shipping Name')
        ->and($result)->toHaveKey('address_street_1')
        ->and($result['address_street_1'])->toBe('123 Shipping St')
        ->and($result)->toHaveKey('city')
        ->and($result['city'])->toBe('Shipping City')
        ->and($result)->toHaveKey('country_id')
        ->and($result['country_id'])->toBe(1);
});

// Test getShippingAddress with empty data
test('getShippingAddress returns array with type when shipping data is empty', function () {
    $request = CustomerProfileRequest::create('/test', 'POST', ['shipping' => []]);
    
    $result = $request->getShippingAddress();
    
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('type')
        ->and($result['type'])->toBe(Address::SHIPPING_TYPE);
});

// Test getShippingAddress with null data
test('getShippingAddress returns array with type when shipping data is null', function () {
    $request = CustomerProfileRequest::create('/test', 'POST', []);
    
    $result = $request->getShippingAddress();
    
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('type')
        ->and($result['type'])->toBe(Address::SHIPPING_TYPE);
});

// Test getBillingAddress method
test('getBillingAddress returns correct array with billing data and type', function () {
    $billingData = [
        'name' => 'Billing Name',
        'address_street_1' => '456 Billing Ave',
        'city' => 'Billing City',
        'country_id' => 2,
    ];
    
    $request = CustomerProfileRequest::create('/test', 'POST', ['billing' => $billingData]);
    
    $result = $request->getBillingAddress();
    
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('type')
        ->and($result['type'])->toBe(Address::BILLING_TYPE)
        ->and($result)->toHaveKey('name')
        ->and($result['name'])->toBe('Billing Name')
        ->and($result)->toHaveKey('address_street_1')
        ->and($result['address_street_1'])->toBe('456 Billing Ave')
        ->and($result)->toHaveKey('city')
        ->and($result['city'])->toBe('Billing City')
        ->and($result)->toHaveKey('country_id')
        ->and($result['country_id'])->toBe(2);
});

// Test getBillingAddress with empty data
test('getBillingAddress returns array with type when billing data is empty', function () {
    $request = CustomerProfileRequest::create('/test', 'POST', ['billing' => []]);
    
    $result = $request->getBillingAddress();
    
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('type')
        ->and($result['type'])->toBe(Address::BILLING_TYPE);
});

// Test getBillingAddress with null data
test('getBillingAddress returns array with type when billing data is null', function () {
    $request = CustomerProfileRequest::create('/test', 'POST', []);
    
    $result = $request->getBillingAddress();
    
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('type')
        ->and($result['type'])->toBe(Address::BILLING_TYPE);
});

// Test that both address methods work with complete data
test('both address methods work correctly with complete data', function () {
    $data = [
        'billing' => [
            'name' => 'Bill Name',
            'address_street_1' => '111 Bill St',
            'city' => 'Bill City',
        ],
        'shipping' => [
            'name' => 'Ship Name',
            'address_street_1' => '222 Ship St',
            'city' => 'Ship City',
        ],
    ];
    
    $request = CustomerProfileRequest::create('/test', 'POST', $data);
    
    $billing = $request->getBillingAddress();
    $shipping = $request->getShippingAddress();
    
    expect($billing['type'])->toBe(Address::BILLING_TYPE)
        ->and($billing['name'])->toBe('Bill Name')
        ->and($shipping['type'])->toBe(Address::SHIPPING_TYPE)
        ->and($shipping['name'])->toBe('Ship Name');
});

// Test Address type constants
test('Address type constants are correctly used', function () {
    $request = CustomerProfileRequest::create('/test', 'POST', []);
    
    $billing = $request->getBillingAddress();
    $shipping = $request->getShippingAddress();
    
    expect($billing['type'])->toBe(Address::BILLING_TYPE)
        ->and($shipping['type'])->toBe(Address::SHIPPING_TYPE)
        ->and($billing['type'])->not->toBe($shipping['type']);
});

// Test that methods return arrays, not collections
test('address methods return arrays not collections', function () {
    $request = CustomerProfileRequest::create('/test', 'POST', [
        'billing' => ['name' => 'Test'],
        'shipping' => ['name' => 'Test'],
    ]);
    
    $billing = $request->getBillingAddress();
    $shipping = $request->getShippingAddress();
    
    expect($billing)->toBeArray()
        ->and($shipping)->toBeArray();
});

// Test validation with only name
test('validation passes with only name provided', function () {
    $request = new CustomerProfileRequest();
    
    $data = ['name' => 'John Doe'];
    
    $rules = $request->rules();
    $simpleRules = $rules;
    $simpleRules['email'] = ['nullable', 'email'];
    
    $validator = Validator::make($data, $simpleRules);
    
    expect($validator->passes())->toBeTrue();
});

// Test validation with only email
test('validation passes with only valid email provided', function () {
    $request = new CustomerProfileRequest();
    
    $data = ['email' => 'test@example.com'];
    
    $rules = $request->rules();
    $simpleRules = $rules;
    $simpleRules['email'] = ['nullable', 'email'];
    
    $validator = Validator::make($data, $simpleRules);
    
    expect($validator->passes())->toBeTrue();
});

// Test validation with only password
test('validation passes with valid password provided', function () {
    $request = new CustomerProfileRequest();
    
    $data = ['password' => 'validpassword123'];
    
    $validator = Validator::make($data, $request->rules());
    
    expect($validator->passes())->toBeTrue();
});