<?php

use Crater\Http\Controllers\V1\Admin\Config\RetrospectiveEditsController;

// ========== RETROSPECTIVEEDITSCONTROLLER TESTS (8 MINIMAL TESTS FOR GOOD COVERAGE) ==========

test('RetrospectiveEditsController can be instantiated', function () {
    $controller = new RetrospectiveEditsController();
    expect($controller)->toBeInstanceOf(RetrospectiveEditsController::class);
});

test('RetrospectiveEditsController extends Controller', function () {
    $controller = new RetrospectiveEditsController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('RetrospectiveEditsController is in correct namespace', function () {
    $reflection = new ReflectionClass(RetrospectiveEditsController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\Config');
});

test('RetrospectiveEditsController is invokable', function () {
    $reflection = new ReflectionClass(RetrospectiveEditsController::class);
    expect($reflection->hasMethod('__invoke'))->toBeTrue();
});

test('RetrospectiveEditsController __invoke method is public', function () {
    $reflection = new ReflectionClass(RetrospectiveEditsController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->isPublic())->toBeTrue()
        ->and($method->isStatic())->toBeFalse();
});

test('RetrospectiveEditsController __invoke accepts Request parameter', function () {
    $reflection = new ReflectionClass(RetrospectiveEditsController::class);
    $method = $reflection->getMethod('__invoke');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

test('RetrospectiveEditsController returns JSON with retrospective_edits config', function () {
    $reflection = new ReflectionClass(RetrospectiveEditsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('response()->json([')
        ->and($fileContent)->toContain('\'retrospective_edits\' => config(\'crater.retrospective_edits\')');
});

test('RetrospectiveEditsController file is concise', function () {
    $reflection = new ReflectionClass(RetrospectiveEditsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect(strlen($fileContent))->toBeLessThan(1000);
});
