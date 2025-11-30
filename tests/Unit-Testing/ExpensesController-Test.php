<?php

use Crater\Http\Controllers\V1\Customer\Expense\ExpensesController;
use Crater\Http\Resources\Customer\ExpenseResource;
use Crater\Models\Company;
use Crater\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Mockery\MockInterface;

beforeEach(function () {
    // Ensure mocks are reset
    Mockery::close();
});

test('index method returns expenses with default limit and filters', function () {
    // Arrange
    $customerId = 1;
    $requestLimit = null;
    $defaultLimit = 10;
    $expectedFilters = [];

    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn($customerId);

    $expenseQueryBuilderMock = Mockery::mock(Builder::class);
    $expenseQueryBuilderMock->shouldReceive('with')
        ->with('category', 'creator', 'fields')
        ->andReturnSelf();
    $expenseQueryBuilderMock->shouldReceive('whereUser')
        ->with($customerId)
        ->andReturnSelf();
    $expenseQueryBuilderMock->shouldReceive('applyFilters')
        ->with($expectedFilters)
        ->andReturnSelf();

    $paginatedExpenses = Mockery::mock(LengthAwarePaginator::class);
    $paginatedExpenses->shouldReceive('toArray')->andReturn(['data' => [], 'meta' => []]); // Simplified for resource collection

    $expenseQueryBuilderMock->shouldReceive('paginateData')
        ->with($defaultLimit)
        ->andReturn($paginatedExpenses);

    $expenseTotalCountQueryBuilderMock = Mockery::mock(Builder::class);
    $expenseTotalCountQueryBuilderMock->shouldReceive('whereCustomer')
        ->with($customerId)
        ->andReturnSelf();
    $expenseTotalCountQueryBuilderMock->shouldReceive('count')
        ->andReturn(50); // Example total count

    // Partially mock the Expense model's static methods
    Mockery::mock('overload:' . Expense::class)
        ->shouldReceive('with')
        ->andReturn($expenseQueryBuilderMock);

    Mockery::mock('overload:' . Expense::class)
        ->shouldReceive('whereCustomer')
        ->andReturn($expenseTotalCountQueryBuilderMock);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('only')->andReturn($expectedFilters);

    // Act
    $controller = new ExpensesController();
    $response = $controller->index($request);

    // Assert
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->additional['meta']['expenseTotalCount'])->toBe(50);
    // Further assertions could inspect the collection's data if needed,
    // but for unit test we assume ExpenseResource collection works correctly.
});

test('index method returns expenses with custom limit and filters', function () {
    // Arrange
    $customerId = 2;
    $requestLimit = 25;
    $expectedFilters = [
        'expense_category_id' => 1,
        'from_date' => '2023-01-01',
        'to_date' => '2023-12-31',
        'orderByField' => 'date',
        'orderBy' => 'desc',
    ];

    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn($customerId);

    $expenseQueryBuilderMock = Mockery::mock(Builder::class);
    $expenseQueryBuilderMock->shouldReceive('with')
        ->with('category', 'creator', 'fields')
        ->andReturnSelf();
    $expenseQueryBuilderMock->shouldReceive('whereUser')
        ->with($customerId)
        ->andReturnSelf();
    $expenseQueryBuilderMock->shouldReceive('applyFilters')
        ->with($expectedFilters)
        ->andReturnSelf();

    $paginatedExpenses = Mockery::mock(LengthAwarePaginator::class);
    $paginatedExpenses->shouldReceive('toArray')->andReturn(['data' => [], 'meta' => []]);

    $expenseQueryBuilderMock->shouldReceive('paginateData')
        ->with($requestLimit)
        ->andReturn($paginatedExpenses);

    $expenseTotalCountQueryBuilderMock = Mockery::mock(Builder::class);
    $expenseTotalCountQueryBuilderMock->shouldReceive('whereCustomer')
        ->with($customerId)
        ->andReturnSelf();
    $expenseTotalCountQueryBuilderMock->shouldReceive('count')
        ->andReturn(120);

    Mockery::mock('overload:' . Expense::class)
        ->shouldReceive('with')
        ->andReturn($expenseQueryBuilderMock);

    Mockery::mock('overload:' . Expense::class)
        ->shouldReceive('whereCustomer')
        ->andReturn($expenseTotalCountQueryBuilderMock);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->limit = $requestLimit;
    $request->shouldReceive('only')->andReturn($expectedFilters);

    // Act
    $controller = new ExpensesController();
    $response = $controller->index($request);

    // Assert
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->additional['meta']['expenseTotalCount'])->toBe(120);
});

test('show method returns an expense if found', function () {
    // Arrange
    $companyId = 100;
    $expenseId = 1;
    $customerId = 10;

    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn($customerId);

    $mockExpense = Mockery::mock(Expense::class);
    $mockExpense->id = $expenseId; // Set a property for verification if needed

    $companyExpenseQueryBuilder = Mockery::mock(Builder::class);
    $companyExpenseQueryBuilder->shouldReceive('whereUser')
        ->with($customerId)
        ->andReturnSelf();
    $companyExpenseQueryBuilder->shouldReceive('where')
        ->with('id', $expenseId)
        ->andReturnSelf();
    $companyExpenseQueryBuilder->shouldReceive('first')
        ->andReturn($mockExpense);

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->shouldReceive('expenses')
        ->andReturn($companyExpenseQueryBuilder);

    // Act
    $controller = new ExpensesController();
    $response = $controller->show($mockCompany, $expenseId);

    // Assert
    expect($response)->toBeInstanceOf(ExpenseResource::class);
    expect($response->resource)->toBe($mockExpense);
});

test('show method returns 404 error if expense not found', function () {
    // Arrange
    $companyId = 101;
    $expenseId = 99;
    $customerId = 11;

    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn($customerId);

    $companyExpenseQueryBuilder = Mockery::mock(Builder::class);
    $companyExpenseQueryBuilder->shouldReceive('whereUser')
        ->with($customerId)
        ->andReturnSelf();
    $companyExpenseQueryBuilder->shouldReceive('where')
        ->with('id', $expenseId)
        ->andReturnSelf();
    $companyExpenseQueryBuilder->shouldReceive('first')
        ->andReturn(null); // Expense not found

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->shouldReceive('expenses')
        ->andReturn($companyExpenseQueryBuilder);

    // Act
    $controller = new ExpensesController();
    $response = $controller->show($mockCompany, $expenseId);

    // Assert
    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toBe(json_encode(['error' => 'expense_not_found']));
});
