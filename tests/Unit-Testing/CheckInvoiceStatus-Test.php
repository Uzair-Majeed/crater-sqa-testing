<?php

use Carbon\Carbon;
use Crater\Console\Commands\CheckInvoiceStatus;
use Crater\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Mockery\MockInterface;

beforeEach(function () {
    // Set a fixed test time for Carbon::now() to ensure consistent date comparisons
    Carbon::setTestNow(Carbon::create(2023, 10, 26, 10, 0, 0));
});


test('command has correct signature and description', function () {
    $command = new CheckInvoiceStatus();

    expect($command->getName())->toBe('check:invoices:status')
        ->and($command->getDescription())->toBe('Check invoices status.');
});

test('handle does nothing when no overdue invoices are found by the query', function () {
    /** @var MockInterface&Invoice $invoiceStaticMock */
    $invoiceStaticMock = Mockery::mock('overload:' . Invoice::class);

    // Expect the full query chain to be called once with correct parameters
    $invoiceStaticMock->shouldReceive('whereNotIn')
        ->with('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT])
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('where')
        ->with('overdue', false)
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('whereDate')
        ->with('due_date', '<', Carbon::now())
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('get')
        ->once()
        ->andReturn(new Collection()); // Simulate no invoices found

    // Execute the command
    $command = new CheckInvoiceStatus();
    $command->handle();

    // Mockery will verify that all expectations (including `once()`) were met.
    // No side effects (like saving invoices or printing output) are expected here.
    $this->assertTrue(true); // Placeholder assertion to satisfy Pest
});

test('handle marks a single qualifying invoice as overdue and saves it', function () {
    // Create a partial mock for an Invoice instance to allow direct property access
    $mockInvoice = Mockery::mock(Invoice::class)->makePartial();
    $mockInvoice->invoice_number = 'INV-001';
    $mockInvoice->overdue = false; // Initial state

    // Expect 'save' method to be called once on the mocked invoice
    $mockInvoice->shouldReceive('save')
        ->once()
        ->andReturn(true);

    // Capture printf output
    ob_start();

    /** @var MockInterface&Invoice $invoiceStaticMock */
    $invoiceStaticMock = Mockery::mock('overload:' . Invoice::class);

    // Expect the query chain to return our single mocked invoice
    $invoiceStaticMock->shouldReceive('whereNotIn')
        ->with('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT])
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('where')
        ->with('overdue', false)
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('whereDate')
        ->with('due_date', '<', Carbon::now())
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('get')
        ->once()
        ->andReturn(new Collection([$mockInvoice]));

    // Execute the command
    $command = new CheckInvoiceStatus();
    $command->handle();

    $output = ob_get_clean();

    // Assertions
    expect($mockInvoice->overdue)->toBeTrue(); // Verify the property change on the partial mock
    expect($output)->toContain('Invoice INV-001 is OVERDUE');

    // Mockery will verify the `save()` call on $mockInvoice.
});

test('handle marks multiple qualifying invoices as overdue and saves them', function () {
    // Create partial mocks for multiple Invoice instances
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

    // Capture printf output
    ob_start();

    /** @var MockInterface&Invoice $invoiceStaticMock */
    $invoiceStaticMock = Mockery::mock('overload:' . Invoice::class);

    // Expect the query chain to return our multiple mocked invoices
    $invoiceStaticMock->shouldReceive('whereNotIn')
        ->with('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT])
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('where')
        ->with('overdue', false)
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('whereDate')
        ->with('due_date', '<', Carbon::now())
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('get')
        ->once()
        ->andReturn(new Collection([$mockInvoice1, $mockInvoice2]));

    // Execute the command
    $command = new CheckInvoiceStatus();
    $command->handle();

    $output = ob_get_clean();

    // Assertions
    expect($mockInvoice1->overdue)->toBeTrue();
    expect($mockInvoice2->overdue)->toBeTrue();
    expect($output)->toContain('Invoice INV-001 is OVERDUE')
        ->and($output)->toContain('Invoice INV-002 is OVERDUE');

    // Mockery will verify the `save()` calls on both $mockInvoice1 and $mockInvoice2.
});

test('handle ensures invoices with STATUS_COMPLETED or STATUS_DRAFT are excluded by the query', function () {
    /** @var MockInterface&Invoice $invoiceStaticMock */
    $invoiceStaticMock = Mockery::mock('overload:' . Invoice::class);

    // Crucial: Ensure 'whereNotIn' is called with the correct statuses
    $invoiceStaticMock->shouldReceive('whereNotIn')
        ->with('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT])
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('where')
        ->with('overdue', false)
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('whereDate')
        ->with('due_date', '<', Carbon::now())
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('get')
        ->once()
        ->andReturn(new Collection()); // If these invoices existed, they would be filtered out, leading to an empty collection.

    $command = new CheckInvoiceStatus();
    $command->handle();

    // No invoice saving or output is expected
    $this->assertTrue(true);
});

test('handle ensures invoices that are already overdue are excluded by the query', function () {
    /** @var MockInterface&Invoice $invoiceStaticMock */
    $invoiceStaticMock = Mockery::mock('overload:' . Invoice::class);

    $invoiceStaticMock->shouldReceive('whereNotIn')
        ->with('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT])
        ->once()
        ->andReturnSelf();

    // Crucial: Ensure 'where' is called with 'overdue', false
    $invoiceStaticMock->shouldReceive('where')
        ->with('overdue', false)
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('whereDate')
        ->with('due_date', '<', Carbon::now())
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('get')
        ->once()
        ->andReturn(new Collection()); // Already overdue invoices would be filtered out

    $command = new CheckInvoiceStatus();
    $command->handle();

    // No invoice saving or output is expected
    $this->assertTrue(true);
});

test('handle ensures invoices with future or current due dates are excluded by the query', function () {
    /** @var MockInterface&Invoice $invoiceStaticMock */
    $invoiceStaticMock = Mockery::mock('overload:' . Invoice::class);

    $invoiceStaticMock->shouldReceive('whereNotIn')
        ->with('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT])
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('where')
        ->with('overdue', false)
        ->once()
        ->andReturnSelf();

    // Crucial: Ensure 'whereDate' is called with 'due_date', '<', Carbon::now()
    $invoiceStaticMock->shouldReceive('whereDate')
        ->with('due_date', '<', Carbon::now())
        ->once()
        ->andReturnSelf();

    $invoiceStaticMock->shouldReceive('get')
        ->once()
        ->andReturn(new Collection()); // Invoices with due dates today or in the future would be filtered out

    $command = new CheckInvoiceStatus();
    $command->handle();

    // No invoice saving or output is expected
    $this->assertTrue(true);
});




afterEach(function () {
    Mockery::close();
});
