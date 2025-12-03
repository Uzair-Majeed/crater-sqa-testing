<?php

use Crater\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Crater\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Crater\Http\Middleware\TrustProxies;
use Crater\Http\Middleware\ConfigMiddleware;
use Fruitcake\Cors\HandleCors;
use Crater\Http\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\Middleware\AuthenticateSession as IlluminateAuthenticateSession; // Alias to avoid conflict with Crater\Http\Middleware\Authenticate
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Crater\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Crater\Http\Middleware\Authenticate;
use Crater\Http\Middleware\ScopeBouncer;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Crater\Http\Middleware\RedirectIfAuthenticated;
use Crater\Http\Middleware\CustomerRedirectIfAuthenticated;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Crater\Http\Middleware\InstallationMiddleware;
use Crater\Http\Middleware\RedirectIfInstalled;
use Crater\Http\Middleware\RedirectIfUnauthorized;
use Crater\Http\Middleware\CustomerGuest;
use Crater\Http\Middleware\CompanyMiddleware;
use Crater\Http\Middleware\PdfMiddleware;
use Crater\Http\Middleware\CronJobMiddleware;
use Crater\Http\Middleware\CustomerPortalMiddleware;

test('global middleware stack is correctly defined', function () {
    $kernel = new Kernel(app(), app(\Illuminate\Contracts\Routing\Registrar::class));
    $reflection = new ReflectionClass($kernel);
    $middlewareProperty = $reflection->getProperty('middleware');
    $middlewareProperty->setAccessible(true);
    $globalMiddleware = $middlewareProperty->getValue($kernel);

    expect($globalMiddleware)->toBeArray()
        ->and($globalMiddleware)->toHaveCount(7)
        ->and($globalMiddleware)->toEqual([
            CheckForMaintenanceMode::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
            TrustProxies::class,
            ConfigMiddleware::class,
            HandleCors::class,
        ]);
});

test('web middleware group is correctly defined', function () {
    $kernel = new Kernel(app(), app(\Illuminate\Contracts\Routing\Registrar::class));
    $reflection = new ReflectionClass($kernel);
    $middlewareGroupsProperty = $reflection->getProperty('middlewareGroups');
    $middlewareGroupsProperty->setAccessible(true);
    $middlewareGroups = $middlewareGroupsProperty->getValue($kernel);

    expect($middlewareGroups)->toBeArray()
        ->and($middlewareGroups)->toHaveKey('web');
    
    expect($middlewareGroups['web'])->toBeArray()
        ->and($middlewareGroups['web'])->toHaveCount(6)
        ->and($middlewareGroups['web'])->toEqual([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class, // This line is commented out in the source
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ]);
});

test('api middleware group is correctly defined', function () {
    $kernel = new Kernel(app(), app(\Illuminate\Contracts\Routing\Registrar::class));
    $reflection = new ReflectionClass($kernel);
    $middlewareGroupsProperty = $reflection->getProperty('middlewareGroups');
    $middlewareGroupsProperty->setAccessible(true);
    $middlewareGroups = $middlewareGroupsProperty->getValue($kernel);

    expect($middlewareGroups)->toBeArray()
        ->and($middlewareGroups)->toHaveKey('api');

    expect($middlewareGroups['api'])->toBeArray()
        ->and($middlewareGroups['api'])->toHaveCount(3)
        ->and($middlewareGroups['api'])->toEqual([
            EnsureFrontendRequestsAreStateful::class,
            'throttle:180,1',
            SubstituteBindings::class,
        ]);
});

test('route middleware definitions are correct', function () {
    $kernel = new Kernel(app(), app(\Illuminate\Contracts\Routing\Registrar::class));
    $reflection = new ReflectionClass($kernel);
    $routeMiddlewareProperty = $reflection->getProperty('routeMiddleware');
    $routeMiddlewareProperty->setAccessible(true);
    $routeMiddleware = $routeMiddlewareProperty->getValue($kernel);

    expect($routeMiddleware)->toBeArray()
        ->and($routeMiddleware)->toHaveCount(17)
        ->and($routeMiddleware)->toEqual([
            'auth' => Authenticate::class,
            'bouncer' => ScopeBouncer::class,
            'auth.basic' => AuthenticateWithBasicAuth::class,
            'bindings' => SubstituteBindings::class,
            'can' => Authorize::class,
            'guest' => RedirectIfAuthenticated::class,
            'customer' => CustomerRedirectIfAuthenticated::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
            'install' => InstallationMiddleware::class,
            'redirect-if-installed' => RedirectIfInstalled::class,
            'redirect-if-unauthenticated' => RedirectIfUnauthorized::class,
            'customer-guest' => CustomerGuest::class,
            'company' => CompanyMiddleware::class,
            'pdf-auth' => PdfMiddleware::class,
            'cron-job' => CronJobMiddleware::class,
            'customer-portal' => CustomerPortalMiddleware::class,
        ]);
});

test('middleware priority list is correctly defined', function () {
    $kernel = new Kernel(app(), app(\Illuminate\Contracts\Routing\Registrar::class));
    $reflection = new ReflectionClass($kernel);
    $middlewarePriorityProperty = $reflection->getProperty('middlewarePriority');
    $middlewarePriorityProperty->setAccessible(true);
    $middlewarePriority = $middlewarePriorityProperty->getValue($kernel);

    expect($middlewarePriority)->toBeArray()
        ->and($middlewarePriority)->toHaveCount(6)
        ->and($middlewarePriority)->toEqual([
            StartSession::class,
            ShareErrorsFromSession::class,
            Authenticate::class,
            IlluminateAuthenticateSession::class, // Using aliased name
            SubstituteBindings::class,
            Authorize::class,
        ]);
});




afterEach(function () {
    Mockery::close();
});
