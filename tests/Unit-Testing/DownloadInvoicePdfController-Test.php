<?php

use Crater\Models\Invoice;
use Crater\Http\Controllers\V1\PDF\DownloadInvoicePdfController;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

test('it downloads the invoice PDF with the correct path', function () {
    // Arrange
    $invoiceId = 123; // A sample invoice ID
    $invoice = Mockery::mock(Invoice::class);
    $invoice->id = $invoiceId;

    // Mock the `ResponseFactory` which the global `response()` helper uses.
    // We bind this mock into the Laravel service container for the duration of the test.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $this->app->instance(ResponseFactory::class, $mockResponseFactory);

    // Calculate the expected full path that the controller should generate.
    // `storage_path()` is allowed to run as it's a simple string operation,
    // and its contribution to the path is verified by the `with()` expectation below.
    $expectedPath = storage_path('app/temp/invoice/' . $invoiceId . '.pdf');

    // Expect the 'download' method to be called exactly once on the mock ResponseFactory.
    // It should be called with the calculated expected path.
    // The `andReturn` part stubs the return value to prevent actual file download operations
    // and provide a valid return type for the method.
    $mockResponseFactory->shouldReceive('download')
        ->once()
        ->with($expectedPath)
        ->andReturn(Mockery::mock(Response::class)); // Stub the return with a mock HTTP Response

    // Act
    $controller = new DownloadInvoicePdfController();
    $controller($invoice); // Invoke the controller's `__invoke` method

    // Assert
    // Mockery's expectations (e.g., `shouldReceive(...)->once()->with(...)`)
    // automatically act as assertions. If the `download` method is not called
    // exactly once with the specified path, Mockery will cause the test to fail.
    // No explicit `expect()` or `assertTrue()` is needed here.
});




afterEach(function () {
    Mockery::close();
});
