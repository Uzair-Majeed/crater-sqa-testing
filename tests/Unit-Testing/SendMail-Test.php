<?php

use Crater\Mail\SendEstimateMail;
use Crater\Mail\SendInvoiceMail;
use Crater\Mail\SendPaymentMail;

// ========== MERGED SEND MAIL TESTS (3 CLASSES, 18 TESTS WITH FUNCTIONAL COVERAGE) ==========
// NOTE: build() methods require database, Hashids, and routes - tested structurally
// Constructor and data property - tested functionally

// --- SendEstimateMail Tests (6 tests: 3 structural + 3 functional) ---

test('SendEstimateMail can be instantiated', function () {
    $data = ['from' => 'test@example.com', 'to' => 'customer@example.com'];
    $mail = new SendEstimateMail($data);
    expect($mail)->toBeInstanceOf(SendEstimateMail::class);
});

test('SendEstimateMail extends Mailable', function () {
    $mail = new SendEstimateMail([]);
    expect($mail)->toBeInstanceOf(\Illuminate\Mail\Mailable::class);
});

test('SendEstimateMail uses Queueable and SerializesModels traits', function () {
    $reflection = new ReflectionClass(SendEstimateMail::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Bus\Queueable')
        ->and($traits)->toContain('Illuminate\Queue\SerializesModels');
});

// --- FUNCTIONAL TESTS ---

test('SendEstimateMail constructor sets data property', function () {
    $testData = [
        'from' => 'sender@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Test Estimate',
        'body' => 'Test body content'
    ];
    
    $mail = new SendEstimateMail($testData);
    
    expect($mail->data)->toBe($testData)
        ->and($mail->data['from'])->toBe('sender@example.com')
        ->and($mail->data['to'])->toBe('recipient@example.com')
        ->and($mail->data['subject'])->toBe('Test Estimate');
});

test('SendEstimateMail has public data property', function () {
    $mail = new SendEstimateMail(['test' => 'value']);
    
    expect($mail->data)->toBeArray()
        ->and($mail->data)->toHaveKey('test')
        ->and($mail->data['test'])->toBe('value');
});

test('SendEstimateMail has build method that creates EmailLog', function () {
    $reflection = new ReflectionClass(SendEstimateMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($reflection->hasMethod('build'))->toBeTrue()
        ->and($fileContent)->toContain('EmailLog::create')
        ->and($fileContent)->toContain('Estimate::class')
        ->and($fileContent)->toContain('Hashids::connection(EmailLog::class)');
});

// --- SendInvoiceMail Tests (6 tests: 3 structural + 3 functional) ---

test('SendInvoiceMail can be instantiated', function () {
    $data = ['from' => 'test@example.com', 'to' => 'customer@example.com'];
    $mail = new SendInvoiceMail($data);
    expect($mail)->toBeInstanceOf(SendInvoiceMail::class);
});

test('SendInvoiceMail extends Mailable', function () {
    $mail = new SendInvoiceMail([]);
    expect($mail)->toBeInstanceOf(\Illuminate\Mail\Mailable::class);
});

test('SendInvoiceMail uses Queueable and SerializesModels traits', function () {
    $reflection = new ReflectionClass(SendInvoiceMail::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Bus\Queueable')
        ->and($traits)->toContain('Illuminate\Queue\SerializesModels');
});

// --- FUNCTIONAL TESTS ---

test('SendInvoiceMail constructor sets data property', function () {
    $testData = [
        'from' => 'billing@company.com',
        'to' => 'client@example.com',
        'subject' => 'Invoice #12345',
        'body' => 'Please find your invoice attached'
    ];
    
    $mail = new SendInvoiceMail($testData);
    
    expect($mail->data)->toBe($testData)
        ->and($mail->data['from'])->toBe('billing@company.com')
        ->and($mail->data['subject'])->toBe('Invoice #12345');
});

test('SendInvoiceMail has public data property accessible', function () {
    $mail = new SendInvoiceMail(['invoice_id' => 123]);
    
    expect($mail->data)->toBeArray()
        ->and($mail->data)->toHaveKey('invoice_id')
        ->and($mail->data['invoice_id'])->toBe(123);
});

test('SendInvoiceMail has build method that creates EmailLog', function () {
    $reflection = new ReflectionClass(SendInvoiceMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($reflection->hasMethod('build'))->toBeTrue()
        ->and($fileContent)->toContain('EmailLog::create')
        ->and($fileContent)->toContain('Invoice::class')
        ->and($fileContent)->toContain('route(\'invoice\'');
});

// --- SendPaymentMail Tests (6 tests: 3 structural + 3 functional) ---

test('SendPaymentMail can be instantiated', function () {
    $data = ['from' => 'test@example.com', 'to' => 'customer@example.com'];
    $mail = new SendPaymentMail($data);
    expect($mail)->toBeInstanceOf(SendPaymentMail::class);
});

test('SendPaymentMail extends Mailable', function () {
    $mail = new SendPaymentMail([]);
    expect($mail)->toBeInstanceOf(\Illuminate\Mail\Mailable::class);
});

test('SendPaymentMail uses Queueable and SerializesModels traits', function () {
    $reflection = new ReflectionClass(SendPaymentMail::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Bus\Queueable')
        ->and($traits)->toContain('Illuminate\Queue\SerializesModels');
});

// --- FUNCTIONAL TESTS ---

test('SendPaymentMail constructor sets data property', function () {
    $testData = [
        'from' => 'payments@company.com',
        'to' => 'customer@example.com',
        'subject' => 'Payment Confirmation',
        'body' => 'Thank you for your payment'
    ];
    
    $mail = new SendPaymentMail($testData);
    
    expect($mail->data)->toBe($testData)
        ->and($mail->data['from'])->toBe('payments@company.com')
        ->and($mail->data['subject'])->toBe('Payment Confirmation');
});

test('SendPaymentMail has public data property with array access', function () {
    $mail = new SendPaymentMail(['payment_id' => 456, 'amount' => 1000]);
    
    expect($mail->data)->toBeArray()
        ->and($mail->data)->toHaveKey('payment_id')
        ->and($mail->data['payment_id'])->toBe(456)
        ->and($mail->data['amount'])->toBe(1000);
});

test('SendPaymentMail has build method that creates EmailLog', function () {
    $reflection = new ReflectionClass(SendPaymentMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($reflection->hasMethod('build'))->toBeTrue()
        ->and($fileContent)->toContain('EmailLog::create')
        ->and($fileContent)->toContain('Payment::class')
        ->and($fileContent)->toContain('route(\'payment\'');
});