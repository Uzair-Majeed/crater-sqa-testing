<?php

use Crater\Http\Controllers\V1\Admin\Update\DeleteFilesController;
use Crater\Space\Updater;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\User as Authenticatable;

beforeEach(function () {
    Mockery::close();
});

// Helper for creating a mock user
function createMockUser($isOwner = false)
{
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn($isOwner);
    return $user;
}

test('it returns 401 if no user is authenticated', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn(null);

    Mockery::mock('alias:' . Updater::class)
        ->shouldNotReceive('deleteFiles');

    $controller = new DeleteFilesController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toEqual([
            'success' => false,
            'message' => 'You are not allowed to update this app.'
        ]);
});

test('it returns 401 if the authenticated user is not an owner', function () {
    $user = createMockUser(false);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);

    Mockery::mock('alias:' . Updater::class)
        ->shouldNotReceive('deleteFiles');

    $controller = new DeleteFilesController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toEqual([
            'success' => false,
            'message' => 'You are not allowed to update this app.'
        ]);
});

test('it returns 200 and does not delete files if deleted_files is not set', function () {
    $user = createMockUser(true);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);
    $request->deleted_files = null;

    Mockery::mock('alias:' . Updater::class)
        ->shouldNotReceive('deleteFiles');

    $controller = new DeleteFilesController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['success' => true]);
});

test('it returns 200 and does not delete files if deleted_files is an empty array', function () {
    $user = createMockUser(true);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);
    $request->deleted_files = [];

    Mockery::mock('alias:' . Updater::class)
        ->shouldNotReceive('deleteFiles');

    $controller = new DeleteFilesController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['success' => true]);
});

test('it returns 200 and does not delete files if deleted_files is an empty string', function () {
    $user = createMockUser(true);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);
    $request->deleted_files = '';

    Mockery::mock('alias:' . Updater::class)
        ->shouldNotReceive('deleteFiles');

    $controller = new DeleteFilesController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['success' => true]);
});

test('it returns 200 and calls Updater::deleteFiles with provided files if user is owner', function () {
    $user = createMockUser(true);

    $filesToDelete = ['path/to/file1.txt', 'path/to/directory/'];

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);
    $request->deleted_files = $filesToDelete;

    Mockery::mock('alias:' . Updater::class)
        ->shouldReceive('deleteFiles')
        ->once()
        ->with($filesToDelete);

    $controller = new DeleteFilesController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['success' => true]);
});

test('it handles non-array but truthy deleted_files gracefully without error', function () {
    $user = createMockUser(true);

    $filesToDelete = 'single_file.txt';

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);
    $request->deleted_files = $filesToDelete;

    Mockery::mock('alias:' . Updater::class)
        ->shouldReceive('deleteFiles')
        ->once()
        ->with($filesToDelete);

    $controller = new DeleteFilesController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual(['success' => true]);
});

afterEach(function () {
    Mockery::close();
});