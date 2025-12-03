<?php

use Carbon\Carbon;
use Crater\Mail\SendInvoiceMail;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\CustomField;
use Crater\Models\ExchangeRateLog;
use Crater\Models\Invoice;
use Crater\Models\InvoiceItem;
use Crater\Models\Payment;
use Crater\Models\RecurringInvoice;
use Crater\Models\Tax;
use Crater\Models\Transaction;
use Crater\Models\User;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Nwidart\Modules\Facades\Module;
use Vinkla\Hashids\Facades\Hashids;
use Barryvdh\DomPDF\Facade as PDF;

// Helper to invoke protected/private methods
function invokeMethod(object $object, string $methodName, array $parameters = [])
{
    $reflection = new ReflectionClass($object);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
}

beforeEach(function () {
    Mockery::close();
});


test('relationships return correct types', function () {
    $invoice = new Invoice();

    expect($invoice->transactions())->toBeInstanceOf(HasMany::class)
        ->and($invoice->emailLogs())->toBeInstanceOf(MorphMany::class)
        ->and($invoice->items())->toBeInstanceOf(HasMany::class)
        ->and($invoice->taxes())->toBeInstanceOf(HasMany::class)
        ->and($invoice->payments())->toBeInstanceOf(HasMany::class)
        ->and($invoice->currency())->toBeInstanceOf(BelongsTo::class)
        ->and($invoice->company())->toBeInstanceOf(BelongsTo::class)
        ->and($invoice->customer())->toBeInstanceOf(BelongsTo::class)
        ->and($invoice->recurringInvoice())->toBeInstanceOf(BelongsTo::class)
        ->and($invoice->creator())->toBeInstanceOf(BelongsTo::class);

    // Assert related models
    expect($invoice->transactions()->getRelated())->toBeInstanceOf(Transaction::class)
        ->and($invoice->emailLogs()->getRelated())->toBeInstanceOf(\App\Models\EmailLog::class)
        ->and($invoice->items()->getRelated())->toBeInstanceOf(InvoiceItem::class)
        ->and($invoice->taxes()->getRelated())->toBeInstanceOf(Tax::class)
        ->and($invoice->payments()->getRelated())->toBeInstanceOf(Payment::class)
        ->and($invoice->currency()->getRelated())->toBeInstanceOf(Currency::class)
        ->and($invoice->company()->getRelated())->toBeInstanceOf(Company::class)
        ->and($invoice->customer()->getRelated())->toBeInstanceOf(Customer::class)
        ->and($invoice->recurringInvoice()->getRelated())->toBeInstanceOf(RecurringInvoice::class)
        ->and($invoice->creator()->getRelated())->toBeInstanceOf(User::class);
});

test('getInvoicePdfUrlAttribute returns correct url', function () {
    $invoice = new Invoice(['unique_hash' => 'test_hash']);
    expect($invoice->invoicePdfUrl)->toBe(url('/invoices/pdf/test_hash'));
});

test('getPaymentModuleEnabledAttribute returns true if module is enabled', function () {
    Module::shouldReceive('has')->with('Payments')->andReturn(true);
    Module::shouldReceive('isEnabled')->with('Payments')->andReturn(true);

    $invoice = new Invoice();
    expect($invoice->paymentModuleEnabled)->toBeTrue();
});

test('getPaymentModuleEnabledAttribute returns false if module is not present', function () {
    Module::shouldReceive('has')->with('Payments')->andReturn(false);
    Module::shouldNotReceive('isEnabled');

    $invoice = new Invoice();
    expect($invoice->paymentModuleEnabled)->toBeFalse();
});

test('getPaymentModuleEnabledAttribute returns false if module is present but disabled', function () {
    Module::shouldReceive('has')->with('Payments')->andReturn(true);
    Module::shouldReceive('isEnabled')->with('Payments')->andReturn(false);

    $invoice = new Invoice();
    expect($invoice->paymentModuleEnabled)->toBeFalse();
});

test('getAllowEditAttribute returns true by default', function () {
    CompanySetting::shouldReceive('getSetting')->andReturn('something_else');

    $invoice = new Invoice([
        'company_id' => 1,
        'status' => Invoice::STATUS_DRAFT,
        'paid_status' => Invoice::STATUS_UNPAID,
    ]);

    expect($invoice->allowEdit)->toBeTrue();
});

test('getAllowEditAttribute disables edit when retrospective_edit is disable_on_invoice_sent and status is sent/viewed/completed/draft and paid_status is partially_paid/paid', function ($status, $paidStatus, $expected) {
    CompanySetting::shouldReceive('getSetting')
        ->with('retrospective_edits', 1)
        ->andReturn('disable_on_invoice_sent');

    $invoice = new Invoice([
        'company_id' => 1,
        'status' => $status,
        'paid_status' => $paidStatus,
    ]);

    expect($invoice->allowEdit)->toBe($expected);
})->with([
    [Invoice::STATUS_DRAFT, Invoice::STATUS_PARTIALLY_PAID, false],
    [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID, false],
    [Invoice::STATUS_VIEWED, Invoice::STATUS_PARTIALLY_PAID, false],
    [Invoice::STATUS_COMPLETED, Invoice::STATUS_PARTIALLY_PAID, false],

    [Invoice::STATUS_DRAFT, Invoice::STATUS_PAID, false],
    [Invoice::STATUS_SENT, Invoice::STATUS_PAID, false],
    [Invoice::STATUS_VIEWED, Invoice::STATUS_PAID, false],
    [Invoice::STATUS_COMPLETED, Invoice::STATUS_PAID, false],

    [Invoice::STATUS_DRAFT, Invoice::STATUS_UNPAID, true],
    [Invoice::STATUS_SENT, Invoice::STATUS_UNPAID, true],
    [Invoice::STATUS_VIEWED, Invoice::STATUS_UNPAID, true],
    [Invoice::STATUS_COMPLETED, Invoice::STATUS_UNPAID, true],
    ['SOME_OTHER_STATUS', Invoice::STATUS_PARTIALLY_PAID, true],
    ['SOME_OTHER_STATUS', Invoice::STATUS_PAID, true],
]);

test('getAllowEditAttribute disables edit when retrospective_edit is disable_on_invoice_partial_paid and paid_status is partially_paid/paid', function ($paidStatus, $expected) {
    CompanySetting::shouldReceive('getSetting')
        ->with('retrospective_edits', 1)
        ->andReturn('disable_on_invoice_partial_paid');

    $invoice = new Invoice([
        'company_id' => 1,
        'status' => Invoice::STATUS_DRAFT,
        'paid_status' => $paidStatus,
    ]);

    expect($invoice->allowEdit)->toBe($expected);
})->with([
    [Invoice::STATUS_PARTIALLY_PAID, false],
    [Invoice::STATUS_PAID, false],
    [Invoice::STATUS_UNPAID, true],
]);


test('getAllowEditAttribute disables edit when retrospective_edit is disable_on_invoice_paid and paid_status is paid', function ($paidStatus, $expected) {
    // Mock the company setting
    CompanySetting::shouldReceive('getSetting')
        ->with('retrospective_edits', 1)
        ->andReturn('disable_on_invoice_paid');

    $invoice = new Invoice([
        'company_id' => 1,
        'status' => Invoice::STATUS_DRAFT,
        'paid_status' => $paidStatus,
    ]);

    // Assert the computed property
    expect($invoice->allowEdit)->toBe($expected);
})->with([
    [Invoice::STATUS_PAID, false],
    [Invoice::STATUS_PARTIALLY_PAID, true],
    [Invoice::STATUS_UNPAID, true],
]);

test('getPreviousStatus returns VIEWED if viewed is true', function () {
    $invoice = new Invoice(['viewed' => true, 'sent' => true]);
    expect($invoice->getPreviousStatus())->toBe(Invoice::STATUS_VIEWED);
});

test('getPreviousStatus returns SENT if sent is true and viewed is false', function () {
    $invoice = new Invoice(['viewed' => false, 'sent' => true]);
    expect($invoice->getPreviousStatus())->toBe(Invoice::STATUS_SENT);
});

test('getPreviousStatus returns DRAFT if neither viewed nor sent is true', function () {
    $invoice = new Invoice(['viewed' => false, 'sent' => false]);
    expect($invoice->getPreviousStatus())->toBe(Invoice::STATUS_DRAFT);
});

test('getFormattedNotesAttribute calls getNotes', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->shouldReceive('getNotes')->once()->andReturn('Formatted Notes');

    expect($invoice->formattedNotes)->toBe('Formatted Notes');
});

test('getFormattedCreatedAtAttribute formats created_at correctly', function () {
    $now = Carbon::now();
    $invoice = new Invoice([
        'created_at' => $now->toDateTimeString(),
        'company_id' => 1,
    ]);

    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', 1)
        ->andReturn('Y-m-d H:i:s');

    expect($invoice->formattedCreatedAt)->toBe($now->format('Y-m-d H:i:s'));
});

test('getFormattedDueDateAttribute formats due_date correctly', function () {
    $dueDate = Carbon::now()->addDays(10);
    $invoice = new Invoice([
        'due_date' => $dueDate->toDateTimeString(),
        'company_id' => 1,
    ]);

    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', 1)
        ->andReturn('Y-m-d');

    expect($invoice->formattedDueDate)->toBe($dueDate->format('Y-m-d'));
});

test('getFormattedInvoiceDateAttribute formats invoice_date correctly', function () {
    $invoiceDate = Carbon::now();
    $invoice = new Invoice([
        'invoice_date' => $invoiceDate->toDateTimeString(),
        'company_id' => 1,
    ]);

    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', 1)
        ->andReturn('d/m/Y');

    expect($invoice->formattedInvoiceDate)->toBe($invoiceDate->format('d/m/Y'));
});


// Scopes
test('scopeWhereStatus applies correct where clause', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('where')->with('invoices.status', 'SENT')->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeWhereStatus($builder, 'SENT');
});

test('scopeWherePaidStatus applies correct where clause', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('where')->with('invoices.paid_status', 'PAID')->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeWherePaidStatus($builder, 'PAID');
});

test('scopeWhereDueStatus applies correct whereIn clause', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('whereIn')->with('invoices.paid_status', [
        Invoice::STATUS_UNPAID,
        Invoice::STATUS_PARTIALLY_PAID,
    ])->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeWhereDueStatus($builder, 'DUE');
});

test('scopeWhereInvoiceNumber applies correct like clause', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('where')->with('invoices.invoice_number', 'LIKE', '%INV-001%')->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeWhereInvoiceNumber($builder, 'INV-001');
});

test('scopeInvoicesBetween applies correct whereBetween clause', function () {
    $start = Carbon::create(2023, 1, 1);
    $end = Carbon::create(2023, 1, 31);

    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('whereBetween')
        ->with('invoices.invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
        ->andReturnSelf()
        ->once();

    $invoice = new Invoice();
    $invoice->scopeInvoicesBetween($builder, $start, $end);
});

test('scopeWhereSearch applies correct whereHas clauses for multiple terms', function () {
    $search = 'John Doe Company';
    $terms = explode(' ', $search);

    $builder = Mockery::mock(Builder::class);

    foreach ($terms as $term) {
        $builder->shouldReceive('whereHas')->once()->with('customer', Mockery::on(function ($closure) use ($term) {
            $customerQuery = Mockery::mock(Builder::class);
            $customerQuery->shouldReceive('where')->once()->with('name', 'LIKE', '%' . $term . '%')->andReturnSelf();
            $customerQuery->shouldReceive('orWhere')->once()->with('contact_name', 'LIKE', '%' . $term . '%')->andReturnSelf();
            $customerQuery->shouldReceive('orWhere')->once()->with('company_name', 'LIKE', '%' . $term . '%')->andReturnSelf();
            $closure($customerQuery);
            return true;
        }))->andReturnSelf();
    }

    $invoice = new Invoice();
    $invoice->scopeWhereSearch($builder, $search);
});


test('scopeWhereOrder applies correct orderBy clause', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('orderBy')->with('created_at', 'desc')->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeWhereOrder($builder, 'created_at', 'desc');
});

test('scopeApplyFilters applies search filter', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('whereSearch')->with('test search')->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeApplyFilters($builder, ['search' => 'test search']);
});

test('scopeApplyFilters applies status filter for paid statuses', function ($status) {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('wherePaidStatus')->with($status)->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeApplyFilters($builder, ['status' => $status]);
})->with([
    Invoice::STATUS_UNPAID,
    Invoice::STATUS_PARTIALLY_PAID,
    Invoice::STATUS_PAID,
]);

test('scopeApplyFilters applies due status filter', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('whereDueStatus')->with('DUE')->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeApplyFilters($builder, ['status' => 'DUE']);
});

test('scopeApplyFilters applies general status filter', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('whereStatus')->with('SENT')->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeApplyFilters($builder, ['status' => 'SENT']);
});

test('scopeApplyFilters applies paid_status filter', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('wherePaidStatus')->with('PARTIALLY_PAID')->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeApplyFilters($builder, ['paid_status' => 'PARTIALLY_PAID']);
});

test('scopeApplyFilters applies invoice_id filter', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('whereInvoice')->with(123)->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeApplyFilters($builder, ['invoice_id' => 123]);
});

test('scopeApplyFilters applies invoice_number filter', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('whereInvoiceNumber')->with('INV-001')->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeApplyFilters($builder, ['invoice_number' => 'INV-001']);
});

test('scopeApplyFilters applies date range filter', function () {
    $start = '2023-01-01';
    $end = '2023-01-31';

    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('invoicesBetween')
        ->withArgs(function ($argStart, $argEnd) use ($start, $end) {
            return $argStart->format('Y-m-d') === $start && $argEnd->format('Y-m-d') === $end;
        })
        ->andReturnSelf()
        ->once();

    $invoice = new Invoice();
    $invoice->scopeApplyFilters($builder, ['from_date' => $start, 'to_date' => $end]);
});

test('scopeApplyFilters applies customer_id filter', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('whereCustomer')->with(456)->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeApplyFilters($builder, ['customer_id' => 456]);
});

test('scopeApplyFilters applies order by filter with defaults', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('whereOrder')->with('sequence_number', 'desc')->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeApplyFilters($builder, ['some_other_filter' => 'value']);
});

test('scopeApplyFilters applies order by filter with custom field and order', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('whereOrder')->with('invoice_date', 'asc')->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeApplyFilters($builder, ['orderByField' => 'invoice_date', 'orderBy' => 'asc']);
});

test('scopeWhereInvoice applies correct orWhere clause', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('orWhere')->with('id', 1)->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeWhereInvoice($builder, 1);
});

test('scopeWhereCompany applies correct where clause using request header', function () {
    RequestFacade::shouldReceive('header')->with('company')->andReturn(123)->once();

    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('where')->with('invoices.company_id', 123)->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeWhereCompany($builder);
});

test('scopeWhereCompanyId applies correct where clause', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('where')->with('invoices.company_id', 456)->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeWhereCompanyId($builder, 456);
});

test('scopeWhereCustomer applies correct where clause', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('where')->with('invoices.customer_id', 789)->andReturnSelf()->once();

    $invoice = new Invoice();
    $invoice->scopeWhereCustomer($builder, 789);
});

test('scopePaginateData returns paginated results when limit is numeric', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('paginate')->with(10)->andReturn('paginated results')->once();

    $invoice = new Invoice();
    expect($invoice->scopePaginateData($builder, 10))->toBe('paginated results');
});

test('scopePaginateData returns all results when limit is all', function () {
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('get')->andReturn('all results')->once();

    $invoice = new Invoice();
    expect($invoice->scopePaginateData($builder, 'all'))->toBe('all results');
});


// Static Methods
test('createInvoice creates an invoice and related data correctly', function () {
    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $mockInvoice = Mockery::mock(Invoice::class)->makePartial();
    $mockInvoice->id = 1;
    $mockInvoice->company_id = 1;
    $mockInvoice->customer_id = 1;
    $mockInvoice->exchange_rate = 1.0;
    $mockInvoice->currency_id = 1;
    $mockInvoice->shouldReceive('save')->once();

    $invoiceData = [
        'company_id' => 1,
        'customer_id' => 1,
        'currency_id' => 1,
        'exchange_rate' => 1.0,
        'total' => 100,
        'sub_total' => 90,
        'tax' => 10,
    ];
    $requestItems = [['price' => 50, 'quantity' => 2, 'total' => 100]];
    $requestTaxes = [['amount' => 10]];
    $requestCustomFields = ['field1' => 'value1'];

    $request->shouldReceive('getInvoicePayload')->andReturn($invoiceData)->once();
    $request->shouldReceive('has')->with('invoiceSend')->andReturn(true);
    $request->shouldReceive('items')->andReturn($requestItems);
    $request->shouldReceive('header')->with('company')->andReturn(1)->once();
    $request->shouldReceive('has')->with('taxes')->andReturn(true);
    $request->shouldReceive('taxes')->andReturn($requestTaxes);
    $request->shouldReceive('customFields')->andReturn($requestCustomFields);

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('create')
        ->with(array_merge($invoiceData, ['status' => Invoice::STATUS_SENT]))
        ->andReturn($mockInvoice)
        ->once();

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('with')
        ->andReturnSelf()
        ->shouldReceive('find')
        ->with($mockInvoice->id)
        ->andReturn($mockInvoice)
        ->once();

    $mockSerialNumberFormatter = Mockery::mock(SerialNumberFormatter::class);
    $mockSerialNumberFormatter->nextSequenceNumber = 'SN001';
    $mockSerialNumberFormatter->nextCustomerSequenceNumber = 'CSN001';
    $mockSerialNumberFormatter->shouldReceive('setModel')->with($mockInvoice)->andReturnSelf();
    $mockSerialNumberFormatter->shouldReceive('setCompany')->with($mockInvoice->company_id)->andReturnSelf();
    $mockSerialNumberFormatter->shouldReceive('setCustomer')->with($mockInvoice->customer_id)->andReturnSelf();
    $mockSerialNumberFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    Mockery::mock('overload:Crater\Services\SerialNumberFormatter', $mockSerialNumberFormatter);

    Hashids::shouldReceive('connection->encode')->with($mockInvoice->id)->andReturn('encoded_hash')->once();

    CompanySetting::shouldReceive('getSetting')->with('currency', 1)->andReturn('1')->once();

    Mockery::mock('alias:Crater\Models\ExchangeRateLog')
        ->shouldNotReceive('addExchangeRateLog');

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('createItems')
        ->with($mockInvoice, $requestItems)
        ->once();

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('createTaxes')
        ->with($mockInvoice, $requestTaxes)
        ->once();

    $mockInvoice->shouldReceive('addCustomFields')->with($requestCustomFields)->once();

    $result = Invoice::createInvoice($request);

    expect($result)->toBe($mockInvoice);
    expect($mockInvoice->sequence_number)->toBe('SN001');
    expect($mockInvoice->customer_sequence_number)->toBe('CSN001');
    expect($mockInvoice->unique_hash)->toBe('encoded_hash');
});

test('createInvoice calls ExchangeRateLog if currency changes', function () {
    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $mockInvoice = Mockery::mock(Invoice::class)->makePartial();
    $mockInvoice->id = 1;
    $mockInvoice->company_id = 1;
    $mockInvoice->customer_id = 1;
    $mockInvoice->exchange_rate = 1.2;
    $mockInvoice->currency_id = 2;
    $mockInvoice->shouldReceive('save')->once();

    $invoiceData = [
        'company_id' => 1,
        'customer_id' => 1,
        'currency_id' => 2,
        'exchange_rate' => 1.2,
        'total' => 100,
        'sub_total' => 90,
        'tax' => 10,
    ];
    $request->shouldReceive('getInvoicePayload')->andReturn($invoiceData)->once();
    $request->shouldReceive('has')->with('invoiceSend')->andReturn(false);
    $request->shouldReceive('items')->andReturn([]);
    $request->shouldReceive('header')->with('company')->andReturn(1)->once();
    $request->shouldReceive('has')->with('taxes')->andReturn(false);
    $request->shouldReceive('customFields')->andReturn(null);

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('create')
        ->with(array_merge($invoiceData, ['status' => Invoice::STATUS_DRAFT]))
        ->andReturn($mockInvoice)
        ->once();

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('with')
        ->andReturnSelf()
        ->shouldReceive('find')
        ->with($mockInvoice->id)
        ->andReturn($mockInvoice)
        ->once();

    $mockSerialNumberFormatter = Mockery::mock(SerialNumberFormatter::class);
    $mockSerialNumberFormatter->nextSequenceNumber = 'SN001';
    $mockSerialNumberFormatter->nextCustomerSequenceNumber = 'CSN001';
    $mockSerialNumberFormatter->shouldReceive('setModel')->andReturnSelf();
    $mockSerialNumberFormatter->shouldReceive('setCompany')->andReturnSelf();
    $mockSerialNumberFormatter->shouldReceive('setCustomer')->andReturnSelf();
    $mockSerialNumberFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    Mockery::mock('overload:Crater\Services\SerialNumberFormatter', $mockSerialNumberFormatter);

    Hashids::shouldReceive('connection->encode')->andReturn('encoded_hash')->once();

    CompanySetting::shouldReceive('getSetting')->with('currency', 1)->andReturn('1')->once();

    Mockery::mock('alias:Crater\Models\ExchangeRateLog')
        ->shouldReceive('addExchangeRateLog')
        ->with($mockInvoice)
        ->once();

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('createItems')
        ->with($mockInvoice, [])
        ->once();

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldNotReceive('createTaxes');

    $mockInvoice->shouldNotReceive('addCustomFields');

    Invoice::createInvoice($request);
});

test('updateInvoice returns error when customer cannot be changed after payment', function () {
    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $invoice = new Invoice([
        'total' => 200,
        'due_amount' => 100,
        'customer_id' => 1,
        'company_id' => 1,
        'exchange_rate' => 1.0,
    ]);
    $request->shouldReceive('getInvoicePayload')->andReturn([])->once();
    $request->shouldReceive('customer_id')->andReturn(2)->once();

    $result = $invoice->updateInvoice($request);
    expect($result)->toBe('customer_cannot_be_changed_after_payment_is_added');
});

test('updateInvoice returns error when total invoice amount is less than paid amount', function () {
    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $invoice = new Invoice([
        'total' => 200,
        'due_amount' => 50,
        'customer_id' => 1,
        'company_id' => 1,
        'exchange_rate' => 1.0,
    ]);
    $request->shouldReceive('getInvoicePayload')->andReturn([])->once();
    $request->shouldReceive('customer_id')->andReturn(1)->once();
    $request->shouldReceive('total')->andReturn(100)->once();

    $result = $invoice->updateInvoice($request);
    expect($result)->toBe('total_invoice_amount_must_be_more_than_paid_amount');
});

test('updateInvoice updates an invoice and related data correctly', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->id = 1;
    $invoice->total = 100;
    $invoice->due_amount = 100;
    $invoice->base_due_amount = 100;
    $invoice->customer_id = 1;
    $invoice->company_id = 1;
    $invoice->exchange_rate = 1.0;
    $invoice->currency_id = 1;
    $invoice->items = collect([]);
    $invoice->shouldReceive('update')->once();
    $invoice->shouldReceive('save')->once();

    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $invoiceData = [
        'customer_id' => 1,
        'total' => 120,
        'exchange_rate' => 1.0,
        'currency_id' => 1,
        'due_amount' => 120,
    ];
    $request->shouldReceive('getInvoicePayload')->andReturn($invoiceData)->once();
    $request->shouldReceive('customer_id')->andReturn(1)->once();
    $request->shouldReceive('total')->andReturn(120)->once();
    $request->shouldReceive('header')->with('company')->andReturn(1)->once();
    $request->shouldReceive('items')->andReturn([['price' => 60, 'quantity' => 2, 'total' => 120]]);
    $request->shouldReceive('has')->with('taxes')->andReturn(true);
    $request->shouldReceive('taxes')->andReturn([['amount' => 20]]);
    $request->shouldReceive('customFields')->andReturn(['field1' => 'value2']);

    $mockSerialNumberFormatter = Mockery::mock(SerialNumberFormatter::class);
    $mockSerialNumberFormatter->nextCustomerSequenceNumber = 'CSN002';
    $mockSerialNumberFormatter->shouldReceive('setModel')->with($invoice)->andReturnSelf();
    $mockSerialNumberFormatter->shouldReceive('setCompany')->with($invoice->company_id)->andReturnSelf();
    $mockSerialNumberFormatter->shouldReceive('setCustomer')->with($request->customer_id)->andReturnSelf();
    $mockSerialNumberFormatter->shouldReceive('setModelObject')->with($invoice->id)->andReturnSelf();
    $mockSerialNumberFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    Mockery::mock('overload:Crater\Services\SerialNumberFormatter', $mockSerialNumberFormatter);

    CompanySetting::shouldReceive('getSetting')->with('currency', 1)->andReturn('1')->once();

    Mockery::mock('alias:Crater\Models\ExchangeRateLog')
        ->shouldNotReceive('addExchangeRateLog');

    $mockItemRelation = Mockery::mock(HasMany::class);
    $mockItemRelation->shouldReceive('delete')->once();
    $invoice->shouldReceive('items')->andReturn($mockItemRelation);

    $mockTaxRelation = Mockery::mock(HasMany::class);
    $mockTaxRelation->shouldReceive('delete')->once();
    $invoice->shouldReceive('taxes')->andReturn($mockTaxRelation);

    $mockInvoiceItem = Mockery::mock(InvoiceItem::class)->makePartial();
    $mockInvoiceItem->shouldReceive('fields')->andReturnSelf();
    $mockInvoiceItem->shouldReceive('get')->andReturn(collect());
    $invoice->items = collect([$mockInvoiceItem]);

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('createItems')
        ->with($invoice, $request->items)
        ->once();

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('createTaxes')
        ->with($invoice, $request->taxes)
        ->once();

    $invoice->shouldReceive('updateCustomFields')->with($request->customFields)->once();
    $invoice->shouldReceive('getPreviousStatus')->andReturn(Invoice::STATUS_DRAFT)->once();

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('with')
        ->andReturnSelf()
        ->shouldReceive('find')
        ->with($invoice->id)
        ->andReturn($invoice)
        ->once();

    $result = $invoice->updateInvoice($request);

    expect($result)->toBe($invoice);
    expect($invoice->due_amount)->toBe(120);
    expect($invoice->base_due_amount)->toBe(120);
    expect($invoice->customer_sequence_number)->toBe('CSN002');
});

test('sendInvoiceData prepares data for email', function () {
    $customer = new Customer(['name' => 'John Doe']);
    $company = new Company(['name' => 'Acme Corp']);

    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->company_id = 1;
    $invoice->invoice_number = 'INV-001';
    $invoice->reference_number = 'REF-001';
    $invoice->formattedInvoiceDate = '2023-01-01';
    $invoice->formattedDueDate = '2023-01-31';

    $invoice->shouldReceive('toArray')->andReturn(['id' => 1, 'number' => 'INV-001']);
    $invoice->customer = $customer;
    $invoice->shouldReceive('customer->toArray')->andReturn(['id' => 1, 'name' => 'John Doe']);

    Mockery::mock('alias:Crater\Models\Company')
        ->shouldReceive('find')
        ->with($invoice->company_id)
        ->andReturn($company)
        ->once();

    $invoice->shouldReceive('getEmailString')
        ->with('Subject: {INVOICE_NUMBER}')
        ->andReturn('Subject: INV-001')
        ->once();
    $invoice->shouldReceive('getEmailString')
        ->with('Body: {INVOICE_DUE_DATE}')
        ->andReturn('Body: 2023-01-31')
        ->once();
    $invoice->shouldReceive('getEmailAttachmentSetting')->andReturn(true)->once();
    $invoice->shouldReceive('getPDFData')->andReturn('PDF Data')->once();

    $initialData = [
        'subject' => 'Subject: {INVOICE_NUMBER}',
        'body' => 'Body: {INVOICE_DUE_DATE}',
    ];

    $result = $invoice->sendInvoiceData($initialData);

    expect($result)->toMatchArray([
        'invoice' => ['id' => 1, 'number' => 'INV-001'],
        'customer' => ['id' => 1, 'name' => 'John Doe'],
        'company' => $company,
        'subject' => 'Subject: INV-001',
        'body' => 'Body: 2023-01-31',
        'attach' => ['data' => 'PDF Data'],
    ]);
});

test('sendInvoiceData handles no PDF attachment', function () {
    $customer = new Customer(['name' => 'John Doe']);
    $company = new Company(['name' => 'Acme Corp']);

    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->company_id = 1;
    $invoice->invoice_number = 'INV-001';
    $invoice->reference_number = 'REF-001';
    $invoice->formattedInvoiceDate = '2023-01-01';
    $invoice->formattedDueDate = '2023-01-31';

    $invoice->shouldReceive('toArray')->andReturn(['id' => 1, 'number' => 'INV-001']);
    $invoice->customer = $customer;
    $invoice->shouldReceive('customer->toArray')->andReturn(['id' => 1, 'name' => 'John Doe']);

    Mockery::mock('alias:Crater\Models\Company')
        ->shouldReceive('find')
        ->andReturn($company);

    $invoice->shouldReceive('getEmailString')->andReturnUsing(fn($s) => $s);
    $invoice->shouldReceive('getEmailAttachmentSetting')->andReturn(false)->once();
    $invoice->shouldNotReceive('getPDFData');

    $initialData = ['subject' => '', 'body' => ''];
    $result = $invoice->sendInvoiceData($initialData);

    expect($result['attach']['data'])->toBeNull();
});

test('preview returns correct structure', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->shouldReceive('sendInvoiceData')->andReturn(['prepared_data'])->once();

    Mockery::mock('overload:Crater\Mail\SendInvoiceMail', function (MockInterface $mock) {
        $mock->shouldReceive('__construct')->with(['prepared_data'])->once();
    });

    $result = $invoice->preview([]);

    expect($result)->toMatchArray([
        'type' => 'preview',
        'view' => Mockery::type(SendInvoiceMail::class),
    ]);
});

test('send dispatches mail and updates invoice status if draft', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->status = Invoice::STATUS_DRAFT;
    $invoice->shouldReceive('sendInvoiceData')->andReturn(['to' => 'test@example.com', 'prepared_data'])->once();
    $invoice->shouldReceive('save')->once();

    Mail::shouldReceive('to')->with('test@example.com')->andReturnSelf()->once();
    Mail::shouldReceive('send')->with(Mockery::type(SendInvoiceMail::class))->once();

    Mockery::mock('overload:Crater\Mail\SendInvoiceMail', function (MockInterface $mock) {
        $mock->shouldReceive('__construct')->with(['prepared_data'])->once();
    });

    $invoice->shouldReceive('getPreviousStatus')->andReturn(Invoice::STATUS_DRAFT);

    $result = $invoice->send([]);

    expect($result)->toMatchArray([
        'success' => true,
        'type' => 'send',
    ]);
    expect($invoice->status)->toBe(Invoice::STATUS_SENT);
    expect($invoice->sent)->toBeTrue();
});

test('send dispatches mail but does not update invoice status if not draft', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->status = Invoice::STATUS_SENT;
    $invoice->shouldReceive('sendInvoiceData')->andReturn(['to' => 'test@example.com', 'prepared_data'])->once();
    $invoice->shouldNotReceive('save');
    $invoice->shouldNotReceive('getPreviousStatus');

    Mail::shouldReceive('to')->with('test@example.com')->andReturnSelf()->once();
    Mail::shouldReceive('send')->with(Mockery::type(SendInvoiceMail::class))->once();

    Mockery::mock('overload:Crater\Mail\SendInvoiceMail', function (MockInterface $mock) {
        $mock->shouldReceive('__construct')->with(['prepared_data'])->once();
    });

    $result = $invoice->send([]);

    expect($result)->toMatchArray([
        'success' => true,
        'type' => 'send',
    ]);
    expect($invoice->status)->toBe(Invoice::STATUS_SENT);
});

test('createItems creates items with correct calculated values and nested taxes/custom fields', function () {
    $mockInvoice = Mockery::mock(Invoice::class)->makePartial();
    $mockInvoice->company_id = 1;
    $mockInvoice->exchange_rate = 1.5;
    $mockInvoice->currency_id = 2;

    $mockItemsRelation = Mockery::mock(HasMany::class);
    $mockInvoice->shouldReceive('items')->andReturn($mockItemsRelation);

    $invoiceItems = [
        [
            'price' => 10, 'quantity' => 1, 'total' => 10, 'tax' => 1, 'discount_val' => 0.5,
            'recurring_invoice_id' => 99,
            'taxes' => [
                ['name' => 'Sales Tax', 'amount' => 1, 'tax_type_id' => 1],
                ['name' => 'VAT', 'amount' => null, 'tax_type_id' => 2],
            ],
            'custom_fields' => ['item_field' => 'item_value'],
        ],
    ];

    $expectedItemData = [
        'company_id' => 1,
        'exchange_rate' => 1.5,
        'base_price' => 15.0,
        'base_discount_val' => 0.75,
        'base_tax' => 1.5,
        'base_total' => 15.0,
        'price' => 10, 'quantity' => 1, 'total' => 10, 'tax' => 1, 'discount_val' => 0.5,
    ];

    $mockCreatedItem = Mockery::mock(InvoiceItem::class)->makePartial();
    $mockCreatedItem->shouldReceive('taxes')->andReturn(Mockery::mock(HasMany::class));
    $mockCreatedItem->shouldReceive('addCustomFields')->with(['item_field' => 'item_value'])->once();

    $mockItemsRelation->shouldReceive('create')
        ->withArgs(function ($item) use ($expectedItemData) {
            unset($item['recurring_invoice_id']);
            unset($item['taxes']);
            unset($item['custom_fields']);
            return $item == $expectedItemData;
        })
        ->andReturn($mockCreatedItem)
        ->once();

    $expectedTaxData = [
        'company_id' => 1,
        'exchange_rate' => 1.5,
        'base_amount' => 1.5,
        'currency_id' => 2,
        'name' => 'Sales Tax', 'amount' => 1, 'tax_type_id' => 1,
    ];
    $mockCreatedItem->taxes()->shouldReceive('create')
        ->withArgs(function ($tax) use ($expectedTaxData) {
            unset($tax['recurring_invoice_id']);
            return $tax == $expectedTaxData;
        })
        ->once();

    Invoice::createItems($mockInvoice, $invoiceItems);
});

test('createItems handles item with no taxes or custom fields', function () {
    $mockInvoice = Mockery::mock(Invoice::class)->makePartial();
    $mockInvoice->company_id = 1;
    $mockInvoice->exchange_rate = 1.0;
    $mockInvoice->currency_id = 1;

    $mockItemsRelation = Mockery::mock(HasMany::class);
    $mockInvoice->shouldReceive('items')->andReturn($mockItemsRelation);

    $invoiceItems = [
        [
            'price' => 10, 'quantity' => 1, 'total' => 10, 'tax' => 0, 'discount_val' => 0,
        ],
    ];

    $mockCreatedItem = Mockery::mock(InvoiceItem::class)->makePartial();
    $mockCreatedItem->shouldNotReceive('taxes');
    $mockCreatedItem->shouldNotReceive('addCustomFields');

    $mockItemsRelation->shouldReceive('create')
        ->andReturn($mockCreatedItem)
        ->once();

    Invoice::createItems($mockInvoice, $invoiceItems);
});

test('createTaxes creates invoice taxes with correct calculated values', function () {
    $mockInvoice = Mockery::mock(Invoice::class)->makePartial();
    $mockInvoice->company_id = 1;
    $mockInvoice->exchange_rate = 2.0;
    $mockInvoice->currency_id = 3;

    $mockTaxesRelation = Mockery::mock(HasMany::class);
    $mockInvoice->shouldReceive('taxes')->andReturn($mockTaxesRelation);

    $taxes = [
        [
            'name' => 'GST', 'amount' => 5, 'recurring_invoice_id' => 100,
        ],
        [
            'name' => 'PST', 'amount' => null,
        ],
    ];

    $expectedTaxData = [
        'company_id' => 1,
        'exchange_rate' => 2.0,
        'base_amount' => 10.0,
        'currency_id' => 3,
        'name' => 'GST', 'amount' => 5,
    ];

    $mockTaxesRelation->shouldReceive('create')
        ->withArgs(function ($tax) use ($expectedTaxData) {
            unset($tax['recurring_invoice_id']);
            return $tax == $expectedTaxData;
        })
        ->once();

    Invoice::createTaxes($mockInvoice, $taxes);
});

test('getPDFData generates PDF data with itemized taxes', function () {
    $company = new Company(['id' => 1, 'logo_path' => 'logo.png']);
    $customer = new Customer(['id' => 1]);

    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->id = 1;
    $invoice->company_id = 1;
    $invoice->tax_per_item = 'YES';
    $invoice->template_name = 'default';
    $invoice->customer = $customer;
    $invoice->company = $company;

    $tax1 = new Tax(['tax_type_id' => 1, 'amount' => 10]);
    $tax2 = new Tax(['tax_type_id' => 2, 'amount' => 5]);
    $tax3 = new Tax(['tax_type_id' => 1, 'amount' => 8]);

    $item1 = new InvoiceItem();
    $item1->setRelation('taxes', collect([$tax1, $tax2]));
    $item2 = new InvoiceItem();
    $item2->setRelation('taxes', collect([$tax3]));

    $invoice->items = collect([$item1, $item2]);

    Mockery::mock('alias:Crater\Models\Company')
        ->shouldReceive('find')
        ->with($invoice->company_id)
        ->andReturn($company)
        ->once();

    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('getSetting')
        ->with('language', $company->id)
        ->andReturn('en')
        ->once();

    Mockery::mock('alias:Crater\Models\CustomField')
        ->shouldReceive('where')
        ->with('model_type', 'Item')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn(collect(['custom_field_mock']))
        ->once();

    App::shouldReceive('setLocale')->with('en')->once();

    $invoice->shouldReceive('getCompanyAddress')->andReturn('Company Address')->once();
    $invoice->shouldReceive('getCustomerShippingAddress')->andReturn('Shipping Address')->once();
    $invoice->shouldReceive('getCustomerBillingAddress')->andReturn('Billing Address')->once();
    $invoice->shouldReceive('getNotes')->andReturn('Notes')->once();

    View::shouldReceive('share')
        ->withArgs(function ($sharedData) use ($invoice) {
            expect($sharedData['invoice'])->toBe($invoice);
            expect($sharedData['customFields'])->toEqual(collect(['custom_field_mock']));
            expect($sharedData['company_address'])->toBe('Company Address');
            expect($sharedData['shipping_address'])->toBe('Shipping Address');
            expect($sharedData['billing_address'])->toBe('Billing Address');
            expect($sharedData['notes'])->toBe('Notes');
            expect($sharedData['logo'])->toBe('logo.png');
            expect($sharedData['taxes']->count())->toBe(2);
            expect($sharedData['taxes'][0]->tax_type_id)->toBe(1);
            expect($sharedData['taxes'][0]->amount)->toBe(18);
            expect($sharedData['taxes'][1]->tax_type_id)->toBe(2);
            expect($sharedData['taxes'][1]->amount)->toBe(5);
            return true;
        })
        ->once();

    RequestFacade::shouldReceive('has')->with('preview')->andReturn(false);
    PDF::shouldReceive('loadView')->with('app.pdf.invoice.default')->andReturn('PDF Instance')->once();

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('find')
        ->with($invoice->id)
        ->andReturn($invoice)
        ->once();


    $result = $invoice->getPDFData();
    expect($result)->toBe('PDF Instance');
});

test('getPDFData generates PDF data without itemized taxes', function () {
    $company = new Company(['id' => 1, 'logo_path' => 'logo.png']);
    $customer = new Customer(['id' => 1]);

    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->id = 1;
    $invoice->company_id = 1;
    $invoice->tax_per_item = 'NO';
    $invoice->template_name = 'clean';
    $invoice->customer = $customer;
    $invoice->company = $company;

    $invoice->items = collect([]);

    Mockery::mock('alias:Crater\Models\Company')
        ->shouldReceive('find')
        ->andReturn($company);
    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('getSetting')
        ->andReturn('en');
    Mockery::mock('alias:Crater\Models\CustomField')
        ->shouldReceive('where->get')
        ->andReturn(collect());

    App::shouldReceive('setLocale')->once();

    $invoice->shouldReceive('getCompanyAddress')->andReturn('Company Address');
    $invoice->shouldReceive('getCustomerShippingAddress')->andReturn('Shipping Address');
    $invoice->shouldReceive('getCustomerBillingAddress')->andReturn('Billing Address');
    $invoice->shouldReceive('getNotes')->andReturn('Notes');

    View::shouldReceive('share')
        ->withArgs(function ($sharedData) {
            expect($sharedData['taxes'])->toBeInstanceOf(Collection::class);
            expect($sharedData['taxes']->isEmpty())->toBeTrue();
            return true;
        })
        ->once();

    RequestFacade::shouldReceive('has')->with('preview')->andReturn(true);
    PDF::shouldNotReceive('loadView');

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('find')
        ->with($invoice->id)
        ->andReturn($invoice)
        ->once();

    $result = $invoice->getPDFData();
    expect($result)->toBe(view('app.pdf.invoice.clean'));
});

test('getEmailAttachmentSetting returns false if setting is NO', function () {
    CompanySetting::shouldReceive('getSetting')
        ->with('invoice_email_attachment', 1)
        ->andReturn('NO')
        ->once();

    $invoice = new Invoice(['company_id' => 1]);
    expect($invoice->getEmailAttachmentSetting())->toBeFalse();
});

test('getEmailAttachmentSetting returns true if setting is not NO', function () {
    CompanySetting::shouldReceive('getSetting')
        ->with('invoice_email_attachment', 1)
        ->andReturn('YES')
        ->once();

    $invoice = new Invoice(['company_id' => 1]);
    expect($invoice->getEmailAttachmentSetting())->toBeTrue();
});

test('getCompanyAddress returns false if company or address does not exist', function () {
    $invoice = new Invoice(['company_id' => 1]);
    $invoice->company = null;
    expect($invoice->getCompanyAddress())->toBeFalse();

    $mockCompany = Mockery::mock(Company::class);
    $mockAddressRelation = Mockery::mock(BelongsTo::class);
    $mockAddressRelation->shouldReceive('exists')->andReturn(false)->once();
    $mockCompany->shouldReceive('address')->andReturn($mockAddressRelation);
    $invoice->company = $mockCompany;

    expect($invoice->getCompanyAddress())->toBeFalse();
});

test('getCompanyAddress returns formatted address string', function () {
    $mockCompany = Mockery::mock(Company::class);
    $mockAddressRelation = Mockery::mock(BelongsTo::class);
    $mockAddressRelation->shouldReceive('exists')->andReturn(true);
    $mockCompany->shouldReceive('address')->andReturn($mockAddressRelation);

    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->company_id = 1;
    $invoice->company = $mockCompany;
    $invoice->shouldReceive('getFormattedString')->with('Company Address Format')->andReturn('Formatted Company Address')->once();

    CompanySetting::shouldReceive('getSetting')
        ->with('invoice_company_address_format', 1)
        ->andReturn('Company Address Format')
        ->once();

    expect($invoice->getCompanyAddress())->toBe('Formatted Company Address');
});

test('getCustomerShippingAddress returns false if customer or shipping address does not exist', function () {
    $invoice = new Invoice(['customer_id' => 1]);
    $invoice->customer = null;
    expect($invoice->getCustomerShippingAddress())->toBeFalse();

    $mockCustomer = Mockery::mock(Customer::class);
    $mockShippingAddressRelation = Mockery::mock(BelongsTo::class);
    $mockShippingAddressRelation->shouldReceive('exists')->andReturn(false)->once();
    $mockCustomer->shouldReceive('shippingAddress')->andReturn($mockShippingAddressRelation);
    $invoice->customer = $mockCustomer;

    expect($invoice->getCustomerShippingAddress())->toBeFalse();
});

test('getCustomerShippingAddress returns formatted address string', function () {
    $mockCustomer = Mockery::mock(Customer::class);
    $mockShippingAddressRelation = Mockery::mock(BelongsTo::class);
    $mockShippingAddressRelation->shouldReceive('exists')->andReturn(true);
    $mockCustomer->shouldReceive('shippingAddress')->andReturn($mockShippingAddressRelation);

    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->company_id = 1;
    $invoice->customer = $mockCustomer;
    $invoice->shouldReceive('getFormattedString')->with('Shipping Address Format')->andReturn('Formatted Shipping Address')->once();

    CompanySetting::shouldReceive('getSetting')
        ->with('invoice_shipping_address_format', 1)
        ->andReturn('Shipping Address Format')
        ->once();

    expect($invoice->getCustomerShippingAddress())->toBe('Formatted Shipping Address');
});

test('getCustomerBillingAddress returns false if customer or billing address does not exist', function () {
    $invoice = new Invoice(['customer_id' => 1]);
    $invoice->customer = null;
    expect($invoice->getCustomerBillingAddress())->toBeFalse();

    $mockCustomer = Mockery::mock(Customer::class);
    $mockBillingAddressRelation = Mockery::mock(BelongsTo::class);
    $mockBillingAddressRelation->shouldReceive('exists')->andReturn(false)->once();
    $mockCustomer->shouldReceive('billingAddress')->andReturn($mockBillingAddressRelation);
    $invoice->customer = $mockCustomer;

    expect($invoice->getCustomerBillingAddress())->toBeFalse();
});

test('getCustomerBillingAddress returns formatted address string', function () {
    $mockCustomer = Mockery::mock(Customer::class);
    $mockBillingAddressRelation = Mockery::mock(BelongsTo::class);
    $mockBillingAddressRelation->shouldReceive('exists')->andReturn(true);
    $mockCustomer->shouldReceive('billingAddress')->andReturn($mockBillingAddressRelation);

    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->company_id = 1;
    $invoice->customer = $mockCustomer;
    $invoice->shouldReceive('getFormattedString')->with('Billing Address Format')->andReturn('Formatted Billing Address')->once();

    CompanySetting::shouldReceive('getSetting')
        ->with('invoice_billing_address_format', 1)
        ->andReturn('Billing Address Format')
        ->once();

    expect($invoice->getCustomerBillingAddress())->toBe('Formatted Billing Address');
});

test('getNotes returns formatted notes string', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->notes = 'Some notes here {FIELD}';
    $invoice->shouldReceive('getFormattedString')->with('Some notes here {FIELD}')->andReturn('Formatted Notes')->once();

    expect($invoice->getNotes())->toBe('Formatted Notes');
});

test('getEmailString replaces placeholders and removes unmatched ones', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->shouldReceive('getFieldsArray')->andReturn([
        '{CUSTOMER_NAME}' => 'Test Customer',
        '{COMPANY_NAME}' => 'Test Company',
    ])->once();
    $invoice->shouldReceive('getExtraFields')->andReturn([
        '{INVOICE_NUMBER}' => 'INV-001',
    ])->once();

    $body = "Hello {CUSTOMER_NAME}, your invoice {INVOICE_NUMBER} from {COMPANY_NAME} is ready. Ignore {UNMATCHED_FIELD}.";
    $expected = "Hello Test Customer, your invoice INV-001 from Test Company is ready. Ignore .";

    expect($invoice->getEmailString($body))->toBe($expected);
});

test('getExtraFields returns correct array', function () {
    $invoice = new Invoice([
        'invoice_number' => 'INV-001',
        'reference_number' => 'REF-001',
        'formattedInvoiceDate' => '2023-01-01',
        'formattedDueDate' => '2023-01-31',
    ]);

    $expected = [
        '{INVOICE_DATE}' => '2023-01-01',
        '{INVOICE_DUE_DATE}' => '2023-01-31',
        '{INVOICE_NUMBER}' => 'INV-001',
        '{INVOICE_REF_NUMBER}' => 'REF-001',
    ];

    expect($invoice->getExtraFields())->toEqual($expected);
});

test('invoiceTemplates returns formatted list of templates', function () {
    $mockFiles = [
        '/app/pdf/invoice/template1.blade.php',
        '/app/pdf/invoice/template2.blade.php',
    ];

    Storage::shouldReceive('disk')->with('views')->andReturnSelf();
    Storage::shouldReceive('files')->with('/app/pdf/invoice')->andReturn($mockFiles)->once();

    Str::shouldReceive('before')->with('template1.blade.php', '.blade.php')->andReturn('template1')->once();
    Str::shouldReceive('before')->with('template2.blade.php', '.blade.php')->andReturn('template2')->once();

    if (!function_exists('vite_asset')) {
        function vite_asset($path) {
            return 'http://localhost/' . $path;
        }
    }

    $expected = [
        ['name' => 'template1', 'path' => 'http://localhost/img/PDF/template1.png'],
        ['name' => 'template2', 'path' => 'http://localhost/img/PDF/template2.png'],
    ];

    expect(Invoice::invoiceTemplates())->toEqual($expected);
});

test('addInvoicePayment updates due amount and calls changeInvoiceStatus', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->total = 200;
    $invoice->due_amount = 100;
    $invoice->exchange_rate = 2.0;
    $invoice->shouldReceive('changeInvoiceStatus')->with(150)->once();
    $invoice->shouldReceive('save');

    $invoice->addInvoicePayment(50);

    expect($invoice->due_amount)->toBe(150.0);
    expect($invoice->base_due_amount)->toBe(300.0);
});

test('subtractInvoicePayment updates due amount and calls changeInvoiceStatus', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->total = 200;
    $invoice->due_amount = 100;
    $invoice->exchange_rate = 2.0;
    $invoice->shouldReceive('changeInvoiceStatus')->with(50)->once();
    $invoice->shouldReceive('save');

    $invoice->subtractInvoicePayment(50);

    expect($invoice->due_amount)->toBe(50.0);
    expect($invoice->base_due_amount)->toBe(100.0);
});

test('changeInvoiceStatus returns error for negative amount', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->shouldNotReceive('save');

    $result = invokeMethod($invoice, 'changeInvoiceStatus', [-10]);
    expect($result)->toEqual(['error' => 'invalid_amount']);
});

test('changeInvoiceStatus sets status to COMPLETED and PAID when amount is zero', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->total = 100;
    $invoice->status = Invoice::STATUS_SENT;
    $invoice->paid_status = Invoice::STATUS_PARTIALLY_PAID;
    $invoice->overdue = true;
    $invoice->shouldReceive('save')->once();
    $invoice->shouldNotReceive('getPreviousStatus');

    invokeMethod($invoice, 'changeInvoiceStatus', [0]);

    expect($invoice->status)->toBe(Invoice::STATUS_COMPLETED);
    expect($invoice->paid_status)->toBe(Invoice::STATUS_PAID);
    expect($invoice->overdue)->toBeFalse();
});

test('changeInvoiceStatus sets status to UNPAID when amount equals total', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->total = 100;
    $invoice->status = Invoice::STATUS_SENT;
    $invoice->paid_status = Invoice::STATUS_PARTIALLY_PAID;
    $invoice->shouldReceive('save')->once();
    $invoice->shouldReceive('getPreviousStatus')->andReturn(Invoice::STATUS_VIEWED)->once();

    invokeMethod($invoice, 'changeInvoiceStatus', [100]);

    expect($invoice->status)->toBe(Invoice::STATUS_VIEWED);
    expect($invoice->paid_status)->toBe(Invoice::STATUS_UNPAID);
});

test('changeInvoiceStatus sets status to PARTIALLY_PAID when amount is between zero and total', function () {
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->total = 100;
    $invoice->status = Invoice::STATUS_SENT;
    $invoice->paid_status = Invoice::STATUS_UNPAID;
    $invoice->shouldReceive('save')->once();
    $invoice->shouldReceive('getPreviousStatus')->andReturn(Invoice::STATUS_SENT)->once();

    invokeMethod($invoice, 'changeInvoiceStatus', [50]);

    expect($invoice->status)->toBe(Invoice::STATUS_SENT);
    expect($invoice->paid_status)->toBe(Invoice::STATUS_PARTIALLY_PAID);
});

test('deleteInvoices deletes multiple invoices and their transactions', function () {
    $invoiceIds = [1, 2];

    $invoice1 = Mockery::mock(Invoice::class)->makePartial();
    $invoice1->shouldReceive('transactions')->andReturnSelf();
    $invoice1->shouldReceive('exists')->andReturn(true)->once();
    $invoice1->shouldReceive('delete')->once();
    $invoice1->shouldReceive('delete')->once();

    $invoice2 = Mockery::mock(Invoice::class)->makePartial();
    $invoice2->shouldReceive('transactions')->andReturnSelf();
    $invoice2->shouldReceive('exists')->andReturn(false)->once();
    $invoice2->shouldNotReceive('delete');
    $invoice2->shouldReceive('delete')->once();

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('find')
        ->with(1)
        ->andReturn($invoice1)
        ->once();
    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('find')
        ->with(2)
        ->andReturn($invoice2)
        ->once();

    $result = Invoice::deleteInvoices($invoiceIds);
    expect($result)->toBeTrue();
});

test('deleteInvoices handles non-existent invoices gracefully', function () {
    $invoiceIds = [1, 3];

    $invoice1 = Mockery::mock(Invoice::class)->makePartial();
    $invoice1->shouldReceive('transactions')->andReturnSelf();
    $invoice1->shouldReceive('exists')->andReturn(false);
    $invoice1->shouldReceive('delete')->once();

    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('find')
        ->with(1)
        ->andReturn($invoice1)
        ->once();
    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('find')
        ->with(3)
        ->andReturn(null)
        ->once();

    $result = Invoice::deleteInvoices($invoiceIds);
    expect($result)->toBeTrue();
});




afterEach(function () {
    Mockery::close();
});
