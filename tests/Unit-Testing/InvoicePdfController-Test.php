<?php

use Crater\Http\Controllers\V1\PDF\InvoicePdfController;
use Crater\Models\Invoice;
use Illuminate\Http\Request;
use Mockery as m;

beforeEach(function () {
    // Ensure Mockery is closed after each test to prevent test pollution
    m::close();
});

test('it returns PDF data when the request has a preview parameter', function () {
    // Arrange
    $request = m::mock(Request::class);
    $invoice = m::mock(Invoice::class);
    $controller = new InvoicePdfController();

    $expectedPdfData = 'mocked_pdf_data_content';

    // Expectations:
    // The request will be checked for the 'preview' parameter.
    $request->shouldReceive('has')
            ->with('preview')
            ->once()
            ->andReturn(true);

    // The invoice model's getPDFData method should be called.
    $invoice->shouldReceive('getPDFData')
            ->once()
            ->andReturn($expectedPdfData);

    // Act
    // Invoke the controller with the mocked request and invoice.
    $result = $controller($request, $invoice);

    // Assert
    // The result should be the data returned by getPDFData.
    expect($result)->toBe($expectedPdfData);
});

test('it returns generated PDF stream when the request does not have a preview parameter', function () {
    // Arrange
    $request = m::mock(Request::class);
    $invoice = m::mock(Invoice::class);
    $controller = new InvoicePdfController();

    $expectedPdfStream = 'mocked_pdf_stream_content';

    // Expectations:
    // The request will be checked for the 'preview' parameter.
    $request->shouldReceive('has')
            ->with('preview')
            ->once()
            ->andReturn(false); // Simulate 'preview' parameter is not present.

    // The invoice model's getGeneratedPDFOrStream method should be called with 'invoice'.
    $invoice->shouldReceive('getGeneratedPDFOrStream')
            ->with('invoice')
            ->once()
            ->andReturn($expectedPdfStream);

    // Act
    // Invoke the controller with the mocked request and invoice.
    $result = $controller($request, $invoice);

    // Assert
    // The result should be the stream returned by getGeneratedPDFOrStream.
    expect($result)->toBe($expectedPdfStream);
});




afterEach(function () {
    Mockery::close();
});
