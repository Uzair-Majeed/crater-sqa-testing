<?php
use Crater\Models\Country;
use Crater\Http\Resources\CountryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

beforeEach(function () {
    // Clear Mockery expectations before each test to prevent conflicts
    Mockery::close();
});

test('it returns a collection of countries when countries exist', function () {
    // Arrange
    $mockCountry1 = (object)['id' => 1, 'name' => 'USA', 'code' => 'US'];
    $mockCountry2 = (object)['id' => 2, 'name' => 'Canada', 'code' => 'CA'];
    $countriesCollection = collect([$mockCountry1, $mockCountry2]);

    // Mock the static `Country::all()` method
    // Use an alias mock for static methods like `Country::all()`
    Mockery::mock('alias:' . Country::class)
        ->shouldReceive('all')
        ->once()
        ->andReturn($countriesCollection);

    // Mock the static `CountryResource::collection()` method
    // This is to verify that the method is called with the correct collection
    // and to control its return value for isolation.
    $mockResourceCollection = Mockery::mock(AnonymousResourceCollection::class);
    Mockery::mock('alias:' . CountryResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($arg) use ($countriesCollection) {
            // Ensure the collection passed to resource is the same as retrieved
            return $arg->toArray() === $countriesCollection->toArray();
        })
        ->andReturn($mockResourceCollection);

    $controller = new \Crater\Http\Controllers\V1\Admin\General\CountriesController();
    $request = Request::create('/');

    // Act
    $result = $controller($request);

    // Assert
    expect($result)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($result)->toBe($mockResourceCollection); // Ensure we received the mocked resource collection
});

test('it returns an empty collection when no countries exist', function () {
    // Arrange
    $emptyCountriesCollection = collect([]);

    // Mock the static `Country::all()` method to return an empty collection
    Mockery::mock('alias:' . Country::class)
        ->shouldReceive('all')
        ->once()
        ->andReturn($emptyCountriesCollection);

    // Mock the static `CountryResource::collection()` method
    // Verify it's called with an empty collection and control its return value.
    $mockEmptyResourceCollection = Mockery::mock(AnonymousResourceCollection::class);
    Mockery::mock('alias:' . CountryResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($arg) use ($emptyCountriesCollection) {
            // Ensure the collection passed to resource is empty and matches
            return $arg->isEmpty() && $arg->toArray() === $emptyCountriesCollection->toArray();
        })
        ->andReturn($mockEmptyResourceCollection);

    $controller = new \Crater\Http\Controllers\V1\Admin\General\CountriesController();
    $request = Request::create('/');

    // Act
    $result = $controller($request);

    // Assert
    expect($result)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($result)->toBe($mockEmptyResourceCollection); // Ensure we received the mocked resource collection
});



afterEach(function () {
    Mockery::close();
});
