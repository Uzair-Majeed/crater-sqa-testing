```php
<?php

use Crater\Http\Middleware\RedirectIfInstalled;
use Crater\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration; // Good practice, though Pest usually handles this implicitly.


// FIX: Move the alias mock creation for `Setting` to the file scope.
// This ensures the alias is established only once for all tests in this file.
// The "class already exists" error occurs because `beforeEach` was trying to create
// the alias mock repeatedly for each test, which fails if the class (or its alias)
// is already loaded by PHP's autoloader in a prior test run.
// `Mockery::close()` in `beforeEach` and `afterEach` will correctly reset expectations
// on this alias mock for each test, but the alias itself will persist, preventing the error.
Mockery::mock('alias:' . Setting::class);


beforeEach(function () {
    // Ensure mocks are cleaned up before each test.
    // This will reset expectations on the global Setting alias mock and other mocks like Storage.
    Mockery::close();

    // Mock the Storage facade's static methods
    Storage::shouldReceive('disk')->andReturnSelf();
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
```