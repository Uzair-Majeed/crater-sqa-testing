
<?php

use Mockery as m;
use Crater\Http\Resources\AddressResource;
use Crater\Http\Resources\CountryResource;
use Crater\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Helper to create a basic mock address model for testing.
// This mock simulates an Eloquent model instance with dynamic properties and relationship methods.
function createMockAddressModel($data = [], $relations = [])
{
    $model = m::mock('stdClass'); // Using stdClass as a base for a generic model mock
    // Assign direct properties from provided data or use defaults
    $model->id = $data['id'] ?? 1;
    $model->name = $data['name'] ?? 'Main Address';
    $model->address_street_1 = $data['address_street_1'] ?? '123 Test St';
    $model->address_street_2 = $data['address_street_2'] ?? 'Apt 101';
    $model->city = $data['city'] ?? 'Test City';
    $model->state = $data['state'] ?? 'TS';
    $model->country_id = $data['country_id'] ?? 10;
    $model->zip = $data['zip'] ?? '12345';
    $model->phone = $data['phone'] ?? '123-456-7890';
    $model->fax = $data['fax'] ?? '098-765-4321';
    $model->type = $data['type'] ?? 'billing';
    $model->user_id = $data['user_id'] ?? 20;
    $model->company_id = $data['company_id'] ?? 30;
    $model->customer_id = $data['customer_id'] ?? 40;

    // Mock Eloquent relationship methods `country()` and `user()`
    $mockCountryRelation = m::mock(BelongsTo::class);
    $mockUserRelation = m::mock(BelongsTo::class);

    // Default behavior: relationships do not exist
    $mockCountryRelation->shouldReceive('exists')->andReturn(false);
    $mockUserRelation->shouldReceive('exists')->andReturn(false);

    // Override relationship existence and related model data if specified
    if (isset($relations['country_exists']) && $relations['country_exists']) {
        $mockCountryRelation->shouldReceive('exists')->andReturn(true);
        // Ensure the `$model->country` property is set for the resource to use
        $model->country = (object)['id' => $data['country_id'] ?? 10, 'name' => 'Mock Country Data'];
    }

    if (isset($relations['user_exists']) && $relations['user_exists']) {
        $mockUserRelation->shouldReceive('exists')->andReturn(true);
        // Ensure the `$model->user` property is set for the resource to use
        $model->user = (object)['id' => $data['user_id'] ?? 20, 'name' => 'Mock User Data'];
    }

    // Configure the model to return the mocked relation objects
    $model->shouldReceive('country')->andReturn($mockCountryRelation);
    $model->shouldReceive('user')->andReturn($mockUserRelation);

    return $model;
}

// Test suite for the AddressResource class


    // Test case: Verifies that all direct properties are mapped correctly from the model.
    // This test ensures that the basic data transformation occurs as expected.
    test('it maps all direct properties correctly', function () {
        $mockModel = createMockAddressModel([
            'id' => 1,
            'name' => 'Home Address',
            'address_street_1' => '10 Downing St',
            'address_street_2' => '',
            'city' => 'London',
            'state' => 'ENG',
            'country_id' => 1,
            'zip' => 'SW1A 2AA',
            'phone' => '02079250918',
            'fax' => null,
            'type' => 'shipping',
            'user_id' => 5,
            'company_id' => 10,
            'customer_id' => 15,
        ]);

        // Assert that nested resources are NOT constructed when relationships don't exist
        m::mock('overload:\Crater\Http\Resources\CountryResource')->shouldNotReceive('__construct');
        m::mock('overload:\Crater\Http\Resources\UserResource')->shouldNotReceive('__construct');

        $resource = new AddressResource($mockModel);
        $result = $resource->toArray(new Request());

        expect($result)->toMatchArray([
            'id' => 1,
            'name' => 'Home Address',
            'address_street_1' => '10 Downing St',
            'address_street_2' => '',
            'city' => 'London',
            'state' => 'ENG',
            'country_id' => 1,
            'zip' => 'SW1A 2AA',
            'phone' => '02079250918',
            'fax' => null,
            'type' => 'shipping',
            'user_id' => 5,
            'company_id' => 10,
            'customer_id' => 15,
        ]);

        // Explicitly assert that conditional relations are not present
        expect($result)->not->toHaveKey('country');
        expect($result)->not->toHaveKey('user');
    });

    // Test case: Checks if the country resource is included when the country relationship exists.
    // This covers one of the conditional `when` blocks in `toArray`.
    test('it includes country resource when country relationship exists', function () {
        $mockModel = createMockAddressModel(
            ['country_id' => 100],
            ['country_exists' => true]
        );
        $mockModel->country = (object)['id' => 100, 'name' => 'United States']; // Mock data for the country model itself

        // Mock the `CountryResource` class to intercept its instantiation and `toArray` call
        $mockCountryResourceInstance = m::mock(CountryResource::class);
        $mockCountryResourceInstance->shouldReceive('toArray')
                                   ->once()
                                   ->andReturn(['id' => 100, 'name' => 'Mocked Country Resource Data']);

        m::mock('overload:' . CountryResource::class)
            ->shouldReceive('__construct')
            ->once()
            ->with(m::on(function ($arg) use ($mockModel) {
                // Assert that the correct country model is passed to the resource constructor
                return $arg === $mockModel->country;
            }))
            ->andReturn($mockCountryResourceInstance); // Return our mock instance

        // Assert that the `UserResource` is NOT constructed
        m::mock('overload:' . UserResource::class)->shouldNotReceive('__construct');

        $resource = new AddressResource($mockModel);
        $result = $resource->toArray(new Request());

        expect($result)->toHaveKey('country');
        expect($result['country'])->toEqual(['id' => 100, 'name' => 'Mocked Country Resource Data']);
        expect($result)->not->toHaveKey('user');
    });

    // Test case: Checks if the user resource is included when the user relationship exists.
    // This covers the other conditional `when` block in `toArray`.
    test('it includes user resource when user relationship exists', function () {
        $mockModel = createMockAddressModel(
            ['user_id' => 200],
            ['user_exists' => true]
        );
        $mockModel->user = (object)['id' => 200, 'name' => 'John Doe']; // Mock data for the user model itself

        // Mock the `UserResource` class
        $mockUserResourceInstance = m::mock(UserResource::class);
        $mockUserResourceInstance->shouldReceive('toArray')
                                 ->once()
                                 ->andReturn(['id' => 200, 'name' => 'Mocked User Resource Data']);

        m::mock('overload:' . UserResource::class)
            ->shouldReceive('__construct')
            ->once()
            ->with(m::on(function ($arg) use ($mockModel) {
                // Assert that the correct user model is passed to the resource constructor
                return $arg === $mockModel->user;
            }))
            ->andReturn($mockUserResourceInstance);

        // Assert that the `CountryResource` is NOT constructed
        m::mock('overload:' . CountryResource::class)->shouldNotReceive('__construct');

        $resource = new AddressResource($mockModel);
        $result = $resource->toArray(new Request());

        expect($result)->toHaveKey('user');
        expect($result['user'])->toEqual(['id' => 200, 'name' => 'Mocked User Resource Data']);
        expect($result)->not->toHaveKey('country');
    });

    // Test case: Verifies both country and user resources are included when both relationships exist.
    // This covers the scenario where both `when` conditions are true.
    test('it includes both country and user resources when both relationships exist', function () {
        $mockModel = createMockAddressModel(
            ['country_id' => 100, 'user_id' => 200],
            ['country_exists' => true, 'user_exists' => true]
        );
        $mockModel->country = (object)['id' => 100, 'name' => 'United States'];
        $mockModel->user = (object)['id' => 200, 'name' => 'John Doe'];

        // Mock CountryResource
        $mockCountryResourceInstance = m::mock(CountryResource::class);
        $mockCountryResourceInstance->shouldReceive('toArray')
                                   ->once()
                                   ->andReturn(['id' => 100, 'name' => 'Mocked Country Resource Data']);

        m::mock('overload:' . CountryResource::class)
            ->shouldReceive('__construct')
            ->once()
            ->with(m::on(function ($arg) use ($mockModel) {
                return $arg === $mockModel->country;
            }))
            ->andReturn($mockCountryResourceInstance);

        // Mock UserResource
        $mockUserResourceInstance = m::mock(UserResource::class);
        $mockUserResourceInstance->shouldReceive('toArray')
                                 ->once()
                                 ->andReturn(['id' => 200, 'name' => 'Mocked User Resource Data']);

        m::mock('overload:' . UserResource::class)
            ->shouldReceive('__construct')
            ->once()
            ->with(m::on(function ($arg) use ($mockModel) {
                return $arg === $mockModel->user;
            }))
            ->andReturn($mockUserResourceInstance);

        $resource = new AddressResource($mockModel);
        $result = $resource->toArray(new Request());

        expect($result)->toHaveKey('country');
        expect($result['country'])->toEqual(['id' => 100, 'name' => 'Mocked Country Resource Data']);
        expect($result)->toHaveKey('user');
        expect($result['user'])->toEqual(['id' => 200, 'name' => 'Mocked User Resource Data']);
    });

    // Test case: Ensures neither country nor user resources are included when relationships do not exist.
    // This covers the scenario where both `when` conditions are false.
    test('it does not include country or user resources when relationships do not exist', function () {
        $mockModel = createMockAddressModel(
            ['country_id' => null, 'user_id' => null],
            ['country_exists' => false, 'user_exists' => false] // Explicitly state no existence
        );

        // Assert that neither resource's constructor is called
        m::mock('overload:' . CountryResource::class)->shouldNotReceive('__construct');
        m::mock('overload:' . UserResource::class)->shouldNotReceive('__construct');

        $resource = new AddressResource($mockModel);
        $result = $resource->toArray(new Request());

        expect($result)->not->toHaveKey('country');
        expect($result)->not->toHaveKey('user');
    });

    // Test case: Handles properties that might be null in the underlying model.
    // This checks robustness against missing data.
    test('it handles null values for nullable properties gracefully', function () {
        $mockModel = createMockAddressModel([
            'id' => 1,
            'name' => null,
            'address_street_1' => 'Street 1',
            'address_street_2' => null,
            'city' => 'City',
            'state' => null,
            'country_id' => null,
            'zip' => null,
            'phone' => null,
            'fax' => null,
            'type' => 'billing',
            'user_id' => null,
            'company_id' => null,
            'customer_id' => null,
        ]);

        // No relations means no nested resources
        m::mock('overload:' . CountryResource::class)->shouldNotReceive('__construct');
        m::mock('overload:' . UserResource::class)->shouldNotReceive('__construct');

        $resource = new AddressResource($mockModel);
        $result = $resource->toArray(new Request());

        expect($result)->toMatchArray([
            'id' => 1,
            'name' => null,
            'address_street_1' => 'Street 1',
            'address_street_2' => null,
            'city' => 'City',
            'state' => null,
            'country_id' => null,
            'zip' => null,
            'phone' => null,
            'fax' => null,
            'type' => 'billing',
            'user_id' => null,
            'company_id' => null,
            'customer_id' => null,
        ]);
        expect($result)->not->toHaveKey('country');
        expect($result)->not->toHaveKey('user');
    });

    // Test case: Checks behavior with a minimal, almost empty model instance (mostly nulls).
    // This is an edge case to ensure no unexpected errors or data are returned.
    test('it handles an empty model with minimal data', function () {
        $mockModel = createMockAddressModel(); // Uses defaults
        // Override most defaults to be null to simulate an empty model
        $mockModel->id = null;
        $mockModel->name = null;
        $mockModel->address_street_1 = null;
        $mockModel->address_street_2 = null;
        $mockModel->city = null;
        $mockModel->state = null;
        $mockModel->country_id = null;
        $mockModel->zip = null;
        $mockModel->phone = null;
        $mockModel->fax = null;
        $mockModel->type = null;
        $mockModel->user_id = null;
        $mockModel->company_id = null;
        $mockModel->customer_id = null;

        // No relations means no nested resources
        m::mock('overload:' . CountryResource::class)->shouldNotReceive('__construct');
        m::mock('overload:' . UserResource::class)->shouldNotReceive('__construct');

        $resource = new AddressResource($mockModel);
        $result = $resource->toArray(new Request());

        expect($result)->toMatchArray([
            'id' => null,
            'name' => null,
            'address_street_1' => null,
            'address_street_2' => null,
            'city' => null,
            'state' => null,
            'country_id' => null,
            'zip' => null,
            'phone' => null,
            'fax' => null,
            'type' => null,
            'user_id' => null,
            'company_id' => null,
            'customer_id' => null,
        ]);
        expect($result)->not->toHaveKey('country');
        expect($result)->not->toHaveKey('user');
    });




afterEach(function () {
    Mockery::close();
});
