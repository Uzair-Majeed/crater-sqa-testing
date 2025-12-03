```php
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
    // Crucial for mocking protected methods like `authorize` from the `AuthorizesRequests` trait.
    $this->controller->shouldAllowMockingProtectedMethods();
});


test('index displays a listing of recurring invoices with default limit', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    // If has('limit') is false, the controller typically falls back to a default (e.g., 10).
    // It might still call `input('limit', default_value)`, so ensure that doesn't cause issues if mocked.
    $request->shouldReceive('all')->andReturn(['filter_key' => 'filter_value']);

    $mockRecurringInvoices = Mockery::mock(LengthAwarePaginator::class);
    // LengthAwarePaginator doesn't have a 'resource' method; it holds the collection of items.
    // The JsonResourceCollection wraps the Paginator directly, so $response->resource IS the paginator.
    // Mocking the total method on the paginator for the meta count.
    $mockRecurringInvoices->shouldReceive('total')->andReturn(50); 

    $mockQueryBuilder = Mockery::mock(Builder::class);
    $mockQueryBuilder->shouldReceive('applyFilters')->with(['filter_key' => 'filter_value'])->andReturnSelf();
    $mockQueryBuilder->shouldReceive('paginateData')->with(10)->andReturn($mockRecurringInvoices); // Default limit

    // Mock static methods on RecurringInvoice using 'alias' for static method mocking
    $recurringInvoiceMock = Mockery::mock('alias:' . RecurringInvoice::class);
    $recurringInvoiceMock->shouldReceive('whereCompany')->once()->andReturn($mockQueryBuilder); // Only once for the main query chain

    // Assert authorize call and ensure it doesn't throw AuthorizationException
    $this->controller->shouldReceive('authorize')->once()->with('viewAny', RecurringInvoice::class)->andReturn(true);

    // Act
    $response = $this->controller->index($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
    expect($response->resource)->toBe($mockRecurringInvoices);
    expect($response->additional['meta']['recurring_invoice_total_count'])->toBe(50);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    $recurringInvoiceMock->shouldHaveReceived('whereCompany')->once(); // Verify alias mock
    $mockQueryBuilder->shouldHaveReceived('applyFilters')->once();
    $mockQueryBuilder->shouldHaveReceived('paginateData')->once();
    $mockRecurringInvoices->shouldHaveReceived('total')->once(); // Verify total method on paginator
});

test('index displays a listing of recurring invoices with custom limit', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    // Use `input` for retrieving request parameters
    $request->shouldReceive('input')->with('limit', Mockery::any())->andReturn(25); // Custom limit
    $request->shouldReceive('all')->andReturn([]);

    $mockRecurringInvoices = Mockery::mock(LengthAwarePaginator::class);
    $mockRecurringInvoices->shouldReceive('total')->andReturn(100); // Mock total for meta

    $mockQueryBuilder = Mockery::mock(Builder::class);
    $mockQueryBuilder->shouldReceive('applyFilters')->with([])->andReturnSelf();
    $mockQueryBuilder->shouldReceive('paginateData')->with(25)->andReturn($mockRecurringInvoices); // Custom limit

    // Mock static methods on RecurringInvoice using 'alias'
    $recurringInvoiceMock = Mockery::mock('alias:' . RecurringInvoice::class);
    $recurringInvoiceMock->shouldReceive('whereCompany')->once()->andReturn($mockQueryBuilder);

    $this->controller->shouldReceive('authorize')->once()->with('viewAny', RecurringInvoice::class)->andReturn(true);

    // Act
    $response = $this->controller->index($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
    expect($response->resource)->toBe($mockRecurringInvoices);
    expect($response->additional['meta']['recurring_invoice_total_count'])->toBe(100);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    $recurringInvoiceMock->shouldHaveReceived('whereCompany')->once(); // Verify alias mock
    $mockQueryBuilder->shouldHaveReceived('applyFilters')->once();
    $mockQueryBuilder->shouldHaveReceived('paginateData')->once();
    $mockRecurringInvoices->shouldHaveReceived('total')->once(); // Verify total method on paginator
});

test('store creates a new recurring invoice', function () {
    // Arrange
    $request = Mockery::mock(RecurringInvoiceRequest::class);
    $mockRecurringInvoice = Mockery::mock(RecurringInvoice::class);

    // Mock static method `createFromRequest` on RecurringInvoice using 'alias'
    $recurringInvoiceMock = Mockery::mock('alias:' . RecurringInvoice::class);
    $recurringInvoiceMock->shouldReceive('createFromRequest')->once()->with($request)->andReturn($mockRecurringInvoice);

    $this->controller->shouldReceive('authorize')->once()->with('create', RecurringInvoice::class)->andReturn(true);

    // Act
    $response = $this->controller->store($request);

    // Assert
    expect($response)->toBeInstanceOf(RecurringInvoiceResource::class);
    expect($response->resource)->toBe($mockRecurringInvoice);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    $recurringInvoiceMock->shouldHaveReceived('createFromRequest')->once(); // Verify alias mock
});

test('show displays the specified recurring invoice', function () {
    // Arrange
    $mockRecurringInvoice = Mockery::mock(RecurringInvoice::class);

    // The beforeEach setup for `shouldAllowMockingProtectedMethods` and this `shouldReceive` call
    // together ensure the authorize call is intercepted and does not throw an exception.
    $this->controller->shouldReceive('authorize')->once()->with('view', $mockRecurringInvoice)->andReturn(true);

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

    // Ensure authorize call is intercepted
    $this->controller->shouldReceive('authorize')->once()->with('update', $mockRecurringInvoice)->andReturn(true);

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
    // Mock the `input` method for accessing request parameters
    $request->shouldReceive('input')->with('ids')->andReturn([1, 2, 3]);

    // Mock static method `deleteRecurringInvoice` on RecurringInvoice using 'alias'
    $recurringInvoiceMock = Mockery::mock('alias:' . RecurringInvoice::class);
    $recurringInvoiceMock->shouldReceive('deleteRecurringInvoice')->once()->with([1, 2, 3])->andReturn(true);

    $this->controller->shouldReceive('authorize')->once()->with('delete multiple recurring invoices')->andReturn(true);

    // Act
    $response = $this->controller->delete($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true]);
    expect($response->getStatusCode())->toBe(200);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    $recurringInvoiceMock->shouldHaveReceived('deleteRecurringInvoice')->once(); // Verify alias mock
});

test('delete handles single recurring invoice removal', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('input')->with('ids')->andReturn([1]);

    // Mock static method `deleteRecurringInvoice` on RecurringInvoice using 'alias'
    $recurringInvoiceMock = Mockery::mock('alias:' . RecurringInvoice::class);
    $recurringInvoiceMock->shouldReceive('deleteRecurringInvoice')->once()->with([1])->andReturn(true);

    $this->controller->shouldReceive('authorize')->once()->with('delete multiple recurring invoices')->andReturn(true);

    // Act
    $response = $this->controller->delete($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true]);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    $recurringInvoiceMock->shouldHaveReceived('deleteRecurringInvoice')->once(); // Verify alias mock
});

test('delete handles no recurring invoices to remove', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('input')->with('ids')->andReturn([]); // Empty array of IDs

    // Mock static method `deleteRecurringInvoice` on RecurringInvoice using 'alias'
    $recurringInvoiceMock = Mockery::mock('alias:' . RecurringInvoice::class);
    $recurringInvoiceMock->shouldReceive('deleteRecurringInvoice')->once()->with([])->andReturn(true);

    $this->controller->shouldReceive('authorize')->once()->with('delete multiple recurring invoices')->andReturn(true);

    // Act
    $response = $this->controller->delete($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true]);

    // Verify mocks
    $this->controller->shouldHaveReceived('authorize')->once();
    $recurringInvoiceMock->shouldHaveReceived('deleteRecurringInvoice')->once(); // Verify alias mock
});


afterEach(function () {
    Mockery::close();
});

```