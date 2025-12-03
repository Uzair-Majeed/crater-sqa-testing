<?php

use Crater\Http\Controllers\V1\Admin\General\SearchController;
use Crater\Models\Customer;
use Crater\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

beforeEach(function () {
    // Clear any Mockery expectations between tests
    Mockery::close();
});

test('it returns customers and an empty users array when the requesting user is not an owner and no search term is provided', function () {
    // Arrange
    $mockRequestUser = Mockery::mock(User::class);
    $mockRequestUser->shouldReceive('isOwner')->once()->andReturn(false);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('user')->once()->andReturn($mockRequestUser);
    $mockRequest->shouldReceive('only')->with(['search'])->once()->andReturn([]); // No search term

    // Mock Customer model's static chain calls
    $mockCustomerQueryBuilder = Mockery::mock();
    $mockCustomerQueryBuilder->shouldReceive('whereCompany')->once()->andReturnSelf();
    $mockCustomerQueryBuilder->shouldReceive('latest')->once()->andReturnSelf();

    $mockCustomerPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockCustomerPaginator->shouldReceive('toArray')->andReturn([
        'data' => [['id' => 1, 'name' => 'Customer A', 'company_id' => 1]],
        'current_page' => 1,
        'last_page' => 1,
        'total' => 1,
        'per_page' => 10,
        'from' => 1,
        'to' => 1,
        'path' => '/search',
    ]);
    $mockCustomerQueryBuilder->shouldReceive('paginate')->with(10)->once()->andReturn($mockCustomerPaginator);

    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('applyFilters')
        ->with([])
        ->once()
        ->andReturn($mockCustomerQueryBuilder);

    // Assert that User::applyFilters is not called since the user is not an owner
    Mockery::mock('alias:' . User::class)
        ->shouldNotReceive('applyFilters');

    $controller = new SearchController();

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response->getStatusCode())->toBe(200);
    $responseData = $response->getData(true); // true for associative array
    expect($responseData)->toHaveKeys(['customers', 'users']);
    expect($responseData['customers'])->toEqual($mockCustomerPaginator->toArray());
    expect($responseData['users'])->toBeArray()->toBeEmpty();
});

test('it returns customers and users when the requesting user is an owner and no search term is provided', function () {
    // Arrange
    $mockRequestUser = Mockery::mock(User::class);
    $mockRequestUser->shouldReceive('isOwner')->once()->andReturn(true);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('user')->once()->andReturn($mockRequestUser);
    $mockRequest->shouldReceive('only')->with(['search'])->once()->andReturn([]); // No search term

    // Mock Customer model's static chain calls
    $mockCustomerQueryBuilder = Mockery::mock();
    $mockCustomerQueryBuilder->shouldReceive('whereCompany')->once()->andReturnSelf();
    $mockCustomerQueryBuilder->shouldReceive('latest')->once()->andReturnSelf();
    $mockCustomerPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockCustomerPaginator->shouldReceive('toArray')->andReturn([
        'data' => [['id' => 1, 'name' => 'Customer A', 'company_id' => 1]],
        'current_page' => 1,
        'last_page' => 1,
        'total' => 1,
        'per_page' => 10,
        'from' => 1,
        'to' => 1,
        'path' => '/search',
    ]);
    $mockCustomerQueryBuilder->shouldReceive('paginate')->with(10)->once()->andReturn($mockCustomerPaginator);

    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('applyFilters')
        ->with([])
        ->once()
        ->andReturn($mockCustomerQueryBuilder);

    // Mock User model's static chain calls (since user is owner)
    $mockUserQueryBuilder = Mockery::mock();
    $mockUserQueryBuilder->shouldReceive('latest')->once()->andReturnSelf();
    $mockUserPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockUserPaginator->shouldReceive('toArray')->andReturn([
        'data' => [['id' => 1, 'name' => 'User A']],
        'current_page' => 1,
        'last_page' => 1,
        'total' => 1,
        'per_page' => 10,
        'from' => 1,
        'to' => 1,
        'path' => '/search',
    ]);
    $mockUserQueryBuilder->shouldReceive('paginate')->with(10)->once()->andReturn($mockUserPaginator);

    Mockery::mock('alias:' . User::class)
        ->shouldReceive('applyFilters')
        ->with([])
        ->once()
        ->andReturn($mockUserQueryBuilder);

    $controller = new SearchController();

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response->getStatusCode())->toBe(200);
    $responseData = $response->getData(true);
    expect($responseData)->toHaveKeys(['customers', 'users']);
    expect($responseData['customers'])->toEqual($mockCustomerPaginator->toArray());
    expect($responseData['users'])->toEqual($mockUserPaginator->toArray());
});

test('it filters customers and returns empty users when the requesting user is not an owner and a search term is provided', function () {
    // Arrange
    $searchTerm = 'test_search';
    $mockRequestUser = Mockery::mock(User::class);
    $mockRequestUser->shouldReceive('isOwner')->once()->andReturn(false);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('user')->once()->andReturn($mockRequestUser);
    $mockRequest->shouldReceive('only')->with(['search'])->once()->andReturn(['search' => $searchTerm]);

    // Mock Customer model's static chain calls with search term
    $mockCustomerQueryBuilder = Mockery::mock();
    $mockCustomerQueryBuilder->shouldReceive('whereCompany')->once()->andReturnSelf();
    $mockCustomerQueryBuilder->shouldReceive('latest')->once()->andReturnSelf();
    $mockCustomerPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockCustomerPaginator->shouldReceive('toArray')->andReturn([
        'data' => [['id' => 1, 'name' => 'Filtered Customer A', 'company_id' => 1]],
        'current_page' => 1,
        'last_page' => 1,
        'total' => 1,
        'per_page' => 10,
        'from' => 1,
        'to' => 1,
        'path' => '/search',
    ]);
    $mockCustomerQueryBuilder->shouldReceive('paginate')->with(10)->once()->andReturn($mockCustomerPaginator);

    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('applyFilters')
        ->with(['search' => $searchTerm])
        ->once()
        ->andReturn($mockCustomerQueryBuilder);

    // Assert that User::applyFilters is not called since the user is not an owner
    Mockery::mock('alias:' . User::class)
        ->shouldNotReceive('applyFilters');

    $controller = new SearchController();

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response->getStatusCode())->toBe(200);
    $responseData = $response->getData(true);
    expect($responseData)->toHaveKeys(['customers', 'users']);
    expect($responseData['customers'])->toEqual($mockCustomerPaginator->toArray());
    expect($responseData['users'])->toBeArray()->toBeEmpty();
});

test('it filters customers and users when the requesting user is an owner and a search term is provided', function () {
    // Arrange
    $searchTerm = 'test_search';
    $mockRequestUser = Mockery::mock(User::class);
    $mockRequestUser->shouldReceive('isOwner')->once()->andReturn(true);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('user')->once()->andReturn($mockRequestUser);
    $mockRequest->shouldReceive('only')->with(['search'])->once()->andReturn(['search' => $searchTerm]);

    // Mock Customer model's static chain calls with search term
    $mockCustomerQueryBuilder = Mockery::mock();
    $mockCustomerQueryBuilder->shouldReceive('whereCompany')->once()->andReturnSelf();
    $mockCustomerQueryBuilder->shouldReceive('latest')->once()->andReturnSelf();
    $mockCustomerPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockCustomerPaginator->shouldReceive('toArray')->andReturn([
        'data' => [['id' => 1, 'name' => 'Filtered Customer A', 'company_id' => 1]],
        'current_page' => 1,
        'last_page' => 1,
        'total' => 1,
        'per_page' => 10,
        'from' => 1,
        'to' => 1,
        'path' => '/search',
    ]);
    $mockCustomerQueryBuilder->shouldReceive('paginate')->with(10)->once()->andReturn($mockCustomerPaginator);

    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('applyFilters')
        ->with(['search' => $searchTerm])
        ->once()
        ->andReturn($mockCustomerQueryBuilder);

    // Mock User model's static chain calls with search term
    $mockUserQueryBuilder = Mockery::mock();
    $mockUserQueryBuilder->shouldReceive('latest')->once()->andReturnSelf();
    $mockUserPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockUserPaginator->shouldReceive('toArray')->andReturn([
        'data' => [['id' => 1, 'name' => 'Filtered User A']],
        'current_page' => 1,
        'last_page' => 1,
        'total' => 1,
        'per_page' => 10,
        'from' => 1,
        'to' => 1,
        'path' => '/search',
    ]);
    $mockUserQueryBuilder->shouldReceive('paginate')->with(10)->once()->andReturn($mockUserPaginator);

    Mockery::mock('alias:' . User::class)
        ->shouldReceive('applyFilters')
        ->with(['search' => $searchTerm])
        ->once()
        ->andReturn($mockUserQueryBuilder);

    $controller = new SearchController();

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response->getStatusCode())->toBe(200);
    $responseData = $response->getData(true);
    expect($responseData)->toHaveKeys(['customers', 'users']);
    expect($responseData['customers'])->toEqual($mockCustomerPaginator->toArray());
    expect($responseData['users'])->toEqual($mockUserPaginator->toArray());
});

test('it handles empty results for customers gracefully', function () {
    // Arrange
    $mockRequestUser = Mockery::mock(User::class);
    $mockRequestUser->shouldReceive('isOwner')->once()->andReturn(false); // Skip user search for this test

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('user')->once()->andReturn($mockRequestUser);
    $mockRequest->shouldReceive('only')->with(['search'])->once()->andReturn([]);

    // Mock Customer model returning an empty paginator
    $mockCustomerQueryBuilder = Mockery::mock();
    $mockCustomerQueryBuilder->shouldReceive('whereCompany')->once()->andReturnSelf();
    $mockCustomerQueryBuilder->shouldReceive('latest')->once()->andReturnSelf();
    $mockCustomerPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockCustomerPaginator->shouldReceive('toArray')->andReturn([
        'data' => [],
        'current_page' => 1,
        'last_page' => 0,
        'total' => 0,
        'per_page' => 10,
        'from' => null,
        'to' => null,
        'path' => '/search',
    ]);
    $mockCustomerQueryBuilder->shouldReceive('paginate')->with(10)->once()->andReturn($mockCustomerPaginator);

    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('applyFilters')
        ->with([])
        ->once()
        ->andReturn($mockCustomerQueryBuilder);

    Mockery::mock('alias:' . User::class)
        ->shouldNotReceive('applyFilters');

    $controller = new SearchController();

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response->getStatusCode())->toBe(200);
    $responseData = $response->getData(true);
    expect($responseData)->toHaveKeys(['customers', 'users']);
    expect($responseData['customers'])->toEqual($mockCustomerPaginator->toArray());
    expect($responseData['users'])->toBeArray()->toBeEmpty();
});

test('it returns an empty users array when user is owner but User::applyFilters returns no results', function () {
    // Arrange
    $mockRequestUser = Mockery::mock(User::class);
    $mockRequestUser->shouldReceive('isOwner')->once()->andReturn(true);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('user')->once()->andReturn($mockRequestUser);
    $mockRequest->shouldReceive('only')->with(['search'])->once()->andReturn([]);

    // Mock Customer model with some results
    $mockCustomerQueryBuilder = Mockery::mock();
    $mockCustomerQueryBuilder->shouldReceive('whereCompany')->once()->andReturnSelf();
    $mockCustomerQueryBuilder->shouldReceive('latest')->once()->andReturnSelf();
    $mockCustomerPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockCustomerPaginator->shouldReceive('toArray')->andReturn([
        'data' => [['id' => 1, 'name' => 'Customer A']],
        'current_page' => 1,
        'last_page' => 1,
        'total' => 1,
        'per_page' => 10,
        'from' => 1,
        'to' => 1,
        'path' => '/search',
    ]);
    $mockCustomerQueryBuilder->shouldReceive('paginate')->with(10)->once()->andReturn($mockCustomerPaginator);

    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('applyFilters')
        ->with([])
        ->once()
        ->andReturn($mockCustomerQueryBuilder);

    // Mock User model returning an empty paginator
    $mockUserQueryBuilder = Mockery::mock();
    $mockUserQueryBuilder->shouldReceive('latest')->once()->andReturnSelf();
    $mockUserPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockUserPaginator->shouldReceive('toArray')->andReturn([
        'data' => [],
        'current_page' => 1,
        'last_page' => 0,
        'total' => 0,
        'per_page' => 10,
        'from' => null,
        'to' => null,
        'path' => '/search',
    ]);
    $mockUserQueryBuilder->shouldReceive('paginate')->with(10)->once()->andReturn($mockUserPaginator);

    Mockery::mock('alias:' . User::class)
        ->shouldReceive('applyFilters')
        ->with([])
        ->once()
        ->andReturn($mockUserQueryBuilder);

    $controller = new SearchController();

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response->getStatusCode())->toBe(200);
    $responseData = $response->getData(true);
    expect($responseData)->toHaveKeys(['customers', 'users']);
    expect($responseData['customers'])->toEqual($mockCustomerPaginator->toArray());
    expect($responseData['users'])->toEqual($mockUserPaginator->toArray());
});




afterEach(function () {
    Mockery::close();
});
