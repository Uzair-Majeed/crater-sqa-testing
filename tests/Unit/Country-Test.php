<?php
use Crater\Models\Address;
use Crater\Models\Country;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mockery as m;

beforeEach(function () {
    // Ensure Mockery expectations are verified and mocks are closed after each test.
    m::close();
});

test('it correctly defines the has many address relationship', function () {
    // Create a partial mock of the Country model.
    $country = m::mock(Country::class)->makePartial();

    // Expect the 'hasMany' method on the mocked Country instance to be called exactly once.
    $country->shouldReceive('hasMany')
            ->once()
            ->with(Address::class)
            ->andReturn(m::mock(HasMany::class));

    // Call the method under test.
    $relation = $country->address();

    // Assert that the returned value is an instance of Illuminate\Database\Eloquent\Relations\HasMany.
    expect($relation)->toBeInstanceOf(HasMany::class);
});

afterEach(function () {
    m::close();
});