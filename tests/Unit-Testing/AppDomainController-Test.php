<?php

use Crater\Http\Controllers\V1\Installation\AppDomainController;
use Crater\Http\Requests\DomainEnvironmentRequest;
use Crater\Space\EnvironmentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Mockery as m;

// This `beforeEach` hook will run before each test in this file.
// It ensures Mockery mocks are closed and cleaned up before each test, preventing test interference.
beforeEach(function () {
    m::close();
});

test('it clears the optimize cache and returns success when environment variables are saved without errors', function () {
    // Arrange: Set up mocks for dependencies.

    // Mock the Artisan facade to ensure `Artisan::call('optimize:clear')` is executed once.
    Artisan::shouldReceive('call')
           ->once()
           ->with('optimize:clear')
           ->andReturn(0); // Simulate successful Artisan command execution (exit code 0).

    // Mock the DomainEnvironmentRequest instance.
    // The controller passes this request to EnvironmentManager, but doesn't directly use its validated data for its own logic.
    $mockRequest = m::mock(DomainEnvironmentRequest::class);

    // Mock the EnvironmentManager class using Mockery's `overload` feature.
    // This allows us to intercept `new EnvironmentManager()` calls within the controller.
    $mockEnvironmentManager = m::mock('overload:' . EnvironmentManager::class);

    // Expect the constructor of EnvironmentManager to be called once when the controller instantiates it.
    $mockEnvironmentManager->shouldReceive('__construct')
                           ->once();

    // Expect the `saveDomainVariables` method to be called once with the mocked request.
    // Simulate a successful scenario where the returned array does NOT contain the string 'error' as a value.
    $mockEnvironmentManager->shouldReceive('saveDomainVariables')
                           ->once()
                           ->with($mockRequest)
                           ->andReturn(['status' => 'success', 'message' => 'Variables saved successfully']);

    // Act: Instantiate the controller and call its `__invoke` method.
    $controller = new AppDomainController();
    $response = $controller($mockRequest);

    // Assert: Verify the behavior and the returned response.
    expect($response)->toBeInstanceOf(JsonResponse::class); // Ensure a JsonResponse is returned.
    $response->assertSuccessful(); // Assert that the HTTP status code is in the 2xx range (e.g., 200 OK).
    expect($response->json())->toEqual(['success' => true]); // Assert the specific JSON payload.
});

test('it clears the optimize cache and returns errors when environment variables fail to save', function () {
    // Arrange: Set up mocks for dependencies.

    // Mock the Artisan facade as in the success case.
    Artisan::shouldReceive('call')
           ->once()
           ->with('optimize:clear')
           ->andReturn(0);

    // Mock the DomainEnvironmentRequest instance.
    $mockRequest = m::mock(DomainEnvironmentRequest::class);

    // Mock the EnvironmentManager class using overload.
    $mockEnvironmentManager = m::mock('overload:' . EnvironmentManager::class);

    // Expect the constructor to be called once.
    $mockEnvironmentManager->shouldReceive('__construct')
                           ->once();

    // Expect the `saveDomainVariables` method to be called once with the mocked request.
    // Simulate a failure scenario: the returned array MUST contain the string 'error' as one of its values
    // to trigger the controller's error handling path (`if (in_array('error', $results))`).
    $expectedErrorResults = ['message' => 'Failed to write to .env file', 'error', 'code' => 500];
    $mockEnvironmentManager->shouldReceive('saveDomainVariables')
                           ->once()
                           ->with($mockRequest)
                           ->andReturn($expectedErrorResults);

    // Act: Instantiate the controller and call its `__invoke` method.
    $controller = new AppDomainController();
    $response = $controller($mockRequest);

    // Assert: Verify the behavior and the returned response.
    expect($response)->toBeInstanceOf(JsonResponse::class); // Ensure a JsonResponse is returned.
    // The controller's logic returns 200 OK even if `saveDomainVariables` indicates an error,
    // as long as the 'error' string is found in the results array.
    $response->assertOk(); // Assert that the HTTP status code is 200 OK.
    expect($response->json())->toEqual($expectedErrorResults); // Assert the specific error JSON payload.
});

// This `afterEach` hook will run after each test in this file.
// It's crucial for Mockery to verify all expectations and clean up mocks.
afterEach(function () {
    m::close();
});

