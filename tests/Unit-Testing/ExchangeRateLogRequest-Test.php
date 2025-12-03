<?php

use Mockery as m;
use Crater\Models\CompanySetting;
use Crater\Http\Requests\ExchangeRateLogRequest;

// Using beforeEach and afterEach for Mockery cleanup and static mock setup.
beforeEach(function () {
    m::close();
    // Mock the static methods of CompanySetting to isolate the request logic
    m::mock('alias:'.CompanySetting::class);
});

afterEach(function () {
    m::close();
});

test('authorize returns true', function () {
    $request = new ExchangeRateLogRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules returns correct validation rules', function () {
    $request = new ExchangeRateLogRequest();
    $expectedRules = [
        'exchange_rate' => ['required'],
        'currency_id' => ['required'],
    ];
    expect($request->rules())->toEqual($expectedRules);
});

test('getExchangeRateLogPayload returns null when request currency id matches company currency id (same type)', function () {
    $companyId = 'test-company-id-1';
    $companyCurrencyId = 100;
    $requestCurrencyId = 100; // Matches company currency, same type

    // Use a partial mock to allow calling original methods while mocking others
    $request = m::partialMock(ExchangeRateLogRequest::class);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->once();
    // `validated()` is called by `collect($this->validated())`, but its content
    // is not used if the condition for returning null is met.
    $request->shouldReceive('validated')->andReturn([]);
    $request->currency_id = $requestCurrencyId;

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrencyId)
        ->once();

    expect($request->getExchangeRateLogPayload())->toBeNull();
});

test('getExchangeRateLogPayload returns null when request currency id matches company currency id (same type, string values)', function () {
    $companyId = 'test-company-id-2';
    $companyCurrencyId = 'USD';
    $requestCurrencyId = 'USD'; // Matches company currency, same type (string)

    $request = m::partialMock(ExchangeRateLogRequest::class);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->once();
    $request->shouldReceive('validated')->andReturn([]);
    $request->currency_id = $requestCurrencyId;

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrencyId)
        ->once();

    expect($request->getExchangeRateLogPayload())->toBeNull();
});

test('getExchangeRateLogPayload returns payload when request currency id differs from company currency id (different integer values)', function () {
    $companyId = 'test-company-id-3';
    $companyCurrencyId = 100;
    $requestCurrencyId = 200; // Differs from company currency
    $validatedData = [
        'exchange_rate' => 1.25,
        'currency_id' => $requestCurrencyId,
        'additional_field' => 'some_value'
    ];

    $request = m::partialMock(ExchangeRateLogRequest::class);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->once();
    $request->shouldReceive('validated')
        ->andReturn($validatedData)
        ->once();
    $request->currency_id = $requestCurrencyId;

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrencyId)
        ->once();

    $expectedPayload = array_merge($validatedData, [
        'company_id' => $companyId,
        'base_currency_id' => $companyCurrencyId,
    ]);

    expect($request->getExchangeRateLogPayload())->toEqual($expectedPayload);
});

test('getExchangeRateLogPayload returns payload when request currency id differs from company currency id (different types, same value)', function () {
    $companyId = 'test-company-id-4';
    $companyCurrencyId = 100;
    $requestCurrencyId = '100'; // Same value, but different type (int vs string), so !== considers them different
    $validatedData = ['exchange_rate' => 1.0, 'currency_id' => $requestCurrencyId];

    $request = m::partialMock(ExchangeRateLogRequest::class);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->once();
    $request->shouldReceive('validated')
        ->andReturn($validatedData)
        ->once();
    $request->currency_id = $requestCurrencyId;

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrencyId)
        ->once();

    $expectedPayload = array_merge($validatedData, [
        'company_id' => $companyId,
        'base_currency_id' => $companyCurrencyId,
    ]);
    expect($request->getExchangeRateLogPayload())->toEqual($expectedPayload);
});

test('getExchangeRateLogPayload handles empty validated data when currency id differs', function () {
    $companyId = 'test-company-id-5';
    $companyCurrencyId = 100;
    $requestCurrencyId = 200; // Differs from company currency
    $validatedData = []; // Empty validated data

    $request = m::partialMock(ExchangeRateLogRequest::class);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->once();
    $request->shouldReceive('validated')
        ->andReturn($validatedData)
        ->once();
    $request->currency_id = $requestCurrencyId;

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrencyId)
        ->once();

    $expectedPayload = [
        'company_id' => $companyId,
        'base_currency_id' => $companyCurrencyId,
    ];

    expect($request->getExchangeRateLogPayload())->toEqual($expectedPayload);
});

test('getExchangeRateLogPayload handles null company header when currency id differs', function () {
    $companyId = null; // Simulate header not being present or returning null
    $companyCurrencyId = 100;
    $requestCurrencyId = 200; // Differs
    $validatedData = ['exchange_rate' => 1.0];

    $request = m::partialMock(ExchangeRateLogRequest::class);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->once();
    $request->shouldReceive('validated')
        ->andReturn($validatedData)
        ->once();
    $request->currency_id = $requestCurrencyId;

    // CompanySetting::getSetting should be called with null or whatever header returns
    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId) // Expect null to be passed as company ID
        ->andReturn($companyCurrencyId)
        ->once();

    $expectedPayload = array_merge($validatedData, [
        'company_id' => $companyId, // company_id should be null
        'base_currency_id' => $companyCurrencyId,
    ]);

    expect($request->getExchangeRateLogPayload())->toEqual($expectedPayload);
});

test('getExchangeRateLogPayload handles null company header when currency id matches', function () {
    $companyId = null;
    $companyCurrencyId = 100;
    $requestCurrencyId = 100; // Matches
    $validatedData = ['exchange_rate' => 1.0];

    $request = m::partialMock(ExchangeRateLogRequest::class);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->once();
    $request->shouldReceive('validated')->andReturn($validatedData); // Not used in this path
    $request->currency_id = $requestCurrencyId;

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrencyId)
        ->once();

    expect($request->getExchangeRateLogPayload())->toBeNull();
});

test('getExchangeRateLogPayload handles company setting returning null for base currency when ids differ', function () {
    $companyId = 'test-company-id-6';
    $companyCurrencyId = null; // CompanySetting returns null for the base currency
    $requestCurrencyId = 200; // Differs
    $validatedData = ['exchange_rate' => 1.0, 'currency_id' => $requestCurrencyId];

    $request = m::partialMock(ExchangeRateLogRequest::class);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->once();
    $request->shouldReceive('validated')
        ->andReturn($validatedData)
        ->once();
    $request->currency_id = $requestCurrencyId;

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($companyCurrencyId)
        ->once();

    $expectedPayload = array_merge($validatedData, [
        'company_id' => $companyId,
        'base_currency_id' => $companyCurrencyId, // Expect null here
    ]);

    expect($request->getExchangeRateLogPayload())->toEqual($expectedPayload);
});



