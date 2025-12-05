<?php

use Crater\Rules\RelationNotExist;
use Mockery as m;

afterEach(function () {
    m::close();
});

// ========== RELATIONNOTEXIST TESTS (12 TESTS: STRUCTURAL + FUNCTIONAL) ==========

// --- Structural Tests (5 tests) ---

test('RelationNotExist can be instantiated', function () {
    $rule = new RelationNotExist();
    expect($rule)->toBeInstanceOf(RelationNotExist::class);
});

test('RelationNotExist implements Rule interface', function () {
    $rule = new RelationNotExist();
    expect($rule)->toBeInstanceOf(\Illuminate\Contracts\Validation\Rule::class);
});

test('RelationNotExist is in correct namespace', function () {
    $reflection = new ReflectionClass(RelationNotExist::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Rules');
});

test('RelationNotExist has public class and relation properties', function () {
    $reflection = new ReflectionClass(RelationNotExist::class);
    
    expect($reflection->hasProperty('class'))->toBeTrue()
        ->and($reflection->hasProperty('relation'))->toBeTrue();
    
    $classProperty = $reflection->getProperty('class');
    $relationProperty = $reflection->getProperty('relation');
    
    expect($classProperty->isPublic())->toBeTrue()
        ->and($relationProperty->isPublic())->toBeTrue();
});

test('RelationNotExist has required methods', function () {
    $reflection = new ReflectionClass(RelationNotExist::class);
    
    expect($reflection->hasMethod('__construct'))->toBeTrue()
        ->and($reflection->hasMethod('passes'))->toBeTrue()
        ->and($reflection->hasMethod('message'))->toBeTrue();
});

// --- Functional Tests (7 tests) ---

test('RelationNotExist constructor sets class and relation properties', function () {
    $rule = new RelationNotExist('SomeClass', 'someRelation');
    
    expect($rule->class)->toBe('SomeClass')
        ->and($rule->relation)->toBe('someRelation');
});

test('RelationNotExist constructor accepts null values', function () {
    $rule = new RelationNotExist(null, null);
    
    expect($rule->class)->toBeNull()
        ->and($rule->relation)->toBeNull();
});

test('RelationNotExist constructor works with no arguments', function () {
    $rule = new RelationNotExist();
    
    expect($rule->class)->toBeNull()
        ->and($rule->relation)->toBeNull();
});

test('RelationNotExist message returns correct format', function () {
    $rule = new RelationNotExist('TestClass', 'testRelation');
    
    $message = $rule->message();
    
    expect($message)->toBe('Relation testRelation exists.');
});

test('RelationNotExist message handles null relation', function () {
    $rule = new RelationNotExist('TestClass', null);
    
    $message = $rule->message();
    
    expect($message)->toBe('Relation  exists.');
});

test('RelationNotExist passes returns true when relation does not exist', function () {
    // Create a mock model instance
    $mockRelation = m::mock();
    $mockRelation->shouldReceive('exists')->once()->andReturn(false);
    
    $mockModel = m::mock();
    $mockModel->shouldReceive('testRelation')->once()->andReturn($mockRelation);
    
    // Create a mock class
    $mockClass = m::mock('alias:TestModelClass');
    $mockClass->shouldReceive('find')->with(123)->once()->andReturn($mockModel);
    
    $rule = new RelationNotExist('TestModelClass', 'testRelation');
    
    $result = $rule->passes('attribute', 123);
    
    expect($result)->toBeTrue();
});

test('RelationNotExist passes returns false when relation exists', function () {
    // Create a mock model instance
    $mockRelation = m::mock();
    $mockRelation->shouldReceive('exists')->once()->andReturn(true);
    
    $mockModel = m::mock();
    $mockModel->shouldReceive('testRelation')->once()->andReturn($mockRelation);
    
    // Create a mock class
    $mockClass = m::mock('alias:AnotherModelClass');
    $mockClass->shouldReceive('find')->with(456)->once()->andReturn($mockModel);
    
    $rule = new RelationNotExist('AnotherModelClass', 'testRelation');
    
    $result = $rule->passes('attribute', 456);
    
    expect($result)->toBeFalse();
});