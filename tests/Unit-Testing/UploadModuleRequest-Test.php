<?php

use Crater\Http\Requests\UploadModuleRequest;

test('authorize method always returns true', function () {
    $request = new UploadModuleRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules', function () {
    $request = new UploadModuleRequest();
    $expectedRules = [
        'avatar' => [
            'required',
            'file',
            'mimes:zip',
            'max:20000'
        ],
        'module' => [
            'required',
            'string',
            'max:100'
        ]
    ];
    expect($request->rules())->toEqual($expectedRules);
});




afterEach(function () {
    Mockery::close();
});