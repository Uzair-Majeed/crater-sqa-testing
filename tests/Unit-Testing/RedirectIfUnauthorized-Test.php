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

    // Mock the $next closure, expecting it to be called with the request
    $next = Mockery::mock(Closure::class);
    $next->shouldReceive('__invoke')
         ->once()
         ->with($request)
         ->andReturn($expectedResponse);

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

    // Mock the $next closure, expecting it to be called with the request
    $next = Mockery::mock(Closure::class);
    $next->shouldReceive('__invoke')
         ->once()
         ->with($request)
         ->andReturn($expectedResponse);

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

    // Mock the $next closure, expecting it NOT to be called
    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

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

    // Mock the $next closure, expecting it NOT to be called
    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

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

    expect($response)->toBe($mockRedirectResponse);
});




afterEach(function () {
    Mockery::close();
});
