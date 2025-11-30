<?php

use Carbon\Carbon;
use Crater\Models\Invoice;
use Crater\Models\InvoiceItem;
use Crater\Models\Item;
use Crater\Models\RecurringInvoice;
use Crater\Models\Tax;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Mockery as m;

// Ensure Mockery is closed after each test
afterEach(fn () => m::close());

test('invoice relationship returns belongsTo Invoice', function () {
    $item = m::mock(InvoiceItem::class)->makePartial();
    $expectedRelation = m::mock(BelongsTo::class);

    $item->shouldReceive('belongsTo')
        ->once()
        ->with(Invoice::class)
        ->andReturn($expectedRelation);

    expect($item->invoice())->toBe($expectedRelation);
});

test('item relationship returns belongsTo Item', function () {
    $item = m::mock(InvoiceItem::class)->makePartial();
    $expectedRelation = m::mock(BelongsTo::class);

    $item->shouldReceive('belongsTo')
        ->once()
        ->with(Item::class)
        ->andReturn($expectedRelation);

    expect($item->item())->toBe($expectedRelation);
});

test('taxes relationship returns hasMany Tax', function () {
    $item = m::mock(InvoiceItem::class)->makePartial();
    $expectedRelation = m::mock(HasMany::class);

    $item->shouldReceive('hasMany')
        ->once()
        ->with(Tax::class)
        ->andReturn($expectedRelation);

    expect($item->taxes())->toBe($expectedRelation);
});

test('recurringInvoice relationship returns belongsTo RecurringInvoice', function () {
    $item = m::mock(InvoiceItem::class)->makePartial();
    $expectedRelation = m::mock(BelongsTo::class);

    $item->shouldReceive('belongsTo')
        ->once()
        ->with(RecurringInvoice::class)
        ->andReturn($expectedRelation);

    expect($item->recurringInvoice())->toBe($expectedRelation);
});

test('scopeWhereCompany applies company_id filter', function () {
    $query = m::mock(Builder::class);
    $companyId = 123;

    $query->shouldReceive('where')
        ->once()
        ->with('company_id', $companyId)
        ->andReturnSelf();

    InvoiceItem::scopeWhereCompany($query, $companyId);
});

test('scopeInvoicesBetween applies date range filter using whereHas and whereBetween', function () {
    $query = m::mock(Builder::class);
    $startDate = Carbon::create(2023, 1, 1);
    $endDate = Carbon::create(2023, 1, 31);

    $innerQuery = m::mock(Builder::class);

    $query->shouldReceive('whereHas')
        ->once()
        ->with('invoice', m::on(function ($callback) use ($innerQuery, $startDate, $endDate) {
            $innerQuery->shouldReceive('whereBetween')
                ->once()
                ->with('invoice_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->andReturnSelf();

            $callback($innerQuery);
            return true;
        }))
        ->andReturnSelf();

    InvoiceItem::scopeInvoicesBetween($query, $startDate, $endDate);
});

test('scopeApplyInvoiceFilters calls invoicesBetween when both from_date and to_date are present', function () {
    $query = m::mock(Builder::class);
    $filters = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ];

    $mockStartDate = Carbon::create(2023, 1, 1);
    $mockEndDate = Carbon::create(2023, 1, 31);

    m::mock('overload:' . Carbon::class)
        ->shouldReceive('createFromFormat')
        ->with('Y-m-d', '2023-01-01')
        ->andReturn($mockStartDate)
        ->shouldReceive('createFromFormat')
        ->with('Y-m-d', '2023-01-31')
        ->andReturn($mockEndDate);

    $query->shouldReceive('invoicesBetween')
        ->once()
        ->with($mockStartDate, $mockEndDate)
        ->andReturnSelf();

    InvoiceItem::scopeApplyInvoiceFilters($query, $filters);
});

test('scopeApplyInvoiceFilters does not call invoicesBetween when from_date is missing', function () {
    $query = m::mock(Builder::class);
    $filters = [
        'to_date' => '2023-01-31',
    ];

    $query->shouldNotReceive('invoicesBetween');

    InvoiceItem::scopeApplyInvoiceFilters($query, $filters);
});

test('scopeApplyInvoiceFilters does not call invoicesBetween when to_date is missing', function () {
    $query = m::mock(Builder::class);
    $filters = [
        'from_date' => '2023-01-01',
    ];

    $query->shouldNotReceive('invoicesBetween');

    InvoiceItem::scopeApplyInvoiceFilters($query, $filters);
});

test('scopeApplyInvoiceFilters does not call invoicesBetween when both dates are missing', function () {
    $query = m::mock(Builder::class);
    $filters = [];

    $query->shouldNotReceive('invoicesBetween');

    InvoiceItem::scopeApplyInvoiceFilters($query, $filters);
});

test('scopeItemAttributes applies select with DB::raw and groupBy clauses', function () {
    $query = m::mock(Builder::class);
    $rawSql = 'sum(quantity) as total_quantity, sum(base_total) as total_amount, invoice_items.name';

    DB::shouldReceive('raw')
        ->once()
        ->with($rawSql)
        ->andReturnUsing(function ($sql) {
            return new class($sql) {
                public $value;
                public function __construct($value) { $this->value = $value; }
                public function __toString() { return $this->value; }
            };
        });

    $query->shouldReceive('select')
        ->once()
        ->with(m::on(function ($argument) use ($rawSql) {
            return (string) $argument === $rawSql;
        }))
        ->andReturnSelf();

    $query->shouldReceive('groupBy')
        ->once()
        ->with('invoice_items.name')
        ->andReturnSelf();

    InvoiceItem::scopeItemAttributes($query);
});
