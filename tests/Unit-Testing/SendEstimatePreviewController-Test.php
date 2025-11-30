<?php

uses(\Mockery::class);
use Illuminate\Mail\Markdown;
use Crater\Models\Estimate;
use Crater\Http\Requests\SendEstimatesRequest;
use Crater\Http\Controllers\V1\Admin\Estimate\SendEstimatePreviewController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\Factory as ViewFactory;

beforeEach(function () {
    // Ensure mocks are cleaned up before each test
    Mockery::close();
});

test('it successfully previews an estimate with valid data', function () {
    // Arrange - Mocks for injected dependencies
    $mockRequest = Mockery::mock(SendEstimatesRequest::class);
    $mockEstimate = Mockery::mock(Estimate::class);

    // Data for mocks
    $requestData = ['email' => 'test@example.com', 'subject' => 'Estimate Preview'];
    $estimateData = ['id' => 1, 'customer' => 'Test Customer'];
    $pdfUrl = 'http://example.com/estimate/1/pdf';
    $renderedContent = '<html><body>Estimate Preview Content</body></html>';

    // Configure mock request
    $mockRequest->shouldReceive('all')
        ->once()
        ->andReturn($requestData);

    // Configure mock estimate
    $mockEstimate->shouldReceive('sendEstimateData')
        ->once()
        ->with($requestData)
        ->andReturn($estimateData);
    $mockEstimate->estimatePdfUrl = $pdfUrl; // Mock property access

    // Arrange - Create a partial mock of the controller to intercept the `authorize` method
    $controller = Mockery::mock(SendEstimatePreviewController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods(); // Necessary for mocking protected methods from parent
    $controller->shouldReceive('authorize')
        ->once()
        ->with('send estimate', $mockEstimate)
        ->andReturn(true); // Simulate successful authorization

    // Arrange - Mock Markdown instantiation using Mockery's 'overload'
    // This allows us to replace `new Markdown(...)` with our mock.
    // We provide expected types/values for `view()` and `config()` helper calls.
    $mockMarkdownConfig = ['theme' => 'default', 'paths' => []]; // Simulate config('mail.markdown')
    $mockMarkdown = Mockery::mock('overload:' . Markdown::class);
    $mockMarkdown->shouldReceive('__construct')
        ->once()
        ->withArgs(function ($viewFactory, $config) use ($mockMarkdownConfig) {
            // Assert that the viewFactory argument is an instance of ViewFactory
            // and the config argument matches our expected config.
            return $viewFactory instanceof ViewFactory && $config === $mockMarkdownConfig;
        })
        ->andReturnSelf(); // Allow chaining method calls

    // Configure mock Markdown's render method
    $expectedRenderData = array_merge($estimateData, ['url' => $pdfUrl]);
    $mockMarkdown->shouldReceive('render')
        ->once()
        ->with('emails.send.estimate', ['data' => $expectedRenderData])
        ->andReturn($renderedContent);

    // Act
    $result = $controller($mockRequest, $mockEstimate);

    // Assert
    expect($result)->toBeString();
    expect($result)->toEqual($renderedContent);
});

test('it handles authorization failure when previewing an estimate', function () {
    // Arrange - Mocks
    $mockRequest = Mockery::mock(SendEstimatesRequest::class);
    $mockEstimate = Mockery::mock(Estimate::class);

    // Arrange - Mock the controller's authorize method to throw an exception
    $controller = Mockery::mock(SendEstimatePreviewController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('send estimate', $mockEstimate)
        ->andThrow(new AuthorizationException('User not authorized to send estimate.'));

    // Ensure no other methods are called if authorization fails
    $mockRequest->shouldNotReceive('all');
    $mockEstimate->shouldNotReceive('sendEstimateData');
    $mockEstimate->shouldNotReceive('estimatePdfUrl');

    // Act & Assert
    expect(fn () => $controller($mockRequest, $mockEstimate))
        ->toThrow(AuthorizationException::class, 'User not authorized to send estimate.');
});

test('it handles empty estimate data returned from sendEstimateData', function () {
    // Arrange - Mocks
    $mockRequest = Mockery::mock(SendEstimatesRequest::class);
    $mockEstimate = Mockery::mock(Estimate::class);

    $requestData = ['email' => 'test@example.com'];
    $estimateData = []; // Empty data returned from model
    $pdfUrl = 'http://example.com/estimate/1/pdf';
    $renderedContent = '<html><body>Empty Estimate Content</body></html>';

    $mockRequest->shouldReceive('all')->once()->andReturn($requestData);
    $mockEstimate->shouldReceive('sendEstimateData')->once()->with($requestData)->andReturn($estimateData);
    $mockEstimate->estimatePdfUrl = $pdfUrl;

    $controller = Mockery::mock(SendEstimatePreviewController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('send estimate', $mockEstimate)->andReturn(true);

    $mockMarkdownConfig = ['theme' => 'default', 'paths' => []];
    $mockMarkdown = Mockery::mock('overload:' . Markdown::class);
    $mockMarkdown->shouldReceive('__construct')
        ->once()
        ->withArgs(function ($viewFactory, $config) use ($mockMarkdownConfig) {
            return $viewFactory instanceof ViewFactory && $config === $mockMarkdownConfig;
        })
        ->andReturnSelf();

    // Expect the render method to receive the URL even with empty estimate data
    $expectedRenderData = ['url' => $pdfUrl];
    $mockMarkdown->shouldReceive('render')
        ->once()
        ->with('emails.send.estimate', ['data' => $expectedRenderData])
        ->andReturn($renderedContent);

    // Act
    $result = $controller($mockRequest, $mockEstimate);

    // Assert
    expect($result)->toBeString();
    expect($result)->toEqual($renderedContent);
});

test('it handles a null estimatePdfUrl gracefully', function () {
    // Arrange - Mocks
    $mockRequest = Mockery::mock(SendEstimatesRequest::class);
    $mockEstimate = Mockery::mock(Estimate::class);

    $requestData = ['email' => 'test@example.com'];
    $estimateData = ['id' => 1];
    $pdfUrl = null; // null PDF URL
    $renderedContent = '<html><body>Estimate with no PDF Link</body></html>';

    $mockRequest->shouldReceive('all')->once()->andReturn($requestData);
    $mockEstimate->shouldReceive('sendEstimateData')->once()->with($requestData)->andReturn($estimateData);
    $mockEstimate->estimatePdfUrl = $pdfUrl;

    $controller = Mockery::mock(SendEstimatePreviewController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('send estimate', $mockEstimate)->andReturn(true);

    $mockMarkdownConfig = ['theme' => 'default', 'paths' => []];
    $mockMarkdown = Mockery::mock('overload:' . Markdown::class);
    $mockMarkdown->shouldReceive('__construct')
        ->once()
        ->withArgs(function ($viewFactory, $config) use ($mockMarkdownConfig) {
            return $viewFactory instanceof ViewFactory && $config === $mockMarkdownConfig;
        })
        ->andReturnSelf();

    // Expect the render method to receive the URL as null
    $expectedRenderData = array_merge($estimateData, ['url' => $pdfUrl]);
    $mockMarkdown->shouldReceive('render')
        ->once()
        ->with('emails.send.estimate', ['data' => $expectedRenderData])
        ->andReturn($renderedContent);

    // Act
    $result = $controller($mockRequest, $mockEstimate);

    // Assert
    expect($result)->toBeString();
    expect($result)->toEqual($renderedContent);
});

test('it correctly merges estimate data and pdf url before rendering', function () {
    // Arrange - Mocks
    $mockRequest = Mockery::mock(SendEstimatesRequest::class);
    $mockEstimate = Mockery::mock(Estimate::class);

    $requestData = ['extra_param' => 'value'];
    $estimateData = ['invoice_number' => 'EST-001', 'client_name' => 'Acme Corp'];
    $pdfUrl = 'http://example.com/some-generated-pdf.pdf';
    $renderedContent = 'Final rendered preview';

    $mockRequest->shouldReceive('all')->once()->andReturn($requestData);
    $mockEstimate->shouldReceive('sendEstimateData')->once()->with($requestData)->andReturn($estimateData);
    $mockEstimate->estimatePdfUrl = $pdfUrl;

    $controller = Mockery::mock(SendEstimatePreviewController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('send estimate', $mockEstimate)->andReturn(true);

    $mockMarkdownConfig = ['theme' => 'default', 'paths' => []];
    $mockMarkdown = Mockery::mock('overload:' . Markdown::class);
    $mockMarkdown->shouldReceive('__construct')
        ->once()
        ->withArgs(function ($viewFactory, $config) use ($mockMarkdownConfig) {
            return $viewFactory instanceof ViewFactory && $config === $mockMarkdownConfig;
        })
        ->andReturnSelf();

    // Crucial assertion: check the exact data structure passed to the Markdown renderer
    $expectedDataForRender = [
        'data' => [
            'invoice_number' => 'EST-001',
            'client_name' => 'Acme Corp',
            'url' => $pdfUrl
        ]
    ];
    $mockMarkdown->shouldReceive('render')
        ->once()
        ->with('emails.send.estimate', $expectedDataForRender)
        ->andReturn($renderedContent);

    // Act
    $result = $controller($mockRequest, $mockEstimate);

    // Assert
    expect($result)->toBeString();
    expect($result)->toEqual($renderedContent);
});
