<?php

use Crater\Models\Invoice;

// ========== STRUCTURAL TESTS (NO MOCKERY) - MAX 10 TESTS ==========

test('Invoice model exists and is instantiable', function () {
    $invoice = new Invoice();
    expect($invoice)->toBeInstanceOf(Invoice::class);
});

test('Invoice has all relationship methods', function () {
    $invoice = new Invoice();
    
    expect(method_exists($invoice, 'transactions'))->toBeTrue()
        ->and(method_exists($invoice, 'items'))->toBeTrue()
        ->and(method_exists($invoice, 'taxes'))->toBeTrue()
        ->and(method_exists($invoice, 'payments'))->toBeTrue()
        ->and(method_exists($invoice, 'currency'))->toBeTrue()
        ->and(method_exists($invoice, 'company'))->toBeTrue()
        ->and(method_exists($invoice, 'customer'))->toBeTrue()
        ->and(method_exists($invoice, 'creator'))->toBeTrue();
});

test('Invoice has status constants', function () {
    expect(defined('Crater\Models\Invoice::STATUS_DRAFT'))->toBeTrue()
        ->and(defined('Crater\Models\Invoice::STATUS_SENT'))->toBeTrue()
        ->and(defined('Crater\Models\Invoice::STATUS_VIEWED'))->toBeTrue()
        ->and(defined('Crater\Models\Invoice::STATUS_COMPLETED'))->toBeTrue()
        ->and(defined('Crater\Models\Invoice::STATUS_UNPAID'))->toBeTrue()
        ->and(defined('Crater\Models\Invoice::STATUS_PAID'))->toBeTrue()
        ->and(defined('Crater\Models\Invoice::STATUS_PARTIALLY_PAID'))->toBeTrue();
});

test('Invoice has scope methods for filtering', function () {
    $reflection = new ReflectionClass(Invoice::class);
    
    expect($reflection->hasMethod('scopeWhereStatus'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWherePaidStatus'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWhereSearch'))->toBeTrue()
        ->and($reflection->hasMethod('scopeApplyFilters'))->toBeTrue()
        ->and($reflection->hasMethod('scopePaginateData'))->toBeTrue();
});

test('Invoice has static methods for CRUD operations', function () {
    $reflection = new ReflectionClass(Invoice::class);
    
    expect($reflection->hasMethod('createInvoice'))->toBeTrue()
        ->and($reflection->getMethod('createInvoice')->isStatic())->toBeTrue()
        ->and($reflection->hasMethod('deleteInvoices'))->toBeTrue()
        ->and($reflection->getMethod('deleteInvoices')->isStatic())->toBeTrue();
});

test('Invoice has accessor methods for formatted attributes', function () {
    $reflection = new ReflectionClass(Invoice::class);
    
    expect($reflection->hasMethod('getFormattedCreatedAtAttribute'))->toBeTrue()
        ->and($reflection->hasMethod('getFormattedDueDateAttribute'))->toBeTrue()
        ->and($reflection->hasMethod('getFormattedInvoiceDateAttribute'))->toBeTrue()
        ->and($reflection->hasMethod('getInvoicePdfUrlAttribute'))->toBeTrue();
});

test('Invoice uses required traits', function () {
    $reflection = new ReflectionClass(Invoice::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Crater\Traits\GeneratesPdfTrait')
        ->and($traits)->toContain('Crater\Traits\HasCustomFieldsTrait');
});

test('Invoice has business logic methods', function () {
    $reflection = new ReflectionClass(Invoice::class);
    
    expect($reflection->hasMethod('getPreviousStatus'))->toBeTrue()
        ->and($reflection->hasMethod('addInvoicePayment'))->toBeTrue()
        ->and($reflection->hasMethod('subtractInvoicePayment'))->toBeTrue()
        ->and($reflection->hasMethod('changeInvoiceStatus'))->toBeTrue();
});

test('Invoice model is in correct namespace', function () {
    $reflection = new ReflectionClass(Invoice::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Models');
});

test('Invoice extends Eloquent Model', function () {
    $invoice = new Invoice();
    expect($invoice)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
});
