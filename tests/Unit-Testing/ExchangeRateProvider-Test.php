<?php

use Carbon\Carbon;
use Crater\Http\Requests\ExchangeRateProviderRequest;
use Crater\Models\Company;
use Crater\Models\ExchangeRateProvider;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Facades\Response;
use Mockery as m;

// Mock the global respondJson helper function
// This mock allows capturing arguments passed to respondJson and verifying its behavior,
// assuming it internally calls Laravel's response()->json() or returns a similar JsonResponse.
if (! function_exists('respondJson')) {
    function respondJson($message, $description = null, $status = 400)
    {
        // For testing purposes, we delegate to Laravel's response()->json()
        // This allows us to mock the `Response` facade and verify interactions.
        return response()->json([
            'message' => $message,
            'description' => $description ?? $message
        ], $status);
    }
}

beforeEach(function () {
    // Ensure Mockery is closed before each test to prevent conflicts
    m::close();
});

test('it belongs to a company', function () {
    $exchangeRateProvider = new ExchangeRateProvider();

    expect($exchangeRateProvider->company())->toBeInstanceOf(BelongsTo::class);
    expect($exchangeRateProvider->company()->getRelated())->toBeInstanceOf(Company::class);
    expect($exchangeRateProvider->company()->getForeignKeyName())->toBe('company_id');
});

test('setCurrenciesAttribute json encodes the value', function () {
    $exchangeRateProvider = new ExchangeRateProvider();
    $currencies = ['USD', 'EUR'];
    $exchangeRateProvider->setCurrenciesAttribute($currencies);

    // Use reflection to access the protected 'attributes' property
    $reflectionProperty = new ReflectionProperty($exchangeRateProvider, 'attributes');
    $reflectionProperty->setAccessible(true);
    $attributes = $reflectionProperty->getValue($exchangeRateProvider);

    expect($attributes['currencies'])->toBe(json_encode($currencies));
    // Verify the accessor also works (Eloquent casting)
    expect($exchangeRateProvider->currencies)->toBe($currencies);
});

test('setDriverConfigAttribute json encodes the value', function () {
    $exchangeRateProvider = new ExchangeRateProvider();
    $driverConfig = ['apiKey' => 'test_key', 'baseUrl' => 'test_url'];
    $exchangeRateProvider->setDriverConfigAttribute($driverConfig);

    // Use reflection to access the protected 'attributes' property
    $reflectionProperty = new ReflectionProperty($exchangeRateProvider, 'attributes');
    $reflectionProperty->setAccessible(true);
    $attributes = $reflectionProperty->getValue($exchangeRateProvider);

    expect($attributes['driver_config'])->toBe(json_encode($driverConfig));
    // Verify the accessor also works (Eloquent casting)
    expect($exchangeRateProvider->driver_config)->toBe($driverConfig);
});

test('scopeWhereCompany adds a where clause for the company_id from request header', function () {
    RequestFacade::shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn(1);

    $mockQuery = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockQuery->shouldReceive('where')
        ->once()
        ->with('exchange_rate_providers.company_id', 1)
        ->andReturn($mockQuery); // Return self for chaining

    // Call the scope method statically
    ExchangeRateProvider::scopeWhereCompany($mockQuery);
});

test('createFromRequest creates an exchange rate provider with the correct payload', function () {
    $payload = ['name' => 'Test Provider', 'driver' => 'currency_freak', 'active' => true];

    $mockRequest = m::mock(ExchangeRateProviderRequest::class);
    $mockRequest->shouldReceive('getExchangeRateProviderPayload')
        ->once()
        ->andReturn($payload);

    // Mock the static 'create' method on the ExchangeRateProvider model itself
    // Use an alias mock to override the static method for this test.
    $mockExchangeRateProvider = m::mock('overload:' . ExchangeRateProvider::class);
    $mockExchangeRateProvider->shouldReceive('create')
        ->once()
        ->with($payload)
        ->andReturn((new ExchangeRateProvider())->forceFill($payload + ['id' => 1])); // Simulate a created model

    $exchangeRateProvider = ExchangeRateProvider::createFromRequest($mockRequest);

    expect($exchangeRateProvider)->toBeInstanceOf(ExchangeRateProvider::class);
    expect($exchangeRateProvider->name)->toBe($payload['name']);
    expect($exchangeRateProvider->driver)->toBe($payload['driver']);
});

test('updateFromRequest updates the exchange rate provider with the correct payload', function () {
    $payload = ['name' => 'Updated Provider', 'driver' => 'currency_layer', 'active' => false];

    $mockRequest = m::mock(ExchangeRateProviderRequest::class);
    $mockRequest->shouldReceive('getExchangeRateProviderPayload')
        ->once()
        ->andReturn($payload);

    // Create a partial mock for the model instance to mock its `update` method
    $exchangeRateProvider = m::mock(ExchangeRateProvider::class)->makePartial();
    $exchangeRateProvider->id = 1; // Assign an ID for realism
    $exchangeRateProvider->shouldReceive('update')
        ->once()
        ->with($payload)
        ->andReturn(true); // Simulate successful update

    $result = $exchangeRateProvider->updateFromRequest($mockRequest);

    expect($result)->toBe($exchangeRateProvider);
});

test('checkActiveCurrencies returns providers matching active and currencies', function () {
    $mockRequest = (object)['currencies' => ['USD', 'EUR']]; // Simulate a request object

    $mockQueryResult = collect([
        (new ExchangeRateProvider())->forceFill(['id' => 1, 'currencies' => ['USD', 'EUR'], 'active' => true]),
        (new ExchangeRateProvider())->forceFill(['id' => 2, 'currencies' => ['USD', 'EUR', 'GBP'], 'active' => true]),
    ]);

    $mockQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockQueryBuilder->shouldReceive('whereJsonContains')
        ->once()
        ->with('currencies', ['USD', 'EUR'])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('where')
        ->once()
        ->with('active', true)
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('get')
        ->once()
        ->andReturn($mockQueryResult);

    // Mock the static calls on ExchangeRateProvider for the query chain
    $mockExchangeRateProviderAlias = m::mock('overload:' . ExchangeRateProvider::class);
    $mockExchangeRateProviderAlias->shouldReceive('whereJsonContains')
        ->andReturn($mockQueryBuilder);

    $result = ExchangeRateProvider::checkActiveCurrencies($mockRequest);

    expect($result)->not->toBeEmpty();
    expect($result)->toHaveCount(2);
    expect($result->first())->toBeInstanceOf(ExchangeRateProvider::class);
});

test('checkActiveCurrencies returns empty collection if no providers match', function () {
    $mockRequest = (object)['currencies' => ['JPY']];

    $mockQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockQueryBuilder->shouldReceive('whereJsonContains')
        ->once()
        ->with('currencies', ['JPY'])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('where')
        ->once()
        ->with('active', true)
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('get')
        ->once()
        ->andReturn(collect([]));

    // Mock the static calls on ExchangeRateProvider for the query chain
    $mockExchangeRateProviderAlias = m::mock('overload:' . ExchangeRateProvider::class);
    $mockExchangeRateProviderAlias->shouldReceive('whereJsonContains')
        ->andReturn($mockQueryBuilder);

    $result = ExchangeRateProvider::checkActiveCurrencies($mockRequest);

    expect($result)->toBeEmpty();
});

test('checkUpdateActiveCurrencies returns providers matching active, currencies and not current id', function () {
    $exchangeRateProvider = (new ExchangeRateProvider())->forceFill(['id' => 5]);
    $mockRequest = (object)['currencies' => ['USD'], 'active' => true];

    $mockQueryResult = collect([
        (new ExchangeRateProvider())->forceFill(['id' => 1, 'currencies' => ['USD'], 'active' => true]),
    ]);

    $mockQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockQueryBuilder->shouldReceive('where')
        ->once()
        ->with('active', true)
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('where')
        ->once()
        ->with('id', '<>', 5)
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('whereJsonContains')
        ->once()
        ->with('currencies', ['USD'])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('get')
        ->once()
        ->andReturn($mockQueryResult);

    // Mock the static calls on ExchangeRateProvider for the query chain
    $mockExchangeRateProviderAlias = m::mock('overload:' . ExchangeRateProvider::class);
    $mockExchangeRateProviderAlias->shouldReceive('where')
        ->andReturn($mockQueryBuilder); // The first 'where' call initiates the chain

    $result = $exchangeRateProvider->checkUpdateActiveCurrencies($mockRequest);

    expect($result)->not->toBeEmpty();
    expect($result)->toHaveCount(1);
    expect($result->first()->id)->not->toBe($exchangeRateProvider->id);
    expect($result->first())->toBeInstanceOf(ExchangeRateProvider::class);
});

test('checkUpdateActiveCurrencies returns empty collection if no providers match', function () {
    $exchangeRateProvider = (new ExchangeRateProvider())->forceFill(['id' => 5]);
    $mockRequest = (object)['currencies' => ['GBP'], 'active' => true];

    $mockQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockQueryBuilder->shouldReceive('where')
        ->once()
        ->with('active', true)
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('where')
        ->once()
        ->with('id', '<>', 5)
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('whereJsonContains')
        ->once()
        ->with('currencies', ['GBP'])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('get')
        ->once()
        ->andReturn(collect([]));

    // Mock the static calls on ExchangeRateProvider for the query chain
    $mockExchangeRateProviderAlias = m::mock('overload:' . ExchangeRateProvider::class);
    $mockExchangeRateProviderAlias->shouldReceive('where')
        ->andReturn($mockQueryBuilder);

    $result = $exchangeRateProvider->checkUpdateActiveCurrencies($mockRequest);

    expect($result)->toBeEmpty();
});

// Test cases for checkExchangeRateProviderStatus

test('checkExchangeRateProviderStatus handles currency_freak success', function () {
    $request = ['driver' => 'currency_freak', 'key' => 'test_key'];
    $mockApiResponse = [
        'success' => true,
        'rates' => ['INR' => 75.0], // Original code uses symbols=INR&base=USD, API returns INR as value
    ];

    Http::fake([
        'https://api.currencyfreaks.com/latest?apikey=test_key&symbols=INR&base=USD' => Http::response($mockApiResponse, 200),
    ]);

    $response = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['exchangeRate' => [75.0]]);
});

test('checkExchangeRateProviderStatus handles currency_freak failure', function () {
    $request = ['driver' => 'currency_freak', 'key' => 'test_key'];
    $mockApiResponse = [
        'success' => false,
        'error' => ['message' => 'Invalid API Key'],
    ];

    Http::fake([
        'https://api.currencyfreaks.com/latest?apikey=test_key&symbols=INR&base=USD' => Http::response($mockApiResponse, 200),
    ]);

    // Mock response() helper to verify respondJson's internal call
    Response::shouldReceive('json')
        ->once()
        ->with([
            'message' => 'Invalid API Key',
            'description' => 'Invalid API Key'
        ], 400)
        ->andReturn(new JsonResponse([
            'message' => 'Invalid API Key',
            'description' => 'Invalid API Key'
        ], 400));

    $response = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true))->toEqual([
        'message' => 'Invalid API Key',
        'description' => 'Invalid API Key'
    ]);
});

test('checkExchangeRateProviderStatus handles currency_layer success', function () {
    $request = ['driver' => 'currency_layer', 'key' => 'test_key'];
    $mockApiResponse = [
        'success' => true,
        'quotes' => ['USDINR' => 74.5], // API returns source+target currency as key
    ];

    Http::fake([
        'http://api.currencylayer.com/live?access_key=test_key&source=INR&currencies=USD' => Http::response($mockApiResponse, 200),
    ]);

    $response = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['exchangeRate' => [74.5]]);
});

test('checkExchangeRateProviderStatus handles currency_layer failure', function () {
    $request = ['driver' => 'currency_layer', 'key' => 'test_key'];
    $mockApiResponse = [
        'success' => false,
        'error' => ['info' => 'API Key expired'],
    ];

    Http::fake([
        'http://api.currencylayer.com/live?access_key=test_key&source=INR&currencies=USD' => Http::response($mockApiResponse, 200),
    ]);

    // Mock response() helper to verify respondJson's internal call
    Response::shouldReceive('json')
        ->once()
        ->with([
            'message' => 'API Key expired',
            'description' => 'API Key expired'
        ], 400)
        ->andReturn(new JsonResponse([
            'message' => 'API Key expired',
            'description' => 'API Key expired'
        ], 400));

    $response = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true))->toEqual([
        'message' => 'API Key expired',
        'description' => 'API Key expired'
    ]);
});

test('checkExchangeRateProviderStatus handles open_exchange_rate success', function () {
    $request = ['driver' => 'open_exchange_rate', 'key' => 'test_key'];
    $mockApiResponse = [
        'disclaimer' => '...',
        'license' => '...',
        'timestamp' => Carbon::now()->timestamp,
        'base' => 'INR',
        'rates' => ['USD' => 0.013],
    ];

    Http::fake([
        'https://openexchangerates.org/api/latest.json?app_id=test_key&base=INR&symbols=USD' => Http::response($mockApiResponse, 200),
    ]);

    $response = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['exchangeRate' => [0.013]]);
});

test('checkExchangeRateProviderStatus handles open_exchange_rate failure', function () {
    $request = ['driver' => 'open_exchange_rate', 'key' => 'test_key'];
    $mockApiResponse = [
        'error' => true,
        'status' => 401,
        'message' => 'Invalid App ID',
        'description' => 'App ID not valid or not found.',
    ];

    Http::fake([
        'https://openexchangerates.org/api/latest.json?app_id=test_key&base=INR&symbols=USD' => Http::response($mockApiResponse, 200),
    ]);

    // Mock response() helper to verify respondJson's internal call
    Response::shouldReceive('json')
        ->once()
        ->with([
            'message' => 'Invalid App ID',
            'description' => 'App ID not valid or not found.'
        ], 400)
        ->andReturn(new JsonResponse([
            'message' => 'Invalid App ID',
            'description' => 'App ID not valid or not found.'
        ], 400));

    $response = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true))->toEqual([
        'message' => 'Invalid App ID',
        'description' => 'App ID not valid or not found.'
    ]);
});

test('checkExchangeRateProviderStatus handles currency_converter premium success', function () {
    $request = [
        'driver' => 'currency_converter',
        'key' => 'test_key',
        'driver_config' => ['type' => 'PREMIUM'],
    ];
    $mockApiResponse = [
        'INR_USD' => [
            'val' => 0.0134,
            'id' => 'INR_USD',
            'fr' => 'INR',
            'ts' => 1678886400,
            'to' => 'USD',
        ],
    ];

    Http::fake([
        'https://api.currconv.com/api/v7/convert?apiKey=test_key&q=INR_USD&compact=y' => Http::response($mockApiResponse, 200),
    ]);

    $response = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['exchangeRate' => [$mockApiResponse['INR_USD']]]);
});

test('checkExchangeRateProviderStatus handles currency_converter prepaid success', function () {
    $request = [
        'driver' => 'currency_converter',
        'key' => 'test_key',
        'driver_config' => ['type' => 'PREPAID'],
    ];
    $mockApiResponse = [
        'INR_USD' => [
            'val' => 0.0135,
            'id' => 'INR_USD',
            'fr' => 'INR',
            'ts' => 1678886400,
            'to' => 'USD',
        ],
    ];

    Http::fake([
        'https://prepaid.currconv.com/api/v7/convert?apiKey=test_key&q=INR_USD&compact=y' => Http::response($mockApiResponse, 200),
    ]);

    $response = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['exchangeRate' => [$mockApiResponse['INR_USD']]]);
});

test('checkExchangeRateProviderStatus handles currency_converter free success', function () {
    $request = [
        'driver' => 'currency_converter',
        'key' => 'test_key',
        'driver_config' => ['type' => 'FREE'],
    ];
    $mockApiResponse = [
        'INR_USD' => [
            'val' => 0.0136,
            'id' => 'INR_USD',
            'fr' => 'INR',
            'ts' => 1678886400,
            'to' => 'USD',
        ],
    ];

    Http::fake([
        'https://free.currconv.com/api/v7/convert?apiKey=test_key&q=INR_USD&compact=y' => Http::response($mockApiResponse, 200),
    ]);

    $response = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['exchangeRate' => [$mockApiResponse['INR_USD']]]);
});

test('checkExchangeRateProviderStatus handles currency_converter dedicated success', function () {
    $dedicatedUrl = 'https://mycustom.currconv.com';
    $request = [
        'driver' => 'currency_converter',
        'key' => 'test_key',
        'driver_config' => ['type' => 'DEDICATED', 'url' => $dedicatedUrl],
    ];
    $mockApiResponse = [
        'INR_USD' => [
            'val' => 0.0137,
            'id' => 'INR_USD',
            'fr' => 'INR',
            'ts' => 1678886400,
            'to' => 'USD',
        ],
    ];

    Http::fake([
        $dedicatedUrl . '/api/v7/convert?apiKey=test_key&q=INR_USD&compact=y' => Http::response($mockApiResponse, 200),
    ]);

    $response = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['exchangeRate' => [$mockApiResponse['INR_USD']]]);
});

test('getCurrencyConverterUrl returns premium url', function () {
    $data = ['type' => 'PREMIUM'];
    expect(ExchangeRateProvider::getCurrencyConverterUrl($data))->toBe("https://api.currconv.com");
});

test('getCurrencyConverterUrl returns prepaid url', function () {
    $data = ['type' => 'PREPAID'];
    expect(ExchangeRateProvider::getCurrencyConverterUrl($data))->toBe("https://prepaid.currconv.com");
});

test('getCurrencyConverterUrl returns free url', function () {
    $data = ['type' => 'FREE'];
    expect(ExchangeRateProvider::getCurrencyConverterUrl($data))->toBe("https://free.currconv.com");
});

test('getCurrencyConverterUrl returns dedicated url', function () {
    $dedicatedUrl = 'https://mycustom.currconv.com';
    $data = ['type' => 'DEDICATED', 'url' => $dedicatedUrl];
    expect(ExchangeRateProvider::getCurrencyConverterUrl($data))->toBe($dedicatedUrl);
});

test('getCurrencyConverterUrl returns null for unknown type', function () {
    $data = ['type' => 'UNKNOWN'];
    // In PHP, if no case matches and there's no default, the function implicitly returns null.
    expect(ExchangeRateProvider::getCurrencyConverterUrl($data))->toBeNull();
});
