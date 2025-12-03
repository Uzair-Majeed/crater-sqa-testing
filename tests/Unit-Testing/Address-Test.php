<?php

use Crater\Models\Address;
use Crater\Models\User;
use Crater\Models\Customer;
use Crater\Models\Company;
use Crater\Models\Country;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

uses(Tests\TestCase::class)->in('Unit-Testing');


test('getCountryNameAttribute returns country name when country relation is loaded', function () {
    $country = new Country();
    $country->name = 'Test Country';

    $address = new Address();
    $address->setRelation('country', $country); // Properly set the loaded relation

    expect($address->getCountryNameAttribute())->toBe('Test Country');
});

test('getCountryNameAttribute returns null when country relation is loaded but country is null', function () {
    $address = new Address();
    $address->setRelation('country', null);

    expect($address->getCountryNameAttribute())->toBeNull();
});

test('getCountryNameAttribute returns null when country relation is not loaded', function () {
    $address = new Address();
    // No relation is set
    expect($address->getCountryNameAttribute())->toBeNull();
});

test('user relationship returns a BelongsTo instance', function () {
    $address = new Address();
    expect($address->user())->toBeInstanceOf(BelongsTo::class);
});

test('customer relationship returns a BelongsTo instance', function () {
    $address = new Address();
    expect($address->customer())->toBeInstanceOf(BelongsTo::class);
});

test('company relationship returns a BelongsTo instance', function () {
    $address = new Address();
    expect($address->company())->toBeInstanceOf(BelongsTo::class);
});

test('country relationship returns a BelongsTo instance', function () {
    $address = new Address();
    expect($address->country())->toBeInstanceOf(BelongsTo::class);
});

test('BILLING_TYPE constant is correctly defined', function () {
    expect(Address::BILLING_TYPE)->toBe('billing');
});

test('SHIPPING_TYPE constant is correctly defined', function () {
    expect(Address::SHIPPING_TYPE)->toBe('shipping');
});

test('guarded property includes id', function () {
    $address = new Address();
    expect($address->getGuarded())->toContain('id');
});



afterEach(function () {
    Mockery::close();
});
