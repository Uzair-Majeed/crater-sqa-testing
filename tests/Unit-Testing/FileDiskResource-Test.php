<?php

use Crater\Http\Resources\FileDiskResource;
use Illuminate\Http\Request;
uses(\Mockery::class);

test('toArray returns correct data with all properties populated', function () {
    // Arrange: Create a mock object to represent the underlying model
    $mockModel = (object) [
        'id' => 1,
        'name' => 'Local Disk',
        'type' => 'local',
        'driver' => 'local',
        'set_as_default' => true,
        'credentials' => ['path' => '/app/public'], // Use a static path for unit test isolation
        'company_id' => 101,
    ];

    // Arrange: Create an instance of the resource with the mock model
    $resource = new FileDiskResource($mockModel);

    // Arrange: Create a mock request (not used by this specific toArray implementation, but required by method signature)
    $mockRequest = Mockery::mock(Request::class);

    // Act: Call the toArray method
    $result = $resource->toArray($mockRequest);

    // Assert: Verify that the returned array matches the expected structure and values
    expect($result)->toEqual([
        'id' => 1,
        'name' => 'Local Disk',
        'type' => 'local',
        'driver' => 'local',
        'set_as_default' => true,
        'credentials' => ['path' => '/app/public'],
        'company_id' => 101,
    ]);
});

test('toArray handles null properties gracefully', function () {
    // Arrange: Create a mock model with some properties explicitly set to null
    $mockModel = (object) [
        'id' => 2,
        'name' => null, // Name can be null
        'type' => 's3',
        'driver' => 's3',
        'set_as_default' => false,
        'credentials' => null, // Credentials can be null
        'company_id' => null, // Company ID can be null
    ];

    $resource = new FileDiskResource($mockModel);
    $mockRequest = Mockery::mock(Request::class);

    // Act: Call the toArray method
    $result = $resource->toArray($mockRequest);

    // Assert: Verify that the returned array includes null for the properties that were null on the source object
    expect($result)->toEqual([
        'id' => 2,
        'name' => null,
        'type' => 's3',
        'driver' => 's3',
        'set_as_default' => false,
        'credentials' => null,
        'company_id' => null,
    ]);
});

test('toArray handles empty strings, zero, and empty array values', function () {
    // Arrange: Create a mock model with properties having empty or default scalar values
    $mockModel = (object) [
        'id' => 3,
        'name' => '', // Empty string
        'type' => '',
        'driver' => '',
        'set_as_default' => false, // Boolean false
        'credentials' => [], // Empty array
        'company_id' => 0, // Zero value
    ];

    $resource = new FileDiskResource($mockModel);
    $mockRequest = Mockery::mock(Request::class);

    // Act: Call the toArray method
    $result = $resource->toArray($mockRequest);

    // Assert: Verify that the returned array correctly reflects these empty/default values
    expect($result)->toEqual([
        'id' => 3,
        'name' => '',
        'type' => '',
        'driver' => '',
        'set_as_default' => false,
        'credentials' => [],
        'company_id' => 0,
    ]);
});

test('toArray ensures all expected keys are present even if properties are undefined on source object', function () {
    // Arrange: Create a mock model with only 'id' defined, simulating a partially populated or incomplete object
    $mockModel = (object) [
        'id' => 4,
        // Other properties like name, type, driver, set_as_default, credentials, company_id are intentionally omitted
    ];

    $resource = new FileDiskResource($mockModel);
    $mockRequest = Mockery::mock(Request::class);

    // Act: Call the toArray method
    $result = $resource->toArray($mockRequest);

    // Assert: All expected keys should be present in the output array, with omitted properties resolving to null
    expect($result)->toHaveKeys([
        'id', 'name', 'type', 'driver', 'set_as_default', 'credentials', 'company_id',
    ])->toEqual([
        'id' => 4,
        'name' => null, // Undefined properties will be treated as null by PHP when accessed on an object
        'type' => null,
        'driver' => null,
        'set_as_default' => null,
        'credentials' => null,
        'company_id' => null,
    ]);
});
