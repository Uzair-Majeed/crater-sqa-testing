<?php

use Crater\Models\EmailLog;
use Illuminate\Database\Eloquent\Model;

// Test model can be instantiated
test('EmailLog can be instantiated', function () {
    $emailLog = new EmailLog();
    expect($emailLog)->toBeInstanceOf(EmailLog::class);
});

// Test model extends Model
test('EmailLog extends Model', function () {
    $emailLog = new EmailLog();
    expect($emailLog)->toBeInstanceOf(Model::class);
});

// Test model has mailable method
test('EmailLog has mailable method', function () {
    $emailLog = new EmailLog();
    expect(method_exists($emailLog, 'mailable'))->toBeTrue();
});

// Test model has isExpired method
test('EmailLog has isExpired method', function () {
    $emailLog = new EmailLog();
    expect(method_exists($emailLog, 'isExpired'))->toBeTrue();
});

// Test mailable method is public
test('mailable method is public', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    $method = $reflection->getMethod('mailable');
    
    expect($method->isPublic())->toBeTrue();
});

// Test isExpired method is public
test('isExpired method is public', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    $method = $reflection->getMethod('isExpired');
    
    expect($method->isPublic())->toBeTrue();
});

// Test model is in correct namespace
test('EmailLog is in correct namespace', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Models');
});

// Test model class name
test('EmailLog has correct class name', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    expect($reflection->getShortName())->toBe('EmailLog');
});

// Test model is not abstract
test('EmailLog is not abstract', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    expect($reflection->isAbstract())->toBeFalse();
});

// Test model is not an interface
test('EmailLog is not an interface', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    expect($reflection->isInterface())->toBeFalse();
});

// Test model is not a trait
test('EmailLog is not a trait', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    expect($reflection->isTrait())->toBeFalse();
});

// Test model is instantiable
test('EmailLog is instantiable', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// Test model uses HasFactory trait
test('EmailLog uses HasFactory trait', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
});

// Test model has guarded property
test('EmailLog has guarded property', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    expect($reflection->hasProperty('guarded'))->toBeTrue();
});

// Test mailable method has no parameters
test('mailable method has no required parameters', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    $method = $reflection->getMethod('mailable');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

// Test isExpired method has no parameters
test('isExpired method has no required parameters', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    $method = $reflection->getMethod('isExpired');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

// Test model uses correct imports
test('EmailLog uses required classes', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Carbon\Carbon')
        ->and($fileContent)->toContain('use Illuminate\Database\Eloquent\Factories\HasFactory')
        ->and($fileContent)->toContain('use Illuminate\Database\Eloquent\Model');
});

// Test multiple instances can be created
test('multiple EmailLog instances can be created', function () {
    $emailLog1 = new EmailLog();
    $emailLog2 = new EmailLog();
    
    expect($emailLog1)->toBeInstanceOf(EmailLog::class)
        ->and($emailLog2)->toBeInstanceOf(EmailLog::class)
        ->and($emailLog1)->not->toBe($emailLog2);
});

// Test model can be type-hinted
test('EmailLog can be used in type hints', function () {
    $testFunction = function (EmailLog $emailLog) {
        return $emailLog;
    };
    
    $emailLog = new EmailLog();
    $result = $testFunction($emailLog);
    
    expect($result)->toBe($emailLog);
});

// Test model is not final
test('EmailLog can be extended', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    expect($reflection->isFinal())->toBeFalse();
});

// Test methods are not static
test('mailable and isExpired methods are not static', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    
    expect($reflection->getMethod('mailable')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('isExpired')->isStatic())->toBeFalse();
});

// Test methods are not abstract
test('mailable and isExpired methods are not abstract', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    
    expect($reflection->getMethod('mailable')->isAbstract())->toBeFalse()
        ->and($reflection->getMethod('isExpired')->isAbstract())->toBeFalse();
});

// Test model file exists
test('EmailLog class is loaded', function () {
    expect(class_exists(EmailLog::class))->toBeTrue();
});

// Test model has expected number of public methods
test('EmailLog has expected public methods', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    $ownMethods = array_filter($publicMethods, function ($method) {
        return $method->class === EmailLog::class;
    });
    
    expect(count($ownMethods))->toBeGreaterThanOrEqual(2);
});

// Test model parent class
test('EmailLog parent class is Model', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe(Model::class);
});

// Test model namespace depth
test('EmailLog is in correct namespace depth', function () {
    $reflection = new ReflectionClass(EmailLog::class);
    $namespace = $reflection->getNamespaceName();
    $parts = explode('\\', $namespace);
    
    expect($parts)->toContain('Crater')
        ->and($parts)->toContain('Models');
});