<?php


beforeEach(function () {
    // Ensure Mockery expectations are cleared between tests
    Mockery::close();
});

test('it successfully generates and returns the invoice preview markdown with valid data', function () {
    // Arrange
    $requestData = ['email' => 'customer@example.com', 'subject' => 'Your Invoice'];
    $mockRequest = mock(\Crater\Http\Requests\SendInvoiceRequest::class);
    $mockRequest->shouldReceive('all')->once()->andReturn($requestData);

    $invoicePdfUrl = 'https://example.com/invoices/123-abc.pdf';
    $sendInvoiceData = [
        'customer_name' => 'Acme Corp',
        'invoice_number' => 'INV-001',
        'total_amount' => 123.45,
    ];
    $mockInvoice = mock(\Crater\Models\Invoice::class);
    $mockInvoice->shouldReceive('sendInvoiceData')->once()->with($requestData)->andReturn($sendInvoiceData);
    $mockInvoice->shouldReceive('getAttribute')->once()->with('invoicePdfUrl')->andReturn($invoicePdfUrl);

    // Mock the ViewFactory that the global view() helper returns
    $mockViewFactory = mock(\Illuminate\Contracts\View\Factory::class);
    app()->instance('view', $mockViewFactory);

    // Mock the Markdown class using Mockery's overload to intercept its instantiation
    $expectedRenderResult = '<h1>Invoice Preview HTML</h1><p>Customer: Acme Corp</p>';
    $mockMarkdown = Mockery::mock('overload:' . \Illuminate\Mail\Markdown::class);
    $mockMarkdown->shouldReceive('__construct')
        ->once()
        ->with($mockViewFactory, Mockery::type('array')); // Expects the mocked view factory and config array
    $mockMarkdown->shouldReceive('render')
        ->once()
        ->with('emails.send.invoice', ['data' => array_merge($sendInvoiceData, ['url' => $invoicePdfUrl])])
        ->andReturn($expectedRenderResult);

    // Set a dummy config value for 'mail.markdown'
    config(['mail.markdown' => ['theme' => 'default', 'paths' => [resource_path('views/vendor/mail')]]]);

    // Create a partial mock of the controller to mock its inherited 'authorize' method
    $controller = Mockery::mock(\Crater\Http\Controllers\V1\Admin\Invoice\SendInvoicePreviewController::class)->makePartial();
    $controller->shouldReceive('authorize')->once()->with('send invoice', $mockInvoice)->andReturn(true);

    // Act
    $result = $controller->__invoke($mockRequest, $mockInvoice);

    // Assert
    expect($result)->toBe($expectedRenderResult);
});

test('it handles empty data from sendInvoiceData method gracefully', function () {
    // Arrange
    $requestData = []; // Empty request data
    $mockRequest = mock(\Crater\Http\Requests\SendInvoiceRequest::class);
    $mockRequest->shouldReceive('all')->once()->andReturn($requestData);

    $invoicePdfUrl = 'https://example.com/invoices/empty.pdf';
    $emptySendInvoiceData = []; // Model returns empty data
    $mockInvoice = mock(\Crater\Models\Invoice::class);
    $mockInvoice->shouldReceive('sendInvoiceData')->once()->with($requestData)->andReturn($emptySendInvoiceData);
    $mockInvoice->shouldReceive('getAttribute')->once()->with('invoicePdfUrl')->andReturn($invoicePdfUrl);

    $mockViewFactory = mock(\Illuminate\Contracts\View\Factory::class);
    app()->instance('view', $mockViewFactory);

    $expectedRenderResult = '<h1>Empty Invoice Preview</h1>';
    $mockMarkdown = Mockery::mock('overload:' . \Illuminate\Mail\Markdown::class);
    $mockMarkdown->shouldReceive('__construct')
        ->once()
        ->with($mockViewFactory, Mockery::type('array'));
    $mockMarkdown->shouldReceive('render')
        ->once()
        ->with('emails.send.invoice', ['data' => array_merge($emptySendInvoiceData, ['url' => $invoicePdfUrl])])
        ->andReturn($expectedRenderResult);

    config(['mail.markdown' => []]); // Empty config for markdown

    $controller = Mockery::mock(\Crater\Http\Controllers\V1\Admin\Invoice\SendInvoicePreviewController::class)->makePartial();
    $controller->shouldReceive('authorize')->once()->with('send invoice', $mockInvoice)->andReturn(true);

    // Act
    $result = $controller->__invoke($mockRequest, $mockInvoice);

    // Assert
    expect($result)->toBe($expectedRenderResult);
});

test('it handles cases where invoicePdfUrl might be null', function () {
    // Arrange
    $requestData = ['note' => 'Special instructions'];
    $mockRequest = mock(\Crater\Http\Requests\SendInvoiceRequest::class);
    $mockRequest->shouldReceive('all')->once()->andReturn($requestData);

    $invoicePdfUrl = null; // simulate null URL
    $sendInvoiceData = ['invoice_total' => 500];
    $mockInvoice = mock(\Crater\Models\Invoice::class);
    $mockInvoice->shouldReceive('sendInvoiceData')->once()->with($requestData)->andReturn($sendInvoiceData);
    $mockInvoice->shouldReceive('getAttribute')->once()->with('invoicePdfUrl')->andReturn($invoicePdfUrl);

    $mockViewFactory = mock(\Illuminate\Contracts\View\Factory::class);
    app()->instance('view', $mockViewFactory);

    $expectedRenderResult = '<h1>Invoice Preview (No PDF Link)</h1>';
    $mockMarkdown = Mockery::mock('overload:' . \Illuminate\Mail\Markdown::class);
    $mockMarkdown->shouldReceive('__construct')
        ->once()
        ->with($mockViewFactory, Mockery::type('array'));
    $mockMarkdown->shouldReceive('render')
        ->once()
        ->with('emails.send.invoice', ['data' => array_merge($sendInvoiceData, ['url' => $invoicePdfUrl])])
        ->andReturn($expectedRenderResult);

    config(['mail.markdown' => ['theme' => 'compact']]);

    $controller = Mockery::mock(\Crater\Http\Controllers\V1\Admin\Invoice\SendInvoicePreviewController::class)->makePartial();
    $controller->shouldReceive('authorize')->once()->with('send invoice', $mockInvoice)->andReturn(true);

    // Act
    $result = $controller->__invoke($mockRequest, $mockInvoice);

    // Assert
    expect($result)->toBe($expectedRenderResult);
});

test('it ensures authorize method is called with correct arguments', function () {
    // Arrange
    $mockRequest = mock(\Crater\Http\Requests\SendInvoiceRequest::class);
    $mockRequest->shouldReceive('all')->andReturn([]); // Minimal expectation for this test

    $mockInvoice = mock(\Crater\Models\Invoice::class);
    $mockInvoice->shouldReceive('sendInvoiceData')->andReturn([]); // Minimal expectation
    $mockInvoice->shouldReceive('getAttribute')->andReturn(null); // Minimal expectation

    // Mock Markdown and ViewFactory to allow the method to complete
    $mockViewFactory = mock(\Illuminate\Contracts\View\Factory::class);
    app()->instance('view', $mockViewFactory);
    $mockMarkdown = Mockery::mock('overload:' . \Illuminate\Mail\Markdown::class);
    $mockMarkdown->shouldReceive('__construct');
    $mockMarkdown->shouldReceive('render')->andReturn('');
    config(['mail.markdown' => []]);

    $controller = Mockery::mock(\Crater\Http\Controllers\V1\Admin\Invoice\SendInvoicePreviewController::class)->makePartial();
    // Expect the authorize method to be called exactly once with the specific arguments
    $controller->shouldReceive('authorize')->once()->with('send invoice', $mockInvoice)->andReturn(true);

    // Act
    $controller->__invoke($mockRequest, $mockInvoice);

    // Assert: Mockery will automatically assert if the expected call was made during Mockery::close().
    // No explicit expect() call is needed here, as the test would fail if authorize wasn't called as expected.
});

test('it handles empty mail markdown configuration gracefully', function () {
    // Arrange
    $requestData = ['email' => 'user@example.com'];
    $mockRequest = mock(\Crater\Http\Requests\SendInvoiceRequest::class);
    $mockRequest->shouldReceive('all')->once()->andReturn($requestData);

    $invoicePdfUrl = 'https://example.com/invoice_path.pdf';
    $sendInvoiceData = ['id' => 1, 'amount' => 100];
    $mockInvoice = mock(\Crater\Models\Invoice::class);
    $mockInvoice->shouldReceive('sendInvoiceData')->once()->with($requestData)->andReturn($sendInvoiceData);
    $mockInvoice->shouldReceive('getAttribute')->once()->with('invoicePdfUrl')->andReturn($invoicePdfUrl);

    $mockViewFactory = mock(\Illuminate\Contracts\View\Factory::class);
    app()->instance('view', $mockViewFactory);

    $expectedRenderResult = '<p>Invoice sent successfully without markdown config.</p>';
    $mockMarkdown = Mockery::mock('overload:' . \Illuminate\Mail\Markdown::class);
    $mockMarkdown->shouldReceive('__construct')
        ->once()
        ->with($mockViewFactory, []); // Expects an empty array here if config is empty
    $mockMarkdown->shouldReceive('render')
        ->once()
        ->with('emails.send.invoice', ['data' => array_merge($sendInvoiceData, ['url' => $invoicePdfUrl])])
        ->andReturn($expectedRenderResult);

    // Set config to an empty array
    config(['mail.markdown' => []]);

    $controller = Mockery::mock(\Crater\Http\Controllers\V1\Admin\Invoice\SendInvoicePreviewController::class)->makePartial();
    $controller->shouldReceive('authorize')->once()->with('send invoice', $mockInvoice)->andReturn(true);

    // Act
    $result = $controller->__invoke($mockRequest, $mockInvoice);

    // Assert
    expect($result)->toBe($expectedRenderResult);
});




afterEach(function () {
    Mockery::close();
});
