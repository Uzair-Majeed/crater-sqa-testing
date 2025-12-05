<?php

test('constructor correctly assigns payment and deleteExistingFile properties', function () {
        $mockPayment = Mockery::mock();
        $job = new \Crater\Jobs\GeneratePaymentPdfJob($mockPayment, true);

        expect($job->payment)->toBe($mockPayment);
        expect($job->deleteExistingFile)->toBeTrue();

        $jobWithDefault = new \Crater\Jobs\GeneratePaymentPdfJob($mockPayment);
        expect($jobWithDefault->deleteExistingFile)->toBeFalse();
    });

    test('handle method calls generatePDF on payment with correct arguments and returns 0 when deleteExistingFile is true', function () {
        $paymentNumber = 'PAY-001';
        $deleteExistingFile = true;

        $mockPayment = Mockery::mock('stdClass');
        $mockPayment->payment_number = $paymentNumber;

        $mockPayment->shouldReceive('generatePDF')
            ->once()
            ->with('payment', $paymentNumber, $deleteExistingFile)
            ->andReturn(null);

        $job = new \Crater\Jobs\GeneratePaymentPdfJob($mockPayment, $deleteExistingFile);
        $result = $job->handle();

        expect($result)->toBe(0);
    });

    test('handle method calls generatePDF on payment with correct arguments and returns 0 when deleteExistingFile is false', function () {
        $paymentNumber = 'PAY-002';
        $deleteExistingFile = false;

        $mockPayment = Mockery::mock('stdClass');
        $mockPayment->payment_number = $paymentNumber;

        $mockPayment->shouldReceive('generatePDF')
            ->once()
            ->with('payment', $paymentNumber, $deleteExistingFile)
            ->andReturn(null);

        $job = new \Crater\Jobs\GeneratePaymentPdfJob($mockPayment, $deleteExistingFile);
        $result = $job->handle();

        expect($result)->toBe(0);
    });

    test('handle method calls generatePDF with default deleteExistingFile (false) when not explicitly provided', function () {
        $paymentNumber = 'PAY-003';
        $expectedDeleteExistingFile = false; // Default value expected

        $mockPayment = Mockery::mock('stdClass');
        $mockPayment->payment_number = $paymentNumber;

        $mockPayment->shouldReceive('generatePDF')
            ->once()
            ->with('payment', $paymentNumber, $expectedDeleteExistingFile)
            ->andReturn(null);

        $job = new \Crater\Jobs\GeneratePaymentPdfJob($mockPayment); // No deleteExistingFile provided
        $result = $job->handle();

        expect($result)->toBe(0);
    });

    test('handle method works correctly when payment_number is null', function () {
        $paymentNumber = null;
        $deleteExistingFile = false;

        $mockPayment = Mockery::mock('stdClass');
        $mockPayment->payment_number = $paymentNumber;

        $mockPayment->shouldReceive('generatePDF')
            ->once()
            ->with('payment', $paymentNumber, $deleteExistingFile)
            ->andReturn(null);

        $job = new \Crater\Jobs\GeneratePaymentPdfJob($mockPayment, $deleteExistingFile);
        $result = $job->handle();

        expect($result)->toBe(0);
    });

    test('handle method works correctly when payment_number is an empty string', function () {
        $paymentNumber = '';
        $deleteExistingFile = true;

        $mockPayment = Mockery::mock('stdClass');
        $mockPayment->payment_number = $paymentNumber;

        $mockPayment->shouldReceive('generatePDF')
            ->once()
            ->with('payment', $paymentNumber, $deleteExistingFile)
            ->andReturn(null);

        $job = new \Crater\Jobs\GeneratePaymentPdfJob($mockPayment, $deleteExistingFile);
        $result = $job->handle();

        expect($result)->toBe(0);
    });

afterEach(function () {
    Mockery::close();
});