<?php

use Illuminate\Http\Request;
use Crater\Models\Currency;
use Crater\Models\ExchangeRateProvider;
use Crater\Http\Controllers\V1\Admin\ExchangeRate\GetActiveProviderController;
use Illuminate\Support\Collection;
use Mockery\MockInterface;

test('it returns success when an active provider is found for the currency', function () {
    $currencyCode = 'USD';
    $mockCurrency = mock(Currency::class)->makePartial();
    $mockCurrency->code = $currencyCode;

    $mockQueryBuilder = Mockery::spy(\stdClass::class);
    $mockQueryBuilder->shouldReceive('whereJsonContains')
                     ->with('currencies', $currencyCode)
                     ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')
                     ->with('active', true)
                     ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('get')
                     ->andReturn(new Collection(['provider1'])); // Simulate finding a provider

    mock(ExchangeRateProvider::class, function (MockInterface $mock) use ($mockQueryBuilder) {
        $mock->shouldReceive('whereCompany')
             ->andReturn($mockQueryBuilder);
    });

    $request = Request::create('/');
    $controller = new GetActiveProviderController();

    $response = $controller->__invoke($request, $mockCurrency);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toMatchArray([
        'success' => true,
        'message' => 'provider_active',
    ]);
});

test('it returns error when no active provider is found for the currency', function () {
    $currencyCode = 'EUR';
    $mockCurrency = mock(Currency::class)->makePartial();
    $mockCurrency->code = $currencyCode;

    $mockQueryBuilder = Mockery::spy(\stdClass::class);
    $mockQueryBuilder->shouldReceive('whereJsonContains')
                     ->with('currencies', $currencyCode)
                     ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')
                     ->with('active', true)
                     ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('get')
                     ->andReturn(new Collection()); // Simulate no providers found

    mock(ExchangeRateProvider::class, function (MockInterface $mock) use ($mockQueryBuilder) {
        $mock->shouldReceive('whereCompany')
             ->andReturn($mockQueryBuilder);
    });

    $request = Request::create('/');
    $controller = new GetActiveProviderController();

    $response = $controller->__invoke($request, $mockCurrency);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toMatchArray([
        'error' => 'no_active_provider',
    ]);
});

test('it handles empty currency code gracefully when no provider is found', function () {
    $currencyCode = ''; // Empty currency code
    $mockCurrency = mock(Currency::class)->makePartial();
    $mockCurrency->code = $currencyCode;

    $mockQueryBuilder = Mockery::spy(\stdClass::class);
    $mockQueryBuilder->shouldReceive('whereJsonContains')
                     ->with('currencies', $currencyCode)
                     ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')
                     ->with('active', true)
                     ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('get')
                     ->andReturn(new Collection()); // No provider expected with empty code

    mock(ExchangeRateProvider::class, function (MockInterface $mock) use ($mockQueryBuilder) {
        $mock->shouldReceive('whereCompany')
             ->andReturn($mockQueryBuilder);
    });

    $request = Request::create('/');
    $controller = new GetActiveProviderController();

    $response = $controller->__invoke($request, $mockCurrency);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toMatchArray([
        'error' => 'no_active_provider',
    ]);
});

test('it calls exchange rate provider methods with correct arguments when an active provider is found', function () {
    $currencyCode = 'JPY';
    $mockCurrency = mock(Currency::class)->makePartial();
    $mockCurrency->code = $currencyCode;

    $mockQueryBuilder = Mockery::spy(\stdClass::class);
    $mockQueryBuilder->shouldReceive('whereJsonContains')
                     ->with('currencies', $currencyCode)
                     ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')
                     ->with('active', true)
                     ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('get')
                     ->andReturn(new Collection(['provider1']));

    mock(ExchangeRateProvider::class, function (MockInterface $mock) use ($mockQueryBuilder) {
        $mock->shouldReceive('whereCompany')
             ->andReturn($mockQueryBuilder)
             ->once();
    });

    $request = Request::create('/');
    $controller = new GetActiveProviderController();

    $controller->__invoke($request, $mockCurrency);

    $mockQueryBuilder->shouldHaveReceived('whereJsonContains')->with('currencies', $currencyCode)->once();
    $mockQueryBuilder->shouldHaveReceived('where')->with('active', true)->once();
    $mockQueryBuilder->shouldHaveReceived('get')->once();
});

test('it calls exchange rate provider methods with correct arguments when no active provider is found even if query returns empty', function () {
    $currencyCode = 'AUD';
    $mockCurrency = mock(Currency::class)->makePartial();
    $mockCurrency->code = $currencyCode;

    $mockQueryBuilder = Mockery::spy(\stdClass::class);
    $mockQueryBuilder->shouldReceive('whereJsonContains')
                     ->with('currencies', $currencyCode)
                     ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')
                     ->with('active', true)
                     ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('get')
                     ->andReturn(new Collection()); // Ensure no provider is found for this test

    mock(ExchangeRateProvider::class, function (MockInterface $mock) use ($mockQueryBuilder) {
        $mock->shouldReceive('whereCompany')
             ->andReturn($mockQueryBuilder)
             ->once();
    });

    $request = Request::create('/');
    $controller = new GetActiveProviderController();

    $controller->__invoke($request, $mockCurrency);

    $mockQueryBuilder->shouldHaveReceived('whereJsonContains')->with('currencies', $currencyCode)->once();
    $mockQueryBuilder->shouldHaveReceived('where')->with('active', true)->once();
    $mockQueryBuilder->shouldHaveReceived('get')->once();
});




afterEach(function () {
    Mockery::close();
});
