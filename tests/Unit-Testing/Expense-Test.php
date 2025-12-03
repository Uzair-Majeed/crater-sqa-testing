<?php

use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\Expense;
use Crater\Models\ExpenseCategory;
use Crater\Models\ExchangeRateLog;
use Crater\Models\PaymentMethod;
use Crater\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\HasMedia;

beforeEach(function () {
    // Ensure Mockery is closed after each test to prevent conflicts
    Mockery::close();
});

// Relationships
test('category relationship returns correct BelongsTo instance', function () {
    $expense = new Expense();
    $relation = $expense->category();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(ExpenseCategory::class)
        ->and($relation->getForeignKeyName())->toBe('expense_category_id');
});

test('customer relationship returns correct BelongsTo instance', function () {
    $expense = new Expense();
    $relation = $expense->customer();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Customer::class)
        ->and($relation->getForeignKeyName())->toBe('customer_id');
});

test('company relationship returns correct BelongsTo instance', function () {
    $expense = new Expense();
    $relation = $expense->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Company::class)
        ->and($relation->getForeignKeyName())->toBe('company_id');
});

test('paymentMethod relationship returns correct BelongsTo instance', function () {
    $expense = new Expense();
    $relation = $expense->paymentMethod();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(PaymentMethod::class)
        ->and($relation->getForeignKeyName())->toBe('payment_method_id');
});

test('currency relationship returns correct BelongsTo instance', function () {
    $expense = new Expense();
    $relation = $expense->currency();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Currency::class)
        ->and($relation->getForeignKeyName())->toBe('currency_id');
});

test('creator relationship returns correct BelongsTo instance', function () {
    $expense = new Expense();
    $relation = $expense->creator();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class)
        ->and($relation->getForeignKeyName())->toBe('creator_id');
});

// Accessors
test('getFormattedExpenseDateAttribute returns formatted date', function () {
    Carbon::setTestNow(Carbon::create(2023, 1, 15, 10, 0, 0));
    
    $expense = new Expense(['expense_date' => '2023-01-15', 'company_id' => 1]);

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', 1)
        ->andReturn('Y/m/d');

    expect($expense->formattedExpenseDate)->toBe('2023/01/15');
    
    Carbon::setTestNow();
});

test('getFormattedCreatedAtAttribute returns formatted date', function () {
    Carbon::setTestNow(Carbon::create(2023, 1, 15, 10, 0, 0));
    
    $expense = new Expense(['created_at' => '2023-01-15 10:00:00', 'company_id' => 1]);

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', 1)
        ->andReturn('d-m-Y H:i');

    expect($expense->formattedCreatedAt)->toBe('15-01-2023 10:00');

    Carbon::setTestNow();
});

test('getReceiptUrlAttribute returns url and type if media exists', function () {
    $expense = Mockery::mock(Expense::class . '[getFirstMedia]');
    $media = Mockery::mock(Media::class);

    $media->shouldReceive('getFullUrl')->andReturn('http://example.com/receipt.jpg');
    $media->shouldReceive('type')->andReturn('image/jpeg');

    $expense->shouldReceive('getFirstMedia')
        ->with('receipts')
        ->andReturn($media);

    expect($expense->receiptUrl)->toEqual([
        'url' => 'http://example.com/receipt.jpg',
        'type' => 'image/jpeg'
    ]);
});

test('getReceiptUrlAttribute returns null if no media exists', function () {
    $expense = Mockery::mock(Expense::class . '[getFirstMedia]');

    $expense->shouldReceive('getFirstMedia')
        ->with('receipts')
        ->andReturn(null);

    expect($expense->receiptUrl)->toBeNull();
});

test('getReceiptAttribute returns path if media exists', function () {
    $expense = Mockery::mock(Expense::class . '[getFirstMedia]');
    $media = Mockery::mock(Media::class);

    $media->shouldReceive('getPath')->andReturn('/path/to/receipt.jpg');

    $expense->shouldReceive('getFirstMedia')
        ->with('receipts')
        ->andReturn($media);

    expect($expense->receipt)->toBe('/path/to/receipt.jpg');
});

test('getReceiptAttribute returns null if no media exists', function () {
    $expense = Mockery::mock(Expense::class . '[getFirstMedia]');

    $expense->shouldReceive('getFirstMedia')
        ->with('receipts')
        ->andReturn(null);

    expect($expense->receipt)->toBeNull();
});

test('getReceiptMetaAttribute returns media object if media exists', function () {
    $expense = Mockery::mock(Expense::class . '[getFirstMedia]');
    $media = Mockery::mock(Media::class);

    $expense->shouldReceive('getFirstMedia')
        ->with('receipts')
        ->andReturn($media);

    expect($expense->receiptMeta)->toBe($media);
});

test('getReceiptMetaAttribute returns null if no media exists', function () {
    $expense = Mockery::mock(Expense::class . '[getFirstMedia]');

    $expense->shouldReceive('getFirstMedia')
        ->with('receipts')
        ->andReturn(null);

    expect($expense->receiptMeta)->toBeNull();
});

// Scopes
test('scopeExpensesBetween applies whereBetween clause', function () {
    $query = Mockery::mock(Builder::class);
    $start = Carbon::parse('2023-01-01');
    $end = Carbon::parse('2023-01-31');

    $query->shouldReceive('whereBetween')
        ->with('expenses.expense_date', ['2023-01-01', '2023-01-31'])
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeExpensesBetween($query, $start, $end);
});

test('scopeWhereCategoryName applies whereHas with like for single term', function () {
    $query = Mockery::mock(Builder::class);
    $innerQuery = Mockery::mock(Builder::class);

    $query->shouldReceive('whereHas')
        ->with('category', Mockery::on(function ($closure) use ($innerQuery) {
            $closure($innerQuery);
            return true;
        }))
        ->andReturnSelf()
        ->once();

    $innerQuery->shouldReceive('where')
        ->with('name', 'LIKE', '%Electronics%')
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeWhereCategoryName($query, 'Electronics');
});

test('scopeWhereCategoryName applies whereHas with like for multiple terms', function () {
    $query = Mockery::mock(Builder::class);
    $innerQuery1 = Mockery::mock(Builder::class);
    $innerQuery2 = Mockery::mock(Builder::class);

    $query->shouldReceive('whereHas')
        ->with('category', Mockery::on(function ($closure) use ($innerQuery1) {
            $closure($innerQuery1);
            return true;
        }))
        ->andReturnSelf()
        ->once();
    $query->shouldReceive('whereHas')
        ->with('category', Mockery::on(function ($closure) use ($innerQuery2) {
            $closure($innerQuery2);
            return true;
        }))
        ->andReturnSelf()
        ->once();

    $innerQuery1->shouldReceive('where')
        ->with('name', 'LIKE', '%Home%')
        ->andReturnSelf()
        ->once();
    $innerQuery2->shouldReceive('where')
        ->with('name', 'LIKE', '%Goods%')
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeWhereCategoryName($query, 'Home Goods');
});

test('scopeWhereNotes applies where clause for notes', function () {
    $query = Mockery::mock(Builder::class);

    $query->shouldReceive('where')
        ->with('notes', 'LIKE', '%some notes%')
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeWhereNotes($query, 'some notes');
});

test('scopeWhereCategory applies where clause for category id', function () {
    $query = Mockery::mock(Builder::class);

    $query->shouldReceive('where')
        ->with('expenses.expense_category_id', 1)
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeWhereCategory($query, 1);
});

test('scopeWhereUser applies where clause for customer id', function () {
    $query = Mockery::mock(Builder::class);

    $query->shouldReceive('where')
        ->with('expenses.customer_id', 1)
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeWhereUser($query, 1);
});

test('scopeApplyFilters applies category filter', function () {
    $query = Mockery::mock(Builder::class);
    $filters = ['expense_category_id' => 1];

    $query->shouldReceive('whereCategory')->with(1)->andReturnSelf()->once();
    $query->shouldNotReceive('whereUser');
    $query->shouldNotReceive('whereExpense');
    $query->shouldNotReceive('expensesBetween');
    $query->shouldNotReceive('whereOrder');
    $query->shouldNotReceive('whereSearch');


    $expense = new Expense();
    $expense->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters applies customer filter', function () {
    $query = Mockery::mock(Builder::class);
    $filters = ['customer_id' => 10];

    $query->shouldReceive('whereUser')->with(10)->andReturnSelf()->once();
    $query->shouldNotReceive('whereCategory');
    $query->shouldNotReceive('whereExpense');
    $query->shouldNotReceive('expensesBetween');
    $query->shouldNotReceive('whereOrder');
    $query->shouldNotReceive('whereSearch');

    $expense = new Expense();
    $expense->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters applies expense id filter', function () {
    $query = Mockery::mock(Builder::class);
    $filters = ['expense_id' => 20];

    $query->shouldReceive('whereExpense')->with(20)->andReturnSelf()->once();
    $query->shouldNotReceive('whereCategory');
    $query->shouldNotReceive('whereUser');
    $query->shouldNotReceive('expensesBetween');
    $query->shouldNotReceive('whereOrder');
    $query->shouldNotReceive('whereSearch');

    $expense = new Expense();
    $expense->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters applies date range filter', function () {
    $query = Mockery::mock(Builder::class);
    $filters = ['from_date' => '2023-01-01', 'to_date' => '2023-01-31'];

    $query->shouldReceive('expensesBetween')
        ->with(
            Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->format('Y-m-d') === '2023-01-01'),
            Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->format('Y-m-d') === '2023-01-31')
        )
        ->andReturnSelf()
        ->once();

    $query->shouldNotReceive('whereCategory');
    $query->shouldNotReceive('whereUser');
    $query->shouldNotReceive('whereExpense');
    $query->shouldNotReceive('whereOrder');
    $query->shouldNotReceive('whereSearch');

    $expense = new Expense();
    $expense->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters applies orderBy filter with default field and order', function () {
    $query = Mockery::mock(Builder::class);
    $filters = ['orderBy' => 'desc'];

    $query->shouldReceive('whereOrder')->with('expense_date', 'desc')->andReturnSelf()->once();
    $query->shouldNotReceive('whereCategory');
    $query->shouldNotReceive('whereUser');
    $query->shouldNotReceive('whereExpense');
    $query->shouldNotReceive('expensesBetween');
    $query->shouldNotReceive('whereSearch');

    $expense = new Expense();
    $expense->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters applies orderBy filter with specified field and order', function () {
    $query = Mockery::mock(Builder::class);
    $filters = ['orderByField' => 'amount', 'orderBy' => 'asc'];

    $query->shouldReceive('whereOrder')->with('amount', 'asc')->andReturnSelf()->once();
    $query->shouldNotReceive('whereCategory');
    $query->shouldNotReceive('whereUser');
    $query->shouldNotReceive('whereExpense');
    $query->shouldNotReceive('expensesBetween');
    $query->shouldNotReceive('whereSearch');

    $expense = new Expense();
    $expense->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters applies search filter', function () {
    $query = Mockery::mock(Builder::class);
    $filters = ['search' => 'test search'];

    $query->shouldReceive('whereSearch')->with('test search')->andReturnSelf()->once();
    $query->shouldNotReceive('whereCategory');
    $query->shouldNotReceive('whereUser');
    $query->shouldNotReceive('whereExpense');
    $query->shouldNotReceive('expensesBetween');
    $query->shouldNotReceive('whereOrder');

    $expense = new Expense();
    $expense->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters applies multiple filters', function () {
    $query = Mockery::mock(Builder::class);
    $filters = [
        'expense_category_id' => 1,
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'orderByField' => 'expense_date',
        'orderBy' => 'desc',
        'search' => 'food',
    ];

    $query->shouldReceive('whereCategory')->with(1)->andReturnSelf()->once();
    $query->shouldReceive('expensesBetween')
        ->with(
            Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->format('Y-m-d') === '2023-01-01'),
            Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->format('Y-m-d') === '2023-01-31')
        )
        ->andReturnSelf()
        ->once();
    $query->shouldReceive('whereOrder')->with('expense_date', 'desc')->andReturnSelf()->once();
    $query->shouldReceive('whereSearch')->with('food')->andReturnSelf()->once();
    $query->shouldNotReceive('whereUser');
    $query->shouldNotReceive('whereExpense');

    $expense = new Expense();
    $expense->scopeApplyFilters($query, $filters);
});

test('scopeWhereExpense applies orWhere clause for expense id', function () {
    $query = Mockery::mock(Builder::class);

    $query->shouldReceive('orWhere')
        ->with('id', 1)
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeWhereExpense($query, 1);
});

test('scopeWhereSearch applies whereHas and orWhere for single term', function () {
    $query = Mockery::mock(Builder::class);
    $innerQuery = Mockery::mock(Builder::class);

    $query->shouldReceive('whereHas')
        ->with('category', Mockery::on(function ($closure) use ($innerQuery) {
            $closure($innerQuery);
            return true;
        }))
        ->andReturnSelf()
        ->once();
    $innerQuery->shouldReceive('where')
        ->with('name', 'LIKE', '%term1%')
        ->andReturnSelf()
        ->once();

    $query->shouldReceive('orWhere')
        ->with('notes', 'LIKE', '%term1%')
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeWhereSearch($query, 'term1');
});

test('scopeWhereSearch applies whereHas and orWhere for multiple terms', function () {
    $query = Mockery::mock(Builder::class);
    $innerQuery1 = Mockery::mock(Builder::class);
    $innerQuery2 = Mockery::mock(Builder::class);

    $query->shouldReceive('whereHas')
        ->with('category', Mockery::on(function ($closure) use ($innerQuery1) {
            $closure($innerQuery1);
            return true;
        }))
        ->andReturnSelf()
        ->once();
    $innerQuery1->shouldReceive('where')
        ->with('name', 'LIKE', '%term1%')
        ->andReturnSelf()
        ->once();
    $query->shouldReceive('orWhere')
        ->with('notes', 'LIKE', '%term1%')
        ->andReturnSelf()
        ->once();

    $query->shouldReceive('whereHas')
        ->with('category', Mockery::on(function ($closure) use ($innerQuery2) {
            $closure($innerQuery2);
            return true;
        }))
        ->andReturnSelf()
        ->once();
    $innerQuery2->shouldReceive('where')
        ->with('name', 'LIKE', '%term2%')
        ->andReturnSelf()
        ->once();
    $query->shouldReceive('orWhere')
        ->with('notes', 'LIKE', '%term2%')
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeWhereSearch($query, 'term1 term2');
});

test('scopeWhereOrder applies orderBy clause', function () {
    $query = Mockery::mock(Builder::class);

    $query->shouldReceive('orderBy')
        ->with('expense_date', 'desc')
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeWhereOrder($query, 'expense_date', 'desc');
});

test('scopeWhereCompany applies where clause based on request header', function () {
    $query = Mockery::mock(Builder::class);

    request()->shouldReceive('header')
        ->with('company')
        ->andReturn(123)
        ->once();

    $query->shouldReceive('where')
        ->with('expenses.company_id', 123)
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeWhereCompany($query);
});

test('scopeWhereCompanyId applies where clause for specific company id', function () {
    $query = Mockery::mock(Builder::class);

    $query->shouldReceive('where')
        ->with('expenses.company_id', 456)
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeWhereCompanyId($query, 456);
});

test('scopePaginateData returns all records if limit is "all"', function () {
    $query = Mockery::mock(Builder::class);

    $query->shouldReceive('get')->andReturn('all_results')->once();
    $query->shouldNotReceive('paginate');

    $expense = new Expense();
    expect($expense->scopePaginateData($query, 'all'))->toBe('all_results');
});

test('scopePaginateData paginates records if limit is a number', function () {
    $query = Mockery::mock(Builder::class);

    $query->shouldReceive('paginate')->with(15)->andReturn('paginated_results')->once();
    $query->shouldNotReceive('get');

    $expense = new Expense();
    expect($expense->scopePaginateData($query, 15))->toBe('paginated_results');
});

test('scopeExpensesAttributes applies select and groupBy clauses', function () {
    $query = Mockery::mock(Builder::class);

    DB::shouldReceive('raw')
        ->with(Mockery::pattern('/count\(\*\) as expenses_count,.*sum\(base_amount\) as total_amount,.*expense_category_id/s'))
        ->andReturn('DB::raw expression')
        ->once();

    $query->shouldReceive('select')
        ->with('DB::raw expression')
        ->andReturnSelf()
        ->once();

    $query->shouldReceive('groupBy')
        ->with('expense_category_id')
        ->andReturnSelf()
        ->once();

    $expense = new Expense();
    $expense->scopeExpensesAttributes($query);
});

// Static method createExpense
test('createExpense creates expense, adds exchange rate log and custom fields if applicable', function () {
    $request = Mockery::mock(Request::class);
    $expensePayload = ['amount' => 100, 'currency_id' => 1, 'notes' => 'Test Expense'];
    $createdExpense = Mockery::mock(Expense::class . ', ' . HasMedia::class);
    $createdExpense->id = 1;
    $createdExpense->currency_id = 1;
    $createdExpense->shouldReceive('addCustomFields')->with(Mockery::type('object'))->andReturnSelf()->once();

    $request->shouldReceive('getExpensePayload')->andReturn($expensePayload)->once();
    $request->shouldReceive('header')->with('company')->andReturn(123)->once();
    $request->shouldReceive('hasFile')->with('attachment_receipt')->andReturn(false)->once();
    $request->customFields = json_encode(['field1' => 'value1']);

    Mockery::mock('alias:'.Expense::class)
        ->shouldReceive('create')
        ->with($expensePayload)
        ->andReturn($createdExpense)
        ->once();

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 123)
        ->andReturn(2)
        ->once();

    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldReceive('addExchangeRateLog')
        ->with($createdExpense)
        ->once();

    $result = Expense::createExpense($request);

    expect($result)->toBe($createdExpense);
});

test('createExpense creates expense and adds media if file exists', function () {
    $request = Mockery::mock(Request::class);
    $expensePayload = ['amount' => 100, 'currency_id' => 1, 'notes' => 'Test Expense'];
    $createdExpense = Mockery::mock(Expense::class . ', ' . HasMedia::class);
    $createdExpense->currency_id = 1;
    $createdExpense->shouldReceive('addCustomFields')->never();

    $fileAdder = Mockery::mock(FileAdder::class);
    $fileAdder->shouldReceive('toMediaCollection')->with('receipts')->andReturn(Mockery::mock(Media::class))->once();

    $request->shouldReceive('getExpensePayload')->andReturn($expensePayload)->once();
    $request->shouldReceive('header')->with('company')->andReturn(123)->once();
    $request->shouldReceive('hasFile')->with('attachment_receipt')->andReturn(true)->once();
    $request->customFields = null;

    Mockery::mock('alias:'.Expense::class)
        ->shouldReceive('create')
        ->with($expensePayload)
        ->andReturn($createdExpense)
        ->once();

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 123)
        ->andReturn(1)
        ->once();

    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldNotReceive('addExchangeRateLog');

    $createdExpense->shouldReceive('addMediaFromRequest')->with('attachment_receipt')->andReturn($fileAdder)->once();

    $result = Expense::createExpense($request);

    expect($result)->toBe($createdExpense);
});

test('createExpense handles no exchange rate log, no media, no custom fields', function () {
    $request = Mockery::mock(Request::class);
    $expensePayload = ['amount' => 100, 'currency_id' => 1];
    $createdExpense = Mockery::mock(Expense::class . ', ' . HasMedia::class);
    $createdExpense->currency_id = 1;
    $createdExpense->shouldReceive('addCustomFields')->never();

    $request->shouldReceive('getExpensePayload')->andReturn($expensePayload)->once();
    $request->shouldReceive('header')->with('company')->andReturn(123)->once();
    $request->shouldReceive('hasFile')->with('attachment_receipt')->andReturn(false)->once();
    $request->customFields = null;

    Mockery::mock('alias:'.Expense::class)
        ->shouldReceive('create')
        ->with($expensePayload)
        ->andReturn($createdExpense)
        ->once();

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 123)
        ->andReturn(1)
        ->once();

    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldNotReceive('addExchangeRateLog');

    $result = Expense::createExpense($request);
    expect($result)->toBe($createdExpense);
});

// Instance method updateExpense
test('updateExpense updates expense, adds exchange rate log and custom fields if applicable', function () {
    $request = Mockery::mock(Request::class);
    $data = ['amount' => 150, 'currency_id' => 2, 'notes' => 'Updated Expense'];
    $expense = Mockery::mock(Expense::class . '[update, updateCustomFields, clearMediaCollection, addMediaFromRequest]');
    $expense->currency_id = 1;

    $request->shouldReceive('getExpensePayload')->andReturn($data)->once();
    $request->shouldReceive('header')->with('company')->andReturn(123)->once();
    $request->shouldReceive('hasFile')->with('attachment_receipt')->andReturn(false)->once();
    $request->shouldReceive('offsetExists')->with('is_attachment_receipt_removed')->andReturn(false);
    $request->customFields = json_encode(['field1' => 'updated_value']);

    $expense->shouldReceive('update')->with($data)->andReturn(true)->once();

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 123)
        ->andReturn(1)
        ->once();

    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldReceive('addExchangeRateLog')
        ->with($expense)
        ->once();

    $expense->shouldReceive('updateCustomFields')->with(Mockery::type('object'))->andReturnSelf()->once();
    $expense->shouldNotReceive('clearMediaCollection');
    $expense->shouldNotReceive('addMediaFromRequest');

    $result = $expense->updateExpense($request);

    expect($result)->toBeTrue();
});

test('updateExpense updates expense, clears and adds new media if file exists', function () {
    $request = Mockery::mock(Request::class);
    $data = ['amount' => 200, 'currency_id' => 1];
    $expense = Mockery::mock(Expense::class . '[update, updateCustomFields, clearMediaCollection, addMediaFromRequest]');
    $expense->currency_id = 1;

    $fileAdder = Mockery::mock(FileAdder::class);
    $fileAdder->shouldReceive('toMediaCollection')->with('receipts')->andReturn(Mockery::mock(Media::class))->once();

    $request->shouldReceive('getExpensePayload')->andReturn($data)->once();
    $request->shouldReceive('header')->with('company')->andReturn(123)->once();
    $request->shouldReceive('hasFile')->with('attachment_receipt')->andReturn(true)->once();
    $request->shouldReceive('offsetExists')->with('is_attachment_receipt_removed')->andReturn(false);
    $request->customFields = null;

    $expense->shouldReceive('update')->with($data)->andReturn(true)->once();

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 123)
        ->andReturn(1)
        ->once();

    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldNotReceive('addExchangeRateLog');

    $expense->shouldReceive('clearMediaCollection')->with('receipts')->once();
    $expense->shouldReceive('addMediaFromRequest')->with('attachment_receipt')->andReturn($fileAdder)->once();
    $expense->shouldNotReceive('updateCustomFields');

    $result = $expense->updateExpense($request);

    expect($result)->toBeTrue();
});

test('updateExpense clears media if is_attachment_receipt_removed is true', function () {
    $request = Mockery::mock(Request::class);
    $data = ['amount' => 200, 'currency_id' => 1];
    $expense = Mockery::mock(Expense::class . '[update, updateCustomFields, clearMediaCollection, addMediaFromRequest]');
    $expense->currency_id = 1;

    $request->shouldReceive('getExpensePayload')->andReturn($data)->once();
    $request->shouldReceive('header')->with('company')->andReturn(123)->once();
    $request->shouldReceive('hasFile')->with('attachment_receipt')->andReturn(false)->once();
    $request->shouldReceive('offsetExists')->with('is_attachment_receipt_removed')->andReturn(true);
    $request->is_attachment_receipt_removed = true;
    $request->customFields = null;

    $expense->shouldReceive('update')->with($data)->andReturn(true)->once();

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 123)
        ->andReturn(1)
        ->once();

    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldNotReceive('addExchangeRateLog');

    $expense->shouldReceive('clearMediaCollection')->with('receipts')->once();
    $expense->shouldNotReceive('addMediaFromRequest');
    $expense->shouldNotReceive('updateCustomFields');

    $result = $expense->updateExpense($request);

    expect($result)->toBeTrue();
});

test('updateExpense handles no exchange rate log, no media changes, no custom fields', function () {
    $request = Mockery::mock(Request::class);
    $data = ['amount' => 50, 'currency_id' => 1];
    $expense = Mockery::mock(Expense::class . '[update, updateCustomFields, clearMediaCollection, addMediaFromRequest]');
    $expense->currency_id = 1;

    $request->shouldReceive('getExpensePayload')->andReturn($data)->once();
    $request->shouldReceive('header')->with('company')->andReturn(123)->once();
    $request->shouldReceive('hasFile')->with('attachment_receipt')->andReturn(false)->once();
    $request->shouldReceive('offsetExists')->with('is_attachment_receipt_removed')->andReturn(false);
    $request->customFields = null;

    $expense->shouldReceive('update')->with($data)->andReturn(true)->once();

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 123)
        ->andReturn(1)
        ->once();

    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldNotReceive('addExchangeRateLog');

    $expense->shouldNotReceive('clearMediaCollection');
    $expense->shouldNotReceive('addMediaFromRequest');
    $expense->shouldNotReceive('updateCustomFields');

    $result = $expense->updateExpense($request);

    expect($result)->toBeTrue();
});

// Edge case for media: what if addMediaFromRequest fails?
test('createExpense propagates FileCannotBeAdded exception if media upload fails', function () {
    $request = Mockery::mock(Request::class);
    $expensePayload = ['amount' => 100, 'currency_id' => 1];
    $createdExpense = Mockery::mock(Expense::class . ', ' . HasMedia::class);
    $createdExpense->currency_id = 1;
    $createdExpense->shouldReceive('addCustomFields')->never();

    $request->shouldReceive('getExpensePayload')->andReturn($expensePayload)->once();
    $request->shouldReceive('header')->with('company')->andReturn(123)->once();
    $request->shouldReceive('hasFile')->with('attachment_receipt')->andReturn(true)->once();
    $request->customFields = null;

    Mockery::mock('alias:'.Expense::class)
        ->shouldReceive('create')
        ->with($expensePayload)
        ->andReturn($createdExpense)
        ->once();

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', 123)
        ->andReturn(1)
        ->once();

    Mockery::mock('alias:'.ExchangeRateLog::class)
        ->shouldNotReceive('addExchangeRateLog');

    $createdExpense->shouldReceive('addMediaFromRequest')
        ->with('attachment_receipt')
        ->andThrow(FileCannotBeAdded::class)
        ->once();

    $this->expectException(FileCannotBeAdded::class);
    Expense::createExpense($request);
});




afterEach(function () {
    Mockery::close();
});
