<?php

use Crater\Http\Requests\GetSettingsRequest;
use Illuminate\Support\Facades\Validator;

test('authorize method always returns true', function () {
    $request = new GetSettingsRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules structure', function () {
    $request = new GetSettingsRequest();
    $expectedRules = [
        'settings' => [
            'required',
        ],
        'settings.*' => [
            'required',
            'string',
        ],
    ];

    expect($request->rules())->toEqual($expectedRules);
});

test('rules correctly validate valid input data', function () {
    $request = new GetSettingsRequest();
    $rules = $request->rules();

    $data = [
        'settings' => [
            'app_name' => 'Crater',
            'app_url' => 'http://localhost',
            'timezone' => 'UTC',
        ],
    ];

    $validator = Validator::make($data, $rules);

    expect($validator->passes())->toBeTrue();
});

test('rules correctly invalidate input when "settings" array is missing', function () {
    $request = new GetSettingsRequest();
    $rules = $request->rules();

    $data = []; // Missing 'settings' key

    $validator = Validator::make($data, $rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('settings'))->toBe('The settings field is required.');
});

test('rules correctly invalidate input when a setting value is null', function () {
    $request = new GetSettingsRequest();
    $rules = $request->rules();

    $data = [
        'settings' => [
            'app_name' => 'Crater',
            'app_url' => null, // Should fail 'required'
        ],
    ];

    $validator = Validator::make($data, $rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('settings.app_url'))->toBe('The settings.app_url field is required.');
});

test('rules correctly invalidate input when a setting value is not a string', function () {
    $request = new GetSettingsRequest();
    $rules = $request->rules();

    $data = [
        'settings' => [
            'app_name' => 'Crater',
            'app_url' => 123, // Should fail 'string'
        ],
    ];

    $validator = Validator::make($data, $rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('settings.app_url'))->toBe('The settings.app_url field must be a string.');
});

test('rules allow empty string values for settings as they are considered "string" and "present"', function () {
    $request = new GetSettingsRequest();
    $rules = $request->rules();

    $data = [
        'settings' => [
            'app_name' => '', // Empty string, but valid for required|string
            'app_url' => 'http://localhost',
        ],
    ];

    $validator = Validator::make($data, $rules);

    expect($validator->passes())->toBeTrue();
});

test('rules ignore settings keys that are not present in the input for "settings.*"', function () {
    // The 'settings.*' rule applies to *existing* values within the settings array.
    // It does not enforce that certain keys *must* be present, only that if they are, their values must be required and string.
    $request = new GetSettingsRequest();
    $rules = $request->rules();

    $data = [
        'settings' => [
            'app_name' => 'Crater',
            // 'app_url' is missing, but this is not validated by 'settings.*' because it doesn't exist to be validated.
        ],
    ];

    $validator = Validator::make($data, $rules);

    expect($validator->passes())->toBeTrue();
});
