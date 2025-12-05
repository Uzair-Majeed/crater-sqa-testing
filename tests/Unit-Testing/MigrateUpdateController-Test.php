<?php

use Crater\Http\Controllers\V1\Admin\Update\MigrateUpdateController;
use Crater\Space\Updater;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;

beforeEach(function () {
    // Ensures Mockery expectations are verified and mocks are cleaned up after each test.
    // This is crucial for static mocks defined with Mockery::mock('alias:...')
    Mockery::close();
});

test('it returns 401 if no user is authenticated', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn(null);

    // Ensure Updater::migrateUpdate is not called when authorization fails
    // Correctly mock the static method of Updater to assert it's not called
    Mockery::mock('alias:'.Updater::class)
        ->shouldNotReceive('migrateUpdate');

    $controller = new MigrateUpdateController();

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toEqual([
            'success' => false,
            'message' => 'You are not allowed to update this app.'
        ]);
});

test('it returns 401 if the authenticated user is not an owner', function () {
    // Arrange
    $user = Mockery::mock(Authenticatable::class);
    // Assuming isOwner is a method on the User model implementing Authenticatable
    $user->shouldReceive('isOwner')->andReturn(false);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);

    // Ensure Updater::migrateUpdate is not called when authorization fails
    // Correctly mock the static method of Updater to assert it's not called
    Mockery::mock('alias:'.Updater::class)
        ->shouldNotReceive('migrateUpdate');

    $controller = new MigrateUpdateController();

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toEqual([
            'success' => false,
            'message' => 'You are not allowed to update this app.'
        ]);
});

test('it calls Updater::migrateUpdate and returns a success response if the user is an owner', function () {
    // Arrange
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(true);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);

    // Mock the static method of Updater to ensure it's called
    // 'alias:' prefix is crucial for mocking static methods with Mockery
    Mockery::mock('alias:'.Updater::class)
        ->shouldReceive('migrateUpdate')
        ->once() // Assert that it is called exactly once
        ->andReturnNull(); // It doesn't return anything, so andReturnNull is fine.

    $controller = new MigrateUpdateController();

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual([
            'success' => true,
        ]);
    // Mockery::close() (in beforeEach) will verify the `once()` expectation.
});

afterEach(function () {
    Mockery::close();
});