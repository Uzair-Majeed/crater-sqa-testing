```php
<?php

use Crater\Models\Invoice;
use Crater\Http\Controllers\V1\PDF\DownloadInvoicePdfController;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

test('it downloads the invoice PDF with the correct path', function () {
    // Arrange
    $invoiceId = 123; // A sample invoice ID

    // Instead of mocking the whole Invoice model (which triggers Eloquent internals),
    // use an actual Invoice instance, not saved to DB (to avoid DB dependence).
    $invoice = new Invoice();
    $invoice->id = $invoiceId;

    // Mock the `ResponseFactory` which the global `response()` helper uses.
    // We bind this mock into the Laravel service container for the duration of the test.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $this->app->instance(ResponseFactory::class, $mockResponseFactory);

    // The expected path to the invoice PDF.
    $expectedPath = storage_path('app/temp/invoice/' . $invoiceId . '.pdf');

    // Expect the 'download' method to be called exactly once.
    $mockResponseFactory->shouldReceive('download')
        ->once()
        ->with($expectedPath)
        ->andReturn(Mockery::mock(Response::class));

    // Act
    $controller = new DownloadInvoicePdfController();
    $controller($invoice);

    // Assert - Mockery will check expectations.
});

afterEach(function () {
    Mockery::close();
});
```