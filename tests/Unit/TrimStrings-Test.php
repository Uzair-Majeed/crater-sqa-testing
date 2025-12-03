<?php

use Crater\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\TrimStrings as BaseTrimStrings;


test('it extends the base TrimStrings middleware', function () {
    $middleware = new TrimStrings();
    expect($middleware)->toBeInstanceOf(BaseTrimStrings::class);
});

test('it correctly defines the attributes that should not be trimmed', function () {
    $middleware = new TrimStrings();

    // Use Reflection to access the protected 'except' property for white-box testing
    $reflectionClass = new ReflectionClass($middleware);
    $exceptProperty = $reflectionClass->getProperty('except');
    $exceptProperty->setAccessible(true); // Temporarily make the protected property accessible

    $except = $exceptProperty->getValue($middleware);

    // Assert that the 'except' array contains the expected attributes
    expect($except)
        ->toBeArray()
        ->toContain('password', 'password_confirmation')
        ->toHaveCount(2); // Verifies that only these two specific attributes are present as defined
});




afterEach(function () {
    Mockery::close();
});