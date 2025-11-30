<?php

use Crater\Http\Requests\DeleteEstimatesRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists; // Explicitly import for type hinting

beforeEach(function () {
    // Instantiate the request directly. For these specific methods (authorize, rules),
    // there's no complex interaction with the underlying FormRequest or Laravel's container
    // that would require extensive mocking of the parent class or its dependencies.
    $this->request = new DeleteEstimatesRequest();
});

test('authorize method always returns true', function () {
    // This method has no internal logic or conditions, so a direct assertion of its return value suffices.
    expect($this->request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules', function () {
    // Define the expected array of validation rules, including the Rule::exists object.
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

    // Assert that the returned rules match the expected structure and values deeply.
    // `toEqual` handles the comparison of Rule objects correctly by value.
    expect($actualRules)->toEqual($expectedRules);

    // Further specific assertions for clarity and robustness, even if `toEqual` covers most cases.
    expect($actualRules)->toBeArray();
    expect($actualRules)->toHaveKeys(['ids', 'ids.*']);

    // Assertions for the 'ids' rule
    expect($actualRules['ids'])->toBeArray()
        ->and($actualRules['ids'])->toContain('required')
        ->and(count($actualRules['ids']))->toBe(1); // Ensure no extra rules for 'ids'

    // Assertions for the 'ids.*' rule
    expect($actualRules['ids.*'])->toBeArray()
        ->and($actualRules['ids.*'])->toContain('required');

    // Explicitly check for the Rule::exists object within 'ids.*' rules.
    // This demonstrates white-box testing by inspecting the properties of the returned Rule object.
    $existsRule = collect($actualRules['ids.*'])->first(fn ($rule) => $rule instanceof Exists);

    expect($existsRule)->not->toBeNull('Rule::exists for estimates was not found in ids.* rules.')
        ->and($existsRule)->toBeInstanceOf(Exists::class);

    // Verify the specific configuration of the Rule::exists object.
    // The `getTable` and `getColumn` methods are public on the Exists rule object.
    expect($existsRule->getTable())->toBe('estimates')
        ->and($existsRule->getColumn())->toBe('id');
});
