<?php
test('authorize method returns true', function () {
    $request = new \Crater\Http\Requests\Customer\CustomerLoginRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct validation rules', function () {
    $request = new \Crater\Http\Requests\Customer\CustomerLoginRequest();

    $expectedRules = [
        'email' => [
            'required',
            'string'
        ],
        'password' => [
            'required',
            'string'
        ]
    ];

    expect($request->rules())->toEqual($expectedRules);
});

 

afterEach(function () {
    Mockery::close();
});
