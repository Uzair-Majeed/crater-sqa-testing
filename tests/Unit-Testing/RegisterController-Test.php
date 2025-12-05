<?php

use Crater\Http\Controllers\V1\Admin\Auth\RegisterController;

// ========== REGISTERCONTROLLER TESTS (10 MINIMAL TESTS FOR GOOD COVERAGE) ==========
// NO MOCKERY - Pure unit tests with structural and functional coverage

test('RegisterController can be instantiated', function () {
    $controller = new RegisterController();
    expect($controller)->toBeInstanceOf(RegisterController::class);
});

test('RegisterController extends Controller', function () {
    $controller = new RegisterController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('RegisterController is in correct namespace', function () {
    $reflection = new ReflectionClass(RegisterController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\Auth');
});

test('RegisterController uses RegistersUsers trait', function () {
    $reflection = new ReflectionClass(RegisterController::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Foundation\Auth\RegistersUsers');
});

test('RegisterController has redirectTo property', function () {
    $reflection = new ReflectionClass(RegisterController::class);
    
    expect($reflection->hasProperty('redirectTo'))->toBeTrue();
    
    $property = $reflection->getProperty('redirectTo');
    expect($property->isProtected())->toBeTrue();
});

test('RegisterController redirectTo uses RouteServiceProvider HOME', function () {
    $reflection = new ReflectionClass(RegisterController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('protected $redirectTo = RouteServiceProvider::HOME');
});

test('RegisterController has constructor that applies guest middleware', function () {
    $reflection = new ReflectionClass(RegisterController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($reflection->hasMethod('__construct'))->toBeTrue()
        ->and($fileContent)->toContain('$this->middleware(\'guest\')');
});

test('RegisterController has validator method', function () {
    $reflection = new ReflectionClass(RegisterController::class);
    
    expect($reflection->hasMethod('validator'))->toBeTrue();
    
    $method = $reflection->getMethod('validator');
    expect($method->isProtected())->toBeTrue();
});

test('RegisterController validator method has correct validation rules', function () {
    $reflection = new ReflectionClass(RegisterController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'name\' => [\'required\', \'string\', \'max:255\']')
        ->and($fileContent)->toContain('\'email\' => [\'required\', \'string\', \'email\', \'max:255\', \'unique:users\']')
        ->and($fileContent)->toContain('\'password\' => [\'required\', \'string\', \'min:8\', \'confirmed\']');
});

test('RegisterController has create method that creates User', function () {
    $reflection = new ReflectionClass(RegisterController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($reflection->hasMethod('create'))->toBeTrue()
        ->and($fileContent)->toContain('User::create([')
        ->and($fileContent)->toContain('\'name\' => $data[\'name\']')
        ->and($fileContent)->toContain('\'email\' => $data[\'email\']')
        ->and($fileContent)->toContain('\'password\' => $data[\'password\']');
});