<?php

namespace Tests\Unit\Http\Controllers\V1\Admin\Users;

use Crater\Http\Controllers\V1\Admin\Users\UsersController;
use Crater\Http\Requests\DeleteUserRequest;
use Crater\Http\Requests\UserRequest;
use Crater\Http\Resources\UserResource;
use Crater\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

// Setup for Pest
beforeEach(function () {
    // Clear mocks before each test to ensure isolation
    Mockery::close();
});

// UsersController::index tests
test('index method authorizes, filters, paginates users with default limit, and returns user resource collection', function () {
    // Arrange
    // Create a partial mock of the controller to mock its 'authorize' method
    $controller = Mockery::mock(UsersController::class)->makePartial();

    // Mock the incoming Request
    $request = Mockery::mock(Request::class);
    // Mock the currently authenticated user
    $currentUser = Mockery::mock(User::class);
    // Mock the paginator and the resource collection
    $paginatedUsers = Mockery::mock(LengthAwarePaginator::class);
    $userResourceCollection = Mockery::mock(stdClass::class); // Represents the collection returned by UserResource::collection

    $currentUser->id = 1; // Simulate current user's ID to exclude them from the list

    // Define request expectations
    $request->shouldReceive('has')->with('limit')->andReturn(false); // No limit provided, expect default
    $request->shouldReceive('user')->andReturn($currentUser);
    $request->shouldReceive('all')->andReturn(['search' => 'test_filter']); // Simulate filters

    // Expect the authorize method to be called
    $controller->shouldReceive('authorize')->once()->with('viewAny', User::class)->andReturn(true);

    // Mock the User model's static methods and the Eloquent query builder chain
    $mockQueryBuilder = Mockery::mock(Builder::class);
    User::shouldReceive('applyFilters')->once()->with(['search' => 'test_filter'])->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('where')->once()->with('id', '<>', $currentUser->id)->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('latest')->once()->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('paginate')->once()->with(10)->andReturn($paginatedUsers); // Expect default limit 10

    // Mock User::count() for total users meta data
    User::shouldReceive('count')->once()->andReturn(50);

    // Mock UserResource::collection and its chained 'additional' method
    UserResource::shouldReceive('collection')->once()->with($paginatedUsers)->andReturn($userResourceCollection);
    $userResourceCollection->shouldReceive('additional')->once()->with([
        'meta' => [
            'user_total_count' => 50,
        ],
    ])->andReturnSelf(); // Allow chaining

    // Act
    $response = $controller->index($request);

    // Assert
    expect($response)->toBe($userResourceCollection);
});

test('index method uses custom limit if provided in request', function () {
    // Arrange
    $controller = Mockery::mock(UsersController::class)->makePartial();
    $request = Mockery::mock(Request::class);
    $currentUser = Mockery::mock(User::class);
    $paginatedUsers = Mockery::mock(LengthAwarePaginator::class);
    $userResourceCollection = Mockery::mock(stdClass::class);

    $currentUser->id = 1;

    // Define request expectations for a custom limit
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->limit = 25; // Simulate custom limit property
    $request->shouldReceive('user')->andReturn($currentUser);
    $request->shouldReceive('all')->andReturn([]); // No filters

    $controller->shouldReceive('authorize')->once()->with('viewAny', User::class)->andReturn(true);

    // Mock User model and query builder chain, expecting the custom limit
    $mockQueryBuilder = Mockery::mock(Builder::class);
    User::shouldReceive('applyFilters')->once()->with([])->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('where')->once()->with('id', '<>', $currentUser->id)->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('latest')->once()->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('paginate')->once()->with(25)->andReturn($paginatedUsers); // Expect custom limit 25

    User::shouldReceive('count')->once()->andReturn(100);

    UserResource::shouldReceive('collection')->once()->with($paginatedUsers)->andReturn($userResourceCollection);
    $userResourceCollection->shouldReceive('additional')->once()->with([
        'meta' => [
            'user_total_count' => 100,
        ],
    ])->andReturnSelf();

    // Act
    $response = $controller->index($request);

    // Assert
    expect($response)->toBe($userResourceCollection);
});


// UsersController::store tests
test('store method authorizes, creates user from request, and returns user resource', function () {
    // Arrange
    $controller = Mockery::mock(UsersController::class)->makePartial();
    $request = Mockery::mock(UserRequest::class);
    $newUser = Mockery::mock(User::class); // The user model instance returned after creation

    $controller->shouldReceive('authorize')->once()->with('create', User::class)->andReturn(true);

    // Expect User::createFromRequest to be called with the request and return a new user
    User::shouldReceive('createFromRequest')->once()->with($request)->andReturn($newUser);

    // Overload UserResource to intercept its constructor call and verify arguments.
    // This ensures `new UserResource($newUser)` is called with the correct user.
    Mockery::mock('overload:' . UserResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($newUser);
        // `new UserResource` will return a mock instance after this setup.

    // Act
    $response = $controller->store($request);

    // Assert
    expect($response)->toBeInstanceOf(UserResource::class); // It will be a Mockery mock instance of UserResource
});


// UsersController::show tests
test('show method authorizes and returns user resource', function () {
    // Arrange
    $controller = Mockery::mock(UsersController::class)->makePartial();
    $user = Mockery::mock(User::class); // The user model to be displayed

    $controller->shouldReceive('authorize')->once()->with('view', $user)->andReturn(true);

    // Overload UserResource to intercept its constructor call and verify arguments.
    // This ensures `new UserResource($user)` is called with the correct user.
    Mockery::mock('overload:' . UserResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($user);

    // Act
    $response = $controller->show($user);

    // Assert
    expect($response)->toBeInstanceOf(UserResource::class);
});


// UsersController::update tests
test('update method authorizes, updates user from request, and returns user resource', function () {
    // Arrange
    $controller = Mockery::mock(UsersController::class)->makePartial();
    $request = Mockery::mock(UserRequest::class);
    $user = Mockery::mock(User::class); // The user model to be updated

    $controller->shouldReceive('authorize')->once()->with('update', $user)->andReturn(true);

    // Expect the updateFromRequest method to be called on the specific user instance
    $user->shouldReceive('updateFromRequest')->once()->with($request)->andReturn(true); // Assuming it returns true or self

    // Overload UserResource to intercept its constructor call and verify arguments.
    // This ensures `new UserResource($user)` is called with the correct user.
    Mockery::mock('overload:' . UserResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($user);

    // Act
    $response = $controller->update($request, $user);

    // Assert
    expect($response)->toBeInstanceOf(UserResource::class);
});


// UsersController::delete tests
test('delete method authorizes and deletes specified users when users are provided in request', function () {
    // Arrange
    $controller = Mockery::mock(UsersController::class)->makePartial();
    $request = Mockery::mock(DeleteUserRequest::class);

    $userIds = [1, 2, 3];
    // Simulate the $request->users property access.
    $request->users = $userIds;

    $controller->shouldReceive('authorize')->once()->with('delete multiple users', User::class)->andReturn(true);

    // Expect User::deleteUsers static method to be called with the provided user IDs
    User::shouldReceive('deleteUsers')->once()->with($userIds)->andReturn(true); // Assuming it returns boolean or void

    // Act
    $response = $controller->delete($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true))->toEqual(['success' => true]);
});

test('delete method authorizes but does not delete users when no users are provided in request', function () {
    // Arrange
    $controller = Mockery::mock(UsersController::class)->makePartial();
    $request = Mockery::mock(DeleteUserRequest::class);

    // Simulate $request->users being null (no users to delete)
    $request->users = null;

    $controller->shouldReceive('authorize')->once()->with('delete multiple users', User::class)->andReturn(true);

    // Ensure User::deleteUsers static method is NOT called
    User::shouldNotReceive('deleteUsers');

    // Act
    $response = $controller->delete($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true))->toEqual(['success' => true]);
});




afterEach(function () {
    Mockery::close();
});
