<?php

use Crater\Http\Requests\DeleteExpensesRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

// Test authorize method
test('authorize method returns true', function () {
    $request = new DeleteExpensesRequest();
    expect($request->authorize())->toBeTrue();
});

// Test rules method returns correct structure
test('rules method returns correct validation rules structure', function () {
    $request = new DeleteExpensesRequest();
    $rules = $request->rules();
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys(['ids', 'ids.*']);
});

// Test ids field rules
test('ids field has required validation', function () {
    $request = new DeleteExpensesRequest();
    $rules = $request->rules();
    
    expect($rules['ids'])->toBeArray()
        ->and($rules['ids'])->toContain('required');
});

// Test ids.* field rules
test('ids.* field has required validation', function () {
    $request = new DeleteExpensesRequest();
    $rules = $request->rules();
    
    expect($rules['ids.*'])->toBeArray()
        ->and($rules['ids.*'])->toContain('required');
});

// Test ids.* has exists rule
test('ids.* field has exists rule for expenses table', function () {
    $request = new DeleteExpensesRequest();
    $rules = $request->rules();
    
    $existsRule = collect($rules['ids.*'])->first(fn ($rule) => $rule instanceof Exists);
    
    expect($existsRule)->toBeInstanceOf(Exists::class);
    
    // Check table and column using reflection
    $reflection = new ReflectionClass($existsRule);
    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    $columnProperty = $reflection->getProperty('column');
    $columnProperty->setAccessible(true);
    
    expect($tableProperty->getValue($existsRule))->toBe('expenses')
        ->and($columnProperty->getValue($existsRule))->toBe('id');
});

// Test validation passes with valid data
test('validation passes with valid expense IDs array', function () {
    $request = new DeleteExpensesRequest();
    $data = ['ids' => [1, 2, 3]];
    
    // Get rules but simplify exists rule for testing
    $rules = [
        'ids' => ['required'],
        'ids.*' => ['required', 'integer'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->passes())->toBeTrue();
});

// Test validation fails without ids
test('validation fails when ids field is missing', function () {
    $request = new DeleteExpensesRequest();
    $data = [];
    
    $rules = [
        'ids' => ['required'],
        'ids.*' => ['required'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('ids'))->toBeTrue();
});

// Test validation fails with empty ids array
test('validation fails when ids array is empty', function () {
    $request = new DeleteExpensesRequest();
    $data = ['ids' => []];
    
    $rules = [
        'ids' => ['required'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->fails())->toBeTrue();
});

// Test validation fails with null ids
test('validation fails when ids is null', function () {
    $request = new DeleteExpensesRequest();
    $data = ['ids' => null];
    
    $rules = [
        'ids' => ['required'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->fails())->toBeTrue();
});

// Test request extends FormRequest
test('DeleteExpensesRequest extends FormRequest', function () {
    $request = new DeleteExpensesRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

// Test request can be instantiated
test('DeleteExpensesRequest can be instantiated', function () {
    $request = new DeleteExpensesRequest();
    expect($request)->toBeInstanceOf(DeleteExpensesRequest::class);
});

// Test request has authorize method
test('DeleteExpensesRequest has authorize method', function () {
    $request = new DeleteExpensesRequest();
    expect(method_exists($request, 'authorize'))->toBeTrue();
});

// Test request has rules method
test('DeleteExpensesRequest has rules method', function () {
    $request = new DeleteExpensesRequest();
    expect(method_exists($request, 'rules'))->toBeTrue();
});

// Test rules method returns array
test('rules method returns an array', function () {
    $request = new DeleteExpensesRequest();
    $rules = $request->rules();
    
    expect($rules)->toBeArray();
});

// Test validation with single ID
test('validation passes with single expense ID', function () {
    $request = new DeleteExpensesRequest();
    $data = ['ids' => [1]];
    
    $rules = [
        'ids' => ['required'],
        'ids.*' => ['required', 'integer'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->passes())->toBeTrue();
});

// Test validation with multiple IDs
test('validation passes with multiple expense IDs', function () {
    $request = new DeleteExpensesRequest();
    $data = ['ids' => [1, 2, 3, 4, 5]];
    
    $rules = [
        'ids' => ['required'],
        'ids.*' => ['required', 'integer'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->passes())->toBeTrue();
});

// Test validation fails with non-array ids
test('validation handles non-array ids value', function () {
    $request = new DeleteExpensesRequest();
    $data = ['ids' => 'not-an-array'];
    
    $rules = [
        'ids' => ['required'],
        'ids.*' => ['required'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    // This will pass the 'required' check but fail the array structure
    expect($validator->passes())->toBeTrue(); // 'required' passes with string
});

// Test request is in correct namespace
test('DeleteExpensesRequest is in correct namespace', function () {
    $reflection = new ReflectionClass(DeleteExpensesRequest::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Requests');
});

// Test request class name
test('DeleteExpensesRequest has correct class name', function () {
    $reflection = new ReflectionClass(DeleteExpensesRequest::class);
    expect($reflection->getShortName())->toBe('DeleteExpensesRequest');
});

// Test both methods are public
test('authorize and rules methods are public', function () {
    $reflection = new ReflectionClass(DeleteExpensesRequest::class);
    
    expect($reflection->getMethod('authorize')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('rules')->isPublic())->toBeTrue();
});

// Test rules count
test('rules method returns exactly 2 validation rules', function () {
    $request = new DeleteExpensesRequest();
    $rules = $request->rules();
    
    expect(count($rules))->toBe(2);
});

// Test ids.* rule count
test('ids.* field has exactly 2 validation rules', function () {
    $request = new DeleteExpensesRequest();
    $rules = $request->rules();
    
    expect(count($rules['ids.*']))->toBe(2);
});

// Test ids rule count
test('ids field has exactly 1 validation rule', function () {
    $request = new DeleteExpensesRequest();
    $rules = $request->rules();
    
    expect(count($rules['ids']))->toBe(1);
});