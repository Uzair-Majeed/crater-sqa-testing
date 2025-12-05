<?php

use Crater\Mail\InvoiceViewedMail;

// ========== INVOICEVIEWEDMAIL TESTS (10 MINIMAL TESTS) ==========

test('InvoiceViewedMail can be instantiated', function () {
    $data = ['invoice_number' => 'INV-001'];
    $mail = new InvoiceViewedMail($data);
    
    expect($mail)->toBeInstanceOf(InvoiceViewedMail::class);
});

test('InvoiceViewedMail extends Mailable', function () {
    $data = ['invoice_number' => 'INV-001'];
    $mail = new InvoiceViewedMail($data);
    
    expect($mail)->toBeInstanceOf(\Illuminate\Mail\Mailable::class);
});

test('InvoiceViewedMail is in correct namespace', function () {
    $reflection = new ReflectionClass(InvoiceViewedMail::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Mail');
});

test('InvoiceViewedMail uses Queueable trait', function () {
    $reflection = new ReflectionClass(InvoiceViewedMail::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Bus\Queueable');
});

test('InvoiceViewedMail uses SerializesModels trait', function () {
    $reflection = new ReflectionClass(InvoiceViewedMail::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Queue\SerializesModels');
});

test('InvoiceViewedMail has public data property', function () {
    $reflection = new ReflectionClass(InvoiceViewedMail::class);
    
    expect($reflection->hasProperty('data'))->toBeTrue();
    
    $property = $reflection->getProperty('data');
    expect($property->isPublic())->toBeTrue();
});

test('InvoiceViewedMail constructor sets data property', function () {
    $data = ['invoice_number' => 'INV-001', 'customer' => 'John Doe'];
    $mail = new InvoiceViewedMail($data);
    
    expect($mail->data)->toBe($data)
        ->and($mail->data['invoice_number'])->toBe('INV-001')
        ->and($mail->data['customer'])->toBe('John Doe');
});

test('InvoiceViewedMail has build method', function () {
    $reflection = new ReflectionClass(InvoiceViewedMail::class);
    
    expect($reflection->hasMethod('build'))->toBeTrue();
    
    $method = $reflection->getMethod('build');
    expect($method->isPublic())->toBeTrue();
});

test('InvoiceViewedMail build method uses markdown view', function () {
    $reflection = new ReflectionClass(InvoiceViewedMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->markdown(')
        ->and($fileContent)->toContain('emails.viewed.invoice');
});

test('InvoiceViewedMail build method sets from address', function () {
    $reflection = new ReflectionClass(InvoiceViewedMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->from(')
        ->and($fileContent)->toContain('config(\'mail.from.address\')')
        ->and($fileContent)->toContain('config(\'mail.from.name\')');
});