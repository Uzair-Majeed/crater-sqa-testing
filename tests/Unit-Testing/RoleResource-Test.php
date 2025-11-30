<?php

use Carbon\Carbon;
use Crater\Http\Resources\RoleResource;
use Crater\Models\CompanySetting;
use Illuminate\Http\Request;
uses(\Mockery::class);

// Ensure Mockery is closed after each test to prevent mock expectation leaks
beforeEach(function () {
    Mockery::close();
});

test('toArray transforms the role resource into an array with expected keys and values', function () {
    // Create a mock for the underlying model instance that the resource wraps
    $mockRole = (object) [
        'id' => 1,
        'name' => 'admin',
        'title' => 'Administrator',
        'level' => 1,
        // created_at and scope are not directly accessed by toArray, but by getFormattedAt.
        // Their values here are less critical for *this* test's direct assertions,
        // as getFormattedAt is mocked, but good practice to include for context.
        'created_at' => Carbon::now()->subDays(5),
        'scope' => 'test_scope'
    ];

    // Instantiate the resource with the mock model
    $resource = new RoleResource($mockRole);

    // Partially mock the *instance* of RoleResource to control its internal method calls
    // Specifically, mock getFormattedAt and getAbilities which are called by toArray.
    $resource = Mockery::mock($resource)->makePartial();

    $resource->shouldReceive('getFormattedAt')
             ->once()
             ->andReturn('2023-10-26'); // Mocked formatted date

    // getAbilities is called by toArray but not defined in RoleResource.
    // It's likely on the underlying model or parent class, so we mock its return for isolation.
    $resource->shouldReceive('getAbilities')
             ->once()
             ->andReturn(['manage-users', 'view-reports']); // Mocked abilities

    // Create a mock request, though it's not directly used in toArray's logic itself
    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    // Assert the structure and values of the returned array
    expect($result)->toBeArray()
        ->toHaveKeys([
            'id',
            'name',
            'title',
            'level',
            'formatted_created_at',
            'abilities',
        ])
        ->and($result['id'])->toBe($mockRole->id)
        ->and($result['name'])->toBe($mockRole->name)
        ->and($result['title'])->toBe($mockRole->title)
        ->and($result['level'])->toBe($mockRole->level)
        ->and($result['formatted_created_at'])->toBe('2023-10-26')
        ->and($result['abilities'])->toBe(['manage-users', 'view-reports']);
});

test('getFormattedAt returns correctly formatted date with default setting from CompanySetting', function () {
    // Mock the static CompanySetting::getSetting method
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', 'test_scope')
        ->andReturn('Y-m-d'); // Default format

    // Mock the resource instance properties for created_at and scope
    $mockRole = (object) [
        'created_at' => Carbon::parse('2023-01-15 10:30:00'),
        'scope' => 'test_scope'
    ];

    $resource = new RoleResource($mockRole);

    $formattedDate = $resource->getFormattedAt();

    expect($formattedDate)->toBe('2023-01-15');
});

test('getFormattedAt returns correctly formatted date with custom setting from CompanySetting', function () {
    // Mock the static CompanySetting::getSetting method
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', 'another_scope')
        ->andReturn('M d, Y H:i'); // Custom format

    // Mock the resource instance properties
    $mockRole = (object) [
        'created_at' => Carbon::parse('2023-03-20 14:05:30'),
        'scope' => 'another_scope'
    ];

    $resource = new RoleResource($mockRole);

    $formattedDate = $resource->getFormattedAt();

    expect($formattedDate)->toBe('Mar 20, 2023 14:05');
});

test('getFormattedAt handles null created_at by formatting the current date', function () {
    // Carbon::parse(null) defaults to Carbon::now(). Test this expected behavior.
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', null) // scope could be null
        ->andReturn('Y-m-d');

    // Mock the resource instance properties with null created_at and scope
    $mockRole = (object) [
        'created_at' => null,
        'scope' => null
    ];

    $resource = new RoleResource($mockRole);

    $formattedDate = $resource->getFormattedAt();

    // Expect the current date formatted according to the provided format
    expect($formattedDate)->toBe(Carbon::now()->format('Y-m-d'));
});

test('getFormattedAt handles Carbon instance for created_at', function () {
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', 'carbon_scope')
        ->andReturn('l, F j, Y'); // Custom format

    $carbonInstance = Carbon::create(2022, 11, 25, 8, 0, 0);

    $mockRole = (object) [
        'created_at' => $carbonInstance,
        'scope' => 'carbon_scope'
    ];

    $resource = new RoleResource($mockRole);

    $formattedDate = $resource->getFormattedAt();

    expect($formattedDate)->toBe('Friday, November 25, 2022');
});

test('getFormattedAt throws exception for malformed created_at string', function () {
    // Carbon::parse() is robust, but for completely unparseable strings, it should throw.
    // In PHP 8.2+ with Carbon 2.x, this typically results in a ValueError.
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', 'error_scope')
        ->andReturn('Y-m-d');

    $mockRole = (object) [
        'created_at' => 'this is definitely not a date',
        'scope' => 'error_scope'
    ];

    $resource = new RoleResource($mockRole);

    // Expect a ValueError (or similar exception) when parsing a completely invalid date string
    expect(fn () => $resource->getFormattedAt())
        ->toThrow(ValueError::class, 'Failed to parse time string');
});

// No separate tests for protected/private methods are needed as RoleResource has none.
// getAbilities is called by toArray but not defined within RoleResource,
// thus it's mocked when testing toArray, as per white-box testing principles for the given file.
