<?php

use Crater\Http\Resources\CountryResource;
use Illuminate\Http\Request;
use Mockery as m;

// Test case 1: Successful transformation with all data present
test('it transforms a country model into an array with all expected properties', function () {
    $countryData = (object) [
        'id' => 101,
        'code' => 'CA',
        'name' => 'Canada',
        'phone_code' => '1',
    ];

    $resource = new CountryResource($countryData);
    $request = m::mock(Request::class); // The request object is not used in toArray, so a simple mock is fine

    $expectedArray = [
        'id' => 101,
        'code' => 'CA',
        'name' => 'Canada',
        'phone_code' => '1',
    ];

    expect($resource->toArray($request))->toEqual($expectedArray);
});

// Test case 2: Transformation with some properties having null values
test('it correctly transforms a country model where some properties are null', function () {
    $countryData = (object) [
        'id' => 102,
        'code' => null, // Test null code
        'name' => 'Country with Nulls',
        'phone_code' => null, // Test null phone code
    ];

    $resource = new CountryResource($countryData);
    $request = m::mock(Request::class);

    $expectedArray = [
        'id' => 102,
        'code' => null,
        'name' => 'Country with Nulls',
        'phone_code' => null,
    ];

    expect($resource->toArray($request))->toEqual($expectedArray);
});

// Test case 3: Transformation with missing properties in the underlying model
// Due to JsonResource's magic __get, accessing a non-existent property on the underlying resource will return null.
test('it returns null for properties that are missing in the underlying model', function () {
    $countryData = (object) [
        'id' => 103,
        'name' => 'Country Missing Data',
        // 'code' and 'phone_code' are intentionally omitted
    ];

    $resource = new CountryResource($countryData);
    $request = m::mock(Request::class);

    $expectedArray = [
        'id' => 103,
        'code' => null, // Expected to be null as 'code' is missing
        'name' => 'Country Missing Data',
        'phone_code' => null, // Expected to be null as 'phone_code' is missing
    ];

    expect($resource->toArray($request))->toEqual($expectedArray);
});

// Test case 4: Transformation with empty string values for properties
test('it correctly transforms a country model with empty string values', function () {
    $countryData = (object) [
        'id' => 104,
        'code' => '',
        'name' => '',
        'phone_code' => '',
    ];

    $resource = new CountryResource($countryData);
    $request = m::mock(Request::class);

    $expectedArray = [
        'id' => 104,
        'code' => '',
        'name' => '',
        'phone_code' => '',
    ];

    expect($resource->toArray($request))->toEqual($expectedArray);
});

// Test case 5: Transformation with different data types (e.g., ID as string, phone_code as int)
test('it passes through various valid data types for properties directly', function () {
    $countryData = (object) [
        'id' => 'custom-id-string', // ID as string
        'code' => 'XX',
        'name' => 'Xanadu Republic',
        'phone_code' => 999, // Phone code as integer
    ];

    $resource = new CountryResource($countryData);
    $request = m::mock(Request::class);

    $expectedArray = [
        'id' => 'custom-id-string',
        'code' => 'XX',
        'name' => 'Xanadu Republic',
        'phone_code' => 999,
    ];

    expect($resource->toArray($request))->toEqual($expectedArray);
});

// Test case 6: The underlying resource is an array instead of an object
test('it transforms an array resource into an array successfully', function () {
    $countryData = [ // Using an array instead of an object
        'id' => 200,
        'code' => 'DE',
        'name' => 'Germany',
        'phone_code' => '49',
    ];

    $resource = new CountryResource($countryData);
    $request = m::mock(Request::class);

    $expectedArray = [
        'id' => 200,
        'code' => 'DE',
        'name' => 'Germany',
        'phone_code' => '49',
    ];

    expect($resource->toArray($request))->toEqual($expectedArray);
});

// Test case 7: The underlying resource passed to the constructor is null
// Accessing properties on a null underlying resource via JsonResource's magic methods
// leads to a TypeError. This tests the expected runtime behavior for this edge case.
test('it throws TypeError when underlying resource is null and properties are accessed', function () {
    $resource = new CountryResource(null); // Pass null as the underlying resource
    $request = m::mock(Request::class);

    // Expecting a TypeError because $this->id (which resolves to $this->resource->id)
    // will attempt to access a property on a null value.
    $this->expectException(TypeError::class);
    $this->expectExceptionMessageMatches('/Attempt to read property \w+ on null/');

    $resource->toArray($request);
});

 

afterEach(function () {
    Mockery::close();
});
