<?php
test('it always authorizes the request', function () {
    $request = new \Crater\Http\Requests\DeleteUserRequest();
    expect($request->authorize())->toBeTrue();
});

test('it returns the correct validation rules for deleting users', function () {
    $request = new \Crater\Http\Requests\DeleteUserRequest();
    $rules = $request->rules();

    // Assert that 'users' key exists and contains 'required'
    expect($rules)->toHaveKey('users');
    expect($rules['users'])->toContain('required');

    // Assert that 'users.*' key exists and contains 'required' and the Rule::exists
    expect($rules)->toHaveKey('users.*');
    expect($rules['users.*'])->toContain('required');

    // Check for the Rule::exists part
    $existsRuleFound = false;
    foreach ($rules['users.*'] as $rule) {
        if ($rule instanceof \Illuminate\Validation\Rules\ExistsRule) {
            $reflectionProperty = new \ReflectionProperty($rule, 'table');
            $reflectionProperty->setAccessible(true);
            $table = $reflectionProperty->getValue($rule);

            $reflectionProperty = new \ReflectionProperty($rule, 'column');
            $reflectionProperty->setAccessible(true);
            $column = $reflectionProperty->getValue($rule);

            if ($table === 'users' && $column === 'id') {
                $existsRuleFound = true;
                break;
            }
        }
    }
    expect($existsRuleFound)->toBeTrue('Expected Rule::exists(\'users\', \'id\') not found for users.*');
});




afterEach(function () {
    Mockery::close();
});
