<?php

use Crater\Http\Middleware\RedirectIfAuthenticated;
use Crater\Http\Middleware\RedirectIfInstalled;
use Crater\Http\Middleware\RedirectIfUnauthorized;

// ========== MERGED REDIRECT MIDDLEWARE TESTS (3 CLASSES, ~15 TESTS) ==========

// --- RedirectIfAuthenticated Tests (5 tests) ---

test('RedirectIfAuthenticated can be instantiated', function () {
    $middleware = new RedirectIfAuthenticated();
    expect($middleware)->toBeInstanceOf(RedirectIfAuthenticated::class);
});

test('RedirectIfAuthenticated is in correct namespace', function () {
    $reflection = new ReflectionClass(RedirectIfAuthenticated::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Middleware');
});

test('RedirectIfAuthenticated has handle method', function () {
    $reflection = new ReflectionClass(RedirectIfAuthenticated::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();
    
    $method = $reflection->getMethod('handle');
    expect($method->isPublic())->toBeTrue();
});

test('RedirectIfAuthenticated handle method accepts request, closure, and guard', function () {
    $reflection = new ReflectionClass(RedirectIfAuthenticated::class);
    $method = $reflection->getMethod('handle');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(3)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[1]->getName())->toBe('next')
        ->and($parameters[2]->getName())->toBe('guard');
});

test('RedirectIfAuthenticated uses Auth guard and RouteServiceProvider', function () {
    $reflection = new ReflectionClass(RedirectIfAuthenticated::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Auth::guard($guard)->check()')
        ->and($fileContent)->toContain('redirect(RouteServiceProvider::HOME)')
        ->and($fileContent)->toContain('return $next($request)');
});

// --- RedirectIfInstalled Tests (5 tests) ---

test('RedirectIfInstalled can be instantiated', function () {
    $middleware = new RedirectIfInstalled();
    expect($middleware)->toBeInstanceOf(RedirectIfInstalled::class);
});

test('RedirectIfInstalled is in correct namespace', function () {
    $reflection = new ReflectionClass(RedirectIfInstalled::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Middleware');
});

test('RedirectIfInstalled has handle method', function () {
    $reflection = new ReflectionClass(RedirectIfInstalled::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();
    
    $method = $reflection->getMethod('handle');
    expect($method->isPublic())->toBeTrue();
});

test('RedirectIfInstalled handle method accepts request and closure', function () {
    $reflection = new ReflectionClass(RedirectIfInstalled::class);
    $method = $reflection->getMethod('handle');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[1]->getName())->toBe('next');
});

test('RedirectIfInstalled checks Storage and Setting', function () {
    $reflection = new ReflectionClass(RedirectIfInstalled::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\\Storage::disk(\'local\')->has(\'database_created\')')
        ->and($fileContent)->toContain('Setting::getSetting(\'profile_complete\')')
        ->and($fileContent)->toContain('=== \'COMPLETED\'')
        ->and($fileContent)->toContain('redirect(\'login\')')
        ->and($fileContent)->toContain('return $next($request)');
});

// --- RedirectIfUnauthorized Tests (5 tests) ---

test('RedirectIfUnauthorized can be instantiated', function () {
    $middleware = new RedirectIfUnauthorized();
    expect($middleware)->toBeInstanceOf(RedirectIfUnauthorized::class);
});

test('RedirectIfUnauthorized is in correct namespace', function () {
    $reflection = new ReflectionClass(RedirectIfUnauthorized::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Middleware');
});

test('RedirectIfUnauthorized has handle method', function () {
    $reflection = new ReflectionClass(RedirectIfUnauthorized::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();
    
    $method = $reflection->getMethod('handle');
    expect($method->isPublic())->toBeTrue();
});

test('RedirectIfUnauthorized handle method accepts request, closure, and guard', function () {
    $reflection = new ReflectionClass(RedirectIfUnauthorized::class);
    $method = $reflection->getMethod('handle');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(3)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[1]->getName())->toBe('next')
        ->and($parameters[2]->getName())->toBe('guard');
});

test('RedirectIfUnauthorized uses Auth guard and redirects to login', function () {
    $reflection = new ReflectionClass(RedirectIfUnauthorized::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Auth::guard($guard)->check()')
        ->and($fileContent)->toContain('return $next($request)')
        ->and($fileContent)->toContain('redirect(\'/login\')');
});
