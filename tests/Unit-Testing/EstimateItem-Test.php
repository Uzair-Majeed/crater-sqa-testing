<?php

use Crater\Models\Estimate;
use Crater\Models\EstimateItem;
use Crater\Models\Item;
use Crater\Models\Tax;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Mockery as m;

beforeEach(function () {
    // Clear Mockery expectations before each test
    m::close();
});

test('it has guarded properties', function () {
    $item = new EstimateItem();
    expect($item->getGuarded())->toBe(['id']);
});

test('it casts attributes correctly to integer, float, and null types', function () {
    $item = new EstimateItem();

    // Test successful casting for various numeric inputs
    $item->price = 10000; // int
    expect($item->price)->toBeInt()->toEqual(10000);

    $item->total = 20000; // int
    expect($item->total)->toBeInt()->toEqual(20000);

    $item->discount = 15.5; // float
    expect($item->discount)->toBeFloat()->toEqual(15.5);

    $item->quantity = 2.5; // float
    expect($item->quantity)->toBeFloat()->toEqual(2.5);

    $item->discount_val = 500; // int
    expect($item->discount_val)->toBeInt()->toEqual(500);

    $item->tax = 100; // int
    expect($item->tax)->toBeInt()->toEqual(100);

    // Test casting with string numeric inputs
    $item->price = "10000";
    expect($item->price)->toBeInt()->toEqual(10000);

    $item->discount = "15.5";
    expect($item->discount)->toBeFloat()->toEqual(15.5);

    // Test casting with null values
    $item->price = null;
    expect($item->price)->toBeNull();

    $item->total = null;
    expect($item->total)->toBeNull();

    $item->discount = null;
    expect($item->discount)->toBeNull();

    $item->quantity = null;
    expect($item->quantity)->toBeNull();

    $item->discount_val = null;
    expect($item->discount_val)->toBeNull();

    $item->tax = null;
    expect($item->tax)->toBeNull();
});

test('it handles non-numeric strings for integer and float casts gracefully', function () {
    $item = new EstimateItem();

    // Laravel's default casting behavior for non-numeric strings to numeric types
    // is to return 0 for integers and 0.0 for floats.
    $item->price = "invalid_price";
    expect($item->price)->toBeInt()->toEqual(0);

    $item->total = "invalid_total";
    expect($item->total)->toBeInt()->toEqual(0);

    $item->discount = "invalid_discount";
    expect($item->discount)->toBeFloat()->toEqual(0.0);

    $item->quantity = "invalid_quantity";
    expect($item->quantity)->toBeFloat()->toEqual(0.0);

    $item->discount_val = "invalid_discount_val";
    expect($item->discount_val)->toBeInt()->toEqual(0);

    $item->tax = "invalid_tax";
    expect($item->tax)->toBeInt()->toEqual(0);
});

test('estimate relationship returns a BelongsTo relationship to Estimate', function () {
    $item = m::mock(EstimateItem::class)->makePartial();

    // Expect the belongsTo method to be called once with Estimate::class
    $item->shouldReceive('belongsTo')
        ->once()
        ->with(Estimate::class)
        ->andReturn(m::mock(BelongsTo::class)); // Return a mock of the relationship type

    $relation = $item->estimate();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
});

test('item relationship returns a BelongsTo relationship to Item', function () {
    $item = m::mock(EstimateItem::class)->makePartial();

    // Expect the belongsTo method to be called once with Item::class
    $item->shouldReceive('belongsTo')
        ->once()
        ->with(Item::class)
        ->andReturn(m::mock(BelongsTo::class));

    $relation = $item->item();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
});

test('taxes relationship returns a HasMany relationship to Tax', function () {
    $item = m::mock(EstimateItem::class)->makePartial();

    // Expect the hasMany method to be called once with Tax::class
    $item->shouldReceive('hasMany')
        ->once()
        ->with(Tax::class)
        ->andReturn(m::mock(HasMany::class));

    $relation = $item->taxes();

    expect($relation)->toBeInstanceOf(HasMany::class);
});

test('scopeWhereCompany applies the correct company_id filter to the query', function () {
    $query = m::mock(Builder::class);
    $companyId = 123;

    // Expect the 'where' method to be called on the query builder with 'company_id' and the given ID
    $query->shouldReceive('where')
        ->once()
        ->with('company_id', $companyId)
        ->andReturn($query); // Important to return the query for chaining

    // Call the scope method statically
    EstimateItem::scopeWhereCompany($query, $companyId);
});

test('scopeWhereCompany handles different company IDs', function () {
    $query = m::mock(Builder::class);
    $companyId = 456; // Different company ID

    $query->shouldReceive('where')
        ->once()
        ->with('company_id', $companyId)
        ->andReturn($query);

    EstimateItem::scopeWhereCompany($query, $companyId);
});

test('scopeWhereCompany handles zero company ID', function () {
    $query = m::mock(Builder::class);
    $companyId = 0; // Zero company ID

    $query->shouldReceive('where')
        ->once()
        ->with('company_id', $companyId)
        ->andReturn($query);

    EstimateItem::scopeWhereCompany($query, $companyId);
});




afterEach(function () {
    Mockery::close();
});
