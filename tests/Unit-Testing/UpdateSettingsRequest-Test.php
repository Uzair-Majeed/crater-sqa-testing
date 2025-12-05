<?php

use Crater\Http\Requests\UpdateSettingsRequest;

test('authorize method always returns true', function () {
    $request = new UpdateSettingsRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules for settings', function () {
    $request = new UpdateSettingsRequest();
    $expectedRules = [
        'settings' => [
            'required',
        ],
    ];
    expect($request->rules())->toEqual($expectedRules);
});




afterEach(function () {
    Mockery::close();
});