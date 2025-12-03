<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Crater\Http\Middleware\CustomerPortalMiddleware;

it('throws an error if the user is null and enable_portal is accessed', function () {
    // Arrange
    $mockGuard = Mockery::mock(\Illuminate\Contracts\Auth\Guard::class);
    $mockGuard->shouldReceive('user')->andReturn(null);

    Auth::shouldReceive('guard')
        ->with('customer')
        ->andReturn($mockGuard);

    $request = Request::create('/portal', 'GET');
    $next = fn ($request) => new Response('Next called', 200); // Should not be called

    $middleware = new CustomerPortalMiddleware();

    // Act & Assert
    // Expect an ErrorException or Error, since null->enable_portal will be accessed
    $this->expectException(ErrorException::class);
    $this->expectExceptionMessageMatches('/Attempt to read property "enable_portal" on null/');
    $middleware->handle($request, $next);
});

it('logs out and returns unauthorized if customer portal is disabled', function () {
    // Arrange
    $user = (object)['enable_portal' => false]; // Using stdClass for the user object

    $mockGuard = Mockery::mock(\Illuminate\Contracts\Auth\Guard::class);
    $mockGuard->shouldReceive('user')->andReturn($user);
    $mockGuard->shouldReceive('logout')->once(); // Expect logout to be called

    Auth::shouldReceive('guard')
        ->with('customer')
        ->andReturn($mockGuard);

    $request = Request::create('/portal', 'GET');

    // The next closure should not be called in this scenario
    $nextWasCalled = false;
    $next = function () use (&$nextWasCalled) {
        $nextWasCalled = true;
        return new Response('Should not be called', 200);
    };

    $middleware = new CustomerPortalMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe('Unauthorized.')
        ->and($nextWasCalled)->toBeFalse(); // Ensure $next is NOT called
});

it('proceeds to the next middleware if customer portal is enabled', function () {
    // Arrange
    $user = (object)['enable_portal' => true]; // Using stdClass for the user object

    $mockGuard = Mockery::mock(\Illuminate\Contracts\Auth\Guard::class);
    $mockGuard->shouldReceive('user')->andReturn($user);
    $mockGuard->shouldNotReceive('logout'); // Ensure logout is NOT called

    Auth::shouldReceive('guard')
        ->with('customer')
        ->andReturn($mockGuard);

    $request = Request::create('/portal', 'GET');
    $expectedNextResponse = new Response('Authorized and proceeded', 200);

    $nextWasCalled = false;
    $next = function ($arg) use ($request, &$nextWasCalled, $expectedNextResponse) {
        expect($arg)->toBe($request);
        $nextWasCalled = true;
        return $expectedNextResponse;
    };

    $middleware = new CustomerPortalMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBeInstanceOf(Response::class)
        ->and($response)->toBe($expectedNextResponse) // Ensure the exact response from $next is returned
        ->and($nextWasCalled)->toBeTrue(); // Ensure $next WAS called
});

afterEach(function () {
    Mockery::close();
});