<?php

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Mockery as m;
use Crater\Http\Controllers\V1\Customer\Payment\PaymentMethodController;
use Crater\Http\Resources\Customer\PaymentMethodResource;
use Crater\Models\Company;
use Crater\Models\PaymentMethod;

/*
|--------------------------------------------------------------------------
| Test Case Setup
|--------------------------------------------------------------------------
|
| The afterEach hook is used to clean up Mockery expectations and mocks
| after each test, ensuring a clean state for subsequent tests.
|
*/
afterEach(function () {
    m::close();
});

/*
|--------------------------------------------------------------------------
| Test Cases for PaymentMethodController::__invoke
|--------------------------------------------------------------------------
|
| These tests cover the __invoke method, which is responsible for fetching
| payment methods associated with a given company and returning them as
| a resource collection.
|
*/

test('invoke returns an empty collection when no payment methods are found for the company', function () {
    // Arrange
    $companyId = 1;
    // Fix: Initialize the 'id' attribute directly during mock creation to avoid BadMethodCallException
    $company = m::mock(Company::class, ['id' => $companyId]);

    $request = m::mock(Request::class);

    // Mock the static calls on PaymentMethod to return an empty collection
    m::mock('alias:' . PaymentMethod::class)
        ->shouldReceive('where')
        ->once()
        ->with('company_id', $companyId)
        ->andReturnSelf() // Allow method chaining
        ->shouldReceive('get')
        ->once()
        ->andReturn(new Collection()); // Simulate no payment methods found

    // Act
    $controller = new PaymentMethodController();
    $response = $controller($request, $company);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class);
    expect($response->resolve())->toBeArray()->toBeEmpty();
});

test('invoke returns a collection with a single payment method for the company', function () {
    // Arrange
    $companyId = 2;
    // Fix: Initialize the 'id' attribute directly during mock creation to avoid BadMethodCallException
    $company = m::mock(Company::class, ['id' => $companyId]);

    $request = m::mock(Request::class);

    // Create a mock payment method model
    // Fix: Initialize all attributes directly during mock creation to avoid BadMethodCallException
    $mockPaymentMethod = m::mock(PaymentMethod::class, [
        'id' => 10,
        'name' => 'Visa Card',
        'card_brand' => 'visa',
        'card_last_four' => '1234',
        'card_expiry_month' => '12',
        'card_expiry_year' => '2025',
    ]);

    $paymentMethodsCollection = new Collection([$mockPaymentMethod]);

    // Mock the static calls on PaymentMethod to return a collection with one item
    m::mock('alias:' . PaymentMethod::class)
        ->shouldReceive('where')
        ->once()
        ->with('company_id', $companyId)
        ->andReturnSelf()
        ->shouldReceive('get')
        ->once()
        ->andReturn($paymentMethodsCollection);

    // Act
    $controller = new PaymentMethodController();
    $response = $controller($request, $company);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class);
    $resolvedData = $response->resolve();
    expect($resolvedData)->toBeArray()->toHaveCount(1);
    expect($resolvedData[0]['id'])->toEqual(10);
    // Further assertions on specific data if the resource transformation is critical to this test
    expect($resolvedData[0]['name'])->toEqual('Visa Card');
});

test('invoke returns a collection with multiple payment methods for the company', function () {
    // Arrange
    $companyId = 3;
    // Fix: Initialize the 'id' attribute directly during mock creation to avoid BadMethodCallException
    $company = m::mock(Company::class, ['id' => $companyId]);

    $request = m::mock(Request::class);

    // Create multiple mock payment method models
    // Fix: Initialize all attributes directly during mock creation to avoid BadMethodCallException
    $mockPaymentMethod1 = m::mock(PaymentMethod::class, [
        'id' => 11,
        'name' => 'Mastercard',
        'card_brand' => 'mastercard',
        'card_last_four' => '5678',
        'card_expiry_month' => '10',
        'card_expiry_year' => '2024',
    ]);

    // Fix: Initialize all attributes directly during mock creation to avoid BadMethodCallException
    $mockPaymentMethod2 = m::mock(PaymentMethod::class, [
        'id' => 12,
        'name' => 'American Express',
        'card_brand' => 'amex',
        'card_last_four' => '9012',
        'card_expiry_month' => '01',
        'card_expiry_year' => '2026',
    ]);

    $paymentMethodsCollection = new Collection([$mockPaymentMethod1, $mockPaymentMethod2]);

    // Mock the static calls on PaymentMethod to return a collection with multiple items
    m::mock('alias:' . PaymentMethod::class)
        ->shouldReceive('where')
        ->once()
        ->with('company_id', $companyId)
        ->andReturnSelf()
        ->shouldReceive('get')
        ->once()
        ->andReturn($paymentMethodsCollection);

    // Act
    $controller = new PaymentMethodController();
    $response = $controller($request, $company);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class);
    $resolvedData = $response->resolve();
    expect($resolvedData)->toBeArray()->toHaveCount(2);
    expect($resolvedData[0]['id'])->toEqual(11);
    expect($resolvedData[1]['id'])->toEqual(12);
});

test('invoke calls PaymentMethod::where with the correct company ID', function () {
    // Arrange
    $companyId = 4;
    // Fix: Initialize the 'id' attribute directly during mock creation to avoid BadMethodCallException
    $company = m::mock(Company::class, ['id' => $companyId]);

    $request = m::mock(Request::class);

    // Expect 'where' to be called exactly once with 'company_id' and the specific $companyId
    m::mock('alias:' . PaymentMethod::class)
        ->shouldReceive('where')
        ->once()
        ->with('company_id', $companyId) // This is the crucial assertion for this test
        ->andReturnSelf()
        ->shouldReceive('get')
        ->once()
        ->andReturn(new Collection()); // Return an empty collection to complete the chain

    // Act
    $controller = new PaymentMethodController();
    $controller($request, $company);

    // Assertions are implicitly handled by Mockery's `shouldReceive->once()->with()` expectations
});