<?php

uses(\Mockery::class);
use Illuminate\Http\JsonResponse;
use Crater\Http\Controllers\V1\Admin\Invoice\SendInvoiceController;
use Crater\Http\Requests\SendInvoiceRequest;
use Crater\Models\Invoice;
use Illuminate\Auth\Access\AuthorizationException;

// Test for successful invoice sending, ensuring all steps complete and a success response is returned.
test('it successfully sends an invoice and returns a success response', function () {
    // Mock the dependencies: SendInvoiceRequest and Invoice model.
    $request = Mockery::mock(SendInvoiceRequest::class);
    $invoice = Mockery::mock(Invoice::class);

    // Define the data that the request's `all()` method should return.
    $requestData = [
        'email' => 'customer@example.com',
        'subject' => 'Your Invoice',
        'body' => 'Please find your attached invoice.',
    ];
    $request->shouldReceive('all')->once()->andReturn($requestData);

    // Expect the `send()` method on the invoice model to be called exactly once with the request data.
    $invoice->shouldReceive('send')->once()->with($requestData)->andReturnNull(); // Assuming `send` returns void.

    // Create a spy for the controller to assert that its internal `authorize` method is called.
    $controller = Mockery::spy(SendInvoiceController::class);

    // Expect the `authorize` method to be called exactly once with the correct policy action and model.
    $controller->shouldReceive('authorize')->once()->with('send invoice', $invoice)->andReturnNull();

    // Invoke the controller's `__invoke` method.
    $response = $controller($request, $invoice);

    // Assertions on the response.
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);

    // Verify that all expected methods on mocks and spies were called.
    $request->shouldHaveReceived('all');
    $invoice->shouldHaveReceived('send');
    $controller->shouldHaveReceived('authorize');
});

// Test to specifically verify that the `authorize` method is called with the correct arguments.
test('it calls authorize with the correct policy action and invoice model', function () {
    $request = Mockery::mock(SendInvoiceRequest::class);
    $invoice = Mockery::mock(Invoice::class);

    // Stub minimal behavior for other methods to allow the test to proceed to `authorize`.
    $request->shouldReceive('all')->andReturn([]);
    $invoice->shouldReceive('send')->andReturnNull();

    $controller = Mockery::spy(SendInvoiceController::class);

    // Expect `authorize` to be called once with 'send invoice' and the invoice instance.
    $controller->shouldReceive('authorize')->once()->with('send invoice', $invoice)->andReturnNull();

    $controller($request, $invoice);

    // Verify the `authorize` method was called with the exact arguments.
    $controller->shouldHaveReceived('authorize', ['send invoice', $invoice]);
});

// Test an edge case where the `SendInvoiceRequest` returns empty data.
test('it handles sending an invoice with empty request data', function () {
    $request = Mockery::mock(SendInvoiceRequest::class);
    $invoice = Mockery::mock(Invoice::class);

    $emptyRequestData = [];
    $request->shouldReceive('all')->once()->andReturn($emptyRequestData);

    // `invoice->send()` should still be called, even with empty data, as per current logic.
    $invoice->shouldReceive('send')->once()->with($emptyRequestData)->andReturnNull();

    $controller = Mockery::spy(SendInvoiceController::class);
    $controller->shouldReceive('authorize')->once()->with('send invoice', $invoice)->andReturnNull();

    $response = $controller($request, $invoice);

    // Assertions remain the same for a successful operation.
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);

    // Verify all interactions.
    $request->shouldHaveReceived('all');
    $invoice->shouldHaveReceived('send');
    $controller->shouldHaveReceived('authorize');
});

// Test for error propagation: if `invoice->send()` throws an exception, the controller should not catch it.
test('it propagates exceptions thrown during the invoice send process', function () {
    $request = Mockery::mock(SendInvoiceRequest::class);
    $invoice = Mockery::mock(Invoice::class);

    $requestData = ['email' => 'error@example.com'];
    $request->shouldReceive('all')->andReturn($requestData);

    $exceptionMessage = 'Failed to connect to email service.';
    // Configure `invoice->send()` to throw an exception.
    $invoice->shouldReceive('send')->once()->with($requestData)->andThrow(new Exception($exceptionMessage));

    $controller = Mockery::spy(SendInvoiceController::class);
    $controller->shouldReceive('authorize')->once()->with('send invoice', $invoice)->andReturnNull();

    // Expect the `__invoke` method call to throw the same exception.
    expect(fn () => $controller($request, $invoice))
        ->toThrow(Exception::class, $exceptionMessage);

    // Ensure `authorize` was called before the exception was thrown from `send()`.
    $controller->shouldHaveReceived('authorize');
    $request->shouldHaveReceived('all');
    $invoice->shouldHaveReceived('send');
});

// Test for authorization failure, ensuring that `invoice->send()` is not called.
test('it prevents invoice from being sent if authorization fails', function () {
    $request = Mockery::mock(SendInvoiceRequest::class);
    $invoice = Mockery::mock(Invoice::class);

    // Create an AuthorizationException instance to be thrown by `authorize`.
    $authorizationException = new AuthorizationException('This action is unauthorized.');

    $controller = Mockery::spy(SendInvoiceController::class);
    // Configure `authorize` to throw an AuthorizationException.
    $controller->shouldReceive('authorize')->once()->with('send invoice', $invoice)->andThrow($authorizationException);

    // Crucially, expect `invoice->send()` NOT to be called, as `authorize` happens first.
    $invoice->shouldNotReceive('send');
    // Similarly, `request->all()` should not be called if authorization fails before it's needed.
    $request->shouldNotReceive('all');

    // Expect the controller's `__invoke` method to re-throw the AuthorizationException.
    expect(fn () => $controller($request, $invoice))
        ->toThrow(AuthorizationException::class, 'This action is unauthorized.');

    // Verify that `authorize` was indeed called.
    $controller->shouldHaveReceived('authorize');
    // And confirm that `invoice->send()` was NOT called.
    $invoice->shouldNotHaveReceived('send');
});
