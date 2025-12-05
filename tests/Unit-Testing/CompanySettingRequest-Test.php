<?php
use Crater\Http\Requests\CompanySettingRequest;

test('the authorization method always returns true for CompanySettingRequest', function () {
    $request = new CompanySettingRequest();

    expect($request->authorize())->toBeTrue();
});

test('CompanySettingRequest returns the correct validation rules', function () {
    $expectedRules = [
        'currency' => [
            'required',
        ],
        'time_zone' => [
            'required',
        ],
        'language' => [
            'required',
        ],
        'fiscal_year' => [
            'required',
        ],
        'moment_date_format' => [
            'required',
        ],
        'carbon_date_format' => [
            'required',
        ],
    ];

    $request = new CompanySettingRequest();

    expect($request->rules())->toEqual($expectedRules);
});

afterEach(function () {
    if (class_exists('Mockery')) {
        \Mockery::close();
    }
});