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




afterEach(function () {
    Mockery::close();
});