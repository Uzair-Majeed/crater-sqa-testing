<?php

use Crater\Http\Requests\DeleteExpensesRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

test('it determines user is authorized to make the request', function () {
    $request = new DeleteExpensesRequest();
    expect($request->authorize())->toBeTrue();
});

test('it returns the correct validation rules', function () {
    $request = new DeleteExpensesRequest();
    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys(['ids', 'ids.*']);

    // Test 'ids' rules
    expect($rules['ids'])->toBeArray()
        ->and($rules['ids'])->toContain('required');

    // Test 'ids.*' rules
    expect($rules['ids.*'])->toBeArray()
        ->and($rules['ids.*'])->toContain('required');

    // Test the Rule::exists part for 'ids.*'
    $existsRule = collect($rules['ids.*'])->first(fn ($rule) => $rule instanceof Exists);

    expect($existsRule)->toBeInstanceOf(Exists::class);

    // Using reflection to check internal properties of Exists rule if necessary,
    // though typically just checking the instance is sufficient as Laravel tests its own rules.
    // For a more robust check:
    $reflection = new ReflectionClass($existsRule);
    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    $columnProperty = $reflection->getProperty('column');
    $columnProperty->setAccessible(true);

    expect($tableProperty->getValue($existsRule))->toBe('expenses')
        ->and($columnProperty->getValue($existsRule))->toBe('id');
});
