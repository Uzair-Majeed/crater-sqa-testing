<?php

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Crater\Http\Controllers\V1\Admin\General\TimezonesController;
use Crater\Space\TimeZones;
uses(\Mockery::class);

beforeEach(function () {
    // Ensure Mockery is closed after each test to prevent mock leaks.
    Mockery::close();
});

test('it successfully retrieves a list of timezones', function () {
    // Arrange
    $mockTimezones = [
        ['value' => 'Africa/Abidjan', 'label' => 'Africa/Abidjan'],
        ['value' => 'Africa/Accra', 'label' => 'Africa/Accra'],
        ['value' => 'America/New_York', 'label' => 'America/New_York'],
    ];

    // Mock the static method TimeZones::get_list()
    Mockery::mock('alias:' . TimeZones::class)
        ->shouldReceive('get_list')
        ->once()
        ->andReturn($mockTimezones);

    $controller = new TimezonesController();
    $request = Request::create('/'); // Create a dummy request instance

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['time_zones' => $mockTimezones]);
});

test('it returns an empty array when no timezones are available', function () {
    // Arrange
    $mockTimezones = [];

    // Mock TimeZones::get_list() to return an empty array
    Mockery::mock('alias:' . TimeZones::class)
        ->shouldReceive('get_list')
        ->once()
        ->andReturn($mockTimezones);

    $controller = new TimezonesController();
    $request = Request::create('/');

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['time_zones' => $mockTimezones]);
});

test('it handles a large number of timezones gracefully', function () {
    // Arrange
    $mockTimezones = [];
    for ($i = 0; $i < 200; $i++) {
        $mockTimezones[] = ['value' => "Zone{$i}", 'label' => "Time Zone Label {$i}"];
    }

    // Mock TimeZones::get_list() to return a large array
    Mockery::mock('alias:' . TimeZones::class)
        ->shouldReceive('get_list')
        ->once()
        ->andReturn($mockTimezones);

    $controller = new TimezonesController();
    $request = Request::create('/');

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['time_zones' => $mockTimezones]);
});

test('it handles null return from TimeZones get_list method gracefully', function () {
    // Arrange
    // While TimeZones::get_list() is expected to return an array,
    // this tests an edge case where a dependency might return null.
    Mockery::mock('alias:' . TimeZones::class)
        ->shouldReceive('get_list')
        ->once()
        ->andReturn(null);

    $controller = new TimezonesController();
    $request = Request::create('/');

    // Act
    $response = $controller->__invoke($request);

    // Assert
    // Laravel's response()->json() helper encodes null values correctly.
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['time_zones' => null]);
});

test('it handles non-array return from TimeZones get_list method gracefully', function () {
    // Arrange
    // This tests an extreme edge case for white-box coverage if TimeZones::get_list()
    // unexpectedly returned a non-array value like a string.
    $unexpectedData = 'an_unexpected_string_instead_of_array';

    Mockery::mock('alias:' . TimeZones::class)
        ->shouldReceive('get_list')
        ->once()
        ->andReturn($unexpectedData);

    $controller = new TimezonesController();
    $request = Request::create('/');

    // Act
    $response = $controller->__invoke($request);

    // Assert
    // Laravel's response()->json() helper encodes non-array values directly into the JSON.
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['time_zones' => $unexpectedData]);
});
