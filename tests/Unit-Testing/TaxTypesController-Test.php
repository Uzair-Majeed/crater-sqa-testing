<?php

use Crater\Http\Controllers\V1\Admin\Settings\TaxTypesController;
use Crater\Http\Requests\TaxTypeRequest;
use Crater\Http\Resources\TaxTypeResource;
use Crater\Models\TaxType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Response; // For mocking the response() helper
// Helper to mock Query Builder chain methods for Eloquent
if (!function_exists('mockQueryBuilderChain')) {
    function mockQueryBuilderChain($modelMock, $methods)
    {
        $queryBuilderMock = Mockery::mock();
        foreach ($methods as $methodName => $returnValue) {
            $queryBuilderMock->shouldReceive($methodName)->andReturnSelf();
        }
        $queryBuilderMock->shouldReceive(array_key_last($methods))->andReturn($returnValue);

        $modelMock->shouldReceive(array_key_first($methods))->andReturn($queryBuilderMock);
    }
}

// Global helper for `respondJson`
if (!function_exists('respondJson')) {
    function respondJson($code, $message)
    {
        return response()->json(['code' => $code, 'message' => $message]);
    }
}


// Global helper for `respondJson` if it's used in `destroy`
// This is outside the `test` blocks to make it available for all tests
// Note: This is a simplification; in a real scenario, consider defining it in a test helper file
// or refactoring the controller to inject a response factory.
if (!function_exists('respondJson')) {
    function respondJson($code, $message)
    {
        return response()->json(['code' => $code, 'message' => $message]);
    }
}

test('index returns a collection of tax types with default limit', function () {
    $this->withoutExceptionHandling();

    // Mock controller and its authorize method
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('viewAny', TaxType::class)->once();

    // Mock request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('all')->andReturn([]);
    $request->limit = null; // Ensure limit is null when not present

    // Mock TaxType model and its query builder chain
    $taxType1 = Mockery::mock(TaxType::class);
    $taxType1->id = 1;
    $taxType2 = Mockery::mock(TaxType::class);
    $taxType2->id = 2;
    $collection = collect([$taxType1, $taxType2]);

    $paginator = Mockery::mock(LengthAwarePaginator::class);
    $paginator->shouldReceive('resource')->andReturn($collection);

    // Mocking static calls and chained methods on TaxType
    $queryBuilderMock = Mockery::mock();
    $queryBuilderMock->shouldReceive('applyFilters')->with([])->andReturnSelf();
    $queryBuilderMock->shouldReceive('where')->with('type', TaxType::TYPE_GENERAL)->andReturnSelf();
    $queryBuilderMock->shouldReceive('whereCompany')->andReturnSelf();
    $queryBuilderMock->shouldReceive('latest')->andReturnSelf();
    $queryBuilderMock->shouldReceive('paginateData')->with(5)->andReturn($paginator);

    Mockery::mock('alias:' . TaxType::class)
        ->shouldReceive('applyFilters')
        ->andReturn($queryBuilderMock);

    // Call the method
    $response = $controller->index($request);

    // Assertions
    expect($response)->toBeInstanceOf(TaxTypeResource::class);
    expect($response->resource)->toBe($paginator);
});

test('index returns a collection of tax types with custom limit', function () {
    $this->withoutExceptionHandling();

    // Mock controller and its authorize method
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('viewAny', TaxType::class)->once();

    // Mock request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->limit = 10;
    $request->shouldReceive('all')->andReturn(['limit' => 10]);

    // Mock TaxType model and its query builder chain
    $taxType1 = Mockery::mock(TaxType::class);
    $collection = collect([$taxType1]);

    $paginator = Mockery::mock(LengthAwarePaginator::class);
    $paginator->shouldReceive('resource')->andReturn($collection);

    // Mocking static calls and chained methods on TaxType
    $queryBuilderMock = Mockery::mock();
    $queryBuilderMock->shouldReceive('applyFilters')->with(['limit' => 10])->andReturnSelf();
    $queryBuilderMock->shouldReceive('where')->with('type', TaxType::TYPE_GENERAL)->andReturnSelf();
    $queryBuilderMock->shouldReceive('whereCompany')->andReturnSelf();
    $queryBuilderMock->shouldReceive('latest')->andReturnSelf();
    $queryBuilderMock->shouldReceive('paginateData')->with(10)->andReturn($paginator);

    Mockery::mock('alias:' . TaxType::class)
        ->shouldReceive('applyFilters')
        ->andReturn($queryBuilderMock);

    // Call the method
    $response = $controller->index($request);

    // Assertions
    expect($response)->toBeInstanceOf(TaxTypeResource::class);
    expect($response->resource)->toBe($paginator);
});

test('index throws authorization exception', function () {
    // Mock controller and its authorize method to throw an exception
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('viewAny', TaxType::class)->andThrow(AuthorizationException::class)->once();

    // Mock request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('all')->andReturn([]);
    $request->limit = null;

    // Call the method and expect the exception
    $this->expectException(AuthorizationException::class);
    $controller->index($request);
});

test('store creates a new tax type', function () {
    $this->withoutExceptionHandling();

    // Mock controller and its authorize method
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('create', TaxType::class)->once();

    // Mock TaxTypeRequest
    $payload = ['name' => 'VAT', 'percent' => 20];
    $request = Mockery::mock(TaxTypeRequest::class);
    $request->shouldReceive('getTaxTypePayload')->andReturn($payload);

    // Mock TaxType model's static create method
    $createdTaxType = Mockery::mock(TaxType::class);
    $createdTaxType->id = 1;
    $createdTaxType->name = 'VAT';
    $createdTaxType->percent = 20;

    Mockery::mock('alias:' . TaxType::class)
        ->shouldReceive('create')->with($payload)->andReturn($createdTaxType)->once();

    // Call the method
    $response = $controller->store($request);

    // Assertions
    expect($response)->toBeInstanceOf(TaxTypeResource::class);
    expect($response->resource)->toBe($createdTaxType);
});

test('store throws authorization exception', function () {
    // Mock controller and its authorize method to throw an exception
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('create', TaxType::class)->andThrow(AuthorizationException::class)->once();

    // Mock TaxTypeRequest
    $request = Mockery::mock(TaxTypeRequest::class);
    $request->shouldReceive('getTaxTypePayload')->andReturn([]);

    // Call the method and expect the exception
    $this->expectException(AuthorizationException::class);
    $controller->store($request);
});

test('show returns the specified tax type', function () {
    $this->withoutExceptionHandling();

    // Mock controller and its authorize method
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('view', Mockery::type(TaxType::class))->once();

    // Mock TaxType model
    $taxType = Mockery::mock(TaxType::class);
    $taxType->id = 1;

    // Call the method
    $response = $controller->show($taxType);

    // Assertions
    expect($response)->toBeInstanceOf(TaxTypeResource::class);
    expect($response->resource)->toBe($taxType);
});

test('show throws authorization exception', function () {
    // Mock controller and its authorize method to throw an exception
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('view', Mockery::type(TaxType::class))->andThrow(AuthorizationException::class)->once();

    // Mock TaxType model
    $taxType = Mockery::mock(TaxType::class);
    $taxType->id = 1;

    // Call the method and expect the exception
    $this->expectException(AuthorizationException::class);
    $controller->show($taxType);
});

test('update updates the specified tax type', function () {
    $this->withoutExceptionHandling();

    // Mock controller and its authorize method
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('update', Mockery::type(TaxType::class))->once();

    // Mock TaxTypeRequest
    $payload = ['name' => 'Updated VAT', 'percent' => 22];
    $request = Mockery::mock(TaxTypeRequest::class);
    $request->shouldReceive('getTaxTypePayload')->andReturn($payload);

    // Mock TaxType model (injected)
    $taxType = Mockery::mock(TaxType::class);
    $taxType->id = 1;
    $taxType->shouldReceive('update')->with($payload)->andReturn(true)->once(); // Assuming update returns boolean

    // Call the method
    $response = $controller->update($request, $taxType);

    // Assertions
    expect($response)->toBeInstanceOf(TaxTypeResource::class);
    expect($response->resource)->toBe($taxType);
});

test('update throws authorization exception', function () {
    // Mock controller and its authorize method to throw an exception
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('update', Mockery::type(TaxType::class))->andThrow(AuthorizationException::class)->once();

    // Mock TaxTypeRequest
    $request = Mockery::mock(TaxTypeRequest::class);
    $request->shouldReceive('getTaxTypePayload')->andReturn([]);

    // Mock TaxType model
    $taxType = Mockery::mock(TaxType::class);
    $taxType->id = 1;

    // Call the method and expect the exception
    $this->expectException(AuthorizationException::class);
    $controller->update($request, $taxType);
});

test('destroy deletes the tax type if no taxes are attached', function () {
    $this->withoutExceptionHandling();

    // Mock controller and its authorize method
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('delete', Mockery::type(TaxType::class))->once();

    // Mock TaxType model
    $taxType = Mockery::mock(TaxType::class);
    $taxType->id = 1;

    // Mock the 'taxes' relationship and count
    $relatedTaxesMock = Mockery::mock();
    $relatedTaxesMock->shouldReceive('count')->andReturn(0)->once();
    $taxType->shouldReceive('taxes')->andReturn($relatedTaxesMock)->once();

    // Mock delete method
    $taxType->shouldReceive('delete')->andReturn(true)->once();

    // Mock the global response() helper for the success case
    Response::shouldReceive('json')
        ->with(['success' => true])
        ->andReturn(new \Illuminate\Http\JsonResponse(['success' => true]))
        ->once();

    // Call the method
    $response = $controller->destroy($taxType);

    // Assertions
    expect($response->getData())->toEqual((object)['success' => true]);
    expect($response->getStatusCode())->toBe(200);
});

test('destroy returns error if taxes are attached', function () {
    $this->withoutExceptionHandling();

    // Mock controller and its authorize method
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('delete', Mockery::type(TaxType::class))->once();

    // Mock TaxType model
    $taxType = Mockery::mock(TaxType::class);
    $taxType->id = 1;

    // Mock the 'taxes' relationship and count to be > 0
    $relatedTaxesMock = Mockery::mock();
    $relatedTaxesMock->shouldReceive('count')->andReturn(1)->once();
    $taxType->shouldReceive('taxes')->andReturn($relatedTaxesMock)->once();

    // Ensure delete is NOT called
    $taxType->shouldNotReceive('delete');

    // Mock the `respondJson` global helper's return value.
    // Since `respondJson` is a global function, we cannot mock it directly like a class method.
    // We assume it returns a JsonResponse and assert against that.
    // The `respondJson` helper is defined at the top of this test file for execution purposes.
    $response = $controller->destroy($taxType);

    // Assertions
    expect($response->getData())->toEqual((object)['code' => 'taxes_attached', 'message' => 'Taxes Attached.']);
    expect($response->getStatusCode())->toBe(200); // Default status for Laravel JsonResponse
});

test('destroy throws authorization exception', function () {
    // Mock controller and its authorize method to throw an exception
    $controller = Mockery::mock(TaxTypesController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->with('delete', Mockery::type(TaxType::class))->andThrow(AuthorizationException::class)->once();

    // Mock TaxType model
    $taxType = Mockery::mock(TaxType::class);
    $taxType->id = 1;

    // Call the method and expect the exception
    $this->expectException(AuthorizationException::class);
    $controller->destroy($taxType);
});
