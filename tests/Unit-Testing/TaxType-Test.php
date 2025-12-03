<?php

use Crater\Models\TaxType;
use Crater\Models\Tax;
use Crater\Models\Company;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

test('tax type has factory trait', function () {
    expect(class_uses(TaxType::class))->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
});

test('tax type guarded attributes', function () {
    $taxType = new TaxType();
    expect($taxType->getGuarded())->toEqual(['id']);
});

test('tax type cast attributes', function () {
    $taxType = new TaxType();
    $casts = $taxType->getCasts();
    expect($casts['percent'])->toEqual('float');
    expect($casts['compound_tax'])->toEqual('boolean');
});

test('tax type has many taxes relationship', function () {
    $taxType = new TaxType();
    $relation = $taxType->taxes();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Tax::class);
    expect($relation->getForeignKeyName())->toBe('tax_type_id');
    expect($relation->getLocalKeyName())->toBe('id');
});

test('tax type belongs to company relationship', function () {
    $taxType = new TaxType();
    $relation = $taxType->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
    expect($relation->getForeignKeyName())->toBe('company_id');
    expect($relation->getOwnerKeyName())->toBe('id');
});

test('scope where company filters by company_id from request header', function () {
    $companyId = 123;
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    app()->instance('request', $mockRequest); // Bind mock to container

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')->with('company_id', $companyId)->once()->andReturnSelf();

    $taxType = new TaxType();
    $taxType->scopeWhereCompany($query);
});

test('scope where company does nothing if company header is null', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')->with('company')->andReturn(null)->once();

    app()->instance('request', $mockRequest); // Bind mock to container

    $query = Mockery::mock(Builder::class);
    $query->shouldNotReceive('where'); // Ensure 'where' is NOT called

    $taxType = new TaxType();
    $taxType->scopeWhereCompany($query);
});

test('scope where tax type filters by id using orWhere', function () {
    $taxTypeId = 1;

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('orWhere')->with('id', $taxTypeId)->once()->andReturnSelf();

    $taxType = new TaxType();
    $taxType->scopeWhereTaxType($query, $taxTypeId);
});

test('scope apply filters with no filters provided', function () {
    $query = Mockery::mock(Builder::class);

    $query->shouldNotReceive('whereTaxType');
    $query->shouldNotReceive('whereCompany');
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereOrder');

    $taxType = new TaxType();
    $taxType->scopeApplyFilters($query, []);
});

test('scope apply filters with tax_type_id filter', function () {
    $taxTypeId = 5;
    $filters = ['tax_type_id' => $taxTypeId];

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereTaxType')->with($taxTypeId)->once()->andReturnSelf();
    $query->shouldNotReceive('whereCompany');
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereOrder');

    $taxType = new TaxType();
    $taxType->scopeApplyFilters($query, $filters);
});

test('scope apply filters with company_id filter', function () {
    $companyId = 10;
    $filters = ['company_id' => $companyId];

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereCompany')->with($companyId)->once()->andReturnSelf();
    $query->shouldNotReceive('whereTaxType');
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereOrder');

    $taxType = new TaxType();
    $taxType->scopeApplyFilters($query, $filters);
});

test('scope apply filters with search filter', function () {
    $search = 'test_search';
    $filters = ['search' => $search];

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereSearch')->with($search)->once()->andReturnSelf();
    $query->shouldNotReceive('whereTaxType');
    $query->shouldNotReceive('whereCompany');
    $query->shouldNotReceive('whereOrder');

    $taxType = new TaxType();
    $taxType->scopeApplyFilters($query, $filters);
});

test('scope apply filters with orderByField and orderBy filters', function () {
    $orderByField = 'name';
    $orderBy = 'desc';
    $filters = ['orderByField' => $orderByField, 'orderBy' => $orderBy];

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereOrder')->with($orderByField, $orderBy)->once()->andReturnSelf();
    $query->shouldNotReceive('whereTaxType');
    $query->shouldNotReceive('whereCompany');
    $query->shouldNotReceive('whereSearch');

    $taxType = new TaxType();
    $taxType->scopeApplyFilters($query, $filters);
});

test('scope apply filters with orderBy filter defaults field to payment_number and order to asc', function () {
    $orderBy = 'desc';
    $filters = ['orderBy' => $orderBy];

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereOrder')->with('payment_number', $orderBy)->once()->andReturnSelf();
    $query->shouldNotReceive('whereTaxType');
    $query->shouldNotReceive('whereCompany');
    $query->shouldNotReceive('whereSearch');

    $taxType = new TaxType();
    $taxType->scopeApplyFilters($query, $filters);
});

test('scope apply filters with orderByField filter defaults orderBy to asc', function () {
    $orderByField = 'created_at';
    $filters = ['orderByField' => $orderByField];

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereOrder')->with($orderByField, 'asc')->once()->andReturnSelf();
    $query->shouldNotReceive('whereTaxType');
    $query->shouldNotReceive('whereCompany');
    $query->shouldNotReceive('whereSearch');

    $taxType = new TaxType();
    $taxType->scopeApplyFilters($query, $filters);
});

test('scope apply filters with multiple filters combined', function () {
    $companyId = 20;
    $taxTypeId = 6;
    $search = 'multi_search';
    $orderByField = 'total';
    $orderBy = 'desc';
    $filters = [
        'company_id' => $companyId,
        'tax_type_id' => $taxTypeId,
        'search' => $search,
        'orderByField' => $orderByField,
        'orderBy' => $orderBy,
    ];

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereTaxType')->with($taxTypeId)->once()->andReturnSelf();
    $query->shouldReceive('whereCompany')->with($companyId)->once()->andReturnSelf();
    $query->shouldReceive('whereSearch')->with($search)->once()->andReturnSelf();
    $query->shouldReceive('whereOrder')->with($orderByField, $orderBy)->once()->andReturnSelf();

    $taxType = new TaxType();
    $taxType->scopeApplyFilters($query, $filters);
});

test('scope where order applies orderBy to query builder', function () {
    $orderByField = 'name';
    $orderBy = 'asc';

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('orderBy')->with($orderByField, $orderBy)->once()->andReturnSelf();

    $taxType = new TaxType();
    $taxType->scopeWhereOrder($query, $orderByField, $orderBy);
});

test('scope where search applies like condition to query builder', function () {
    $search = 'keyword';

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')->with('name', 'LIKE', '%'.$search.'%')->once()->andReturnSelf();

    $taxType = new TaxType();
    $taxType->scopeWhereSearch($query, $search);
});

test('scope paginate data returns all records if limit is string "all"', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('get')->once()->andReturn('all_records');
    $query->shouldNotReceive('paginate');

    $taxType = new TaxType();
    $result = $taxType->scopePaginateData($query, 'all');

    expect($result)->toEqual('all_records');
});

test('scope paginate data paginates records if limit is an integer', function () {
    $limit = 15;

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('paginate')->with($limit)->once()->andReturn('paginated_records');
    $query->shouldNotReceive('get');

    $taxType = new TaxType();
    $result = $taxType->scopePaginateData($query, $limit);

    expect($result)->toEqual('paginated_records');
});

afterEach(function () {
    // Clean up any mocked global request instance
    if (app()->bound('request') && app('request') instanceof Mockery\MockInterface) {
        app()->instance('request', new Request()); // Reset to a default request instance
    }
    Mockery::close(); // Close any Mockery mocks
});



