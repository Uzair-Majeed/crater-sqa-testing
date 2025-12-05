<?php

use Crater\Space\EnvironmentManager;
use Crater\Http\Requests\DatabaseEnvironmentRequest;
use Crater\Http\Requests\MailEnvironmentRequest;
use Crater\Http\Requests\DiskEnvironmentRequest;
use Crater\Http\Requests\DomainEnvironmentRequest;

// ========== CLASS STRUCTURE TESTS ==========

test('EnvironmentManager is in correct namespace', function () {
    $reflection = new ReflectionClass(EnvironmentManager::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Space');
});

test('EnvironmentManager is not abstract', function () {
    $reflection = new ReflectionClass(EnvironmentManager::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('EnvironmentManager is instantiable', function () {
    $reflection = new ReflectionClass(EnvironmentManager::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== CONSTRUCTOR TESTS ==========

test('constructor exists and is public', function () {
    $reflection = new ReflectionClass(EnvironmentManager::class);
    $constructor = $reflection->getConstructor();
    
    expect($constructor)->not->toBeNull()
        ->and($constructor->isPublic())->toBeTrue();
});

test('constructor has no required parameters', function () {
    $reflection = new ReflectionClass(EnvironmentManager::class);
    $constructor = $reflection->getConstructor();
    
    expect($constructor->getNumberOfParameters())->toBe(0);
});


test('EnvironmentManager has private helper methods', function () {
    $reflection = new ReflectionClass(EnvironmentManager::class);
    $privateMethods = $reflection->getMethods(ReflectionMethod::IS_PRIVATE);
    
    $ownPrivateMethods = array_filter($privateMethods, function ($method) {
        return $method->class === EnvironmentManager::class;
    });
    
    expect(count($ownPrivateMethods))->toBeGreaterThan(0);
});



// ========== CLASS CHARACTERISTICS TESTS ==========

test('EnvironmentManager is not final', function () {
    $reflection = new ReflectionClass(EnvironmentManager::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('EnvironmentManager is not an interface', function () {
    $reflection = new ReflectionClass(EnvironmentManager::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('EnvironmentManager is not a trait', function () {
    $reflection = new ReflectionClass(EnvironmentManager::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('EnvironmentManager class is loaded', function () {
    expect(class_exists(EnvironmentManager::class))->toBeTrue();
});



// ========== NAMESPACE TESTS ==========

test('EnvironmentManager is in correct namespace depth', function () {
    $reflection = new ReflectionClass(EnvironmentManager::class);
    $namespace = $reflection->getNamespaceName();
    $parts = explode('\\', $namespace);
    
    expect($parts)->toContain('Crater')
        ->and($parts)->toContain('Space')
        ->and(count($parts))->toBe(2);
});
