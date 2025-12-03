<?php

use Crater\Http\Controllers\V1\Admin\Modules\ApiTokenController;
use Crater\Space\ModuleInstaller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

beforeEach(function () {
    // Clear mocks before each test to prevent interference
    Mockery::close();
});

test('invoke authorizes and returns module installer response on success', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->api_token = 'valid-token-123';

    $mockModuleInstaller = Mockery::mock('alias:' . ModuleInstaller::class);
    $expectedPayload = ['success' => true, 'message' => 'Token verified'];
    $expectedResponse = new Response(json_encode($expectedPayload), 200, ['Content-Type' => 'application/json']);
    $mockModuleInstaller->shouldReceive('checkToken')
                        ->once()
                        ->with('valid-token-123')
                        ->andReturn($expectedResponse);

    $controller = Mockery::mock(ApiTokenController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    $response = $controller->__invoke($mockRequest);

    expect($response)->toBe($expectedResponse);
    expect($response->getStatusCode())->toBe(200);

    // Decode JSON content for assertion
    $actualData = json_decode($response->getContent(), true);
    expect($actualData)->toEqual($expectedPayload);

    Mockery::close();
});

test('invoke authorizes and returns module installer response on failure', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->api_token = 'invalid-token-xyz';

    $mockModuleInstaller = Mockery::mock('alias:' . ModuleInstaller::class);
    $expectedPayload = ['success' => false, 'message' => 'Invalid token'];
    $expectedResponse = new Response(json_encode($expectedPayload), 401, ['Content-Type' => 'application/json']);
    $mockModuleInstaller->shouldReceive('checkToken')
                        ->once()
                        ->with('invalid-token-xyz')
                        ->andReturn($expectedResponse);

    $controller = Mockery::mock(ApiTokenController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    $response = $controller->__invoke($mockRequest);

    expect($response)->toBe($expectedResponse);
    expect($response->getStatusCode())->toBe(401);

    $actualData = json_decode($response->getContent(), true);
    expect($actualData)->toEqual($expectedPayload);

    Mockery::close();
});

test('invoke passes null api token to module installer if not present in request', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->api_token = null;

    $mockModuleInstaller = Mockery::mock('alias:' . ModuleInstaller::class);
    $expectedPayload = ['success' => false, 'message' => 'API token is required'];
    $expectedResponse = new Response(json_encode($expectedPayload), 400, ['Content-Type' => 'application/json']);
    $mockModuleInstaller->shouldReceive('checkToken')
                        ->once()
                        ->with(null)
                        ->andReturn($expectedResponse);

    $controller = Mockery::mock(ApiTokenController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    $response = $controller->__invoke($mockRequest);

    expect($response)->toBe($expectedResponse);
    expect($response->getStatusCode())->toBe(400);

    $actualData = json_decode($response->getContent(), true);
    expect($actualData)->toEqual($expectedPayload);

    Mockery::close();
});

test('invoke passes empty string api token to module installer if present as empty string in request', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->api_token = '';

    $mockModuleInstaller = Mockery::mock('alias:' . ModuleInstaller::class);
    $expectedPayload = ['success' => false, 'message' => 'API token cannot be empty'];
    $expectedResponse = new Response(json_encode($expectedPayload), 400, ['Content-Type' => 'application/json']);
    $mockModuleInstaller->shouldReceive('checkToken')
                        ->once()
                        ->with('')
                        ->andReturn($expectedResponse);

    $controller = Mockery::mock(ApiTokenController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    $response = $controller->__invoke($mockRequest);

    expect($response)->toBe($expectedResponse);
    expect($response->getStatusCode())->toBe(400);

    $actualData = json_decode($response->getContent(), true);
    expect($actualData)->toEqual($expectedPayload);

    Mockery::close();
});

test('authorize method is called with correct capability', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->api_token = 'any-token';

    $mockModuleInstaller = Mockery::mock('alias:' . ModuleInstaller::class);
    $expectedPayload = ['success' => true];
    $expectedResponse = new Response(json_encode($expectedPayload), 200, ['Content-Type' => 'application/json']);
    $mockModuleInstaller->shouldReceive('checkToken')
                        ->once()
                        ->with('any-token')
                        ->andReturn($expectedResponse);

    $controller = Mockery::mock(ApiTokenController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    $response = $controller->__invoke($mockRequest);

    expect($response)->toBeInstanceOf(Response::class);

    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});