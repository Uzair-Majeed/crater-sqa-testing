
<?php


use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Crater\Http\Middleware\RedirectIfAuthenticated;
use Crater\Providers\RouteServiceProvider;

/*
|--------------------------------------------------------------------------
| Test Helper Functions
|--------------------------------------------------------------------------
|
| These helper functions streamline common mocking patterns across tests.
|
*/

/**
 * Mocks the Auth facade's guard() and check() methods.
 *
 * @param bool $isAuthenticated Whether the user is considered authenticated.
 * @param string|null $guardName The name of the guard to mock (null for default).
 * @return void
 */
function mockAuthGuardCheck(bool $isAuthenticated, ?string $guardName = null)
{
    // Create a mock for the Guard contract
    $mockGuard = Mockery::mock(\Illuminate\Contracts\Auth\Guard::class);

    // Expect 'check()' to be called once on the mock guard and return the specified status
    $mockGuard->shouldReceive('check')
        ->once()
        ->andReturn($isAuthenticated);

    // Mock the Auth facade's 'guard()' method to return our mock guard
    // This ensures that Auth::guard($guardName)->check() works as expected.
    Auth::shouldReceive('guard')
        ->with($guardName) // Expect the specific guard or null for default
        ->once()
        ->andReturn($mockGuard);
}

test('it redirects authenticated users to the home page with default guard', function () {
    // Arrange
    $request = Request::create('/dashboard', 'GET');

    // Create a Mockery spy to track calls to the $next closure.
    // The actual closure passed to the middleware will delegate to this spy.
    $nextSpy = Mockery::spy();
    $nextClosure = function (Request $req) use ($nextSpy) {
        $nextSpy($req); // Record that the closure was called
        return 'Response from next middleware (should not be reached)'; // Dummy return
    };

    // Mock Auth facade to simulate an authenticated user with the default guard
    mockAuthGuardCheck(true, null);

    $middleware = new RedirectIfAuthenticated();

    // Act
    $response = $middleware->handle($request, $nextClosure);

    // Assert
    expect($response)
        ->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(url(RouteServiceProvider::HOME)); // Ensure redirect to home URL

    // Ensure the $next middleware in the pipeline was NOT called by checking the spy
    $nextSpy->shouldNotHaveBeenCalled();
});

test('it redirects authenticated users to the home page with a specific guard', function () {
    // Arrange
    $request = Request::create('/admin/login', 'GET');
    $guard = 'admin';

    $nextSpy = Mockery::spy();
    $nextClosure = function (Request $req) use ($nextSpy) {
        $nextSpy($req);
        return 'Response from next middleware (should not be reached)';
    };

    // Mock Auth facade for the specific guard
    mockAuthGuardCheck(true, $guard);

    $middleware = new RedirectIfAuthenticated();

    // Act
    $response = $middleware->handle($request, $nextClosure, $guard);

    // Assert
    expect($response)
        ->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(url(RouteServiceProvider::HOME));

    $nextSpy->shouldNotHaveBeenCalled();
});

test('it passes unauthenticated users to the next middleware with default guard', function () {
    // Arrange
    $request = Request::create('/public-page', 'GET');
    $expectedResponseFromNext = 'Response from the next middleware in the pipeline';

    // Create a Mockery spy and a real closure.
    // The closure will return the expected response and delegate call tracking to the spy.
    $nextSpy = Mockery::spy();
    $nextClosure = function (Request $req) use ($nextSpy, $expectedResponseFromNext) {
        $nextSpy($req); // Record the call with the request
        return $expectedResponseFromNext; // Return the expected response
    };

    // Mock Auth facade to simulate an unauthenticated user with the default guard
    mockAuthGuardCheck(false, null);

    $middleware = new RedirectIfAuthenticated();

    // Act
    $response = $middleware->handle($request, $nextClosure);

    // Assert
    expect($response)->toBe($expectedResponseFromNext); // Ensure the response from $next is returned

    // Ensure the $next middleware in the pipeline was called exactly once with the request
    $nextSpy->shouldHaveBeenCalled()->once()->with($request);
});

test('it passes unauthenticated users to the next middleware with a specific guard', function () {
    // Arrange
    $request = Request::create('/api/data', 'GET');
    $expectedResponseFromNext = ['data' => 'API payload'];
    $guard = 'api';

    $nextSpy = Mockery::spy();
    $nextClosure = function (Request $req) use ($nextSpy, $expectedResponseFromNext) {
        $nextSpy($req);
        return $expectedResponseFromNext;
    };

    // Mock Auth facade for the specific guard
    mockAuthGuardCheck(false, $guard);

    $middleware = new RedirectIfAuthenticated();

    // Act
    $response = $middleware->handle($request, $nextClosure, $guard);

    // Assert
    expect($response)->toBe($expectedResponseFromNext);

    $nextSpy->shouldHaveBeenCalled()->once()->with($request);
});


afterEach(function () {
    // Close Mockery after each test to prevent residual mocks affecting other tests
    Mockery::close();
});
