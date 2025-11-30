<?php

use Tests\TestCase;
uses(\Mockery::class);
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;

// Define a simple class that uses the trait for testing
// This class also has a public respondJson method to intercept calls to the global helper
// and allows us to make it a partial mock to mock its own methods.
class TraitTestClass {
    use \Crater\Traits\ExchangeRateProvidersTrait;

    public function respondJson($message, $error) {
        // Return a recognizable structure for assertion
        return (new JsonResponse([
            'message' => $message,
            'error' => $error
        ], 400)); // Assuming 400 for error responses
    }
}

// The main Pest test suite for ExchangeRateProvidersTrait
beforeEach(function () {
        // Create a mock for the ResponseFactory (used by response()->json())
        $this->mockResponseFactory = Mockery::mock(ResponseFactory::class);
        app()->instance(ResponseFactory::class, $this->mockResponseFactory);

        // Mock the Http facade using its alias for static mocking
        $this->httpFacadeMock = Mockery::mock('alias:Illuminate\Support\Facades\Http');

        // Create a partial mock of the TraitTestClass
        // This allows us to mock its public methods like getUrl, getCurrencyConverterUrl later
        $this->traitInstance = Mockery::mock(TraitTestClass::class)->makePartial();
    });

    // This will run after each test in this describe block
    afterEach(function () {
        Mockery::close();
    });

    // --- Tests for getExchangeRate method ---
   test('currency_freak returns exchange rate on success', function () {
            $filter = ['driver' => 'currency_freak', 'key' => 'test_freak_key'];
            $baseCurrencyCode = 'USD';
            $currencyCode = 'INR';
            $expectedRate = 74.5;

            $this->httpFacadeMock->shouldReceive('get')
                ->with("https://api.currencyfreaks.com/latest?apikey={$filter['key']}&symbols={$currencyCode}&base={$baseCurrencyCode}")
                ->once()
                ->andReturn(Http::response([
                    'success' => true,
                    'rates' => ["{$baseCurrencyCode}{$currencyCode}" => $expectedRate],
                ], 200));

            $this->mockResponseFactory->shouldReceive('json')
                ->once()
                ->with(['exchangeRate' => [$expectedRate]], 200)
                ->andReturn(new JsonResponse(['exchangeRate' => [$expectedRate]], 200));

            $response = $this->traitInstance->getExchangeRate($filter, $baseCurrencyCode, $currencyCode);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['exchangeRate'][0])->toBe($expectedRate);
        });

        test('currency_freak handles API error response', function () {
            $filter = ['driver' => 'currency_freak', 'key' => 'test_freak_key'];
            $baseCurrencyCode = 'USD';
            $currencyCode = 'INR';
            $errorMessage = 'Invalid API key provided.';

            $this->httpFacadeMock->shouldReceive('get')
                ->once()
                ->andReturn(Http::response([
                    'success' => false,
                    'error' => ['message' => $errorMessage],
                ], 200));

            $response = $this->traitInstance->getExchangeRate($filter, $baseCurrencyCode, $currencyCode);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($errorMessage)
                ->and($response->getData(true)['error'])->toBe($errorMessage)
                ->and($response->getStatusCode())->toBe(400); // respondJson default status
        });

        // currency_layer driver
        test('currency_layer returns exchange rate on success', function () {
            $filter = ['driver' => 'currency_layer', 'key' => 'test_layer_key'];
            $baseCurrencyCode = 'USD';
            $currencyCode = 'INR';
            $expectedRate = 74.5;

            $this->httpFacadeMock->shouldReceive('get')
                ->with("http://api.currencylayer.com/live?access_key={$filter['key']}&source={$baseCurrencyCode}&currencies={$currencyCode}")
                ->once()
                ->andReturn(Http::response([
                    'success' => true,
                    'quotes' => ["{$baseCurrencyCode}{$currencyCode}" => $expectedRate],
                ], 200));

            $this->mockResponseFactory->shouldReceive('json')
                ->once()
                ->with(['exchangeRate' => [$expectedRate]], 200)
                ->andReturn(new JsonResponse(['exchangeRate' => [$expectedRate]], 200));

            $response = $this->traitInstance->getExchangeRate($filter, $baseCurrencyCode, $currencyCode);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['exchangeRate'][0])->toBe($expectedRate);
        });

        test('currency_layer handles API error response', function () {
            $filter = ['driver' => 'currency_layer', 'key' => 'test_layer_key'];
            $baseCurrencyCode = 'USD';
            $currencyCode = 'INR';
            $errorInfo = 'API Key Missing.';

            $this->httpFacadeMock->shouldReceive('get')
                ->once()
                ->andReturn(Http::response([
                    'success' => false,
                    'error' => ['code' => 101, 'type' => 'missing_access_key', 'info' => $errorInfo],
                ], 200));

            $response = $this->traitInstance->getExchangeRate($filter, $baseCurrencyCode, $currencyCode);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($errorInfo)
                ->and($response->getData(true)['error'])->toBe($errorInfo)
                ->and($response->getStatusCode())->toBe(400);
        });

        // open_exchange_rate driver
        test('open_exchange_rate returns exchange rate on success', function () {
            $filter = ['driver' => 'open_exchange_rate', 'key' => 'test_oexr_key'];
            $baseCurrencyCode = 'USD';
            $currencyCode = 'INR';
            $expectedRate = 74.5;

            $this->httpFacadeMock->shouldReceive('get')
                ->with("https://openexchangerates.org/api/latest.json?app_id={$filter['key']}&base={$baseCurrencyCode}&symbols={$currencyCode}")
                ->once()
                ->andReturn(Http::response([
                    'base' => $baseCurrencyCode,
                    'rates' => [$currencyCode => $expectedRate],
                ], 200));

            $this->mockResponseFactory->shouldReceive('json')
                ->once()
                ->with(['exchangeRate' => [$expectedRate]], 200)
                ->andReturn(new JsonResponse(['exchangeRate' => [$expectedRate]], 200));

            $response = $this->traitInstance->getExchangeRate($filter, $baseCurrencyCode, $currencyCode);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['exchangeRate'][0])->toBe($expectedRate);
        });

        test('open_exchange_rate handles API error response', function () {
            $filter = ['driver' => 'open_exchange_rate', 'key' => 'test_oexr_key'];
            $baseCurrencyCode = 'USD';
            $currencyCode = 'INR';
            $errorMessage = 'Invalid App ID';
            $errorDescription = 'The App ID "test_oexr_key" is invalid or does not exist.';

            $this->httpFacadeMock->shouldReceive('get')
                ->once()
                ->andReturn(Http::response([
                    'error' => true,
                    'status' => 401,
                    'message' => $errorMessage,
                    'description' => $errorDescription,
                ], 401));

            $response = $this->traitInstance->getExchangeRate($filter, $baseCurrencyCode, $currencyCode);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($errorMessage)
                ->and($response->getData(true)['error'])->toBe($errorDescription)
                ->and($response->getStatusCode())->toBe(400);
        });

        // currency_converter driver
        test('currency_converter returns exchange rate on success', function () {
            $filter = [
                'driver' => 'currency_converter',
                'key' => 'test_cc_key',
                'driver_config' => ['type' => 'FREE'],
            ];
            $baseCurrencyCode = 'USD';
            $currencyCode = 'INR';
            $query = "{$baseCurrencyCode}_{$currencyCode}";
            $expectedRate = 74.5;
            $converterBaseUrl = "https://free.currconv.com";

            // Mock the internal getCurrencyConverterUrl method
            $this->traitInstance->shouldReceive('getCurrencyConverterUrl')
                ->once()
                ->with($filter['driver_config'])
                ->andReturn($converterBaseUrl);

            $this->httpFacadeMock->shouldReceive('get')
                ->with("{$converterBaseUrl}/api/v7/convert?apiKey={$filter['key']}&q={$query}&compact=y")
                ->once()
                ->andReturn(Http::response([
                    $query => ['val' => $expectedRate],
                ], 200));

            $this->mockResponseFactory->shouldReceive('json')
                ->once()
                ->with(['exchangeRate' => [['val' => $expectedRate]]], 200)
                ->andReturn(new JsonResponse(['exchangeRate' => [['val' => $expectedRate]]], 200));

            $response = $this->traitInstance->getExchangeRate($filter, $baseCurrencyCode, $currencyCode);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['exchangeRate'][0]['val'])->toBe($expectedRate);
        });
   test('returns premium URL for PREMIUM type', function () {
            $data = ['type' => 'PREMIUM'];
            $url = $this->traitInstance->getCurrencyConverterUrl($data);
            expect($url)->toBe('https://api.currconv.com');
        });

        test('returns prepaid URL for PREPAID type', function () {
            $data = ['type' => 'PREPAID'];
            $url = $this->traitInstance->getCurrencyConverterUrl($data);
            expect($url)->toBe('https://prepaid.currconv.com');
        });

        test('returns free URL for FREE type', function () {
            $data = ['type' => 'FREE'];
            $url = $this->traitInstance->getCurrencyConverterUrl($data);
            expect($url)->toBe('https://free.currconv.com');
        });

        test('returns dedicated URL for DEDICATED type', function () {
            $customUrl = 'https://mycustom.currconv.com';
            $data = ['type' => 'DEDICATED', 'url' => $customUrl];
            $url = $this->traitInstance->getCurrencyConverterUrl($data);
            expect($url)->toBe($customUrl);
        });

        test('returns null for unknown type', function () {
            $data = ['type' => 'UNKNOWN'];
            $url = $this->traitInstance->getCurrencyConverterUrl($data);
            expect($url)->toBeNull();
        });

    // --- Tests for getSupportedCurrencies method ---
    $message = 'Please Enter Valid Provider Key.';
        $error = 'invalid_key';
        $server_message = 'Server not responding';
        $error_message = 'server_error';

        // currency_freak driver
        test('currency_freak returns supported currencies on success', function () use ($server_message, $error_message) {
            $request = new Request(['driver' => 'currency_freak', 'key' => 'test_freak_key']);
            $currencySymbolsResponse = ['USD' => 'US Dollar', 'INR' => 'Indian Rupee'];
            $checkKeyResponse = ['success' => true, 'rates' => ['USDINR' => 74.5]]; // A valid key response

            $this->httpFacadeMock->shouldReceive('get')
                ->with("https://api.currencyfreaks.com/currency-symbols")
                ->once()
                ->andReturn(Http::response($currencySymbolsResponse, 200));

            $this->traitInstance->shouldReceive('getUrl')
                ->with($request)
                ->once()
                ->andReturn($checkKeyResponse);

            $this->mockResponseFactory->shouldReceive('json')
                ->once()
                ->with(['supportedCurrencies' => array_keys($currencySymbolsResponse)])
                ->andReturn(new JsonResponse(['supportedCurrencies' => array_keys($currencySymbolsResponse)], 200));

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['supportedCurrencies'])->toBe(array_keys($currencySymbolsResponse));
        });

        test('currency_freak handles server error for currency symbols API', function () use ($server_message, $error_message) {
            $request = new Request(['driver' => 'currency_freak', 'key' => 'test_freak_key']);

            $this->httpFacadeMock->shouldReceive('get')
                ->with("https://api.currencyfreaks.com/currency-symbols")
                ->once()
                ->andReturn(Http::response(null, 200)); // Simulating null json() response

            $this->traitInstance->shouldNotReceive('getUrl'); // Should not be called if first API fails

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($server_message)
                ->and($response->getData(true)['error'])->toBe($error_message)
                ->and($response->getStatusCode())->toBe(400);
        });

        test('currency_freak handles server error for key check API', function () use ($server_message, $error_message) {
            $request = new Request(['driver' => 'currency_freak', 'key' => 'test_freak_key']);
            $currencySymbolsResponse = ['USD' => 'US Dollar'];

            $this->httpFacadeMock->shouldReceive('get')
                ->with("https://api.currencyfreaks.com/currency-symbols")
                ->once()
                ->andReturn(Http::response($currencySymbolsResponse, 200));

            $this->traitInstance->shouldReceive('getUrl')
                ->with($request)
                ->once()
                ->andReturn(null); // Simulating null json() response from getUrl

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($server_message)
                ->and($response->getData(true)['error'])->toBe($error_message)
                ->and($response->getStatusCode())->toBe(400);
        });

        test('currency_freak handles invalid key error', function () use ($message, $error) {
            $request = new Request(['driver' => 'currency_freak', 'key' => 'invalid_freak_key']);
            $currencySymbolsResponse = ['USD' => 'US Dollar'];
            $checkKeyErrorResponse = [
                'success' => true, // API might return success:true even for 404
                'error' => ['status' => 404, 'message' => 'Not Found']
            ];

            $this->httpFacadeMock->shouldReceive('get')
                ->with("https://api.currencyfreaks.com/currency-symbols")
                ->once()
                ->andReturn(Http::response($currencySymbolsResponse, 200));

            $this->traitInstance->shouldReceive('getUrl')
                ->with($request)
                ->once()
                ->andReturn($checkKeyErrorResponse);

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($message)
                ->and($response->getData(true)['error'])->toBe($error)
                ->and($response->getStatusCode())->toBe(400);
        });

        // currency_layer driver
        test('currency_layer returns supported currencies on success', function () {
            $request = new Request(['driver' => 'currency_layer', 'key' => 'test_layer_key']);
            $currenciesResponse = ['USD' => 'US Dollar', 'INR' => 'Indian Rupee'];

            $this->httpFacadeMock->shouldReceive('get')
                ->with("http://api.currencylayer.com/list?access_key={$request->key}")
                ->once()
                ->andReturn(Http::response([
                    'success' => true,
                    'currencies' => $currenciesResponse
                ], 200));

            $this->mockResponseFactory->shouldReceive('json')
                ->once()
                ->with(['supportedCurrencies' => array_keys($currenciesResponse)])
                ->andReturn(new JsonResponse(['supportedCurrencies' => array_keys($currenciesResponse)], 200));

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['supportedCurrencies'])->toBe(array_keys($currenciesResponse));
        });

        test('currency_layer handles server error', function () use ($server_message, $error_message) {
            $request = new Request(['driver' => 'currency_layer', 'key' => 'test_layer_key']);

            $this->httpFacadeMock->shouldReceive('get')
                ->once()
                ->andReturn(Http::response(null, 200)); // Simulating null json() response

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($server_message)
                ->and($response->getData(true)['error'])->toBe($error_message)
                ->and($response->getStatusCode())->toBe(400);
        });

        test('currency_layer handles invalid key/currencies missing', function () use ($message, $error) {
            $request = new Request(['driver' => 'currency_layer', 'key' => 'invalid_layer_key']);

            $this->httpFacadeMock->shouldReceive('get')
                ->once()
                ->andReturn(Http::response([
                    'success' => false,
                    'error' => ['code' => 101, 'type' => 'missing_access_key', 'info' => 'API Key Missing.'],
                ], 200)); // No 'currencies' key

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($message)
                ->and($response->getData(true)['error'])->toBe($error)
                ->and($response->getStatusCode())->toBe(400);
        });

        // open_exchange_rate driver
        test('open_exchange_rate returns supported currencies on success', function () {
            $request = new Request(['driver' => 'open_exchange_rate', 'key' => 'test_oexr_key']);
            $currenciesResponse = ['USD' => 'US Dollar', 'INR' => 'Indian Rupee'];
            $checkKeyResponse = ['base' => 'USD', 'rates' => ['INR' => 74.5]]; // A valid key response

            $this->httpFacadeMock->shouldReceive('get')
                ->with("https://openexchangerates.org/api/currencies.json")
                ->once()
                ->andReturn(Http::response($currenciesResponse, 200));

            $this->traitInstance->shouldReceive('getUrl')
                ->with($request)
                ->once()
                ->andReturn($checkKeyResponse);

            $this->mockResponseFactory->shouldReceive('json')
                ->once()
                ->with(['supportedCurrencies' => array_keys($currenciesResponse)])
                ->andReturn(new JsonResponse(['supportedCurrencies' => array_keys($currenciesResponse)], 200));

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['supportedCurrencies'])->toBe(array_keys($currenciesResponse));
        });

        test('open_exchange_rate handles server error for currencies API', function () use ($server_message, $error_message) {
            $request = new Request(['driver' => 'open_exchange_rate', 'key' => 'test_oexr_key']);

            $this->httpFacadeMock->shouldReceive('get')
                ->with("https://openexchangerates.org/api/currencies.json")
                ->once()
                ->andReturn(Http::response(null, 200)); // Simulating null json() response

            $this->traitInstance->shouldNotReceive('getUrl'); // Should not be called

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($server_message)
                ->and($response->getData(true)['error'])->toBe($error_message)
                ->and($response->getStatusCode())->toBe(400);
        });

        test('open_exchange_rate handles server error for key check API', function () use ($server_message, $error_message) {
            $request = new Request(['driver' => 'open_exchange_rate', 'key' => 'test_oexr_key']);
            $currenciesResponse = ['USD' => 'US Dollar'];

            $this->httpFacadeMock->shouldReceive('get')
                ->with("https://openexchangerates.org/api/currencies.json")
                ->once()
                ->andReturn(Http::response($currenciesResponse, 200));

            $this->traitInstance->shouldReceive('getUrl')
                ->with($request)
                ->once()
                ->andReturn(null); // Simulating null json() response from getUrl

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($server_message)
                ->and($response->getData(true)['error'])->toBe($error_message)
                ->and($response->getStatusCode())->toBe(400);
        });

        test('open_exchange_rate handles invalid key error', function () use ($message, $error) {
            $request = new Request(['driver' => 'open_exchange_rate', 'key' => 'invalid_oexr_key']);
            $currenciesResponse = ['USD' => 'US Dollar'];
            $checkKeyErrorResponse = [
                'error' => true,
                'status' => 401,
                'message' => 'invalid_app_id',
                'description' => 'Invalid App ID was provided.',
            ];

            $this->httpFacadeMock->shouldReceive('get')
                ->with("https://openexchangerates.org/api/currencies.json")
                ->once()
                ->andReturn(Http::response($currenciesResponse, 200));

            $this->traitInstance->shouldReceive('getUrl')
                ->with($request)
                ->once()
                ->andReturn($checkKeyErrorResponse);

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($message)
                ->and($response->getData(true)['error'])->toBe($error)
                ->and($response->getStatusCode())->toBe(400);
        });

        // currency_converter driver
        test('currency_converter returns supported currencies on success', function () {
            $request = new Request(['driver' => 'currency_converter', 'key' => 'test_cc_key', 'driver_config' => ['type' => 'FREE']]);
            $getUrlResponse = ['results' => ['USD' => ['currencyName' => 'US Dollar'], 'INR' => ['currencyName' => 'Indian Rupee']]];

            $this->traitInstance->shouldReceive('getUrl')
                ->with($request)
                ->once()
                ->andReturn($getUrlResponse);

            $this->mockResponseFactory->shouldReceive('json')
                ->once()
                ->with(['supportedCurrencies' => array_keys($getUrlResponse['results'])])
                ->andReturn(new JsonResponse(['supportedCurrencies' => array_keys($getUrlResponse['results'])], 200));

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['supportedCurrencies'])->toBe(array_keys($getUrlResponse['results']));
        });

        test('currency_converter handles server error', function () use ($server_message, $error_message) {
            $request = new Request(['driver' => 'currency_converter', 'key' => 'test_cc_key', 'driver_config' => ['type' => 'FREE']]);

            $this->traitInstance->shouldReceive('getUrl')
                ->with($request)
                ->once()
                ->andReturn(null); // Simulating null json() response from getUrl

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($server_message)
                ->and($response->getData(true)['error'])->toBe($error_message)
                ->and($response->getStatusCode())->toBe(400);
        });

        test('currency_converter handles invalid key/results missing', function () use ($message, $error) {
            $request = new Request(['driver' => 'currency_converter', 'key' => 'invalid_cc_key', 'driver_config' => ['type' => 'FREE']]);
            // API response for invalid key might not contain 'results'
            $getUrlErrorResponse = ['error' => ['message' => 'Invalid API key']];

            $this->traitInstance->shouldReceive('getUrl')
                ->with($request)
                ->once()
                ->andReturn($getUrlErrorResponse);

            $response = $this->traitInstance->getSupportedCurrencies($request);

            expect($response)->toBeInstanceOf(JsonResponse::class)
                ->and($response->getData(true)['message'])->toBe($message)
                ->and($response->getData(true)['error'])->toBe($error)
                ->and($response->getStatusCode())->toBe(400);
        });

    // --- Tests for getUrl method ---
     test('getUrl for currency_freak constructs correct URL and returns response', function () {
            $request = new Request(['driver' => 'currency_freak', 'key' => 'test_freak_key']);
            $expectedUrl = "https://api.currencyfreaks.com/latest?apikey={$request->key}&symbols=INR&base=USD";
            $apiResponse = ['success' => true, 'rates' => ['USDINR' => 74.5]];

            $this->httpFacadeMock->shouldReceive('get')
                ->with($expectedUrl)
                ->once()
                ->andReturn(Http::response($apiResponse, 200));

            $result = $this->traitInstance->getUrl($request);

            expect($result)->toBe($apiResponse);
        });

        // currency_layer driver
        test('getUrl for currency_layer constructs correct URL and returns response', function () {
            $request = new Request(['driver' => 'currency_layer', 'key' => 'test_layer_key']);
            $expectedUrl = "http://api.currencylayer.com/live?access_key={$request->key}&source=INR&currencies=USD";
            $apiResponse = ['success' => true, 'quotes' => ['USDINR' => 74.5]];

            $this->httpFacadeMock->shouldReceive('get')
                ->with($expectedUrl)
                ->once()
                ->andReturn(Http::response($apiResponse, 200));

            $result = $this->traitInstance->getUrl($request);

            expect($result)->toBe($apiResponse);
        });

        // open_exchange_rate driver
        test('getUrl for open_exchange_rate constructs correct URL and returns response', function () {
            $request = new Request(['driver' => 'open_exchange_rate', 'key' => 'test_oexr_key']);
            $expectedUrl = "https://openexchangerates.org/api/latest.json?app_id={$request->key}&base=INR&symbols=USD";
            $apiResponse = ['timestamp' => 123, 'base' => 'INR', 'rates' => ['USD' => 0.013]];

            $this->httpFacadeMock->shouldReceive('get')
                ->with($expectedUrl)
                ->once()
                ->andReturn(Http::response($apiResponse, 200));

            $result = $this->traitInstance->getUrl($request);

            expect($result)->toBe($apiResponse);
        });

        // currency_converter driver
        test('getUrl for currency_converter calls internal getCurrencyConverterUrl and returns response', function () {
            $request = new Request(['driver' => 'currency_converter', 'key' => 'test_cc_key', 'driver_config' => ['type' => 'FREE']]);
            $converterBaseUrl = "https://free.currconv.com";
            $expectedUrl = "{$converterBaseUrl}/api/v7/currencies?apiKey={$request->key}";
            $apiResponse = ['results' => ['USD' => ['currencyName' => 'US Dollar']]];

            // Mock the internal getCurrencyConverterUrl method
            $this->traitInstance->shouldReceive('getCurrencyConverterUrl')
                ->with($request->driver_config)
                ->once()
                ->andReturn($converterBaseUrl);

            $this->httpFacadeMock->shouldReceive('get')
                ->with($expectedUrl)
                ->once()
                ->andReturn(Http::response($apiResponse, 200));

            $result = $this->traitInstance->getUrl($request);

            expect($result)->toBe($apiResponse);
        });
