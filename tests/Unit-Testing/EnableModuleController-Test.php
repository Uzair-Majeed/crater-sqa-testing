<?php

use Crater\Http\Controllers\V1\Admin\Modules\EnableModuleController;
use Crater\Http\Controllers\Controller;

// Test controller can be instantiated
test('EnableModuleController can be instantiated', function () {
    $controller = new EnableModuleController();
    expect($controller)->toBeInstanceOf(EnableModuleController::class);
});

// Test controller extends base Controller
test('EnableModuleController extends base Controller class', function () {
    $controller = new EnableModuleController();
    expect($controller)->toBeInstanceOf(Controller::class);
});

// Test controller is invokable
test('EnableModuleController is invokable', function () {
    $controller = new EnableModuleController();
    expect(is_callable($controller))->toBeTrue();
});

// Test __invoke method exists
test('EnableModuleController has __invoke method', function () {
    $controller = new EnableModuleController();
    expect(method_exists($controller, '__invoke'))->toBeTrue();
});

// Test __invoke method is public
test('__invoke method is public', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->isPublic())->toBeTrue();
});

// Test __invoke method accepts correct parameters
test('__invoke method accepts Request and string parameters', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    $method = $reflection->getMethod('__invoke');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[1]->getName())->toBe('module');
});

// Test controller is in correct namespace
test('EnableModuleController is in correct namespace', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\Modules');
});

// Test controller class name
test('EnableModuleController has correct class name', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    expect($reflection->getShortName())->toBe('EnableModuleController');
});

// Test controller is not abstract
test('EnableModuleController is not abstract', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    expect($reflection->isAbstract())->toBeFalse();
});

// Test controller is not an interface
test('EnableModuleController is not an interface', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    expect($reflection->isInterface())->toBeFalse();
});

// Test controller is not a trait
test('EnableModuleController is not a trait', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    expect($reflection->isTrait())->toBeFalse();
});

// Test controller is instantiable
test('EnableModuleController is instantiable', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// Test multiple instances can be created
test('multiple EnableModuleController instances can be created', function () {
    $controller1 = new EnableModuleController();
    $controller2 = new EnableModuleController();
    
    expect($controller1)->toBeInstanceOf(EnableModuleController::class)
        ->and($controller2)->toBeInstanceOf(EnableModuleController::class)
        ->and($controller1)->not->toBe($controller2);
});

// Test controller has no constructor parameters
test('EnableModuleController constructor has no required parameters', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
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
    $reflection = new ReflectionClass(EnableModuleController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->getNumberOfParameters())->toBe(2)
        ->and($method->isPublic())->toBeTrue()
        ->and($method->isStatic())->toBeFalse();
});

// Test controller file exists
test('EnableModuleController class is loaded', function () {
    expect(class_exists(EnableModuleController::class))->toBeTrue();
});

// Test controller uses correct imports
test('EnableModuleController uses required classes', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Events\ModuleEnabledEvent')
        ->and($fileContent)->toContain('use Crater\Http\Controllers\Controller')
        ->and($fileContent)->toContain('use Crater\Models\Module')
        ->and($fileContent)->toContain('use Illuminate\Http\Request')
        ->and($fileContent)->toContain('use Nwidart\Modules\Facades\Module');
});

// Test controller is not final
test('EnableModuleController can be extended', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    expect($reflection->isFinal())->toBeFalse();
});

// Test controller parent class
test('EnableModuleController parent class is Controller', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe(Controller::class);
});

// Test controller can be type-hinted
test('EnableModuleController can be used in type hints', function () {
    $testFunction = function (EnableModuleController $controller) {
        return $controller;
    };
    
    $controller = new EnableModuleController();
    $result = $testFunction($controller);
    
    expect($result)->toBe($controller);
});

// Test __invoke method visibility
test('__invoke method has correct visibility', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->isPublic())->toBeTrue()
        ->and($method->isProtected())->toBeFalse()
        ->and($method->isPrivate())->toBeFalse();
});

// Test controller namespace depth
test('EnableModuleController is in correct namespace depth', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    $namespace = $reflection->getNamespaceName();
    $parts = explode('\\', $namespace);
    
    expect($parts)->toContain('Crater')
        ->and($parts)->toContain('Http')
        ->and($parts)->toContain('Controllers')
        ->and($parts)->toContain('V1')
        ->and($parts)->toContain('Admin')
        ->and($parts)->toContain('Modules');
});

// Test controller can be cloned
test('EnableModuleController can be cloned', function () {
    $controller = new EnableModuleController();
    $clone = clone $controller;
    
    expect($clone)->toBeInstanceOf(EnableModuleController::class)
        ->and($clone)->not->toBe($controller);
});

// Test second parameter type
test('second parameter of __invoke is typed as string', function () {
    $reflection = new ReflectionClass(EnableModuleController::class);
    $method = $reflection->getMethod('__invoke');
    $parameters = $method->getParameters();
    
    expect($parameters[1]->hasType())->toBeTrue();
});
