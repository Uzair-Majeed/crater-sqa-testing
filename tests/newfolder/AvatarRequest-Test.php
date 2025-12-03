<?php

use Crater\Http\Requests\AvatarRequest;
use Crater\Rules\Base64Mime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// The test file should usually be in tests/Unit, so dependencies are typically autoloaded.
// If testing a FormRequest directly, it might need to mock parts of the Laravel container
// if it relies on specific services during instantiation, but for authorize/rules, it's usually straightforward.

test('it extends Illuminate FormRequest', function () {
    $request = new AvatarRequest();
    expect($request)->toBeInstanceOf(FormRequest::class);
});

test('authorize method always returns true', function () {
    $request = new AvatarRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules', function () {
    $request = new AvatarRequest();
    $rules = $request->rules();

    // Assert the structure and content of the returned rules array
    expect($rules)->toBeArray()
        ->toHaveKey('admin_avatar')
        ->toHaveKey('avatar');

    // Assert rules for 'admin_avatar'
    expect($rules['admin_avatar'])->toBeArray()
        ->toContain('nullable')
        ->toContain('file')
        ->toContain('mimes:gif,jpg,png')
        ->toContain('max:20000');
    
    // Assert rules for 'avatar'
    expect($rules['avatar'])->toBeArray()
        ->toContain('nullable');
    
    // Assert the custom Base64Mime rule for 'avatar'
    $base64MimeRule = collect($rules['avatar'])->first(fn ($rule) => $rule instanceof Base64Mime);
    expect($base64MimeRule)->not->toBeNull()
        ->toBeInstanceOf(Base64Mime::class);
    
    // Use reflection to check the private or protected property of Base64Mime
    // Try both 'mimes' and 'allowedMimes' (as the property name may differ in implementation)
    $reflectionProp = null;
    if (property_exists($base64MimeRule, 'allowedMimes')) {
        $reflectionProp = new ReflectionProperty($base64MimeRule, 'allowedMimes');
    } elseif (property_exists($base64MimeRule, 'mimes')) {
        $reflectionProp = new ReflectionProperty($base64MimeRule, 'mimes');
    } elseif (property_exists($base64MimeRule, 'mimeTypes')) {
        $reflectionProp = new ReflectionProperty($base64MimeRule, 'mimeTypes');
    }
    expect($reflectionProp)->not->toBeNull();

    $reflectionProp->setAccessible(true);
    $allowedMimes = $reflectionProp->getValue($base64MimeRule);

    expect($allowedMimes)->toBeArray()
        ->toEqualCanonicalized(['gif', 'jpg', 'png']);
});

// If there were protected/private methods, we would use reflection:
// For example, if there was a private method `getCommonRules()`
// test('private method getCommonRules returns expected rules', function () {
//     $request = new AvatarRequest();
//     $reflection = new ReflectionMethod(AvatarRequest::class, 'getCommonRules');
//     $reflection->setAccessible(true);
//
//     $result = $reflection->invoke($request);
//     expect($result)->toEqual(['some_rule']);
// });



afterEach(function () {
    Mockery::close();
});