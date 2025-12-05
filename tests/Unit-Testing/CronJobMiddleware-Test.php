<?php

namespace Tests\Unit;

use Crater\Http\Middleware\CronJobMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;

// Test the CronJobMiddleware without custom Request class
// We'll use the actual Request class and set headers via constructor

test('it allows access with correct authorization token', function () {
    $expectedToken = 'super_secret_cron_token';
    Config::set('services.cron_job.auth_token', $expectedToken);
    
    // Create request with headers
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', $expectedToken);
    
    $next = function ($req) {
        return 'allowed_response_from_controller';
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBe('allowed_response_from_controller');
});

test('it denies access if authorization token header is missing', function () {
    Config::set('services.cron_job.auth_token', 'super_secret_cron_token');
    
    $request = Request::create('/', 'GET');
    // Don't set the header at all
    
    $next = function ($req) {
        throw new \Exception('Next closure should not be invoked');
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getData())->toBe(['unauthorized']);
});

test('it denies access if authorization token is incorrect', function () {
    Config::set('services.cron_job.auth_token', 'correct_token');
    
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', 'incorrect_token');
    
    $next = function ($req) {
        throw new \Exception('Next closure should not be invoked');
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getData())->toBe(['unauthorized']);
});

test('it denies access if header token is an empty string', function () {
    Config::set('services.cron_job.auth_token', 'super_secret_cron_token');
    
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', '');
    
    $next = function ($req) {
        throw new \Exception('Next closure should not be invoked');
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getData())->toBe(['unauthorized']);
});

test('it denies access if config token is an empty string and header is present', function () {
    Config::set('services.cron_job.auth_token', '');
    
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', 'some_token_from_request');
    
    $next = function ($req) {
        throw new \Exception('Next closure should not be invoked');
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getData())->toBe(['unauthorized']);
});

test('it denies access if config token is null', function () {
    Config::set('services.cron_job.auth_token', null);
    
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', 'some_token_from_request');
    
    $next = function ($req) {
        throw new \Exception('Next closure should not be invoked');
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getData())->toBe(['unauthorized']);
});

test('it denies access if both header token and config token are empty strings', function () {
    Config::set('services.cron_job.auth_token', '');
    
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', '');
    
    $next = function ($req) {
        throw new \Exception('Next closure should not be invoked');
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getData())->toBe(['unauthorized']);
});

test('it denies access if config key is not set at all', function () {
    // Remove the config key entirely
    Config::offsetUnset('services.cron_job.auth_token');
    
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', 'some_valid_looking_token');
    
    $next = function ($req) {
        throw new \Exception('Next closure should not be invoked');
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getData())->toBe(['unauthorized']);
});

test('it passes the request to next middleware when authorized', function () {
    Config::set('services.cron_job.auth_token', 'test_token');
    
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', 'test_token');
    
    $nextCalled = false;
    $passedRequest = null;
    
    $next = function ($req) use (&$nextCalled, &$passedRequest) {
        $nextCalled = true;
        $passedRequest = $req;
        return 'next_response';
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($nextCalled)->toBeTrue()
        ->and($passedRequest)->toBe($request)
        ->and($response)->toBe('next_response');
});

test('it handles case-insensitive header names', function () {
    Config::set('services.cron_job.auth_token', 'test_token');
    
    // Test with different case variations
    $testCases = [
        'X-Authorization-Token',
        'X-AUTHORIZATION-TOKEN',
        'x-authorization-token',
        'X-Authorization-Token',
    ];
    
    foreach ($testCases as $headerName) {
        $request = Request::create('/', 'GET');
        $request->headers->set($headerName, 'test_token');
        
        $next = function ($req) {
            return 'allowed';
        };
        
        $middleware = new CronJobMiddleware();
        $response = $middleware->handle($request, $next);
        
        expect($response)->toBe('allowed');
    }
});

test('it returns JSON response with 401 status on unauthorized', function () {
    Config::set('services.cron_job.auth_token', 'test_token');
    
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', 'wrong_token');
    
    $next = function ($req) {
        throw new \Exception('Should not be called');
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->headers->get('Content-Type'))->toContain('application/json')
        ->and($response->getData())->toBe(['unauthorized']);
});

test('it handles special characters in tokens', function () {
    $specialToken = 'token@#$%^&*()_+-=[]{}|;:,.<>?';
    Config::set('services.cron_job.auth_token', $specialToken);
    
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', $specialToken);
    
    $next = function ($req) {
        return 'allowed';
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBe('allowed');
});

test('it handles very long tokens', function () {
    $longToken = str_repeat('a', 1000);
    Config::set('services.cron_job.auth_token', $longToken);
    
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', $longToken);
    
    $next = function ($req) {
        return 'allowed';
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBe('allowed');
});

test('it handles whitespace in tokens', function () {
    Config::set('services.cron_job.auth_token', 'token with spaces');
    
    $request = Request::create('/', 'GET');
    $request->headers->set('x-authorization-token', 'token with spaces');
    
    $next = function ($req) {
        return 'allowed';
    };
    
    $middleware = new CronJobMiddleware();
    $response = $middleware->handle($request, $next);
    
    expect($response)->toBe('allowed');
});

test('it is strict about token matching', function () {
    Config::set('services.cron_job.auth_token', 'exact_token');
    
    // These should all fail
    $invalidTokens = [
        'Exact_Token', // different case
        'exact_token ', // trailing space
        ' exact_token', // leading space
        'exact token', // missing underscore
        'exact_token_extra', // extra characters
    ];
    
    foreach ($invalidTokens as $invalidToken) {
        $request = Request::create('/', 'GET');
        $request->headers->set('x-authorization-token', $invalidToken);
        
        $next = function ($req) {
            throw new \Exception('Should not be called');
        };
        
        $middleware = new CronJobMiddleware();
        $response = $middleware->handle($request, $next);
        
        expect($response->getStatusCode())->toBe(401);
    }
});

test('it works with different HTTP methods', function () {
    Config::set('services.cron_job.auth_token', 'test_token');
    
    $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
    
    foreach ($methods as $method) {
        $request = Request::create('/', $method);
        $request->headers->set('x-authorization-token', 'test_token');
        
        $next = function ($req) {
            return 'allowed';
        };
        
        $middleware = new CronJobMiddleware();
        $response = $middleware->handle($request, $next);
        
        expect($response)->toBe('allowed');
    }
});

test('middleware can be instantiated without errors', function () {
    $middleware = new CronJobMiddleware();
    
    expect($middleware)->toBeInstanceOf(CronJobMiddleware::class);
});

// Clean up after tests
afterEach(function () {
    // Clear config to prevent test pollution
    Config::offsetUnset('services.cron_job.auth_token');
});