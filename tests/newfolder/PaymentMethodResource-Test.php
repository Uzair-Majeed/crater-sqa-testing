```php
<?php

use Crater\Http\Resources\PaymentMethodResource;
use Crater\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mockery\MockInterface; // Import MockInterface for better type hinting

beforeEach(function () {
    // Ensure Mockery is reset before each test to prevent cross-test contamination
    Mockery::close();
});


test('payment method resource transforms correctly when company relationship exists', function () {
    // 1. Mock the underlying PaymentMethod model that the resource wraps
    /** @var MockInterface&\Illuminate\Database\Eloquent\Model $mockPaymentMethod */
    $mockPaymentMethod = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);

    // Instead of direct assignment ($mockPaymentMethod->id = 1;), which triggers setAttribute()
    // and causes a BadMethodCallException on a strict mock, mock the getAttribute() calls.
    // The JsonResource's __get magic method will eventually call getAttribute on the underlying model.
    $mockPaymentMethod->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $mockPaymentMethod->shouldReceive('getAttribute')->with('name')->andReturn('Credit Card');
    $mockPaymentMethod->shouldReceive('getAttribute')->with('company_id')->andReturn(10);
    $mockPaymentMethod->shouldReceive('getAttribute')->with('type')->andReturn('card');

    // 2. Mock the related Company model
    /** @var MockInterface&\Illuminate\Database\Eloquent\Model $mockCompany */
    $mockCompany = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
    // Assign it to the payment method model mock for $this->company access within the resource
    // This also needs to be a mocked getAttribute call if the resource accesses $this->company
    $mockPaymentMethod->shouldReceive('getAttribute')->with('company')->andReturn($mockCompany);


    // 3. Mock the relationship builder returned by the `company()` method
    /** @var MockInterface&BelongsTo $mockRelation */
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
    /** @var MockInterface&\Illuminate\Database\Eloquent\Model $mockPaymentMethod */
    $mockPaymentMethod = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);

    // Mock getAttribute calls
    $mockPaymentMethod->shouldReceive('getAttribute')->with('id')->andReturn(2);
    $mockPaymentMethod->shouldReceive('getAttribute')->with('name')->andReturn('Bank Transfer');
    $mockPaymentMethod->shouldReceive('getAttribute')->with('company_id')->andReturn(null); // No company associated
    $mockPaymentMethod->shouldReceive('getAttribute')->with('type')->andReturn('bank');

    // We do NOT set $mockPaymentMethod->company here, as it's not expected to exist,
    // and thus the resource will not attempt to access $this->company directly.

    // 2. Mock the relationship builder for company()
    /** @var MockInterface&BelongsTo $mockRelation */
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
    /** @var MockInterface&\Illuminate\Database\Eloquent\Model $mockPaymentMethod */
    $mockPaymentMethod = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);

    // Mock getAttribute calls for null values
    $mockPaymentMethod->shouldReceive('getAttribute')->with('id')->andReturn(null); // Test null ID
    $mockPaymentMethod->shouldReceive('getAttribute')->with('name')->andReturn(null);
    $mockPaymentMethod->shouldReceive('getAttribute')->with('company_id')->andReturn(null);
    $mockPaymentMethod->shouldReceive('getAttribute')->with('type')->andReturn(null);

    // 2. Mock the relationship builder for company() (not existing in this case)
    /** @var MockInterface&BelongsTo $mockRelation */
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
    /** @var MockInterface&\Illuminate\Database\Eloquent\Model $mockPaymentMethod */
    $mockPaymentMethod = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);

    // Mock getAttribute calls for empty strings
    $mockPaymentMethod->shouldReceive('getAttribute')->with('id')->andReturn(3);
    $mockPaymentMethod->shouldReceive('getAttribute')->with('name')->andReturn(''); // Test empty string
    $mockPaymentMethod->shouldReceive('getAttribute')->with('company_id')->andReturn(''); // Test empty string
    $mockPaymentMethod->shouldReceive('getAttribute')->with('type')->andReturn(''); // Test empty string

    // 2. Mock the relationship builder for company() (not existing in this case)
    /** @var MockInterface&BelongsTo $mockRelation */
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

    /** @var MockInterface&\Illuminate\Database\Eloquent\Model $mockPaymentMethod */
    $mockPaymentMethod = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);

    // Mock getAttribute calls
    $mockPaymentMethod->shouldReceive('getAttribute')->with('id')->andReturn(4);
    $mockPaymentMethod->shouldReceive('getAttribute')->with('name')->andReturn('Visa');
    $mockPaymentMethod->shouldReceive('getAttribute')->with('company_id')->andReturn(20);
    $mockPaymentMethod->shouldReceive('getAttribute')->with('type')->andReturn('card');

    /** @var MockInterface&\Illuminate\Database\Eloquent\Model $mockCompany */
    $mockCompany = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
    // Mock the 'company' property access for the resource
    $mockPaymentMethod->shouldReceive('getAttribute')->with('company')->andReturn($mockCompany);

    /** @var MockInterface&BelongsTo $mockRelation */
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


afterEach(function () {
    Mockery::close();
});
```