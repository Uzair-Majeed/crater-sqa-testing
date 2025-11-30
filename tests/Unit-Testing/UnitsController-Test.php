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
uses(\Mockery::class);

beforeEach(function () {
    Mockery::close(); // Ensure mocks are cleaned up before each test
});

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
        Mockery::mock('alias:' . Unit::class)
            ->shouldReceive('applyFilters')
            ->andReturn($mockBuilder);

        // Mock UnitResource::collection static method
        Mockery::mock('alias:' . UnitResource::class)
            ->shouldReceive('collection')
            ->with($paginator)
            ->andReturn('unit_resource_collection'); // Simulate resource collection

        $response = $controller->index($request);

        expect($response)->toBe('unit_resource_collection');
    });

    test('index method returns a collection of units with custom limit and filters', function () {
        $controller = Mockery::mock(UnitsController::class)->makePartial();
        $controller->shouldReceive('authorize')->with('viewAny', Unit::class)->andReturn(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('has')->with('limit')->andReturn(true);
        $request->limit = 10; // Directly assign for property access
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

        Mockery::mock('alias:' . UnitResource::class)
            ->shouldReceive('collection')
            ->with($paginator)
            ->andReturn('unit_resource_collection_custom_limit');

        $response = $controller->index($request);

        expect($response)->toBe('unit_resource_collection_custom_limit');
    });

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
            ->once();

        $response = $controller->store($unitRequest);

        expect($response)->toBeInstanceOf(UnitResource::class);
    });

    test('show method returns the specified unit resource', function () {
        $controller = Mockery::mock(UnitsController::class)->makePartial();
        $mockUnit = Mockery::mock(Unit::class); // The unit passed to show
        $controller->shouldReceive('authorize')->with('view', $mockUnit)->andReturn(true);

        // Mock the UnitResource constructor call using overload
        Mockery::mock('overload:' . UnitResource::class)
            ->shouldReceive('__construct')
            ->with($mockUnit)
            ->once();

        $response = $controller->show($mockUnit);

        expect($response)->toBeInstanceOf(UnitResource::class);
    });

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
            ->once();

        $response = $controller->update($unitRequest, $mockUnit);

        expect($response)->toBeInstanceOf(UnitResource::class);
    });

    test('destroy method returns an error if unit has attached items', function () {
        $controller = Mockery::mock(UnitsController::class)->makePartial();
        $mockUnit = Mockery::mock(Unit::class);
        $controller->shouldReceive('authorize')->with('delete', $mockUnit)->andReturn(true);

        $mockHasMany = Mockery::mock(HasMany::class);
        $mockUnit->shouldReceive('items')->andReturn($mockHasMany);
        $mockHasMany->shouldReceive('exists')->andReturn(true); // Simulate attached items

        // Assuming 'respondJson' is a globally available helper that returns JsonResponse
        // We assert on its expected output structure and status.
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
        $mockJsonResponse->shouldReceive('getData')->andReturn(['success' => 'Unit deleted successfully']);
        $mockResponseFactory->shouldReceive('json')->with(['success' => 'Unit deleted successfully'])->andReturn($mockJsonResponse);

        // Use app()->instance to replace the ResponseFactory service in the container
        app()->instance(ResponseFactory::class, $mockResponseFactory);

        $response = $controller->destroy($mockUnit);

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBe('Unit deleted successfully');
    });
