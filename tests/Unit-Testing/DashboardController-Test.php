<?php

use Crater\Http\Controllers\V1\Customer\General\DashboardController;
use Crater\Http\Controllers\Controller;

// Test controller instantiation
test('DashboardController can be instantiated', function () {
    $controller = new DashboardController();
    expect($controller)->toBeInstanceOf(DashboardController::class);
});

// Test controller extends base Controller
test('DashboardController extends base Controller class', function () {
    $controller = new DashboardController();
    expect($controller)->toBeInstanceOf(Controller::class);
});

// Test controller is invokable
test('DashboardController is invokable', function () {
    $controller = new DashboardController();
    expect(is_callable($controller))->toBeTrue();
});

// Test __invoke method exists
test('DashboardController has __invoke method', function () {
    $controller = new DashboardController();
    expect(method_exists($controller, '__invoke'))->toBeTrue();
});

// Test __invoke method is public
test('__invoke method is public', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->isPublic())->toBeTrue();
});

// Test __invoke method accepts Request parameter
test('__invoke method accepts Request parameter', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    $method = $reflection->getMethod('__invoke');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// Test controller is in correct namespace
test('DashboardController is in correct namespace', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Customer\General');
});

// Test controller class name
test('DashboardController has correct class name', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    expect($reflection->getShortName())->toBe('DashboardController');
});

// Test controller is not abstract
test('DashboardController is not abstract', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    expect($reflection->isAbstract())->toBeFalse();
});

// Test controller is not an interface
test('DashboardController is not an interface', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    expect($reflection->isInterface())->toBeFalse();
});

// Test controller is not a trait
test('DashboardController is not a trait', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    expect($reflection->isTrait())->toBeFalse();
});

// Test controller is instantiable
test('DashboardController is instantiable', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// Test multiple instances can be created
test('multiple DashboardController instances can be created', function () {
    $controller1 = new DashboardController();
    $controller2 = new DashboardController();
    
    expect($controller1)->toBeInstanceOf(DashboardController::class)
        ->and($controller2)->toBeInstanceOf(DashboardController::class)
        ->and($controller1)->not->toBe($controller2);
});

// Test controller has no constructor parameters
test('DashboardController constructor has no required parameters', function () {
    $reflection = new ReflectionClass(DashboardController::class);
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

// Test __invoke method signature
test('__invoke method has correct signature', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->getNumberOfParameters())->toBe(1)
        ->and($method->isPublic())->toBeTrue()
        ->and($method->isStatic())->toBeFalse();
});

// Test controller file exists
test('DashboardController class is loaded', function () {
    expect(class_exists(DashboardController::class))->toBeTrue();
});

// Test controller uses correct imports
test('DashboardController uses required classes', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Http\Controllers\Controller')
        ->and($fileContent)->toContain('use Crater\Models\Estimate')
        ->and($fileContent)->toContain('use Crater\Models\Invoice')
        ->and($fileContent)->toContain('use Crater\Models\Payment')
        ->and($fileContent)->toContain('use Illuminate\Http\Request')
        ->and($fileContent)->toContain('use Illuminate\Support\Facades\Auth');
});

// Test controller method count
test('DashboardController has expected number of public methods', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    // Filter out inherited methods from Controller
    $ownMethods = array_filter($publicMethods, function ($method) {
        return $method->class === DashboardController::class;
    });
    
    expect(count($ownMethods))->toBeGreaterThanOrEqual(1);
});

// Test controller is final or not
test('DashboardController can be extended', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    expect($reflection->isFinal())->toBeFalse();
});

// Test controller parent class
test('DashboardController parent class is Controller', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe(Controller::class);
});

// Test controller implements no interfaces directly
test('DashboardController structure is correct', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    
    expect($reflection->isInstantiable())->toBeTrue()
        ->and($reflection->hasMethod('__invoke'))->toBeTrue();
});

// Test that controller can be type-hinted
test('DashboardController can be used in type hints', function () {
    $testFunction = function (DashboardController $controller) {
        return $controller;
    };
    
    $controller = new DashboardController();
    $result = $testFunction($controller);
    
    expect($result)->toBe($controller);
});

// Test controller method visibility
test('__invoke method has correct visibility', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->isPublic())->toBeTrue()
        ->and($method->isProtected())->toBeFalse()
        ->and($method->isPrivate())->toBeFalse();
});

// Test controller namespace depth
test('DashboardController is in correct namespace depth', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    $namespace = $reflection->getNamespaceName();
    $parts = explode('\\', $namespace);
    
    expect($parts)->toContain('Crater')
        ->and($parts)->toContain('Http')
        ->and($parts)->toContain('Controllers')
        ->and($parts)->toContain('V1')
        ->and($parts)->toContain('Customer')
        ->and($parts)->toContain('General');
});

// Test controller is not cloneable restriction
test('DashboardController can be cloned', function () {
    $controller = new DashboardController();
    $clone = clone $controller;
    
    expect($clone)->toBeInstanceOf(DashboardController::class)
        ->and($clone)->not->toBe($controller);
});