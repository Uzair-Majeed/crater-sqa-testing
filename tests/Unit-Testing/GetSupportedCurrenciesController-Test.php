<?php

use Illuminate\Http\Request;
use Crater\Models\ExchangeRateProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Crater\Http\Controllers\V1\Admin\ExchangeRate\GetSupportedCurrenciesController;

beforeEach(function () {
    // Ensure Mockery is reset before each test
    Mockery::close();
});

test('it successfully authorizes and delegates to getSupportedCurrencies', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $expectedCurrenciesData = ['USD', 'EUR', 'GBP'];
    $expectedResponse = new JsonResponse(['data' => $expectedCurrenciesData], 200);

    // Create a partial mock for the controller to mock its inherited methods (`authorize`)
    // and the trait method (`getSupportedCurrencies`) that it uses.
    $controller = Mockery::mock(GetSupportedCurrenciesController::class)->makePartial();

    // Expect the 'authorize' method to be called once with the correct arguments
    $controller->shouldReceive('authorize')
        ->once()
        ->with('viewAny', ExchangeRateProvider::class);

    // Expect the 'getSupportedCurrencies' method (from the trait) to be called once
    // with the request and return our predefined response.
    $controller->shouldReceive('getSupportedCurrencies')
        ->once()
        ->with($request)
        ->andReturn($expectedResponse);

    // Act
    $response = $controller($request); // Invokes the __invoke method

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true))->toEqual(['data' => $expectedCurrenciesData])
        ->and($response->getStatusCode())->toBe(200);
});

test('it throws AuthorizationException when authorization fails', function () {
    // Arrange
    $request = Mockery::mock(Request::class);

    $controller = Mockery::mock(GetSupportedCurrenciesController::class)->makePartial();

    // Expect the 'authorize' method to be called once and throw an AuthorizationException
    $controller->shouldReceive('authorize')
        ->once()
        ->with('viewAny', ExchangeRateProvider::class)
        ->andThrow(new AuthorizationException('User not authorized.'));

    // Ensure 'getSupportedCurrencies' is NOT called if authorization fails
    $controller->shouldNotReceive('getSupportedCurrencies');

    // Act & Assert
    $this->expectException(AuthorizationException::class);
    $this->expectExceptionMessage('User not authorized.');

    $controller($request); // Invokes the __invoke method
});




afterEach(function () {
    Mockery::close();
});
