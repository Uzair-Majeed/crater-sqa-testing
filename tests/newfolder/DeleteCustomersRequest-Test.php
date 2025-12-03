```php
<?php
test('authorize method always returns true', function () {
    $request = new \Crater\Http\Requests\DeleteCustomersRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules', function () {
    $request = new \Crater\Http\Requests\DeleteCustomersRequest();
    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->toHaveKeys(['ids', 'ids.*'])
        ->toHaveCount(2);

    // Test 'ids' rules
    expect($rules['ids'])->toBeArray()
        ->toEqual(['required']);

    // Test 'ids.*' rules
    expect($rules['ids.*'])->toBeArray()
        ->toHaveCount(2);
    expect($rules['ids.*'][0])->toBe('required');

    // Test the Rule::exists part
    $existsRule = $rules['ids.*'][1];
    expect($existsRule)->toBeInstanceOf(\Illuminate\Validation\Rules\Exists::class);
    // Instead of getTable and getColumn (not available), 
    // assert a string representation, or if possible, via toArray format.
    $existsArray = $existsRule->toArray();
    expect($existsArray['table'])->toBe('customers');
    expect($existsArray['column'])->toBe('id');
});

afterEach(function () {
    Mockery::close();
});
```