```php
<?php

use Carbon\Carbon;
use Crater\Http\Requests\RecurringInvoiceRequest;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\ExchangeRateLog;
use Crater\Models\Invoice;
use Crater\Models\InvoiceItem;
use Crater\Models\RecurringInvoice;
use Crater\Models\Tax;
use Crater\Models\User;
use Crater\Services\SerialNumberFormatter;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery as m;
use Vinkla\Hashids\Facades\Hashids;

// Mock dependencies at the top level
beforeEach(function () {
    // These classes are used for static calls or are third-party and safe to alias mock.
    m::mock('alias:' . CompanySetting::class);
    m::mock('alias:' . ExchangeRateLog::class);
    m::mock('alias:' . Hashids::class);
    m::mock('alias:' . CronExpression::class);
    m::mock('alias:' . SerialNumberFormatter::class);
    m::mock('alias:' . Carbon::class); // For mocking Carbon::now() and Carbon::today()

    // IMPORTANT: Removed alias mocks for Eloquent models (Invoice, Customer, Company)
    // because they can cause "class already exists" errors when the RecurringInvoice
    // model (the SUT) is instantiated and its relationships implicitly load these classes.
    // Instead, static methods on these models will be mocked directly using ClassName::shouldReceive().
    // Relationships will be mocked on the RecurringInvoice instance itself.
});

afterEach(function () {
    m::close();
});

test('getFormattedStartsAtAttribute returns correctly formatted date', function () {
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', 1)
        ->andReturn('d/m/Y');

    $recurringInvoice = new RecurringInvoice([
        'starts_at' => '2023-01-15 10:00:00',
        'company_id' => 1
    ]);

    // Mock Carbon::parse to ensure consistency
    $carbonMock = m::mock(Carbon::class);
    $carbonMock->shouldReceive('format')->with('d/m/Y')->andReturn('15/01/2023');
    Carbon::shouldReceive('parse')->with('2023-01-15 10:00:00')->andReturn($carbonMock);

    expect($recurringInvoice->formattedStartsAt)->toBe('15/01/2023');
});

test('getFormattedNextInvoiceAtAttribute returns correctly formatted date', function () {
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', 1)
        ->andReturn('Y-m-d');

    $recurringInvoice = new RecurringInvoice([
        'next_invoice_at' => '2023-02-15 12:00:00',
        'company_id' => 1
    ]);

    $carbonMock = m::mock(Carbon::class);
    $carbonMock->shouldReceive('format')->with('Y-m-d')->andReturn('2023-02-15');
    Carbon::shouldReceive('parse')->with('2023-02-15 12:00:00')->andReturn($carbonMock);

    expect($recurringInvoice->formattedNextInvoiceAt)->toBe('2023-02-15');
});

test('getFormattedLimitDateAttribute returns correctly formatted date', function () {
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', 1)
        ->andReturn('m-d-Y');

    $recurringInvoice = new RecurringInvoice([
        'limit_date' => '2023-03-20 00:00:00',
        'company_id' => 1
    ]);

    $carbonMock = m::mock(Carbon::class);
    $carbonMock->shouldReceive('format')->with('m-d-Y')->andReturn('03-20-2023');
    Carbon::shouldReceive('parse')->with('2023-03-20 00:00:00')->andReturn($carbonMock);

    expect($recurringInvoice->formattedLimitDate)->toBe('03-20-2023');
});

test('getFormattedCreatedAtAttribute returns correctly formatted date', function () {
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', 1)
        ->andReturn('F j, Y');

    $recurringInvoice = new RecurringInvoice([
        'created_at' => '2022-12-01 08:30:00',
        'company_id' => 1
    ]);

    $carbonMock = m::mock(Carbon::class);
    $carbonMock->shouldReceive('format')->with('F j, Y')->andReturn('December 1, 2022');
    Carbon::shouldReceive('parse')->with('2022-12-01 08:30:00')->andReturn($carbonMock);

    expect($recurringInvoice->formattedCreatedAt)->toBe('December 1, 2022');
});

test('invoices relationship returns HasMany', function () {
    $recurringInvoice = new RecurringInvoice();
    expect($recurringInvoice->invoices())->toBeInstanceOf(HasMany::class);
    expect($recurringInvoice->invoices()->getRelated())->toBeInstanceOf(Invoice::class);
});

test('taxes relationship returns HasMany', function () {
    $recurringInvoice = new RecurringInvoice();
    expect($recurringInvoice->taxes())->toBeInstanceOf(HasMany::class);
    expect($recurringInvoice->taxes()->getRelated())->toBeInstanceOf(Tax::class);
});

test('items relationship returns HasMany', function () {
    $recurringInvoice = new RecurringInvoice();
    expect($recurringInvoice->items())->toBeInstanceOf(HasMany::class);
    expect($recurringInvoice->items()->getRelated())->toBeInstanceOf(InvoiceItem::class);
});

test('customer relationship returns BelongsTo', function () {
    $recurringInvoice = new RecurringInvoice();
    expect($recurringInvoice->customer())->toBeInstanceOf(BelongsTo::class);
    expect($recurringInvoice->customer()->getRelated())->toBeInstanceOf(Customer::class);
});

test('company relationship returns BelongsTo', function () {
    $recurringInvoice = new RecurringInvoice();
    expect($recurringInvoice->company())->toBeInstanceOf(BelongsTo::class);
    expect($recurringInvoice->company()->getRelated())->toBeInstanceOf(Company::class);
});

test('creator relationship returns BelongsTo', function () {
    $recurringInvoice = new RecurringInvoice();
    expect($recurringInvoice->creator())->toBeInstanceOf(BelongsTo::class);
    expect($recurringInvoice->creator()->getRelated())->toBeInstanceOf(User::class);
    expect($recurringInvoice->creator()->getForeignKeyName())->toBe('creator_id');
});

test('currency relationship returns BelongsTo', function () {
    $recurringInvoice = new RecurringInvoice();
    expect($recurringInvoice->currency())->toBeInstanceOf(BelongsTo::class);
    expect($recurringInvoice->currency()->getRelated())->toBeInstanceOf(Currency::class);
});

test('scopeWhereCompany applies company filter', function () {
    $query = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('where')->once()->with('recurring_invoices.company_id', 1)->andReturnSelf();

    // Mock request()->header('company')
    $requestMock = m::mock(Request::class);
    $requestMock->shouldReceive('header')->with('company')->andReturn(1);
    \Illuminate\Support\Facades\Request::swap($requestMock);

    $recurringInvoice = new RecurringInvoice();
    $recurringInvoice->scopeWhereCompany($query);
});

test('scopePaginateData returns all records when limit is all', function () {
    $query = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('get')->once()->andReturn(new Collection(['item1', 'item2']));

    $recurringInvoice = new RecurringInvoice();
    $result = $recurringInvoice->scopePaginateData($query, 'all');
    expect($result)->toBeInstanceOf(Collection::class);
});

test('scopePaginateData paginates records when limit is a number', function () {
    $query = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $paginator = m::mock(LengthAwarePaginator::class);
    $query->shouldReceive('paginate')->once()->with(10)->andReturn($paginator);

    $recurringInvoice = new RecurringInvoice();
    $result = $recurringInvoice->scopePaginateData($query, 10);
    expect($result)->toBe($paginator);
});

test('scopeWhereOrder applies order by', function () {
    $query = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('orderBy')->once()->with('name', 'asc')->andReturnSelf();

    $recurringInvoice = new RecurringInvoice();
    $recurringInvoice->scopeWhereOrder($query, 'name', 'asc');
});

test('scopeWhereStatus applies status filter', function () {
    $query = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('where')->once()->with('recurring_invoices.status', 'ACTIVE')->andReturnSelf();

    $recurringInvoice = new RecurringInvoice();
    $recurringInvoice->scopeWhereStatus($query, 'ACTIVE');
});

test('scopeWhereCustomer applies customer filter', function () {
    $query = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('where')->once()->with('customer_id', 5)->andReturnSelf();

    $recurringInvoice = new RecurringInvoice();
    $recurringInvoice->scopeWhereCustomer($query, 5);
});

test('scopeRecurringInvoicesStartBetween applies date range filter', function () {
    $query = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $start = Carbon::create(2023, 1, 1);
    $end = Carbon::create(2023, 1, 31);
    $query->shouldReceive('whereBetween')->once()->with('starts_at', ['2023-01-01', '2023-01-31'])->andReturnSelf();

    $recurringInvoice = new RecurringInvoice();
    $recurringInvoice->scopeRecurringInvoicesStartBetween($query, $start, $end);
});

test('scopeWhereSearch applies search filters on customer relations', function () {
    $query = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $innerQueryMock1 = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $innerQueryMock2 = m::mock(\Illuminate\Database\Eloquent\Builder::class);

    $query->shouldReceive('whereHas')->times(2)
          ->andReturnUsing(function ($relation, $callback) use ($innerQueryMock1, $innerQueryMock2) {
              // Capture the callback and execute it with a mock inner query
              static $callCount = 0;
              $mock = (++$callCount == 1) ? $innerQueryMock1 : $innerQueryMock2;
              $callback($mock);
              return $query; // Return the outer query for chaining
          });

    $innerQueryMock1->shouldReceive('where')->once()->with('name', 'LIKE', '%term1%')->andReturnSelf();
    $innerQueryMock1->shouldReceive('orWhere')->once()->with('contact_name', 'LIKE', '%term1%')->andReturnSelf();
    $innerQueryMock1->shouldReceive('orWhere')->once()->with('company_name', 'LIKE', '%term1%')->andReturnSelf();

    $innerQueryMock2->shouldReceive('where')->once()->with('name', 'LIKE', '%term2%')->andReturnSelf();
    $innerQueryMock2->shouldReceive('orWhere')->once()->with('contact_name', 'LIKE', '%term2%')->andReturnSelf();
    $innerQueryMock2->shouldReceive('orWhere')->once()->with('company_name', 'LIKE', '%term2%')->andReturnSelf();

    $recurringInvoice = new RecurringInvoice();
    $recurringInvoice->scopeWhereSearch($query, 'term1 term2');
});


test('scopeApplyFilters applies all available filters', function () {
    $query = m::mock(\Illuminate\Database\Eloquent\Builder::class);

    // Mock Carbon for date filters
    $mockStartCarbon = m::mock(Carbon::class);
    $mockEndCarbon = m::mock(Carbon::class);
    Carbon::shouldReceive('createFromFormat')
        ->once()->with('Y-m-d', '2023-01-01')->andReturn($mockStartCarbon);
    Carbon::shouldReceive('createFromFormat')
        ->once()->with('Y-m-d', '2023-01-31')->andReturn($mockEndCarbon);

    // Expect calls to specific scopes
    $query->shouldReceive('whereStatus')->once()->with('ACTIVE')->andReturnSelf();
    $query->shouldReceive('whereSearch')->once()->with('search term')->andReturnSelf();
    $query->shouldReceive('recurringInvoicesStartBetween')->once()->with($mockStartCarbon, $mockEndCarbon)->andReturnSelf();
    $query->shouldReceive('whereCustomer')->once()->with(123)->andReturnSelf();
    $query->shouldReceive('whereOrder')->once()->with('created_at', 'desc')->andReturnSelf(); // Default field and specified order

    $filters = [
        'status' => 'ACTIVE',
        'search' => 'search term',
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'customer_id' => 123,
        'orderBy' => 'desc',
    ];

    $recurringInvoice = new RecurringInvoice();
    $recurringInvoice->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters handles default order by field and order', function () {
    $query = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('whereOrder')->once()->with('created_at', 'asc')->andReturnSelf();

    $filters = [
        'orderByField' => null, // Test default field
        'orderBy' => null // Test default order
    ];

    $recurringInvoice = new RecurringInvoice();
    $recurringInvoice->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters handles empty filters', function () {
    $query = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldNotReceive('whereStatus');
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('recurringInvoicesStartBetween');
    $query->shouldNotReceive('whereCustomer');
    $query->shouldNotReceive('whereOrder');

    $filters = [];

    $recurringInvoice = new RecurringInvoice();
    $recurringInvoice->scopeApplyFilters($query, $filters);
});

test('createFromRequest creates recurring invoice and handles dependencies', function () {
    $request = m::mock(RecurringInvoiceRequest::class);
    // Create a partial mock for RecurringInvoice, allowing real methods but mocking others
    $recurringInvoiceMock = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoiceMock->id = 1;
    $recurringInvoiceMock->company_id = 1;
    $recurringInvoiceMock['currency_id'] = 1; // Explicitly set for comparison

    $payload = ['field' => 'value', 'currency_id' => 1];
    $request->shouldReceive('getRecurringInvoicePayload')->andReturn($payload);
    $request->shouldReceive('header')->with('company')->andReturn(1); // Company ID for settings
    $request->shouldReceive('items')->andReturn([['item1'], ['item2']]);
    $request->shouldReceive('has')->with('taxes')->andReturn(true);
    $request->shouldReceive('taxes')->andReturn([['tax1'], ['tax2']]);
    $request->shouldReceive('customFields')->andReturn([['field_id' => 1, 'value' => 'test']]);

    // Mock static methods directly on the class
    RecurringInvoice::shouldReceive('create')->once()->with($payload)->andReturn($recurringInvoiceMock);
    CompanySetting::shouldReceive('getSetting')->with('currency', 1)->andReturn(1); // Same currency
    ExchangeRateLog::shouldNotReceive('addExchangeRateLog'); // Should not be called
    RecurringInvoice::shouldReceive('createItems')->once()->with($recurringInvoiceMock, [['item1'], ['item2']]);
    RecurringInvoice::shouldReceive('createTaxes')->once()->with($recurringInvoiceMock, [['tax1'], ['tax2']]);

    // Mock addCustomFields trait method (if it exists)
    $recurringInvoiceMock->shouldReceive('addCustomFields')->once()->with([['field_id' => 1, 'value' => 'test']]);

    $result = RecurringInvoice::createFromRequest($request);

    expect($result)->toBe($recurringInvoiceMock);
});

test('createFromRequest calls ExchangeRateLog if currency changes', function () {
    $request = m::mock(RecurringInvoiceRequest::class);
    $recurringInvoiceMock = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoiceMock->id = 1;
    $recurringInvoiceMock->company_id = 1;
    $recurringInvoiceMock['currency_id'] = 2; // Different currency

    $payload = ['field' => 'value', 'currency_id' => 2];
    $request->shouldReceive('getRecurringInvoicePayload')->andReturn($payload);
    $request->shouldReceive('header')->with('company')->andReturn(1);
    $request->shouldReceive('items')->andReturn([]);
    $request->shouldReceive('has')->with('taxes')->andReturn(false);
    $request->shouldReceive('customFields')->andReturn(null);

    RecurringInvoice::shouldReceive('create')->once()->with($payload)->andReturn($recurringInvoiceMock);
    CompanySetting::shouldReceive('getSetting')->with('currency', 1)->andReturn(1); // Company currency is 1
    ExchangeRateLog::shouldReceive('addExchangeRateLog')->once()->with($recurringInvoiceMock);
    RecurringInvoice::shouldReceive('createItems')->once()->with($recurringInvoiceMock, []);
    RecurringInvoice::shouldNotReceive('createTaxes');

    $result = RecurringInvoice::createFromRequest($request);

    expect($result)->toBe($recurringInvoiceMock);
});

test('updateFromRequest updates recurring invoice and handles dependencies', function () {
    $request = m::mock(RecurringInvoiceRequest::class);
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->id = 1;
    $recurringInvoice->company_id = 1;
    $recurringInvoice->currency_id = 1; // Existing currency

    $payload = ['field' => 'updated_value', 'currency_id' => 2]; // New currency
    $request->shouldReceive('getRecurringInvoicePayload')->andReturn($payload);
    $request->shouldReceive('header')->with('company')->andReturn(1);
    $request->shouldReceive('items')->andReturn([['item1'], ['item2']]);
    $request->shouldReceive('has')->with('taxes')->andReturn(true);
    $request->shouldReceive('taxes')->andReturn([['tax1'], ['tax2']]);
    $request->shouldReceive('customFields')->andReturn([['field_id' => 1, 'value' => 'updated']]);

    $recurringInvoice->shouldReceive('update')->once()->with($payload);
    CompanySetting::shouldReceive('getSetting')->with('currency', 1)->andReturn(1); // Company currency is 1
    ExchangeRateLog::shouldReceive('addExchangeRateLog')->once()->with($recurringInvoice);

    // Mock relationship methods
    $itemsRelationMock = m::mock(HasMany::class);
    $itemsRelationMock->shouldReceive('delete')->once();
    $recurringInvoice->shouldReceive('items')->andReturn($itemsRelationMock);

    $taxesRelationMock = m::mock(HasMany::class);
    $taxesRelationMock->shouldReceive('exists')->andReturn(true); // Ensure taxes()->delete() is called
    $taxesRelationMock->shouldReceive('delete')->once();
    $recurringInvoice->shouldReceive('taxes')->andReturn($taxesRelationMock);

    RecurringInvoice::shouldReceive('createItems')->once()->with($recurringInvoice, [['item1'], ['item2']]);
    RecurringInvoice::shouldReceive('createTaxes')->once()->with($recurringInvoice, [['tax1'], ['tax2']]);

    // Mock updateCustomFields trait method
    $recurringInvoice->shouldReceive('updateCustomFields')->once()->with([['field_id' => 1, 'value' => 'updated']]);

    $result = $recurringInvoice->updateFromRequest($request);

    expect($result)->toBe($recurringInvoice);
});

test('createItems creates invoice items and their taxes', function () {
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->company_id = 1;

    $itemRelationMock = m::mock(HasMany::class);
    $recurringInvoice->shouldReceive('items')->andReturn($itemRelationMock);

    $item1Mock = m::mock(InvoiceItem::class);
    $item1Mock->shouldReceive('taxes')->andReturn(m::mock(HasMany::class)->shouldReceive('create')->once()->with(['name' => 'Tax1', 'amount' => 10.0, 'company_id' => 1])->getMock());

    $item2Mock = m::mock(InvoiceItem::class);
    // For 'NULL' amount, taxes should not be created. Mock should not receive 'create'.
    $item2Mock->shouldReceive('taxes')->andReturn(m::mock(HasMany::class)->shouldNotReceive('create')->getMock());

    // Mock InvoiceItem instances that will be returned by ->create()
    $mockItemForCreate1 = m::mock(InvoiceItem::class);
    $mockItemForCreate1->shouldReceive('taxes')->andReturn(m::mock(HasMany::class)->shouldReceive('create')->once()->with(['name' => 'Tax1', 'amount' => 10.0, 'company_id' => 1])->getMock());

    $mockItemForCreate2 = m::mock(InvoiceItem::class);
    $mockItemForCreate2->shouldReceive('taxes')->andReturn(m::mock(HasMany::class)->shouldNotReceive('create')->getMock());

    $mockItemForCreate3 = m::mock(InvoiceItem::class); // No taxes specified
    $mockItemForCreate3->shouldNotReceive('taxes');

    $invoiceItems = [
        ['name' => 'Item 1', 'taxes' => [['name' => 'Tax1', 'amount' => 10.0]]],
        ['name' => 'Item 2', 'taxes' => [['name' => 'Tax2', 'amount' => 'NULL']]],
        ['name' => 'Item 3']
    ];

    $itemRelationMock->shouldReceive('create')->once()->with(['name' => 'Item 1', 'taxes' => [['name' => 'Tax1', 'amount' => 10.0]], 'company_id' => 1])->andReturn($mockItemForCreate1);
    $itemRelationMock->shouldReceive('create')->once()->with(['name' => 'Item 2', 'taxes' => [['name' => 'Tax2', 'amount' => 'NULL']], 'company_id' => 1])->andReturn($mockItemForCreate2);
    $itemRelationMock->shouldReceive('create')->once()->with(['name' => 'Item 3', 'company_id' => 1])->andReturn($mockItemForCreate3);

    RecurringInvoice::createItems($recurringInvoice, $invoiceItems);
});

test('createTaxes creates recurring invoice taxes', function () {
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->company_id = 1;

    $taxesRelationMock = m::mock(HasMany::class);
    $recurringInvoice->shouldReceive('taxes')->andReturn($taxesRelationMock);

    $taxes = [
        ['name' => 'Service Tax', 'amount' => 50.0],
        ['name' => 'VAT', 'amount' => 'NULL'], // This one should be skipped
        ['name' => 'Sales Tax', 'amount' => 25.0]
    ];

    $taxesRelationMock->shouldReceive('create')->once()->with(['name' => 'Service Tax', 'amount' => 50.0, 'company_id' => 1]);
    $taxesRelationMock->shouldReceive('create')->once()->with(['name' => 'Sales Tax', 'amount' => 25.0, 'company_id' => 1]);
    $taxesRelationMock->shouldNotReceive('create')->with(['name' => 'VAT', 'amount' => 'NULL', 'company_id' => 1]);

    RecurringInvoice::createTaxes($recurringInvoice, $taxes);
});

test('generateInvoice returns early if starts_at is in the future', function () {
    Carbon::shouldReceive('now')->andReturn(Carbon::create(2023, 1, 1));
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->starts_at = Carbon::create(2023, 1, 2); // Future date

    $recurringInvoice->shouldNotReceive('createInvoice');
    $recurringInvoice->shouldNotReceive('updateNextInvoiceDate');
    $recurringInvoice->shouldNotReceive('markStatusAsCompleted');

    $recurringInvoice->generateInvoice();
});

test('generateInvoice creates invoice and updates date when limit_by is DATE and not expired', function () {
    Carbon::shouldReceive('now')->andReturn(Carbon::create(2023, 1, 15));
    Carbon::shouldReceive('today')->andReturn(Carbon::create(2023, 1, 15)); // today() returns Carbon instance, not string
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->starts_at = Carbon::create(2023, 1, 1);
    $recurringInvoice->limit_by = RecurringInvoice::DATE;
    $recurringInvoice->limit_date = '2023-01-31'; // Future date

    $recurringInvoice->shouldReceive('createInvoice')->once();
    $recurringInvoice->shouldReceive('updateNextInvoiceDate')->once();
    $recurringInvoice->shouldNotReceive('markStatusAsCompleted');

    $recurringInvoice->generateInvoice();
});

test('generateInvoice marks as completed when limit_by is DATE and expired', function () {
    Carbon::shouldReceive('now')->andReturn(Carbon::create(2023, 1, 15));
    Carbon::shouldReceive('today')->andReturn(Carbon::create(2023, 1, 15)); // today() returns Carbon instance, not string
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->starts_at = Carbon::create(2023, 1, 1);
    $recurringInvoice->limit_by = RecurringInvoice::DATE;
    $recurringInvoice->limit_date = '2023-01-14'; // Past date

    $recurringInvoice->shouldNotReceive('createInvoice');
    $recurringInvoice->shouldNotReceive('updateNextInvoiceDate');
    $recurringInvoice->shouldReceive('markStatusAsCompleted')->once();

    $recurringInvoice->generateInvoice();
});

test('generateInvoice creates invoice and updates date when limit_by is COUNT and not reached', function () {
    Carbon::shouldReceive('now')->andReturn(Carbon::create(2023, 1, 15));
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->id = 1;
    $recurringInvoice->starts_at = Carbon::create(2023, 1, 1);
    $recurringInvoice->limit_by = RecurringInvoice::COUNT;
    $recurringInvoice->limit_count = 5;

    $invoiceQueryMock = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $invoiceQueryMock->shouldReceive('where')->once()->with('recurring_invoice_id', 1)->andReturnSelf();
    $invoiceQueryMock->shouldReceive('count')->once()->andReturn(3); // 3 < 5

    Invoice::shouldReceive('where')->once()->andReturn($invoiceQueryMock);

    $recurringInvoice->shouldReceive('createInvoice')->once();
    $recurringInvoice->shouldReceive('updateNextInvoiceDate')->once();
    $recurringInvoice->shouldNotReceive('markStatusAsCompleted');

    $recurringInvoice->generateInvoice();
});

test('generateInvoice marks as completed when limit_by is COUNT and reached', function () {
    Carbon::shouldReceive('now')->andReturn(Carbon::create(2023, 1, 15));
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->id = 1;
    $recurringInvoice->starts_at = Carbon::create(2023, 1, 1);
    $recurringInvoice->limit_by = RecurringInvoice::COUNT;
    $recurringInvoice->limit_count = 5;

    $invoiceQueryMock = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $invoiceQueryMock->shouldReceive('where')->once()->with('recurring_invoice_id', 1)->andReturnSelf();
    $invoiceQueryMock->shouldReceive('count')->once()->andReturn(5); // 5 >= 5

    Invoice::shouldReceive('where')->once()->andReturn($invoiceQueryMock);

    $recurringInvoice->shouldNotReceive('createInvoice');
    $recurringInvoice->shouldNotReceive('updateNextInvoiceDate');
    $recurringInvoice->shouldReceive('markStatusAsCompleted')->once();

    $recurringInvoice->generateInvoice();
});

test('generateInvoice creates invoice and updates date when limit_by is NONE', function () {
    Carbon::shouldReceive('now')->andReturn(Carbon::create(2023, 1, 15));
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->starts_at = Carbon::create(2023, 1, 1);
    $recurringInvoice->limit_by = RecurringInvoice::NONE;

    $recurringInvoice->shouldReceive('createInvoice')->once();
    $recurringInvoice->shouldReceive('updateNextInvoiceDate')->once();
    $recurringInvoice->shouldNotReceive('markStatusAsCompleted');

    $recurringInvoice->generateInvoice();
});

test('createInvoice generates a new invoice with all details', function () {
    // Mock Carbon static calls
    $today = Carbon::create(2023, 1, 15);
    $dueDate = Carbon::create(2023, 1, 22);
    Carbon::shouldReceive('today')->andReturn($today);
    $today->shouldReceive('format')->with('Y-m-d')->andReturn('2023-01-15');
    $today->shouldReceive('addDays')->with(7)->andReturn($dueDate);
    $dueDate->shouldReceive('format')->with('Y-m-d')->andReturn('2023-01-22');

    // Mock CompanySetting
    CompanySetting::shouldReceive('getSetting')->with('invoice_due_date_days', 1)->andReturn(7);
    CompanySetting::shouldReceive('getSetting')->with('invoice_mail_body', 1)->andReturn('Mail body');

    // Mock SerialNumberFormatter
    $serialFormatterMock = m::mock(SerialNumberFormatter::class);
    $serialFormatterMock->shouldReceive('setModel')->once()->andReturnSelf();
    $serialFormatterMock->shouldReceive('setCompany')->once()->with(1)->andReturnSelf();
    $serialFormatterMock->shouldReceive('setCustomer')->once()->with(10)->andReturnSelf();
    $serialFormatterMock->shouldReceive('setNextNumbers')->once()->andReturnSelf();
    $serialFormatterMock->shouldReceive('getNextNumber')->andReturn('INV-001');
    $serialFormatterMock->nextSequenceNumber = 1;
    $serialFormatterMock->nextCustomerSequenceNumber = 1;
    SerialNumberFormatter::shouldReceive('__construct')->andReturn($serialFormatterMock);

    // Mock Customer::find()
    $customerMock = m::mock(Customer::class);
    $customerMock->currency_id = 100;
    $customerMock->email = 'customer@example.com';
    $customerMock->shouldReceive('toArray')->andReturn(['id' => 10, 'email' => 'customer@example.com']);
    Customer::shouldReceive('find')->with(10)->andReturn($customerMock);

    // Mock Company::find()
    $companyMock = m::mock(Company::class);
    $companyMock->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test Company']);
    Company::shouldReceive('find')->with(1)->andReturn($companyMock);

    // Mock Hashids
    Hashids::shouldReceive('connection')->with(Invoice::class)->andReturn(m::mock()->shouldReceive('encode')->with(2)->andReturn('hash')->getMock());

    // Mock Invoice model (static create method and instance methods)
    $newInvoiceMock = m::mock(Invoice::class)->makePartial();
    $newInvoiceMock->id = 2; // ID assigned after creation
    $newInvoiceMock->shouldReceive('save')->once();
    $newInvoiceMock->shouldReceive('addCustomFields')->once()->with([['id' => 1, 'value' => 'answer']]);
    $newInvoiceMock->shouldReceive('send')->once()->with(m::subset([
        'to' => 'customer@example.com',
        'subject' => 'New Invoice',
        'invoice' => m::type('array'), // JsonResource would be converted to array
        'customer' => m::type('array'), // Model converted to array
        'company' => m::type('array'), // Model converted to array
    ]));

    Invoice::shouldReceive('create')->once()->andReturnUsing(function ($data) use ($newInvoiceMock, $today, $dueDate, $serialFormatterMock) {
        expect($data['creator_id'])->toBe(1);
        expect($data['invoice_date'])->toBe($today->format('Y-m-d'));
        expect($data['due_date'])->toBe($dueDate->format('Y-m-d'));
        expect($data['status'])->toBe(Invoice::STATUS_DRAFT);
        expect($data['company_id'])->toBe(1);
        expect($data['paid_status'])->toBe(Invoice::STATUS_UNPAID);
        expect($data['sub_total'])->toBe(100.0);
        expect($data['tax_per_item'])->toBe(true);
        expect($data['discount_per_item'])->toBe(false);
        expect($data['tax'])->toBe(10.0);
        expect($data['total'])->toBe(110.0);
        expect($data['customer_id'])->toBe(10);
        expect($data['currency_id'])->toBe(100);
        expect($data['template_name'])->toBe('default');
        expect($data['due_amount'])->toBe(110.0);
        expect($data['recurring_invoice_id'])->toBe(1);
        expect($data['discount_val'])->toBe(5.0);
        expect($data['discount'])->toBe(5.0);
        expect($data['discount_type'])->toBe('percent');
        expect($data['notes'])->toBe('Some notes');
        expect($data['exchange_rate'])->toBe(1.0);
        expect($data['sales_tax_type'])->toBe('exclusive');
        expect($data['sales_tax_address_type'])->toBe('billing');
        expect($data['invoice_number'])->toBe('INV-001');
        expect($data['sequence_number'])->toBe(1);
        expect($data['customer_sequence_number'])->toBe(1);
        expect($data['base_due_amount'])->toBe(110.0);
        expect($data['base_discount_val'])->toBe(5.0);
        expect($data['base_sub_total'])->toBe(100.0);
        expect($data['base_tax'])->toBe(10.0);
        expect($data['base_total'])->toBe(110.0);
        $newInvoiceMock->unique_hash = 'hash'; // Set the hash generated by Hashids
        return $newInvoiceMock;
    });

    // Mock createItems and createTaxes static methods on Invoice
    Invoice::shouldReceive('createItems')->once()->with($newInvoiceMock, [['name' => 'Rec Item 1']]);
    Invoice::shouldReceive('createTaxes')->once()->with($newInvoiceMock, [['name' => 'Rec Tax 1']]);

    // Mock recurring invoice itself and its relations
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->id = 1;
    $recurringInvoice->creator_id = 1;
    $recurringInvoice->company_id = 1;
    $recurringInvoice->customer_id = 10;
    $recurringInvoice->sub_total = 100.0;
    $recurringInvoice->tax_per_item = true;
    $recurringInvoice->discount_per_item = false;
    $recurringInvoice->tax = 10.0;
    $recurringInvoice->total = 110.0;
    $recurringInvoice->template_name = 'default';
    $recurringInvoice->due_amount = 110.0;
    $recurringInvoice->discount_val = 5.0;
    $recurringInvoice->discount = 5.0;
    $recurringInvoice->discount_type = 'percent';
    $recurringInvoice->notes = 'Some notes';
    $recurringInvoice->exchange_rate = 1.0;
    $recurringInvoice->sales_tax_type = 'exclusive';
    $recurringInvoice->sales_tax_address_type = 'billing';
    $recurringInvoice->send_automatically = true;

    // Mock relation calls on recurringInvoice
    $itemsCollection = new Collection([['name' => 'Rec Item 1']]);
    $recurringInvoice->shouldReceive('load')->with('items.taxes')->andReturnSelf();
    // Directly assign property if the collection is loaded this way, or mock the method to return the relation
    $recurringInvoice->items = $itemsCollection;

    $taxesRelation = m::mock(HasMany::class);
    $taxesRelation->shouldReceive('exists')->andReturn(true);
    $taxesCollection = new Collection([['name' => 'Rec Tax 1']]);
    $taxesRelation->shouldReceive('toArray')->andReturn($taxesCollection->toArray());
    $recurringInvoice->shouldReceive('taxes')->andReturn($taxesRelation);

    $fieldsCollection = new Collection([
        (object)['custom_field_id' => 1, 'defaultAnswer' => 'answer']
    ]);
    $fieldsRelation = m::mock(HasMany::class);
    $fieldsRelation->shouldReceive('exists')->andReturn(true);
    $recurringInvoice->shouldReceive('fields')->andReturn($fieldsRelation);
    $recurringInvoice->fields = $fieldsCollection;

    $customerRelation = m::mock(BelongsTo::class);
    $customerRelation->shouldReceive('getResults')->andReturn($customerMock);
    $recurringInvoice->shouldReceive('customer')->andReturn($customerRelation);


    $recurringInvoice->createInvoice();
});

test('createInvoice does not send email if send_automatically is false', function () {
    // Mock Carbon static calls
    $today = Carbon::create(2023, 1, 15);
    $dueDate = Carbon::create(2023, 1, 22);
    Carbon::shouldReceive('today')->andReturn($today);
    $today->shouldReceive('format')->with('Y-m-d')->andReturn('2023-01-15');
    $today->shouldReceive('addDays')->with(7)->andReturn($dueDate);
    $dueDate->shouldReceive('format')->with('Y-m-d')->andReturn('2023-01-22');

    CompanySetting::shouldReceive('getSetting')->with('invoice_due_date_days', 1)->andReturn(7);

    $serialFormatterMock = m::mock(SerialNumberFormatter::class);
    $serialFormatterMock->shouldReceive('setModel')->andReturnSelf();
    $serialFormatterMock->shouldReceive('setCompany')->andReturnSelf();
    $serialFormatterMock->shouldReceive('setCustomer')->andReturnSelf();
    $serialFormatterMock->shouldReceive('setNextNumbers')->andReturnSelf();
    $serialFormatterMock->shouldReceive('getNextNumber')->andReturn('INV-001');
    $serialFormatterMock->nextSequenceNumber = 1;
    $serialFormatterMock->nextCustomerSequenceNumber = 1;
    SerialNumberFormatter::shouldReceive('__construct')->andReturn($serialFormatterMock);

    $customerMock = m::mock(Customer::class);
    $customerMock->currency_id = 100;
    $customerMock->email = 'customer@example.com'; // Needed for the send method if it were called
    Customer::shouldReceive('find')->with(10)->andReturn($customerMock);

    $companyMock = m::mock(Company::class); // Added mock for Company::find
    $companyMock->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test Company']);
    Company::shouldReceive('find')->with(1)->andReturn($companyMock);

    Hashids::shouldReceive('connection')->with(Invoice::class)->andReturn(m::mock()->shouldReceive('encode')->with(2)->andReturn('hash')->getMock());

    $newInvoiceMock = m::mock(Invoice::class)->makePartial();
    $newInvoiceMock->id = 2;
    $newInvoiceMock->shouldReceive('save')->once();
    $newInvoiceMock->shouldReceive('addCustomFields')->once();
    $newInvoiceMock->shouldNotReceive('send'); // Crucial for this test

    Invoice::shouldReceive('create')->once()->andReturn($newInvoiceMock);
    Invoice::shouldReceive('createItems')->once();
    Invoice::shouldReceive('createTaxes')->once();

    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->id = 1;
    $recurringInvoice->creator_id = 1;
    $recurringInvoice->company_id = 1;
    $recurringInvoice->customer_id = 10;
    $recurringInvoice->sub_total = 100.0;
    $recurringInvoice->tax_per_item = true;
    $recurringInvoice->discount_per_item = false;
    $recurringInvoice->tax = 10.0;
    $recurringInvoice->total = 110.0;
    $recurringInvoice->template_name = 'default';
    $recurringInvoice->due_amount = 110.0;
    $recurringInvoice->discount_val = 5.0;
    $recurringInvoice->discount = 5.0;
    $recurringInvoice->discount_type = 'percent';
    $recurringInvoice->notes = 'Some notes';
    $recurringInvoice->exchange_rate = 1.0;
    $recurringInvoice->sales_tax_type = 'exclusive';
    $recurringInvoice->sales_tax_address_type = 'billing';
    $recurringInvoice->send_automatically = false; // Important for this test

    $itemsCollection = new Collection([['name' => 'Rec Item 1']]);
    $recurringInvoice->shouldReceive('load')->with('items.taxes')->andReturnSelf();
    $recurringInvoice->items = $itemsCollection;

    $taxesRelation = m::mock(HasMany::class);
    $taxesRelation->shouldReceive('exists')->andReturn(true);
    $taxesCollection = new Collection([['name' => 'Rec Tax 1']]);
    $taxesRelation->shouldReceive('toArray')->andReturn($taxesCollection->toArray());
    $recurringInvoice->shouldReceive('taxes')->andReturn($taxesRelation);

    $fieldsCollection = new Collection([
        (object)['custom_field_id' => 1, 'defaultAnswer' => 'answer']
    ]);
    $fieldsRelation = m::mock(HasMany::class);
    $fieldsRelation->shouldReceive('exists')->andReturn(true);
    $recurringInvoice->shouldReceive('fields')->andReturn($fieldsRelation);
    $recurringInvoice->fields = $fieldsCollection;

    $customerRelation = m::mock(BelongsTo::class);
    $customerRelation->shouldReceive('getResults')->andReturn($customerMock);
    $recurringInvoice->shouldReceive('customer')->andReturn($customerRelation);

    $recurringInvoice->createInvoice();
});

test('markStatusAsCompleted sets status to COMPLETED and saves', function () {
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->status = RecurringInvoice::ACTIVE; // Initial status
    $recurringInvoice->shouldReceive('save')->once();

    $recurringInvoice->markStatusAsCompleted();

    expect($recurringInvoice->status)->toBe(RecurringInvoice::COMPLETED);
});

test('markStatusAsCompleted sets status to COMPLETED and saves even if already completed', function () {
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->status = RecurringInvoice::COMPLETED; // Already completed
    $recurringInvoice->shouldReceive('save')->once();

    $recurringInvoice->markStatusAsCompleted();

    expect($recurringInvoice->status)->toBe(RecurringInvoice::COMPLETED);
});

test('getNextInvoiceDate calculates and formats the next run date', function () {
    $frequency = '0 0 1 * *'; // Monthly
    $startsAt = Carbon::create(2023, 1, 15);
    $nextRunDate = Carbon::create(2023, 2, 1); // Expected next run date

    $cronExpressionMock = m::mock(CronExpression::class);
    $cronExpressionMock->shouldReceive('getNextRunDate')->with($startsAt)->andReturn($nextRunDate);
    CronExpression::shouldReceive('__construct')->with($frequency)->andReturn($cronExpressionMock);

    // Mock Carbon::format on the result
    // The actual Carbon instance returned by getNextRunDate needs to be mocked for its format method.
    $nextRunDate->shouldReceive('format')->with('Y-m-d H:i:s')->andReturn('2023-02-01 00:00:00');

    $result = RecurringInvoice::getNextInvoiceDate($frequency, $startsAt);
    expect($result)->toBe('2023-02-01 00:00:00');
});

test('updateNextInvoiceDate updates the next_invoice_at and saves', function () {
    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->frequency = '0 0 1 * *';
    $recurringInvoice->starts_at = Carbon::create(2023, 1, 15);

    $nextRunDateString = '2023-02-01 00:00:00';
    RecurringInvoice::shouldReceive('getNextInvoiceDate')
        ->once()
        ->with($recurringInvoice->frequency, $recurringInvoice->starts_at)
        ->andReturn($nextRunDateString);

    $recurringInvoice->shouldReceive('save')->once();

    $recurringInvoice->updateNextInvoiceDate();

    expect($recurringInvoice->next_invoice_at)->toBe($nextRunDateString);
});

test('deleteRecurringInvoice deletes multiple recurring invoices and related data', function () {
    $ids = [1, 2];

    // Mock for recurring invoice 1
    $recurringInvoice1 = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice1->id = 1;

    $invoicesRelation1 = m::mock(HasMany::class);
    $invoicesRelation1->shouldReceive('exists')->andReturn(true);
    $invoicesRelation1->shouldReceive('update')->once()->with(['recurring_invoice_id' => null]);
    $recurringInvoice1->shouldReceive('invoices')->andReturn($invoicesRelation1);

    $itemsRelation1 = m::mock(HasMany::class);
    $itemsRelation1->shouldReceive('exists')->andReturn(true);
    $itemsRelation1->shouldReceive('delete')->once();
    $recurringInvoice1->shouldReceive('items')->andReturn($itemsRelation1);

    $taxesRelation1 = m::mock(HasMany::class);
    $taxesRelation1->shouldReceive('exists')->andReturn(true);
    $taxesRelation1->shouldReceive('delete')->once();
    $recurringInvoice1->shouldReceive('taxes')->andReturn($taxesRelation1);

    $recurringInvoice1->shouldReceive('delete')->once();

    // Mock for recurring invoice 2 (no relations exist)
    $recurringInvoice2 = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice2->id = 2;

    $invoicesRelation2 = m::mock(HasMany::class);
    $invoicesRelation2->shouldReceive('exists')->andReturn(false);
    $invoicesRelation2->shouldNotReceive('update');
    $recurringInvoice2->shouldReceive('invoices')->andReturn($invoicesRelation2);

    $itemsRelation2 = m::mock(HasMany::class);
    $itemsRelation2->shouldReceive('exists')->andReturn(false);
    $itemsRelation2->shouldNotReceive('delete');
    $recurringInvoice2->shouldReceive('items')->andReturn($itemsRelation2);

    $taxesRelation2 = m::mock(HasMany::class);
    $taxesRelation2->shouldReceive('exists')->andReturn(false);
    $taxesRelation2->shouldNotReceive('delete');
    $recurringInvoice2->shouldReceive('taxes')->andReturn($taxesRelation2);

    $recurringInvoice2->shouldReceive('delete')->once();

    // Mock static find calls
    RecurringInvoice::shouldReceive('find')->with(1)->andReturn($recurringInvoice1);
    RecurringInvoice::shouldReceive('find')->with(2)->andReturn($recurringInvoice2);

    $result = RecurringInvoice::deleteRecurringInvoice($ids);

    expect($result)->toBeTrue();
});

// Added this to ensure that `Carbon::parse` actually gets mocked if called directly and not via `Carbon::createFromFormat`
test('Carbon::parse can be mocked globally', function () {
    CompanySetting::shouldReceive('getSetting')->andReturn('Y-m-d'); // Ensure CompanySetting is mocked
    Carbon::shouldReceive('parse')->andReturn(m::mock(Carbon::class)->shouldReceive('format')->andReturn('mocked date')->getMock());
    $recurringInvoice = new RecurringInvoice(['starts_at' => 'some date', 'company_id' => 1]);
    expect($recurringInvoice->formattedStartsAt)->toBe('mocked date');
});

// Test for due date days being null or "null" in createInvoice
test('createInvoice uses default due date days if company setting is null or "null"', function () {
    // Mock Carbon static calls
    $today = Carbon::create(2023, 1, 15);
    $dueDate = Carbon::create(2023, 1, 22); // 7 days later
    Carbon::shouldReceive('today')->andReturn($today);
    $today->shouldReceive('format')->with('Y-m-d')->andReturn('2023-01-15');
    $today->shouldReceive('addDays')->with(7)->andReturn($dueDate); // Expect 7 days
    $dueDate->shouldReceive('format')->with('Y-m-d')->andReturn('2023-01-22');

    // Mock CompanySetting to return null for invoice_due_date_days
    CompanySetting::shouldReceive('getSetting')->with('invoice_due_date_days', 1)->andReturn(null);
    CompanySetting::shouldReceive('getSetting')->with('invoice_mail_body', 1)->andReturn('Mail body');


    // Mock other dependencies as needed for createInvoice
    $serialFormatterMock = m::mock(SerialNumberFormatter::class);
    $serialFormatterMock->shouldReceive('setModel')->andReturnSelf();
    $serialFormatterMock->shouldReceive('setCompany')->andReturnSelf();
    $serialFormatterMock->shouldReceive('setCustomer')->andReturnSelf();
    $serialFormatterMock->shouldReceive('setNextNumbers')->andReturnSelf();
    $serialFormatterMock->shouldReceive('getNextNumber')->andReturn('INV-001');
    $serialFormatterMock->nextSequenceNumber = 1;
    $serialFormatterMock->nextCustomerSequenceNumber = 1;
    SerialNumberFormatter::shouldReceive('__construct')->andReturn($serialFormatterMock);

    $customerMock = m::mock(Customer::class);
    $customerMock->currency_id = 100;
    $customerMock->email = 'customer@example.com';
    $customerMock->shouldReceive('toArray')->andReturn(['id' => 10, 'email' => 'customer@example.com']);
    Customer::shouldReceive('find')->with(10)->andReturn($customerMock);

    $companyMock = m::mock(Company::class);
    $companyMock->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test Company']);
    Company::shouldReceive('find')->with(1)->andReturn($companyMock);

    Hashids::shouldReceive('connection')->with(Invoice::class)->andReturn(m::mock()->shouldReceive('encode')->with(2)->andReturn('hash')->getMock());

    $newInvoiceMock = m::mock(Invoice::class)->makePartial();
    $newInvoiceMock->id = 2;
    $newInvoiceMock->shouldReceive('save')->once();
    $newInvoiceMock->shouldReceive('addCustomFields')->once();
    $newInvoiceMock->shouldReceive('send')->once()->with(m::subset([
        'to' => 'customer@example.com',
        'subject' => 'New Invoice',
        'invoice' => m::type('array'),
        'customer' => m::type('array'),
        'company' => m::type('array'),
    ]));

    Invoice::shouldReceive('create')->once()->andReturnUsing(function ($data) use ($newInvoiceMock, $dueDate) {
        expect($data['due_date'])->toBe($dueDate->format('Y-m-d')); // Assert the correct due date
        $newInvoiceMock->unique_hash = 'hash'; // Set the hash generated by Hashids
        return $newInvoiceMock;
    });

    Invoice::shouldReceive('createItems')->once();
    Invoice::shouldReceive('createTaxes')->once();

    $recurringInvoice = m::mock(RecurringInvoice::class)->makePartial();
    $recurringInvoice->id = 1;
    $recurringInvoice->creator_id = 1;
    $recurringInvoice->company_id = 1;
    $recurringInvoice->customer_id = 10;
    $recurringInvoice->sub_total = 100.0;
    $recurringInvoice->tax_per_item = true;
    $recurringInvoice->discount_per_item = false;
    $recurringInvoice->tax = 10.0;
    $recurringInvoice->total = 110.0;
    $recurringInvoice->template_name = 'default';
    $recurringInvoice->due_amount = 110.0;
    $recurringInvoice->discount_val = 5.0;
    $recurringInvoice->discount = 5.0;
    $recurringInvoice->discount_type = 'percent';
    $recurringInvoice->notes = 'Some notes';
    $recurringInvoice->exchange_rate = 1.0;
    $recurringInvoice->sales_tax_type = 'exclusive';
    $recurringInvoice->sales_tax_address_type = 'billing';
    $recurringInvoice->send_automatically = true;

    $itemsCollection = new Collection([['name' => 'Rec Item 1']]);
    $recurringInvoice->shouldReceive('load')->with('items.taxes')->andReturnSelf();
    $recurringInvoice->items = $itemsCollection;

    $taxesRelation = m::mock(HasMany::class);
    $taxesRelation->shouldReceive('exists')->andReturn(true);
    $taxesCollection = new Collection([['name' => 'Rec Tax 1']]);
    $taxesRelation->shouldReceive('toArray')->andReturn($taxesCollection->toArray());
    $recurringInvoice->shouldReceive('taxes')->andReturn($taxesRelation);

    $fieldsCollection = new Collection([
        (object)['custom_field_id' => 1, 'defaultAnswer' => 'answer']
    ]);
    $fieldsRelation = m::mock(HasMany::class);
    $fieldsRelation->shouldReceive('exists')->andReturn(true);
    $recurringInvoice->shouldReceive('fields')->andReturn($fieldsRelation);
    $recurringInvoice->fields = $fieldsCollection;

    $customerRelation = m::mock(BelongsTo::class);
    $customerRelation->shouldReceive('getResults')->andReturn($customerMock);
    $recurringInvoice->shouldReceive('customer')->andReturn($customerRelation);


    $recurringInvoice->createInvoice();
});
```