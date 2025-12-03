<?php

use Crater\Http\Middleware\CronJobMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Mockery::close();
});

test('it allows access with correct authorization token', function () {
    $expectedToken = 'super_secret_cron_token';
    Config::set('services.cron_job.auth_token', $expectedToken);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
            ->once()
            ->with('x-authorization-token')
            ->andReturn($expectedToken);

    $next = Mockery::mock(\Closure::class);
    $next->shouldReceive('__invoke')
         ->once()
         ->with($request)
         ->andReturn('allowed_response_from_controller');

    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);

    expect($response)->toBe('allowed_response_from_controller');
});

test('it denies access if authorization token header is missing', function () {
    Config::set('services.cron_job.auth_token', 'super_secret_cron_token');

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
            ->once()
            ->with('x-authorization-token')
            ->andReturn(null); // Header is explicitly null/missing

    $next = Mockery::mock(\Closure::class);
    $next->shouldNotReceive('__invoke');

    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe(json_encode(['unauthorized']));
});

test('it denies access if authorization token is incorrect', function () {
    Config::set('services.cron_job.auth_token', 'correct_token');

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
            ->once()
            ->with('x-authorization-token')
            ->andReturn('incorrect_token'); // Header has a value, but it's wrong

    $next = Mockery::mock(\Closure::class);
    $next->shouldNotReceive('__invoke');

    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe(json_encode(['unauthorized']));
});

test('it denies access if header token is an empty string', function () {
    Config::set('services.cron_job.auth_token', 'super_secret_cron_token');

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
            ->once()
            ->with('x-authorization-token')
            ->andReturn(''); // Header is an empty string

    $next = Mockery::mock(\Closure::class);
    $next->shouldNotReceive('__invoke');

    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe(json_encode(['unauthorized']));
});

test('it denies access if config token is an empty string and header is present', function () {
    Config::set('services.cron_job.auth_token', ''); // Config token is empty string

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
            ->once()
            ->with('x-authorization-token')
            ->andReturn('some_token_from_request');

    $next = Mockery::mock(\Closure::class);
    $next->shouldNotReceive('__invoke');

    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe(json_encode(['unauthorized']));
});

test('it denies access if config token is null', function () {
    Config::set('services.cron_job.auth_token', null); // Config token is null

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
            ->once()
            ->with('x-authorization-token')
            ->andReturn('some_token_from_request');

    $next = Mockery::mock(\Closure::class);
    $next->shouldNotReceive('__invoke');

    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe(json_encode(['unauthorized']));
});

test('it denies access if both header token and config token are empty strings', function () {
    Config::set('services.cron_job.auth_token', ''); // Config token is empty string

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
            ->once()
            ->with('x-authorization-token')
            ->andReturn(''); // Header token is empty string

    $next = Mockery::mock(\Closure::class);
    $next->shouldNotReceive('__invoke');

    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe(json_encode(['unauthorized']));
});

test('it denies access if config key is not set at all', function () {
    // Ensure the config key is not present or explicitly null
    Config::set('services.cron_job.auth_token', null);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
            ->once()
            ->with('x-authorization-token')
            ->andReturn('some_valid_looking_token'); // Header present

    $next = Mockery::mock(\Closure::class);
    $next->shouldNotReceive('__invoke');

    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe(json_encode(['unauthorized']));
});
 

afterEach(function () {
    Mockery::close();
});
