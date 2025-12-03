<?php

use Crater\Http\Controllers\AppVersionController;
use Crater\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery as m;

test('it returns the application version successfully', function () {
    // Arrange
    $expectedVersion = '1.2.3';

    // Mock the static method Setting::getSetting
    // We use an alias mock to intercept static calls to the Setting model.
    m::mock('alias:'.Setting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('version')
        ->andReturn($expectedVersion);

    $controller = new AppVersionController();
    $request = Request::create('/'); // Create a dummy request instance

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['version' => $expectedVersion]);
});

test('it returns null for version if the setting is not found', function () {
    // Arrange
    m::mock('alias:'.Setting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('version')
        ->andReturn(null); // Simulate setting not being present or found

    $controller = new AppVersionController();
    $request = Request::create('/');

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['version' => null]);
});

test('it returns an empty string for version if the setting is empty', function () {
    // Arrange
    m::mock('alias:'.Setting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('version')
        ->andReturn(''); // Simulate an empty string setting value

    $controller = new AppVersionController();
    $request = Request::create('/');

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['version' => '']);
});

test('it handles a request instance, even if not used internally', function () {
    // Arrange
    $expectedVersion = '4.5.6';

    m::mock('alias:'.Setting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('version')
        ->andReturn($expectedVersion);

    $controller = new AppVersionController();
    // Create a specific request instance to ensure it's accepted by the method signature
    $request = Request::create('/api/version', 'GET', ['some_param' => 'value']);

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['version' => $expectedVersion]);
    // No specific assertion about the $request content itself, as the controller doesn't use it.
    // The test confirms the method call completes successfully with a valid Request object.
});



afterEach(function () {
    Mockery::close();
});
