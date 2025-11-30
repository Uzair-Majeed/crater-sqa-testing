<?php

it('returns the preview view when the request has a preview parameter', function () {
    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $request->shouldReceive('has')
            ->with('preview')
            ->andReturn(true)
            ->once();

    $payment = Mockery::mock(\Crater\Models\Payment::class);
    // Ensure getGeneratedPDFOrStream is NOT called in this branch
    $payment->shouldNotReceive('getGeneratedPDFOrStream');

    $controller = new \Crater\Http\Controllers\V1\PDF\PaymentPdfController();

    // Call the __invoke method
    $response = $controller($request, $payment);

    // Assert that a view instance is returned with the correct view name
    expect($response)
        ->toBeInstanceOf(\Illuminate\View\View::class)
        ->and($response->name())
        ->toBe('app.pdf.payment.payment');
});

it('returns the generated PDF stream when the request does not have a preview parameter', function () {
    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $request->shouldReceive('has')
            ->with('preview')
            ->andReturn(false)
            ->once();

    $expectedPdfContent = 'mock-pdf-stream-content'; // Or a mock Response object
    $payment = Mockery::mock(\Crater\Models\Payment::class);
    $payment->shouldReceive('getGeneratedPDFOrStream')
            ->with('payment')
            ->andReturn($expectedPdfContent)
            ->once();

    $controller = new \Crater\Http\Controllers\V1\PDF\PaymentPdfController();

    // Call the __invoke method
    $response = $controller($request, $payment);

    // Assert that the response from getGeneratedPDFOrStream is returned
    expect($response)->toBe($expectedPdfContent);
});
