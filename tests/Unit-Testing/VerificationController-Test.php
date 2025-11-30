<?php

use Mockery as m;
use Crater\Http\Controllers\V1\Admin\Auth\VerificationController;
use Illuminate\Routing\PendingMiddleware; // Represents the return type of the middleware() method
//use ReflectionClass; // For accessing protected properties

// Helper function to access protected properties via Reflection
if (!function_exists('getProtectedProperty')) {
    function getProtectedProperty($object, $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}


test('constructor applies correct middleware with proper chaining', function () {
    // Mock the return values for chained middleware calls (.only())
    $pendingMiddlewareSigned = m::mock(PendingMiddleware::class);
    $pendingMiddlewareSigned->shouldReceive('only')
        ->once()
        ->with('verify')
        ->andReturnNull(); // The .only() method usually returns void or itself for chaining

    $pendingMiddlewareThrottle = m::mock(PendingMiddleware::class);
    $pendingMiddlewareThrottle->shouldReceive('only')
        ->once()
        ->with('verify', 'resend')
        ->andReturnNull();

    // Create a partial mock of the controller. This allows the real constructor to run,
    // while allowing us to mock/spy on its methods (like `middleware`).
    $controller = m::mock(VerificationController::class)->makePartial();

    // Expect the `middleware` method calls and provide the mocked return values for chaining
    $controller->shouldReceive('middleware')
        ->once()
        ->with('auth')
        ->andReturnSelf(); // `middleware('auth')` doesn't chain with .only(), so it can return itself

    $controller->shouldReceive('middleware')
        ->once()
        ->with('signed')
        ->andReturn($pendingMiddlewareSigned); // Returns our mock for .only('verify')

    $controller->shouldReceive('middleware')
        ->once()
        ->with('throttle:6,1')
        ->andReturn($pendingMiddlewareThrottle); // Returns our mock for .only('verify', 'resend')

    // Call the constructor on the partially mocked object
    $controller->__construct();

    // Mockery will automatically verify that `shouldReceive->once()` expectations were met when `m::close()` is called.
    m::close();
});

test('redirectTo property is correctly set to RouteServiceProvider::HOME', function () {
    $controller = new VerificationController();

    // Use the helper function to access the protected property
    $redirectTo = getProtectedProperty($controller, 'redirectTo');

    expect($redirectTo)->toBe(\Crater\Providers\RouteServiceProvider::HOME);
});

test('controller correctly uses VerifiesEmails trait methods', function () {
    $controller = new VerificationController();

    // The VerifiesEmails trait provides these methods.
    // Unit testing here verifies that the trait is indeed used by the controller
    // and its methods are available.
    expect(method_exists($controller, 'show'))->toBeTrue(); // Shows the verification notice
    expect(method_exists($controller, 'verify'))->toBeTrue(); // Handles the verification link
    expect(method_exists($controller, 'resend'))->toBeTrue(); // Resends the verification email
});
