<?php

use Crater\Http\Controllers\V1\Admin\Item\ItemsController;
use Crater\Http\Requests\DeleteItemsRequest;
use Crater\Http\Requests\ItemsRequest;
use Crater\Http\Resources\ItemResource;
use Crater\Models\Item;
use Crater\Models\TaxType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;

beforeEach(function () {
    Mockery::close();
});

test('index method authorizes, fetches and returns items with default limit and meta', function () {
    $controller = Mockery::mock(ItemsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('viewAny', Item::class);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('all')->andReturn([]); // For applyFilters

    $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockPaginator->shouldReceive('toArray')->andReturn([
        'data' => [['id' => 1, 'name' => 'Item 1', 'unit_name' => 'kg']],
    ]);
    $mockPaginator->shouldReceive('resource')->andReturnSelf();

    $mockItemBuilderForQuery = Mockery::mock(Builder::class);
    $mockItemBuilderForQuery->shouldReceive('leftJoin')->andReturnSelf();
    $mockItemBuilderForQuery->shouldReceive('applyFilters')->with([])->andReturnSelf();
    $mockItemBuilderForQuery->shouldReceive('select')->andReturnSelf();
    $mockItemBuilderForQuery->shouldReceive('latest')->andReturnSelf();
    $mockItemBuilderForQuery->shouldReceive('paginateData')->with(10)->andReturn($mockPaginator);

    $mockItemBuilderForCount = Mockery::mock(Builder::class);
    $mockItemBuilderForCount->shouldReceive('count')->andReturn(5);

    $mockTaxTypeCollection = new Collection([['id' => 1, 'name' => 'GST'], ['id' => 2, 'name' => 'VAT']]);
    $mockTaxTypeBuilder = Mockery::mock(Builder::class);
    $mockTaxTypeBuilder->shouldReceive('latest')->andReturnSelf();
    $mockTaxTypeBuilder->shouldReceive('get')->andReturn($mockTaxTypeCollection);

    // Fix: Use 'overload' instead of 'alias' for classes that might already be loaded
    // and whose static methods need to be mocked.
    // This ensures Mockery correctly replaces the class for the test's scope.
    $mockItem = Mockery::mock('overload:' . Item::class);
    $mockItem->shouldReceive('whereCompany')
        ->once()
        ->ordered()
        ->andReturn($mockItemBuilderForQuery);
    $mockItem->shouldReceive('whereCompany')
        ->once()
        ->ordered()
        ->andReturn($mockItemBuilderForCount);

    $mockTaxType = Mockery::mock('overload:' . TaxType::class);
    $mockTaxType->shouldReceive('whereCompany')
        ->once()
        ->andReturn($mockTaxTypeBuilder);

    $mockAnonymousResourceCollection = Mockery::mock(AnonymousResourceCollection::class);
    $mockAnonymousResourceCollection->shouldReceive('additional')
        ->once()
        ->with([
            'meta' => [
                'tax_types' => $mockTaxTypeCollection,
                'item_total_count' => 5,
            ],
        ])
        ->andReturn(new JsonResponse([
            'data' => [['id' => 1, 'name' => 'Item 1', 'unit_name' => 'kg']],
            'meta' => [
                'tax_types' => $mockTaxTypeCollection->toArray(),
                'item_total_count' => 5,
            ],
        ]));

    // Fix: Use 'overload' for ItemResource::collection static method
    $mockItemResource = Mockery::mock('overload:' . ItemResource::class);
    $mockItemResource->shouldReceive('collection')
        ->once()
        ->with(Mockery::type(LengthAwarePaginator::class))
        ->andReturn($mockAnonymousResourceCollection);

    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true)['data'])->toBe([['id' => 1, 'name' => 'Item 1', 'unit_name' => 'kg']]);
    expect($response->getData(true)['meta']['tax_types'])->toBe($mockTaxTypeCollection->toArray());
    expect($response->getData(true)['meta']['item_total_count'])->toBe(5);
});

test('index method fetches items with custom limit', function () {
    $controller = Mockery::mock(ItemsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('viewAny', Item::class);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    // Assuming the controller uses request->limit property or method. If it uses request->input('limit'), this would need to change.
    $request->shouldReceive('limit')->andReturn(5); 
    $request->shouldReceive('all')->andReturn([]);

    $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockPaginator->shouldReceive('toArray')->andReturn([
        'data' => [['id' => 1, 'name' => 'Item 1', 'unit_name' => 'kg']],
    ]);
    $mockPaginator->shouldReceive('resource')->andReturnSelf();

    $mockItemBuilderForQuery = Mockery::mock(Builder::class);
    $mockItemBuilderForQuery->shouldReceive('leftJoin')->andReturnSelf();
    $mockItemBuilderForQuery->shouldReceive('applyFilters')->with([])->andReturnSelf();
    $mockItemBuilderForQuery->shouldReceive('select')->andReturnSelf();
    $mockItemBuilderForQuery->shouldReceive('latest')->andReturnSelf();
    $mockItemBuilderForQuery->shouldReceive('paginateData')->with(5)->andReturn($mockPaginator);

    $mockItemBuilderForCount = Mockery::mock(Builder::class);
    $mockItemBuilderForCount->shouldReceive('count')->andReturn(5);

    $mockTaxTypeCollection = new Collection([['id' => 1, 'name' => 'GST'], ['id' => 2, 'name' => 'VAT']]);
    $mockTaxTypeBuilder = Mockery::mock(Builder::class);
    $mockTaxTypeBuilder->shouldReceive('latest')->andReturnSelf();
    $mockTaxTypeBuilder->shouldReceive('get')->andReturn($mockTaxTypeCollection);

    // Fix: Use 'overload' instead of 'alias' for classes that might already be loaded
    $mockItem = Mockery::mock('overload:' . Item::class);
    $mockItem->shouldReceive('whereCompany')
        ->once()
        ->ordered()
        ->andReturn($mockItemBuilderForQuery);
    $mockItem->shouldReceive('whereCompany')
        ->once()
        ->ordered()
        ->andReturn($mockItemBuilderForCount);

    $mockTaxType = Mockery::mock('overload:' . TaxType::class);
    $mockTaxType->shouldReceive('whereCompany')
        ->once()
        ->andReturn($mockTaxTypeBuilder);

    $mockAnonymousResourceCollection = Mockery::mock(AnonymousResourceCollection::class);
    $mockAnonymousResourceCollection->shouldReceive('additional')
        ->once()
        ->with([
            'meta' => [
                'tax_types' => $mockTaxTypeCollection,
                'item_total_count' => 5,
            ],
        ])
        ->andReturn(new JsonResponse([
            'data' => [['id' => 1, 'name' => 'Item 1', 'unit_name' => 'kg']],
            'meta' => [
                'tax_types' => $mockTaxTypeCollection->toArray(),
                'item_total_count' => 5,
            ],
        ]));

    // Fix: Use 'overload' for ItemResource::collection static method
    $mockItemResource = Mockery::mock('overload:' . ItemResource::class);
    $mockItemResource->shouldReceive('collection')
        ->once()
        ->with(Mockery::type(LengthAwarePaginator::class))
        ->andReturn($mockAnonymousResourceCollection);

    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true)['data'])->toBe([['id' => 1, 'name' => 'Item 1', 'unit_name' => 'kg']]);
    expect($response->getData(true)['meta']['tax_types'])->toBe($mockTaxTypeCollection->toArray());
    expect($response->getData(true)['meta']['item_total_count'])->toBe(5);
});

test('store method authorizes, creates and returns a new item', function () {
    $controller = Mockery::mock(ItemsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('create', Item::class);

    $request = Mockery::mock(ItemsRequest::class);
    $createdItem = Mockery::mock(Item::class);

    // Fix: Use 'overload' instead of 'alias' for Item::createItem static method
    $mockItem = Mockery::mock('overload:' . Item::class);
    $mockItem->shouldReceive('createItem')
        ->once()
        ->with($request)
        ->andReturn($createdItem);

    Mockery::mock('overload:' . ItemResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($createdItem);

    $response = $controller->store($request);

    expect($response)->toBeInstanceOf(ItemResource::class);
});

test('show method authorizes and returns an item', function () {
    $controller = Mockery::mock(ItemsController::class)->makePartial();
    $existingItem = Mockery::mock(Item::class);

    $controller->shouldReceive('authorize')
        ->once()
        ->with('view', $existingItem);

    Mockery::mock('overload:' . ItemResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($existingItem);

    $response = $controller->show($existingItem);

    expect($response)->toBeInstanceOf(ItemResource::class);
});

test('update method authorizes, updates and returns an item', function () {
    $controller = Mockery::mock(ItemsController::class)->makePartial();
    $request = Mockery::mock(ItemsRequest::class);
    $existingItem = Mockery::mock(Item::class);

    $controller->shouldReceive('authorize')
        ->once()
        ->with('update', $existingItem);

    $updatedItem = Mockery::mock(Item::class);
    $existingItem->shouldReceive('updateItem')
        ->once()
        ->with($request)
        ->andReturn($updatedItem);

    Mockery::mock('overload:' . ItemResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($updatedItem);

    $response = $controller->update($request, $existingItem);

    expect($response)->toBeInstanceOf(ItemResource::class);
});

test('delete method authorizes, deletes items and returns success', function () {
    $controller = Mockery::mock(ItemsController::class)->makePartial();
    $request = Mockery::mock(DeleteItemsRequest::class);
    $itemIds = [1, 2, 3];

    $controller->shouldReceive('authorize')
        ->once()
        ->with('delete multiple items');

    $request->shouldReceive('offsetGet')->with('ids')->andReturn($itemIds);

    // Fix: Use 'overload' instead of 'alias' for Item::destroy static method
    $mockItem = Mockery::mock('overload:' . Item::class);
    $mockItem->shouldReceive('destroy')
        ->once()
        ->with($itemIds)
        ->andReturn(count($itemIds));

    Response::shouldReceive('json')
        ->once()
        ->with(['success' => true])
        ->andReturn(new JsonResponse(['success' => true]));

    $response = $controller->delete($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true)['success'])->toBeTrue();
});


afterEach(function () {
    Mockery::close();
});