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

// Clean and fresh test isolation using Pest's `uses()` with Laravel's test case
uses(\Tests\TestCase::class);

// Mock the Auth facade for all tests in this file
beforeEach(function () {
    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn(1); // Simulate customer ID 1
});

test('index method returns estimates with default limit and no filters', function () {
    // Arrange
    $controller = new EstimatesController();
    $customerId = Auth::id();

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('only')->once()->with([
        'status', 'estimate_number', 'from_date', 'to_date', 'orderByField', 'orderBy',
    ])->andReturn([]);

    // Mock Estimate model query chain for the paginated data
    $mockQuery = Mockery::mock();
    $mockQuery->shouldReceive('with')->once()->with(['items', 'customer', 'taxes', 'creator'])->andReturnSelf();
    $mockQuery->shouldReceive('where')->once()->with('status', '<>', 'DRAFT')->andReturnSelf();
    $mockQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockQuery->shouldReceive('applyFilters')->once()->with([])->andReturnSelf();
    $mockQuery->shouldReceive('latest')->once()->andReturnSelf();

    $mockEstimatesPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockQuery->shouldReceive('paginateData')->once()->with(10)->andReturn($mockEstimatesPaginator);

    // Mock the count query chain for additional meta data
    $mockCountQuery = Mockery::mock();
    $mockCountQuery->shouldReceive('where')->once()->with('status', '<>', 'DRAFT')->andReturnSelf();
    $mockCountQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockCountQuery->shouldReceive('count')->once()->andReturn(5);

    // Alias the Estimate model to control its static methods (unique alias per test to avoid Mockery collision)
    $estimateAliasMock = Mockery::mock('alias:Crater\Models\Estimate_index_default');
    $estimateAliasMock->shouldReceive('with')
        ->once()
        ->andReturn($mockQuery);
    $estimateAliasMock->shouldReceive('where')
        ->once()
        ->andReturn($mockCountQuery);

    // Replace EstimateResource::collection with mock
    Mockery::mock('alias:Crater\Http\Resources\Customer\EstimateResource_index_default')
        ->shouldReceive('collection')
        ->once()
        ->with($mockEstimatesPaginator)
        ->andReturnUsing(function ($estimates) {
            $collectionResultMock = Mockery::mock();
            $collectionResultMock->shouldReceive('additional')->once()->with(['meta' => ['estimateTotalCount' => 5]])->andReturnSelf();
            return $collectionResultMock;
        });

    // Act
    // Temporarily swap class_alias so EstimatesController uses our alias
    class_alias('Crater\Models\Estimate_index_default', 'Crater\Models\Estimate');
    class_alias('Crater\Http\Resources\Customer\EstimateResource_index_default', 'Crater\Http\Resources\Customer\EstimateResource');
    $response = $controller->index($request);
    // Reset alias for next test cleanup
    class_alias('Crater\Models\Estimate', 'Crater\Models\Estimate_index_default');
    class_alias('Crater\Http\Resources\Customer\EstimateResource', 'Crater\Http\Resources\Customer\EstimateResource_index_default');

    // Assert
    expect($response)->toBeInstanceOf(\Mockery\MockInterface::class);
});

test('index method returns estimates with custom limit and all filters', function () {
    // Arrange
    $controller = new EstimatesController();
    $customerId = Auth::id();
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
    $request->shouldReceive('get')->with('limit')->andReturn($customLimit);
    $request->limit = $customLimit;
    $request->shouldReceive('only')->once()->with([
        'status', 'estimate_number', 'from_date', 'to_date', 'orderByField', 'orderBy',
    ])->andReturn($requestFilters);

    // Mock Estimate model query chain
    $mockQuery = Mockery::mock();
    $mockQuery->shouldReceive('with')->once()->with(['items', 'customer', 'taxes', 'creator'])->andReturnSelf();
    $mockQuery->shouldReceive('where')->once()->with('status', '<>', 'DRAFT')->andReturnSelf();
    $mockQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockQuery->shouldReceive('applyFilters')->once()->with($requestFilters)->andReturnSelf();
    $mockQuery->shouldReceive('latest')->once()->andReturnSelf();

    $mockEstimatesPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockQuery->shouldReceive('paginateData')->once()->with($customLimit)->andReturn($mockEstimatesPaginator);

    // Mock the count query chain
    $mockCountQuery = Mockery::mock();
    $mockCountQuery->shouldReceive('where')->once()->with('status', '<>', 'DRAFT')->andReturnSelf();
    $mockCountQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockCountQuery->shouldReceive('count')->once()->andReturn(10);

    $estimateAliasMock = Mockery::mock('alias:Crater\Models\Estimate_index_custom');
    $estimateAliasMock->shouldReceive('with')
        ->once()
        ->andReturn($mockQuery);
    $estimateAliasMock->shouldReceive('where')
        ->once()
        ->andReturn($mockCountQuery);

    Mockery::mock('alias:Crater\Http\Resources\Customer\EstimateResource_index_custom')
        ->shouldReceive('collection')
        ->once()
        ->with($mockEstimatesPaginator)
        ->andReturnUsing(function ($estimates) {
            $collectionResultMock = Mockery::mock();
            $collectionResultMock->shouldReceive('additional')->once()->with(['meta' => ['estimateTotalCount' => 10]])->andReturnSelf();
            return $collectionResultMock;
        });

    // Act
    class_alias('Crater\Models\Estimate_index_custom', 'Crater\Models\Estimate');
    class_alias('Crater\Http\Resources\Customer\EstimateResource_index_custom', 'Crater\Http\Resources\Customer\EstimateResource');
    $response = $controller->index($request);
    class_alias('Crater\Models\Estimate', 'Crater\Models\Estimate_index_custom');
    class_alias('Crater\Http\Resources\Customer\EstimateResource', 'Crater\Http\Resources\Customer\EstimateResource_index_custom');

    // Assert
    expect($response)->toBeInstanceOf(\Mockery\MockInterface::class);
});

test('show method returns an estimate if found', function () {
    // Arrange
    $controller = new EstimatesController();
    $customerId = Auth::id();
    $estimateId = 100;

    $mockCompany = Mockery::mock(Company::class);
    $mockEstimateModel = Mockery::mock(Estimate::class);

    $mockQuery = Mockery::mock();
    $mockQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockQuery->shouldReceive('where')->once()->with('id', $estimateId)->andReturnSelf();
    $mockQuery->shouldReceive('first')->once()->andReturn($mockEstimateModel);

    $mockCompany->shouldReceive('estimates')->once()->andReturn($mockQuery);

    // Overload the EstimateResource constructor
    $mockResourceInstance = Mockery::mock(EstimateResource::class);
    Mockery::mock('overload:Crater\Http\Resources\Customer\EstimateResource_show_found')
        ->shouldReceive('__construct')
        ->once()
        ->with($mockEstimateModel)
        ->andReturn($mockResourceInstance);

    // Act
    class_alias('Crater\Http\Resources\Customer\EstimateResource_show_found', 'Crater\Http\Resources\Customer\EstimateResource');
    $response = $controller->show($mockCompany, $estimateId);
    class_alias('Crater\Http\Resources\Customer\EstimateResource', 'Crater\Http\Resources\Customer\EstimateResource_show_found');

    // Assert
    expect($response)->toBe($mockResourceInstance);
});

test('show method returns 404 if estimate not found', function () {
    // Arrange
    $controller = new EstimatesController();
    $customerId = Auth::id();
    $estimateId = 999;

    $mockCompany = Mockery::mock(Company::class);

    $mockQuery = Mockery::mock();
    $mockQuery->shouldReceive('whereCustomer')->once()->with($customerId)->andReturnSelf();
    $mockQuery->shouldReceive('where')->once()->with('id', $estimateId)->andReturnSelf();
    $mockQuery->shouldReceive('first')->once()->andReturn(null);

    $mockCompany->shouldReceive('estimates')->once()->andReturn($mockQuery);

    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockJsonResponse = Mockery::mock(JsonResponse::class);
    $mockJsonResponse->shouldReceive('getData')->andReturn((object)['error' => 'estimate_not_found']);
    $mockJsonResponse->shouldReceive('getStatusCode')->andReturn(404);

    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => 'estimate_not_found'], 404)
        ->andReturn($mockJsonResponse);

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