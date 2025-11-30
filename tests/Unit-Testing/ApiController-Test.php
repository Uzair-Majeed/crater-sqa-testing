<?php

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;

uses(\Mockery::class);

test('respondSuccess returns a JsonResponse with success set to true', function () {
    // Arrange
    // 1. Mock the JsonResponse object that we expect the factory to return.
    //    We need to mock its methods (`getData`, `getStatusCode`) that will be used for assertions.
    $mockJsonResponse = Mockery::mock(JsonResponse::class);
    $mockJsonResponse->shouldReceive('getData')
                     ->andReturn((object)['success' => true]) // JsonResponse::getData() typically returns an object for array data
                     ->once(); // Ensure getData is called for assertion

    $mockJsonResponse->shouldReceive('getStatusCode')
                     ->andReturn(200) // Default status for `response()->json()`
                     ->once(); // Ensure getStatusCode is called for assertion

    // 2. Mock the ResponseFactory that the global `response()` helper resolves from the container.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('json')
                        ->once() // Ensure `json` method is called exactly once
                        ->with(['success' => true], 200, [], 0) // Verify arguments passed to json()
                        ->andReturn($mockJsonResponse); // Make it return our mocked JsonResponse

    // 3. Swap the 'response' binding in the Laravel service container with our mock factory.
    //    This ensures that when `response()` helper is called, it uses our mock.
    app()->instance(ResponseFactory::class, $mockResponseFactory);

    // Instantiate the controller under test
    $controller = new \Crater\Http\Controllers\V1\Admin\Backup\ApiController();

    // Act
    $response = $controller->respondSuccess();

    // Assert
    // Verify that the returned object is indeed our mocked JsonResponse instance.
    expect($response)->toBe($mockJsonResponse);

    // Verify the content and status code using the mocked JsonResponse methods.
    expect($response->getData())->toHaveProperty('success', true);
    expect($response->getStatusCode())->toBe(200);

    // Close Mockery to ensure all expectations set on mocks are met.
    Mockery::close();
});
