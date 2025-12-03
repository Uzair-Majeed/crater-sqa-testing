<?php

use Crater\Http\Requests\SendPaymentRequest;

test('authorize method always returns true', function () {
    $request = new SendPaymentRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules', function () {
    $request = new SendPaymentRequest();
    $expectedRules = [
        'subject' => ['required'],
        'body' => ['required'],
        'from' => ['required'],
        'to' => ['required'],
    ];
    expect($request->rules())->toEqual($expectedRules);
});




afterEach(function () {
    Mockery::close();
});
