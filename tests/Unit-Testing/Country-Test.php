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
    // This allows us to call the actual 'address()' method while also being able
    // to mock and assert calls to internal methods like 'hasMany()'.
    $country = m::mock(Country::class)->makePartial();

    // Expect the 'hasMany' method on the mocked Country instance to be called exactly once.
    // We assert that it is called with 'Address::class' as its argument,
    // which is the target model for the relationship.
    // We return a mock of the HasMany relationship builder to satisfy the call
    // and allow the test to proceed without needing a real database connection or builder instance.
    $country->shouldReceive('hasMany')
            ->once()
            ->with(Address::class)
            ->andReturn(m::mock(HasMany::class));

    // Call the method under test.
    $relation = $country->address();

    // Assert that the returned value is an instance of Illuminate\Database\Eloquent\Relations\HasMany.
    // This confirms that the 'address()' method correctly delegates to the 'hasMany()' method
    // provided by Eloquent and returns its result.
    expect($relation)->toBeInstanceOf(HasMany::class);

    // Mockery's `shouldReceive->once()` will automatically fail the test if the method
    // was not called or called with incorrect arguments, verifying the interaction.
});
