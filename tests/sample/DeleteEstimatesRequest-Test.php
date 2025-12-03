```php
<?php

use Crater\Http\Requests\DeleteEstimatesRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists; // Explicitly import for type hinting

beforeEach(function () {
    $this->request = new DeleteEstimatesRequest();
});

test('authorize method always returns true', function () {
    expect($this->request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules', function () {
    $expectedRules = [
        'ids' => [
            'required',
        ],
        'ids.*' => [
            'required',
            Rule::exists('estimates', 'id'),
        ],
    ];

    $actualRules = $this->request->rules();

    expect($actualRules)->toEqual($expectedRules);

    expect($actualRules)->toBeArray();
    expect($actualRules)->toHaveKeys(['ids', 'ids.*']);

    expect($actualRules['ids'])->toBeArray()
        ->and($actualRules['ids'])->toContain('required')
        ->and(count($actualRules['ids']))->toBe(1);

    expect($actualRules['ids.*'])->toBeArray()
        ->and($actualRules['ids.*'])->toContain('required');

    // Find the Rule::exists object within 'ids.*'
    $existsRule = collect($actualRules['ids.*'])->first(fn ($rule) => $rule instanceof Exists);

    expect($existsRule)->not->toBeNull('Rule::exists for estimates was not found in ids.* rules.')
        ->and($existsRule)->toBeInstanceOf(Exists::class);

    // Fix: The Exists object does NOT have getTable()/getColumn(), but its properties are protected.
    // Check using reflection instead.
    $reflection = new ReflectionClass($existsRule);
    $tableProp = $reflection->getProperty('table');
    $tableProp->setAccessible(true);
    $columnProp = $reflection->getProperty('column');
    $columnProp->setAccessible(true);

    expect($tableProp->getValue($existsRule))->toBe('estimates')
        ->and($columnProp->getValue($existsRule))->toBe('id');
});

afterEach(function () {
    Mockery::close();
});
```