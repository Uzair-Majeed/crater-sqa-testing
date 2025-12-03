```php
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

    // Set related properties to null to reflect absent relations
    $mockCustomer->billingAddress = null;
    $mockCustomer->shippingAddress = null;
    $mockCustomer->fields = null;
    $mockCustomer->company = null;
    $mockCustomer->currency = null;

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

    // Partial mock AddressResource to prevent actual hydration! Only override toArray.
    $addressResourcePartial = Mockery::mock(AddressResource::class, [$mockBillingAddress])->makePartial();
    $addressResourcePartial->shouldReceive('toArray')->andReturn(['id' => 10, 'address_line_1' => '123 Billing St', 'city' => 'Billing City', 'state' => 'BL']);

    // Swap AddressResource globally with partial mock ONLY for this test
    $instanceReplace = function ($class, $callback) {
        $orig = app()->bound($class) ? app($class) : null;
        app()->instance($class, $callback);
        return $orig;
    };
    $orig = $instanceReplace(AddressResource::class, function ($billingAddress) use ($addressResourcePartial) {
        return $addressResourcePartial;
    });

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('billing');
    expect($result['billing'])->toMatchArray(['id' => 10, 'address_line_1' => '123 Billing St', 'city' => 'Billing City', 'state' => 'BL']);
    expect($result)->not->toHaveKey('shipping');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('company');
    expect($result)->not->toHaveKey('currency');

    // Restore original binding if set
    if ($orig) {
        app()->instance(AddressResource::class, $orig);
    } else {
        app()->forgetInstance(AddressResource::class);
    }
});

test('customer resource excludes billing address when it does not exist', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();

    // billingAddress returns an object with exists() as false and billingAddress property is null
    $mockRelationQueryBuilderFalse = Mockery::mock(\stdClass::class);
    $mockRelationQueryBuilderFalse->shouldReceive('exists')->andReturn(false);

    $mockCustomer->shouldReceive('billingAddress')->andReturn($mockRelationQueryBuilderFalse);
    $mockCustomer->billingAddress = null;

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

    // Partial mock AddressResource to prevent actual hydration! Only override toArray.
    $shippingAddressResourcePartial = Mockery::mock(AddressResource::class, [$mockShippingAddress])->makePartial();
    $shippingAddressResourcePartial->shouldReceive('toArray')->andReturn(['id' => 20, 'address_line_1' => '456 Shipping Rd', 'city' => 'Shipping Town', 'state' => 'SH']);

    $instanceReplace = function ($class, $callback) {
        $orig = app()->bound($class) ? app($class) : null;
        app()->instance($class, $callback);
        return $orig;
    };
    $orig = $instanceReplace(AddressResource::class, function ($shippingAddress) use ($shippingAddressResourcePartial) {
        return $shippingAddressResourcePartial;
    });

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('shipping');
    expect($result['shipping'])->toMatchArray(['id' => 20, 'address_line_1' => '456 Shipping Rd', 'city' => 'Shipping Town', 'state' => 'SH']);
    expect($result)->not->toHaveKey('billing');
    expect($result)->not->toHaveKey('fields');

    if ($orig) {
        app()->instance(AddressResource::class, $orig);
    } else {
        app()->forgetInstance(AddressResource::class);
    }
});

test('customer resource excludes shipping address when it does not exist', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();

    $mockRelationQueryBuilderFalse = Mockery::mock(\stdClass::class);
    $mockRelationQueryBuilderFalse->shouldReceive('exists')->andReturn(false);

    $mockCustomer->shouldReceive('shippingAddress')->andReturn($mockRelationQueryBuilderFalse);
    $mockCustomer->shippingAddress = null;

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

    // Partial mock CustomFieldValueResource collection
    $mockedFieldsOutput = [
        ['id' => 1, 'name' => 'Field 1', 'value' => 'Value 1'],
        ['id' => 2, 'name' => 'Field 2', 'value' => 'Value 2'],
    ];

    // Temporarily replace the static method collection
    $original = CustomFieldValueResource::class;
    $mockCollectionStatic = Mockery::mock('alias:' . $original);
    $mockCollectionStatic->shouldReceive('collection')
        ->once()
        ->with($mockFieldsCollection)
        ->andReturn($mockedFieldsOutput);

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('fields');
    expect($result['fields'])->toMatchArray($mockedFieldsOutput);
    expect($result)->not->toHaveKey('billing');
    expect($result)->not->toHaveKey('shipping');
});

test('customer resource excludes custom fields when they do not exist', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();

    $mockRelationQueryBuilderFalse = Mockery::mock(\stdClass::class);
    $mockRelationQueryBuilderFalse->shouldReceive('exists')->andReturn(false);

    $mockCustomer->shouldReceive('fields')->andReturn($mockRelationQueryBuilderFalse);
    $mockCustomer->fields = null;

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

    // Partial mock CompanyResource to override toArray
    $companyResourcePartial = Mockery::mock(CompanyResource::class, [$mockCompany])->makePartial();
    $companyResourcePartial->shouldReceive('toArray')->andReturn(['id' => 30, 'name' => 'Associated Company Inc.', 'status' => 'active']);

    $instanceReplace = function ($class, $callback) {
        $orig = app()->bound($class) ? app($class) : null;
        app()->instance($class, $callback);
        return $orig;
    };
    $orig = $instanceReplace(CompanyResource::class, function ($company) use ($companyResourcePartial) {
        return $companyResourcePartial;
    });

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('company');
    expect($result['company'])->toMatchArray(['id' => 30, 'name' => 'Associated Company Inc.', 'status' => 'active']);
    expect($result)->not->toHaveKey('fields');

    if ($orig) {
        app()->instance(CompanyResource::class, $orig);
    } else {
        app()->forgetInstance(CompanyResource::class);
    }
});

test('customer resource excludes company when it does not exist', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();

    $mockRelationQueryBuilderFalse = Mockery::mock(\stdClass::class);
    $mockRelationQueryBuilderFalse->shouldReceive('exists')->andReturn(false);

    $mockCustomer->shouldReceive('company')->andReturn($mockRelationQueryBuilderFalse);
    $mockCustomer->company = null;

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

    // Partial mock CurrencyResource to override toArray
    $currencyResourcePartial = Mockery::mock(CurrencyResource::class, [$mockCurrency])->makePartial();
    $currencyResourcePartial->shouldReceive('toArray')->andReturn(['id' => 40, 'name' => 'US Dollar', 'code' => 'USD_MOCKED']);

    $instanceReplace = function ($class, $callback) {
        $orig = app()->bound($class) ? app($class) : null;
        app()->instance($class, $callback);
        return $orig;
    };
    $orig = $instanceReplace(CurrencyResource::class, function ($currency) use ($currencyResourcePartial) {
        return $currencyResourcePartial;
    });

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->toHaveKey('currency');
    expect($result['currency'])->toMatchArray(['id' => 40, 'name' => 'US Dollar', 'code' => 'USD_MOCKED']);
    expect($result)->not->toHaveKey('company');

    if ($orig) {
        app()->instance(CurrencyResource::class, $orig);
    } else {
        app()->forgetInstance(CurrencyResource::class);
    }
});

test('customer resource excludes currency when it does not exist', function () {
    $mockCustomer = createMockCustomerWithBaseProperties();

    $mockRelationQueryBuilderFalse = Mockery::mock(\stdClass::class);
    $mockRelationQueryBuilderFalse->shouldReceive('exists')->andReturn(false);

    $mockCustomer->shouldReceive('currency')->andReturn($mockRelationQueryBuilderFalse);
    $mockCustomer->currency = null;

    $resource = new \Crater\Http\Resources\CustomerResource($mockCustomer);
    $result = $resource->toArray(new Request());

    expect($result)->not->toHaveKey('currency');
});

afterEach(function () {
    Mockery::close();
    // Forget possible app instance for resources that may be bound
    app()->forgetInstance(AddressResource::class);
    app()->forgetInstance(CompanyResource::class);
    app()->forgetInstance(CurrencyResource::class);
});
```