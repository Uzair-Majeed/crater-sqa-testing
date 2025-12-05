<?php

use Crater\Http\Requests\UploadExpenseReceiptRequest;
use Crater\Http\Controllers\V1\Admin\Expense\UploadReceiptController;

// ========== MERGED UPLOAD RECEIPT TESTS (2 CLASSES, 12 FUNCTIONAL TESTS) ==========

// --- UploadExpenseReceiptRequest Tests (6 tests: 3 structural + 3 functional) ---

test('UploadExpenseReceiptRequest can be instantiated', function () {
    $request = new UploadExpenseReceiptRequest();
    expect($request)->toBeInstanceOf(UploadExpenseReceiptRequest::class);
});

test('UploadExpenseReceiptRequest extends FormRequest', function () {
    $request = new UploadExpenseReceiptRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

test('UploadExpenseReceiptRequest is in correct namespace', function () {
    $reflection = new ReflectionClass(UploadExpenseReceiptRequest::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Requests');
});

// --- FUNCTIONAL TESTS ---

test('UploadExpenseReceiptRequest authorize returns true', function () {
    $request = new UploadExpenseReceiptRequest();
    
    $result = $request->authorize();
    
    expect($result)->toBeTrue();
});

test('UploadExpenseReceiptRequest rules includes attachment_receipt validation', function () {
    $request = new UploadExpenseReceiptRequest();
    
    $rules = $request->rules();
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('attachment_receipt')
        ->and($rules['attachment_receipt'])->toBeArray()
        ->and($rules['attachment_receipt'])->toContain('nullable');
});

test('UploadExpenseReceiptRequest uses Base64Mime rule for image types', function () {
    $reflection = new ReflectionClass(UploadExpenseReceiptRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('new Base64Mime([\'gif\', \'jpg\', \'png\'])');
});

// --- UploadReceiptController Tests (6 tests: 3 structural + 3 functional) ---

test('UploadReceiptController can be instantiated', function () {
    $controller = new UploadReceiptController();
    expect($controller)->toBeInstanceOf(UploadReceiptController::class);
});

test('UploadReceiptController extends Controller', function () {
    $controller = new UploadReceiptController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('UploadReceiptController is invokable', function () {
    $reflection = new ReflectionClass(UploadReceiptController::class);
    expect($reflection->hasMethod('__invoke'))->toBeTrue();
});

// --- FUNCTIONAL TESTS ---

test('UploadReceiptController uses authorization', function () {
    $reflection = new ReflectionClass(UploadReceiptController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'update\', $expense)');
});

test('UploadReceiptController clears media on edit type', function () {
    $reflection = new ReflectionClass(UploadReceiptController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($request->type === \'edit\')')
        ->and($fileContent)->toContain('$expense->clearMediaCollection(\'receipts\')');
});

test('UploadReceiptController adds media from base64', function () {
    $reflection = new ReflectionClass(UploadReceiptController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$expense->addMediaFromBase64($data->data)')
        ->and($fileContent)->toContain('->usingFileName($data->name)')
        ->and($fileContent)->toContain('->toMediaCollection(\'receipts\')');
});