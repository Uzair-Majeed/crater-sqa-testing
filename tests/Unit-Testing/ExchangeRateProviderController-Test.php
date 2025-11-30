<?php

use Tests\TestCase;
use Crater\Http\Controllers\V1\Admin\ExchangeRate\ExchangeRateProviderController;
use Crater\Http\Requests\ExchangeRateProviderRequest;
use Crater\Http\Resources\ExchangeRateProviderResource;
use Crater\Models\ExchangeRateProvider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Mockery\MockInterface;

// Define helper functions if they don't exist in the test environment,
// otherwise Laravel's will be used. This provides a controlled environment for unit testing.
if (!function_exists('respondJson')) {
    function respondJson(string $key, string $message, int $status = 400): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}

uses(TestCase::class)->group('exchange-rate-provider-controller');

beforeEach(function () {
    $this->controller = new ExchangeRateProviderController();
});

// --- index method tests ---
test('index displays a listing of exchange rate providers with a specified limit', function () {
    // Mock dependencies
    $request = Mockery::mock(Request::class);
    $paginator = Mockery::mock(LengthAwarePaginator::class);
    $resourceCollection = Mockery::mock(AnonymousResourceCollection::class);

    // Set up expectations for Request
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    // Accessing limit as a property per original code
    $request->limit = 10;

    // Mock the static calls on ExchangeRateProvider
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldReceive('whereCompany')
        ->once()
        ->andReturnSelf()
        ->shouldReceive('paginate')
        ->once()
        ->with(10)
        ->andReturn($paginator);

    // Mock ExchangeRateProviderResource::collection
    Mockery::mock('alias:' . ExchangeRateProviderResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with($paginator)
        ->andReturn($resourceCollection);

    // Mock the authorize method on the controller
    $this->controller = Mockery::partialMock(ExchangeRateProviderController::class, function (MockInterface $mock) {
        $mock->shouldReceive('authorize')->once()->with('viewAny', ExchangeRateProvider::class)->andReturn(true);
    });

    // Call the method under test
    $result = $this->controller->index($request);

    // Assertions
    expect($result)->toBe($resourceCollection);
});

test('index displays a listing of exchange rate providers with default limit', function () {
    // Mock dependencies
    $request = Mockery::mock(Request::class);
    $paginator = Mockery::mock(LengthAwarePaginator::class);
    $resourceCollection = Mockery::mock(AnonymousResourceCollection::class);

    // Set up expectations for Request
    $request->shouldReceive('has')->with('limit')->andReturn(false);

    // Mock the static calls on ExchangeRateProvider
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldReceive('whereCompany')
        ->once()
        ->andReturnSelf()
        ->shouldReceive('paginate')
        ->once()
        ->with(5) // Default limit
        ->andReturn($paginator);

    // Mock ExchangeRateProviderResource::collection
    Mockery::mock('alias:' . ExchangeRateProviderResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with($paginator)
        ->andReturn($resourceCollection);

    // Mock the authorize method on the controller
    $this->controller = Mockery::partialMock(ExchangeRateProviderController::class, function (MockInterface $mock) {
        $mock->shouldReceive('authorize')->once()->with('viewAny', ExchangeRateProvider::class)->andReturn(true);
    });

    // Call the method under test
    $result = $this->controller->index($request);

    // Assertions
    expect($result)->toBe($resourceCollection);
});

// --- store method tests ---
test('store successfully creates an exchange rate provider', function () {
    // Mock dependencies
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class); // Mock instance for the created model
    $apiCheckResponse = Mockery::mock(JsonResponse::class);

    // Set up expectations for checkActiveCurrencies (static method)
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldReceive('checkActiveCurrencies')
        ->once()
        ->with($request)
        ->andReturn([]); // No active currencies used

    // Set up expectations for checkExchangeRateProviderStatus (static method)
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldReceive('checkExchangeRateProviderStatus')
        ->once()
        ->with($request)
        ->andReturn($apiCheckResponse);

    $apiCheckResponse->shouldReceive('status')->once()->andReturn(200);

    // Set up expectations for createFromRequest (static method)
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldReceive('createFromRequest')
        ->once()
        ->with($request)
        ->andReturn($exchangeRateProvider);

    // Mock the authorize method on the controller
    $this->controller = Mockery::partialMock(ExchangeRateProviderController::class, function (MockInterface $mock) {
        $mock->shouldReceive('authorize')->once()->with('create', ExchangeRateProvider::class)->andReturn(true);
    });

    // Call the method under test
    $result = $this->controller->store($request);

    // Assertions
    // We expect an instance of ExchangeRateProviderResource, encapsulating the mocked model
    expect($result)->toBeInstanceOf(ExchangeRateProviderResource::class);
    // Use reflection to assert the underlying resource data, which is passed in the constructor
    $reflectedResource = new ReflectionClass($result);
    $modelProperty = $reflectedResource->getProperty('resource');
    $modelProperty->setAccessible(true);
    expect($modelProperty->getValue($result))->toBe($exchangeRateProvider);
});

test('store returns error if currency is already used', function () {
    // Mock dependencies
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $queryResult = ['some_currency']; // Non-empty array

    // Set up expectations for checkActiveCurrencies (static method)
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldReceive('checkActiveCurrencies')
        ->once()
        ->with($request)
        ->andReturn($queryResult);

    // Ensure no further calls are made after returning early
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldNotReceive('checkExchangeRateProviderStatus');
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldNotReceive('createFromRequest');

    // Mock the authorize method on the controller
    $this->controller = Mockery::partialMock(ExchangeRateProviderController::class, function (MockInterface $mock) {
        $mock->shouldReceive('authorize')->once()->with('create', ExchangeRateProvider::class)->andReturn(true);
    });

    // Call the method under test
    $result = $this->controller->store($request);

    // Assertions
    expect($result)->toBeInstanceOf(JsonResponse::class);
    expect($result->getData(true))->toMatchArray(['message' => 'Currency used.']);
    expect($result->getStatusCode())->toBe(400); // Default status for respondJson
});

test('store returns API check response if status is not 200', function () {
    // Mock dependencies
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $apiCheckResponse = Mockery::mock(JsonResponse::class);

    // Set up expectations for checkActiveCurrencies (static method)
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldReceive('checkActiveCurrencies')
        ->once()
        ->with($request)
        ->andReturn([]); // No active currencies used

    // Set up expectations for checkExchangeRateProviderStatus (static method)
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldReceive('checkExchangeRateProviderStatus')
        ->once()
        ->with($request)
        ->andReturn($apiCheckResponse);

    $apiCheckResponse->shouldReceive('status')->once()->andReturn(500); // API check failure

    // Ensure createFromRequest is NOT called
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldNotReceive('createFromRequest');

    // Mock the authorize method on the controller
    $this->controller = Mockery::partialMock(ExchangeRateProviderController::class, function (MockInterface $mock) {
        $mock->shouldReceive('authorize')->once()->with('create', ExchangeRateProvider::class)->andReturn(true);
    });

    // Call the method under test
    $result = $this->controller->store($request);

    // Assertions
    expect($result)->toBe($apiCheckResponse);
});

// --- show method tests ---
test('show displays the specified exchange rate provider', function () {
    // Mock dependencies
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);

    // Mock the authorize method on the controller
    $this->controller = Mockery::partialMock(ExchangeRateProviderController::class, function (MockInterface $mock) {
        $mock->shouldReceive('authorize')->once()->with('view', $exchangeRateProvider)->andReturn(true);
    });

    // Call the method under test
    $result = $this->controller->show($exchangeRateProvider);

    // Assertions
    expect($result)->toBeInstanceOf(ExchangeRateProviderResource::class);
    // Verify the model passed to the resource
    $reflectedResource = new ReflectionClass($result);
    $modelProperty = $reflectedResource->getProperty('resource');
    $modelProperty->setAccessible(true);
    expect($modelProperty->getValue($result))->toBe($exchangeRateProvider);
});

// --- update method tests ---
test('update successfully updates an exchange rate provider', function () {
    // Mock dependencies
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $apiCheckResponse = Mockery::mock(JsonResponse::class);

    // Set up expectations for checkUpdateActiveCurrencies on the model instance
    $exchangeRateProvider->shouldReceive('checkUpdateActiveCurrencies')
        ->once()
        ->with($request)
        ->andReturn([]); // No active currencies used

    // Set up expectations for checkExchangeRateProviderStatus (static method)
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldReceive('checkExchangeRateProviderStatus')
        ->once()
        ->with($request)
        ->andReturn($apiCheckResponse);

    $apiCheckResponse->shouldReceive('status')->once()->andReturn(200);

    // Set up expectations for updateFromRequest on the model instance
    $exchangeRateProvider->shouldReceive('updateFromRequest')
        ->once()
        ->with($request)
        ->andReturnSelf(); // Update method usually returns void or the instance

    // Mock the authorize method on the controller
    $this->controller = Mockery::partialMock(ExchangeRateProviderController::class, function (MockInterface $mock) {
        $mock->shouldReceive('authorize')->once()->with('update', $exchangeRateProvider)->andReturn(true);
    });

    // Call the method under test
    $result = $this->controller->update($request, $exchangeRateProvider);

    // Assertions
    expect($result)->toBeInstanceOf(ExchangeRateProviderResource::class);
    // Verify the model passed to the resource
    $reflectedResource = new ReflectionClass($result);
    $modelProperty = $reflectedResource->getProperty('resource');
    $modelProperty->setAccessible(true);
    expect($modelProperty->getValue($result))->toBe($exchangeRateProvider);
});

test('update returns error if currency is already used', function () {
    // Mock dependencies
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $queryResult = ['some_currency']; // Non-empty array

    // Set up expectations for checkUpdateActiveCurrencies on the model instance
    $exchangeRateProvider->shouldReceive('checkUpdateActiveCurrencies')
        ->once()
        ->with($request)
        ->andReturn($queryResult);

    // Ensure no further calls are made after returning early
    $exchangeRateProvider->shouldNotReceive('updateFromRequest');
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldNotReceive('checkExchangeRateProviderStatus');

    // Mock the authorize method on the controller
    $this->controller = Mockery::partialMock(ExchangeRateProviderController::class, function (MockInterface $mock) {
        $mock->shouldReceive('authorize')->once()->with('update', $exchangeRateProvider)->andReturn(true);
    });

    // Call the method under test
    $result = $this->controller->update($request, $exchangeRateProvider);

    // Assertions
    expect($result)->toBeInstanceOf(JsonResponse::class);
    expect($result->getData(true))->toMatchArray(['message' => 'Currency used.']);
    expect($result->getStatusCode())->toBe(400);
});

test('update returns API check response if status is not 200', function () {
    // Mock dependencies
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $apiCheckResponse = Mockery::mock(JsonResponse::class);

    // Set up expectations for checkUpdateActiveCurrencies on the model instance
    $exchangeRateProvider->shouldReceive('checkUpdateActiveCurrencies')
        ->once()
        ->with($request)
        ->andReturn([]); // No active currencies used

    // Set up expectations for checkExchangeRateProviderStatus (static method)
    Mockery::mock('alias:' . ExchangeRateProvider::class)
        ->shouldReceive('checkExchangeRateProviderStatus')
        ->once()
        ->with($request)
        ->andReturn($apiCheckResponse);

    $apiCheckResponse->shouldReceive('status')->once()->andReturn(500); // API check failure

    // Ensure updateFromRequest is NOT called
    $exchangeRateProvider->shouldNotReceive('updateFromRequest');

    // Mock the authorize method on the controller
    $this->controller = Mockery::partialMock(ExchangeRateProviderController::class, function (MockInterface $mock) {
        $mock->shouldReceive('authorize')->once()->with('update', $exchangeRateProvider)->andReturn(true);
    });

    // Call the method under test
    $result = $this->controller->update($request, $exchangeRateProvider);

    // Assertions
    expect($result)->toBe($apiCheckResponse);
});

// --- destroy method tests ---
test('destroy successfully deletes an inactive exchange rate provider', function () {
    // Mock dependencies
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);

    // Set up expectations for active property
    // Mockery can intercept property access for objects it mocks, or you can define it
    $exchangeRateProvider->shouldReceive('getAttribute')->with('active')->andReturn(false);

    // Set up expectations for delete
    $exchangeRateProvider->shouldReceive('delete')->once()->andReturn(true);

    // Mock the authorize method on the controller
    $this->controller = Mockery::partialMock(ExchangeRateProviderController::class, function (MockInterface $mock) {
        $mock->shouldReceive('authorize')->once()->with('delete', $exchangeRateProvider)->andReturn(true);
    });

    // Call the method under test
    $result = $this->controller->destroy($exchangeRateProvider);

    // Assertions
    expect($result)->toBeInstanceOf(JsonResponse::class);
    expect($result->getData(true))->toMatchArray(['success' => true]);
    expect($result->getStatusCode())->toBe(200); // Default status for response()->json()
});

test('destroy returns error if exchange rate provider is active', function () {
    // Mock dependencies
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);

    // Set up expectations for active property
    $exchangeRateProvider->shouldReceive('getAttribute')->with('active')->andReturn(true); // Active

    // Expect delete not to be called
    $exchangeRateProvider->shouldNotReceive('delete');

    // Mock the authorize method on the controller
    $this->controller = Mockery::partialMock(ExchangeRateProviderController::class, function (MockInterface $mock) {
        $mock->shouldReceive('authorize')->once()->with('delete', $exchangeRateProvider)->andReturn(true);
    });

    // Call the method under test
    $result = $this->controller->destroy($exchangeRateProvider);

    // Assertions
    expect($result)->toBeInstanceOf(JsonResponse::class);
    expect($result->getData(true))->toMatchArray(['message' => 'Provider Active.']);
    expect($result->getStatusCode())->toBe(400); // Default status for respondJson
});
