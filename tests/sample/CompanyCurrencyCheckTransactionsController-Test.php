<?php

use Crater\Http\Controllers\V1\Admin\Settings\CompanyCurrencyCheckTransactionsController;
use Crater\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    // Reset static mocks for each test
    Company::shouldReceive('find')->passthru(); // Reset any lingering Mockery class aliasing
    Mockery::close();
});

afterEach(function () {
    // Reset static mocks for each test
    Company::shouldReceive('find')->passthru();
    Mockery::close();
});

test('it returns true for has_transactions when company has transactions', function () {
    // 1. Arrange
    $companyId = '123';

    // Mock the Request object
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId);

    // Mock the Company model instance
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->shouldReceive('hasTransactions')
        ->once()
        ->andReturn(true);

    // Mock the static find method of the Company model using instance mocking
    // Use Laravel's Facade/Static method mocking via Mockery::mock() not available if already loaded.
    // Instead, use Facade or partial mock via Laravel. Or, override with global override.
    Company::shouldReceive('find')
        ->with($companyId)
        ->andReturn($mockCompany);

    // Create a partial mock of the controller to mock its authorize method
    $controller = Mockery::mock(CompanyCurrencyCheckTransactionsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage company', $mockCompany)
        ->andReturn(true); // Simulate successful authorization

    // 2. Act
    $response = $controller->__invoke($mockRequest);

    // 3. Assert
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['has_transactions' => true]);
});

test('it returns false for has_transactions when company has no transactions', function () {
    // 1. Arrange
    $companyId = '123';

    // Mock the Request object
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId);

    // Mock the Company model instance
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->shouldReceive('hasTransactions')
        ->once()
        ->andReturn(false);

    // Mock the static find method of the Company model
    Company::shouldReceive('find')
        ->with($companyId)
        ->andReturn($mockCompany);

    // Create a partial mock of the controller to mock its authorize method
    $controller = Mockery::mock(CompanyCurrencyCheckTransactionsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage company', $mockCompany)
        ->andReturn(true); // Simulate successful authorization

    // 2. Act
    $response = $controller->__invoke($mockRequest);

    // 3. Assert
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['has_transactions' => false]);
});

test('it throws AuthorizationException when company is not found', function () {
    // 1. Arrange
    $companyId = '123';

    // Mock the Request object
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId);

    // Mock the static find method of the Company model to return null
    Company::shouldReceive('find')
        ->with($companyId)
        ->andReturn(null);

    // Create a partial mock of the controller to mock its authorize method
    $controller = Mockery::mock(CompanyCurrencyCheckTransactionsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage company', null) // Authorize will be called with null when company is not found
        ->andThrow(new AuthorizationException('Unauthorized to manage company. Company not found.')); // Simulate authorization failure

    // 2. Act & 3. Assert
    expect(function () use ($controller, $mockRequest) {
        $controller->__invoke($mockRequest);
    })->throws(AuthorizationException::class, 'Unauthorized to manage company. Company not found.');
});

test('it throws AuthorizationException when authorization fails even if company is found', function () {
    // 1. Arrange
    $companyId = '123';

    // Mock the Request object
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId);

    // Mock the Company model instance
    $mockCompany = Mockery::mock(Company::class);
    // hasTransactions should not be called if authorization fails before it.
    $mockCompany->shouldNotReceive('hasTransactions');

    // Mock the static find method of the Company model
    Company::shouldReceive('find')
        ->with($companyId)
        ->andReturn($mockCompany);

    // Create a partial mock of the controller to mock its authorize method
    $controller = Mockery::mock(CompanyCurrencyCheckTransactionsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage company', $mockCompany)
        ->andThrow(new AuthorizationException('Not allowed to manage this specific company.'));

    // 2. Act & 3. Assert
    expect(function () use ($controller, $mockRequest) {
        $controller->__invoke($mockRequest);
    })->throws(AuthorizationException::class, 'Not allowed to manage this specific company.');
});

test('it handles empty company header by implicitly failing authorization', function () {
    // 1. Arrange
    $companyId = ''; // Empty company ID

    // Mock the Request object
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId);

    // Mock the static find method of the Company model to return null for empty/invalid ID
    Company::shouldReceive('find')
        ->with($companyId) // Eloquent's find might return null for empty string or 0
        ->andReturn(null);

    // Create a partial mock of the controller to mock its authorize method
    $controller = Mockery::mock(CompanyCurrencyCheckTransactionsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage company', null) // Authorize will be called with null
        ->andThrow(new AuthorizationException('Company ID is missing or invalid.'));

    // 2. Act & 3. Assert
    expect(function () use ($controller, $mockRequest) {
        $controller->__invoke($mockRequest);
    })->throws(AuthorizationException::class, 'Company ID is missing or invalid.');
});