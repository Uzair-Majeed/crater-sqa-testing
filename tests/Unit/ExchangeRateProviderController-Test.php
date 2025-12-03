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

beforeEach(function () {
    $this->controller = new ExchangeRateProviderController();
    // Unalias before each test to avoid redeclare errors
    Mockery::getContainer()->_mockery_removeAlias(ExchangeRateProvider::class);
    Mockery::getContainer()->_mockery_removeAlias(ExchangeRateProviderResource::class);
});

test('index displays a listing of exchange rate providers with a specified limit', function () {
    $request = Mockery::mock(Request::class);
    $paginator = Mockery::mock(LengthAwarePaginator::class);
    $resourceCollection = Mockery::mock(AnonymousResourceCollection::class);

    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->limit = 10;

    $exchangeRateProviderMock = Mockery::mock('alias:' . ExchangeRateProvider::class);
    $exchangeRateProviderMock->shouldReceive('whereCompany')->once()->andReturnSelf();
    $exchangeRateProviderMock->shouldReceive('paginate')->once()->with(10)->andReturn($paginator);

    $exchangeRateProviderResourceMock = Mockery::mock('alias:' . ExchangeRateProviderResource::class);
    $exchangeRateProviderResourceMock->shouldReceive('collection')->once()->with($paginator)->andReturn($resourceCollection);

    $this->controller = Mockery::mock(ExchangeRateProviderController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->once()->with('viewAny', ExchangeRateProvider::class)->andReturn(true);

    $result = $this->controller->index($request);

    expect($result)->toBe($resourceCollection);
});

test('index displays a listing of exchange rate providers with default limit', function () {
    $request = Mockery::mock(Request::class);
    $paginator = Mockery::mock(LengthAwarePaginator::class);
    $resourceCollection = Mockery::mock(AnonymousResourceCollection::class);

    $request->shouldReceive('has')->with('limit')->andReturn(false);

    $exchangeRateProviderMock = Mockery::mock('alias:' . ExchangeRateProvider::class);
    $exchangeRateProviderMock->shouldReceive('whereCompany')->once()->andReturnSelf();
    $exchangeRateProviderMock->shouldReceive('paginate')->once()->with(5)->andReturn($paginator);

    $exchangeRateProviderResourceMock = Mockery::mock('alias:' . ExchangeRateProviderResource::class);
    $exchangeRateProviderResourceMock->shouldReceive('collection')->once()->with($paginator)->andReturn($resourceCollection);

    $this->controller = Mockery::mock(ExchangeRateProviderController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->once()->with('viewAny', ExchangeRateProvider::class)->andReturn(true);

    $result = $this->controller->index($request);

    expect($result)->toBe($resourceCollection);
});

test('store successfully creates an exchange rate provider', function () {
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $apiCheckResponse = Mockery::mock(JsonResponse::class);

    $exchangeRateProviderMock = Mockery::mock('alias:' . ExchangeRateProvider::class);
    $exchangeRateProviderMock->shouldReceive('checkActiveCurrencies')->once()->with($request)->andReturn([]);
    $exchangeRateProviderMock->shouldReceive('checkExchangeRateProviderStatus')->once()->with($request)->andReturn($apiCheckResponse);

    $apiCheckResponse->shouldReceive('status')->once()->andReturn(200);

    $exchangeRateProviderMock->shouldReceive('createFromRequest')->once()->with($request)->andReturn($exchangeRateProvider);

    $this->controller = Mockery::mock(ExchangeRateProviderController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->once()->with('create', ExchangeRateProvider::class)->andReturn(true);

    $result = $this->controller->store($request);

    expect($result)->toBeInstanceOf(ExchangeRateProviderResource::class);
    $reflectedResource = new ReflectionClass($result);
    $modelProperty = $reflectedResource->getProperty('resource');
    $modelProperty->setAccessible(true);
    expect($modelProperty->getValue($result))->toBe($exchangeRateProvider);
});

test('store returns error if currency is already used', function () {
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $queryResult = ['some_currency'];

    $exchangeRateProviderMock = Mockery::mock('alias:' . ExchangeRateProvider::class);
    $exchangeRateProviderMock->shouldReceive('checkActiveCurrencies')->once()->with($request)->andReturn($queryResult);

    $exchangeRateProviderMock->shouldReceive('checkExchangeRateProviderStatus')->never();
    $exchangeRateProviderMock->shouldReceive('createFromRequest')->never();

    $this->controller = Mockery::mock(ExchangeRateProviderController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->once()->with('create', ExchangeRateProvider::class)->andReturn(true);

    $result = $this->controller->store($request);

    expect($result)->toBeInstanceOf(JsonResponse::class);
    expect($result->getData(true))->toMatchArray(['message' => 'Currency used.']);
    expect($result->getStatusCode())->toBe(400);
});

test('store returns API check response if status is not 200', function () {
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $apiCheckResponse = Mockery::mock(JsonResponse::class);

    $exchangeRateProviderMock = Mockery::mock('alias:' . ExchangeRateProvider::class);
    $exchangeRateProviderMock->shouldReceive('checkActiveCurrencies')->once()->with($request)->andReturn([]);
    $exchangeRateProviderMock->shouldReceive('checkExchangeRateProviderStatus')->once()->with($request)->andReturn($apiCheckResponse);

    $apiCheckResponse->shouldReceive('status')->once()->andReturn(500);

    $exchangeRateProviderMock->shouldReceive('createFromRequest')->never();

    $this->controller = Mockery::mock(ExchangeRateProviderController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->once()->with('create', ExchangeRateProvider::class)->andReturn(true);

    $result = $this->controller->store($request);

    expect($result)->toBe($apiCheckResponse);
});

test('show displays the specified exchange rate provider', function () {
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);

    $this->controller = Mockery::mock(ExchangeRateProviderController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->once()->with('view', $exchangeRateProvider)->andReturn(true);

    $result = $this->controller->show($exchangeRateProvider);

    expect($result)->toBeInstanceOf(ExchangeRateProviderResource::class);
    $reflectedResource = new ReflectionClass($result);
    $modelProperty = $reflectedResource->getProperty('resource');
    $modelProperty->setAccessible(true);
    expect($modelProperty->getValue($result))->toBe($exchangeRateProvider);
});

test('update successfully updates an exchange rate provider', function () {
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $apiCheckResponse = Mockery::mock(JsonResponse::class);

    $exchangeRateProvider->shouldReceive('checkUpdateActiveCurrencies')->once()->with($request)->andReturn([]);

    $exchangeRateProviderMock = Mockery::mock('alias:' . ExchangeRateProvider::class);
    $exchangeRateProviderMock->shouldReceive('checkExchangeRateProviderStatus')->once()->with($request)->andReturn($apiCheckResponse);

    $apiCheckResponse->shouldReceive('status')->once()->andReturn(200);

    $exchangeRateProvider->shouldReceive('updateFromRequest')->once()->with($request)->andReturnSelf();

    $this->controller = Mockery::mock(ExchangeRateProviderController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->once()->with('update', $exchangeRateProvider)->andReturn(true);

    $result = $this->controller->update($request, $exchangeRateProvider);

    expect($result)->toBeInstanceOf(ExchangeRateProviderResource::class);
    $reflectedResource = new ReflectionClass($result);
    $modelProperty = $reflectedResource->getProperty('resource');
    $modelProperty->setAccessible(true);
    expect($modelProperty->getValue($result))->toBe($exchangeRateProvider);
});

test('update returns error if currency is already used', function () {
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $queryResult = ['some_currency'];

    $exchangeRateProvider->shouldReceive('checkUpdateActiveCurrencies')->once()->with($request)->andReturn($queryResult);
    $exchangeRateProvider->shouldReceive('updateFromRequest')->never();

    $exchangeRateProviderMock = Mockery::mock('alias:' . ExchangeRateProvider::class);
    $exchangeRateProviderMock->shouldReceive('checkExchangeRateProviderStatus')->never();

    $this->controller = Mockery::mock(ExchangeRateProviderController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->once()->with('update', $exchangeRateProvider)->andReturn(true);

    $result = $this->controller->update($request, $exchangeRateProvider);

    expect($result)->toBeInstanceOf(JsonResponse::class);
    expect($result->getData(true))->toMatchArray(['message' => 'Currency used.']);
    expect($result->getStatusCode())->toBe(400);
});

test('update returns API check response if status is not 200', function () {
    $request = Mockery::mock(ExchangeRateProviderRequest::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $apiCheckResponse = Mockery::mock(JsonResponse::class);

    $exchangeRateProvider->shouldReceive('checkUpdateActiveCurrencies')->once()->with($request)->andReturn([]);

    $exchangeRateProviderMock = Mockery::mock('alias:' . ExchangeRateProvider::class);
    $exchangeRateProviderMock->shouldReceive('checkExchangeRateProviderStatus')->once()->with($request)->andReturn($apiCheckResponse);

    $apiCheckResponse->shouldReceive('status')->once()->andReturn(500);

    $exchangeRateProvider->shouldReceive('updateFromRequest')->never();

    $this->controller = Mockery::mock(ExchangeRateProviderController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->once()->with('update', $exchangeRateProvider)->andReturn(true);

    $result = $this->controller->update($request, $exchangeRateProvider);

    expect($result)->toBe($apiCheckResponse);
});

test('destroy successfully deletes an inactive exchange rate provider', function () {
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);

    $exchangeRateProvider->shouldReceive('getAttribute')->with('active')->andReturn(false);

    $exchangeRateProvider->shouldReceive('delete')->once()->andReturn(true);

    $this->controller = Mockery::mock(ExchangeRateProviderController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->once()->with('delete', $exchangeRateProvider)->andReturn(true);

    $result = $this->controller->destroy($exchangeRateProvider);

    expect($result)->toBeInstanceOf(JsonResponse::class);
    expect($result->getData(true))->toMatchArray(['success' => true]);
    expect($result->getStatusCode())->toBe(200);
});

test('destroy returns error if exchange rate provider is active', function () {
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);

    $exchangeRateProvider->shouldReceive('getAttribute')->with('active')->andReturn(true);
    $exchangeRateProvider->shouldReceive('delete')->never();

    $this->controller = Mockery::mock(ExchangeRateProviderController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->once()->with('delete', $exchangeRateProvider)->andReturn(true);

    $result = $this->controller->destroy($exchangeRateProvider);

    expect($result)->toBeInstanceOf(JsonResponse::class);
    expect($result->getData(true))->toMatchArray(['message' => 'Provider Active.']);
    expect($result->getStatusCode())->toBe(400);
});

afterEach(function () {
    Mockery::close();
    // Remove class aliases to prevent redeclaration on next test run
    Mockery::getContainer()->_mockery_removeAlias(ExchangeRateProvider::class);
    Mockery::getContainer()->_mockery_removeAlias(ExchangeRateProviderResource::class);
});