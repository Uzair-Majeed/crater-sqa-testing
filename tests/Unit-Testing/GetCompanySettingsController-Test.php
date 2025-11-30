<?php

use Illuminate\Http\JsonResponse;
uses(\Mockery::class);
use Crater\Http\Controllers\V1\Admin\Settings\GetCompanySettingsController;
use Crater\Http\Requests\GetSettingsRequest;
use Crater\Models\CompanySetting;

beforeEach(function () {
    // Clear Mockery mocks before each test to prevent interference
    Mockery::close();
});

test('it returns company settings when multiple keys are requested', function () {
    // Arrange
    $settingsKeys = ['app_name', 'currency_code', 'date_format'];
    $companyId = 1;
    $expectedSettings = [
        'app_name' => 'Crater Finance',
        'currency_code' => 'USD',
        'date_format' => 'Y-m-d',
    ];

    // Mock the GetSettingsRequest to control its properties and methods
    $mockRequest = Mockery::mock(GetSettingsRequest::class);
    $mockRequest->settings = $settingsKeys; // Set the public property
    $mockRequest->shouldReceive('header')
                ->with('company')
                ->andReturn($companyId)
                ->once();

    // Mock the static method `CompanySetting::getSettings`
    // using Mockery's alias feature for static methods
    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);
    $mockCompanySetting->shouldReceive('getSettings')
                       ->once() // Expect the method to be called exactly once
                       ->with($settingsKeys, $companyId) // Expect specific arguments
                       ->andReturn($expectedSettings); // Return mock data

    $controller = new GetCompanySettingsController();

    // Act
    $response = $controller($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($expectedSettings);
    expect($response->getStatusCode())->toBe(200);

    // Verify all expectations on mocks were met
    Mockery::close();
});

test('it returns company settings when a single key is requested', function () {
    // Arrange
    $settingsKeys = ['tax_type'];
    $companyId = 2;
    $expectedSettings = [
        'tax_type' => 'exclusive',
    ];

    $mockRequest = Mockery::mock(GetSettingsRequest::class);
    $mockRequest->settings = $settingsKeys;
    $mockRequest->shouldReceive('header')
                ->with('company')
                ->andReturn($companyId)
                ->once();

    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);
    $mockCompanySetting->shouldReceive('getSettings')
                       ->once()
                       ->with($settingsKeys, $companyId)
                       ->andReturn($expectedSettings);

    $controller = new GetCompanySettingsController();

    // Act
    $response = $controller($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($expectedSettings);
    expect($response->getStatusCode())->toBe(200);

    Mockery::close();
});

test('it returns an empty array when no settings are found for the requested keys', function () {
    // Arrange
    $settingsKeys = ['non_existent_key_1', 'non_existent_key_2'];
    $companyId = 3;
    $expectedSettings = []; // Simulate no settings found

    $mockRequest = Mockery::mock(GetSettingsRequest::class);
    $mockRequest->settings = $settingsKeys;
    $mockRequest->shouldReceive('header')
                ->with('company')
                ->andReturn($companyId)
                ->once();

    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);
    $mockCompanySetting->shouldReceive('getSettings')
                       ->once()
                       ->with($settingsKeys, $companyId)
                       ->andReturn($expectedSettings);

    $controller = new GetCompanySettingsController();

    // Act
    $response = $controller($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($expectedSettings);
    expect($response->getStatusCode())->toBe(200);

    Mockery::close();
});

test('it handles null company header gracefully', function () {
    // Arrange
    $settingsKeys = ['email_sender'];
    $companyId = null; // Simulate a missing or null 'company' header
    $expectedSettings = [
        'email_sender' => 'noreply@crater.app',
    ];

    $mockRequest = Mockery::mock(GetSettingsRequest::class);
    $mockRequest->settings = $settingsKeys;
    $mockRequest->shouldReceive('header')
                ->with('company')
                ->andReturn($companyId)
                ->once();

    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);
    $mockCompanySetting->shouldReceive('getSettings')
                       ->once()
                       ->with($settingsKeys, $companyId)
                       ->andReturn($expectedSettings);

    $controller = new GetCompanySettingsController();

    // Act
    $response = $controller($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($expectedSettings);
    expect($response->getStatusCode())->toBe(200);

    Mockery::close();
});

test('it handles an empty array of requested settings keys', function () {
    // Arrange
    $settingsKeys = []; // Requesting no specific settings
    $companyId = 4;
    $expectedSettings = []; // CompanySetting::getSettings should return empty for empty input

    $mockRequest = Mockery::mock(GetSettingsRequest::class);
    $mockRequest->settings = $settingsKeys;
    $mockRequest->shouldReceive('header')
                ->with('company')
                ->andReturn($companyId)
                ->once();

    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);
    $mockCompanySetting->shouldReceive('getSettings')
                       ->once()
                       ->with($settingsKeys, $companyId)
                       ->andReturn($expectedSettings);

    $controller = new GetCompanySettingsController();

    // Act
    $response = $controller($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($expectedSettings);
    expect($response->getStatusCode())->toBe(200);

    Mockery::close();
});
