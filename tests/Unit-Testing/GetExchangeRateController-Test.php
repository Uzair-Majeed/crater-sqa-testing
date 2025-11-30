<?php

use Crater\Http\Controllers\V1\Admin\ExchangeRate\GetExchangeRateController;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\ExchangeRateLog;
use Crater\Models\ExchangeRateProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
uses(\Mockery::class);

// Test Case 1: Exchange rate from provider is found and succeeds (status 200).
test('it returns exchange rate from provider if available and successful', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn(1);

    $inputCurrency = Mockery::mock(Currency::class);
    $inputCurrency->id = 100;
    $inputCurrency->code = 'USD';

    // Mock CompanySetting::getSettings
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSettings')
        ->with(['currency'], 1)
        ->andReturn(['currency' => 200]);

    // Mock Currency::findOrFail for base currency
    $baseCurrency = Mockery::mock(Currency::class);
    $baseCurrency->id = 200;
    $baseCurrency->code = 'EUR';
    Mockery::mock('alias:'.Currency::class)
        ->shouldReceive('findOrFail')
        ->with(200)
        ->andReturn($baseCurrency);

    // Mock ExchangeRateProvider query chain
    $providerQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $providerQueryBuilder->shouldReceive('whereJsonContains')->with('currencies', 'USD')->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('where')->with('active', true)->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('get')->andReturn(collect([
        ['key' => 'provider_key', 'driver' => 'fixer', 'driver_config' => ['api_key' => 'abc'], 'currencies' => ['USD']]
    ]));
    Mockery::mock('alias:'.ExchangeRateProvider::class)
        ->shouldReceive('whereJsonContains')
        ->andReturn($providerQueryBuilder);

    // Mock ExchangeRateLog query chain (should return null as provider takes precedence)
    $logQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder); // base_currency_id
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder); // currency_id
    $logQueryBuilder->shouldReceive('orderBy')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('value')->with('exchange_rate')->andReturn(null);
    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldReceive('where')
        ->andReturn($logQueryBuilder);

    // Mock the trait method getExchangeRate on a partial mock of the controller
    $controller = Mockery::mock(GetExchangeRateController::class . '[getExchangeRate]');
    $mockExchangeRateValue = Mockery::mock(Response::class);
    $mockExchangeRateValue->shouldReceive('status')->andReturn(200); // Simulate success
    $controller->shouldReceive('getExchangeRate')
        ->once()
        ->with(['key' => 'provider_key', 'driver' => 'fixer', 'driver_config' => ['api_key' => 'abc']], 'USD', 'EUR')
        ->andReturn($mockExchangeRateValue);

    // Act
    $response = $controller->__invoke($request, $inputCurrency);

    // Assert
    expect($response)->toBe($mockExchangeRateValue);
    Mockery::close();
});

// Test Case 2: Exchange rate from provider is found but fails (non-200 status), and no log entry exists.
test('it returns error if provider fails and no exchange rate log exists', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn(1);

    $inputCurrency = Mockery::mock(Currency::class);
    $inputCurrency->id = 100;
    $inputCurrency->code = 'USD';

    // Mock CompanySetting::getSettings
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSettings')
        ->with(['currency'], 1)
        ->andReturn(['currency' => 200]);

    // Mock Currency::findOrFail for base currency
    $baseCurrency = Mockery::mock(Currency::class);
    $baseCurrency->id = 200;
    $baseCurrency->code = 'EUR';
    Mockery::mock('alias:'.Currency::class)
        ->shouldReceive('findOrFail')
        ->with(200)
        ->andReturn($baseCurrency);

    // Mock ExchangeRateProvider query chain
    $providerQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $providerQueryBuilder->shouldReceive('whereJsonContains')->with('currencies', 'USD')->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('where')->with('active', true)->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('get')->andReturn(collect([
        ['key' => 'provider_key', 'driver' => 'fixer', 'driver_config' => ['api_key' => 'abc'], 'currencies' => ['USD']]
    ]));
    Mockery::mock('alias:'.ExchangeRateProvider::class)
        ->shouldReceive('whereJsonContains')
        ->andReturn($providerQueryBuilder);

    // Mock ExchangeRateLog query chain (should return null)
    $logQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('orderBy')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('value')->with('exchange_rate')->andReturn(null);
    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldReceive('where')
        ->andReturn($logQueryBuilder);

    // Mock the trait method getExchangeRate to return a non-200 status
    $controller = Mockery::mock(GetExchangeRateController::class . '[getExchangeRate]');
    $mockExchangeRateValue = Mockery::mock(Response::class);
    $mockExchangeRateValue->shouldReceive('status')->andReturn(400); // Simulate failure
    $controller->shouldReceive('getExchangeRate')
        ->once()
        ->with(['key' => 'provider_key', 'driver' => 'fixer', 'driver_config' => ['api_key' => 'abc']], 'USD', 'EUR')
        ->andReturn($mockExchangeRateValue);

    // Act
    $response = $controller->__invoke($request, $inputCurrency);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200) // The controller returns 200 even for no_exchange_rate_available
        ->and($response->getData(true))->toEqual(['error' => 'no_exchange_rate_available']);
    Mockery::close();
});

// Test Case 3: Exchange rate from provider is found but fails (non-200 status), but a log entry exists.
test('it returns exchange rate from log if provider fails but log exists', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn(1);

    $inputCurrency = Mockery::mock(Currency::class);
    $inputCurrency->id = 100;
    $inputCurrency->code = 'USD';

    // Mock CompanySetting::getSettings
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSettings')
        ->with(['currency'], 1)
        ->andReturn(['currency' => 200]);

    // Mock Currency::findOrFail for base currency
    $baseCurrency = Mockery::mock(Currency::class);
    $baseCurrency->id = 200;
    $baseCurrency->code = 'EUR';
    Mockery::mock('alias:'.Currency::class)
        ->shouldReceive('findOrFail')
        ->with(200)
        ->andReturn($baseCurrency);

    // Mock ExchangeRateProvider query chain
    $providerQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $providerQueryBuilder->shouldReceive('whereJsonContains')->with('currencies', 'USD')->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('where')->with('active', true)->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('get')->andReturn(collect([
        ['key' => 'provider_key', 'driver' => 'fixer', 'driver_config' => ['api_key' => 'abc'], 'currencies' => ['USD']]
    ]));
    Mockery::mock('alias:'.ExchangeRateProvider::class)
        ->shouldReceive('whereJsonContains')
        ->andReturn($providerQueryBuilder);

    // Mock ExchangeRateLog query chain (should return a value)
    $logQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('orderBy')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('value')->with('exchange_rate')->andReturn(1.23); // Log exists
    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldReceive('where')
        ->andReturn($logQueryBuilder);

    // Mock the trait method getExchangeRate to return a non-200 status
    $controller = Mockery::mock(GetExchangeRateController::class . '[getExchangeRate]');
    $mockExchangeRateValue = Mockery::mock(Response::class);
    $mockExchangeRateValue->shouldReceive('status')->andReturn(400); // Simulate failure
    $controller->shouldReceive('getExchangeRate')
        ->once()
        ->with(['key' => 'provider_key', 'driver' => 'fixer', 'driver_config' => ['api_key' => 'abc']], 'USD', 'EUR')
        ->andReturn($mockExchangeRateValue);

    // Act
    $response = $controller->__invoke($request, $inputCurrency);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['exchangeRate' => [1.23]]);
    Mockery::close();
});


// Test Case 4: No exchange rate provider found, but a log entry exists.
test('it returns exchange rate from log if no provider found', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn(1);

    $inputCurrency = Mockery::mock(Currency::class);
    $inputCurrency->id = 100;
    $inputCurrency->code = 'USD';

    // Mock CompanySetting::getSettings
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSettings')
        ->with(['currency'], 1)
        ->andReturn(['currency' => 200]);

    // Mock Currency::findOrFail for base currency
    $baseCurrency = Mockery::mock(Currency::class);
    $baseCurrency->id = 200;
    $baseCurrency->code = 'EUR';
    Mockery::mock('alias:'.Currency::class)
        ->shouldReceive('findOrFail')
        ->with(200)
        ->andReturn($baseCurrency);

    // Mock ExchangeRateProvider query chain (returns empty collection)
    $providerQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $providerQueryBuilder->shouldReceive('whereJsonContains')->with('currencies', 'USD')->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('where')->with('active', true)->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('get')->andReturn(collect([])); // No provider found
    Mockery::mock('alias:'.ExchangeRateProvider::class)
        ->shouldReceive('whereJsonContains')
        ->andReturn($providerQueryBuilder);

    // Mock ExchangeRateLog query chain (returns a value)
    $logQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('orderBy')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('value')->with('exchange_rate')->andReturn(1.23);
    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldReceive('where')
        ->andReturn($logQueryBuilder);

    // Create controller (no need to mock getExchangeRate as it won't be called)
    $controller = new GetExchangeRateController();

    // Act
    $response = $controller->__invoke($request, $inputCurrency);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['exchangeRate' => [1.23]]);
    Mockery::close();
});

// Test Case 5: Neither exchange rate provider nor log entry found.
test('it returns error if neither provider nor exchange rate log exists', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn(1);

    $inputCurrency = Mockery::mock(Currency::class);
    $inputCurrency->id = 100;
    $inputCurrency->code = 'USD';

    // Mock CompanySetting::getSettings
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSettings')
        ->with(['currency'], 1)
        ->andReturn(['currency' => 200]);

    // Mock Currency::findOrFail for base currency
    $baseCurrency = Mockery::mock(Currency::class);
    $baseCurrency->id = 200;
    $baseCurrency->code = 'EUR';
    Mockery::mock('alias:'.Currency::class)
        ->shouldReceive('findOrFail')
        ->with(200)
        ->andReturn($baseCurrency);

    // Mock ExchangeRateProvider query chain (returns empty collection)
    $providerQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $providerQueryBuilder->shouldReceive('whereJsonContains')->with('currencies', 'USD')->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('where')->with('active', true)->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('get')->andReturn(collect([])); // No provider found
    Mockery::mock('alias:'.ExchangeRateProvider::class)
        ->shouldReceive('whereJsonContains')
        ->andReturn($providerQueryBuilder);

    // Mock ExchangeRateLog query chain (returns null)
    $logQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('orderBy')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('value')->with('exchange_rate')->andReturn(null);
    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldReceive('where')
        ->andReturn($logQueryBuilder);

    // Create controller
    $controller = new GetExchangeRateController();

    // Act
    $response = $controller->__invoke($request, $inputCurrency);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['error' => 'no_exchange_rate_available']);
    Mockery::close();
});

// Test Case 6: Provider found, getExchangeRate returns 200, and log also exists (provider takes precedence)
test('it prioritizes provider result over log if provider succeeds', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn(1);

    $inputCurrency = Mockery::mock(Currency::class);
    $inputCurrency->id = 100;
    $inputCurrency->code = 'USD';

    // Mock CompanySetting::getSettings
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSettings')
        ->with(['currency'], 1)
        ->andReturn(['currency' => 200]);

    // Mock Currency::findOrFail for base currency
    $baseCurrency = Mockery::mock(Currency::class);
    $baseCurrency->id = 200;
    $baseCurrency->code = 'EUR';
    Mockery::mock('alias:'.Currency::class)
        ->shouldReceive('findOrFail')
        ->with(200)
        ->andReturn($baseCurrency);

    // Mock ExchangeRateProvider query chain
    $providerQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $providerQueryBuilder->shouldReceive('whereJsonContains')->with('currencies', 'USD')->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('where')->with('active', true)->andReturn($providerQueryBuilder);
    $providerQueryBuilder->shouldReceive('get')->andReturn(collect([
        ['key' => 'provider_key', 'driver' => 'fixer', 'driver_config' => ['api_key' => 'abc'], 'currencies' => ['USD']]
    ]));
    Mockery::mock('alias:'.ExchangeRateProvider::class)
        ->shouldReceive('whereJsonContains')
        ->andReturn($providerQueryBuilder);

    // Mock ExchangeRateLog query chain (returns a value, but should be ignored)
    $logQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('where')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('orderBy')->andReturn($logQueryBuilder);
    $logQueryBuilder->shouldReceive('value')->with('exchange_rate')->andReturn(1.50); // Log exists
    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldReceive('where')
        ->andReturn($logQueryBuilder);

    // Mock the trait method getExchangeRate on a partial mock of the controller
    $controller = Mockery::mock(GetExchangeRateController::class . '[getExchangeRate]');
    $mockExchangeRateValue = Mockery::mock(Response::class);
    $mockExchangeRateValue->shouldReceive('status')->andReturn(200); // Simulate success
    $controller->shouldReceive('getExchangeRate')
        ->once()
        ->with(['key' => 'provider_key', 'driver' => 'fixer', 'driver_config' => ['api_key' => 'abc']], 'USD', 'EUR')
        ->andReturn($mockExchangeRateValue);

    // Act
    $response = $controller->__invoke($request, $inputCurrency);

    // Assert
    expect($response)->toBe($mockExchangeRateValue);
    Mockery::close();
});
