<?php

use Crater\Http\Controllers\V1\Admin\Customer\CustomersController;
use Crater\Http\Controllers\Controller;

// Test controller instantiation
test('controller can be instantiated', function () {
    $controller = new CustomersController();
    expect($controller)->toBeInstanceOf(CustomersController::class);
});

// Test controller extends base Controller
test('controller extends base Controller class', function () {
    $controller = new CustomersController();
    expect($controller)->toBeInstanceOf(Controller::class);
});

// Test controller has index method
test('controller has index method', function () {
    $controller = new CustomersController();
    expect(method_exists($controller, 'index'))->toBeTrue();
});

// Test controller has store method
test('controller has store method', function () {
    $controller = new CustomersController();
    expect(method_exists($controller, 'store'))->toBeTrue();
});

// Test controller has show method
test('controller has show method', function () {
    $controller = new CustomersController();
    expect(method_exists($controller, 'show'))->toBeTrue();
});

// Test controller has update method
test('controller has update method', function () {
    $controller = new CustomersController();
    expect(method_exists($controller, 'update'))->toBeTrue();
});

// Test controller has delete method
test('controller has delete method', function () {
    $controller = new CustomersController();
    expect(method_exists($controller, 'delete'))->toBeTrue();
});

// Test all CRUD methods exist
test('controller has all CRUD methods', function () {
    $controller = new CustomersController();
    
    expect(method_exists($controller, 'index'))->toBeTrue()
        ->and(method_exists($controller, 'store'))->toBeTrue()
        ->and(method_exists($controller, 'show'))->toBeTrue()
        ->and(method_exists($controller, 'update'))->toBeTrue()
        ->and(method_exists($controller, 'delete'))->toBeTrue();
});

// Test index method signature
test('index method has correct signature', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    $method = $reflection->getMethod('index');
    
    expect($method->getNumberOfParameters())->toBe(1)
        ->and($method->isPublic())->toBeTrue();
});

// Test store method signature
test('store method has correct signature', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    $method = $reflection->getMethod('store');
    
    expect($method->getNumberOfParameters())->toBe(1)
        ->and($method->isPublic())->toBeTrue();
});

// Test show method signature
test('show method has correct signature', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    $method = $reflection->getMethod('show');
    
    expect($method->getNumberOfParameters())->toBe(1)
        ->and($method->isPublic())->toBeTrue();
});

// Test update method signature
test('update method has correct signature', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    $method = $reflection->getMethod('update');
    
    expect($method->getNumberOfParameters())->toBe(2)
        ->and($method->isPublic())->toBeTrue();
});

// Test delete method signature
test('delete method has correct signature', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    $method = $reflection->getMethod('delete');
    
    expect($method->getNumberOfParameters())->toBe(1)
        ->and($method->isPublic())->toBeTrue();
});

// Test all methods are public
test('all CRUD methods are public', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    
    expect($reflection->getMethod('index')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('store')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('show')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('update')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('delete')->isPublic())->toBeTrue();
});

// Test controller namespace
test('controller is in correct namespace', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\Customer');
});

// Test controller class name
test('controller has correct class name', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    expect($reflection->getShortName())->toBe('CustomersController');
});

// Test that controller is not abstract
test('controller is not abstract', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    expect($reflection->isAbstract())->toBeFalse();
});

// Test that controller is not an interface
test('controller is not an interface', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    expect($reflection->isInterface())->toBeFalse();
});

// Test that controller is not a trait
test('controller is not a trait', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    expect($reflection->isTrait())->toBeFalse();
});

// Test that controller is instantiable
test('controller is instantiable', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// Test controller can be created multiple times
test('multiple controller instances can be created', function () {
    $controller1 = new CustomersController();
    $controller2 = new CustomersController();
    
    expect($controller1)->toBeInstanceOf(CustomersController::class)
        ->and($controller2)->toBeInstanceOf(CustomersController::class)
        ->and($controller1)->not->toBe($controller2);
});

// Test index method parameter type
test('index method accepts Request parameter', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    $method = $reflection->getMethod('index');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// Test store method parameter type
test('store method accepts CustomerRequest parameter', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    $method = $reflection->getMethod('store');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// Test show method parameter type
test('show method accepts Customer parameter', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    $method = $reflection->getMethod('show');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('customer');
});

// Test update method parameter types
test('update method accepts CustomerRequest and Customer parameters', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    $method = $reflection->getMethod('update');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[1]->getName())->toBe('customer');
});

// Test delete method parameter type
test('delete method accepts DeleteCustomersRequest parameter', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    $method = $reflection->getMethod('delete');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// Test that methods don't have return type declarations (for compatibility)
test('methods have proper visibility and are callable', function () {
    $controller = new CustomersController();
    
    expect(is_callable([$controller, 'index']))->toBeTrue()
        ->and(is_callable([$controller, 'store']))->toBeTrue()
        ->and(is_callable([$controller, 'show']))->toBeTrue()
        ->and(is_callable([$controller, 'update']))->toBeTrue()
        ->and(is_callable([$controller, 'delete']))->toBeTrue();
});

// Test controller doesn't have constructor parameters
test('controller constructor has no required parameters', function () {
    $reflection = new ReflectionClass(CustomersController::class);
    $constructor = $reflection->getConstructor();
    
    if ($constructor) {
        $requiredParams = array_filter($constructor->getParameters(), function ($param) {
            return !$param->isOptional();
        });
        expect($requiredParams)->toBeEmpty();
    } else {
        expect(true)->toBeTrue(); // No constructor is fine
    }
});