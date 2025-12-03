<?php

use Crater\Http\Controllers\V1\Admin\Settings\GetCompanyMailConfigurationController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Support\Facades\Config;

// Ensures Mockery expectations are cleared before each test runs.
beforeEach(function () {
    Mockery::close();
});

test('it returns mail configuration with valid from name and address', function () {
    // Arrange
    $expectedFromName = 'My Awesome Company';
    $expectedFromMail = 'support@awesome.com';

    // Mock the Config facade to control the values returned by the global config() helper.
    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.name')
        ->andReturn($expectedFromName);

    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.address')
        ->andReturn($expectedFromMail);

    $expectedMailConfig = [
        'from_name' => $expectedFromName,
        'from_mail' => $expectedFromMail,
    ];

    // Mock the ResponseFactory to intercept the behavior of the global response() helper.
    // We bind a Mockery instance into the Laravel application container.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $this->app->instance(ResponseFactory::class, $mockResponseFactory);

    // Expect the 'json' method on the mocked ResponseFactory to be called once
    // with the expected mail configuration data.
    // We use andReturnUsing to ensure a real JsonResponse object is returned by the mock,
    // allowing us to assert on its type and data later.
    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->with($expectedMailConfig)
        ->andReturnUsing(fn ($data, $status = 200, array $headers = []) => new JsonResponse($data, $status, $headers));

    $controller = new GetCompanyMailConfigurationController();
    // The Request object is not used by the controller's logic, so a basic instance is sufficient.
    $request = new Request();

    // Act
    $response = $controller->__invoke($request);

    // Assert
    // Verify that the controller returned an instance of JsonResponse.
    expect($response)->toBeInstanceOf(JsonResponse::class);
    // Verify that the JSON response data matches the expected mail configuration.
    expect($response->getData(true))->toEqual($expectedMailConfig);
});

test('it returns mail configuration with null values if config keys are not set', function () {
    // Arrange
    // Explicitly mock Config::get to return null for the mail configuration keys.
    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.name')
        ->andReturn(null);

    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.address')
        ->andReturn(null);

    $expectedMailConfig = [
        'from_name' => null,
        'from_mail' => null,
    ];

    // Setup ResponseFactory mock similar to the success case.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $this->app->instance(ResponseFactory::class, $mockResponseFactory);
    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->with($expectedMailConfig)
        ->andReturnUsing(fn ($data, $status = 200, array $headers = []) => new JsonResponse($data, $status, $headers));

    $controller = new GetCompanyMailConfigurationController();
    $request = new Request();

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($expectedMailConfig);
});

test('it returns mail configuration with empty string values if config keys are empty strings', function () {
    // Arrange
    // Explicitly mock Config::get to return empty strings for the mail configuration keys.
    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.name')
        ->andReturn('');

    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.address')
        ->andReturn('');

    $expectedMailConfig = [
        'from_name' => '',
        'from_mail' => '',
    ];

    // Setup ResponseFactory mock similar to the success case.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $this->app->instance(ResponseFactory::class, $mockResponseFactory);
    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->with($expectedMailConfig)
        ->andReturnUsing(fn ($data, $status = 200, array $headers = []) => new JsonResponse($data, $status, $headers));

    $controller = new GetCompanyMailConfigurationController();
    $request = new Request();

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($expectedMailConfig);
});




afterEach(function () {
    Mockery::close();
});
