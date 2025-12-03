<?php

use Crater\Http\Middleware\Authenticate;
use Illuminate\Http\Request;
use function Pest\Mock\Functions\expect;

beforeEach(function () {
    // Ensure mocks are cleaned up before each test
    Mockery::close();
});

test('redirectTo returns login route when request does not expect JSON', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('expectsJson')
        ->once()
        ->andReturn(false); // Simulate a non-JSON request

    // Mock the global route() helper function to return a predictable URL
    expect('route')
        ->with('login')
        ->andReturn('/mocked-login-url')
        ->once();

    $middleware = new Authenticate();

    // Act
    $result = $middleware->redirectTo($mockRequest);

    // Assert
    expect($result)->toBe('/mocked-login-url');
});

test('redirectTo returns null when request expects JSON', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('expectsJson')
        ->once()
        ->andReturn(true); // Simulate a JSON request

    // Ensure route() helper is NOT called when expectsJson is true
    expect('route')->never();

    $middleware = new Authenticate();

    // Act
    $result = $middleware->redirectTo($mockRequest);

    // Assert
    expect($result)->toBeNull();
});

test('redirectTo handles multiple calls correctly for non-JSON requests', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('expectsJson')
        ->times(2) // Expect two calls to expectsJson
        ->andReturn(false);

    // Mock the global route() helper function
    expect('route')
        ->with('login')
        ->andReturn('/mocked-login-url')
        ->times(2); // Expect two calls to route()

    $middleware = new Authenticate();

    // Act
    $result1 = $middleware->redirectTo($mockRequest);
    $result2 = $middleware->redirectTo($mockRequest);

    // Assert
    expect($result1)->toBe('/mocked-login-url');
    expect($result2)->toBe('/mocked-login-url');
});

test('redirectTo handles multiple calls correctly for mixed JSON and non-JSON requests', function () {
    // Arrange
    $mockRequest1 = Mockery::mock(Request::class);
    $mockRequest1->shouldReceive('expectsJson')
        ->once()
        ->andReturn(false); // First request is non-JSON

    $mockRequest2 = Mockery::mock(Request::class);
    $mockRequest2->shouldReceive('expectsJson')
        ->once()
        ->andReturn(true); // Second request is JSON

    $mockRequest3 = Mockery::mock(Request::class);
    $mockRequest3->shouldReceive('expectsJson')
        ->once()
        ->andReturn(false); // Third request is non-JSON

    // Mock the global route() helper function
    expect('route')
        ->with('login')
        ->andReturn('/mocked-login-url')
        ->times(2); // Expected to be called twice (for mockRequest1 and mockRequest3)

    $middleware = new Authenticate();

    // Act & Assert
    expect($middleware->redirectTo($mockRequest1))->toBe('/mocked-login-url'); // Non-JSON
    expect($middleware->redirectTo($mockRequest2))->toBeNull(); // JSON
    expect($middleware->redirectTo($mockRequest3))->toBe('/mocked-login-url'); // Non-JSON
});



afterEach(function () {
    Mockery::close();
});
