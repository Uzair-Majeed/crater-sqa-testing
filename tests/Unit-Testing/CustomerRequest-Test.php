<?php

use Crater\Http\Requests\CustomerRequest;
use Crater\Models\Address;
use Illuminate\Support\Facades\Validator;

// Test authorize method
test('authorize method always returns true', function () {
    $request = new CustomerRequest();
    expect($request->authorize())->toBeTrue();
});

// Test rules method returns correct structure
test('rules method returns validation rules with all required fields', function () {
    $request = CustomerRequest::create('/test', 'POST', []);
    $rules = $request->rules();
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('name')
        ->and($rules)->toHaveKey('email')
        ->and($rules)->toHaveKey('password')
        ->and($rules)->toHaveKey('phone')
        ->and($rules)->toHaveKey('company_name')
        ->and($rules)->toHaveKey('contact_name')
        ->and($rules)->toHaveKey('website')
        ->and($rules)->toHaveKey('prefix')
        ->and($rules)->toHaveKey('enable_portal')
        ->and($rules)->toHaveKey('currency_id');
});

// Test billing address rules
test('rules method includes all billing address fields', function () {
    $request = CustomerRequest::create('/test', 'POST', []);
    $rules = $request->rules();
    
    expect($rules)->toHaveKey('billing.name')
        ->and($rules)->toHaveKey('billing.address_street_1')
        ->and($rules)->toHaveKey('billing.address_street_2')
        ->and($rules)->toHaveKey('billing.city')
        ->and($rules)->toHaveKey('billing.state')
        ->and($rules)->toHaveKey('billing.country_id')
        ->and($rules)->toHaveKey('billing.zip')
        ->and($rules)->toHaveKey('billing.phone')
        ->and($rules)->toHaveKey('billing.fax');
});

// Test shipping address rules
test('rules method includes all shipping address fields', function () {
    $request = CustomerRequest::create('/test', 'POST', []);
    $rules = $request->rules();
    
    expect($rules)->toHaveKey('shipping.name')
        ->and($rules)->toHaveKey('shipping.address_street_1')
        ->and($rules)->toHaveKey('shipping.address_street_2')
        ->and($rules)->toHaveKey('shipping.city')
        ->and($rules)->toHaveKey('shipping.state')
        ->and($rules)->toHaveKey('shipping.country_id')
        ->and($rules)->toHaveKey('shipping.zip')
        ->and($rules)->toHaveKey('shipping.phone')
        ->and($rules)->toHaveKey('shipping.fax');
});

// Test specific validation rules
test('name field is required', function () {
    $request = CustomerRequest::create('/test', 'POST', []);
    $rules = $request->rules();
    
    expect($rules['name'])->toContain('required');
});

test('email field has email and nullable validation', function () {
    $request = CustomerRequest::create('/test', 'POST', []);
    $rules = $request->rules();
    
    expect($rules['email'])->toContain('email')
        ->and($rules['email'])->toContain('nullable');
});

test('enable_portal field has boolean validation', function () {
    $request = CustomerRequest::create('/test', 'POST', []);
    $rules = $request->rules();
    
    expect($rules['enable_portal'])->toContain('boolean');
});

// Test validation with valid data
test('validation passes with complete valid data', function () {
    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secret123',
        'phone' => '123-456-7890',
        'company_name' => 'Acme Inc.',
        'contact_name' => 'Jane Smith',
        'website' => 'acme.com',
        'enable_portal' => true,
        'currency_id' => 1,
        'prefix' => 'CUST-',
        'billing' => [
            'name' => 'Billing Name',
            'address_street_1' => '123 Main St',
            'city' => 'New York',
            'country_id' => 1,
        ],
        'shipping' => [
            'name' => 'Shipping Name',
            'address_street_1' => '456 Oak Ave',
            'city' => 'Los Angeles',
            'country_id' => 1,
        ],
    ];
    
    $request = CustomerRequest::create('/test', 'POST', $data);
    $rules = $request->rules();
    
    // Remove unique rule for testing
    $simpleRules = $rules;
    $simpleRules['email'] = ['email', 'nullable'];
    
    $validator = Validator::make($data, $simpleRules);
    
    expect($validator->passes())->toBeTrue();
});

// Test validation fails without required name
test('validation fails when name is missing', function () {
    $data = [
        'email' => 'test@example.com',
    ];
    
    $request = CustomerRequest::create('/test', 'POST', $data);
    $rules = $request->rules();
    $simpleRules = $rules;
    $simpleRules['email'] = ['email', 'nullable'];
    
    $validator = Validator::make($data, $simpleRules);
    
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('name'))->toBeTrue();
});

// Test validation fails with invalid email
test('validation fails with invalid email format', function () {
    $data = [
        'name' => 'John Doe',
        'email' => 'not-an-email',
    ];
    
    $request = CustomerRequest::create('/test', 'POST', $data);
    $rules = $request->rules();
    $simpleRules = $rules;
    $simpleRules['email'] = ['email', 'nullable'];
    
    $validator = Validator::make($data, $simpleRules);
    
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('email'))->toBeTrue();
});

// Test getShippingAddress method
test('getShippingAddress returns correct data with type', function () {
    $shippingData = [
        'name' => 'Shipping Name',
        'address_street_1' => '123 Shipping St',
        'city' => 'Ship City',
    ];
    
    $request = CustomerRequest::create('/test', 'POST', ['shipping' => $shippingData]);
    
    $address = $request->getShippingAddress();
    
    expect($address)->toBeArray()
        ->and($address)->toHaveKey('type')
        ->and($address['type'])->toBe(Address::SHIPPING_TYPE)
        ->and($address)->toHaveKey('name')
        ->and($address['name'])->toBe('Shipping Name')
        ->and($address)->toHaveKey('address_street_1')
        ->and($address['address_street_1'])->toBe('123 Shipping St')
        ->and($address)->toHaveKey('city')
        ->and($address['city'])->toBe('Ship City');
});

test('getShippingAddress returns only type when shipping data is empty', function () {
    $request = CustomerRequest::create('/test', 'POST', ['shipping' => []]);
    
    $address = $request->getShippingAddress();
    
    expect($address)->toBeArray()
        ->and($address)->toHaveKey('type')
        ->and($address['type'])->toBe(Address::SHIPPING_TYPE);
});

// Test getBillingAddress method
test('getBillingAddress returns correct data with type', function () {
    $billingData = [
        'name' => 'Billing Name',
        'address_street_1' => '456 Billing Ave',
        'state' => 'Bill State',
    ];
    
    $request = CustomerRequest::create('/test', 'POST', ['billing' => $billingData]);
    
    $address = $request->getBillingAddress();
    
    expect($address)->toBeArray()
        ->and($address)->toHaveKey('type')
        ->and($address['type'])->toBe(Address::BILLING_TYPE)
        ->and($address)->toHaveKey('name')
        ->and($address['name'])->toBe('Billing Name')
        ->and($address)->toHaveKey('address_street_1')
        ->and($address['address_street_1'])->toBe('456 Billing Ave')
        ->and($address)->toHaveKey('state')
        ->and($address['state'])->toBe('Bill State');
});

test('getBillingAddress returns only type when billing data is empty', function () {
    $request = CustomerRequest::create('/test', 'POST', ['billing' => []]);
    
    $address = $request->getBillingAddress();
    
    expect($address)->toBeArray()
        ->and($address)->toHaveKey('type')
        ->and($address['type'])->toBe(Address::BILLING_TYPE);
});

// Test hasAddress method
test('hasAddress returns only non-null values from array', function () {
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
    
    $request = new CustomerRequest();
    $result = $request->hasAddress($addressData);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('name')
        ->and($result['name'])->toBe('Test Name')
        ->and($result)->toHaveKey('street')
        ->and($result['street'])->toBe('123 Main St')
        ->and($result)->not->toHaveKey('city')
        ->and($result)->not->toHaveKey('country_id');
});

test('hasAddress returns empty array if all values are null', function () {
    $addressData = [
        'name' => null,
        'street' => null,
        'city' => null,
    ];
    
    $request = new CustomerRequest();
    $result = $request->hasAddress($addressData);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('hasAddress returns same array if no values are null', function () {
    $addressData = [
        'name' => 'Value',
        'street' => 'Another Value',
        'city' => 'Some City',
    ];
    
    $request = new CustomerRequest();
    $result = $request->hasAddress($addressData);
    
    expect($result)->toEqual($addressData);
});

test('hasAddress handles empty input array', function () {
    $addressData = [];
    
    $request = new CustomerRequest();
    $result = $request->hasAddress($addressData);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

// Test that both address methods work together
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
    
    $request = CustomerRequest::create('/test', 'POST', $data);
    
    $billing = $request->getBillingAddress();
    $shipping = $request->getShippingAddress();
    
    expect($billing['type'])->toBe(Address::BILLING_TYPE)
        ->and($billing['name'])->toBe('Bill Name')
        ->and($shipping['type'])->toBe(Address::SHIPPING_TYPE)
        ->and($shipping['name'])->toBe('Ship Name');
});

// Test Address type constants are different
test('Address type constants are correctly used and different', function () {
    $request = CustomerRequest::create('/test', 'POST', []);
    
    $billing = $request->getBillingAddress();
    $shipping = $request->getShippingAddress();
    
    expect($billing['type'])->toBe(Address::BILLING_TYPE)
        ->and($shipping['type'])->toBe(Address::SHIPPING_TYPE)
        ->and($billing['type'])->not->toBe($shipping['type']);
});

// Test validation with minimal data
test('validation passes with only required name field', function () {
    $data = ['name' => 'John Doe'];
    
    $request = CustomerRequest::create('/test', 'POST', $data);
    $rules = $request->rules();
    $simpleRules = $rules;
    $simpleRules['email'] = ['email', 'nullable'];
    
    $validator = Validator::make($data, $simpleRules);
    
    expect($validator->passes())->toBeTrue();
});

// Test that request extends FormRequest
test('CustomerRequest extends FormRequest', function () {
    $request = new CustomerRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

// Test method existence
test('CustomerRequest has all required methods', function () {
    $request = new CustomerRequest();
    
    expect(method_exists($request, 'authorize'))->toBeTrue()
        ->and(method_exists($request, 'rules'))->toBeTrue()
        ->and(method_exists($request, 'getCustomerPayload'))->toBeTrue()
        ->and(method_exists($request, 'getShippingAddress'))->toBeTrue()
        ->and(method_exists($request, 'getBillingAddress'))->toBeTrue()
        ->and(method_exists($request, 'hasAddress'))->toBeTrue();
});