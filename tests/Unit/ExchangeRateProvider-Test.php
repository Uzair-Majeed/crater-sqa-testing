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
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Mockery as m;

if (! function_exists('respondJson')) {
    function respondJson($message, $description = null, $status = 400)
    {
        return response()->json([
            'message' => $message,
            'description' => $description ?? $message
        ], $status);
    }
}

beforeEach(function () {
    m::close();
    Http::preventStrayRequests();
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

    $reflectionProperty = new ReflectionProperty($exchangeRateProvider, 'attributes');
    $reflectionProperty->setAccessible(true);
    $attributes = $reflectionProperty->getValue($exchangeRateProvider);

    expect($attributes['currencies'])->toBe(json_encode($currencies));
    expect($exchangeRateProvider->currencies)->toBe($currencies);
});

test('setDriverConfigAttribute json encodes the value', function () {
    $exchangeRateProvider = new ExchangeRateProvider();
    $driverConfig = ['apiKey' => 'test_key', 'baseUrl' => 'test_url'];
    $exchangeRateProvider->setDriverConfigAttribute($driverConfig);

    $reflectionProperty = new ReflectionProperty($exchangeRateProvider, 'attributes');
    $reflectionProperty->setAccessible(true);
    $attributes = $reflectionProperty->getValue($exchangeRateProvider);

    expect($attributes['driver_config'])->toBe(json_encode($driverConfig));
    expect($exchangeRateProvider->driver_config)->toBe($driverConfig);
});

test('scopeWhereCompany adds a where clause for the company_id from request header', function () {
    // Swap out real Request instance with real one, add header "company" for isolation.
    $originalRequest = App::make('request');
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_COMPANY' => '1']);
    App::instance('request', $request);

    $mockQuery = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockQuery->shouldReceive('where')
        ->once()
        ->with('exchange_rate_providers.company_id', 1)
        ->andReturn($mockQuery);

    ExchangeRateProvider::scopeWhereCompany($mockQuery);

    // Restore request object
    App::instance('request', $originalRequest);
});

test('createFromRequest creates an exchange rate provider with the correct payload', function () {
    $payload = ['name' => 'Test Provider', 'driver' => 'currency_freak', 'active' => true];

    $mockRequest = m::mock(ExchangeRateProviderRequest::class);
    $mockRequest->shouldReceive('getExchangeRateProviderPayload')
        ->once()
        ->andReturn($payload);

    // Instead of overload mock (conflicts/crashes), mock using shouldReceive on model itself.
    $partial = m::mock('alias:' . ExchangeRateProvider::class);
    $partial->shouldReceive('create')
        ->once()
        ->with($payload)
        ->andReturn((new ExchangeRateProvider())->forceFill($payload + ['id' => 1]));

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

    $exchangeRateProvider = m::mock(ExchangeRateProvider::class)->makePartial();
    $exchangeRateProvider->id = 1;
    $exchangeRateProvider->shouldReceive('update')
        ->once()
        ->with($payload)
        ->andReturn(true);

    $result = $exchangeRateProvider->updateFromRequest($mockRequest);

    expect($result)->toBe($exchangeRateProvider);
});

test('checkActiveCurrencies returns providers matching active and currencies', function () {
    $mockRequest = (object)['currencies' => ['USD', 'EUR']];

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

    // Use aliasing not overload to avoid class load conflicts
    $mockExchangeRateProviderAlias = m::mock('alias:' . ExchangeRateProvider::class);
    $mockExchangeRateProviderAlias->shouldReceive('whereJsonContains')
        ->with('currencies', ['USD', 'EUR'])
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

    $mockExchangeRateProviderAlias = m::mock('alias:' . ExchangeRateProvider::class);
    $mockExchangeRateProviderAlias->shouldReceive('whereJsonContains')
        ->with('currencies', ['JPY'])
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
        ->with('active', true)
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('where')
        ->with('id', '<>', 5)
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('whereJsonContains')
        ->with('currencies', ['USD'])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('get')
        ->once()
        ->andReturn($mockQueryResult);

    $mockExchangeRateProviderAlias = m::mock('alias:' . ExchangeRateProvider::class);
    $mockExchangeRateProviderAlias->shouldReceive('where')
        ->with('active', true)
        ->andReturn($mockQueryBuilder);

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
        ->with('active', true)
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('where')
        ->with('id', '<>', 5)
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('whereJsonContains')
        ->with('currencies', ['GBP'])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('get')
        ->once()
        ->andReturn(collect([]));

    $mockExchangeRateProviderAlias = m::mock('alias:' . ExchangeRateProvider::class);
    $mockExchangeRateProviderAlias->shouldReceive('where')
        ->with('active', true)
        ->andReturn($mockQueryBuilder);

    $result = $exchangeRateProvider->checkUpdateActiveCurrencies($mockRequest);

    expect($result)->toBeEmpty();
});

// Test cases for checkExchangeRateProviderStatus

test('checkExchangeRateProviderStatus handles currency_freak success', function () {
    $request = ['driver' => 'currency_freak', 'key' => 'test_key'];
    $mockApiResponse = [
        'success' => true,
        'rates' => ['INR' => 75.0],
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

    // Use shouldReceive on Response facade for json
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
        'quotes' => ['USDINR' => 74.5],
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
    expect(ExchangeRateProvider::getCurrencyConverterUrl($data))->toBeNull();
});

afterEach(function () {
    Mockery::close();
});