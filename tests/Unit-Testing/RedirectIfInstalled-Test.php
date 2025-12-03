<?php

use Crater\Http\Middleware\RedirectIfInstalled;
use Crater\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;


beforeEach(function () {
    // Ensure mocks are cleaned up before each test
    Mockery::close();

    // Mock the Storage facade's static methods
    Storage::shouldReceive('disk')->andReturnSelf();

    // Mock the Setting model's static methods
    Mockery::mock('alias:' . Setting::class);
});

test('it calls next middleware if the application is not installed', function () {
    // Arrange: Simulate 'database_created' file not existing
    Storage::shouldReceive('has')->with('database_created')->andReturn(false);

    $request = Mockery::mock(Request::class);
    // Arrange: Spy on the $next closure to assert its invocation
    $next = Mockery::spy(Closure::class);
    $next->shouldReceive('__invoke')->with($request)->andReturn('next_response');

    $middleware = new RedirectIfInstalled();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('next_response');
    $next->shouldHaveReceived('__invoke')->once()->with($request);
});

test('it calls next middleware if installed but profile is not complete', function () {
    // Arrange: Simulate 'database_created' file existing
    Storage::shouldReceive('has')->with('database_created')->andReturn(true);
    // Arrange: Simulate profile_complete setting being something other than 'COMPLETED'
    Setting::shouldReceive('getSetting')->with('profile_complete')->andReturn('PENDING');

    $request = Mockery::mock(Request::class);
    $next = Mockery::spy(Closure::class);
    $next->shouldReceive('__invoke')->with($request)->andReturn('next_response');

    $middleware = new RedirectIfInstalled();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('next_response');
    $next->shouldHaveReceived('__invoke')->once()->with($request);
});

test('it calls next middleware if installed but profile_complete setting is null', function () {
    // Arrange: Simulate 'database_created' file existing
    Storage::shouldReceive('has')->with('database_created')->andReturn(true);
    // Arrange: Simulate profile_complete setting being null
    Setting::shouldReceive('getSetting')->with('profile_complete')->andReturn(null);

    $request = Mockery::mock(Request::class);
    $next = Mockery::spy(Closure::class);
    $next->shouldReceive('__invoke')->with($request)->andReturn('next_response');

    $middleware = new RedirectIfInstalled();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('next_response');
    $next->shouldHaveReceived('__invoke')->once()->with($request);
});

test('it calls next middleware if installed but profile_complete setting is an empty string', function () {
    // Arrange: Simulate 'database_created' file existing
    Storage::shouldReceive('has')->with('database_created')->andReturn(true);
    // Arrange: Simulate profile_complete setting being an empty string
    Setting::shouldReceive('getSetting')->with('profile_complete')->andReturn('');

    $request = Mockery::mock(Request::class);
    $next = Mockery::spy(Closure::class);
    $next->shouldReceive('__invoke')->with($request)->andReturn('next_response');

    $middleware = new RedirectIfInstalled();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('next_response');
    $next->shouldHaveReceived('__invoke')->once()->with($request);
});

test('it redirects to login if installed and profile is complete', function () {
    // Arrange: Simulate 'database_created' file existing
    Storage::shouldReceive('has')->with('database_created')->andReturn(true);
    // Arrange: Simulate profile_complete setting being 'COMPLETED'
    Setting::shouldReceive('getSetting')->with('profile_complete')->andReturn('COMPLETED');

    $request = Mockery::mock(Request::class);
    // Arrange: The $next closure should not be called in this case
    $next = Mockery::spy(Closure::class);

    $middleware = new RedirectIfInstalled();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert: Expect a RedirectResponse to 'login'
    expect($response)
        ->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe('login');
    $next->shouldNotHaveReceived('__invoke');
});




afterEach(function () {
    Mockery::close();
});
