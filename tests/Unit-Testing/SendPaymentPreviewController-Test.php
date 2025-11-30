<?php

uses(\Mockery::class);
use Crater\Http\Controllers\V1\Admin\Payment\SendPaymentPreviewController;
use Crater\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Auth\Access\AuthorizationException;

// Assume standard Laravel TestCase setup for Pest, so $this->app is available for container binding.
beforeEach(function () {
        // Ensure Mockery is clean before each test, especially important for 'overload' mocks.
        Mockery::close();
    });

    test('it authorizes, processes payment data, and renders the payment preview markdown successfully', function () {
        // Mock external dependencies
        $mockRequest = Mockery::mock(Request::class);
        $mockPayment = Mockery::mock(Payment::class);
        $mockViewFactory = Mockery::mock(ViewFactory::class); // For view() helper

        // Define expected data for mocks
        $requestData = ['items' => ['product_a'], 'total' => 150.00];
        $processedPaymentData = ['items' => ['product_a_processed'], 'total' => 150.00, 'currency' => 'USD'];
        $paymentPdfUrl = 'http://example.com/payment/pdf/invoice-123';
        $expectedRenderedMarkdown = '<p>Payment preview for invoice-123</p>';
        $markdownConfig = ['theme' => 'default', 'paths' => [resource_path('views/vendor/mail')]];

        // Set expectations for Request and Payment mocks
        $mockRequest->shouldReceive('all')
                    ->once()
                    ->andReturn($requestData);

        $mockPayment->shouldReceive('sendPaymentData')
                    ->once()
                    ->with($requestData)
                    ->andReturn($processedPaymentData);

        $mockPayment->shouldReceive('getAttribute') // For $payment->paymentPdfUrl property access
                    ->with('paymentPdfUrl')
                    ->once()
                    ->andReturn($paymentPdfUrl);

        // Mock global helpers/facades for Markdown constructor
        Config::shouldReceive('get')
              ->with('mail.markdown')
              ->once()
              ->andReturn($markdownConfig);

        // Bind the mock ViewFactory into the container for the view() helper
        $this->app->instance('view', $mockViewFactory);

        // Overload the Markdown class so that `new Markdown(...)` returns our mock instance
        // We need to define expectations for its constructor and `render` method here.
        $mockMarkdownInstance = Mockery::mock(Markdown::class); // Create the actual mock instance to be returned
        Mockery::mock('overload:' . Markdown::class)
            ->shouldReceive('__construct')
            ->once()
            ->withArgs(function ($viewFactoryArg, $markdownConfigArg) use ($mockViewFactory, $markdownConfig) {
                // Ensure the constructor receives the mocked view factory and the expected config
                return $viewFactoryArg === $mockViewFactory && $markdownConfigArg === $markdownConfig;
            })
            ->andReturnUsing(function () use ($mockMarkdownInstance) {
                // When `new Markdown()` is called, return our specific mock instance
                return $mockMarkdownInstance;
            });

        // Expectations for the mocked Markdown instance's `render` method
        $mockMarkdownInstance->shouldReceive('render')
                     ->once()
                     ->with('emails.send.payment', ['data' => array_merge($processedPaymentData, ['url' => $paymentPdfUrl])])
                     ->andReturn($expectedRenderedMarkdown);

        // Create a partial mock of the controller to mock its inherited `authorize` method
        $controller = Mockery::mock(SendPaymentPreviewController::class)->makePartial();
        $controller->shouldReceive('authorize')
                   ->once()
                   ->with('send payment', $mockPayment)
                   ->andReturn(true); // Simulate successful authorization

        // Act
        $response = $controller->__invoke($mockRequest, $mockPayment);

        // Assert
        expect($response)->toBe($expectedRenderedMarkdown);
    });

    test('it handles empty request data passed to sendPaymentData', function () {
        $mockRequest = Mockery::mock(Request::class);
        $mockPayment = Mockery::mock(Payment::class);
        $mockViewFactory = Mockery::mock(ViewFactory::class);

        $requestData = []; // Empty request data
        $processedPaymentData = ['items' => [], 'total' => 0, 'currency' => 'USD'];
        $paymentPdfUrl = 'http://example.com/payment/pdf/invoice-456';
        $expectedRenderedMarkdown = 'Empty data payment preview.';
        $markdownConfig = ['theme' => 'default']; // Simplified config for this test

        $mockRequest->shouldReceive('all')
                    ->once()
                    ->andReturn($requestData);

        $mockPayment->shouldReceive('sendPaymentData')
                    ->once()
                    ->with($requestData)
                    ->andReturn($processedPaymentData);

        $mockPayment->shouldReceive('getAttribute')
                    ->with('paymentPdfUrl')
                    ->once()
                    ->andReturn($paymentPdfUrl);

        Config::shouldReceive('get')
              ->with('mail.markdown')
              ->once()
              ->andReturn($markdownConfig);

        $this->app->instance('view', $mockViewFactory);

        $mockMarkdownInstance = Mockery::mock(Markdown::class);
        Mockery::mock('overload:' . Markdown::class)
            ->shouldReceive('__construct')
            ->once()
            ->andReturnUsing(function () use ($mockMarkdownInstance) { return $mockMarkdownInstance; });

        $mockMarkdownInstance->shouldReceive('render')
                     ->once()
                     ->with('emails.send.payment', ['data' => array_merge($processedPaymentData, ['url' => $paymentPdfUrl])])
                     ->andReturn($expectedRenderedMarkdown);

        $controller = Mockery::mock(SendPaymentPreviewController::class)->makePartial();
        $controller->shouldReceive('authorize')
                   ->once()
                   ->with('send payment', $mockPayment)
                   ->andReturn(true);

        $response = $controller->__invoke($mockRequest, $mockPayment);

        expect($response)->toBe($expectedRenderedMarkdown);
    });

    test('it passes null paymentPdfUrl to markdown data if the url is not available', function () {
        $mockRequest = Mockery::mock(Request::class);
        $mockPayment = Mockery::mock(Payment::class);
        $mockViewFactory = Mockery::mock(ViewFactory::class);

        $requestData = ['info' => 'some_important_data'];
        $processedPaymentData = ['processed_info' => 'some_important_data_processed'];
        $paymentPdfUrl = null; // Testing with a null URL
        $expectedRenderedMarkdown = 'Preview without a direct PDF link.';
        $markdownConfig = ['theme' => 'default'];

        $mockRequest->shouldReceive('all')
                    ->once()
                    ->andReturn($requestData);

        $mockPayment->shouldReceive('sendPaymentData')
                    ->once()
                    ->with($requestData)
                    ->andReturn($processedPaymentData);

        $mockPayment->shouldReceive('getAttribute')
                    ->with('paymentPdfUrl')
                    ->once()
                    ->andReturn($paymentPdfUrl);

        Config::shouldReceive('get')
              ->with('mail.markdown')
              ->once()
              ->andReturn($markdownConfig);

        $this->app->instance('view', $mockViewFactory);

        $mockMarkdownInstance = Mockery::mock(Markdown::class);
        Mockery::mock('overload:' . Markdown::class)
            ->shouldReceive('__construct')
            ->once()
            ->andReturnUsing(function () use ($mockMarkdownInstance) { return $mockMarkdownInstance; });

        $mockMarkdownInstance->shouldReceive('render')
                     ->once()
                     ->with('emails.send.payment', ['data' => array_merge($processedPaymentData, ['url' => $paymentPdfUrl])])
                     ->andReturn($expectedRenderedMarkdown);

        $controller = Mockery::mock(SendPaymentPreviewController::class)->makePartial();
        $controller->shouldReceive('authorize')
                   ->once()
                   ->with('send payment', $mockPayment)
                   ->andReturn(true);

        $response = $controller->__invoke($mockRequest, $mockPayment);

        expect($response)->toBe($expectedRenderedMarkdown);
    });

    test('it throws AuthorizationException if the user is not authorized to send payment', function () {
        $mockRequest = Mockery::mock(Request::class);
        $mockPayment = Mockery::mock(Payment::class);

        // Create a partial mock of the controller to simulate authorization failure
        $controller = Mockery::mock(SendPaymentPreviewController::class)->makePartial();
        $controller->shouldReceive('authorize')
                   ->once()
                   ->with('send payment', $mockPayment)
                   ->andThrow(new AuthorizationException('User not authorized to send payment.'));

        // Assert that calling the invoke method throws the expected AuthorizationException
        expect(fn () => $controller->__invoke($mockRequest, $mockPayment))
            ->toThrow(AuthorizationException::class, 'User not authorized to send payment.');
    });