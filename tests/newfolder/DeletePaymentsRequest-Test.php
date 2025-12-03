```php
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
        ->toContain('required');

    // Assert rules for 'ids.*'
    expect($rules['ids.*'])
        ->toBeArray();

    // Make sure there's a 'required' rule in 'ids.*'
    expect($rules['ids.*'])->toContain('required');

    // Try to find a Rule::exists or Exists instance in 'ids.*'
    $existsRule = collect($rules['ids.*'])->first(function ($rule) {
        return $rule instanceof Exists || (
            $rule instanceof Rule &&
            method_exists($rule, 'toArray') &&
            isset($rule->toArray()['table']) &&
            $rule->toArray()['table'] === 'payments'
        );
    });

    // If not found, try to find via string if the rule was specified as a string (fallback edge case)
    if (!$existsRule) {
        $existsRule = collect($rules['ids.*'])->first(function ($rule) {
            return $rule instanceof Rule || $rule instanceof Exists;
        });
    }

    expect($existsRule)
        ->not->toBeNull('Rule::exists rule was not found in ids.* array.');

    // Check the instance
    expect($existsRule)->toBeInstanceOf(Exists::class);

    // Reflection: check 'table' and 'column'
    $reflection = new ReflectionClass($existsRule);

    $tableProperty = $reflection->hasProperty('table')
        ? $reflection->getProperty('table')
        : ($reflection->hasProperty('tableName')
            ? $reflection->getProperty('tableName')
            : null);

    expect($tableProperty)->not->toBeNull();
    $tableProperty->setAccessible(true);

    // Payment table check
    expect($tableProperty->getValue($existsRule))->toBe('payments');

    // Column property check
    $columnProperty = $reflection->hasProperty('column')
        ? $reflection->getProperty('column')
        : ($reflection->hasProperty('columnName')
            ? $reflection->getProperty('columnName')
            : null);

    expect($columnProperty)->not->toBeNull();
    $columnProperty->setAccessible(true);

    expect($columnProperty->getValue($existsRule))->toBe('id');
});

afterEach(function () {
    Mockery::close();
});
```