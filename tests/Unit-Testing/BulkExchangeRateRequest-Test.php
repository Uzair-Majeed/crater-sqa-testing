<?php

use Crater\Http\Requests\BulkExchangeRateRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Container\Container;

// It's good practice to set up a basic application container for FormRequest tests
// if they rely on services like the validator.
beforeEach(function () {
    // We need to bind a validator factory to the container, as FormRequest
    // internally uses the container to resolve the validator.
    $app = new Container();
    $app->singleton('validator', function ($app) {
        return new Illuminate\Validation\Factory(
            new Illuminate\Translation\Translator(
                new Illuminate\Translation\ArrayLoader(),
                'en'
            )
        );
    });
    Container::setInstance($app);

    // Also, usually FormRequest is resolved via the container,
    // which injects dependencies. For these simple methods,
    // direct instantiation is fine, but for full validation,
    // the container setup is crucial.
});

test('authorize method always returns true', function () {
    $request = new BulkExchangeRateRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules', function () {
    $request = new BulkExchangeRateRequest();

    $expectedRules = [
        'currencies' => [
            'required'
        ],
        'currencies.*.id' => [
            'required',
            'numeric'
        ],
        'currencies.*.exchange_rate' => [
            'required'
        ]
    ];

    expect($request->rules())->toEqual($expectedRules);
});

// Although the request specifies "Focus ONLY on unit-level white-box testing of the class/functions/methods.
// Do NOT generate tests for HTTP endpoints, routes, middleware, authorization, or full database/framework integration.",
// testing the *application* of the rules returned by `rules()` can be considered part of
// "logic coverage" for the overall FormRequest behavior, without being a full integration test.
// We are essentially unit testing that the *rules themselves* are functionally correct
// when used with Laravel's Validator, given that `rules()` is the only method supplying them.

test('validation passes with valid currencies data', function () {
    $request = new BulkExchangeRateRequest();
    $rules = $request->rules();

    $data = [
        'currencies' => [
            ['id' => 1, 'exchange_rate' => 1.23],
            ['id' => 2, 'exchange_rate' => 0.98],
        ],
    ];

    $validator = Validator::make($data, $rules);
    expect($validator->passes())->toBeTrue();
});

test('validation fails when currencies array is missing', function () {
    $request = new BulkExchangeRateRequest();
    $rules = $request->rules();

    $data = []; // Missing 'currencies'

    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('currencies'))->toBeTrue();
});

test('validation fails when currencies array is empty', function () {
    $request = new BulkExchangeRateRequest();
    $rules = $request->rules();

    $data = ['currencies' => []]; // Empty 'currencies' array

    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('currencies'))->toBeTrue();
});


test('validation fails when currency id is missing', function () {
    $request = new BulkExchangeRateRequest();
    $rules = $request->rules();

    $data = [
        'currencies' => [
            ['exchange_rate' => 1.23], // 'id' is missing
        ],
    ];

    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('currencies.0.id'))->toBeTrue();
});

test('validation fails when currency id is not numeric', function () {
    $request = new BulkExchangeRateRequest();
    $rules = $request->rules();

    $data = [
        'currencies' => [
            ['id' => 'abc', 'exchange_rate' => 1.23], // 'id' is not numeric
        ],
    ];

    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('currencies.0.id'))->toBeTrue();
});

test('validation fails when currency exchange_rate is missing', function () {
    $request = new BulkExchangeRateRequest();
    $rules = $request->rules();

    $data = [
        'currencies' => [
            ['id' => 1], // 'exchange_rate' is missing
        ],
    ];

    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('currencies.0.exchange_rate'))->toBeTrue();
});

test('validation fails when multiple currency items have issues', function () {
    $request = new BulkExchangeRateRequest();
    $rules = $request->rules();

    $data = [
        'currencies' => [
            ['id' => 'abc', 'exchange_rate' => 1.23], // Invalid ID
            ['id' => 2], // Missing exchange_rate
            ['exchange_rate' => 4.56], // Missing ID
        ],
    ];

    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('currencies.0.id'))->toBeTrue();
    expect($validator->errors()->has('currencies.1.exchange_rate'))->toBeTrue();
    expect($validator->errors()->has('currencies.2.id'))->toBeTrue();
});

 

afterEach(function () {
    Mockery::close();
});
