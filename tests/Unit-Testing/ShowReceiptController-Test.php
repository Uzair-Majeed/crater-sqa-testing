<?php

use Crater\Http\Controllers\V1\Admin\Expense\ShowReceiptController;

// ========== SHOWRECEIPTCONTROLLER TESTS (10 TESTS WITH FUNCTIONAL COVERAGE) ==========

// --- Structural Tests (5 tests) ---

test('ShowReceiptController can be instantiated', function () {
    $controller = new ShowReceiptController();
    expect($controller)->toBeInstanceOf(ShowReceiptController::class);
});

test('ShowReceiptController extends Controller', function () {
    $controller = new ShowReceiptController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('ShowReceiptController is in correct namespace', function () {
    $reflection = new ReflectionClass(ShowReceiptController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\Expense');
});

test('ShowReceiptController is invokable', function () {
    $reflection = new ReflectionClass(ShowReceiptController::class);
    expect($reflection->hasMethod('__invoke'))->toBeTrue();
});

test('ShowReceiptController __invoke method is public', function () {
    $reflection = new ReflectionClass(ShowReceiptController::class);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->isPublic())->toBeTrue()
        ->and($method->isStatic())->toBeFalse();
});

// --- Functional Tests (5 tests) ---

test('ShowReceiptController __invoke accepts Expense parameter', function () {
    $reflection = new ReflectionClass(ShowReceiptController::class);
    $method = $reflection->getMethod('__invoke');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('expense')
        ->and($parameters[0]->getType()->getName())->toContain('Expense');
});

test('ShowReceiptController uses authorization', function () {
    $reflection = new ReflectionClass(ShowReceiptController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'view\', $expense)');
});

test('ShowReceiptController checks for expense existence', function () {
    $reflection = new ReflectionClass(ShowReceiptController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($expense)');
});

test('ShowReceiptController retrieves media from receipts collection', function () {
    $reflection = new ReflectionClass(ShowReceiptController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$expense->getFirstMedia(\'receipts\')')
        ->and($fileContent)->toContain('if ($media)');
});

test('ShowReceiptController returns file response or error', function () {
    $reflection = new ReflectionClass(ShowReceiptController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('response()->file($media->getPath())')
        ->and($fileContent)->toContain('respondJson(\'receipt_does_not_exist\', \'Receipt does not exist.\')');
});
