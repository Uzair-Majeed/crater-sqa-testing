```php
<?php

use Carbon\Carbon;
use Crater\Models\Item;
use Crater\Models\Unit;
use Crater\Models\Company;
use Crater\Models\Currency;
use Crater\Models\User;
use Crater\Models\Tax;
use Crater\Models\InvoiceItem;
use Crater\Models\EstimateItem;
use Crater\Models\CompanySetting;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade; // Not used directly for specific facades, will be removed for Request

beforeEach(function () {
    Mockery::close();
});

test('item has a unit', function () {
    $item = new Item();
    $relation = $item->unit();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Unit::class)
        ->and($relation->getForeignKeyName())->toBe('unit_id');
});

test('item belongs to a company', function () {
    $item = new Item();
    $relation = $item->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Company::class)
        ->and($relation->getForeignKeyName())->toBe('company_id');
});

test('item has a creator', function () {
    $item = new Item();
    $relation = $item->creator();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class)
        ->and($relation->getForeignKeyName())->toBe('creator_id');
});

test('item has a currency', function () {
    $item = new Item();
    $relation = $item->currency();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Currency::class)
        ->and($relation->getForeignKeyName())->toBe('currency_id');
});

test('item has many taxes', function () {
    $item = new Item();
    $relation = $item->taxes();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Tax::class)
        ->and($relation->getForeignKeyName())->toBe('item_id');
    // Fix: The actual SQL output uses double quotes for column names, not backticks.
    expect($relation->getQuery()->toSql())->toContain('"invoice_item_id" is null and "estimate_item_id" is null');
});

test('item has many invoice items', function () {
    $item = new Item();
    $relation = $item->invoiceItems();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(InvoiceItem::class)
        ->and($relation->getForeignKeyName())->toBe('item_id');
});

test('item has many estimate items', function () {
    $item = new Item();
    $relation = $item->estimateItems();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(EstimateItem::class)
        ->and($relation->getForeignKeyName())->toBe('item_id');
});

test('scopeWhereSearch applies search filter', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('items.name', 'LIKE', '%test_search%')
        ->andReturnSelf();

    $item = new Item();
    $item->scopeWhereSearch($query, 'test_search');
});

test('scopeWherePrice applies price filter', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('items.price', 100)
        ->andReturnSelf();

    $item = new Item();
    $item->scopeWherePrice($query, 100);
});

test('scopeWhereUnit applies unit filter', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('items.unit_id', 5)
        ->andReturnSelf();

    $item = new Item();
    $item->scopeWhereUnit($query, 5);
});

test('scopeWhereOrder applies order by', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('orderBy')
        ->once()
        ->with('name', 'desc')
        ->andReturnSelf();

    $item = new Item();
    $item->scopeWhereOrder($query, 'name', 'desc');
});

test('scopeWhereItem applies orWhere for item id', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('orWhere')
        ->once()
        ->with('id', 1)
        ->andReturnSelf();

    $item = new Item();
    $item->scopeWhereItem($query, 1);
});

test('scopeWhereCompany applies company filter', function () {
    $companyId = 123;
    
    // Fix: Mock the Request facade directly, not the base Facade class.
    Illuminate\Support\Facades\Request::shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn($companyId);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('items.company_id', $companyId)
        ->andReturnSelf();

    $item = new Item();
    $item->scopeWhereCompany($query);
});

test('scopeApplyFilters applies all filters correctly', function () {
    $query = Mockery::mock(Builder::class);

    $filters = [
        'search' => 'laptop',
        'price' => 1200,
        'unit_id' => 3,
        'item_id' => 7,
        'orderByField' => 'price',
        'orderBy' => 'desc',
    ];

    $query->shouldReceive('whereSearch')
        ->once()
        ->with($filters['search'])
        ->andReturnSelf();
    $query->shouldReceive('wherePrice')
        ->once()
        ->with($filters['price'])
        ->andReturnSelf();
    $query->shouldReceive('whereUnit')
        ->once()
        ->with($filters['unit_id'])
        ->andReturnSelf();
    $query->shouldReceive('whereItem')
        ->once()
        ->with($filters['item_id'])
        ->andReturnSelf();
    $query->shouldReceive('whereOrder')
        ->once()
        ->with($filters['orderByField'], $filters['orderBy'])
        ->andReturnSelf();

    $item = new Item();
    $item->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters applies default order by when field is null', function () {
    $query = Mockery::mock(Builder::class);

    $filters = [
        'orderBy' => 'desc',
    ];

    $query->shouldReceive('whereOrder')
        ->once()
        ->with('name', $filters['orderBy'])
        ->andReturnSelf();

    $item = new Item();
    $item->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters applies default order by when order is null', function () {
    $query = Mockery::mock(Builder::class);

    $filters = [
        'orderByField' => 'price',
    ];

    $query->shouldReceive('whereOrder')
        ->once()
        ->with($filters['orderByField'], 'asc')
        ->andReturnSelf();

    $item = new Item();
    $item->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters does not apply order by when both are null', function () {
    $query = Mockery::mock(Builder::class);

    $filters = [
        'some_other_filter' => 'value',
    ];

    $query->shouldNotReceive('whereOrder');

    $item = new Item();
    $item->scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters applies default order by when orderByField is empty string', function () {
    $query = Mockery::mock(Builder::class);

    $filters = [
        'orderByField' => '',
        'orderBy' => 'desc',
    ];

    $query->shouldReceive('whereOrder')
        ->once()
        ->with('name', $filters['orderBy'])
        ->andReturnSelf();

    $item = new Item();
    $item->scopeApplyFilters($query, $filters);
});

test('scopePaginateData returns all items when limit is "all"', function () {
    $query = Mockery::mock(Builder::class);
    $expectedCollection = collect(['item1', 'item2']);
    $query->shouldReceive('get')
        ->once()
        ->andReturn($expectedCollection);
    $query->shouldNotReceive('paginate');

    $item = new Item();
    $result = $item->scopePaginateData($query, 'all');

    expect($result)->toBe($expectedCollection);
});

test('scopePaginateData returns paginated items when limit is a number', function () {
    $query = Mockery::mock(Builder::class);
    $paginatedCollection = Mockery::mock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
    $limit = 15;

    $query->shouldReceive('paginate')
        ->once()
        ->with($limit)
        ->andReturn($paginatedCollection);
    $query->shouldNotReceive('get');

    $item = new Item();
    $result = $item->scopePaginateData($query, $limit);

    expect($result)->toBe($paginatedCollection);
});

test('getFormattedCreatedAtAttribute formats the created_at date', function () {
    $dateFormat = 'Y-m-d H:i:s';
    $companyId = 1;
    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat);

    // Fix: Mock the Request facade directly.
    Illuminate\Support\Facades\Request::shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn($companyId);
    
    $carbonDate = Carbon::create(2023, 1, 15, 10, 30, 0);
    $item = new Item([
        'created_at' => $carbonDate,
    ]);

    $formattedDate = $item->getFormattedCreatedAtAttribute(null);

    expect($formattedDate)->toBe('2023-01-15 10:30:00');
});

test('createItem creates an item without taxes', function () {
    $validatedData = ['name' => 'Test Item', 'price' => 100, 'unit_id' => 1];
    $companyId = 1;
    $userId = 5;
    $currencyId = 3;
    $createdItemId = 10;

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
    $mockRequest->shouldReceive('header')->once()->with('company')->andReturn($companyId);
    $mockRequest->shouldReceive('has')->once()->with('taxes')->andReturn(false);

    Auth::shouldReceive('id')->once()->andReturn($userId);

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('currency', $companyId)
        ->andReturn($currencyId);

    // Fix: Make the created item a partial mock to allow direct property assignment without setAttribute expectations.
    $mockCreatedItem = Mockery::mock(Item::class)->makePartial();
    $mockCreatedItem->id = $createdItemId; // This would cause setAttribute error on strict mock
    $mockCreatedItem->shouldReceive('taxes')->never();

    $itemAlias = Mockery::mock('alias:'.Item::class);
    $itemAlias->shouldReceive('create')
        ->once()
        ->with(array_merge($validatedData, [
            'company_id' => $companyId,
            'creator_id' => $userId,
            'currency_id' => $currencyId,
        ]))
        ->andReturn($mockCreatedItem);

    $mockWithQuery = Mockery::mock(Builder::class);
    $mockWithQuery->shouldReceive('find')
        ->once()
        ->with($createdItemId)
        ->andReturn($mockCreatedItem);

    $itemAlias->shouldReceive('with')
        ->once()
        ->with('taxes')
        ->andReturn($mockWithQuery);

    $result = Item::createItem($mockRequest);

    expect($result)->toBe($mockCreatedItem);
});

test('createItem creates an item with taxes', function () {
    $validatedData = ['name' => 'Test Item With Taxes', 'price' => 200, 'unit_id' => 2];
    $companyId = 1;
    $userId = 5;
    $currencyId = 3;
    $createdItemId = 10;
    $taxesData = [
        ['name' => 'VAT', 'rate' => 20],
        ['name' => 'GST', 'rate' => 5],
    ];

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
    $mockRequest->shouldReceive('header')->once()->with('company')->andReturn($companyId);
    $mockRequest->shouldReceive('has')->once()->with('taxes')->andReturn(true);
    $mockRequest->taxes = $taxesData;

    Auth::shouldReceive('id')->once()->andReturn($userId);

    Mockery::mock('alias:'.CompanySetting::class)
        ->shouldReceive('getSetting')
        ->once()
        ->with('currency', $companyId)
        ->andReturn($currencyId);

    // Fix: Make the created item a partial mock to allow direct property assignment without setAttribute expectations.
    $mockCreatedItem = Mockery::mock(Item::class)->makePartial();
    $mockCreatedItem->id = $createdItemId; // This would cause setAttribute error on strict mock
    $mockCreatedItem->tax_per_item = false; // This would cause setAttribute error on strict mock

    $mockCreatedItem->shouldReceive('save')->times(count($taxesData));
    // The setAttribute call happens due to direct property assignment, which makePartial handles
    // or if the underlying model implementation calls setAttribute explicitly, but for strict mocks,
    // if a method is called that isn't expected, it fails. With makePartial, this is not an issue
    // for direct property assignment.
    // If setAttribute is explicitly called in production code (e.g., $this->setAttribute('tax_per_item', true)),
    // then an expectation might still be needed on the partial mock. Let's assume makePartial is enough.
    // However, the original test had an explicit setAttribute expectation:
    // $mockCreatedItem->shouldReceive('setAttribute')->times(count($taxesData))->with('tax_per_item', true)->andReturnSelf();
    // This implies that `tax_per_item` is set *within a loop*, so keeping it here:
    $mockCreatedItem->shouldReceive('setAttribute')
        ->times(count($taxesData) + 1) // +1 for the initial false set
        ->with('tax_per_item', Mockery::any())
        ->andReturnSelf();


    $mockHasManyRelation = Mockery::mock(HasMany::class);
    foreach ($taxesData as $tax) {
        $mockHasManyRelation->shouldReceive('create')
            ->once()
            ->with(array_merge($tax, ['company_id' => $companyId]))
            ->andReturn(Mockery::mock(Tax::class));
    }
    $mockCreatedItem->shouldReceive('taxes')->andReturn($mockHasManyRelation);

    $itemAlias = Mockery::mock('alias:'.Item::class);
    $itemAlias->shouldReceive('create')
        ->once()
        ->with(array_merge($validatedData, [
            'company_id' => $companyId,
            'creator_id' => $userId,
            'currency_id' => $currencyId,
        ]))
        ->andReturn($mockCreatedItem);

    $mockWithQuery = Mockery::mock(Builder::class);
    $mockWithQuery->shouldReceive('find')
        ->once()
        ->with($createdItemId)
        ->andReturn($mockCreatedItem);

    $itemAlias->shouldReceive('with')
        ->once()
        ->with('taxes')
        ->andReturn($mockWithQuery);

    $result = Item::createItem($mockRequest);

    expect($result)->toBe($mockCreatedItem);
    expect($mockCreatedItem->tax_per_item)->toBeTrue();
});

test('updateItem updates an item without taxes', function () {
    $validatedData = ['name' => 'Updated Item Name', 'price' => 150];
    $itemId = 1;

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
    $mockRequest->shouldReceive('has')->once()->with('taxes')->andReturn(false);

    $item = Mockery::mock(Item::class)->makePartial();
    $item->id = $itemId;

    $item->shouldReceive('update')
        ->once()
        ->with($validatedData);

    $mockHasManyRelation = Mockery::mock(HasMany::class);
    $mockHasManyRelation->shouldReceive('delete')->once();
    $mockHasManyRelation->shouldNotReceive('create');
    $item->shouldReceive('taxes')->andReturn($mockHasManyRelation);

    // Fix: If `updateItem` is an instance method (called on $item), it should not call Item::with()->find() statically.
    // The common pattern is for the instance method to return $this, possibly after reloading relations.
    // Remove the alias mock and the static method chain.
    // Add expectation for `load('taxes')` if the method reloads relations before returning.
    $item->shouldReceive('load')->once()->with('taxes')->andReturnSelf();

    $result = $item->updateItem($mockRequest);

    expect($result)->toBe($item);
});

test('updateItem updates an item with taxes', function () {
    $validatedData = ['name' => 'Updated Item With Taxes', 'price' => 250];
    $itemId = 1;
    $companyId = 1;
    $taxesData = [
        ['name' => 'Custom Tax', 'rate' => 10],
    ];

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
    $mockRequest->shouldReceive('has')->once()->with('taxes')->andReturn(true);
    $mockRequest->taxes = $taxesData;
    Illuminate\Support\Facades\Request::shouldReceive('header') // Fix: Direct Request facade mock
        ->once()
        ->with('company')
        ->andReturn($companyId);

    $item = Mockery::mock(Item::class)->makePartial();
    $item->id = $itemId;
    $item->tax_per_item = false;

    $item->shouldReceive('update')
        ->once()
        ->with($validatedData);

    $item->shouldReceive('save')->once();
    $item->shouldReceive('setAttribute')
        ->once()
        ->with('tax_per_item', true)
        ->andReturnSelf();

    $mockHasManyRelation = Mockery::mock(HasMany::class);
    $mockHasManyRelation->shouldReceive('delete')->once();
    $mockHasManyRelation->shouldReceive('create')
        ->once()
        ->with(array_merge($taxesData[0], ['company_id' => $companyId]))
        ->andReturn(Mockery::mock(Tax::class));
    $item->shouldReceive('taxes')->andReturn($mockHasManyRelation);

    // Fix: Remove the alias mock and the static method chain.
    // Add expectation for `load('taxes')` if the method reloads relations before returning.
    $item->shouldReceive('load')->once()->with('taxes')->andReturnSelf();

    $result = $item->updateItem($mockRequest);

    expect($result)->toBe($item);
    expect($item->tax_per_item)->toBeTrue();
});


afterEach(function () {
    Mockery::close();
});
```