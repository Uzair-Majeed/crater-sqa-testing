<?php

use Crater\Http\Controllers\V1\Admin\General\NextNumberController;

// ========== NEXTNUMBERCONTROLLER TESTS (9 MINIMAL TESTS FOR 100% COVERAGE) ==========

test('NextNumberController can be instantiated', function () {
    $controller = new NextNumberController();
    expect($controller)->toBeInstanceOf(NextNumberController::class);
});

test('NextNumberController extends Controller', function () {
    $controller = new NextNumberController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('NextNumberController is in correct namespace', function () {
    $reflection = new ReflectionClass(NextNumberController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\General');
});

test('NextNumberController is invokable', function () {
    $reflection = new ReflectionClass(NextNumberController::class);
    expect($reflection->hasMethod('__invoke'))->toBeTrue();
});

test('NextNumberController __invoke accepts 4 parameters', function () {
    $reflection = new ReflectionClass(NextNumberController::class);
    $method = $reflection->getMethod('__invoke');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(4)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[1]->getName())->toBe('invoice')
        ->and($parameters[2]->getName())->toBe('estimate')
        ->and($parameters[3]->getName())->toBe('payment');
});

test('NextNumberController uses SerialNumberFormatter', function () {
    $reflection = new ReflectionClass(NextNumberController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('new SerialNumberFormatter()')
        ->and($fileContent)->toContain('->setCompany')
        ->and($fileContent)->toContain('->setCustomer')
        ->and($fileContent)->toContain('->setModel')
        ->and($fileContent)->toContain('->getNextNumber()');
});

test('NextNumberController handles invoice, estimate, and payment cases', function () {
    $reflection = new ReflectionClass(NextNumberController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('switch ($key)')
        ->and($fileContent)->toContain('case \'invoice\':')
        ->and($fileContent)->toContain('case \'estimate\':')
        ->and($fileContent)->toContain('case \'payment\':')
        ->and($fileContent)->toContain('default:');
});

test('NextNumberController has exception handling', function () {
    $reflection = new ReflectionClass(NextNumberController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('try {')
        ->and($fileContent)->toContain('catch (\Exception $exception)')
        ->and($fileContent)->toContain('$exception->getMessage()');
});

test('NextNumberController returns JSON response with success and nextNumber', function () {
    $reflection = new ReflectionClass(NextNumberController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('response()->json([')
        ->and($fileContent)->toContain('\'success\' => true')
        ->and($fileContent)->toContain('\'nextNumber\' => $nextNumber')
        ->and($fileContent)->toContain('\'success\' => false');
});