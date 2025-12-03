<?php

use Crater\Http\Requests\Customer\CustomerProfileRequest;
use Crater\Models\Address;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Mockery\MockInterface;

// Ensure Mockery is closed after each test to prevent mock leakages
beforeEach(function () {
    Mockery::close();
});

test('authorize method returns true', function () {
    $request = new CustomerProfileRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct validation rules including dynamic unique rule', function () {
    // Mock Auth::id() to control the ID for the unique rule's ignore part
    Auth::shouldReceive('id')
        ->once()
        ->andReturn('mock-auth-id');

    // Create a partial mock of CustomerProfileRequest to mock its `header()` method
    /** @var CustomerProfileRequest|MockInterface $request */
    $request = Mockery::mock(CustomerProfileRequest::class . '[header]');
    $request->shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn('mock-company-id');

    // Mock the Rule facade's `unique` method and its chained methods
    // We create a mock instance that will be returned by `Rule::unique`
    $mockRuleUniqueInstance = Mockery::mock();

    // Expect `where` to be called on the unique rule instance
    $mockRuleUniqueInstance->shouldReceive('where')
        ->once()
        ->with('company_id', 'mock-company-id')
        ->andReturnSelf(); // Allow chaining

    // Expect `ignore` to be called on the unique rule instance
    $mockRuleUniqueInstance->shouldReceive('ignore')
        ->once()
        ->with('mock-auth-id', 'id')
        ->andReturnSelf(); // Allow chaining

    // Mock the static Rule facade to return our mock instance when `unique` is called
    Mockery::mock('alias:Illuminate\Validation\Rule')
        ->shouldReceive('unique')
        ->once()
        ->with('customers')
        ->andReturn($mockRuleUniqueInstance);

    // Call the rules method on the request instance
    $rules = $request->rules();

    // Assert the overall structure and content of the rules array
    expect($rules)->toBeArray()
        ->toHaveKeys([
            'name', 'password', 'email',
            'billing.name', 'billing.address_street_1', 'billing.address_street_2', 'billing.city',
            'billing.state', 'billing.country_id', 'billing.zip', 'billing.phone', 'billing.fax',
            'shipping.name', 'shipping.address_street_1', 'shipping.address_street_2', 'shipping.city',
            'shipping.state', 'shipping.country_id', 'shipping.zip', 'shipping.phone', 'shipping.fax',
            'customer_avatar',
        ]);

    // Assert specific rule values
    expect($rules['name'])->toEqual(['nullable']);
    expect($rules['password'])->toEqual(['nullable', 'min:8']);

    // Assert the email rules, especially the mocked Rule::unique part
    expect($rules['email'][0])->toEqual('nullable');
    expect($rules['email'][1])->toEqual('email');
    // Ensure the third element of the email rule array is our mock object
    expect($rules['email'][2])->toBe($mockRuleUniqueInstance);

    // Assert customer_avatar rules
    expect($rules['customer_avatar'])->toEqual(['nullable', 'file', 'mimes:gif,jpg,png', 'max:20000']);
});

test('getShippingAddress returns correct array with shipping data and type', function () {
    $shippingData = [
        'name' => 'Shipping Name',
        'address_street_1' => '123 Shipping St',
        'city' => 'Shipping City',
        'country_id' => 1,
    ];

    // Use a partial mock to mock only the __get magic method
    /** @var CustomerProfileRequest|MockInterface $request */
    $request = Mockery::mock(CustomerProfileRequest::class . '::__get');
    $request->shouldReceive('__get')
        ->with('shipping')
        ->andReturn((object) $shippingData);

    // When calling getShippingAddress, allow the real method to run
    $request->shouldAllowMockingProtectedMethods();
    $request->makePartial();

    $expected = array_merge($shippingData, ['type' => Address::SHIPPING_TYPE]);

    expect($request->getShippingAddress())->toEqual($expected)
        ->toBeArray();
});

test('getShippingAddress returns array with only type when shipping data is empty', function () {
    /** @var CustomerProfileRequest|MockInterface $request */
    $request = Mockery::mock(CustomerProfileRequest::class . '::__get');
    $request->shouldReceive('__get')
        ->with('shipping')
        ->andReturn((object) []);

    $request->shouldAllowMockingProtectedMethods();
    $request->makePartial();

    $expected = ['type' => Address::SHIPPING_TYPE];

    expect($request->getShippingAddress())->toEqual($expected)
        ->toBeArray();
});

test('getShippingAddress returns array with only type when shipping data is null', function () {
    /** @var CustomerProfileRequest|MockInterface $request */
    $request = Mockery::mock(CustomerProfileRequest::class . '::__get');
    $request->shouldReceive('__get')
        ->with('shipping')
        ->andReturn(null);

    $request->shouldAllowMockingProtectedMethods();
    $request->makePartial();

    $expected = ['type' => Address::SHIPPING_TYPE];

    expect($request->getShippingAddress())->toEqual($expected)
        ->toBeArray();
});

test('getBillingAddress returns correct array with billing data and type', function () {
    $billingData = [
        'name' => 'Billing Name',
        'address_street_1' => '456 Billing Ave',
        'city' => 'Billing City',
        'country_id' => 2,
    ];

    /** @var CustomerProfileRequest|MockInterface $request */
    $request = Mockery::mock(CustomerProfileRequest::class . '::__get');
    $request->shouldReceive('__get')
        ->with('billing')
        ->andReturn((object) $billingData);

    $request->shouldAllowMockingProtectedMethods();
    $request->makePartial();

    $expected = array_merge($billingData, ['type' => Address::BILLING_TYPE]);

    expect($request->getBillingAddress())->toEqual($expected)
        ->toBeArray();
});

test('getBillingAddress returns array with only type when billing data is empty', function () {
    /** @var CustomerProfileRequest|MockInterface $request */
    $request = Mockery::mock(CustomerProfileRequest::class . '::__get');
    $request->shouldReceive('__get')
        ->with('billing')
        ->andReturn((object) []);

    $request->shouldAllowMockingProtectedMethods();
    $request->makePartial();

    $expected = ['type' => Address::BILLING_TYPE];

    expect($request->getBillingAddress())->toEqual($expected)
        ->toBeArray();
});

test('getBillingAddress returns array with only type when billing data is null', function () {
    /** @var CustomerProfileRequest|MockInterface $request */
    $request = Mockery::mock(CustomerProfileRequest::class . '::__get');
    $request->shouldReceive('__get')
        ->with('billing')
        ->andReturn(null);

    $request->shouldAllowMockingProtectedMethods();
    $request->makePartial();

    $expected = ['type' => Address::BILLING_TYPE];

    expect($request->getBillingAddress())->toEqual($expected)
        ->toBeArray();
});

afterEach(function () {
    Mockery::close();
});