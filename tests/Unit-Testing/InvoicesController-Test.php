<?php

uses(\Tests\TestCase::class)->group('unit');

use Crater\Http\Controllers\V1\Customer\Invoice\InvoicesController;
use Crater\Http\Resources\Customer\InvoiceResource;
use Crater\Models\Company;
use Crater\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
uses(\Mockery::class);

beforeEach(function () {
    Mockery::close();
});

test('index method returns a collection of customer invoices with specified limit and filters', function () {
    // Arrange
    $customerId = 1;
    $limit = 5;
    $requestData = ['limit' => $limit, 'status' => 'SENT', 'sort' => 'date'];

    // Mock Request
    $requestMock = Mockery::mock(Request::class);
    $requestMock->shouldReceive('has')->with('limit')->andReturn(true);
    $requestMock->shouldReceive('__get')->with('limit')->andReturn($limit); // For $request->limit
    $requestMock->shouldReceive('all')->andReturn($requestData);

    // Mock Auth Facade
    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn($customerId);

    // Mock Invoice Model instances for the collection
    $invoice1 = Mockery::mock(Invoice::class);
    $invoice1->id = 1;
    $invoice1->amount = 100;
    $invoice1->customer_id = $customerId;

    $invoice2 = Mockery::mock(Invoice::class);
    $invoice2->id = 2;
    $invoice2->amount = 200;
    $invoice2->customer_id = $customerId;

    $invoiceCollection = collect([$invoice1, $invoice2]);

    $totalInvoiceCount = 10;
    $paginator = new LengthAwarePaginator($invoiceCollection, $totalInvoiceCount, $limit, 1);

    // Mock Invoice model methods
    $invoiceModelMock = Mockery::mock('alias:' . Invoice::class);
    $invoiceModelMock->shouldReceive('with')->with(['items', 'customer', 'creator', 'taxes'])->andReturnSelf();
    $invoiceModelMock->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf();
    $invoiceModelMock->shouldReceive('applyFilters')->with($requestData)->andReturnSelf();
    $invoiceModelMock->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $invoiceModelMock->shouldReceive('latest')->andReturnSelf();
    $invoiceModelMock->shouldReceive('paginateData')->with($limit)->andReturn($paginator);

    // Mock count for additional meta data
    $invoiceModelMock->shouldReceive('count')->andReturn($totalInvoiceCount);

    // Act
    $controller = new InvoicesController();
    $response = $controller->index($requestMock);

    // Assert
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->resource)->toHaveCount(2);
    expect($response->resource->first())->toBeInstanceOf(Invoice::class);
    expect($response->additional['meta']['invoiceTotalCount'])->toBe($totalInvoiceCount);
    expect($response->resource->first()->id)->toBe($invoice1->id);
    expect($response->resource->last()->id)->toBe($invoice2->id);
});

test('index method returns a collection of customer invoices with default limit if not provided and no filters', function () {
    // Arrange
    $customerId = 1;
    $defaultLimit = 10;
    $requestData = []; // No limit, no filters

    // Mock Request
    $requestMock = Mockery::mock(Request::class);
    $requestMock->shouldReceive('has')->with('limit')->andReturn(false);
    $requestMock->shouldNotReceive('__get')->with('limit'); // Should not be called if 'limit' is not present
    $requestMock->shouldReceive('all')->andReturn($requestData);

    // Mock Auth Facade
    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn($customerId);

    // Mock Invoice Model instances for the collection
    $invoice1 = Mockery::mock(Invoice::class);
    $invoice1->id = 1;
    $invoice1->amount = 100;
    $invoice1->customer_id = $customerId;

    $invoice2 = Mockery::mock(Invoice::class);
    $invoice2->id = 2;
    $invoice2->amount = 200;
    $invoice2->customer_id = $customerId;

    $invoiceCollection = collect([$invoice1, $invoice2]);

    $totalInvoiceCount = 10;
    $paginator = new LengthAwarePaginator($invoiceCollection, $totalInvoiceCount, $defaultLimit, 1);

    // Mock Invoice model methods
    $invoiceModelMock = Mockery::mock('alias:' . Invoice::class);
    $invoiceModelMock->shouldReceive('with')->with(['items', 'customer', 'creator', 'taxes'])->andReturnSelf();
    $invoiceModelMock->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf();
    $invoiceModelMock->shouldReceive('applyFilters')->with($requestData)->andReturnSelf(); // Empty filters
    $invoiceModelMock->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $invoiceModelMock->shouldReceive('latest')->andReturnSelf();
    $invoiceModelMock->shouldReceive('paginateData')->with($defaultLimit)->andReturn($paginator); // Expect default limit

    // Mock count for additional meta data
    $invoiceModelMock->shouldReceive('count')->andReturn($totalInvoiceCount);

    // Act
    $controller = new InvoicesController();
    $response = $controller->index($requestMock);

    // Assert
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->resource)->toHaveCount(2);
    expect($response->resource->first())->toBeInstanceOf(Invoice::class);
    expect($response->additional['meta']['invoiceTotalCount'])->toBe($totalInvoiceCount);
});

test('index method returns an empty collection when no invoices are found', function () {
    // Arrange
    $customerId = 1;
    $limit = 5;
    $requestData = ['limit' => $limit];

    // Mock Request
    $requestMock = Mockery::mock(Request::class);
    $requestMock->shouldReceive('has')->with('limit')->andReturn(true);
    $requestMock->shouldReceive('__get')->with('limit')->andReturn($limit);
    $requestMock->shouldReceive('all')->andReturn($requestData);

    // Mock Auth Facade
    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn($customerId);

    $emptyCollection = collect([]);
    $totalInvoiceCount = 0;
    $paginator = new LengthAwarePaginator($emptyCollection, $totalInvoiceCount, $limit, 1);

    // Mock Invoice model methods
    $invoiceModelMock = Mockery::mock('alias:' . Invoice::class);
    $invoiceModelMock->shouldReceive('with')->with(['items', 'customer', 'creator', 'taxes'])->andReturnSelf();
    $invoiceModelMock->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf();
    $invoiceModelMock->shouldReceive('applyFilters')->with($requestData)->andReturnSelf();
    $invoiceModelMock->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $invoiceModelMock->shouldReceive('latest')->andReturnSelf();
    $invoiceModelMock->shouldReceive('paginateData')->with($limit)->andReturn($paginator);

    // Mock count for additional meta data
    $invoiceModelMock->shouldReceive('count')->andReturn($totalInvoiceCount);

    // Act
    $controller = new InvoicesController();
    $response = $controller->index($requestMock);

    // Assert
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->resource)->toHaveCount(0);
    expect($response->additional['meta']['invoiceTotalCount'])->toBe($totalInvoiceCount);
});

test('show method returns an invoice if found for the authenticated customer', function () {
    // Arrange
    $companyId = 1;
    $invoiceId = 123;
    $customerId = 456;

    $expectedInvoice = Mockery::mock(Invoice::class);
    $expectedInvoice->id = $invoiceId;
    $expectedInvoice->company_id = $companyId;
    $expectedInvoice->customer_id = $customerId;
    $expectedInvoice->amount = 500;

    // Mock Auth Facade
    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn($customerId);

    // Mock Query Builder Chain
    $queryBuilderMock = Mockery::mock();
    $queryBuilderMock->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $queryBuilderMock->shouldReceive('where')->with('id', $invoiceId)->andReturnSelf();
    $queryBuilderMock->shouldReceive('first')->andReturn($expectedInvoice);

    // Mock Company Model
    $companyMock = Mockery::mock(Company::class);
    $companyMock->shouldReceive('invoices')->andReturn($queryBuilderMock);

    // Act
    $controller = new InvoicesController();
    $response = $controller->show($companyMock, $invoiceId);

    // Assert
    expect($response)->toBeInstanceOf(InvoiceResource::class);
    expect($response->resource)->toBe($expectedInvoice);
    expect($response->resource->id)->toBe($invoiceId);
});

test('show method returns 404 error if invoice is not found for the authenticated customer', function () {
    // Arrange
    $companyId = 1;
    $invoiceId = 123;
    $customerId = 456;

    // Mock Auth Facade
    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn($customerId);

    // Mock Query Builder Chain to return null (invoice not found)
    $queryBuilderMock = Mockery::mock();
    $queryBuilderMock->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $queryBuilderMock->shouldReceive('where')->with('id', $invoiceId)->andReturnSelf();
    $queryBuilderMock->shouldReceive('first')->andReturn(null);

    // Mock Company Model
    $companyMock = Mockery::mock(Company::class);
    $companyMock->shouldReceive('invoices')->andReturn($queryBuilderMock);

    // Act
    $controller = new InvoicesController();
    $response = $controller->show($companyMock, $invoiceId);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getStatusCode())->toBe(404);
    expect($response->getData(true))->toEqual(['error' => 'invoice_not_found']);
});
