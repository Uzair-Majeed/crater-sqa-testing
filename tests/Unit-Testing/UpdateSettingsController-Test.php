<?php

uses(\Mockery::class);
use Crater\Http\Controllers\V1\Admin\Settings\UpdateSettingsController;
use Crater\Http\Requests\SettingRequest;
use Crater\Models\Setting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

// Clear Mockery expectations before each test to prevent conflicts
beforeEach(function () {
    Mockery::close();
});

test('it successfully updates settings and returns a success JSON response with the updated settings', function () {
    // Arrange: Define the settings data to be passed
    $mockSettings = ['app_name' => 'Crater Invoicing', 'currency' => 'EUR', 'locale' => 'en'];

    // Mock the SettingRequest to simulate an incoming request with specific settings
    $request = Mockery::mock(SettingRequest::class);
    // Assign the mockSettings to the 'settings' property of the request
    $request->settings = $mockSettings;

    // Mock the static `Setting::setSettings` method to ensure it's called correctly
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('setSettings')
        ->once() // Expect it to be called exactly once
        ->with($mockSettings) // Expect it to be called with the mockSettings
        ->andReturn(null); // The actual method doesn't return a value

    // Create a partial mock of the UpdateSettingsController to mock its 'authorize' method
    $controller = Mockery::mock(UpdateSettingsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once() // Expect 'authorize' to be called once
        ->with('manage settings') // Expect it to be called with this specific permission string
        ->andReturn(true); // Simulate successful authorization

    // Act: Invoke the controller's __invoke method with the mocked request
    $response = $controller->__invoke($request);

    // Assert: Verify the response
    expect($response)
        ->toBeInstanceOf(JsonResponse::class) // Ensure the response is a JSON response
        ->and($response->getStatusCode())->toBe(200); // Ensure HTTP status is 200 OK

    // Get the JSON data from the response and assert its structure and content
    $responseData = $response->getData(true); // true to get associative array
    expect($responseData)->toMatchArray([
        'success' => true,
        0 => $mockSettings // The controller returns settings as an indexed array element '0'
    ]);
});

test('it throws an AuthorizationException if the user is not authorized to manage settings', function () {
    // Arrange: Define some settings data
    $mockSettings = ['app_name' => 'Unauthorized Test'];

    // Mock the SettingRequest
    $request = Mockery::mock(SettingRequest::class);
    $request->settings = $mockSettings;

    // Mock the static `Setting::setSettings` method and ensure it's NOT called
    Mockery::mock('alias:' . Setting::class)
        ->shouldNotReceive('setSettings'); // Should not be called if authorization fails

    // Create a partial mock of the controller and configure its 'authorize' method to throw an exception
    $controller = Mockery::mock(UpdateSettingsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage settings')
        ->andThrow(new AuthorizationException('User does not have permission to manage settings.')); // Simulate authorization failure

    // Act & Assert: Expect an AuthorizationException to be thrown when invoking the controller
    expect(fn () => $controller->__invoke($request))
        ->toThrow(AuthorizationException::class, 'User does not have permission to manage settings.');
});

test('it gracefully handles an empty settings array and returns a success response', function () {
    // Arrange: Use an empty array for settings
    $mockSettings = [];

    // Mock the SettingRequest with the empty settings array
    $request = Mockery::mock(SettingRequest::class);
    $request->settings = $mockSettings;

    // Mock `Setting::setSettings` to ensure it's called with the empty array
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('setSettings')
        ->once()
        ->with($mockSettings)
        ->andReturn(null);

    // Mock successful authorization
    $controller = Mockery::mock(UpdateSettingsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage settings')
        ->andReturn(true);

    // Act: Invoke the controller
    $response = $controller->__invoke($request);

    // Assert: Verify the response for an empty settings case
    expect($response)
        ->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200);

    $responseData = $response->getData(true);
    expect($responseData)->toMatchArray([
        'success' => true,
        0 => $mockSettings
    ]);
});

test('it correctly processes settings containing various data types including booleans, integers, and arrays', function () {
    // Arrange: Define settings with mixed data types
    $mockSettings = [
        'app_name' => 'Advanced App',
        'debug_mode' => true,
        'item_limit' => 500,
        'tax_rates' => [0.05, 0.10, 0.15],
        'admin_contact' => 'admin@example.com',
        'footer_text' => null, // Testing null value
    ];

    // Mock the SettingRequest
    $request = Mockery::mock(SettingRequest::class);
    $request->settings = $mockSettings;

    // Mock `Setting::setSettings` to ensure it's called with the diverse settings array
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('setSettings')
        ->once()
        ->with($mockSettings)
        ->andReturn(null);

    // Mock successful authorization
    $controller = Mockery::mock(UpdateSettingsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage settings')
        ->andReturn(true);

    // Act: Invoke the controller
    $response = $controller->__invoke($request);

    // Assert: Verify the response contains the diverse settings as expected
    expect($response)
        ->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200);

    $responseData = $response->getData(true);
    expect($responseData)->toMatchArray([
        'success' => true,
        0 => $mockSettings
    ]);
});
