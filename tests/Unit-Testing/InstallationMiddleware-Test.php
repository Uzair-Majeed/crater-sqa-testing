<?php

use Crater\Http\Middleware\InstallationMiddleware;

// ========== STRUCTURAL TESTS (NO MOCKERY) ==========

test('InstallationMiddleware class exists and is instantiable', function () {
    $middleware = new InstallationMiddleware();
    expect($middleware)->toBeInstanceOf(InstallationMiddleware::class);
});

test('InstallationMiddleware is in correct namespace', function () {
    $reflection = new ReflectionClass(InstallationMiddleware::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Middleware');
});

test('InstallationMiddleware has handle method', function () {
    $reflection = new ReflectionClass(InstallationMiddleware::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();
});

test('handle method accepts request and Closure parameters', function () {
    $reflection = new ReflectionClass(InstallationMiddleware::class);
    $method = $reflection->getMethod('handle');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[1]->getName())->toBe('next');
});

test('handle method is public', function () {
    $reflection = new ReflectionClass(InstallationMiddleware::class);
    $method = $reflection->getMethod('handle');
    
    expect($method->isPublic())->toBeTrue()
        ->and($method->isStatic())->toBeFalse();
});

test('InstallationMiddleware checks database_created file', function () {
    $reflection = new ReflectionClass(InstallationMiddleware::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Storage::disk(\'local\')->has(\'database_created\')');
});

test('InstallationMiddleware redirects to installation route', function () {
    $reflection = new ReflectionClass(InstallationMiddleware::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('redirect(\'/installation\')');
});

test('InstallationMiddleware checks profile_complete setting', function () {
    $reflection = new ReflectionClass(InstallationMiddleware::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Setting::getSetting(\'profile_complete\')')
        ->and($fileContent)->toContain('COMPLETED');
});

test('InstallationMiddleware calls next middleware when conditions met', function () {
    $reflection = new ReflectionClass(InstallationMiddleware::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('return $next($request)');
});

test('InstallationMiddleware uses Setting model', function () {
    $reflection = new ReflectionClass(InstallationMiddleware::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Models\Setting')
        ->and($fileContent)->toContain('use Closure');
});