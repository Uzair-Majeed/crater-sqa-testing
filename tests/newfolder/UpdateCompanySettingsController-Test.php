```php
<?php

use Crater\Http\Controllers\V1\Admin\Settings\UpdateCompanySettingsController;
use Crater\Http\Requests\UpdateSettingsRequest;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

beforeEach(function () {
    // Ensure Mockery is closed before each test to prevent mock expectation leaks
    Mockery::close();
});

test('it successfully updates settings when currency is not changed and no transactions exist', function () {
    // Arrange
    $companyId = 1;
    $requestData = [
        'currency' => 'USD', // Same as existing
        'tax_name' => 'VAT',
        'locale' => 'en',
    ];

    $mockRequest = Mockery::mock(UpdateSettingsRequest::class);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId);
    // Request properties are often accessed directly or via `all()`/`input()`
    // For unit tests, we can set the property directly on the mock if the controller accesses it as such.
    $mockRequest->settings = $requestData;

    // Use 'overload' to mock static methods like find() and also handle instance behavior
    $companyMock = Mockery::mock('overload:' . Company::class, ['id' => $companyId]);
    $companyMock->shouldReceive('find')
        ->with($companyId)
        ->andReturn($companyMock) // Static find() should return this mock instance
        ->once();
    $companyMock->shouldReceive('hasTransactions')->andReturn(false)->once(); // No transactions

    // Mock authorization to pass, using the same overload mock instance
    $controller = Mockery::mock(UpdateCompanySettingsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods()->shouldReceive('authorize')
        ->with('manage company', $companyMock)
        ->andReturn(true);

    Mockery::mock('alias:Illuminate\Support\Arr')
        ->shouldReceive('exists')
        ->with($requestData, 'currency')
        ->andReturn(true) // Currency key exists in request
        ->once();

    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn('USD') // Existing currency is USD, matches request
        ->once();

    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('setSettings')
        ->with($requestData, $companyId)
        ->once(); // setSettings should be called

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);
});

test('it successfully updates settings when currency is changed but no transactions exist', function () {
    // Arrange
    $companyId = 1;
    $requestData = [
        'currency' => 'EUR', // New currency
        'tax_name' => 'VAT',
    ];

    $mockRequest = Mockery::mock(UpdateSettingsRequest::class);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId);
    $mockRequest->settings = $requestData;

    // Use 'overload' to mock static methods like find() and also handle instance behavior
    $companyMock = Mockery::mock('overload:' . Company::class, ['id' => $companyId]);
    $companyMock->shouldReceive('find')
        ->with($companyId)
        ->andReturn($companyMock) // Static find() should return this mock instance
        ->once();
    $companyMock->shouldReceive('hasTransactions')->andReturn(false)->once(); // No transactions

    // Mock authorization to pass, using the same overload mock instance
    $controller = Mockery::mock(UpdateCompanySettingsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods()->shouldReceive('authorize')
        ->with('manage company', $companyMock)
        ->andReturn(true);

    Mockery::mock('alias:Illuminate\Support\Arr')
        ->shouldReceive('exists')
        ->with($requestData, 'currency')
        ->andReturn(true)
        ->once();

    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn('USD') // Existing currency is USD, different from request
        ->once();

    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('setSettings')
        ->with($requestData, $companyId)
        ->once(); // setSettings should be called

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);
});

test('it returns an error when currency is changed and transactions exist', function () {
    // Arrange
    $companyId = 1;
    $requestData = [
        'currency' => 'EUR', // New currency
        'tax_name' => 'VAT',
    ];

    $mockRequest = Mockery::mock(UpdateSettingsRequest::class);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId);
    $mockRequest->settings = $requestData;

    // Use 'overload' to mock static methods like find() and also handle instance behavior
    $companyMock = Mockery::mock('overload:' . Company::class, ['id' => $companyId]);
    $companyMock->shouldReceive('find')
        ->with($companyId)
        ->andReturn($companyMock) // Static find() should return this mock instance
        ->once();
    $companyMock->shouldReceive('hasTransactions')->andReturn(true)->once(); // Transactions exist

    // Mock authorization to pass, using the same overload mock instance
    $controller = Mockery::mock(UpdateCompanySettingsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods()->shouldReceive('authorize')
        ->with('manage company', $companyMock)
        ->andReturn(true);

    Mockery::mock('alias:Illuminate\Support\Arr')
        ->shouldReceive('exists')
        ->with($requestData, 'currency')
        ->andReturn(true)
        ->once();

    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn('USD') // Existing currency is USD, different from request
        ->once();

    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldNotReceive('setSettings'); // setSettings should NOT be called

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => false,
        'message' => 'Cannot update company currency after transactions are created.'
    ]);
});

test('it successfully updates settings when currency is not present in request data', function () {
    // Arrange
    $companyId = 1;
    $requestData = [
        'tax_name' => 'VAT',
        'locale' => 'en',
    ];

    $mockRequest = Mockery::mock(UpdateSettingsRequest::class);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId);
    $mockRequest->settings = $requestData;

    // Use 'overload' to mock static methods like find() and also handle instance behavior
    $companyMock = Mockery::mock('overload:' . Company::class, ['id' => $companyId]);
    $companyMock->shouldReceive('find')
        ->with($companyId)
        ->andReturn($companyMock) // Static find() should return this mock instance
        ->once();
    // hasTransactions, getSetting, etc., should not be called if currency key is absent
    $companyMock->shouldNotReceive('hasTransactions');

    // Mock authorization to pass, using the same overload mock instance
    $controller = Mockery::mock(UpdateCompanySettingsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods()->shouldReceive('authorize')
        ->with('manage company', $companyMock)
        ->andReturn(true);

    Mockery::mock('alias:Illuminate\Support\Arr')
        ->shouldReceive('exists')
        ->with($requestData, 'currency')
        ->andReturn(false) // Currency key does NOT exist
        ->once();

    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldNotReceive('getSetting'); // getSetting should not be called

    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('setSettings')
        ->with($requestData, $companyId)
        ->once();

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);
});

test('it throws an AuthorizationException if company not found for authorization', function () {
    // Arrange
    $companyId = 999; // Non-existent company ID
    $requestData = [
        'tax_name' => 'VAT',
    ];

    $mockRequest = Mockery::mock(UpdateSettingsRequest::class);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId);
    $mockRequest->settings = $requestData;

    // Use 'overload' to mock static methods like find()
    $companyMock = Mockery::mock('overload:' . Company::class); // No id needed for null return
    $companyMock->shouldReceive('find')
        ->with($companyId)
        ->andReturn(null) // Company not found
        ->once();

    // Mock authorization to throw an exception when company is null
    $mockController = Mockery::mock(UpdateCompanySettingsController::class)->makePartial();
    $mockController->shouldAllowMockingProtectedMethods()->shouldReceive('authorize')
        ->with('manage company', null) // Authorize is called with null company
        ->andThrow(AuthorizationException::class) // Simulate authorization failure
        ->once();

    // Ensure no other static methods are called after authorization failure
    Mockery::mock('alias:Illuminate\Support\Arr')->shouldNotReceive('exists');
    Mockery::mock('alias:Crater\Models\CompanySetting')->shouldNotReceive('getSetting');
    Mockery::mock('alias:Crater\Models\CompanySetting')->shouldNotReceive('setSettings');

    // Act & Assert
    $this->expectException(AuthorizationException::class);
    $mockController->__invoke($mockRequest);
});

test('it throws an AuthorizationException if user is not authorized to manage the company', function () {
    // Arrange
    $companyId = 1;
    $requestData = [
        'tax_name' => 'VAT',
    ];

    $mockRequest = Mockery::mock(UpdateSettingsRequest::class);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId);
    $mockRequest->settings = $requestData;

    // Use 'overload' to mock static methods like find() and also handle instance behavior
    $companyMock = Mockery::mock('overload:' . Company::class, ['id' => $companyId]);
    $companyMock->shouldReceive('find')
        ->with($companyId)
        ->andReturn($companyMock) // Static find() should return this mock instance
        ->once();

    // Ensure no instance methods on Company are called before authorization
    $companyMock->shouldNotReceive('hasTransactions');

    // Mock authorization to throw an exception
    $mockController = Mockery::mock(UpdateCompanySettingsController::class)->makePartial();
    $mockController->shouldAllowMockingProtectedMethods()->shouldReceive('authorize')
        ->with('manage company', $companyMock)
        ->andThrow(AuthorizationException::class) // Simulate authorization failure
        ->once();

    // Ensure no other static methods are called after authorization failure
    Mockery::mock('alias:Illuminate\Support\Arr')->shouldNotReceive('exists');
    Mockery::mock('alias:Crater\Models\CompanySetting')->shouldNotReceive('getSetting');
    Mockery::mock('alias:Crater\Models\CompanySetting')->shouldNotReceive('setSettings');

    // Act & Assert
    $this->expectException(AuthorizationException::class);
    $mockController->__invoke($mockRequest);
});


afterEach(function () {
    Mockery::close();
});
```