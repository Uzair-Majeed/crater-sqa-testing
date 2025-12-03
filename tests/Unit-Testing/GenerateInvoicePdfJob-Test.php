<?php

use Crater\Jobs\GenerateInvoicePdfJob;


test('it constructs with invoice and deleteExistingFile set to true', function () {
    $invoice = (object) ['id' => 1, 'invoice_number' => 'INV-001'];
    $deleteExistingFile = true;

    $job = new GenerateInvoicePdfJob($invoice, $deleteExistingFile);

    expect($job->invoice)->toBe($invoice);
    expect($job->deleteExistingFile)->toBeTrue();
});

test('it constructs with invoice and deleteExistingFile set to false', function () {
    $invoice = (object) ['id' => 1, 'invoice_number' => 'INV-002'];
    $deleteExistingFile = false;

    $job = new GenerateInvoicePdfJob($invoice, $deleteExistingFile);

    expect($job->invoice)->toBe($invoice);
    expect($job->deleteExistingFile)->toBeFalse();
});

test('it constructs with deleteExistingFile defaulting to false', function () {
    $invoice = (object) ['id' => 1, 'invoice_number' => 'INV-003'];

    $job = new GenerateInvoicePdfJob($invoice);

    expect($job->invoice)->toBe($invoice);
    expect($job->deleteExistingFile)->toBeFalse();
});

test('handle calls generatePDF on the invoice with true for deleteExistingFile', function () {
    $invoiceNumber = 'INV-TEST-001';
    $mockInvoice = Mockery::mock();
    $mockInvoice->invoice_number = $invoiceNumber;

    $mockInvoice->shouldReceive('generatePDF')
        ->once()
        ->with('invoice', $invoiceNumber, true)
        ->andReturnNull();

    $job = new GenerateInvoicePdfJob($mockInvoice, true);
    $result = $job->handle();

    expect($result)->toBe(0);
});

test('handle calls generatePDF on the invoice with false for deleteExistingFile', function () {
    $invoiceNumber = 'INV-TEST-002';
    $mockInvoice = Mockery::mock();
    $mockInvoice->invoice_number = $invoiceNumber;

    $mockInvoice->shouldReceive('generatePDF')
        ->once()
        ->with('invoice', $invoiceNumber, false)
        ->andReturnNull();

    $job = new GenerateInvoicePdfJob($mockInvoice, false);
    $result = $job->handle();

    expect($result)->toBe(0);
});

test('handle calls generatePDF on the invoice with default false for deleteExistingFile', function () {
    $invoiceNumber = 'INV-TEST-003';
    $mockInvoice = Mockery::mock();
    $mockInvoice->invoice_number = $invoiceNumber;

    $mockInvoice->shouldReceive('generatePDF')
        ->once()
        ->with('invoice', $invoiceNumber, false)
        ->andReturnNull();

    $job = new GenerateInvoicePdfJob($mockInvoice);
    $result = $job->handle();

    expect($result)->toBe(0);
});

test('handle calls generatePDF with empty string for invoice_number if it is null', function () {
    $mockInvoice = Mockery::mock();
    $mockInvoice->invoice_number = null; // Simulate null invoice_number

    $mockInvoice->shouldReceive('generatePDF')
        ->once()
        ->with('invoice', '', false) // Expect empty string as the second argument
        ->andReturnNull();

    $job = new GenerateInvoicePdfJob($mockInvoice);
    $result = $job->handle();

    expect($result)->toBe(0);
});

test('handle calls generatePDF with empty string for invoice_number if it is an empty string', function () {
    $mockInvoice = Mockery::mock();
    $mockInvoice->invoice_number = ''; // Simulate empty string invoice_number

    $mockInvoice->shouldReceive('generatePDF')
        ->once()
        ->with('invoice', '', false) // Expect empty string as the second argument
        ->andReturnNull();

    $job = new GenerateInvoicePdfJob($mockInvoice);
    $result = $job->handle();

    expect($result)->toBe(0);
});




afterEach(function () {
    Mockery::close();
});
