<?php

use Crater\Http\Controllers\V1\Admin\Expense\ExpenseCategoriesController;
use Crater\Http\Requests\ExpenseCategoryRequest;
use Crater\Http\Resources\ExpenseCategoryResource;
use Crater\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Mockery as m;

// Use Pest's test isolation for each test run
uses()->group('expense-category-controller');

// Helper to clear aliases and static mocks between tests to avoid redeclare errors
function resetMockAliases()
{
    if (class_exists('Mockery_ExpenseCategory_Alias')) {
        class_alias(ExpenseCategory::class, 'Mockery_ExpenseCategory_Alias', true);
    }
    if (class_exists('Mockery_ExpenseCategoryResource_Alias')) {
        class_alias(ExpenseCategoryResource::class, 'Mockery_ExpenseCategoryResource_Alias', true);
    }
    m::close();
}

// Set up Mockery and mock common dependencies before each test
beforeEach(function () {
    resetMockAliases();

    $this->controller = m::mock(ExpenseCategoriesController::class)->makePartial();
    $this->controller->shouldAllowMockingProtectedMethods();
    $this->controller->shouldReceive('authorize')->zeroOrMoreTimes()->andReturn(true);

    // Remove previous alias mocks to avoid duplicate redeclaration
    m::close();

    // Set up mocks for alias classes for statics.
    // ExpenseCategory
    m::close();
    m::getConfiguration()->setConstantsMap([]);
    m::getConfiguration()->resetGlobally();

    $this->expenseCategoryAlias = m::mock('alias:' . ExpenseCategory::class);
    $this->expenseCategoryResourceAlias = m::mock('alias:' . ExpenseCategoryResource::class);
    // For the response helper
    $this->responseAlias = m::mock('alias:response');
});

// Close Mockery mocks after each test to prevent memory leaks and ensure clean state.
afterEach(function () {
    m::close();
    // Unset aliases to avoid "redeclare" mock errors
    unset($this->expenseCategoryAlias, $this->expenseCategoryResourceAlias, $this->responseAlias);
    unset($this->controller);
});

test('index displays a listing of expense categories with default limit', function () {
    $request = m::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('all')->andReturn([]);

    $builder = m::mock(Builder::class);
    $paginator = m::mock(LengthAwarePaginator::class);
    $paginator->shouldReceive('resource')->andReturn(new Collection());

    $this->expenseCategoryAlias
        ->shouldReceive('applyFilters')->with([])->andReturn($builder)
        ->shouldReceive('whereCompany')->andReturn($builder)
        ->shouldReceive('latest')->andReturn($builder)
        ->shouldReceive('paginateData')->with(5)->andReturn($paginator);

    $anonResourceCollection = m::mock('Illuminate\Http\Resources\Json\AnonymousResourceCollection');
    $this->expenseCategoryResourceAlias
        ->shouldReceive('collection')
        ->with($paginator)
        ->andReturn($anonResourceCollection);

    $response = $this->controller->index($request);

    expect($response)->toBeInstanceOf('Illuminate\Http\Resources\Json\AnonymousResourceCollection');
    $this->controller->shouldHaveReceived('authorize')->once()->with('viewAny', ExpenseCategory::class);
});

test('index displays a listing of expense categories with custom limit', function () {
    $request = m::mock(Request::class);
    $customLimit = 10;
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->limit = $customLimit;
    $request->shouldReceive('all')->andReturn(['limit' => $customLimit]);

    $builder = m::mock(Builder::class);
    $paginator = m::mock(LengthAwarePaginator::class);
    $paginator->shouldReceive('resource')->andReturn(new Collection());

    $this->expenseCategoryAlias
        ->shouldReceive('applyFilters')->with(['limit' => $customLimit])->andReturn($builder)
        ->shouldReceive('whereCompany')->andReturn($builder)
        ->shouldReceive('latest')->andReturn($builder)
        ->shouldReceive('paginateData')->with($customLimit)->andReturn($paginator);

    $anonResourceCollection = m::mock('Illuminate\Http\Resources\Json\AnonymousResourceCollection');
    $this->expenseCategoryResourceAlias
        ->shouldReceive('collection')
        ->with($paginator)
        ->andReturn($anonResourceCollection);

    $response = $this->controller->index($request);

    expect($response)->toBeInstanceOf('Illuminate\Http\Resources\Json\AnonymousResourceCollection');
    $this->controller->shouldHaveReceived('authorize')->once()->with('viewAny', ExpenseCategory::class);
});

test('store creates a new expense category successfully', function () {
    $request = m::mock(ExpenseCategoryRequest::class);
    $payload = ['name' => 'New Category', 'description' => 'A new expense category'];
    $request->shouldReceive('getExpenseCategoryPayload')->andReturn($payload);

    $category = m::mock(ExpenseCategory::class);
    $category->name = 'New Category';

    $this->expenseCategoryAlias
        ->shouldReceive('create')
        ->with($payload)
        ->andReturn($category);

    // Resource instance for return value
    $resourceInstance = new ExpenseCategoryResource($category);
    $this->expenseCategoryResourceAlias
        ->shouldReceive('__construct')
        ->with($category)
        ->once()
        ->andReturn($resourceInstance);

    $response = $this->controller->store($request);

    expect($response)->toBeInstanceOf(ExpenseCategoryResource::class);
    expect($response->resource)->toBe($category);
    $this->controller->shouldHaveReceived('authorize')->once()->with('create', ExpenseCategory::class);
});

test('show displays the specified expense category successfully', function () {
    $category = m::mock(ExpenseCategory::class);
    $category->id = 1;

    $resourceInstance = new ExpenseCategoryResource($category);
    $this->expenseCategoryResourceAlias
        ->shouldReceive('__construct')
        ->with($category)
        ->once()
        ->andReturn($resourceInstance);

    $response = $this->controller->show($category);

    expect($response)->toBeInstanceOf(ExpenseCategoryResource::class);
    expect($response->resource)->toBe($category);
    $this->controller->shouldHaveReceived('authorize')->once()->with('view', $category);
});

test('update updates the specified expense category successfully', function () {
    $request = m::mock(ExpenseCategoryRequest::class);
    $payload = ['name' => 'Updated Category', 'description' => 'Updated description'];
    $request->shouldReceive('getExpenseCategoryPayload')->andReturn($payload);

    $category = m::mock(ExpenseCategory::class);
    $category->id = 1;
    $category->shouldReceive('update')
        ->with($payload)
        ->once()
        ->andReturn(true);

    $resourceInstance = new ExpenseCategoryResource($category);
    $this->expenseCategoryResourceAlias
        ->shouldReceive('__construct')
        ->with($category)
        ->once()
        ->andReturn($resourceInstance);

    $response = $this->controller->update($request, $category);

    expect($response)->toBeInstanceOf(ExpenseCategoryResource::class);
    expect($response->resource)->toBe($category);
    $this->controller->shouldHaveReceived('authorize')->once()->with('update', $category);
});

test('destroy deletes an expense category when no expenses are attached', function () {
    $category = m::mock(ExpenseCategory::class);
    $category->id = 1;

    $expensesRelation = m::mock('stdClass');
    $expensesRelation->shouldReceive('count')->andReturn(0);
    $category->shouldReceive('expenses')->andReturn($expensesRelation);
    $category->shouldReceive('delete')->once()->andReturn(true);

    $jsonResponse = m::mock(JsonResponse::class);
    $jsonResponse->shouldReceive('getData')->andReturn((object)['success' => true]);

    $this->responseAlias
        ->shouldReceive('json')
        ->with(['success' => true])
        ->andReturn($jsonResponse);

    $response = $this->controller->destroy($category);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true]);
    $category->shouldHaveReceived('delete')->once();
    $this->controller->shouldHaveReceived('authorize')->once()->with('delete', $category);
});

test('destroy returns error if expense category has attached expenses', function () {
    $category = m::mock(ExpenseCategory::class);
    $category->id = 1;

    $expensesRelation = m::mock('stdClass');
    $expensesRelation->shouldReceive('count')->andReturn(1);
    $category->shouldReceive('expenses')->andReturn($expensesRelation);
    $category->shouldNotReceive('delete');

    $jsonResponse = m::mock(JsonResponse::class);
    $jsonResponse->shouldReceive('getData')->andReturn((object)[
        'message' => 'expense_attached',
        'message_string' => 'Expense Attached',
    ]);
    $jsonResponse->shouldReceive('getStatusCode')->andReturn(400);

    $this->responseAlias
        ->shouldReceive('json')
        ->with([
            'message' => 'expense_attached',
            'message_string' => 'Expense Attached',
        ], 400)
        ->andReturn($jsonResponse);

    $response = $this->controller->destroy($category);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)[
        'message' => 'expense_attached',
        'message_string' => 'Expense Attached',
    ]);
    expect($response->getStatusCode())->toBe(400);
    $category->shouldNotHaveReceived('delete');
    $this->controller->shouldHaveReceived('authorize')->once()->with('delete', $category);
});