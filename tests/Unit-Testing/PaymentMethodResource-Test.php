<?php

use Crater\Http\Resources\PaymentMethodResource;
use Crater\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
uses(\Mockery::class);
use Illuminate\Database\Eloquent\Relations\BelongsTo;

beforeEach(function () {
    // Ensure Mockery is reset before each test to prevent cross-test contamination
    Mockery::close();
});

afterEach(function () {
    // Ensure all Mockery expectations are met and cleanup after each test
    Mockery::close();
});

test('payment method resource transforms correctly when company relationship exists', function () {
    // 1. Mock the underlying PaymentMethod model that the resource wraps
    $mockPaymentMethod = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
    $mockPaymentMethod->id = 1;
    $mockPaymentMethod->name = 'Credit Card';
    $mockPaymentMethod->company_id = 10;
    $mockPaymentMethod->type = 'card';

    // 2. Mock the related Company model
    $mockCompany = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
    // Assign it to the payment method model directly for $this->company access within the resource
    $mockPaymentMethod->company = $mockCompany;

    // 3. Mock the relationship builder returned by the `company()` method
    $mockRelation = Mockery::mock(BelongsTo::class);
    $mockRelation->shouldReceive('exists')->once()->andReturn(true);

    // 4. Set up the `company()` method on the PaymentMethod model mock to return our mocked relation
    $mockPaymentMethod->shouldReceive('company')->once()->andReturn($mockRelation);

    // 5. Overload (mock) the CompanyResource constructor to ensure it's called with the correct model
    // This allows us to verify its instantiation without relying on its internal logic
    Mockery::mock('overload:' . CompanyResource::class)
        ->shouldReceive('__construct')
        ->with($mockCompany)
        ->once();

    // 6. Instantiate the PaymentMethodResource with our mocked model
    $resource = new PaymentMethodResource($mockPaymentMethod);

    // 7. Call the toArray method
    $result = $resource->toArray(new Request());

    // 8. Assertions
    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['id', 'name', 'company_id', 'type', 'company'])
        ->and($result['id'])->toBe(1)
        ->and($result['name'])->toBe('Credit Card')
        ->and($result['company_id'])->toBe(10)
        ->and($result['type'])->toBe('card');

    // The 'company' value should be an instance of our overloaded CompanyResource mock
    expect($result['company'])->toBeInstanceOf(CompanyResource::class);
});

test('payment method resource transforms correctly when company relationship does not exist', function () {
    // 1. Mock the underlying PaymentMethod model
    $mockPaymentMethod = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
    $mockPaymentMethod->id = 2;
    $mockPaymentMethod->name = 'Bank Transfer';
    $mockPaymentMethod->company_id = null; // No company associated
    $mockPaymentMethod->type = 'bank';

    // We do NOT set $mockPaymentMethod->company here, as it's not expected to exist.

    // 2. Mock the relationship builder for company()
    $mockRelation = Mockery::mock(BelongsTo::class);
    $mockRelation->shouldReceive('exists')->once()->andReturn(false);

    // 3. Set up the company() method on the PaymentMethod model mock
    $mockPaymentMethod->shouldReceive('company')->once()->andReturn($mockRelation);

    // Important: Do NOT overload CompanyResource here, as we expect it *not* to be instantiated.
    // If it were, it would fail the test by calling `__construct` unexpectedly.

    // 4. Instantiate the resource with the mocked model
    $resource = new PaymentMethodResource($mockPaymentMethod);

    // 5. Call the toArray method
    $result = $resource->toArray(new Request());

    // 6. Assertions
    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['id', 'name', 'company_id', 'type'])
        ->and($result)->not->toHaveKey('company') // Assert 'company' key is absent
        ->and($result['id'])->toBe(2)
        ->and($result['name'])->toBe('Bank Transfer')
        ->and($result['company_id'])->toBeNull()
        ->and($result['type'])->toBe('bank');
});

test('payment method resource handles null values for core properties', function () {
    // 1. Mock the underlying PaymentMethod model with null values
    $mockPaymentMethod = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
    $mockPaymentMethod->id = null; // Test null ID
    $mockPaymentMethod->name = null;
    $mockPaymentMethod->company_id = null;
    $mockPaymentMethod->type = null;

    // 2. Mock the relationship builder for company() (not existing in this case)
    $mockRelation = Mockery::mock(BelongsTo::class);
    $mockRelation->shouldReceive('exists')->once()->andReturn(false);
    $mockPaymentMethod->shouldReceive('company')->once()->andReturn($mockRelation);

    // 3. Instantiate the resource
    $resource = new PaymentMethodResource($mockPaymentMethod);

    // 4. Call toArray
    $result = $resource->toArray(new Request());

    // 5. Assertions
    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['id', 'name', 'company_id', 'type'])
        ->and($result)->not->toHaveKey('company')
        ->and($result['id'])->toBeNull()
        ->and($result['name'])->toBeNull()
        ->and($result['company_id'])->toBeNull()
        ->and($result['type'])->toBeNull();
});

test('payment method resource handles empty strings for core properties', function () {
    // 1. Mock the underlying PaymentMethod model with empty string values
    $mockPaymentMethod = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
    $mockPaymentMethod->id = 3;
    $mockPaymentMethod->name = ''; // Test empty string
    $mockPaymentMethod->company_id = ''; // Test empty string
    $mockPaymentMethod->type = ''; // Test empty string

    // 2. Mock the relationship builder for company() (not existing in this case)
    $mockRelation = Mockery::mock(BelongsTo::class);
    $mockRelation->shouldReceive('exists')->once()->andReturn(false);
    $mockPaymentMethod->shouldReceive('company')->once()->andReturn($mockRelation);

    // 3. Instantiate the resource
    $resource = new PaymentMethodResource($mockPaymentMethod);

    // 4. Call toArray
    $result = $resource->toArray(new Request());

    // 5. Assertions
    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['id', 'name', 'company_id', 'type'])
        ->and($result)->not->toHaveKey('company')
        ->and($result['id'])->toBe(3)
        ->and($result['name'])->toBe('')
        ->and($result['company_id'])->toBe('')
        ->and($result['type'])->toBe('');
});

test('payment method resource handles when the request parameter is not an Illuminate Request object', function () {
    // The `toArray` method does not use the `$request` parameter, so it should behave identically.

    $mockPaymentMethod = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
    $mockPaymentMethod->id = 4;
    $mockPaymentMethod->name = 'Visa';
    $mockPaymentMethod->company_id = 20;
    $mockPaymentMethod->type = 'card';

    $mockCompany = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
    $mockPaymentMethod->company = $mockCompany;

    $mockRelation = Mockery::mock(BelongsTo::class);
    $mockRelation->shouldReceive('exists')->once()->andReturn(true);
    $mockPaymentMethod->shouldReceive('company')->once()->andReturn($mockRelation);

    Mockery::mock('overload:' . CompanyResource::class)
        ->shouldReceive('__construct')
        ->with($mockCompany)
        ->once();

    $resource = new PaymentMethodResource($mockPaymentMethod);

    // Pass a generic object instead of an Illuminate\Http\Request
    $result = $resource->toArray(new stdClass());

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['id', 'name', 'company_id', 'type', 'company'])
        ->and($result['id'])->toBe(4)
        ->and($result['name'])->toBe('Visa')
        ->and($result['company'])->toBeInstanceOf(CompanyResource::class);
});
