<?php

use Crater\Console\Commands\CheckInvoiceStatus;

test('check invoice status command exists', function () {
    expect(class_exists(CheckInvoiceStatus::class))->toBeTrue();
});

test('command has correct properties', function () {
    $command = new CheckInvoiceStatus();
    
    expect($command->getName())->toBe('check:invoices:status');
    expect($command->getDescription())->toBe('Check invoices status.');
    
    // Check it extends Command
    expect($command)->toBeInstanceOf(Illuminate\Console\Command::class);
});

test('command methods exist', function () {
    $command = new CheckInvoiceStatus();
    
    expect(method_exists($command, 'handle'))->toBeTrue();
    expect(method_exists($command, '__construct'))->toBeTrue();
});

test('command signature is correct', function () {
    $reflection = new ReflectionClass(CheckInvoiceStatus::class);
    $property = $reflection->getProperty('signature');
    $property->setAccessible(true);
    
    $signature = $property->getValue(new CheckInvoiceStatus());
    expect($signature)->toBe('check:invoices:status');
});

test('command description is correct', function () {
    $reflection = new ReflectionClass(CheckInvoiceStatus::class);
    $property = $reflection->getProperty('description');
    $property->setAccessible(true);
    
    $description = $property->getValue(new CheckInvoiceStatus());
    expect($description)->toBe('Check invoices status.');
});

// Simple test that doesn't execute the handle method
test('command structure is valid', function () {
    $command = new CheckInvoiceStatus();
    
    // Just verify the object can be created
    expect($command)->toBeObject();
    
    // Verify it has the required console command properties
    $reflection = new ReflectionClass($command);
    
    expect($reflection->hasProperty('signature'))->toBeTrue();
    expect($reflection->hasProperty('description'))->toBeTrue();
    expect($reflection->hasMethod('handle'))->toBeTrue();
});