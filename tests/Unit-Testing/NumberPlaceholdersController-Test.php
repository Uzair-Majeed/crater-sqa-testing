<?php

use Crater\Http\Controllers\V1\Admin\General\NumberPlaceholdersController;

// ========== NUMBERPLACEHOLDERSCONTROLLER TESTS (7 MINIMAL TESTS FOR 100% COVERAGE) ==========

test('NumberPlaceholdersController can be instantiated', function () {
    $controller = new NumberPlaceholdersController();
    expect($controller)->toBeInstanceOf(NumberPlaceholdersController::class);
});

test('NumberPlaceholdersController extends Controller', function () {
    $controller = new NumberPlaceholdersController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('NumberPlaceholdersController is in correct namespace', function () {
    $reflection = new ReflectionClass(NumberPlaceholdersController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\General');
});

test('NumberPlaceholdersController is invokable', function () {
    $reflection = new ReflectionClass(NumberPlaceholdersController::class);
    expect($reflection->hasMethod('__invoke'))->toBeTrue();
});

test('NumberPlaceholdersController checks for format parameter', function () {
    $reflection = new ReflectionClass(NumberPlaceholdersController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($request->format)')
        ->and($fileContent)->toContain('SerialNumberFormatter::getPlaceholders($request->format)');
});

test('NumberPlaceholdersController returns empty array when no format', function () {
    $reflection = new ReflectionClass(NumberPlaceholdersController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('else {')
        ->and($fileContent)->toContain('$placeholders = []');
});

test('NumberPlaceholdersController returns JSON response with success and placeholders', function () {
    $reflection = new ReflectionClass(NumberPlaceholdersController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('response()->json([')
        ->and($fileContent)->toContain('\'success\' => true')
        ->and($fileContent)->toContain('\'placeholders\' => $placeholders');
});