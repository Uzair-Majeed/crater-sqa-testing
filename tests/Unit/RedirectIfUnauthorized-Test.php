<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Crater\Http\Middleware\RedirectIfUnauthorized;
use Illuminate\Routing\Redirector;
use Illuminate\Http\RedirectResponse;

// This will be called before each test to ensure a clean slate
beforeEach(function () {
    Mockery::close(); // Close any previous Mockery expectations
});

// This will be called after each test to verify and close mocks

test('it calls the next middleware if the user is authenticated with the default guard', function () {
    $request = Request::create('/dashboard', 'GET');
    $expectedResponse = 'next middleware response';

    // Mock Auth facade for the default guard
    Auth::shouldReceive('guard')
        ->with(null) // Expect default guard
        ->andReturnSelf(); // Allow chaining ->check()
    Auth::shouldReceive('check')
        ->once()
        ->andReturn(true); // Simulate authenticated user

    // Fix: Instead of mocking Closure::class (which is final),
    // provide a concrete closure that returns the expected response.
    // The main assertion `expect($response)->toBe($expectedResponse)` will verify it was called.
    $next = function (Request $req) use ($expectedResponse) {
        return $expectedResponse;
    };

    $middleware = new RedirectIfUnauthorized();

    $response = $middleware->handle($request, $next);

    expect($response)->toBe($expectedResponse);
});

test('it calls the next middleware if the user is authenticated with a specific guard', function () {
    $request = Request::create('/admin/dashboard', 'GET');
    $expectedResponse = 'next admin middleware response';
    $guard = 'admin';

    // Mock Auth facade for the specific guard
    Auth::shouldReceive('guard')
        ->with($guard)
        ->andReturnSelf();
    Auth::shouldReceive('check')
        ->once()
        ->andReturn(true); // Simulate authenticated user

    // Fix: Instead of mocking Closure::class,
    // provide a concrete closure that returns the expected response.
    $next = function (Request $req) use ($expectedResponse) {
        return $expectedResponse;
    };

    $middleware = new RedirectIfUnauthorized();

    $response = $middleware->handle($request, $next, $guard);

    expect($response)->toBe($expectedResponse);
});

test('it redirects to login if the user is unauthenticated with the default guard', function () {
    $request = Request::create('/dashboard', 'GET');

    // Mock Auth facade for the default guard
    Auth::shouldReceive('guard')
        ->with(null) // Expect default guard
        ->andReturnSelf();
    Auth::shouldReceive('check')
        ->once()
        ->andReturn(false); // Simulate unauthenticated user

    // Fix: Use Mockery::spy for the $next closure to assert it's NOT called.
    // The callable itself throws an exception if it's unexpectedly invoked.
    $next = Mockery::spy(function (Request $req) {
        throw new LogicException('The next middleware should not have been called for an unauthenticated user.');
    });

    // Mock the Redirector to control and verify the redirect() helper call
    $mockRedirectResponse = Mockery::mock(RedirectResponse::class);
    $mockRedirector = Mockery::mock(Redirector::class);
    $mockRedirector->shouldReceive('to')
                   ->once()
                   ->with('/login')
                   ->andReturn($mockRedirectResponse); // Simulate `redirect('/login')` return value

    // Bind the mock Redirector into the application container to override the helper
    app()->instance('redirect', $mockRedirector);

    $middleware = new RedirectIfUnauthorized();

    $response = $middleware->handle($request, $next);

    // Assert that the spy's __invoke method was never called.
    $next->shouldNotHaveReceived('__invoke');

    expect($response)->toBe($mockRedirectResponse);
});

test('it redirects to login if the user is unauthenticated with a specific guard', function () {
    $request = Request::create('/admin/dashboard', 'GET');
    $guard = 'admin';

    // Mock Auth facade for the specific guard
    Auth::shouldReceive('guard')
        ->with($guard)
        ->andReturnSelf();
    Auth::shouldReceive('check')
        ->once()
        ->andReturn(false); // Simulate unauthenticated user

    // Fix: Use Mockery::spy for the $next closure to assert it's NOT called.
    $next = Mockery::spy(function (Request $req) {
        throw new LogicException('The next middleware should not have been called for an unauthenticated user.');
    });

    // Mock the Redirector to control and verify the redirect() helper call
    $mockRedirectResponse = Mockery::mock(RedirectResponse::class);
    $mockRedirector = Mockery::mock(Redirector::class);
    $mockRedirector->shouldReceive('to')
                   ->once()
                   ->with('/login')
                   ->andReturn($mockRedirectResponse); // Simulate `redirect('/login')` return value

    // Bind the mock Redirector into the application container
    app()->instance('redirect', $mockRedirector);

    $middleware = new RedirectIfUnauthorized();

    $response = $middleware->handle($request, $next, $guard);

    // Assert that the spy's __invoke method was never called.
    $next->shouldNotHaveReceived('__invoke');

    expect($response)->toBe($mockRedirectResponse);
});


afterEach(function () {
    Mockery::close();
});