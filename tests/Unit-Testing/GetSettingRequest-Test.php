<?php

use Crater\Http\Requests\GetSettingRequest;
use Illuminate\Support\Facades\Validator;

test('authorize method returns true', function () {
    $request = new GetSettingRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules structure', function () {
    $request = new GetSettingRequest();
    $expectedRules = [
        'key' => [
            'required',
            'string'
        ]
    ];
    expect($request->rules())->toEqual($expectedRules);
});

test('rules method successfully validates data with a valid string key', function () {
    $request = new GetSettingRequest();
    $data = ['key' => 'valid_setting_key'];
    $validator = Validator::make($data, $request->rules());
    expect($validator->passes())->toBeTrue();
});

test('rules method fails validation when the key is missing', function () {
    $request = new GetSettingRequest();
    $data = [];
    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('key'))->toBeTrue();
    expect($validator->errors()->first('key'))->toBe('The key field is required.');
});

test('rules method fails validation when the key is an empty string', function () {
    $request = new GetSettingRequest();
    $data = ['key' => ''];
    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('key'))->toBeTrue();
    expect($validator->errors()->first('key'))->toBe('The key field is required.');
});

test('rules method fails validation when the key is not a string (integer)', function () {
    $request = new GetSettingRequest();
    $data = ['key' => 123];
    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('key'))->toBeTrue();
    expect($validator->errors()->first('key'))->toBe('The key field must be a string.');
});

test('rules method fails validation when the key is not a string (boolean)', function () {
    $request = new GetSettingRequest();
    $data = ['key' => true];
    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('key'))->toBeTrue();
    expect($validator->errors()->first('key'))->toBe('The key field must be a string.');
});

test('rules method fails validation when the key is not a string (array)', function () {
    $request = new GetSettingRequest();
    $data = ['key' => ['an', 'array']];
    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('key'))->toBeTrue();
    expect($validator->errors()->first('key'))->toBe('The key field must be a string.');
});

test('rules method fails validation when the key is not a string (null)', function () {
    $request = new GetSettingRequest();
    $data = ['key' => null];
    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('key'))->toBeTrue();
    // Laravel's required rule often treats null as missing
    expect($validator->errors()->first('key'))->toBe('The key field is required.');
});




afterEach(function () {
    Mockery::close();
});
