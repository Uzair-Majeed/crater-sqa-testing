<?php

use Crater\Http\Controllers\V1\Admin\Update\DownloadUpdateController;
use Crater\Space\Updater;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\MessageBag;

beforeEach(fn () => Mockery::close());

test('it returns 401 if no user is authenticated', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn(null);

    $controller = new DownloadUpdateController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(401);
    expect($response->getData(true))->toMatchArray([
        'success' => false,
        'message' => 'You are not allowed to update this app.'
    ]);
});

test('it returns 401 if authenticated user is not an owner', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(false);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);

    $controller = new DownloadUpdateController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(401);
    expect($response->getData(true))->toMatchArray([
        'success' => false,
        'message' => 'You are not allowed to update this app.'
    ]);
});

test('it throws ValidationException if version parameter is missing', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(true);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);

    // Mock a Validator instance for ValidationException
    $mockValidator = Mockery::mock(\Illuminate\Contracts\Validation\Validator::class);
    $mockValidator->shouldReceive('fails')->andReturn(true);
    $mockValidator->shouldReceive('errors')->andReturn(new MessageBag(['version' => ['The version field is required.']]));

    // Simulate validation failure by making `validate` throw an exception
    $request->shouldReceive('validate')
            ->with(['version' => 'required'])
            ->andThrow(new ValidationException($mockValidator));

    $controller = new DownloadUpdateController();

    $this->expectException(ValidationException::class);
    $controller->__invoke($request);
});

test('it downloads the update and returns success with path', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(true);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);
    $request->shouldReceive('validate')->with(['version' => 'required'])->andReturn([]); // Simulate successful validation
    $request->shouldReceive('input')->with('version')->andReturn('1.0.0'); // For $request->version access

    $expectedPath = 'path/to/downloaded/update.zip';

    // Mock Updater::download static method using Mockery alias
    Mockery::mock('alias:' . Updater::class)
        ->shouldReceive('download')
        ->once()
        ->with('1.0.0')
        ->andReturn($expectedPath);

    $controller = new DownloadUpdateController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toMatchArray([
        'success' => true,
        'path' => $expectedPath,
    ]);
});




afterEach(function () {
    Mockery::close();
});
