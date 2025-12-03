<?php

use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Expense;
use Crater\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use function Pest\Faker\faker;
use function Pest\Laravel\mock;

beforeEach(function () {
    Mockery::close();
});

// ---------------------------------------------------
// Relationships
// ---------------------------------------------------

test('expenses relationship returns hasMany', function () {
    $category = new ExpenseCategory();
    $relation = $category->expenses();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Expense::class);
    expect($relation->getForeignKeyName())->toBe('expense_category_id');
    expect($relation->getLocalKeyName())->toBe('id');
});

test('company relationship returns belongsTo', function () {
    $category = new ExpenseCategory();
    $relation = $category->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
    expect($relation->getForeignKeyName())->toBe('company_id');
    expect($relation->getOwnerKeyName())->toBe('id');
});

// ---------------------------------------------------
// Accessor tests
// ---------------------------------------------------

test('getFormattedCreatedAtAttribute formats created_at correctly based on company setting', function () {
    $companyId = faker()->randomNumber();
    $createdAtString = faker()->dateTimeThisYear()->format('Y-m-d H:i:s');
    $dateFormat = 'd/m/Y H:i A';
    $expectedFormattedDate = Carbon::parse($createdAtString)->format($dateFormat);

    // Instead of aliasing Carbon, we'll just partial/mock ExpenseCategory's logic.
    mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat)
        ->once();

    // For Carbon, we avoid aliasing and let real Carbon be used.
    $category = new ExpenseCategory();
    $category->company_id = $companyId;
    $category->created_at = $createdAtString;

    expect($category->formattedCreatedAt)->toBe($expectedFormattedDate);
});

test('getAmountAttribute sums amounts from expenses relationship', function () {
    $expectedSum = faker()->randomFloat(2, 100, 1000);

    $mockQueryBuilder = Mockery::mock(Builder::class);
    $mockQueryBuilder->shouldReceive('sum')
                     ->with('amount')
                     ->andReturn($expectedSum)
                     ->once();

    $category = Mockery::mock(ExpenseCategory::class)->makePartial();
    $category->shouldReceive('expenses')
             ->andReturn($mockQueryBuilder)
             ->once();

    expect($category->amount)->toBe($expectedSum);
});

// ---------------------------------------------------
// Scope tests
// ---------------------------------------------------

test('scopeWhereCompany applies company_id filter from request header', function () {
    $companyId = faker()->uuid();

    // Fix: Set request() global helper to use our mocked Request instance.
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->once();

    // Swap request() global helper
    app()->instance('request', $request);

    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('where')
              ->with('company_id', $companyId)
              ->andReturnSelf()
              ->once();

    $category = new ExpenseCategory();
    $category->scopeWhereCompany($mockQuery);

    // Cleanup request mock
    app()->forgetInstance('request');
});

test('scopeWhereCategory applies category_id filter', function () {
    $categoryId = faker()->randomNumber();

    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('orWhere')
              ->with('id', $categoryId)
              ->andReturnSelf()
              ->once();

    $category = new ExpenseCategory();
    $category->scopeWhereCategory($mockQuery, $categoryId);
});

test('scopeWhereSearch applies name search filter', function () {
    $searchTerm = faker()->word();

    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('where')
              ->with('name', 'LIKE', '%'.$searchTerm.'%')
              ->andReturnSelf()
              ->once();

    $category = new ExpenseCategory();
    $category->scopeWhereSearch($mockQuery, $searchTerm);
});

test('scopeApplyFilters applies all filters correctly', function () {
    $companyId = faker()->uuid();
    $categoryId = faker()->randomNumber();
    $search = faker()->word();

    $filters = [
        'category_id' => $categoryId,
        'company_id' => $companyId,
        'search' => $search,
    ];

    // Swap request() global helper for scopeWhereCompany
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->once();
    app()->instance('request', $request);

    $mockQuery = Mockery::mock(Builder::class);

    // Instead of expecting direct orWhere/where on Builder, we need to allow ExpenseCategory to call its own scopes.
    $mockQuery->shouldReceive('whereCategory')
              ->with($categoryId)
              ->andReturnSelf()
              ->once();

    $mockQuery->shouldReceive('whereCompany')
              ->with($companyId)
              ->andReturnSelf()
              ->once();

    $mockQuery->shouldReceive('whereSearch')
              ->with($search)
              ->andReturnSelf()
              ->once();

    $category = new ExpenseCategory();
    $category->scopeApplyFilters($mockQuery, $filters);

    app()->forgetInstance('request');
});

test('scopeApplyFilters applies no filters when none are provided', function () {
    $filters = [];

    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldNotReceive('orWhere');
    $mockQuery->shouldNotReceive('where');
    $mockQuery->shouldNotReceive('whereCategory');
    $mockQuery->shouldNotReceive('whereCompany');
    $mockQuery->shouldNotReceive('whereSearch');

    $category = new ExpenseCategory();
    $category->scopeApplyFilters($mockQuery, $filters);
});

test('scopeApplyFilters applies only category_id filter', function () {
    $categoryId = faker()->randomNumber();
    $filters = ['category_id' => $categoryId];

    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('whereCategory')
              ->with($categoryId)
              ->andReturnSelf()
              ->once();
    $mockQuery->shouldNotReceive('whereCompany');
    $mockQuery->shouldNotReceive('whereSearch');

    $category = new ExpenseCategory();
    $category->scopeApplyFilters($mockQuery, $filters);
});

test('scopeApplyFilters applies only company_id filter', function () {
    $companyId = faker()->uuid();
    $filters = ['company_id' => $companyId];

    // Swap request() global helper for scopeWhereCompany
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->once();
    app()->instance('request', $request);

    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('whereCompany')
              ->with($companyId)
              ->andReturnSelf()
              ->once();
    $mockQuery->shouldNotReceive('whereCategory');
    $mockQuery->shouldNotReceive('whereSearch');

    $category = new ExpenseCategory();
    $category->scopeApplyFilters($mockQuery, $filters);

    app()->forgetInstance('request');
});

test('scopeApplyFilters applies only search filter', function () {
    $search = faker()->word();
    $filters = ['search' => $search];

    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('whereSearch')
              ->with($search)
              ->andReturnSelf()
              ->once();
    $mockQuery->shouldNotReceive('whereCategory');
    $mockQuery->shouldNotReceive('whereCompany');

    $category = new ExpenseCategory();
    $category->scopeApplyFilters($mockQuery, $filters);
});

// ---------------------------------------------------
// Pagination scopes
// ---------------------------------------------------

test('scopePaginateData returns all data when limit is all', function () {
    $mockCollection = new Collection();

    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('get')
              ->andReturn($mockCollection)
              ->once();
    $mockQuery->shouldNotReceive('paginate');

    $category = new ExpenseCategory();
    $result = $category->scopePaginateData($mockQuery, 'all');

    expect($result)->toBe($mockCollection);
});

test('scopePaginateData paginates data when limit is numeric', function () {
    $limit = faker()->numberBetween(5, 20);
    $mockPaginator = Mockery::mock(LengthAwarePaginator::class);

    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('paginate')
              ->with($limit)
              ->andReturn($mockPaginator)
              ->once();
    $mockQuery->shouldNotReceive('get');

    $category = new ExpenseCategory();
    $result = $category->scopePaginateData($mockQuery, $limit);

    expect($result)->toBe($mockPaginator);
});

afterAll(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});