```php
<?php

use Crater\Http\Controllers\V1\Admin\Item\UnitsController;
use Crater\Http\Requests\UnitRequest;
use Crater\Http\Resources\UnitResource;
use Crater\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection; // Import for UnitResource::collection return type

beforeEach(function () {
    Mockery::close(); // Ensure mocks are cleaned up before each test
});

test('index method returns a collection of units with default limit', function () {
    // Tests using 'alias:' or 'overload:' mocks must run in separate processes to prevent "Cannot redeclare" errors.
    // Mockery::mock('alias:...') creates a real class that persists across tests if not run in isolation.
})->runsInSeparateProcess();

test('index method returns a collection of units with default limit', function () {
    $controller = Mockery::mock(UnitsController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('viewAny', Unit::class)->andReturn(true);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('all')->andReturn([]); // No filters

    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('applyFilters')->with([])->andReturnSelf();
    $mockBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $mockBuilder->shouldReceive('latest')->andReturnSelf();

    $paginator = Mockery::mock(LengthAwarePaginator::class);
    $mockBuilder->shouldReceive('paginateData')->with(5)->andReturn($paginator);

    // Mock Unit::class static methods to start the chain
    // For Eloquent models, `applyFilters` is often a scope method, so `Unit::query()` is the starting point.
    // If `applyFilters` is a static method that returns a Builder, the existing alias mock is correct for its behavior.
    Mockery::mock('alias:' . Unit::class)
        ->shouldReceive('applyFilters')
        ->andReturn($mockBuilder);

    // Mock UnitResource::collection static method
    // JsonResource::collection returns an AnonymousResourceCollection.
    $mockResourceCollection = Mockery::mock(AnonymousResourceCollection::class);
    Mockery::mock('alias:' . UnitResource::class)
        ->shouldReceive('collection')
        ->with($paginator)
        ->andReturn($mockResourceCollection); // Return a mock of the actual collection type

    $response = $controller->index($request);

    // Assert that the response is an instance of AnonymousResourceCollection
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
})->runsInSeparateProcess();

test('index method returns a collection of units with custom limit and filters', function () {
    $controller = Mockery::mock(UnitsController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('viewAny', Unit::class)->andReturn(true);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    // For property access (e.g., $request->limit), it's often better to mock the `input` or `get` methods
    // or set a public property on a mock of a concrete Request class if the controller directly accesses properties.
    // For strict `Request` mock, this would be $request->shouldReceive('input')->with('limit')->andReturn(10);
    // Given the current setup directly assigning `limit` and `all()`, we'll assume `request->limit` works.
    $request->limit = 10;
    $request->shouldReceive('all')->andReturn(['limit' => 10, 'search' => 'test']); // With filters

    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('applyFilters')->with(['limit' => 10, 'search' => 'test'])->andReturnSelf();
    $mockBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $mockBuilder->shouldReceive('latest')->andReturnSelf();

    $paginator = Mockery::mock(LengthAwarePaginator::class);
    $mockBuilder->shouldReceive('paginateData')->with(10)->andReturn($paginator);

    Mockery::mock('alias:' . Unit::class)
        ->shouldReceive('applyFilters')
        ->andReturn($mockBuilder);

    // Mock UnitResource::collection static method
    // JsonResource::collection returns an AnonymousResourceCollection.
    $mockResourceCollection = Mockery::mock(AnonymousResourceCollection::class);
    Mockery::mock('alias:' . UnitResource::class)
        ->shouldReceive('collection')
        ->with($paginator)
        ->andReturn($mockResourceCollection); // Return a mock of the actual collection type

    $response = $controller->index($request);

    // Assert that the response is an instance of AnonymousResourceCollection
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
})->runsInSeparateProcess();

test('store method creates and returns a new unit resource', function () {
    $controller = Mockery::mock(UnitsController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('create', Unit::class)->andReturn(true);

    $unitPayload = ['name' => 'New Unit'];
    $unitRequest = Mockery::mock(UnitRequest::class);
    $unitRequest->shouldReceive('getUnitPayload')->andReturn($unitPayload);

    $newUnit = Mockery::mock(Unit::class); // A mock for the created unit instance
    Mockery::mock('alias:' . Unit::class)
        ->shouldReceive('create')
        ->with($unitPayload)
        ->andReturn($newUnit);

    // Mock the UnitResource constructor call using overload
    Mockery::mock('overload:' . UnitResource::class)
        ->shouldReceive('__construct')
        ->with($newUnit)
        ->once()
        ->andReturnNull(); // __construct should return null

    $response = $controller->store($unitRequest);

    expect($response)->toBeInstanceOf(UnitResource::class);
})->runsInSeparateProcess();

test('show method returns the specified unit resource', function () {
    $controller = Mockery::mock(UnitsController::class)->makePartial();
    $mockUnit = Mockery::mock(Unit::class); // The unit passed to show
    $controller->shouldReceive('authorize')->with('view', $mockUnit)->andReturn(true);

    // Mock the UnitResource constructor call using overload
    Mockery::mock('overload:' . UnitResource::class)
        ->shouldReceive('__construct')
        ->with($mockUnit)
        ->once()
        ->andReturnNull(); // __construct should return null

    $response = $controller->show($mockUnit);

    expect($response)->toBeInstanceOf(UnitResource::class);
})->runsInSeparateProcess();

test('update method updates the specified unit and returns its resource', function () {
    $controller = Mockery::mock(UnitsController::class)->makePartial();
    $mockUnit = Mockery::mock(Unit::class); // The unit passed to update
    $controller->shouldReceive('authorize')->with('update', $mockUnit)->andReturn(true);

    $unitPayload = ['name' => 'Updated Unit Name'];
    $unitRequest = Mockery::mock(UnitRequest::class);
    $unitRequest->shouldReceive('getUnitPayload')->andReturn($unitPayload);

    $mockUnit->shouldReceive('update')->with($unitPayload)->once()->andReturn(true);

    // Mock the UnitResource constructor call using overload
    Mockery::mock('overload:' . UnitResource::class)
        ->shouldReceive('__construct')
        ->with($mockUnit)
        ->once()
        ->andReturnNull(); // __construct should return null

    $response = $controller->update($unitRequest, $mockUnit);

    expect($response)->toBeInstanceOf(UnitResource::class);
})->runsInSeparateProcess();

test('destroy method returns an error if unit has attached items', function () {
    $controller = Mockery::mock(UnitsController::class)->makePartial();
    $mockUnit = Mockery::mock(Unit::class);
    $controller->shouldReceive('authorize')->with('delete', $mockUnit)->andReturn(true);

    $mockHasMany = Mockery::mock(HasMany::class);
    $mockUnit->shouldReceive('items')->andReturn($mockHasMany);
    $mockHasMany->shouldReceive('exists')->andReturn(true); // Simulate attached items

    // Mock Laravel's `response()` helper by binding a mock ResponseFactory to the container
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockJsonResponse = Mockery::mock(JsonResponse::class);

    // Assuming the controller calls `response()->json(['message' => 'items_attached', 'data' => 'Items Attached'], 422)`
    $expectedData = ['message' => 'items_attached', 'data' => 'Items Attached'];
    $expectedStatus = 422;

    $mockJsonResponse->shouldReceive('getData')->with(true)->andReturn($expectedData);
    $mockJsonResponse->shouldReceive('getStatusCode')->andReturn($expectedStatus);
    $mockResponseFactory->shouldReceive('json')
        ->with($expectedData, $expectedStatus)
        ->andReturn($mockJsonResponse);

    app()->instance(ResponseFactory::class, $mockResponseFactory); // Replace the real ResponseFactory

    $response = $controller->destroy($mockUnit);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true)['message'])->toBe('items_attached')
        ->and($response->getData(true)['data'])->toBe('Items Attached')
        ->and($response->getStatusCode())->toBe(422);

    $mockUnit->shouldNotHaveReceived('delete'); // Ensure delete was not called
});

test('destroy method deletes the unit if no attached items', function () {
    $controller = Mockery::mock(UnitsController::class)->makePartial();
    $mockUnit = Mockery::mock(Unit::class);
    $controller->shouldReceive('authorize')->with('delete', $mockUnit)->andReturn(true);

    $mockHasMany = Mockery::mock(HasMany::class);
    $mockUnit->shouldReceive('items')->andReturn($mockHasMany);
    $mockHasMany->shouldReceive('exists')->andReturn(false); // Simulate no attached items

    $mockUnit->shouldReceive('delete')->once()->andReturn(true);

    // Mock Laravel's `response()` helper by binding a mock ResponseFactory to the container
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockJsonResponse = Mockery::mock(JsonResponse::class);

    $expectedData = ['success' => 'Unit deleted successfully'];
    $expectedStatus = 200; // Assuming a successful deletion returns 200 OK by default for a JSON response

    $mockJsonResponse->shouldReceive('getData')->with(true)->andReturn($expectedData);
    $mockJsonResponse->shouldReceive('getStatusCode')->andReturn($expectedStatus); // Set expected status code
    $mockResponseFactory->shouldReceive('json')
        ->with($expectedData, $expectedStatus) // Include status code in expected arguments
        ->andReturn($mockJsonResponse);

    // Use app()->instance to replace the ResponseFactory service in the container
    app()->instance(ResponseFactory::class, $mockResponseFactory);

    $response = $controller->destroy($mockUnit);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true)['success'])->toBe('Unit deleted successfully')
        ->and($response->getStatusCode())->toBe(200); // Assert the status code
});

afterEach(function () {
    Mockery::close();
});
```