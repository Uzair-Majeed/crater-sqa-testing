<?php

use Crater\Http\Controllers\V1\Admin\Update\CopyFilesController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Mockery::close();
});

test('it returns 401 if no user is authenticated', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn(null);

    $controller = new CopyFilesController();
    $response = $controller->__invoke($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(401);
    expect($response->getData()->success)->toBeFalse();
    expect($response->getData()->message)->toBe('You are not allowed to update this app.');
});

test('it returns 401 if the authenticated user is not an owner', function () {
    $mockUser = Mockery::mock();
    $mockUser->shouldReceive('isOwner')->andReturn(false);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($mockUser);

    $controller = new CopyFilesController();
    $response = $controller->__invoke($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(401);
    expect($response->getData()->success)->toBeFalse();
    expect($response->getData()->message)->toBe('You are not allowed to update this app.');
});

test('it throws ValidationException if path is missing', function () {
    $mockUser = Mockery::mock();
    $mockUser->shouldReceive('isOwner')->andReturn(true);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($mockUser);

    $request->shouldReceive('validate')
        ->with(['path' => 'required'])
        ->andThrow(new ValidationException(
            Validator::make([], ['path' => 'required'])
        ));

    $controller = new CopyFilesController();

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

    $request->shouldReceive('validate')
        ->with(['path' => 'required'])
        ->andReturn(['path' => $dummyPath]);
    $request->path = $dummyPath;

    Mockery::mock('alias:\Crater\Space\Updater')
        ->shouldReceive('copyFiles')
        ->with($dummyPath)
        ->andReturn($copiedPath)
        ->once();

    $controller = new CopyFilesController();
    $response = $controller->__invoke($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData()->success)->toBeTrue();
    expect($response->getData()->path)->toBe($copiedPath);
});

afterEach(function () {
    Mockery::close();
});