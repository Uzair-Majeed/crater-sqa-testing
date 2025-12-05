<?php

test('authorize method always returns true', function () {
    $request = new \Crater\Http\Requests\SettingKeyRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct validation rules for key', function () {
    $request = new \Crater\Http\Requests\SettingKeyRequest();
    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('key')
        ->and($rules['key'])->toBeArray()
        ->and($rules['key'])->toContain('required');
});

test('validation passes when the key is provided', function () {
    $request = new \Crater\Http\Requests\SettingKeyRequest();
    $data = ['key' => 'some_valid_key_string'];

    $validator = \Illuminate\Support\Facades\Validator::make($data, $request->rules());

    expect($validator->passes())->toBeTrue();
    expect($validator->errors()->all())->toBeEmpty();
});

test('validation fails when the key is missing', function () {
    $request = new \Crater\Http\Requests\SettingKeyRequest();
    $data = []; // 'key' is missing

    $validator = \Illuminate\Support\Facades\Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('key'))->toBeTrue();
    expect($validator->errors()->first('key'))->toBe('The key field is required.');
});

test('validation fails when the key is an empty string', function () {
    $request = new \Crater\Http\Requests\SettingKeyRequest();
    $data = ['key' => '']; // 'key' is an empty string

    $validator = \Illuminate\Support\Facades\Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('key'))->toBeTrue();
    expect($validator->errors()->first('key'))->toBe('The key field is required.');
});

test('validation fails when the key is null', function () {
    $request = new \Crater\Http\Requests\SettingKeyRequest();
    $data = ['key' => null]; // 'key' is null

    $validator = \Illuminate\Support\Facades\Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('key'))->toBeTrue();
    expect($validator->errors()->first('key'))->toBe('The key field is required.');
});




afterEach(function () {
    Mockery::close();
});