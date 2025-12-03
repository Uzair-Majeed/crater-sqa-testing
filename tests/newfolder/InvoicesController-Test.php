```php
<?php


use Crater\Http\Controllers\V1\Customer\Invoice\InvoicesController;
use Crater\Http\Resources\Customer\InvoiceResource;
use Crater\Models\Company;
use Crater\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder; // Added for explicit Eloquent Builder mocking

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

    // Mock Invoice Model instances for the collection - FIX: provide properties directly to avoid setAttribute exception
    $invoice1 = Mockery::mock(Invoice::class, [
        'id' => 1,
        'amount' => 100,
        'customer_id' => $customerId,
    ]);

    $invoice2 = Mockery::mock(Invoice::class, [
        'id' => 2,
        'amount' => 200,
        'customer_id' => $customerId,
    ]);

    $invoiceCollection = collect([$invoice1, $invoice2]);

    $totalInvoiceCount = 10;
    $paginator = new LengthAwarePaginator($invoiceCollection, $totalInvoiceCount, $limit, 1);

    // Mock Invoice model for static methods and the query builder chain
    // FIX 1: Use 'overload:' to prevent "class already exists" error.
    // FIX 2: Correctly mock the Eloquent query builder chain by returning a Builder mock from 'with()'.
    $invoiceClassMock = Mockery::mock('overload:' . Invoice::class);
    $queryBuilderMock = Mockery::mock(Builder::class); // Mock the Eloquent Builder instance

    $invoiceClassMock->shouldReceive('with')->with(['items', 'customer', 'creator', 'taxes'])->andReturn($queryBuilderMock);
    $queryBuilderMock->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf();
    $queryBuilderMock->shouldReceive('applyFilters')->with($requestData)->andReturnSelf();
    $queryBuilderMock->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $queryBuilderMock->shouldReceive('latest')->andReturnSelf();
    $queryBuilderMock->shouldReceive('paginateData')->with($limit)->andReturn($paginator);

    // Mock count for additional meta data, assuming Invoice::count() or $queryBuilder->count()
    // Based on original test, it implies a call on the initial model/query.
    $invoiceClassMock->shouldReceive('count')->andReturn($totalInvoiceCount);

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

    // Mock Invoice Model instances for the collection - FIX: provide properties directly
    $invoice1 = Mockery::mock(Invoice::class, [
        'id' => 1,
        'amount' => 100,
        'customer_id' => $customerId,
    ]);

    $invoice2 = Mockery::mock(Invoice::class, [
        'id' => 2,
        'amount' => 200,
        'customer_id' => $customerId,
    ]);

    $invoiceCollection = collect([$invoice1, $invoice2]);

    $totalInvoiceCount = 10;
    $paginator = new LengthAwarePaginator($invoiceCollection, $totalInvoiceCount, $defaultLimit, 1);

    // Mock Invoice model for static methods and the query builder chain - FIX: use overload and separate builder mock
    $invoiceClassMock = Mockery::mock('overload:' . Invoice::class);
    $queryBuilderMock = Mockery::mock(Builder::class);

    $invoiceClassMock->shouldReceive('with')->with(['items', 'customer', 'creator', 'taxes'])->andReturn($queryBuilderMock);
    $queryBuilderMock->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf();
    $queryBuilderMock->shouldReceive('applyFilters')->with($requestData)->andReturnSelf(); // Empty filters
    $queryBuilderMock->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $queryBuilderMock->shouldReceive('latest')->andReturnSelf();
    $queryBuilderMock->shouldReceive('paginateData')->with($defaultLimit)->andReturn($paginator); // Expect default limit

    // Mock count for additional meta data, assumed to be a static call or on the initial query
    $invoiceClassMock->shouldReceive('count')->andReturn($totalInvoiceCount);

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

    // Mock Invoice model for static methods and the query builder chain - FIX: use overload and separate builder mock
    $invoiceClassMock = Mockery::mock('overload:' . Invoice::class);
    $queryBuilderMock = Mockery::mock(Builder::class);

    $invoiceClassMock->shouldReceive('with')->with(['items', 'customer', 'creator', 'taxes'])->andReturn($queryBuilderMock);
    $queryBuilderMock->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf();
    $queryBuilderMock->shouldReceive('applyFilters')->with($requestData)->andReturnSelf();
    $queryBuilderMock->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $queryBuilderMock->shouldReceive('latest')->andReturnSelf();
    $queryBuilderMock->shouldReceive('paginateData')->with($limit)->andReturn($paginator);

    // Mock count for additional meta data, assumed to be a static call or on the initial query
    $invoiceClassMock->shouldReceive('count')->andReturn($totalInvoiceCount);

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

    // FIX: provide properties directly to mock to avoid setAttribute exception
    $expectedInvoice = Mockery::mock(Invoice::class, [
        'id' => $invoiceId,
        'company_id' => $companyId,
        'customer_id' => $customerId,
        'amount' => 500,
    ]);

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


afterEach(function () {
    Mockery::close();
});
```