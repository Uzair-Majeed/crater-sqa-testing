<?php

use Crater\Providers\RouteServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Mockery\MockInterface;

// Setup a clean environment for each test
beforeEach(function () {
    // Clear Mockery mocks before each test
    Mockery::close();

    // Ensure the application instance is bound for RateLimiter and Route facades
    $app = new Application();
    RateLimiter::setFacadeApplication($app);
    Route::setFacadeApplication($app);
});

// Test constants
test('HOME constant is correctly defined', function () {
    expect(RouteServiceProvider::HOME)->toBe('/admin/dashboard');
});

test('CUSTOMER_HOME constant is correctly defined', function () {
    expect(RouteServiceProvider::CUSTOMER_HOME)->toBe('/customer/dashboard');
});

// Test configureRateLimiting method
test('configureRateLimiting sets up the API rate limiter correctly', function () {
    $capturedCallback = null;

    /** @var MockInterface|RateLimiter $rateLimiterMock */
    RateLimiter::shouldReceive('for')
        ->once()
        ->with('api', \Mockery::type(\Closure::class))
        ->andReturnUsing(function ($name, $callback) use (&$capturedCallback) {
            // Capture the callback to test its behavior
            $capturedCallback = $callback;
            return null; // Return value of `for` doesn't matter for this test
        });

    // Instantiate the service provider
    $provider = new RouteServiceProvider(new Application());

    // Access the protected method using reflection
    $method = new ReflectionMethod($provider, 'configureRateLimiting');
    $method->setAccessible(true);
    $method->invoke($provider);

    // Assert the callback was captured
    expect($capturedCallback)->toBeInstanceOf(\Closure::class);

    // Test the captured callback's return value
    $request = Mockery::mock(Request::class);
    $limit = call_user_func($capturedCallback, $request);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->decayMinutes)->toBe(1) // perMinute means 1 minute
        ->and($limit->maxAttempts)->toBe(60);
});

// Test boot method's overall flow and interaction with dependencies
test('boot method calls configureRateLimiting and registers routes', function () {
    // Mock RateLimiter to ensure configureRateLimiting is called
    RateLimiter::shouldReceive('for')->once()->with('api', \Mockery::type(\Closure::class))->andReturn(null);

    // Mock Route facade methods for chaining
    $routeRegistrarApiMock = Mockery::mock(\stdClass::class);
    $routeRegistrarWebMock = Mockery::mock(\stdClass::class);

    // Expectations for API routes
    Route::shouldReceive('prefix')
        ->once()
        ->with('api')
        ->andReturn($routeRegistrarApiMock);

    $routeRegistrarApiMock->shouldReceive('middleware')
        ->once()
        ->with('api')
        ->andReturn($routeRegistrarApiMock);

    // The 'namespace' property is commented out in the provided code, so it's null by default
    $routeRegistrarApiMock->shouldReceive('namespace')
        ->once()
        ->with(null) // Expecting null namespace
        ->andReturn($routeRegistrarApiMock);

    $routeRegistrarApiMock->shouldReceive('group')
        ->once()
        ->with(\Mockery::pattern('/.*routes\/api\.php/')) // Expect path ending with 'routes/api.php'
        ->andReturn(null);

    // Expectations for WEB routes
    Route::shouldReceive('middleware')
        ->once()
        ->with('web')
        ->andReturn($routeRegistrarWebMock);

    // The 'namespace' property is commented out in the provided code, so it's null by default
    $routeRegistrarWebMock->shouldReceive('namespace')
        ->once()
        ->with(null) // Expecting null namespace
        ->andReturn($routeRegistrarWebMock);

    $routeRegistrarWebMock->shouldReceive('group')
        ->once()
        ->with(\Mockery::pattern('/.*routes\/web\.php/')) // Expect path ending with 'routes/web.php'
        ->andReturn(null);

    // Create a partial mock of RouteServiceProvider to capture the closure passed to the 'routes' method
    $provider = Mockery::mock(RouteServiceProvider::class, [new Application()])
        ->makePartial();

    $capturedRoutesClosure = null;
    $provider->shouldReceive('routes')
        ->once()
        ->andReturnUsing(function (\Closure $closure) use (&$capturedRoutesClosure) {
            $capturedRoutesClosure = $closure;
        });

    // Call the boot method
    $provider->boot();

    // Assert the routes closure was captured
    expect($capturedRoutesClosure)->toBeInstanceOf(\Closure::class);

    // Execute the captured closure to trigger Route facade interactions
    $capturedRoutesClosure();
});

// Edge case: Test boot method with a custom namespace explicitly set
test('boot method registers routes with custom namespace if protected namespace property is set', function () {
    // Mock RateLimiter as before
    RateLimiter::shouldReceive('for')->once()->with('api', \Mockery::type(\Closure::class))->andReturn(null);

    $customNamespace = 'Crater\\Http\\Controllers\\Custom';

    // Create a partial mock for the provider
    $provider = Mockery::mock(RouteServiceProvider::class, [new Application()])
        ->makePartial();

    // Set the protected 'namespace' property using reflection
    $reflector = new ReflectionClass($provider);
    $property = $reflector->getProperty('namespace');
    $property->setAccessible(true);
    $property->setValue($provider, $customNamespace);

    // Mock Route facade methods for chaining with custom namespace
    $routeRegistrarApiMock = Mockery::mock(\stdClass::class);
    $routeRegistrarWebMock = Mockery::mock(\stdClass::class);

    Route::shouldReceive('prefix')->once()->with('api')->andReturn($routeRegistrarApiMock);
    $routeRegistrarApiMock->shouldReceive('middleware')->once()->with('api')->andReturn($routeRegistrarApiMock);
    $routeRegistrarApiMock->shouldReceive('namespace')->once()->with($customNamespace)->andReturn($routeRegistrarApiMock);
    $routeRegistrarApiMock->shouldReceive('group')->once()->with(\Mockery::pattern('/.*routes\/api\.php/'))->andReturn(null);

    Route::shouldReceive('middleware')->once()->with('web')->andReturn($routeRegistrarWebMock);
    $routeRegistrarWebMock->shouldReceive('namespace')->once()->with($customNamespace)->andReturn($routeRegistrarWebMock);
    $routeRegistrarWebMock->shouldReceive('group')->once()->with(\Mockery::pattern('/.*routes\/web\.php/'))->andReturn(null);

    // Capture the routes closure
    $capturedRoutesClosure = null;
    $provider->shouldReceive('routes')
        ->once()
        ->andReturnUsing(function (\Closure $closure) use (&$capturedRoutesClosure) {
            $capturedRoutesClosure = $closure;
        });

    // Call boot
    $provider->boot();

    expect($capturedRoutesClosure)->toBeInstanceOf(\Closure::class);
    // Execute the captured closure to verify interactions with the custom namespace
    $capturedRoutesClosure();
});

// Test that `group` methods are called with string paths even if `base_path` behaved unusually
test('boot method calls group with string paths for api and web routes', function () {
    RateLimiter::shouldReceive('for')->once()->andReturn(null);

    $routeRegistrarApiMock = Mockery::mock(\stdClass::class);
    $routeRegistrarWebMock = Mockery::mock(\stdClass::class);

    Route::shouldReceive('prefix')->andReturn($routeRegistrarApiMock);
    $routeRegistrarApiMock->shouldReceive('middleware')->andReturn($routeRegistrarApiMock);
    $routeRegistrarApiMock->shouldReceive('namespace')->andReturn($routeRegistrarApiMock);
    $routeRegistrarApiMock->shouldReceive('group')
        ->once()
        ->with(\Mockery::type('string')) // Just ensure it's called with a string
        ->andReturn(null);

    Route::shouldReceive('middleware')->andReturn($routeRegistrarWebMock);
    $routeRegistrarWebMock->shouldReceive('namespace')->andReturn($routeRegistrarWebMock);
    $routeRegistrarWebMock->shouldReceive('group')
        ->once()
        ->with(\Mockery::type('string')) // Just ensure it's called with a string
        ->andReturn(null);

    $provider = Mockery::mock(RouteServiceProvider::class, [new Application()])->makePartial();
    $capturedRoutesClosure = null;
    $provider->shouldReceive('routes')
        ->once()
        ->andReturnUsing(function (\Closure $closure) use (&$capturedRoutesClosure) {
            $capturedRoutesClosure = $closure;
        });

    $provider->boot();
    $capturedRoutesClosure();
});




afterEach(function () {
    Mockery::close();
});
