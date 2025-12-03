<?php

use Crater\Http\Middleware\Authenticate;
use Illuminate\Http\Request;
use Mockery;
use Pest\Laravel;

/**
 * Utility: swap the global 'route' helper with predictable output for the test,
 * and restore after test. This is required because Authenticate uses route('login)
 * and we want deterministic return values.
 */
function mockRouteHelper($times = 1, $returnUrl = '/mocked-login-url')
{
    // Store original route function
    if (!function_exists('__original_route')) {
        // Only store once for all tests
        function __original_route()
        {
            // Intentionally left blank, just a function placeholder
        }
        global $__original_route_func;
        if (!isset($__original_route_func)) {
            $__original_route_func = true;
            if (function_exists('route')) {
                runkit_function_rename('route', '__original_route_real');
            }
        }
    }

    // Create a mock route helper in global namespace.
    // PHP doesn't allow direct redeclaration within same process,
    // So, we use Pest's built-in swap helper if available, otherwise use eval.

    // Use Pest's helper, or fallback to eval if unavailable.
    // Ensuring deterministic output.
    if (!function_exists('route')) {
        eval('function route($name, ...$params) { return "/mocked-login-url"; }');
    }
}

/**
 * Restore the original route helper function (after test).
 */
function restoreRouteHelper()
{
    if (function_exists('route') && function_exists('__original_route_real')) {
        // Remove test mock and restore
        runkit_function_remove('route');
        runkit_function_rename('__original_route_real', 'route');
    } elseif (function_exists('route')) {
        // Remove test mock, leave as unset
        runkit_function_remove('route');
    }
}



// Instead of redefining global helpers, use Pest's swap if available: prefer built-in.
function swapRouteHelper($returnUrl = '/mocked-login-url')
{
    // If Pest's swap() exists:
    if (function_exists('\Pest\Laravel\swap')) {
        \Pest\Laravel\swap('route', function ($name, ...$params) use ($returnUrl) {
            // Only handle login route
            if ($name === 'login') {
                return $returnUrl;
            }
            // For other routes, fallback to default (optional)
            return '/default-url';
        });
    } else {
        // If Pest's swap not available, fallback to manual override for legacy PHP.
        if (!function_exists('route')) {
            eval('function route($name, ...$params) { return "' . $returnUrl . '"; }');
        }
    }
}

function restoreRouteSwap()
{
    // Pest swap is auto-restored for each test, but if manual, remove.
    if (function_exists('route')) {
        // If function is provided by eval, we cannot easily remove, but Pest test is isolated.
        // Do nothing; Pest test isolation handles this.
    }
}

beforeEach(function () {
    Mockery::close();
});

test('redirectTo returns login route when request does not expect JSON', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('expectsJson')
        ->once()
        ->andReturn(false); // Simulate a non-JSON request

    // Swap the global route() helper to return a predictable URL
    swapRouteHelper('/mocked-login-url');

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

    // Swap the route() helper, but Authenticate should NOT call it if expectsJson is true.
    swapRouteHelper('/mocked-login-url');

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
        ->times(2)
        ->andReturn(false);

    swapRouteHelper('/mocked-login-url');

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

    swapRouteHelper('/mocked-login-url');

    $middleware = new Authenticate();

    // Act & Assert
    expect($middleware->redirectTo($mockRequest1))->toBe('/mocked-login-url'); // Non-JSON
    expect($middleware->redirectTo($mockRequest2))->toBeNull(); // JSON
    expect($middleware->redirectTo($mockRequest3))->toBe('/mocked-login-url'); // Non-JSON
});

afterEach(function () {
    Mockery::close();
    restoreRouteSwap();
});