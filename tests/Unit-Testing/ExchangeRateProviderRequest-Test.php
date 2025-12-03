<?php

use Crater\Http\Requests\ExchangeRateProviderRequest;
use Mockery as m;

test('authorize method always returns true', function () {
    $request = new ExchangeRateProviderRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns the correct validation rules', function () {
    $request = new ExchangeRateProviderRequest();

    $expectedRules = [
        'driver' => ['required'],
        'key' => ['required'],
        'currencies' => ['nullable'],
        'currencies.*' => ['nullable'],
        'driver_config' => ['nullable'],
        'active' => ['nullable', 'boolean'],
    ];

    expect($request->rules())->toEqual($expectedRules);
});

test('getExchangeRateProviderPayload returns the correct payload with company_id from header', function () {
    // Create a partial mock of ExchangeRateProviderRequest to mock `validated()` and `header()`
    // while allowing `getExchangeRateProviderPayload()` to execute its actual logic.
    $mockRequest = m::mock(ExchangeRateProviderRequest::class)
        ->makePartial();

    $validatedData = [
        'driver' => 'some_driver',
        'key' => 'some_key',
        'currencies' => ['USD', 'EUR'],
        'active' => true,
    ];
    $companyId = 123;

    $mockRequest->shouldReceive('validated')
        ->once()
        ->andReturn($validatedData);

    $mockRequest->shouldReceive('header')
        ->with('company')
        ->once()
        ->andReturn($companyId);

    $expectedPayload = array_merge($validatedData, ['company_id' => $companyId]);

    expect($mockRequest->getExchangeRateProviderPayload())->toEqual($expectedPayload);

    m::close(); // Clean up Mockery expectations
});

test('getExchangeRateProviderPayload handles empty validated data gracefully', function () {
    $mockRequest = m::mock(ExchangeRateProviderRequest::class)
        ->makePartial();

    $validatedData = []; // Simulate no validated data
    $companyId = 456;

    $mockRequest->shouldReceive('validated')
        ->once()
        ->andReturn($validatedData);

    $mockRequest->shouldReceive('header')
        ->with('company')
        ->once()
        ->andReturn($companyId);

    $expectedPayload = ['company_id' => $companyId]; // Only company_id should be present

    expect($mockRequest->getExchangeRateProviderPayload())->toEqual($expectedPayload);

    m::close();
});

test('getExchangeRateProviderPayload handles null company_id from header', function () {
    $mockRequest = m::mock(ExchangeRateProviderRequest::class)
        ->makePartial();

    $validatedData = [
        'driver' => 'another_driver',
        'key' => 'another_key',
    ];
    $companyId = null; // Simulate header not present or returning null

    $mockRequest->shouldReceive('validated')
        ->once()
        ->andReturn($validatedData);

    $mockRequest->shouldReceive('header')
        ->with('company')
        ->once()
        ->andReturn($companyId);

    $expectedPayload = array_merge($validatedData, ['company_id' => $companyId]);

    expect($mockRequest->getExchangeRateProviderPayload())->toEqual($expectedPayload);

    m::close();
});

test('getExchangeRateProviderPayload handles complete validated data and null company_id', function () {
    $mockRequest = m::mock(ExchangeRateProviderRequest::class)
        ->makePartial();

    $validatedData = [
        'driver' => 'complete_driver',
        'key' => 'complete_key',
        'currencies' => ['AUD'],
        'driver_config' => ['endpoint' => 'some_url'],
        'active' => false,
    ];
    $companyId = null;

    $mockRequest->shouldReceive('validated')
        ->once()
        ->andReturn($validatedData);

    $mockRequest->shouldReceive('header')
        ->with('company')
        ->once()
        ->andReturn($companyId);

    $expectedPayload = array_merge($validatedData, ['company_id' => $companyId]);

    expect($mockRequest->getExchangeRateProviderPayload())->toEqual($expectedPayload);

    m::close();
});




afterEach(function () {
    Mockery::close();
});
