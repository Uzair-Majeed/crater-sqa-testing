<?php

test('it returns unauthorized if user is not logged in', function () {
    // Arrange
    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $request->shouldReceive('user')->andReturn(null); // No user logged in
    $request->shouldReceive('all')->andReturn([]); // Mock all() as controllers often access it for logging or general input

    $controller = new \Crater\Http\Controllers\V1\Admin\Update\UnzipUpdateController();

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toMatchArray([
            'success' => false,
            'message' => 'You are not allowed to update this app.'
        ]);
});

test('it returns unauthorized if user is logged in but not an owner', function () {
    // Arrange
    $user = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(false); // User is not an owner

    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $request->shouldReceive('user')->andReturn($user); // User is logged in
    $request->shouldNotReceive('validate'); // Should not reach validation
    $request->shouldReceive('all')->andReturn([]); // Mock all() as controllers often access it

    $controller = new \Crater\Http\Controllers\V1\Admin\Update\UnzipUpdateController();

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toMatchArray([
            'success' => false,
            'message' => 'You are not allowed to update this app.'
        ]);
});

test('it throws validation exception if path is missing and user is owner', function () {
    // Arrange
    $user = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(true); // User is an owner

    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $request->shouldReceive('user')->andReturn($user); // User is logged in and owner
    // Simulate validation failure by making validate method throw an exception
    $request->shouldReceive('validate')
            ->once()
            ->with(['path' => 'required'])
            ->andThrow(new \Illuminate\Validation\ValidationException(
                Mockery::mock(\Illuminate\Validation\Validator::class)
                    ->shouldReceive('errors')->andReturn(new \Illuminate\Support\MessageBag(['path' => ['The path field is required.']]))
                    ->getMock()
            ));
    $request->shouldNotReceive('path'); // Should not get to request->path() if validation fails
    $request->shouldReceive('all')->andReturn([]); // Mock all() as controllers often access it, possibly even before validation or for error context

    $controller = new \Crater\Http\Controllers\V1\Admin\Update\UnzipUpdateController();

    // Act & Assert
    expect(fn () => $controller->__invoke($request))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('it successfully unzips the update file when user is owner and path is provided', function () {
    // Arrange
    $user = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(true); // User is an owner

    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $request->shouldReceive('user')->andReturn($user); // User is logged in and owner
    $request->shouldReceive('validate')->once()->with(['path' => 'required']); // Expect validation to pass
    $request->shouldReceive('path')->withNoArgs()->andReturn('/tmp/update.zip'); // Simulate request path
    $request->shouldReceive('all')->andReturn(['path' => '/tmp/update.zip']); // Mock all() for general input access, common in controllers

    // Mock Updater::unzip static method using alias
    Mockery::mock('alias:'. \Crater\Space\Updater::class)
        ->shouldReceive('unzip')
        ->once()
        ->with('/tmp/update.zip')
        ->andReturn('/app/unzipped_path'); // Simulate successful unzip path

    $controller = new \Crater\Http\Controllers\V1\Admin\Update\UnzipUpdateController();

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toMatchArray([
            'success' => true,
            'path' => '/app/unzipped_path',
        ]);
});

test('it handles unzip failure gracefully when user is owner and path is provided', function () {
    // Arrange
    $user = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(true); // User is an owner

    $request = Mockery::mock(\Illuminate\Http\Request::class);
    $request->shouldReceive('user')->andReturn($user); // User is logged in and owner
    $request->shouldReceive('validate')->once()->with(['path' => 'required']); // Expect validation to pass
    $request->shouldReceive('path')->withNoArgs()->andReturn('/tmp/bad_update.zip'); // Simulate request path
    $request->shouldReceive('all')->andReturn(['path' => '/tmp/bad_update.zip']); // Mock all() for general input access, especially in error handling

    $errorMessage = 'Failed to unzip archive: Corrupted file.';
    // Mock Updater::unzip static method to throw an exception
    Mockery::mock('alias:'. \Crater\Space\Updater::class)
        ->shouldReceive('unzip')
        ->once()
        ->with('/tmp/bad_update.zip')
        ->andThrow(new \Exception($errorMessage)); // Simulate unzip failure

    $controller = new \Crater\Http\Controllers\V1\Admin\Update\UnzipUpdateController();

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class)
        ->and($response->getStatusCode())->toBe(500)
        ->and($response->getData(true))->toMatchArray([
            'success' => false,
            'error' => $errorMessage,
        ]);
});


afterEach(function () {
    Mockery::close();
});