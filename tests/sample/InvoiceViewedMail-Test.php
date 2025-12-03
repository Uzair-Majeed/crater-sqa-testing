<?php

use Crater\Mail\InvoiceViewedMail;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Mockery::close();
});

test('it constructs with provided data', function () {
    $mailData = ['invoice_id' => 123, 'total' => 100, 'customer_name' => 'Jane Doe'];
    $mail = new InvoiceViewedMail($mailData);

    expect($mail->data)->toBe($mailData);
});

test('build method configures the mail correctly with valid data', function () {
    $fromAddress = 'noreply@craterapp.com';
    $fromName = 'Crater Application';
    $invoiceData = ['invoice_id' => 123, 'customer' => 'John Doe'];

    // Fix: Add null as the second argument to match how config() helper calls Config::get()
    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.address', null)
        ->andReturn($fromAddress);

    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.name', null)
        ->andReturn($fromName);

    // Create a partial mock of InvoiceViewedMail to assert method calls
    $mail = Mockery::mock(InvoiceViewedMail::class, [$invoiceData])->makePartial();

    $mail->shouldReceive('from')
        ->once()
        ->with($fromAddress, $fromName)
        ->andReturn($mail); // Important: from method returns $this for chaining

    // Fix: The data array for the markdown method should be associative,
    // typically ['data' => $this->data] where $this->data is $invoiceData.
    $mail->shouldReceive('markdown')
        ->once()
        ->with('emails.viewed.invoice', ['data' => $invoiceData])
        ->andReturn($mail); // markdown method also returns $this for chaining

    $result = $mail->build();

    expect($result)->toBe($mail);
});

test('build method handles null values for from address and name', function () {
    $fromAddress = null;
    $fromName = '';
    $invoiceData = ['invoice_id' => 456, 'status' => 'viewed'];

    // Fix: Add null as the second argument to match how config() helper calls Config::get()
    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.address', null)
        ->andReturn($fromAddress);

    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.name', null)
        ->andReturn($fromName);

    $mail = Mockery::mock(InvoiceViewedMail::class, [$invoiceData])->makePartial();

    $mail->shouldReceive('from')
        ->once()
        ->with($fromAddress, $fromName)
        ->andReturn($mail);

    // Fix: The data array for the markdown method should be associative.
    $mail->shouldReceive('markdown')
        ->once()
        ->with('emails.viewed.invoice', ['data' => $invoiceData])
        ->andReturn($mail);

    $result = $mail->build();

    expect($result)->toBe($mail);
});

test('build method handles empty invoice data', function () {
    $fromAddress = 'admin@example.com';
    $fromName = 'Test App';
    $invoiceData = []; // Empty data

    // Fix: Add null as the second argument to match how config() helper calls Config::get()
    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.address', null)
        ->andReturn($fromAddress);

    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.name', null)
        ->andReturn($fromName);

    $mail = Mockery::mock(InvoiceViewedMail::class, [$invoiceData])->makePartial();

    $mail->shouldReceive('from')
        ->once()
        ->with($fromAddress, $fromName)
        ->andReturn($mail);

    // Fix: The data array for the markdown method should be associative.
    $mail->shouldReceive('markdown')
        ->once()
        ->with('emails.viewed.invoice', ['data' => $invoiceData])
        ->andReturn($mail);

    $result = $mail->build();

    expect($result)->toBe($mail);
});

test('build method handles different types of invoice data', function (array $invoiceData) {
    $fromAddress = 'support@company.org';
    $fromName = 'Company Support';

    // Fix: Add null as the second argument to match how config() helper calls Config::get()
    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.address', null)
        ->andReturn($fromAddress);

    Config::shouldReceive('get')
        ->once()
        ->with('mail.from.name', null)
        ->andReturn($fromName);

    $mail = Mockery::mock(InvoiceViewedMail::class, [$invoiceData])->makePartial();

    $mail->shouldReceive('from')
        ->once()
        ->with($fromAddress, $fromName)
        ->andReturn($mail);

    // Fix: The data array for the markdown method should be associative.
    $mail->shouldReceive('markdown')
        ->once()
        ->with('emails.viewed.invoice', ['data' => $invoiceData])
        ->andReturn($mail);

    $result = $mail->build();

    expect($result)->toBe($mail);
})->with([
    'data with string values' => [['invoice_number' => 'INV-2023-001', 'total' => '150.00']],
    'data with mixed types' => [['id' => 1, 'date' => '2023-01-01', 'paid' => true]],
    'data with nested array' => [['customer' => ['id' => 101, 'name' => 'Alice'], 'items' => ['item1']]],
]);


afterEach(function () {
    Mockery::close();
});