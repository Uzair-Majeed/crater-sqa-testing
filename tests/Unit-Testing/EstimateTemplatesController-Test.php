<?php

use Crater\Http\Controllers\V1\Admin\Estimate\EstimateTemplatesController;
use Crater\Models\Estimate;
use Illuminate\Http\Request;

// ========== CLASS STRUCTURE TESTS ==========

test('EstimateTemplatesController can be instantiated', function () {
    $controller = new EstimateTemplatesController();
    expect($controller)->toBeInstanceOf(EstimateTemplatesController::class);
});

test('EstimateTemplatesController extends Controller', function () {
    $controller = new EstimateTemplatesController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('EstimateTemplatesController is in correct namespace', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\Estimate');
});

test('EstimateTemplatesController is not abstract', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('EstimateTemplatesController is instantiable', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== INVOKABLE CONTROLLER TESTS ==========

test('EstimateTemplatesController has __invoke method', function () {
    $controller = new EstimateTemplatesController();
    expect(method_exists($controller, '__invoke'))->toBeTrue();
});

test('__invoke method is public', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->isPublic())->toBeTrue();
});

test('__invoke method accepts Request parameter', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $method = $reflection->getMethod('__invoke');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[0]->getType()->getName())->toContain('Request');
});

test('__invoke method is not static', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->isStatic())->toBeFalse();
});

test('__invoke method is not abstract', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->isAbstract())->toBeFalse();
});

// ========== CONTROLLER CHARACTERISTICS TESTS ==========

test('controller is invokable', function () {
    $controller = new EstimateTemplatesController();
    expect(is_callable($controller))->toBeTrue();
});

test('controller can be called as function', function () {
    $controller = new EstimateTemplatesController();
    $request = new Request();
    
    // Controller should be callable
    expect(is_callable([$controller, '__invoke']))->toBeTrue();
});

// ========== INSTANCE TESTS ==========

test('multiple EstimateTemplatesController instances can be created', function () {
    $controller1 = new EstimateTemplatesController();
    $controller2 = new EstimateTemplatesController();
    
    expect($controller1)->toBeInstanceOf(EstimateTemplatesController::class)
        ->and($controller2)->toBeInstanceOf(EstimateTemplatesController::class)
        ->and($controller1)->not->toBe($controller2);
});

test('EstimateTemplatesController can be cloned', function () {
    $controller = new EstimateTemplatesController();
    $clone = clone $controller;
    
    expect($clone)->toBeInstanceOf(EstimateTemplatesController::class)
        ->and($clone)->not->toBe($controller);
});

test('EstimateTemplatesController can be used in type hints', function () {
    $testFunction = function (EstimateTemplatesController $controller) {
        return $controller;
    };
    
    $controller = new EstimateTemplatesController();
    $result = $testFunction($controller);
    
    expect($result)->toBe($controller);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('EstimateTemplatesController is not final', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('EstimateTemplatesController is not an interface', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('EstimateTemplatesController is not a trait', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('EstimateTemplatesController class is loaded', function () {
    expect(class_exists(EstimateTemplatesController::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('EstimateTemplatesController uses required classes', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Http\Controllers\Controller')
        ->and($fileContent)->toContain('use Crater\Models\Estimate')
        ->and($fileContent)->toContain('use Illuminate\Http\Request');
});

// ========== FILE STRUCTURE TESTS ==========

test('EstimateTemplatesController file has expected structure', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class EstimateTemplatesController extends Controller')
        ->and($fileContent)->toContain('public function __invoke');
});

test('EstimateTemplatesController has compact implementation', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be concise (< 1000 bytes for simple controller)
    expect(strlen($fileContent))->toBeLessThan(1000);
});

test('EstimateTemplatesController has minimal line count', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(50);
});

// ========== IMPLEMENTATION TESTS ==========

test('__invoke method uses authorization', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize');
});

test('__invoke method authorizes viewAny on Estimate', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('viewAny')
        ->and($fileContent)->toContain('Estimate::class');
});

test('__invoke method calls Estimate::estimateTemplates', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Estimate::estimateTemplates()');
});

test('__invoke method returns JSON response', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('response()->json');
});

test('__invoke method returns estimateTemplates key', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('estimateTemplates');
});

// ========== METHOD COUNT TESTS ==========

test('EstimateTemplatesController has only __invoke method', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    $ownMethods = array_filter($publicMethods, function ($method) {
        return $method->class === EstimateTemplatesController::class;
    });
    
    expect(count($ownMethods))->toBe(1);
});

test('__invoke is the only declared method', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $methods = $reflection->getMethods();
    
    $declaredMethods = array_filter($methods, function ($method) {
        return $method->class === EstimateTemplatesController::class;
    });
    
    expect(count($declaredMethods))->toBe(1)
        ->and($declaredMethods[0]->getName())->toBe('__invoke');
});

// ========== NAMESPACE TESTS ==========

test('EstimateTemplatesController is in correct namespace depth', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $namespace = $reflection->getNamespaceName();
    $parts = explode('\\', $namespace);
    
    expect($parts)->toContain('Crater')
        ->and($parts)->toContain('Http')
        ->and($parts)->toContain('Controllers')
        ->and($parts)->toContain('V1')
        ->and($parts)->toContain('Admin')
        ->and($parts)->toContain('Estimate');
});

test('EstimateTemplatesController namespace has correct structure', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $namespace = $reflection->getNamespaceName();
    
    expect($namespace)->toBe('Crater\Http\Controllers\V1\Admin\Estimate');
});

// ========== PARENT CLASS TESTS ==========

test('EstimateTemplatesController parent is Controller', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe('Crater\Http\Controllers\Controller');
});

test('EstimateTemplatesController inherits from base Controller', function () {
    $controller = new EstimateTemplatesController();
    expect($controller)->toBeInstanceOf(\Illuminate\Routing\Controller::class);
});

// ========== AUTHORIZATION LOGIC TESTS ==========

test('controller implements authorization before action', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $method = $reflection->getMethod('__invoke');
    $fileContent = file_get_contents($reflection->getFileName());
    
    // Authorization should come before the main logic
    $authorizePos = strpos($fileContent, '$this->authorize');
    $templatesPos = strpos($fileContent, 'estimateTemplates');
    
    expect($authorizePos)->toBeLessThan($templatesPos);
});

test('controller uses Estimate model', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Estimate::');
});

// ========== RESPONSE STRUCTURE TESTS ==========

test('controller returns array with estimateTemplates key', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain("'estimateTemplates'");
});

test('controller uses response helper', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('response()');
});

// ========== CODE QUALITY TESTS ==========

test('controller has proper documentation', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('controller method has return type documentation', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $method = $reflection->getMethod('__invoke');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@return');
});

test('controller method has param documentation', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $method = $reflection->getMethod('__invoke');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@param');
});

// ========== SINGLE RESPONSIBILITY TESTS ==========

test('controller has single responsibility', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    $ownPublicMethods = array_filter($methods, function ($method) {
        return $method->class === EstimateTemplatesController::class;
    });
    
    // Should only have __invoke method (single action controller)
    expect(count($ownPublicMethods))->toBe(1);
});

test('controller follows invokable controller pattern', function () {
    $reflection = new ReflectionClass(EstimateTemplatesController::class);
    expect($reflection->hasMethod('__invoke'))->toBeTrue();
});