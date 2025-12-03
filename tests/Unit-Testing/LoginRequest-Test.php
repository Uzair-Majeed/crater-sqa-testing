<?php

test('authorize method always returns true', function () {
        $request = new \Crater\Http\Requests\LoginRequest();
        expect($request->authorize())->toBeTrue();
    });

    // Test for the rules method
    test('rules method returns the correct validation rules', function () {
        $request = new \Crater\Http\Requests\LoginRequest();
        $rules = $request->rules();

        // Assert that the returned array contains the expected keys
        expect($rules)->toHaveKeys(['username', 'password', 'device_name']);

        // Assert the validation rules for each field
        expect($rules['username'])->toEqual(['required']);
        expect($rules['password'])->toEqual(['required']);
        expect($rules['device_name'])->toEqual(['required']);

        // Assert that no other unexpected rules are present (white-box specific)
        expect(count($rules))->toBe(3);
    });




afterEach(function () {
    Mockery::close();
});
