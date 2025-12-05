<?php

use Crater\Console\Commands\ResetApp;

// ========== RESETAPP TESTS (10 TESTS: STRUCTURAL + FUNCTIONAL) ==========

// --- Structural Tests (6 tests) ---

test('ResetApp can be instantiated', function () {
    $command = new ResetApp();
    expect($command)->toBeInstanceOf(ResetApp::class);
});

test('ResetApp extends Command', function () {
    $command = new ResetApp();
    expect($command)->toBeInstanceOf(\Illuminate\Console\Command::class);
});

test('ResetApp is in correct namespace', function () {
    $reflection = new ReflectionClass(ResetApp::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Console\Commands');
});

test('ResetApp uses ConfirmableTrait', function () {
    $reflection = new ReflectionClass(ResetApp::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Console\ConfirmableTrait');
});

test('ResetApp has signature property', function () {
    $reflection = new ReflectionClass(ResetApp::class);
    
    expect($reflection->hasProperty('signature'))->toBeTrue();
    
    $property = $reflection->getProperty('signature');
    expect($property->isProtected())->toBeTrue();
});

test('ResetApp has description property', function () {
    $reflection = new ReflectionClass(ResetApp::class);
    
    expect($reflection->hasProperty('description'))->toBeTrue();
    
    $property = $reflection->getProperty('description');
    expect($property->isProtected())->toBeTrue();
});

// --- Functional Tests (4 tests) ---

test('ResetApp signature is reset:app with force option', function () {
    $command = new ResetApp();
    $reflection = new ReflectionClass($command);
    $property = $reflection->getProperty('signature');
    $property->setAccessible(true);
    
    expect($property->getValue($command))->toBe('reset:app {--force}');
});

test('ResetApp description mentions database and storage cleanup', function () {
    $command = new ResetApp();
    $reflection = new ReflectionClass($command);
    $property = $reflection->getProperty('description');
    $property->setAccessible(true);
    
    $description = $property->getValue($command);
    
    expect($description)->toContain('database')
        ->and($description)->toContain('storage');
});

test('ResetApp has handle method', function () {
    $reflection = new ReflectionClass(ResetApp::class);
    
    expect($reflection->hasMethod('handle'))->toBeTrue();
    
    $method = $reflection->getMethod('handle');
    expect($method->isPublic())->toBeTrue();
});

test('ResetApp handle method uses Artisan calls and file operations', function () {
    $reflection = new ReflectionClass(ResetApp::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->confirmToProceed()')
        ->and($fileContent)->toContain('Artisan::call(\'migrate:fresh --seed --force\')')
        ->and($fileContent)->toContain('Artisan::call(\'db:seed\'')
        ->and($fileContent)->toContain('DemoSeeder')
        ->and($fileContent)->toContain('base_path(\'.env\')')
        ->and($fileContent)->toContain('file_exists')
        ->and($fileContent)->toContain('file_put_contents')
        ->and($fileContent)->toContain('APP_DEBUG=true')
        ->and($fileContent)->toContain('APP_DEBUG=false');
});