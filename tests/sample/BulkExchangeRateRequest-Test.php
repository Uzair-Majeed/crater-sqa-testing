<?php

use Crater\Http\Requests\BulkExchangeRateRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Container\Container;

beforeEach(function () {
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

    $data = [];

    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('currencies'))->toBeTrue();
});

test('validation fails when currencies array is empty', function () {
    $request = new BulkExchangeRateRequest();
    $rules = $request->rules();

    $data = ['currencies' => []];

    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('currencies'))->toBeTrue();
});

test('validation fails when currency id is missing', function () {
    $request = new BulkExchangeRateRequest();
    $rules = $request->rules();

    $data = [
        'currencies' => [
            ['exchange_rate' => 1.23],
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
            ['id' => 'abc', 'exchange_rate' => 1.23],
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
            ['id' => 1],
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
    if (class_exists('Mockery')) {
        \Mockery::close();
    }
});