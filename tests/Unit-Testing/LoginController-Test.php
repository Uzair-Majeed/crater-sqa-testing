<?php

use Crater\Http\Controllers\V1\Admin\Auth\LoginController;

// ========== LOGINCONTROLLER TESTS (10 MINIMAL TESTS FOR 100% COVERAGE) ==========

test('LoginController can be instantiated', function () {
    $controller = new LoginController();
    expect($controller)->toBeInstanceOf(LoginController::class);
});

test('LoginController extends Controller', function () {
    $controller = new LoginController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('LoginController is in correct namespace', function () {
    $reflection = new ReflectionClass(LoginController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\Auth');
});

test('LoginController uses AuthenticatesUsers trait', function () {
    $reflection = new ReflectionClass(LoginController::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Foundation\Auth\AuthenticatesUsers');
});

test('LoginController has redirectTo property', function () {
    $reflection = new ReflectionClass(LoginController::class);
    
    expect($reflection->hasProperty('redirectTo'))->toBeTrue();
    
    $property = $reflection->getProperty('redirectTo');
    expect($property->isProtected())->toBeTrue();
});

test('LoginController redirectTo uses RouteServiceProvider HOME', function () {
    $reflection = new ReflectionClass(LoginController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('protected $redirectTo = RouteServiceProvider::HOME');
});

test('LoginController has constructor', function () {
    $reflection = new ReflectionClass(LoginController::class);
    
    expect($reflection->hasMethod('__construct'))->toBeTrue();
    
    $method = $reflection->getMethod('__construct');
    expect($method->isPublic())->toBeTrue();
});

test('LoginController constructor applies guest middleware', function () {
    $reflection = new ReflectionClass(LoginController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->middleware(\'guest\')');
});

test('LoginController guest middleware excludes logout', function () {
    $reflection = new ReflectionClass(LoginController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->except(\'logout\')');
});

test('LoginController file has proper documentation', function () {
    $reflection = new ReflectionClass(LoginController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Login Controller')
        ->and($fileContent)->toContain('authenticating users');
});
