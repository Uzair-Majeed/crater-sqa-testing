<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
//use Closure;
use Crater\Http\Middleware\AdminMiddleware;


beforeEach(function () {
    Mockery::close(); // Ensure Mockery is fresh for each test
});

test('it allows access if user is super admin', function () {
    $middleware = new AdminMiddleware();

    $request = Mockery::mock(Request::class);
    // These should not be called if access is granted, as the execution flow does not reach the inner if block
    $request->shouldNotReceive('ajax');
    $request->shouldNotReceive('wantsJson');

    $next = Mockery::mock(Closure::class);
    $next->shouldReceive('__invoke')->once()->with($request)->andReturn('next_middleware_executed');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(false); // User is not a guest

    $authUser = Mockery::mock();
    $authUser->shouldReceive('isSuperAdminOrAdmin')->once()->andReturn(true); // User is super admin/admin

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with(null)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldReceive('user')
        ->andReturn($authUser);

    $response = $middleware->handle($request, $next, null);

    expect($response)->toBe('next_middleware_executed');
});

test('it allows access if user is admin', function () {
    $middleware = new AdminMiddleware();

    $request = Mockery::mock(Request::class);
    $request->shouldNotReceive('ajax');
    $request->shouldNotReceive('wantsJson');

    $next = Mockery::mock(Closure::class);
    $next->shouldReceive('__invoke')->once()->with($request)->andReturn('next_middleware_executed');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(false);

    $authUser = Mockery::mock();
    $authUser->shouldReceive('isSuperAdminOrAdmin')->once()->andReturn(true);

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with(null)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldReceive('user')
        ->andReturn($authUser);

    $response = $middleware->handle($request, $next, null);

    expect($response)->toBe('next_middleware_executed');
});

test('it allows access with a custom guard if user is authorized', function () {
    $middleware = new AdminMiddleware();
    $customGuard = 'custom_guard';

    $request = Mockery::mock(Request::class);
    $request->shouldNotReceive('ajax');
    $request->shouldNotReceive('wantsJson');

    $next = Mockery::mock(Closure::class);
    $next->shouldReceive('__invoke')->once()->with($request)->andReturn('next_middleware_executed');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(false);

    $authUser = Mockery::mock();
    $authUser->shouldReceive('isSuperAdminOrAdmin')->once()->andReturn(true);

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with($customGuard)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldReceive('user')
        ->andReturn($authUser);

    $response = $middleware->handle($request, $next, $customGuard);

    expect($response)->toBe('next_middleware_executed');
});

test('it denies access and returns 401 for guest ajax request', function () {
    $middleware = new AdminMiddleware();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('ajax')->once()->andReturn(true);
    $request->shouldNotReceive('wantsJson'); // Short-circuited if ajax is true

    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(true); // User is a guest

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with(null)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldNotReceive('user'); // Auth::user() is not called if guest() is true

    $response = $middleware->handle($request, $next, null);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe('Unauthorized.');
});

test('it denies access and returns 401 for guest wants json request', function () {
    $middleware = new AdminMiddleware();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('ajax')->once()->andReturn(false);
    $request->shouldReceive('wantsJson')->once()->andReturn(true);

    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(true);

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with(null)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldNotReceive('user');

    $response = $middleware->handle($request, $next, null);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe('Unauthorized.');
});

test('it denies access and returns 404 json for guest non-ajax/non-json request', function () {
    $middleware = new AdminMiddleware();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('ajax')->once()->andReturn(false);
    $request->shouldReceive('wantsJson')->once()->andReturn(false);

    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(true);

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with(null)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldNotReceive('user');

    $response = $middleware->handle($request, $next, null);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toBeJson()
        ->and(json_decode($response->getContent(), true))->toEqual(['error' => 'user_is_not_admin']);
});

test('it denies access and returns 401 for authenticated non-admin ajax request', function () {
    $middleware = new AdminMiddleware();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('ajax')->once()->andReturn(true);
    $request->shouldNotReceive('wantsJson');

    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(false); // User is authenticated

    $authUser = Mockery::mock();
    $authUser->shouldReceive('isSuperAdminOrAdmin')->once()->andReturn(false); // But not admin/super_admin

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with(null)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldReceive('user')
        ->andReturn($authUser);

    $response = $middleware->handle($request, $next, null);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe('Unauthorized.');
});

test('it denies access and returns 401 for authenticated non-admin wants json request', function () {
    $middleware = new AdminMiddleware();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('ajax')->once()->andReturn(false);
    $request->shouldReceive('wantsJson')->once()->andReturn(true);

    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(false);

    $authUser = Mockery::mock();
    $authUser->shouldReceive('isSuperAdminOrAdmin')->once()->andReturn(false);

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with(null)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldReceive('user')
        ->andReturn($authUser);

    $response = $middleware->handle($request, $next, null);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe('Unauthorized.');
});

test('it denies access and returns 404 json for authenticated non-admin non-ajax/non-json request', function () {
    $middleware = new AdminMiddleware();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('ajax')->once()->andReturn(false);
    $request->shouldReceive('wantsJson')->once()->andReturn(false);

    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(false);

    $authUser = Mockery::mock();
    $authUser->shouldReceive('isSuperAdminOrAdmin')->once()->andReturn(false);

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with(null)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldReceive('user')
        ->andReturn($authUser);

    $response = $middleware->handle($request, $next, null);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toBeJson()
        ->and(json_decode($response->getContent(), true))->toEqual(['error' => 'user_is_not_admin']);
});

test('it denies access with custom guard for guest ajax request', function () {
    $middleware = new AdminMiddleware();
    $customGuard = 'custom_guard';

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('ajax')->once()->andReturn(true);
    $request->shouldNotReceive('wantsJson');

    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(true);

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with($customGuard)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldNotReceive('user');

    $response = $middleware->handle($request, $next, $customGuard);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe('Unauthorized.');
});

test('it denies access with custom guard for authenticated non-admin non-ajax/non-json request', function () {
    $middleware = new AdminMiddleware();
    $customGuard = 'custom_guard';

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('ajax')->once()->andReturn(false);
    $request->shouldReceive('wantsJson')->once()->andReturn(false);

    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(false);

    $authUser = Mockery::mock();
    $authUser->shouldReceive('isSuperAdminOrAdmin')->once()->andReturn(false);

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with($customGuard)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldReceive('user')
        ->andReturn($authUser);

    $response = $middleware->handle($request, $next, $customGuard);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toBeJson()
        ->and(json_decode($response->getContent(), true))->toEqual(['error' => 'user_is_not_admin']);
});

test('it handles request as ajax if both ajax and wantsJson are true for unauthorized user', function () {
    $middleware = new AdminMiddleware();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('ajax')->once()->andReturn(true);
    $request->shouldNotReceive('wantsJson'); // The OR condition means wantsJson will not be called

    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(true); // Unauthorized condition met

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with(null)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldNotReceive('user');

    $response = $middleware->handle($request, $next, null);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe('Unauthorized.');
});

test('it handles non-boolean falsy return from isSuperAdminOrAdmin as unauthorized', function () {
    $middleware = new AdminMiddleware();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('ajax')->once()->andReturn(false);
    $request->shouldReceive('wantsJson')->once()->andReturn(false);

    $next = Mockery::mock(Closure::class);
    $next->shouldNotReceive('__invoke');

    $authGuard = Mockery::mock();
    $authGuard->shouldReceive('guest')->once()->andReturn(false); // User is authenticated

    $authUser = Mockery::mock();
    $authUser->shouldReceive('isSuperAdminOrAdmin')->once()->andReturn(0); // Falsy value

    Mockery::mock('alias:Auth')
        ->shouldReceive('guard')
        ->with(null)
        ->andReturn($authGuard);

    Mockery::mock('alias:Auth')
        ->shouldReceive('user')
        ->andReturn($authUser);

    $response = $middleware->handle($request, $next, null);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toBeJson()
        ->and(json_decode($response->getContent(), true))->toEqual(['error' => 'user_is_not_admin']);
});




afterEach(function () {
    Mockery::close();
});
