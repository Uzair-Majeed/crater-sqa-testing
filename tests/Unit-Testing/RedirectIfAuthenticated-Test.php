<?php


use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Crater\Http\Middleware\RedirectIfAuthenticated;
use Crater\Providers\RouteServiceProvider;

test('it redirects authenticated users to the home page with default guard', function () {
    // Arrange
    $request = Request::create('/dashboard', 'GET');
    $next = Mockery::spy(Closure::class);

    // Mock Auth facade to simulate an authenticated user
    Auth::shouldReceive('guard')
        ->with(null) // Expect default guard
        ->andReturnSelf(); // Allow chaining methods

    Auth::shouldReceive('check')
        ->once() // Expect check() to be called once
        ->andReturn(true); // User is authenticated

    $middleware = new RedirectIfAuthenticated();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)
        ->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(url(RouteServiceProvider::HOME)); // Ensure redirect to home URL

    // Ensure the $next middleware in the pipeline was NOT called
    $next->shouldNotHaveBeenCalled();
});

test('it redirects authenticated users to the home page with a specific guard', function () {
    // Arrange
    $request = Request::create('/admin/login', 'GET');
    $next = Mockery::spy(Closure::class);
    $guard = 'admin';

    // Mock Auth facade for the specific guard
    Auth::shouldReceive('guard')
        ->with($guard) // Expect the 'admin' guard
        ->andReturnSelf();

    Auth::shouldReceive('check')
        ->once()
        ->andReturn(true); // User is authenticated under the 'admin' guard

    $middleware = new RedirectIfAuthenticated();

    // Act
    $response = $middleware->handle($request, $next, $guard);

    // Assert
    expect($response)
        ->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(url(RouteServiceProvider::HOME));

    $next->shouldNotHaveBeenCalled();
});

test('it passes unauthenticated users to the next middleware with default guard', function () {
    // Arrange
    $request = Request::create('/public-page', 'GET');
    $expectedResponseFromNext = 'Response from the next middleware in the pipeline';

    // Mock the $next closure to return a specific response and assert arguments
    $next = Mockery::spy(function ($req) use ($request, $expectedResponseFromNext) {
        expect($req)->toBe($request); // Ensure the original request object is passed
        return $expectedResponseFromNext;
    });

    // Mock Auth facade to simulate an unauthenticated user
    Auth::shouldReceive('guard')
        ->with(null) // Expect default guard
        ->andReturnSelf();

    Auth::shouldReceive('check')
        ->once()
        ->andReturn(false); // User is NOT authenticated

    $middleware = new RedirectIfAuthenticated();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe($expectedResponseFromNext); // Ensure the response from $next is returned

    // Ensure the $next middleware in the pipeline was called exactly once with the request
    $next->shouldHaveBeenCalled()->once()->with($request);
});

test('it passes unauthenticated users to the next middleware with a specific guard', function () {
    // Arrange
    $request = Request::create('/api/data', 'GET');
    $expectedResponseFromNext = ['data' => 'API payload'];
    $guard = 'api';

    $next = Mockery::spy(function ($req) use ($request, $expectedResponseFromNext) {
        expect($req)->toBe($request);
        return $expectedResponseFromNext;
    });

    // Mock Auth facade for the specific guard
    Auth::shouldReceive('guard')
        ->with($guard) // Expect the 'api' guard
        ->andReturnSelf();

    Auth::shouldReceive('check')
        ->once()
        ->andReturn(false); // User is NOT authenticated under the 'api' guard

    $middleware = new RedirectIfAuthenticated();

    // Act
    $response = $middleware->handle($request, $next, $guard);

    // Assert
    expect($response)->toBe($expectedResponseFromNext);

    $next->shouldHaveBeenCalled()->once()->with($request);
});




afterEach(function () {
    Mockery::close();
});
