<?php

use Crater\Models\Unit;
use Crater\Models\Item;
use Crater\Models\Company;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Mockery\MockInterface;

// Helper to mock the global request() function
if (!function_exists('request')) {
    function request(): Request|MockInterface
    {
        return test()->mock(Request::class);
    }
}

// Ensure the request() helper's mock instance is reset for each test
beforeEach(function () {
    test()->instance(Request::class, Mockery::mock(Request::class));
});

test('it has fillable attributes', function () {
    $unit = new Unit();
    expect($unit->getFillable())->toBe(['name', 'company_id']);
});

test('items relationship returns has many relationship', function () {
    $unit = new Unit();
    $relation = $unit->items();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Item::class);
    expect($relation->getForeignKeyName())->toBe('unit_id');
    expect($relation->getLocalKeyName())->toBe('id');
});

test('company relationship returns belongs to relationship', function () {
    $unit = new Unit();
    $relation = $unit->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
    expect($relation->getForeignKeyName())->toBe('company_id');
    expect($relation->getOwnerKeyName())->toBe('id');
});



test('scopeWhereUnit applies orWhere id filter', function () {
    $unitId = 456;
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('orWhere')
        ->with('id', $unitId)
        ->andReturnSelf()
        ->once();

    $unit = new Unit();
    $unit->scopeWhereUnit($mockBuilder, $unitId);
});

test('scopeWhereUnit applies orWhere id filter with null unit id', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('orWhere')
        ->with('id', null)
        ->andReturnSelf()
        ->once();

    $unit = new Unit();
    $unit->scopeWhereUnit($mockBuilder, null);
});

test('scopeWhereSearch applies name like filter', function () {
    $search = 'test unit';
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('where')
        ->with('name', 'LIKE', '%' . $search . '%')
        ->andReturnSelf()
        ->once();

    $unit = new Unit();
    $result = $unit->scopeWhereSearch($mockBuilder, $search);

    expect($result)->toBe($mockBuilder);
});

test('scopeWhereSearch applies name like filter with empty search string', function () {
    $search = '';
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('where')
        ->with('name', 'LIKE', '%%')
        ->andReturnSelf()
        ->once();

    $unit = new Unit();
    $result = $unit->scopeWhereSearch($mockBuilder, $search);

    expect($result)->toBe($mockBuilder);
});

test('scopeApplyFilters applies search filter', function () {
    $filters = ['search' => 'desk'];
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('whereSearch')
        ->with($filters['search'])
        ->andReturnSelf()
        ->once();

    $unit = new Unit();
    $result = $unit->scopeApplyFilters($mockBuilder, $filters);

    expect($result)->toBe($mockBuilder);
});

test('scopeApplyFilters applies unit_id filter', function () {
    $filters = ['unit_id' => 1];
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('whereUnit')
        ->with($filters['unit_id'])
        ->andReturnSelf()
        ->once();

    $unit = new Unit();
    $result = $unit->scopeApplyFilters($mockBuilder, $filters);

    expect($result)->toBe($mockBuilder);
});

test('scopeApplyFilters applies no filters when none are provided', function () {
    $filters = [];
    $mockBuilder = Mockery::mock(Builder::class);

    $mockBuilder->shouldNotReceive('whereSearch');
    $mockBuilder->shouldNotReceive('whereUnit');
    $mockBuilder->shouldNotReceive('where'); // For company filter

    $unit = new Unit();
    $result = $unit->scopeApplyFilters($mockBuilder, $filters);

    expect($result)->toBe($mockBuilder);
});

test('scopeApplyFilters ignores unknown filters', function () {
    $filters = [
        'unknown_filter' => 'value',
        'another_unknown' => 123
    ];
    $mockBuilder = Mockery::mock(Builder::class);

    $mockBuilder->shouldNotReceive('whereSearch');
    $mockBuilder->shouldNotReceive('whereUnit');
    $mockBuilder->shouldNotReceive('where');

    $unit = new Unit();
    $result = $unit->scopeApplyFilters($mockBuilder, $filters);

    expect($result)->toBe($mockBuilder);
});

test('scopePaginateData returns all items when limit is all', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('get')
        ->andReturn('all_results')
        ->once();
    $mockBuilder->shouldNotReceive('paginate');

    $unit = new Unit();
    $result = $unit->scopePaginateData($mockBuilder, 'all');

    expect($result)->toBe('all_results');
});

test('scopePaginateData paginates items when limit is an integer', function () {
    $limit = 10;
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('paginate')
        ->with($limit)
        ->andReturn('paginated_results')
        ->once();
    $mockBuilder->shouldNotReceive('get');

    $unit = new Unit();
    $result = $unit->scopePaginateData($mockBuilder, $limit);

    expect($result)->toBe('paginated_results');
});

test('scopePaginateData paginates items when limit is a string that is not all', function () {
    $limit = 'invalid_string';
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('paginate')
        ->with($limit)
        ->andReturn('paginated_results_string')
        ->once();
    $mockBuilder->shouldNotReceive('get');

    $unit = new Unit();
    $result = $unit->scopePaginateData($mockBuilder, $limit);

    expect($result)->toBe('paginated_results_string');
});




afterEach(function () {
    Mockery::close();
});
