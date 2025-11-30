<?php

use Carbon\Carbon;
use Crater\Models\Currency;
use Crater\Models\Estimate;
use Crater\Models\EstimateItem;
use Crater\Models\Invoice;
use Crater\Models\InvoiceItem;
use Crater\Models\Item;
use Crater\Models\RecurringInvoice;
use Crater\Models\Tax;
use Crater\Models\TaxType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

// Helper to create a Tax instance
function createTaxInstance(): Tax
{
    return new Tax();
}

test('taxType returns a belongsTo relationship', function () {
    $tax = createTaxInstance();
    $relation = $tax->taxType();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(TaxType::class);
    expect($relation->getForeignKeyName())->toBe('tax_type_id');
    expect($relation->getOwnerKeyName())->toBe('id');
});

test('invoice returns a belongsTo relationship', function () {
    $tax = createTaxInstance();
    $relation = $tax->invoice();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Invoice::class);
    expect($relation->getForeignKeyName())->toBe('invoice_id');
    expect($relation->getOwnerKeyName())->toBe('id');
});

test('recurringInvoice returns a belongsTo relationship', function () {
    $tax = createTaxInstance();
    $relation = $tax->recurringInvoice();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(RecurringInvoice::class);
    expect($relation->getForeignKeyName())->toBe('recurring_invoice_id');
    expect($relation->getOwnerKeyName())->toBe('id');
});

test('estimate returns a belongsTo relationship', function () {
    $tax = createTaxInstance();
    $relation = $tax->estimate();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Estimate::class);
    expect($relation->getForeignKeyName())->toBe('estimate_id');
    expect($relation->getOwnerKeyName())->toBe('id');
});

test('currency returns a belongsTo relationship', function () {
    $tax = createTaxInstance();
    $relation = $tax->currency();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Currency::class);
    expect($relation->getForeignKeyName())->toBe('currency_id');
    expect($relation->getOwnerKeyName())->toBe('id');
});

test('invoiceItem returns a belongsTo relationship', function () {
    $tax = createTaxInstance();
    $relation = $tax->invoiceItem();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(InvoiceItem::class);
    expect($relation->getForeignKeyName())->toBe('invoice_item_id');
    expect($relation->getOwnerKeyName())->toBe('id');
});

test('estimateItem returns a belongsTo relationship', function () {
    $tax = createTaxInstance();
    $relation = $tax->estimateItem();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(EstimateItem::class);
    expect($relation->getForeignKeyName())->toBe('estimate_item_id');
    expect($relation->getOwnerKeyName())->toBe('id');
});

test('item returns a belongsTo relationship', function () {
    $tax = createTaxInstance();
    $relation = $tax->item();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Item::class);
    expect($relation->getForeignKeyName())->toBe('item_id');
    expect($relation->getOwnerKeyName())->toBe('id');
});

test('scopeWhereCompany applies company_id filter', function () {
    $companyId = 123;

    $queryMock = Mockery::mock(Builder::class);
    $queryMock->shouldReceive('where')
        ->once()
        ->with('company_id', $companyId)
        ->andReturnSelf();

    $tax = createTaxInstance();
    $tax->scopeWhereCompany($queryMock, $companyId);
});

test('scopeTaxAttributes selects and groups by tax_type_id', function () {
    DB::shouldReceive('raw')
        ->once()
        ->with('sum(base_amount) as total_tax_amount, tax_type_id')
        ->andReturnUsing(fn($sql) => new Expression($sql));

    $queryMock = Mockery::mock(Builder::class);
    $queryMock->shouldReceive('select')
        ->once()
        ->andReturnUsing(function ($raw) use ($queryMock) {
            expect($raw)->toBeInstanceOf(Expression::class);
            expect($raw->getValue())->toBe('sum(base_amount) as total_tax_amount, tax_type_id');
            return $queryMock;
        });
    $queryMock->shouldReceive('groupBy')
        ->once()
        ->with('tax_type_id')
        ->andReturnSelf();

    $tax = createTaxInstance();
    $tax->scopeTaxAttributes($queryMock);
});

test('scopeInvoicesBetween filters by paid invoices within date range', function () {
    $startDate = Carbon::create(2023, 1, 1);
    $endDate = Carbon::create(2023, 1, 31);

    $queryMock = Mockery::mock(Builder::class);

    // Mock the first whereHas for 'invoice'
    $queryMock->shouldReceive('whereHas')
        ->once()
        ->with('invoice', Mockery::on(function ($callback) use ($startDate, $endDate) {
            $nestedQueryMock = Mockery::mock(Builder::class);
            $nestedQueryMock->shouldReceive('where')
                ->once()
                ->with('paid_status', Invoice::STATUS_PAID)
                ->andReturnSelf();
            $nestedQueryMock->shouldReceive('whereBetween')
                ->once()
                ->with('invoice_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->andReturnSelf();

            $callback($nestedQueryMock);
            return true;
        }))
        ->andReturnSelf();

    // Mock the orWhereHas for 'invoiceItem.invoice'
    $queryMock->shouldReceive('orWhereHas')
        ->once()
        ->with('invoiceItem.invoice', Mockery::on(function ($callback) use ($startDate, $endDate) {
            $nestedQueryMock = Mockery::mock(Builder::class);
            $nestedQueryMock->shouldReceive('where')
                ->once()
                ->with('paid_status', Invoice::STATUS_PAID)
                ->andReturnSelf();
            $nestedQueryMock->shouldReceive('whereBetween')
                ->once()
                ->with('invoice_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->andReturnSelf();

            $callback($nestedQueryMock);
            return true;
        }))
        ->andReturnSelf();

    $tax = createTaxInstance();
    $tax->scopeInvoicesBetween($queryMock, $startDate, $endDate);
});

test('scopeWhereInvoicesFilters calls invoicesBetween when both dates are provided', function () {
    $filters = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ];

    // Mock Carbon::createFromFormat for isolation and consistency
    Carbon::setTestNow(Carbon::create(2023, 1, 15)); // Set a stable "now" for any implicit Carbon usage if it occurred.

    $mockStartDate = Carbon::createFromFormat('Y-m-d', '2023-01-01');
    $mockEndDate = Carbon::createFromFormat('Y-m-d', '2023-01-31');

    $queryMock = Mockery::mock(Builder::class);

    // The scope calls $query->invoicesBetween, so the mocked builder needs this method.
    $queryMock->shouldReceive('invoicesBetween')
        ->once()
        ->withArgs(function ($start, $end) use ($mockStartDate, $mockEndDate) {
            return $start->equalTo($mockStartDate) && $end->equalTo($mockEndDate);
        })
        ->andReturnSelf();

    $tax = createTaxInstance();
    $tax->scopeWhereInvoicesFilters($queryMock, $filters);
});

test('scopeWhereInvoicesFilters does not call invoicesBetween when from_date is missing', function () {
    $filters = [
        'to_date' => '2023-01-31',
    ];

    $queryMock = Mockery::mock(Builder::class);

    $queryMock->shouldNotReceive('invoicesBetween');

    $tax = createTaxInstance();
    $tax->scopeWhereInvoicesFilters($queryMock, $filters);
});

test('scopeWhereInvoicesFilters does not call invoicesBetween when to_date is missing', function () {
    $filters = [
        'from_date' => '2023-01-01',
    ];

    $queryMock = Mockery::mock(Builder::class);

    $queryMock->shouldNotReceive('invoicesBetween');

    $tax = createTaxInstance();
    $tax->scopeWhereInvoicesFilters($queryMock, $filters);
});

test('scopeWhereInvoicesFilters does not call invoicesBetween when both dates are missing', function () {
    $filters = [];

    $queryMock = Mockery::mock(Builder::class);

    $queryMock->shouldNotReceive('invoicesBetween');

    $tax = createTaxInstance();
    $tax->scopeWhereInvoicesFilters($queryMock, $filters);
});

test('guarded properties are correctly configured', function () {
    $tax = new Tax();
    $guarded = $tax->getGuarded();

    expect($guarded)->toContain('id');
    expect(count($guarded))->toBe(1);
});

test('casts properties are correctly configured', function () {
    $tax = new Tax();
    $casts = $tax->getCasts();

    expect($casts)->toHaveKey('amount', 'integer');
    expect($casts)->toHaveKey('percent', 'float');
    expect(count($casts))->toBe(2);
});
