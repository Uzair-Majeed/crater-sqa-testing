```php
<?php
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

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

    // Check for the Rule::exists part (support for both Rule::exists and Exists)
    $existsRuleFound = false;
    foreach ($rules['users.*'] as $rule) {
        // Handle Illuminate\Validation\Rules\Exists (Laravel >=9)
        if ($rule instanceof Exists) {
            $reflectionProperty = new ReflectionProperty($rule, 'table');
            $reflectionProperty->setAccessible(true);
            $table = $reflectionProperty->getValue($rule);

            $reflectionProperty = new ReflectionProperty($rule, 'column');
            $reflectionProperty->setAccessible(true);
            $column = $reflectionProperty->getValue($rule);

            if ($table === 'users' && $column === 'id') {
                $existsRuleFound = true;
                break;
            }
        }

        // Handle Illuminate\Validation\Rule (Laravel <=8)
        if ($rule instanceof Rule && method_exists($rule, '__toString')) {
            if ((string)$rule === 'exists:users,id') {
                $existsRuleFound = true;
                break;
            }
        }
        // Handle string fallback
        if (is_string($rule) && (stripos($rule, 'exists:users,id') !== false)) {
            $existsRuleFound = true;
            break;
        }
    }
    expect($existsRuleFound)->toBeTrue('Expected Rule::exists(\'users\', \'id\') not found for users.*');
});

afterEach(function () {
    Mockery::close();
});
```