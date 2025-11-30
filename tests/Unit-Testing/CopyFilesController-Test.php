<?php

use Crater\Http\Controllers\V1\Admin\Update\CopyFilesController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
uses(\Mockery::class);

beforeEach(function () {
    // Ensure mocks are closed after each test to prevent interference
    Mockery::close();
});

test('it returns 401 if no user is authenticated', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn(null);

    $controller = new CopyFilesController();
    $response = $controller->__invoke($request);

    expect($response)
        ->toBeInstanceOf(JsonResponse::class)
        ->status()->toBe(401)
        ->original['success']->toBeFalse()
        ->original['message']->toBe('You are not allowed to update this app.');
});

test('it returns 401 if the authenticated user is not an owner', function () {
    $mockUser = Mockery::mock();
    $mockUser->shouldReceive('isOwner')->andReturn(false);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($mockUser);

    $controller = new CopyFilesController();
    $response = $controller->__invoke($request);

    expect($response)
        ->toBeInstanceOf(JsonResponse::class)
        ->status()->toBe(401)
        ->original['success']->toBeFalse()
        ->original['message']->toBe('You are not allowed to update this app.');
});

test('it throws ValidationException if path is missing', function () {
    $mockUser = Mockery::mock();
    $mockUser->shouldReceive('isOwner')->andReturn(true);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($mockUser);

    // Mock validate method to throw ValidationException
    $request->shouldReceive('validate')
        ->with(['path' => 'required'])
        ->andThrow(new ValidationException(
            Validator::make([], ['path' => 'required'])
        ));

    $controller = new CopyFilesController();

    // Expect the exception to be thrown
    $this->expectException(ValidationException::class);
    $controller->__invoke($request);
});

test('it successfully copies files and returns the path', function () {
    $mockUser = Mockery::mock();
    $mockUser->shouldReceive('isOwner')->andReturn(true);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($mockUser);

    $dummyPath = '/path/to/source';
    $copiedPath = '/path/to/destination';

    // Simulate the 'path' parameter being present on the request
    // and make validate method return the validated data
    $request->shouldReceive('validate')
        ->with(['path' => 'required'])
        ->andReturn(['path' => $dummyPath]);
    $request->path = $dummyPath; // Set dynamic property for access

    // Mock the static method call for Updater::copyFiles
    // 'alias' creates a temporary alias for the class for this test
    Mockery::mock('alias:\Crater\Space\Updater')
        ->shouldReceive('copyFiles')
        ->with($dummyPath)
        ->andReturn($copiedPath)
        ->once();

    $controller = new CopyFilesController();
    $response = $controller->__invoke($request);

    expect($response)
        ->toBeInstanceOf(JsonResponse::class)
        ->status()->toBe(200)
        ->original['success']->toBeTrue()
        ->original['path']->toBe($copiedPath);
});
