<?php

use Carbon\Carbon;
use Crater\Console\Commands\CheckInvoiceStatus;
use Crater\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Mockery\MockInterface;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2023, 10, 26, 10, 0, 0));
});

function mockInvoiceStatic($returnCollection) {
    $mockBuilder = Mockery::mock('alias:' . Invoice::class);

    $mockBuilder->shouldReceive('whereNotIn')
        ->with('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT])
        ->once()
        ->andReturnSelf();

    $mockBuilder->shouldReceive('where')
        ->with('overdue', false)
        ->once()
        ->andReturnSelf();

    $mockBuilder->shouldReceive('whereDate')
        ->with('due_date', '<', Carbon::now())
        ->once()
        ->andReturnSelf();

    $mockBuilder->shouldReceive('get')
        ->once()
        ->andReturn($returnCollection);

    return $mockBuilder;
}

test('command has correct signature and description', function () {
    $command = new CheckInvoiceStatus();

    expect($command->getName())->toBe('check:invoices:status')
        ->and($command->getDescription())->toBe('Check invoices status.');
});

test('handle does nothing when no overdue invoices are found by the query', function () {
    mockInvoiceStatic(new Collection());

    $command = new CheckInvoiceStatus();
    $command->handle();

    $this->assertTrue(true);
});

test('handle marks a single qualifying invoice as overdue and saves it', function () {
    $mockInvoice = Mockery::mock(Invoice::class)->makePartial();
    $mockInvoice->invoice_number = 'INV-001';
    $mockInvoice->overdue = false;

    $mockInvoice->shouldReceive('save')
        ->once()
        ->andReturn(true);

    ob_start();
    mockInvoiceStatic(new Collection([$mockInvoice]));
    $command = new CheckInvoiceStatus();
    $command->handle();

    $output = ob_get_clean();

    expect($mockInvoice->overdue)->toBeTrue();
    expect($output)->toContain('Invoice INV-001 is OVERDUE');
});

test('handle marks multiple qualifying invoices as overdue and saves them', function () {
    $mockInvoice1 = Mockery::mock(Invoice::class)->makePartial();
    $mockInvoice1->invoice_number = 'INV-001';
    $mockInvoice1->overdue = false;
    $mockInvoice1->shouldReceive('save')
        ->once()
        ->andReturn(true);

    $mockInvoice2 = Mockery::mock(Invoice::class)->makePartial();
    $mockInvoice2->invoice_number = 'INV-002';
    $mockInvoice2->overdue = false;
    $mockInvoice2->shouldReceive('save')
        ->once()
        ->andReturn(true);

    ob_start();
    mockInvoiceStatic(new Collection([$mockInvoice1, $mockInvoice2]));
    $command = new CheckInvoiceStatus();
    $command->handle();

    $output = ob_get_clean();

    expect($mockInvoice1->overdue)->toBeTrue();
    expect($mockInvoice2->overdue)->toBeTrue();
    expect($output)->toContain('Invoice INV-001 is OVERDUE')
        ->and($output)->toContain('Invoice INV-002 is OVERDUE');
});

test('handle ensures invoices with STATUS_COMPLETED or STATUS_DRAFT are excluded by the query', function () {
    mockInvoiceStatic(new Collection()); // If these invoices existed, they would be filtered out, leading to an empty collection.

    $command = new CheckInvoiceStatus();
    $command->handle();

    $this->assertTrue(true);
});

test('handle ensures invoices that are already overdue are excluded by the query', function () {
    mockInvoiceStatic(new Collection()); // Already overdue invoices would be filtered out

    $command = new CheckInvoiceStatus();
    $command->handle();

    $this->assertTrue(true);
});

test('handle ensures invoices with future or current due dates are excluded by the query', function () {
    mockInvoiceStatic(new Collection()); // Invoices with due dates today or in the future would be filtered out

    $command = new CheckInvoiceStatus();
    $command->handle();

    $this->assertTrue(true);
});

afterEach(function () {
    Mockery::close();
});