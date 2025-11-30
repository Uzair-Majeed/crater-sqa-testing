<?php

use Crater\Http\Requests\SendEstimatesRequest;
use Illuminate\Validation\Rule;

test('authorize method always returns true', function () {
    $request = new SendEstimatesRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules', function () {
    $request = new SendEstimatesRequest();
    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys(['subject', 'body', 'from', 'to'])
        ->and($rules['subject'])->toEqual(['required'])
        ->and($rules['body'])->toEqual(['required'])
        ->and($rules['from'])->toEqual(['required'])
        ->and($rules['to'])->toEqual(['required']);
});
