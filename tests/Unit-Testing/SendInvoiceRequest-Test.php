<?php

test('authorize method always returns true', function () {
    $request = new \Crater\Http\Requests\SendInvoiceRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the expected validation rules', function () {
    $request = new \Crater\Http\Requests\SendInvoiceRequest();
    $expectedRules = [
        'body' => ['required'],
        'subject' => ['required'],
        'from' => ['required'],
        'to' => ['required'],
    ];
    expect($request->rules())->toEqual($expectedRules);
});




afterEach(function () {
    Mockery::close();
});
