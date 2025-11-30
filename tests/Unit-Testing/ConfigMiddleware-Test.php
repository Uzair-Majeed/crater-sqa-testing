<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Crater\Models\FileDisk;
use Crater\Http\Middleware\ConfigMiddleware;

beforeEach(function () {
    // Ensure Mockery mocks are closed and cleaned up before each test
    Mockery::close();
});

test('it calls next and does not interact with FileDisk or setConfig if database_created file does not exist', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $next = Mockery::mock(Closure::class);

    // Mock Storage facade to indicate 'database_created' file does not exist
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturnSelf();
    Storage::shouldReceive('has')
        ->with('database_created')
        ->andReturn(false);

    // Expect the next middleware in the stack to be called with the original request
    $next->shouldReceive('__invoke')
        ->once()
        ->with($request)
        ->andReturn('response_from_next');

    // Ensure that no static methods of FileDisk are called
    Mockery::mock('overload:Crater\Models\FileDisk')
        ->shouldNotReceive('find')
        ->shouldNotReceive('whereSetAsDefault');

    $middleware = new ConfigMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('response_from_next');
});

test('it calls next and finds file disk by id and calls setConfig if database_created exists and request has file_disk_id', function () {
    // Arrange
    $fileDiskId = 123;
    $request = Mockery::mock(Request::class);
    $next = Mockery::mock(Closure::class);
    $fileDisk = Mockery::mock(FileDisk::class); // Mock for the FileDisk instance

    // Mock Storage facade to indicate 'database_created' file exists
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturnSelf();
    Storage::shouldReceive('has')
        ->with('database_created')
        ->andReturn(true);

    // Mock request to have 'file_disk_id'
    $request->shouldReceive('has')
        ->with('file_disk_id')
        ->andReturn(true);
    // Directly set the property as it's accessed dynamically in the middleware
    $request->file_disk_id = $fileDiskId;

    // Mock FileDisk::find to return a specific FileDisk instance
    Mockery::mock('overload:Crater\Models\FileDisk')
        ->shouldReceive('find')
        ->once()
        ->with($fileDiskId)
        ->andReturn($fileDisk);

    // Expect setConfig to be called on the found FileDisk instance
    $fileDisk->shouldReceive('setConfig')
        ->once();

    // Expect the next middleware in the stack to be called
    $next->shouldReceive('__invoke')
        ->once()
        ->with($request)
        ->andReturn('response_from_next');

    $middleware = new ConfigMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('response_from_next');
});

test('it calls next and tries to find file disk by id but does not call setConfig if file disk not found', function () {
    // Arrange
    $fileDiskId = 123;
    $request = Mockery::mock(Request::class);
    $next = Mockery::mock(Closure::class);

    // Mock Storage facade to indicate 'database_created' file exists
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturnSelf();
    Storage::shouldReceive('has')
        ->with('database_created')
        ->andReturn(true);

    // Mock request to have 'file_disk_id'
    $request->shouldReceive('has')
        ->with('file_disk_id')
        ->andReturn(true);
    $request->file_disk_id = $fileDiskId;

    // Mock FileDisk::find to return null (not found)
    Mockery::mock('overload:Crater\Models\FileDisk')
        ->shouldReceive('find')
        ->once()
        ->with($fileDiskId)
        ->andReturn(null);

    // Ensure setConfig is never called, as no FileDisk instance is found
    // (This is implicitly covered since no instance is returned from find())

    // Expect the next middleware in the stack to be called
    $next->shouldReceive('__invoke')
        ->once()
        ->with($request)
        ->andReturn('response_from_next');

    $middleware = new ConfigMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('response_from_next');
});

test('it calls next and finds default file disk and calls setConfig if database_created exists and request has no file_disk_id', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $next = Mockery::mock(Closure::class);
    $fileDisk = Mockery::mock(FileDisk::class); // Mock for the FileDisk instance
    $queryBuilder = Mockery::mock(); // Mock for the query builder returned by whereSetAsDefault

    // Mock Storage facade to indicate 'database_created' file exists
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturnSelf();
    Storage::shouldReceive('has')
        ->with('database_created')
        ->andReturn(true);

    // Mock request to NOT have 'file_disk_id'
    $request->shouldReceive('has')
        ->with('file_disk_id')
        ->andReturn(false);

    // Mock FileDisk::whereSetAsDefault()->first() to return a FileDisk instance
    Mockery::mock('overload:Crater\Models\FileDisk')
        ->shouldReceive('whereSetAsDefault')
        ->once()
        ->with(true)
        ->andReturn($queryBuilder); // Chain with a mock query builder

    $queryBuilder->shouldReceive('first')
        ->once()
        ->andReturn($fileDisk); // Return a FileDisk instance

    // Expect setConfig to be called on the found FileDisk instance
    $fileDisk->shouldReceive('setConfig')
        ->once();

    // Expect the next middleware in the stack to be called
    $next->shouldReceive('__invoke')
        ->once()
        ->with($request)
        ->andReturn('response_from_next');

    $middleware = new ConfigMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('response_from_next');
});

test('it calls next and tries to find default file disk but does not call setConfig if default file disk not found', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $next = Mockery::mock(Closure::class);
    $queryBuilder = Mockery::mock(); // Mock for the query builder returned by whereSetAsDefault

    // Mock Storage facade to indicate 'database_created' file exists
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturnSelf();
    Storage::shouldReceive('has')
        ->with('database_created')
        ->andReturn(true);

    // Mock request to NOT have 'file_disk_id'
    $request->shouldReceive('has')
        ->with('file_disk_id')
        ->andReturn(false);

    // Mock FileDisk::whereSetAsDefault()->first() to return null (not found)
    Mockery::mock('overload:Crater\Models\FileDisk')
        ->shouldReceive('whereSetAsDefault')
        ->once()
        ->with(true)
        ->andReturn($queryBuilder);

    $queryBuilder->shouldReceive('first')
        ->once()
        ->andReturn(null); // Return null, indicating no default file disk was found

    // Ensure setConfig is never called, as no FileDisk instance is found
    // (This is implicitly covered since null is returned from first())

    // Expect the next middleware in the stack to be called
    $next->shouldReceive('__invoke')
        ->once()
        ->with($request)
        ->andReturn('response_from_next');

    $middleware = new ConfigMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('response_from_next');
});
