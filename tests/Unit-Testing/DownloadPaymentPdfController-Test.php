<?php

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Crater\Models\Payment;
use Crater\Http\Controllers\V1\PDF\DownloadPaymentPdfController;
uses(\Mockery::class);

// Set up a group for PDF related tests and ensure Mockery is closed after each test
uses()->group('pdf')->afterEach(function () {
    Mockery::close();
});

test('it successfully downloads a payment PDF with the correct path for a given payment ID', function () {
    // Arrange
    $paymentId = 123;
    $expectedPath = storage_path("app/temp/payment/{$paymentId}.pdf");

    // Create a mock Payment object
    $mockPayment = Mockery::mock(Payment::class);
    // The controller directly accesses the 'id' property, so we set it on the mock.
    $mockPayment->id = $paymentId;

    // Mock the ResponseFactory to control the behavior of the global `response()` helper.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);

    // Set an expectation: the `download` method should be called exactly once
    // with the precisely constructed expected path.
    $mockResponseFactory->shouldReceive('download')
                        ->once()
                        ->with($expectedPath)
                        ->andReturn(Mockery::mock(Response::class)); // Return a mock Response object

    // Bind the mock ResponseFactory into the service container.
    // This ensures that when the `response()` helper is called within the controller,
    // it resolves to our mock instead of the real implementation.
    $this->app->instance(ResponseFactory::class, $mockResponseFactory);

    // Instantiate the controller under test.
    $controller = new DownloadPaymentPdfController();

    // Act
    // Invoke the controller's __invoke method with our mock payment.
    $response = $controller->__invoke($mockPayment);

    // Assert
    // Verify that the returned value is an instance of Illuminate\Http\Response.
    // Mockery handles the assertion that `download` was called with the correct arguments based on the `shouldReceive` expectation.
    expect($response)->toBeInstanceOf(Response::class);
});

test('it generates the correct download path for a different payment ID', function () {
    // Arrange
    $paymentId = 98765; // A different, larger payment ID
    $expectedPath = storage_path("app/temp/payment/{$paymentId}.pdf");

    $mockPayment = Mockery::mock(Payment::class);
    $mockPayment->id = $paymentId;

    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('download')
                        ->once()
                        ->with($expectedPath)
                        ->andReturn(Mockery::mock(Response::class));

    $this->app->instance(ResponseFactory::class, $mockResponseFactory);

    $controller = new DownloadPaymentPdfController();

    // Act
    $response = $controller->__invoke($mockPayment);

    // Assert
    expect($response)->toBeInstanceOf(Response::class);
});

test('it correctly constructs the path for a payment ID of 1 (edge case for smallest positive ID)', function () {
    // Arrange
    $paymentId = 1; // Smallest typical positive ID
    $expectedPath = storage_path("app/temp/payment/{$paymentId}.pdf");

    $mockPayment = Mockery::mock(Payment::class);
    $mockPayment->id = $paymentId;

    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('download')
                        ->once()
                        ->with($expectedPath)
                        ->andReturn(Mockery::mock(Response::class));

    $this->app->instance(ResponseFactory::class, $mockResponseFactory);

    $controller = new DownloadPaymentPdfController();

    // Act
    $response = $controller->__invoke($mockPayment);

    // Assert
    expect($response)->toBeInstanceOf(Response::class);
});

// Note: This unit test focuses solely on the controller's logic:
// 1. Correctly accessing the Payment ID.
// 2. Correctly constructing the file path.
// 3. Correctly calling the `response()->download()` helper with that path.
// It does NOT test:
// - If the file actually exists at `storage_path('app/temp/payment/')`.
// - File system permissions or errors related to actual file downloading.
// These concerns would typically be covered by feature/integration tests or higher-level system tests.
// The method itself contains no conditional logic or branches to test.
