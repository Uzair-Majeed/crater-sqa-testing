<?php

use Carbon\Carbon;
use Crater\Http\Resources\AddressResource;
use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\CurrencyResource;
use Crater\Http\Resources\CustomFieldValueResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

uses(MockeryPHPUnitIntegration::class);

/**
 * Helper function to create a mock customer with all required properties to avoid undefined property errors.
 * Relation methods are initially set to return non-existent relations.
 */
function createMockCustomerWithBaseProperties(): Mockery\MockInterface
{
    $now = Carbon::now();
    $mockCustomer = Mockery::mock(new \stdClass());
    $mockCustomer->id = 1;
    $mockCustomer->name = 'Test Customer';
    $mockCustomer->email = 'test@example.com';
    $mockCustomer->phone = '1234567890';
    $mockCustomer->contact_name = 'John Doe';
    $mockCustomer->company_name = 'Test Co.';
    $mockCustomer->website = 'https://test.com';
    $mockCustomer->enable_portal = true;
    $mockCustomer->password = 'hashed_password'; // Default to existing password
    $mockCustomer->currency_id = 100;
    $mockCustomer->company_id = 200;
    $mockCustomer->facebook_id = 'fb123';
    $mockCustomer->google_id = 'ggl456';
    $mockCustomer->github_id = 'gh789';
    $mockCustomer->created_at = $now->copy()->subDays(5);
    $mockCustomer->formattedCreatedAt = $now->copy()->subDays(5)->toDateString();
    $mockCustomer->updated_at = $now;
    $mockCustomer->avatar = 'avatar.png';
    $mockCustomer->due_amount = 100.50;
    $mockCustomer->base_due_amount = 90.25;
    $mockCustomer->prefix = 'CUST';

    // Mock relations to not exist by default
    $mockRelationQueryBuilderFalse = Mockery::mock(\stdClass::class);
    $mockRelationQueryBuilderFalse->shouldReceive('exists')->andReturn(false);

    $mockCustomer->shouldReceive('billingAddress')->andReturn($mockRelationQueryBuilderFalse);
    $mockCustomer->shouldReceive('shippingAddress')->andReturn($mockRelationQueryBuilderFalse);
    $mockCustomer->shouldReceive('fields')->andReturn($mockRelationQueryBuilderFalse);
    $mockCustomer->shouldReceive('company')->andReturn($mockRelationQueryBuilderFalse);
    $mockCustomer->shouldReceive('currency')->andReturn($mockRelationQueryBuilderFalse);

    return $mockCustomer;
}

test('customer resource transforms all basic properties correctly when all relations are absent', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->toMatchArray([
        'id' => 1,
        'name' => 'Test Customer',
        'email' => 'test@example.com',
        'phone' => '1234567890',
        'contact_name' => 'John Doe',
        'company_name' => 'Test Co.',
        'website' => 'https://test.com',
        'enable_portal' => true,
        'password_added' => true, // Password exists
        'currency_id' => 100,
        'company_id' => 200,
        'facebook_id' => 'fb123',
        'google_id' => 'ggl456',
        'github_id' => 'gh789',
        'created_at' => $mockCustomer->created_at,
        'formatted_created_at' => $mockCustomer->formattedCreatedAt,
        'updated_at' => $mockCustomer->updated_at,
        'avatar' => 'avatar.png',
        'due_amount' => 100.50,
        'base_due_amount' => 90.25,
        'prefix' => 'CUST',
    ]);

    expect($result)->not->toHaveKey('billing');
    expect($result)->not->toHaveKey('shipping');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('company');
    expect($result)->not->toHaveKey('currency');
});

test('customer resource transforms password_added correctly when password is null', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();
    $mockCustomer->password = null; // Password is null

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result['password_added'])->toBeFalse();
});

test('customer resource transforms password_added correctly when password is an empty string', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();
    $mockCustomer->password = ''; // Password is an empty string

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result['password_added'])->toBeFalse();
});

test('customer resource includes billing address when it exists', function () {
    $mockBillingAddress = (object)['id' => 10, 'address_line_1' => '123 Billing St', 'city' => 'Billing City', 'state' => 'BL'];

    $mockCustomer = createMockCustomerWithBaseProperties();

    $mockRelationQueryBuilderTrue = Mockery::mock(\stdClass::class);
    $mockRelationQueryBuilderTrue->shouldReceive('exists')->andReturn(true);

    // Setup billingAddress relation to exist
    $mockCustomer->shouldReceive('billingAddress')->andReturn($mockRelationQueryBuilderTrue);
    $mockCustomer->billingAddress = $mockBillingAddress;

    // Mock the AddressResource constructor
    $mockAddressResource = Mockery::mock(AddressResource::class);
    $mockAddressResource->shouldReceive('toArray')->once()->andReturn(['id' => 10, 'address_line_1' => '123 Billing St', 'city' => 'Billing City', 'state' => 'BL']);
    Mockery::mock('overload:' . AddressResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($mockBillingAddress)
        ->andReturn($mockAddressResource);

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('billing');
    expect($result['billing'])->toMatchArray(['id' => 10, 'address_line_1' => '123 Billing St', 'city' => 'Billing City', 'state' => 'BL']);
    expect($result)->not->toHaveKey('shipping');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('company');
    expect($result)->not->toHaveKey('currency');
});

test('customer resource excludes billing address when it does not exist', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();

    // Ensure AddressResource is NOT instantiated
    Mockery::mock('overload:' . AddressResource::class)
        ->shouldNotReceive('__construct');

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->not->toHaveKey('billing');
});

test('customer resource includes shipping address when it exists', function () {
    $mockShippingAddress = (object)['id' => 20, 'address_line_1' => '456 Shipping Rd', 'city' => 'Shipping Town', 'state' => 'SH'];

    $mockCustomer = createMockCustomerWithBaseProperties();

    $mockRelationQueryBuilderTrue = Mockery::mock(\stdClass::class);
    $mockRelationQueryBuilderTrue->shouldReceive('exists')->andReturn(true);

    // Setup shippingAddress relation to exist
    $mockCustomer->shouldReceive('shippingAddress')->andReturn($mockRelationQueryBuilderTrue);
    $mockCustomer->shippingAddress = $mockShippingAddress;

    // Mock the AddressResource constructor
    $mockAddressResource = Mockery::mock(AddressResource::class);
    $mockAddressResource->shouldReceive('toArray')->once()->andReturn(['id' => 20, 'address_line_1' => '456 Shipping Rd', 'city' => 'Shipping Town', 'state' => 'SH']);
    Mockery::mock('overload:' . AddressResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($mockShippingAddress)
        ->andReturn($mockAddressResource);

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('shipping');
    expect($result['shipping'])->toMatchArray(['id' => 20, 'address_line_1' => '456 Shipping Rd', 'city' => 'Shipping Town', 'state' => 'SH']);
    expect($result)->not->toHaveKey('billing');
    expect($result)->not->toHaveKey('fields');
});

test('customer resource excludes shipping address when it does not exist', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();

    Mockery::mock('overload:' . AddressResource::class)
        ->shouldNotReceive('__construct');

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->not->toHaveKey('shipping');
});

test('customer resource includes custom fields when they exist', function () {
    $mockCustomField1 = (object)['id' => 1, 'name' => 'Field 1', 'value' => 'Value 1'];
    $mockCustomField2 = (object)['id' => 2, 'name' => 'Field 2', 'value' => 'Value 2'];
    $mockFieldsCollection = Collection::make([$mockCustomField1, $mockCustomField2]);

    $mockCustomer = createMockCustomerWithBaseProperties();

    $mockRelationQueryBuilderTrue = Mockery::mock(\stdClass::class);
    $mockRelationQueryBuilderTrue->shouldReceive('exists')->andReturn(true);

    // Setup fields relation to exist
    $mockCustomer->shouldReceive('fields')->andReturn($mockRelationQueryBuilderTrue);
    $mockCustomer->fields = $mockFieldsCollection;

    // Mock CustomFieldValueResource::collection static method
    Mockery::mock('alias:' . CustomFieldValueResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($arg) use ($mockFieldsCollection) {
            return $arg === $mockFieldsCollection;
        })
        ->andReturn('mocked_fields_collection_output');

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('fields');
    expect($result['fields'])->toBe('mocked_fields_collection_output');
    expect($result)->not->toHaveKey('billing');
    expect($result)->not->toHaveKey('shipping');
});

test('customer resource excludes custom fields when they do not exist', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();

    // Ensure CustomFieldValueResource::collection is NOT called
    Mockery::mock('alias:' . CustomFieldValueResource::class)
        ->shouldNotReceive('collection');

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->not->toHaveKey('fields');
});

test('customer resource includes company when it exists', function () {
    $mockCompany = (object)['id' => 30, 'name' => 'Associated Company Inc.'];

    $mockCustomer = createMockCustomerWithBaseProperties();

    $mockRelationQueryBuilderTrue = Mockery::mock(\stdClass::class);
    $mockRelationQueryBuilderTrue->shouldReceive('exists')->andReturn(true);

    // Setup company relation to exist
    $mockCustomer->shouldReceive('company')->andReturn($mockRelationQueryBuilderTrue);
    $mockCustomer->company = $mockCompany;

    // Mock the CompanyResource constructor
    $mockCompanyResource = Mockery::mock(CompanyResource::class);
    $mockCompanyResource->shouldReceive('toArray')->once()->andReturn(['id' => 30, 'name' => 'Associated Company Inc.', 'status' => 'active']);
    Mockery::mock('overload:' . CompanyResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($mockCompany)
        ->andReturn($mockCompanyResource);

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('company');
    expect($result['company'])->toMatchArray(['id' => 30, 'name' => 'Associated Company Inc.', 'status' => 'active']);
    expect($result)->not->toHaveKey('fields');
});

test('customer resource excludes company when it does not exist', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();

    Mockery::mock('overload:' . CompanyResource::class)
        ->shouldNotReceive('__construct');

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->not->toHaveKey('company');
});

test('customer resource includes currency when it exists', function () {
    $mockCurrency = (object)['id' => 40, 'name' => 'US Dollar', 'code' => 'USD'];

    $mockCustomer = createMockCustomerWithBaseProperties();

    $mockRelationQueryBuilderTrue = Mockery::mock(\stdClass::class);
    $mockRelationQueryBuilderTrue->shouldReceive('exists')->andReturn(true);

    // Setup currency relation to exist
    $mockCustomer->shouldReceive('currency')->andReturn($mockRelationQueryBuilderTrue);
    $mockCustomer->currency = $mockCurrency;

    // Mock the CurrencyResource constructor
    $mockCurrencyResource = Mockery::mock(CurrencyResource::class);
    $mockCurrencyResource->shouldReceive('toArray')->once()->andReturn(['id' => 40, 'name' => 'US Dollar', 'code' => 'USD_MOCKED']);
    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($mockCurrency)
        ->andReturn($mockCurrencyResource);

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('currency');
    expect($result['currency'])->toMatchArray(['id' => 40, 'name' => 'US Dollar', 'code' => 'USD_MOCKED']);
    expect($result)->not->toHaveKey('company');
});

test('customer resource excludes currency when it does not exist', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();

    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldNotReceive('__construct');

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->not->toHaveKey('currency');
});

 

afterEach(function () {
    Mockery::close();
});
