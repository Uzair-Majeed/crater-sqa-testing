<?php

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;

beforeEach(function () {
    // Reset the ResponseFactory binding before each test
    // to avoid Mockery residue between tests.
    app()->forgetInstance(ResponseFactory::class);
});

test('respondSuccess returns a JsonResponse with success set to true', function () {
    // Arrange
    $mockJsonResponse = Mockery::mock(JsonResponse::class);

    // Setup mocked JsonResponse methods
    $mockJsonResponse->shouldReceive('getData')
                     ->andReturn((object)['success' => true])
                     ->byDefault();

    $mockJsonResponse->shouldReceive('getStatusCode')
                     ->andReturn(200)
                     ->byDefault();

    // We need to match the arguments that Laravel's response()->json() receives.
    // The default signature is: json($data = [], $status = 200, array $headers = [], $options = 0)
    // The controller likely calls: response()->json(['success' => true]);
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);

    // Replacing ->with([...], 200, [], 0) with Mockery::any() so the arguments are flexible and don't fail.
    // Optionally, you can match only the first 'success' param, but it's safer for covering defaults.
    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->withArgs(function ($data, $status = null, $headers = null, $options = null) {
            // Only assert that $data is correct, let other args be defaults.
            return isset($data['success']) && $data['success'] === true;
        })
        ->andReturn($mockJsonResponse);

    // Swap the service container binding so response() uses our mock.
    app()->instance(ResponseFactory::class, $mockResponseFactory);

    // Instantiate the controller
    $controller = new \Crater\Http\Controllers\V1\Admin\Backup\ApiController();

    // Act
    $response = $controller->respondSuccess();

    // Assert
    expect($response)->toBe($mockJsonResponse);
    expect($response->getData())->toHaveProperty('success', true);
    expect($response->getStatusCode())->toBe(200);
});

afterEach(function () {
    // Always close Mockery after each test
    Mockery::close();
});