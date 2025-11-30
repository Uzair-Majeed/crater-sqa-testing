<?php
use Crater\Http\Requests\DeletePaymentsRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;


test('authorize method returns true', function () {
    $request = new DeletePaymentsRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct validation rules', function () {
    $request = new DeletePaymentsRequest();
    $rules = $request->rules();

    // Assert the overall structure of the rules array
    expect($rules)->toBeArray()
        ->toHaveKeys(['ids', 'ids.*']);

    // Assert rules for 'ids'
    expect($rules['ids'])
        ->toBeArray()
        ->toHaveCount(1)
        ->toContain('required');

    // Assert rules for 'ids.*'
    expect($rules['ids.*'])
        ->toBeArray()
        ->toHaveCount(2)
        ->toContain('required');

    // Find and assert the Rule::exists instance within 'ids.*'
    $existsRule = collect($rules['ids.*'])->first(fn ($rule) => $rule instanceof Rule);

    expect($existsRule)
        ->not->toBeNull('Rule::exists rule was not found in ids.* array.')
        ->toBeInstanceOf(Exists::class);

    // Perform white-box testing on the Rule::exists object using reflection
    // to verify its internal table and column properties.
    $reflection = new ReflectionClass($existsRule);

    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    expect($tableProperty->getValue($existsRule))->toBe('payments');

    $columnProperty = $reflection->getProperty('column');
    $columnProperty->setAccessible(true);
    expect($columnProperty->getValue($existsRule))->toBe('id');
});
