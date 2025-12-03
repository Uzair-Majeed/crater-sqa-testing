<?php

test('authorize method always returns true', function () {
    $request = new \Crater\Http\Requests\SettingRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules', function () {
    $request = new \Crater\Http\Requests\SettingRequest();
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
