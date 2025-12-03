<?php

use Crater\Http\Requests\UnzipUpdateRequest;
use Illuminate\Validation\Rule;

test('authorize method returns true', function () {
    $request = new UnzipUpdateRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules', function () {
    $request = new UnzipUpdateRequest();
    $rules = $request->rules();

    expect($rules)->toBeArray();
    expect($rules)->toHaveKeys(['path', 'module']);

    // Test 'path' rules
    expect($rules['path'])->toBeArray();
    expect($rules['path'])->toContain('required');
    expect($rules['path'])->toContain('regex:/^[\.\/\w\-]+$/');
    expect($rules['path'])->toHaveCount(2);

    // Test 'module' rules
    expect($rules['module'])->toBeArray();
    expect($rules['module'])->toContain('required');
    expect($rules['module'])->toContain('string');
    expect($rules['module'])->toHaveCount(2);
});




afterEach(function () {
    Mockery::close();
});
