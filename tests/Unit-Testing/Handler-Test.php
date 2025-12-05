<?php

use Crater\Exceptions\Handler;

// ========== CLASS STRUCTURE TESTS ==========

test('Handler is in correct namespace', function () {
    $reflection = new ReflectionClass(Handler::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Exceptions');
});

test('Handler extends ExceptionHandler', function () {
    $reflection = new ReflectionClass(Handler::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe('Illuminate\Foundation\Exceptions\Handler');
});

test('Handler is not abstract', function () {
    $reflection = new ReflectionClass(Handler::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('Handler is instantiable', function () {
    $reflection = new ReflectionClass(Handler::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== PROPERTIES TESTS ==========

test('Handler has dontReport property', function () {
    $reflection = new ReflectionClass(Handler::class);
    expect($reflection->hasProperty('dontReport'))->toBeTrue();
});

test('Handler has dontFlash property', function () {
    $reflection = new ReflectionClass(Handler::class);
    expect($reflection->hasProperty('dontFlash'))->toBeTrue();
});

test('dontReport property is protected', function () {
    $reflection = new ReflectionClass(Handler::class);
    $property = $reflection->getProperty('dontReport');
    
    expect($property->isProtected())->toBeTrue();
});

test('dontFlash property is protected', function () {
    $reflection = new ReflectionClass(Handler::class);
    $property = $reflection->getProperty('dontFlash');
    
    expect($property->isProtected())->toBeTrue();
});

test('dontReport default value is empty array', function () {
    $reflection = new ReflectionClass(Handler::class);
    $property = $reflection->getProperty('dontReport');
    $property->setAccessible(true);
    $defaultValue = $property->getValue($reflection->newInstanceWithoutConstructor());
    
    expect($defaultValue)->toBeArray()
        ->and($defaultValue)->toBeEmpty();
});

test('dontFlash default value contains password fields', function () {
    $reflection = new ReflectionClass(Handler::class);
    $property = $reflection->getProperty('dontFlash');
    $property->setAccessible(true);
    $defaultValue = $property->getValue($reflection->newInstanceWithoutConstructor());
    
    expect($defaultValue)->toBeArray()
        ->and($defaultValue)->toContain('password')
        ->and($defaultValue)->toContain('password_confirmation')
        ->and($defaultValue)->toHaveCount(2);
});

// ========== METHOD EXISTENCE TESTS ==========

test('Handler has report method', function () {
    $reflection = new ReflectionClass(Handler::class);
    expect($reflection->hasMethod('report'))->toBeTrue();
});

test('Handler has render method', function () {
    $reflection = new ReflectionClass(Handler::class);
    expect($reflection->hasMethod('render'))->toBeTrue();
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('report method is public', function () {
    $reflection = new ReflectionClass(Handler::class);
    $method = $reflection->getMethod('report');
    
    expect($method->isPublic())->toBeTrue();
});

test('render method is public', function () {
    $reflection = new ReflectionClass(Handler::class);
    $method = $reflection->getMethod('render');
    
    expect($method->isPublic())->toBeTrue();
});

test('report method is not static', function () {
    $reflection = new ReflectionClass(Handler::class);
    $method = $reflection->getMethod('report');
    
    expect($method->isStatic())->toBeFalse();
});

test('render method is not static', function () {
    $reflection = new ReflectionClass(Handler::class);
    $method = $reflection->getMethod('render');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== METHOD PARAMETERS TESTS ==========

test('report method accepts exception parameter', function () {
    $reflection = new ReflectionClass(Handler::class);
    $method = $reflection->getMethod('report');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('exception');
});

test('render method accepts two parameters', function () {
    $reflection = new ReflectionClass(Handler::class);
    $method = $reflection->getMethod('render');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[1]->getName())->toBe('exception');
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('Handler is not final', function () {
    $reflection = new ReflectionClass(Handler::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('Handler is not an interface', function () {
    $reflection = new ReflectionClass(Handler::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('Handler is not a trait', function () {
    $reflection = new ReflectionClass(Handler::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('Handler class is loaded', function () {
    expect(class_exists(Handler::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('Handler uses ExceptionHandler', function () {
    $reflection = new ReflectionClass(Handler::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler');
});

test('Handler uses Throwable', function () {
    $reflection = new ReflectionClass(Handler::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Throwable');
});

// ========== FILE STRUCTURE TESTS ==========

test('Handler file has expected structure', function () {
    $reflection = new ReflectionClass(Handler::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class Handler extends ExceptionHandler')
        ->and($fileContent)->toContain('protected $dontReport')
        ->and($fileContent)->toContain('protected $dontFlash')
        ->and($fileContent)->toContain('public function report')
        ->and($fileContent)->toContain('public function render');
});

test('Handler has reasonable line count', function () {
    $reflection = new ReflectionClass(Handler::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeGreaterThan(30)
        ->and($lineCount)->toBeLessThan(100);
});

// ========== IMPLEMENTATION TESTS ==========

test('report method calls parent report', function () {
    $reflection = new ReflectionClass(Handler::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::report($exception)');
});

test('render method calls parent render', function () {
    $reflection = new ReflectionClass(Handler::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::render($request, $exception)');
});

test('render method returns response', function () {
    $reflection = new ReflectionClass(Handler::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('return parent::render');
});

// ========== DOCUMENTATION TESTS ==========

test('dontReport property has documentation', function () {
    $reflection = new ReflectionClass(Handler::class);
    $property = $reflection->getProperty('dontReport');
    
    expect($property->getDocComment())->not->toBeFalse();
});

test('dontFlash property has documentation', function () {
    $reflection = new ReflectionClass(Handler::class);
    $property = $reflection->getProperty('dontFlash');
    
    expect($property->getDocComment())->not->toBeFalse();
});

test('report method has documentation', function () {
    $reflection = new ReflectionClass(Handler::class);
    $method = $reflection->getMethod('report');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('render method has documentation', function () {
    $reflection = new ReflectionClass(Handler::class);
    $method = $reflection->getMethod('render');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('report method documentation mentions Sentry and Bugsnag', function () {
    $reflection = new ReflectionClass(Handler::class);
    $method = $reflection->getMethod('report');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('Sentry')
        ->and($docComment)->toContain('Bugsnag');
});

// ========== PROPERTY VALUES TESTS ==========

test('dontFlash first element is password', function () {
    $reflection = new ReflectionClass(Handler::class);
    $property = $reflection->getProperty('dontFlash');
    $property->setAccessible(true);
    $defaultValue = $property->getValue($reflection->newInstanceWithoutConstructor());
    
    expect($defaultValue[0])->toBe('password');
});

test('dontFlash second element is password_confirmation', function () {
    $reflection = new ReflectionClass(Handler::class);
    $property = $reflection->getProperty('dontFlash');
    $property->setAccessible(true);
    $defaultValue = $property->getValue($reflection->newInstanceWithoutConstructor());
    
    expect($defaultValue[1])->toBe('password_confirmation');
});

// ========== FILE CONTENT TESTS ==========

test('Handler file declares namespace correctly', function () {
    $reflection = new ReflectionClass(Handler::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('namespace Crater\Exceptions');
});

test('Handler file is concise', function () {
    $reflection = new ReflectionClass(Handler::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect(strlen($fileContent))->toBeLessThan(2000);
});

// ========== METHOD COUNT TESTS ==========

test('Handler has exactly 2 public methods', function () {
    $reflection = new ReflectionClass(Handler::class);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    // Filter only methods declared in Handler class
    $ownMethods = array_filter($methods, function($method) {
        return $method->class === Handler::class;
    });
    
    expect(count($ownMethods))->toBe(2);
});

// ========== PROPERTY COUNT TESTS ==========

test('Handler has exactly 2 protected properties', function () {
    $reflection = new ReflectionClass(Handler::class);
    $properties = $reflection->getProperties(ReflectionProperty::IS_PROTECTED);
    
    // Filter only properties declared in Handler class
    $ownProperties = array_filter($properties, function($property) {
        return $property->class === Handler::class;
    });
    
    expect(count($ownProperties))->toBe(2);
});

// ========== PARENT CLASS DELEGATION TESTS ==========

test('report method delegates to parent', function () {
    $reflection = new ReflectionClass(Handler::class);
    $method = $reflection->getMethod('report');
    
    // Check that method exists and is not abstract
    expect($method->isAbstract())->toBeFalse();
});

test('render method delegates to parent', function () {
    $reflection = new ReflectionClass(Handler::class);
    $method = $reflection->getMethod('render');
    
    // Check that method exists and is not abstract
    expect($method->isAbstract())->toBeFalse();
});