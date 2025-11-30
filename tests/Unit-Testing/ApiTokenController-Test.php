<?php

use Crater\Http\Controllers\V1\Admin\Modules\ApiTokenController;
use Crater\Space\ModuleInstaller;
use Illuminate\Http\Request;
use Illuminate\Http\Response; // Using Illuminate\Http\Response for the return type in mock

beforeEach(function () {
    // Clear mocks before each test to prevent interference
    Mockery::close();
});

test('invoke authorizes and returns module installer response on success', function () {
    // Mock the Request object
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->api_token = 'valid-token-123';

    // Mock the ModuleInstaller class (static facade-like method)
    // Using alias allows mocking static methods
    $mockModuleInstaller = Mockery::mock('alias:' . ModuleInstaller::class);
    $expectedResponse = new Response(['success' => true, 'message' => 'Token verified'], 200);
    $mockModuleInstaller->shouldReceive('checkToken')
                        ->once()
                        ->with('valid-token-123')
                        ->andReturn($expectedResponse);

    // Mock the controller itself to intercept the authorize method
    $controller = Mockery::mock(ApiTokenController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true); // Simulate successful authorization

    // Call the __invoke method
    $response = $controller->__invoke($mockRequest);

    // Assert the response
    expect($response)->toBe($expectedResponse);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['success' => true, 'message' => 'Token verified']);

    // Verify mocks
    Mockery::close();
});

test('invoke authorizes and returns module installer response on failure', function () {
    // Mock the Request object
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->api_token = 'invalid-token-xyz';

    // Mock the ModuleInstaller class (static facade-like method)
    $mockModuleInstaller = Mockery::mock('alias:' . ModuleInstaller::class);
    $expectedResponse = new Response(['success' => false, 'message' => 'Invalid token'], 401);
    $mockModuleInstaller->shouldReceive('checkToken')
                        ->once()
                        ->with('invalid-token-xyz')
                        ->andReturn($expectedResponse);

    // Mock the controller itself to intercept the authorize method
    $controller = Mockery::mock(ApiTokenController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true); // Simulate successful authorization for the method call

    // Call the __invoke method
    $response = $controller->__invoke($mockRequest);

    // Assert the response
    expect($response)->toBe($expectedResponse);
    expect($response->getStatusCode())->toBe(401);
    expect($response->getData(true))->toEqual(['success' => false, 'message' => 'Invalid token']);

    // Verify mocks
    Mockery::close();
});

test('invoke passes null api token to module installer if not present in request', function () {
    // Mock the Request object without an api_token property
    $mockRequest = Mockery::mock(Request::class);
    // Explicitly set api_token to null to simulate it not being present or being null
    $mockRequest->api_token = null;

    // Mock the ModuleInstaller class
    $mockModuleInstaller = Mockery::mock('alias:' . ModuleInstaller::class);
    $expectedResponse = new Response(['success' => false, 'message' => 'API token is required'], 400);
    $mockModuleInstaller->shouldReceive('checkToken')
                        ->once()
                        ->with(null) // Expect null to be passed if the property is not set or is null
                        ->andReturn($expectedResponse);

    // Mock the controller
    $controller = Mockery::mock(ApiTokenController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    // Call the __invoke method
    $response = $controller->__invoke($mockRequest);

    // Assert the response
    expect($response)->toBe($expectedResponse);
    expect($response->getStatusCode())->toBe(400);

    // Verify mocks
    Mockery::close();
});

test('invoke passes empty string api token to module installer if present as empty string in request', function () {
    // Mock the Request object with an empty string api_token
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->api_token = '';

    // Mock the ModuleInstaller class
    $mockModuleInstaller = Mockery::mock('alias:' . ModuleInstaller::class);
    $expectedResponse = new Response(['success' => false, 'message' => 'API token cannot be empty'], 400);
    $mockModuleInstaller->shouldReceive('checkToken')
                        ->once()
                        ->with('') // Expect empty string to be passed
                        ->andReturn($expectedResponse);

    // Mock the controller
    $controller = Mockery::mock(ApiTokenController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    // Call the __invoke method
    $response = $controller->__invoke($mockRequest);

    // Assert the response
    expect($response)->toBe($expectedResponse);
    expect($response->getStatusCode())->toBe(400);

    // Verify mocks
    Mockery::close();
});

test('authorize method is called with correct capability', function () {
    // Mock the Request object
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->api_token = 'any-token';

    // Mock the ModuleInstaller class, since it will be called regardless of authorize outcome (in this unit test scenario)
    $mockModuleInstaller = Mockery::mock('alias:' . ModuleInstaller::class);
    $mockModuleInstaller->shouldReceive('checkToken')
                        ->once()
                        ->andReturn(new Response(['success' => true], 200));

    // Mock the controller itself to assert authorize call
    $controller = Mockery::mock(ApiTokenController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules'); // We are asserting that 'manage modules' is passed

    // Call the __invoke method
    $controller->__invoke($mockRequest);

    // If the test reaches here and no Mockery expectation failures, it means authorize was called correctly
    // The explicit 'once()' and 'with()' on 'shouldReceive' handles the assertion.
    Mockery::close();
});
