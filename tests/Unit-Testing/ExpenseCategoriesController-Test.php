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

// Set up Mockery and mock common dependencies before each test
beforeEach(function () {
    // Create a partial mock of the controller to allow mocking protected methods (like authorize)
    // while still executing the original logic of public methods.
    $this->controller = m::mock(ExpenseCategoriesController::class)->makePartial();
    // Allow mocking protected methods on the partial mock.
    $this->controller->shouldAllowMockingProtectedMethods();
    // Default behavior for `authorize`: assume authorization passes for unit tests
    // unless a specific test needs to override it to test authorization failures (which is more of a feature test).
    $this->controller->shouldReceive('authorize')->zeroOrMoreTimes()->andReturn(true);

    // Mock the `ExpenseCategoryResource` class statically to control its behavior
    // when `::collection()` or `new ExpenseCategoryResource()` are called.
    m::mock('alias:' . ExpenseCategoryResource::class);
});

// Close Mockery mocks after each test to prevent memory leaks and ensure clean state.
afterEach(function () {
    m::close();
});

test('index displays a listing of expense categories with default limit', function () {
    // 1. Arrange
    $request = m::mock(Request::class);
    // Request does not have a 'limit' parameter
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    // Request returns an empty array for filters
    $request->shouldReceive('all')->andReturn([]);

    // Mock an Eloquent Builder instance for the query chain.
    $builder = m::mock(Builder::class);
    // Mock a Paginator instance to be returned by `paginateData`.
    $paginator = m::mock(LengthAwarePaginator::class);
    // The paginator resource might be accessed by the collection, so mock it.
    $paginator->shouldReceive('resource')->andReturn(new Collection());

    // Mock the static methods chain on ExpenseCategory model.
    m::mock('alias:' . ExpenseCategory::class)
        ->shouldReceive('applyFilters')
        ->with([]) // Expect no filters
        ->andReturn($builder) // Return the mock builder
        ->shouldReceive('whereCompany')
        ->andReturn($builder) // Return the mock builder
        ->shouldReceive('latest')
        ->andReturn($builder) // Return the mock builder
        ->shouldReceive('paginateData')
        ->with(5) // Expect the default limit of 5
        ->andReturn($paginator); // Return the mock paginator

    // Mock the static `collection` method of ExpenseCategoryResource.
    ExpenseCategoryResource::shouldReceive('collection')
        ->with($paginator) // Expect the mock paginator
        ->andReturn(m::mock('Illuminate\Http\Resources\Json\AnonymousResourceCollection')); // Return a mock resource collection

    // 2. Act
    $response = $this->controller->index($request);

    // 3. Assert
    expect($response)->toBeInstanceOf('Illuminate\Http\Resources\Json\AnonymousResourceCollection');
    // Verify that the `authorize` method was called correctly.
    $this->controller->shouldHaveReceived('authorize')->once()->with('viewAny', ExpenseCategory::class);
});

test('index displays a listing of expense categories with custom limit', function () {
    // 1. Arrange
    $request = m::mock(Request::class);
    $customLimit = 10;
    // Request has a 'limit' parameter
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    // Set the `limit` property directly on the request mock.
    $request->limit = $customLimit;
    // Request returns the limit in all parameters.
    $request->shouldReceive('all')->andReturn(['limit' => $customLimit]);

    $builder = m::mock(Builder::class);
    $paginator = m::mock(LengthAwarePaginator::class);
    $paginator->shouldReceive('resource')->andReturn(new Collection());

    // Mock the static methods chain on ExpenseCategory model.
    m::mock('alias:' . ExpenseCategory::class)
        ->shouldReceive('applyFilters')
        ->with(['limit' => $customLimit]) // Expect custom limit in filters
        ->andReturn($builder)
        ->shouldReceive('whereCompany')
        ->andReturn($builder)
        ->shouldReceive('latest')
        ->andReturn($builder)
        ->shouldReceive('paginateData')
        ->with($customLimit) // Expect the custom limit
        ->andReturn($paginator);

    // Mock the static `collection` method of ExpenseCategoryResource.
    ExpenseCategoryResource::shouldReceive('collection')
        ->with($paginator)
        ->andReturn(m::mock('Illuminate\Http\Resources\Json\AnonymousResourceCollection'));

    // 2. Act
    $response = $this->controller->index($request);

    // 3. Assert
    expect($response)->toBeInstanceOf('Illuminate\Http\Resources\Json\AnonymousResourceCollection');
    $this->controller->shouldHaveReceived('authorize')->once()->with('viewAny', ExpenseCategory::class);
});

test('store creates a new expense category successfully', function () {
    // 1. Arrange
    $request = m::mock(ExpenseCategoryRequest::class);
    $payload = ['name' => 'New Category', 'description' => 'A new expense category'];
    // Mock the form request method to return a specific payload.
    $request->shouldReceive('getExpenseCategoryPayload')->andReturn($payload);

    $category = m::mock(ExpenseCategory::class); // Mock the created ExpenseCategory instance.
    $category->name = 'New Category'; // Simulate attributes being set.

    // Mock the static `create` method of ExpenseCategory.
    m::mock('alias:' . ExpenseCategory::class)
        ->shouldReceive('create')
        ->with($payload) // Expect the payload
        ->andReturn($category); // Return the mock category

    // Mock the constructor call for ExpenseCategoryResource.
    ExpenseCategoryResource::shouldReceive('__construct')
        ->with($category) // Expect the created category
        ->once()
        ->andReturnSelf(); // Allow subsequent method calls on the mocked resource

    // 2. Act
    $response = $this->controller->store($request);

    // 3. Assert
    expect($response)->toBeInstanceOf(ExpenseCategoryResource::class);
    $this->controller->shouldHaveReceived('authorize')->once()->with('create', ExpenseCategory::class);
});

test('show displays the specified expense category successfully', function () {
    // 1. Arrange
    $category = m::mock(ExpenseCategory::class);
    $category->id = 1;

    // Mock the constructor call for ExpenseCategoryResource.
    ExpenseCategoryResource::shouldReceive('__construct')
        ->with($category) // Expect the provided category
        ->once()
        ->andReturnSelf();

    // 2. Act
    $response = $this->controller->show($category);

    // 3. Assert
    expect($response)->toBeInstanceOf(ExpenseCategoryResource::class);
    $this->controller->shouldHaveReceived('authorize')->once()->with('view', $category);
});

test('update updates the specified expense category successfully', function () {
    // 1. Arrange
    $request = m::mock(ExpenseCategoryRequest::class);
    $payload = ['name' => 'Updated Category', 'description' => 'Updated description'];
    // Mock the form request method to return an updated payload.
    $request->shouldReceive('getExpenseCategoryPayload')->andReturn($payload);

    $category = m::mock(ExpenseCategory::class);
    $category->id = 1;
    // Mock the `update` method on the ExpenseCategory instance.
    $category->shouldReceive('update')
        ->with($payload) // Expect the updated payload
        ->once()
        ->andReturn(true); // Simulate successful update

    // Mock the constructor call for ExpenseCategoryResource.
    ExpenseCategoryResource::shouldReceive('__construct')
        ->with($category) // Expect the updated category
        ->once()
        ->andReturnSelf();

    // 2. Act
    $response = $this->controller->update($request, $category);

    // 3. Assert
    expect($response)->toBeInstanceOf(ExpenseCategoryResource::class);
    $this->controller->shouldHaveReceived('authorize')->once()->with('update', $category);
});

test('destroy deletes an expense category when no expenses are attached', function () {
    // 1. Arrange
    $category = m::mock(ExpenseCategory::class);
    $category->id = 1;
    // Mock `expenses()` relation and its `count()` method to return 0.
    $category->shouldReceive('expenses')->andReturn(m::mock('stdClass', ['count' => 0]));
    // Mock the `delete` method on the ExpenseCategory instance.
    $category->shouldReceive('delete')->once()->andReturn(true); // Simulate successful deletion

    // Mock the global `response()` helper and its `json()` method.
    // This is done by aliasing `response` globally and expecting its `json` method.
    // This mock returns a generic `JsonResponse` mock.
    $jsonResponse = m::mock(JsonResponse::class);
    $jsonResponse->shouldReceive('getData')->andReturn((object)['success' => true]);

    m::mock('alias:response')
        ->shouldReceive('json')
        ->with(['success' => true])
        ->andReturn($jsonResponse);

    // 2. Act
    $response = $this->controller->destroy($category);

    // 3. Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true]);
    $category->shouldHaveReceived('delete')->once(); // Verify delete was called
    $this->controller->shouldHaveReceived('authorize')->once()->with('delete', $category);
});

test('destroy returns error if expense category has attached expenses', function () {
    // 1. Arrange
    $category = m::mock(ExpenseCategory::class);
    $category->id = 1;
    // Mock `expenses()` relation and its `count()` method to return > 0.
    $category->shouldReceive('expenses')->andReturn(m::mock('stdClass', ['count' => 1]));
    // Ensure `delete` is NOT called in this scenario.
    $category->shouldNotReceive('delete');

    // Mock the global `response()` helper and its `json()` method for the `respondJson` helper.
    // `respondJson` typically returns a `JsonResponse` with a default status code (e.g., 400).
    $jsonResponse = m::mock(JsonResponse::class);
    $jsonResponse->shouldReceive('getData')->andReturn((object)[
        'message' => 'expense_attached',
        'message_string' => 'Expense Attached',
    ]);
    $jsonResponse->shouldReceive('getStatusCode')->andReturn(400); // Typical status for error responses

    m::mock('alias:response')
        ->shouldReceive('json')
        ->with([
            'message' => 'expense_attached',
            'message_string' => 'Expense Attached',
        ], 400) // Expect the specific arguments from respondJson
        ->andReturn($jsonResponse);

    // 2. Act
    $response = $this->controller->destroy($category);

    // 3. Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)[
        'message' => 'expense_attached',
        'message_string' => 'Expense Attached',
    ]);
    expect($response->getStatusCode())->toBe(400); // Verify the status code
    $category->shouldNotHaveReceived('delete'); // Verify delete was not called
    $this->controller->shouldHaveReceived('authorize')->once()->with('delete', $category);
});



