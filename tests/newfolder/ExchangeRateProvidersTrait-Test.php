<?php

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Mockery; // Explicitly add Mockery for clarity

// Define a simple class that uses the trait for testing
// This class also has a public respondJson method to intercept calls to the global helper
// and allows us to make it a partial mock to mock its own methods.
//
// NOTE: Based on the debug output, the trait *actually* calls a global 'respondJson' helper,
// which then calls response()->json(). This method in TraitTestClass is currently not used
// by the trait under test directly, as suggested by the error trace.
// For error handling tests, we will mock the `response()->json()` calls via $this->mockResponseFactory.
class TraitTestClass
{
    use \Crater\Traits\ExchangeRateProvidersTrait;

    // This method is present in TraitTestClass but not directly invoked by the trait itself
    // when using the global `respondJson` helper function.
    public function respondJson($message, $error)
    {
        // Return a recognizable structure for assertion
        return (new JsonResponse([
            'message' => $message,
            'error' => $error,
        ], 400)); // Assuming 400 for error responses
    }
}

// Helper function for mocking Http responses returned by Http::get().
// This ensures the mock response has `json`, `status`, `successful`, `failed` methods
// which are commonly used by the Http client and expected by the trait logic.
function mockHttpResponse(array|null $json = [], int $status = 200): Mockery\MockInterface
{
    $mockResponse = Mockery::mock(\Illuminate\Http\Client\Response::class);
    // Use byDefault() to avoid strict expectation counting if these methods are called multiple times
    // or not at all in some paths, but are generally available.
    $mockResponse->shouldReceive('json')->andReturn($json)->byDefault();
    $mockResponse->shouldReceive('status')->andReturn($status)->byDefault();
    $mockResponse->shouldReceive('successful')->andReturn($status >= 200 && $status < 300)->byDefault();
    $mockResponse->shouldReceive('failed')->andReturn($status >= 400 || $status < 200)->byDefault();
    // Add more methods if the trait might use them, e.g., ->body(), ->collect()
    return $mockResponse;
}

// The main Pest test suite for ExchangeRateProvidersTrait
beforeEach(function () {
    // Create a mock for the ResponseFactory (used by response()->json() helper)
    $this->mockResponseFactory = Mockery::mock(ResponseFactory::class);
    app()->instance(ResponseFactory::class, $this->mockResponseFactory);

    // Mock the Http facade using its alias for static mocking (e.g., Http::get())
    $this->httpFacadeMock = Mockery::mock('alias:Illuminate\Support\Facades\Http');

    // Create a partial mock of the TraitTestClass.
    // This allows us to mock its public methods like getUrl, getCurrencyConverterUrl later,
    // while allowing the rest of the trait's logic to execute.
    $this->traitInstance = Mockery::mock(TraitTestClass::class)->makePartial();
});

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
        ->andReturn(mockHttpResponse([
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
        ->with("https://api.currencyfreaks.com/latest?apikey={$filter['key']}&symbols={$currencyCode}&base={$baseCurrencyCode}")
        ->once()
        ->andReturn(mockHttpResponse([
            'success' => false,
            'error' => ['message' => $errorMessage],
        ], 200)); // API returns 200 but with 'success: false'

    // The trait calls a global `respondJson($error, $message, $status)` helper.
    // We assume the trait calls it as `respondJson($errorMessage, $errorMessage, 400)`.
    // The helper then passes `['error' => $errorMessage, 'message' => $errorMessage]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $errorMessage, 'message' => $errorMessage], 400)
        ->andReturn(new JsonResponse(['error' => $errorMessage, 'message' => $errorMessage], 400));

    $response = $this->traitInstance->getExchangeRate($filter, $baseCurrencyCode, $currencyCode);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true)['message'])->toBe($errorMessage)
        ->and($response->getData(true)['error'])->toBe($errorMessage)
        ->and($response->getStatusCode())->toBe(400);
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
        ->andReturn(mockHttpResponse([
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
        ->with("http://api.currencylayer.com/live?access_key={$filter['key']}&source={$baseCurrencyCode}&currencies={$currencyCode}")
        ->once()
        ->andReturn(mockHttpResponse([
            'success' => false,
            'error' => ['code' => 101, 'type' => 'missing_access_key', 'info' => $errorInfo],
        ], 200)); // API returns 200 but with 'success: false'

    // The trait calls `respondJson($errorInfo, $errorInfo, 400)`.
    // Helper passes `['error' => $errorInfo, 'message' => $errorInfo]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $errorInfo, 'message' => $errorInfo], 400)
        ->andReturn(new JsonResponse(['error' => $errorInfo, 'message' => $errorInfo], 400));

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
        ->andReturn(mockHttpResponse([
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
        ->with("https://openexchangerates.org/api/latest.json?app_id={$filter['key']}&base={$baseCurrencyCode}&symbols={$currencyCode}")
        ->once()
        ->andReturn(mockHttpResponse([
            'error' => true,
            'status' => 401,
            'message' => $errorMessage,
            'description' => $errorDescription,
        ], 401)); // Actual API returns 401 status

    // The trait calls `respondJson($errorDescription, $errorMessage, 400)`.
    // Helper passes `['error' => $errorDescription, 'message' => $errorMessage]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $errorDescription, 'message' => $errorMessage], 400)
        ->andReturn(new JsonResponse(['error' => $errorDescription, 'message' => $errorMessage], 400));

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

    // Mock the internal getCurrencyConverterUrl method as the trait will call it.
    $this->traitInstance->shouldReceive('getCurrencyConverterUrl')
        ->once()
        ->with($filter['driver_config'])
        ->andReturn($converterBaseUrl);

    $this->httpFacadeMock->shouldReceive('get')
        ->with("{$converterBaseUrl}/api/v7/convert?apiKey={$filter['key']}&q={$query}&compact=y")
        ->once()
        ->andReturn(mockHttpResponse([
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

// --- Tests for getCurrencyConverterUrl method (these were already passing) ---
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
        ->andReturn(mockHttpResponse($currencySymbolsResponse, 200));

    // getUrl is mocked to return the array directly. The `getUrl` method is expected to return the JSON body.
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
        ->andReturn(mockHttpResponse(null, 200)); // Simulating a response where json() would return null (e.g., empty body or invalid JSON)

    $this->traitInstance->shouldNotReceive('getUrl'); // Should not be called if first API fails

    // The trait calls `respondJson($error_message, $server_message, 400)`.
    // Helper passes `['error' => $error_message, 'message' => $server_message]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $error_message, 'message' => $server_message], 400)
        ->andReturn(new JsonResponse(['error' => $error_message, 'message' => $server_message], 400));

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
        ->andReturn(mockHttpResponse($currencySymbolsResponse, 200));

    $this->traitInstance->shouldReceive('getUrl')
        ->with($request)
        ->once()
        ->andReturn(null); // Simulating getUrl returning null, indicating a failure to check the key

    // The trait calls `respondJson($error_message, $server_message, 400)`.
    // Helper passes `['error' => $error_message, 'message' => $server_message]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $error_message, 'message' => $server_message], 400)
        ->andReturn(new JsonResponse(['error' => $error_message, 'message' => $server_message], 400));

    $response = $this->traitInstance->getSupportedCurrencies($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true)['message'])->toBe($server_message)
        ->and($response->getData(true)['error'])->toBe($error_message)
        ->and($response->getStatusCode())->toBe(400);
});

test('currency_freak handles invalid key error', function () use ($message, $error) {
    $request = new Request(['driver' => 'currency_freak', 'key' => 'invalid_freak_key']);
    $currencySymbolsResponse = ['USD' => 'US Dollar'];
    // This is the response structure when the key check fails (via getUrl)
    $checkKeyErrorResponse = [
        'success' => false,
        'error' => ['status' => 404, 'message' => 'Not Found'] // Example error from API
    ];

    $this->httpFacadeMock->shouldReceive('get')
        ->with("https://api.currencyfreaks.com/currency-symbols")
        ->once()
        ->andReturn(mockHttpResponse($currencySymbolsResponse, 200));

    $this->traitInstance->shouldReceive('getUrl')
        ->with($request)
        ->once()
        ->andReturn($checkKeyErrorResponse);

    // The trait calls `respondJson($error, $message, 400)`.
    // Helper passes `['error' => $error, 'message' => $message]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $error, 'message' => $message], 400)
        ->andReturn(new JsonResponse(['error' => $error, 'message' => $message], 400));

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
        ->andReturn(mockHttpResponse([
            'success' => true,
            'currencies' => $currenciesResponse,
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
        ->with("http://api.currencylayer.com/list?access_key={$request->key}")
        ->once()
        ->andReturn(mockHttpResponse(null, 200)); // Simulating null json() response

    // The trait calls `respondJson($error_message, $server_message, 400)`.
    // Helper passes `['error' => $error_message, 'message' => $server_message]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $error_message, 'message' => $server_message], 400)
        ->andReturn(new JsonResponse(['error' => $error_message, 'message' => $server_message], 400));

    $response = $this->traitInstance->getSupportedCurrencies($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true)['message'])->toBe($server_message)
        ->and($response->getData(true)['error'])->toBe($error_message)
        ->and($response->getStatusCode())->toBe(400);
});

test('currency_layer handles invalid key/currencies missing', function () use ($message, $error) {
    $request = new Request(['driver' => 'currency_layer', 'key' => 'invalid_layer_key']);

    $this->httpFacadeMock->shouldReceive('get')
        ->with("http://api.currencylayer.com/list?access_key={$request->key}")
        ->once()
        ->andReturn(mockHttpResponse([
            'success' => false,
            'error' => ['code' => 101, 'type' => 'missing_access_key', 'info' => 'API Key Missing.'],
        ], 200)); // API returns 200 but no 'currencies' key, or 'success: false'

    // The trait calls `respondJson($error, $message, 400)`.
    // Helper passes `['error' => $error, 'message' => $message]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $error, 'message' => $message], 400)
        ->andReturn(new JsonResponse(['error' => $error, 'message' => $message], 400));

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
    $checkKeyResponse = ['base' => 'USD', 'rates' => ['INR' => 74.5]]; // A valid key response from getUrl

    $this->httpFacadeMock->shouldReceive('get')
        ->with("https://openexchangerates.org/api/currencies.json")
        ->once()
        ->andReturn(mockHttpResponse($currenciesResponse, 200));

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
        ->andReturn(mockHttpResponse(null, 200)); // Simulating null json() response

    $this->traitInstance->shouldNotReceive('getUrl'); // Should not be called if first API fails

    // The trait calls `respondJson($error_message, $server_message, 400)`.
    // Helper passes `['error' => $error_message, 'message' => $server_message]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $error_message, 'message' => $server_message], 400)
        ->andReturn(new JsonResponse(['error' => $error_message, 'message' => $server_message], 400));

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
        ->andReturn(mockHttpResponse($currenciesResponse, 200));

    $this->traitInstance->shouldReceive('getUrl')
        ->with($request)
        ->once()
        ->andReturn(null); // Simulating null json() response from getUrl

    // The trait calls `respondJson($error_message, $server_message, 400)`.
    // Helper passes `['error' => $error_message, 'message' => $server_message]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $error_message, 'message' => $server_message], 400)
        ->andReturn(new JsonResponse(['error' => $error_message, 'message' => $server_message], 400));

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
        ->andReturn(mockHttpResponse($currenciesResponse, 200));

    $this->traitInstance->shouldReceive('getUrl')
        ->with($request)
        ->once()
        ->andReturn($checkKeyErrorResponse);

    // The trait calls `respondJson($error, $message, 400)`.
    // Helper passes `['error' => $error, 'message' => $message]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $error, 'message' => $message], 400)
        ->andReturn(new JsonResponse(['error' => $error, 'message' => $message], 400));

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

    // The `getSupportedCurrencies` method for this driver calls `getUrl` internally
    // and expects its direct return value to contain the 'results' key.
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
        ->andReturn(null); // Simulating null response from getUrl

    // The trait calls `respondJson($error_message, $server_message, 400)`.
    // Helper passes `['error' => $error_message, 'message' => $server_message]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $error_message, 'message' => $server_message], 400)
        ->andReturn(new JsonResponse(['error' => $error_message, 'message' => $server_message], 400));

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

    // The trait calls `respondJson($error, $message, 400)`.
    // Helper passes `['error' => $error, 'message' => $message]` to `response()->json()`.
    $this->mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => $error, 'message' => $message], 400)
        ->andReturn(new JsonResponse(['error' => $error, 'message' => $message], 400));

    $response = $this->traitInstance->getSupportedCurrencies($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true)['message'])->toBe($message)
        ->and($response->getData(true)['error'])->toBe($error)
        ->and($response->getStatusCode())->toBe(400);
});

// --- Tests for getUrl method (these mostly had the Http::response() error) ---
test('getUrl for currency_freak constructs correct URL and returns response', function () {
    $request = new Request(['driver' => 'currency_freak', 'key' => 'test_freak_key']);
    // The default base and symbols are USD and INR for getUrl method if not specified in request.
    $expectedUrl = "https://api.currencyfreaks.com/latest?apikey={$request->key}&symbols=INR&base=USD";
    $apiResponse = ['success' => true, 'rates' => ['USDINR' => 74.5]];

    $this->httpFacadeMock->shouldReceive('get')
        ->with($expectedUrl)
        ->once()
        ->andReturn(mockHttpResponse($apiResponse, 200));

    $result = $this->traitInstance->getUrl($request);

    expect($result)->toBe($apiResponse);
});

// currency_layer driver
test('getUrl for currency_layer constructs correct URL and returns response', function () {
    $request = new Request(['driver' => 'currency_layer', 'key' => 'test_layer_key']);
    // The default base and symbols are INR and USD for getUrl method if not specified in request.
    $expectedUrl = "http://api.currencylayer.com/live?access_key={$request->key}&source=INR&currencies=USD";
    $apiResponse = ['success' => true, 'quotes' => ['USDINR' => 74.5]];

    $this->httpFacadeMock->shouldReceive('get')
        ->with($expectedUrl)
        ->once()
        ->andReturn(mockHttpResponse($apiResponse, 200));

    $result = $this->traitInstance->getUrl($request);

    expect($result)->toBe($apiResponse);
});

// open_exchange_rate driver
test('getUrl for open_exchange_rate constructs correct URL and returns response', function () {
    $request = new Request(['driver' => 'open_exchange_rate', 'key' => 'test_oexr_key']);
    // The default base and symbols are INR and USD for getUrl method if not specified in request.
    $expectedUrl = "https://openexchangerates.org/api/latest.json?app_id={$request->key}&base=INR&symbols=USD";
    $apiResponse = ['timestamp' => 123, 'base' => 'INR', 'rates' => ['USD' => 0.013]];

    $this->httpFacadeMock->shouldReceive('get')
        ->with($expectedUrl)
        ->once()
        ->andReturn(mockHttpResponse($apiResponse, 200));

    $result = $this->traitInstance->getUrl($request);

    expect($result)->toBe($apiResponse);
});

// currency_converter driver
test('getUrl for currency_converter calls internal getCurrencyConverterUrl and returns response', function () {
    $request = new Request(['driver' => 'currency_converter', 'key' => 'test_cc_key', 'driver_config' => ['type' => 'FREE']]);
    $converterBaseUrl = "https://free.currconv.com";
    // For getUrl, currency_converter fetches all currencies list if specific base/symbols are not in request
    $expectedUrl = "{$converterBaseUrl}/api/v7/currencies?apiKey={$request->key}";
    $apiResponse = ['results' => ['USD' => ['currencyName' => 'US Dollar']]];

    // Mock the internal getCurrencyConverterUrl method, as it's a dependency of getUrl.
    $this->traitInstance->shouldReceive('getCurrencyConverterUrl')
        ->with($request->driver_config)
        ->once()
        ->andReturn($converterBaseUrl);

    $this->httpFacadeMock->shouldReceive('get')
        ->with($expectedUrl)
        ->once()
        ->andReturn(mockHttpResponse($apiResponse, 200));

    $result = $this->traitInstance->getUrl($request);

    expect($result)->toBe($apiResponse);
});