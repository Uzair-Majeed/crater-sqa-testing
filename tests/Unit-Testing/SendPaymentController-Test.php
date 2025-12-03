<?php

use Crater\Http\Controllers\V1\Admin\Payment\SendPaymentController;
use Crater\Http\Requests\SendPaymentRequest;
use Crater\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->requestMock = Mockery::mock(SendPaymentRequest::class);
    $this->paymentMock = Mockery::mock(Payment::class);
});


test('it successfully sends a payment and returns a json response', function () {
    $requestData = ['amount' => 100.00, 'currency' => 'USD', 'notes' => 'Test Payment'];
    $paymentResponseData = ['success' => true, 'transaction_id' => 'tx_abc123', 'amount' => 100.00];

    $this->requestMock->shouldReceive('all')
                      ->once()
                      ->andReturn($requestData);

    $this->paymentMock->shouldReceive('send')
                      ->once()
                      ->with($requestData)
                      ->andReturn($paymentResponseData);

    // Create a partial mock of the controller to mock the authorize method
    $controller = Mockery::mock(SendPaymentController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods(); // Enable mocking protected methods
    $controller->shouldReceive('authorize')
               ->once()
               ->with('send payment', $this->paymentMock)
               ->andReturn(true); // Simulate successful authorization

    $response = $controller->__invoke($this->requestMock, $this->paymentMock);

    expect($response)->toBeInstanceOf(JsonResponse::class)
                     ->and($response->getData(true))->toEqual($paymentResponseData)
                     ->and($response->getStatusCode())->toBe(200);
});

test('it calls authorize with the correct arguments', function () {
    $requestData = ['amount' => 50.00];
    $paymentResponseData = ['success' => true];

    $this->requestMock->shouldReceive('all')
                      ->once()
                      ->andReturn($requestData);

    $this->paymentMock->shouldReceive('send')
                      ->once()
                      ->andReturn($paymentResponseData); // This will be called after authorize

    $controller = Mockery::mock(SendPaymentController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('send payment', $this->paymentMock) // Explicitly verify arguments
               ->andReturn(true); // Simulate successful authorization

    $controller->__invoke($this->requestMock, $this->paymentMock);

    // The shouldReceive->once() and with() assertions for 'authorize' confirm it was called correctly.
});

test('it throws AuthorizationException if authorization fails', function () {
    $requestData = ['amount' => 150.00];

    $this->requestMock->shouldReceive('all')
                      ->andReturn($requestData); // The controller might access it before/after, but send() definitely won't be called.

    // Expect send() not to be called if authorization fails
    $this->paymentMock->shouldNotReceive('send');

    $controller = Mockery::mock(SendPaymentController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('send payment', $this->paymentMock)
               ->andThrow(new AuthorizationException('Unauthorized to send payment'));

    $this->expectException(AuthorizationException::class);
    $this->expectExceptionMessage('Unauthorized to send payment');

    $controller->__invoke($this->requestMock, $this->paymentMock);
});

test('it handles null response from payment send method', function () {
    $requestData = ['amount' => 200.00];
    $paymentResponseData = null; // Simulate send method returning null

    $this->requestMock->shouldReceive('all')
                      ->once()
                      ->andReturn($requestData);

    $this->paymentMock->shouldReceive('send')
                      ->once()
                      ->with($requestData)
                      ->andReturn($paymentResponseData);

    $controller = Mockery::mock(SendPaymentController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')
               ->once()
               ->andReturn(true); // Simulate successful authorization

    $response = $controller->__invoke($this->requestMock, $this->paymentMock);

    expect($response)->toBeInstanceOf(JsonResponse::class)
                     ->and($response->getData(true))->toBeNull() // Expect null data
                     ->and($response->getStatusCode())->toBe(200);
});

test('it handles empty array response from payment send method', function () {
    $requestData = ['amount' => 500.00];
    $paymentResponseData = []; // Simulate send method returning an empty array

    $this->requestMock->shouldReceive('all')
                      ->once()
                      ->andReturn($requestData);

    $this->paymentMock->shouldReceive('send')
                      ->once()
                      ->with($requestData)
                      ->andReturn($paymentResponseData);

    $controller = Mockery::mock(SendPaymentController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')
               ->once()
               ->andReturn(true); // Simulate successful authorization

    $response = $controller->__invoke($this->requestMock, $this->paymentMock);

    expect($response)->toBeInstanceOf(JsonResponse::class)
                     ->and($response->getData(true))->toEqual([]) // Expect empty array
                     ->and($response->getStatusCode())->toBe(200);
});

test('it passes all request data to payment send method', function () {
    $requestData = [
        'amount' => 123.45,
        'currency' => 'EUR',
        'gateway' => 'stripe',
        'description' => 'Product purchase',
        'metadata' => ['order_id' => 789],
    ];
    $paymentResponseData = ['status' => 'completed'];

    $this->requestMock->shouldReceive('all')
                      ->once()
                      ->andReturn($requestData);

    $this->paymentMock->shouldReceive('send')
                      ->once()
                      ->with($requestData) // Ensure all data is passed
                      ->andReturn($paymentResponseData);

    $controller = Mockery::mock(SendPaymentController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')
               ->once()
               ->andReturn(true);

    $response = $controller->__invoke($this->requestMock, $this->paymentMock);

    expect($response->getData(true))->toEqual($paymentResponseData);
});




afterEach(function () {
    Mockery::close();
});
