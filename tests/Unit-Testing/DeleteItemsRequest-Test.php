<?php

use Crater\Http\Requests\DeleteItemsRequest;
use Crater\Models\Item;
use Crater\Rules\RelationNotExist;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Exists;

// Test authorize method
test('authorize method returns true', function () {
    $request = new DeleteItemsRequest();
    expect($request->authorize())->toBeTrue();
});

// Test rules structure
test('rules method returns correct structure', function () {
    $request = new DeleteItemsRequest();
    $rules = $request->rules();
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys(['ids', 'ids.*']);
});

// Test ids field rules
test('ids field has required validation', function () {
    $request = new DeleteItemsRequest();
    $rules = $request->rules();
    
    expect($rules['ids'])->toBeArray()
        ->and($rules['ids'])->toContain('required');
});

// Test ids.* field rules
test('ids.* field has required validation', function () {
    $request = new DeleteItemsRequest();
    $rules = $request->rules();
    
    expect($rules['ids.*'])->toBeArray()
        ->and($rules['ids.*'])->toContain('required');
});

// Test Exists rule
test('ids.* has exists rule for items table', function () {
    $request = new DeleteItemsRequest();
    $rules = $request->rules();
    
    $existsRule = collect($rules['ids.*'])->first(fn ($rule) => $rule instanceof Exists);
    
    expect($existsRule)->toBeInstanceOf(Exists::class);
    
    $reflection = new ReflectionClass($existsRule);
    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    $columnProperty = $reflection->getProperty('column');
    $columnProperty->setAccessible(true);
    
    expect($tableProperty->getValue($existsRule))->toBe('items')
        ->and($columnProperty->getValue($existsRule))->toBe('id');
});

// Test RelationNotExist rules
test('ids.* has three RelationNotExist rules', function () {
    $request = new DeleteItemsRequest();
    $rules = $request->rules();
    
    $relationNotExistRules = collect($rules['ids.*'])->filter(fn ($rule) => $rule instanceof RelationNotExist);
    
    expect($relationNotExistRules)->toHaveCount(3);
});

// Test validation passes with valid data
test('validation passes with valid item IDs', function () {
    $data = ['ids' => [1, 2, 3]];
    
    $rules = [
        'ids' => ['required'],
        'ids.*' => ['required', 'integer'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->passes())->toBeTrue();
});

// Test validation fails without ids
test('validation fails when ids is missing', function () {
    $data = [];
    
    $rules = [
        'ids' => ['required'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->fails())->toBeTrue();
});

// Test validation fails with empty array
test('validation fails when ids array is empty', function () {
    $data = ['ids' => []];
    
    $rules = [
        'ids' => ['required'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->fails())->toBeTrue();
});

// Test request extends FormRequest
test('DeleteItemsRequest extends FormRequest', function () {
    $request = new DeleteItemsRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

// Test request can be instantiated
test('DeleteItemsRequest can be instantiated', function () {
    $request = new DeleteItemsRequest();
    expect($request)->toBeInstanceOf(DeleteItemsRequest::class);
});

// Test request has required methods
test('DeleteItemsRequest has authorize and rules methods', function () {
    $request = new DeleteItemsRequest();
    
    expect(method_exists($request, 'authorize'))->toBeTrue()
        ->and(method_exists($request, 'rules'))->toBeTrue();
});

// Test rules method returns array
test('rules method returns array', function () {
    $request = new DeleteItemsRequest();
    $rules = $request->rules();
    
    expect($rules)->toBeArray();
});

// Test total rule count for ids.*
test('ids.* has exactly 5 validation rules', function () {
    $request = new DeleteItemsRequest();
    $rules = $request->rules();
    
    expect(count($rules['ids.*']))->toBe(5);
});

// Test request namespace
test('DeleteItemsRequest is in correct namespace', function () {
    $reflection = new ReflectionClass(DeleteItemsRequest::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Requests');
});

// Test request class name
test('DeleteItemsRequest has correct class name', function () {
    $reflection = new ReflectionClass(DeleteItemsRequest::class);
    expect($reflection->getShortName())->toBe('DeleteItemsRequest');
});

// Test methods are public
test('authorize and rules methods are public', function () {
    $reflection = new ReflectionClass(DeleteItemsRequest::class);
    
    expect($reflection->getMethod('authorize')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('rules')->isPublic())->toBeTrue();
});

// Test validation with single ID
test('validation passes with single item ID', function () {
    $data = ['ids' => [1]];
    
    $rules = [
        'ids' => ['required'],
        'ids.*' => ['required', 'integer'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->passes())->toBeTrue();
});

// Test validation with multiple IDs
test('validation passes with multiple item IDs', function () {
    $data = ['ids' => [1, 2, 3, 4, 5]];
    
    $rules = [
        'ids' => ['required'],
        'ids.*' => ['required', 'integer'],
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->passes())->toBeTrue();
});

// Test rule types
test('ids.* contains correct rule types', function () {
    $request = new DeleteItemsRequest();
    $rules = $request->rules();
    
    $hasString = false;
    $hasExists = false;
    $hasRelationNotExist = false;
    
    foreach ($rules['ids.*'] as $rule) {
        if (is_string($rule)) $hasString = true;
        if ($rule instanceof Exists) $hasExists = true;
        if ($rule instanceof RelationNotExist) $hasRelationNotExist = true;
    }
    
    expect($hasString)->toBeTrue()
        ->and($hasExists)->toBeTrue()
        ->and($hasRelationNotExist)->toBeTrue();
});