<?php

use Crater\Models\Address;
use Crater\Models\User;
use Crater\Models\Customer;
use Crater\Models\Company;
use Crater\Models\Country;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

beforeEach(function () {
    \Mockery::close();
});


test('getCountryNameAttribute returns country name when country relation is loaded', function () {
    $country = Mockery::mock(Country::class);
    $country->name = 'Test Country';

    $address = Mockery::mock(Address::class)->makePartial();
    $address->country = $country; // Simulate the relationship being loaded

    expect($address->getCountryNameAttribute())->toBe('Test Country');
});

test('getCountryNameAttribute returns null when country relation is loaded but country is null', function () {
    $address = Mockery::mock(Address::class)->makePartial();
    $address->country = null; // Simulate the relationship being loaded as null

    expect($address->getCountryNameAttribute())->toBeNull();
});

test('getCountryNameAttribute returns null when country relation is not loaded', function () {
    $address = Mockery::mock(Address::class)->makePartial();
    // Do not set $address->country property, simulating it not being loaded or non-existent

    expect($address->getCountryNameAttribute())->toBeNull();
});

test('user relationship returns a BelongsTo instance with correct related model', function () {
    $address = Mockery::mock(Address::class)->makePartial();
    $mockBelongsTo = Mockery::mock(BelongsTo::class); // Mock the return value of belongsTo

    $address->shouldReceive('belongsTo')
        ->with(User::class)
        ->andReturn($mockBelongsTo)
        ->once();

    expect($address->user())->toBe($mockBelongsTo);
});

test('customer relationship returns a BelongsTo instance with correct related model', function () {
    $address = Mockery::mock(Address::class)->makePartial();
    $mockBelongsTo = Mockery::mock(BelongsTo::class);

    $address->shouldReceive('belongsTo')
        ->with(Customer::class)
        ->andReturn($mockBelongsTo)
        ->once();

    expect($address->customer())->toBe($mockBelongsTo);
});

test('company relationship returns a BelongsTo instance with correct related model', function () {
    $address = Mockery::mock(Address::class)->makePartial();
    $mockBelongsTo = Mockery::mock(BelongsTo::class);

    $address->shouldReceive('belongsTo')
        ->with(Company::class)
        ->andReturn($mockBelongsTo)
        ->once();

    expect($address->company())->toBe($mockBelongsTo);
});

test('country relationship returns a BelongsTo instance with correct related model', function () {
    $address = Mockery::mock(Address::class)->makePartial();
    $mockBelongsTo = Mockery::mock(BelongsTo::class);

    $address->shouldReceive('belongsTo')
        ->with(Country::class)
        ->andReturn($mockBelongsTo)
        ->once();

    expect($address->country())->toBe($mockBelongsTo);
});

test('BILLING_TYPE constant is correctly defined', function () {
    expect(Address::BILLING_TYPE)->toBe('billing');
});

test('SHIPPING_TYPE constant is correctly defined', function () {
    expect(Address::SHIPPING_TYPE)->toBe('shipping');
});

test('guarded property includes id', function () {
    $address = new Address(); // Instantiate a real model to check protected property via public method
    expect($address->getGuarded())->toContain('id');
});
