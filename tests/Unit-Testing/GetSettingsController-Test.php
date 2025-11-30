<?php

uses(\Mockery::class);
use Crater\Http\Controllers\V1\Admin\Settings\GetSettingsController;
use Crater\Http\Requests\GetSettingRequest;
use Crater\Models\Setting;
use Illuminate\Http\JsonResponse;

beforeEach(function () {
    // Ensure Mockery is clean before each test
    Mockery::close();
});

test('it successfully retrieves an existing setting', function () {
    // Arrange
    $key = 'app_name';
    $value = 'Crater CRM';

    // Mock the GetSettingRequest to return the expected key
    $request = Mockery::mock(GetSettingRequest::class);
    $request->shouldReceive('offsetGet')->with('key')->andReturn($key);

    // Create a partial mock of the controller to mock the authorize method
    $controller = Mockery::mock(GetSettingsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage settings')
        ->andReturn(true); // Simulate successful authorization

    // Mock the static getSetting method of the Setting model
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with($key)
        ->andReturn($value);

    // Act
    $response = $controller($request);

    // Assert
    expect($response)
        ->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true))
        ->toEqual([$key => $value]);
});

test('it retrieves null for a non-existent setting', function () {
    // Arrange
    $key = 'non_existent_key';
    $value = null; // Simulate setting not found

    // Mock the GetSettingRequest
    $request = Mockery::mock(GetSettingRequest::class);
    $request->shouldReceive('offsetGet')->with('key')->andReturn($key);

    // Create a partial mock of the controller
    $controller = Mockery::mock(GetSettingsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage settings')
        ->andReturn(true);

    // Mock the static getSetting method of the Setting model
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with($key)
        ->andReturn($value);

    // Act
    $response = $controller($request);

    // Assert
    expect($response)
        ->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true))
        ->toEqual([$key => $value]);
});

test('it handles an empty string key returning null if setting does not exist', function () {
    // Arrange
    $key = ''; // An empty string key
    $value = null; // Assume no setting for an empty key

    // Mock the GetSettingRequest
    $request = Mockery::mock(GetSettingRequest::class);
    $request->shouldReceive('offsetGet')->with('key')->andReturn($key);

    // Create a partial mock of the controller
    $controller = Mockery::mock(GetSettingsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage settings')
        ->andReturn(true);

    // Mock the static getSetting method of the Setting model
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with($key)
        ->andReturn($value);

    // Act
    $response = $controller($request);

    // Assert
    expect($response)
        ->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true))
        ->toEqual([$key => $value]);
});

test('it ensures authorization is called', function () {
    // Arrange
    $key = 'some_key';
    $value = 'some_value';

    // Mock the GetSettingRequest
    $request = Mockery::mock(GetSettingRequest::class);
    $request->shouldReceive('offsetGet')->with('key')->andReturn($key);

    // Create a partial mock of the controller and expect authorize to be called
    $controller = Mockery::mock(GetSettingsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once() // This ensures it's called exactly once
        ->with('manage settings')
        ->andReturn(true);

    // Mock the static getSetting method
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with($key)
        ->andReturn($value);

    // Act
    $controller($request);

    // Assertions are handled by Mockery's expectation that authorize was called once.
    // If authorize was not called, Mockery would throw an error at the end of the test.
    // No explicit expect() call is strictly necessary here for this specific assertion.
    $this->assertTrue(true); // Placeholder to satisfy Pest's expectation of an assertion
});
