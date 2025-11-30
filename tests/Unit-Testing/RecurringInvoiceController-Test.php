<?php

use Crater\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceController;
use Crater\Http\Requests\RecurringInvoiceRequest;
use Crater\Http\Resources\RecurringInvoiceResource;
use Crater\Models\RecurringInvoice;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder; // For query builder mocks

// Mock the base Controller's authorize method for all tests
beforeEach(function () {
    // We'll use a spy to ensure authorize is called without actually executing it.
    // This allows us to test the controller's logic in isolation from the authorization system.
    $this->controller = Mockery::spy(new RecurringInvoiceController());
});

afterEach(function () {
    Mockery::close();
});

test('index displays a listing of recurring invoices with default limit', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldNotReceive('limit'); // Should not be called if has('limit') is false
    $request->shouldReceive('all')->andReturn(['filter_key' => 'filter_value']);

    $mockRecurringInvoices = Mockery::mock(LengthAwarePaginator::class);
    // When ResourceCollection wraps a paginator, it accesses its 'resource' property to get the actual items.
    // For unit testing, we want to ensure the paginator itself is passed through.
    $mockRecurringInvoices->shouldReceive('resource')->andReturnSelf();

    $mockQueryBuilder = Mockery::mock(Builder::class);
    $mockQueryBuilder->shouldReceive('applyFilters')->with(['filter_key' => 'filter_value'])->andReturnSelf();
    $mockQueryBuilder->shouldReceive('paginateData')->with(10)->andReturn($mockRecurringInvoices); // Default limit
    $mockQueryBuilder->shouldReceive('count')->andReturn(50); // For total count meta

    // Mock static methods on RecurringInvoice
    RecurringInvoice::shouldReceive('whereCompany')->twice()->andReturn($mockQueryBuilder); // Once for paginate, once for count

    // Assert authorize call
    $this->controller->shouldReceive('authorize')->once()->with('viewAny', RecurringInvoice::class);

    // Act
    $response = $this->controller->index($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
    expect($response->resource)->toBe($mockRecurringInvoices);
    expect($response->additional['meta']['recurring_invoice_total_count'])->toBe(50);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    RecurringInvoice::shouldHaveReceived('whereCompany')->twice();
    $mockQueryBuilder->shouldHaveReceived('applyFilters')->once();
    $mockQueryBuilder->shouldHaveReceived('paginateData')->once();
    $mockQueryBuilder->shouldHaveReceived('count')->once();
});

test('index displays a listing of recurring invoices with custom limit', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->shouldReceive('limit')->andReturn(25); // Custom limit
    $request->shouldReceive('all')->andReturn([]);

    $mockRecurringInvoices = Mockery::mock(LengthAwarePaginator::class);
    $mockRecurringInvoices->shouldReceive('resource')->andReturnSelf();

    $mockQueryBuilder = Mockery::mock(Builder::class);
    $mockQueryBuilder->shouldReceive('applyFilters')->with([])->andReturnSelf();
    $mockQueryBuilder->shouldReceive('paginateData')->with(25)->andReturn($mockRecurringInvoices); // Custom limit
    $mockQueryBuilder->shouldReceive('count')->andReturn(100);

    RecurringInvoice::shouldReceive('whereCompany')->twice()->andReturn($mockQueryBuilder);

    $this->controller->shouldReceive('authorize')->once()->with('viewAny', RecurringInvoice::class);

    // Act
    $response = $this->controller->index($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
    expect($response->resource)->toBe($mockRecurringInvoices);
    expect($response->additional['meta']['recurring_invoice_total_count'])->toBe(100);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    RecurringInvoice::shouldHaveReceived('whereCompany')->twice();
    $mockQueryBuilder->shouldHaveReceived('applyFilters')->once();
    $mockQueryBuilder->shouldHaveReceived('paginateData')->once();
    $mockQueryBuilder->shouldHaveReceived('count')->once();
});

test('store creates a new recurring invoice', function () {
    // Arrange
    $request = Mockery::mock(RecurringInvoiceRequest::class);
    $mockRecurringInvoice = Mockery::mock(RecurringInvoice::class);

    RecurringInvoice::shouldReceive('createFromRequest')->once()->with($request)->andReturn($mockRecurringInvoice);

    $this->controller->shouldReceive('authorize')->once()->with('create', RecurringInvoice::class);

    // Act
    $response = $this->controller->store($request);

    // Assert
    expect($response)->toBeInstanceOf(RecurringInvoiceResource::class);
    expect($response->resource)->toBe($mockRecurringInvoice);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    RecurringInvoice::shouldHaveReceived('createFromRequest')->once();
});

test('show displays the specified recurring invoice', function () {
    // Arrange
    $mockRecurringInvoice = Mockery::mock(RecurringInvoice::class);

    $this->controller->shouldReceive('authorize')->once()->with('view', $mockRecurringInvoice);

    // Act
    $response = $this->controller->show($mockRecurringInvoice);

    // Assert
    expect($response)->toBeInstanceOf(RecurringInvoiceResource::class);
    expect($response->resource)->toBe($mockRecurringInvoice);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
});

test('update updates the specified recurring invoice', function () {
    // Arrange
    $request = Mockery::mock(RecurringInvoiceRequest::class);
    $mockRecurringInvoice = Mockery::mock(RecurringInvoice::class);
    $mockRecurringInvoice->shouldReceive('updateFromRequest')->once()->with($request)->andReturn(true); // updateFromRequest typically returns boolean or self

    $this->controller->shouldReceive('authorize')->once()->with('update', $mockRecurringInvoice);

    // Act
    $response = $this->controller->update($request, $mockRecurringInvoice);

    // Assert
    expect($response)->toBeInstanceOf(RecurringInvoiceResource::class);
    expect($response->resource)->toBe($mockRecurringInvoice);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    $mockRecurringInvoice->shouldHaveReceived('updateFromRequest')->once();
});

test('delete removes specified recurring invoices', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    // Mock property access for $request->ids
    $request->ids = [1, 2, 3];

    // Mock static method on RecurringInvoice
    RecurringInvoice::shouldReceive('deleteRecurringInvoice')->once()->with([1, 2, 3])->andReturn(true);

    $this->controller->shouldReceive('authorize')->once()->with('delete multiple recurring invoices');

    // Act
    $response = $this->controller->delete($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true]);
    expect($response->getStatusCode())->toBe(200);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    RecurringInvoice::shouldHaveReceived('deleteRecurringInvoice')->once();
});

test('delete handles single recurring invoice removal', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->ids = [1];

    RecurringInvoice::shouldReceive('deleteRecurringInvoice')->once()->with([1])->andReturn(true);

    $this->controller->shouldReceive('authorize')->once()->with('delete multiple recurring invoices');

    // Act
    $response = $this->controller->delete($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true]);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    RecurringInvoice::shouldHaveReceived('deleteRecurringInvoice')->once();
});

test('delete handles no recurring invoices to remove', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->ids = []; // Empty array of IDs

    RecurringInvoice::shouldReceive('deleteRecurringInvoice')->once()->with([])->andReturn(true);

    $this->controller->shouldReceive('authorize')->once()->with('delete multiple recurring invoices');

    // Act
    $response = $this->controller->delete($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true]);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    RecurringInvoice::shouldHaveReceived('deleteRecurringInvoice')->once();
});
