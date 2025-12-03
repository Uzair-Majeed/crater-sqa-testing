<?php

use Crater\Http\Controllers\V1\Customer\Estimate\EstimatesController;
use Crater\Http\Resources\Customer\EstimateResource;
use Crater\Models\Company;
use Crater\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Routing\ResponseFactory;

// Ensure Laravel's TestCase setup is used for global app() access to mock global helpers like `response()`

// Mock the Auth facade for all tests in this file
beforeEach(function () {
    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn(1); // Simulate customer ID 1
});

test('index method returns estimates with default limit and no filters', function () {
    // Arrange
    $controller = new EstimatesController();
    $customerId = Auth::id(); // Get the mocked customer ID

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('only')->once()->with([
        'status', 'estimate_number', 'from_date', 'to_date', 'orderByField', 'orderBy',
    ])->andReturn([]); // No filters applied via request initially

    // Mock Estimate model query chain for the paginated data
    $mockQuery = Mockery::mock();
    $mockQuery->shouldReceive('with')->once()->with(['items', 'customer', 'taxes', 'creator'])->andReturnSelf();
    $mockQuery->shouldReceive('where')->once()->with('status', '<>', 'DRAFT')->andReturnSelf();
    $mockQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockQuery->shouldReceive('applyFilters')->once()->with([])->andReturnSelf();
    $mockQuery->shouldReceive('latest')->once()->andReturnSelf();

    // Mock paginator data that `paginateData` returns
    $mockEstimatesPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockQuery->shouldReceive('paginateData')->once()->with(10)->andReturn($mockEstimatesPaginator);

    // Mock the count query chain for additional meta data
    $mockCountQuery = Mockery::mock();
    $mockCountQuery->shouldReceive('where')->once()->with('status', '<>', 'DRAFT')->andReturnSelf();
    $mockCountQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockCountQuery->shouldReceive('count')->once()->andReturn(5); // Simulate total count

    // Alias the Estimate model to control its static methods for both queries
    $estimateAliasMock = Mockery::mock('alias:Crater\Models\Estimate');
    $estimateAliasMock->shouldReceive('with') // For the main query chain (starting with `with`)
        ->once()
        ->andReturn($mockQuery);
    $estimateAliasMock->shouldReceive('where') // For the count query chain (starting with `where`)
        ->once()
        ->andReturn($mockCountQuery);

    // Replace the real EstimateResource::collection with our mock
    // This mock represents the AnonymousResourceCollection that `collection` returns,
    // and it must have an `additional` method.
    Mockery::mock('alias:Crater\Http\Resources\Customer\EstimateResource')
        ->shouldReceive('collection')
        ->once()
        ->with($mockEstimatesPaginator) // Ensure it's called with the paginator
        ->andReturnUsing(function ($estimates) {
            $collectionResultMock = Mockery::mock();
            // The `additional` method must be called with the correct meta data
            $collectionResultMock->shouldReceive('additional')->once()->with(['meta' => ['estimateTotalCount' => 5]])->andReturnSelf();
            return $collectionResultMock;
        });

    // Act
    $response = (new EstimatesController())->index($request);

    // Assert
    // The response is the mock object returned by `collection(...)->additional(...)`
    expect($response)->toBeInstanceOf(\Mockery\MockInterface::class);
});

test('index method returns estimates with custom limit and all filters', function () {
    // Arrange
    $controller = new EstimatesController();
    $customerId = Auth::id(); // Get the mocked customer ID
    $customLimit = 25;
    $requestFilters = [
        'status' => 'APPROVED',
        'estimate_number' => 'EST-001',
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'orderByField' => 'created_at',
        'orderBy' => 'desc',
    ];

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->shouldReceive('get')->with('limit')->andReturn($customLimit); // For $request->limit
    $request->limit = $customLimit; // Simulate property access
    $request->shouldReceive('only')->once()->with([
        'status', 'estimate_number', 'from_date', 'to_date', 'orderByField', 'orderBy',
    ])->andReturn($requestFilters);

    // Mock Estimate model query chain for the paginated data
    $mockQuery = Mockery::mock();
    $mockQuery->shouldReceive('with')->once()->with(['items', 'customer', 'taxes', 'creator'])->andReturnSelf();
    $mockQuery->shouldReceive('where')->once()->with('status', '<>', 'DRAFT')->andReturnSelf();
    $mockQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockQuery->shouldReceive('applyFilters')->once()->with($requestFilters)->andReturnSelf();
    $mockQuery->shouldReceive('latest')->once()->andReturnSelf();

    $mockEstimatesPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockQuery->shouldReceive('paginateData')->once()->with($customLimit)->andReturn($mockEstimatesPaginator);

    // Mock the count query chain for additional meta data
    $mockCountQuery = Mockery::mock();
    $mockCountQuery->shouldReceive('where')->once()->with('status', '<>', 'DRAFT')->andReturnSelf();
    $mockCountQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockCountQuery->shouldReceive('count')->once()->andReturn(10); // Simulate total count

    // Alias the Estimate model to control its static methods for both queries
    $estimateAliasMock = Mockery::mock('alias:Crater\Models\Estimate');
    $estimateAliasMock->shouldReceive('with')
        ->once()
        ->andReturn($mockQuery);
    $estimateAliasMock->shouldReceive('where')
        ->once()
        ->andReturn($mockCountQuery);

    Mockery::mock('alias:Crater\Http\Resources\Customer\EstimateResource')
        ->shouldReceive('collection')
        ->once()
        ->with($mockEstimatesPaginator)
        ->andReturnUsing(function ($estimates) {
            $collectionResultMock = Mockery::mock();
            $collectionResultMock->shouldReceive('additional')->once()->with(['meta' => ['estimateTotalCount' => 10]])->andReturnSelf();
            return $collectionResultMock;
        });

    // Act
    $response = (new EstimatesController())->index($request);

    // Assert
    expect($response)->toBeInstanceOf(\Mockery\MockInterface::class);
});

test('show method returns an estimate if found', function () {
    // Arrange
    $controller = new EstimatesController();
    $customerId = Auth::id();
    $estimateId = 100;

    $mockCompany = Mockery::mock(Company::class);
    $mockEstimateModel = Mockery::mock(Estimate::class); // Represents the found estimate

    // Mock the query chain from company->estimates()
    $mockQuery = Mockery::mock();
    $mockQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockQuery->shouldReceive('where')->once()->with('id', $estimateId)->andReturnSelf();
    $mockQuery->shouldReceive('first')->once()->andReturn($mockEstimateModel); // Estimate is found

    $mockCompany->shouldReceive('estimates')->once()->andReturn($mockQuery);

    // Mock the EstimateResource constructor using 'overload'
    // This allows us to intercept `new EstimateResource($estimate)`
    $mockResourceInstance = Mockery::mock(EstimateResource::class);
    Mockery::mock('overload:Crater\Http\Resources\Customer\EstimateResource')
        ->shouldReceive('__construct')
        ->once()
        ->with($mockEstimateModel)
        ->andReturn($mockResourceInstance); // Return our mock instance

    // Act
    $response = $controller->show($mockCompany, $estimateId);

    // Assert
    // The response should be the mock instance we configured
    expect($response)->toBe($mockResourceInstance);
});

test('show method returns 404 if estimate not found', function () {
    // Arrange
    $controller = new EstimatesController();
    $customerId = Auth::id();
    $estimateId = 999; // Non-existent ID

    $mockCompany = Mockery::mock(Company::class);

    // Mock the query chain from company->estimates()
    $mockQuery = Mockery::mock();
    $mockQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockQuery->shouldReceive('where')->once()->with('id', $estimateId)->andReturnSelf();
    $mockQuery->shouldReceive('first')->once()->andReturn(null); // Estimate not found

    $mockCompany->shouldReceive('estimates')->once()->andReturn($mockQuery);

    // Mock the ResponseFactory instance that the global 'response()' helper retrieves.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockJsonResponse = Mockery::mock(JsonResponse::class);
    $mockJsonResponse->shouldReceive('getData')->andReturn((object)['error' => 'estimate_not_found']);
    $mockJsonResponse->shouldReceive('getStatusCode')->andReturn(404);

    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => 'estimate_not_found'], 404)
        ->andReturn($mockJsonResponse);

    // Bind the mock to the container so `response()` helper uses it
    app()->instance(ResponseFactory::class, $mockResponseFactory);

    // Act
    $response = $controller->show($mockCompany, $estimateId);

    // Assert
    expect($response)->toBe($mockJsonResponse);
    expect($response->getStatusCode())->toBe(404);
    expect($response->getData())->toEqual((object)['error' => 'estimate_not_found']);
});

// Clean up mocks after each test




afterEach(function () {
    Mockery::close();
});
