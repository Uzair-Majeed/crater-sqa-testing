<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Crater\Models\FileDisk;
use Crater\Http\Middleware\ConfigMiddleware;

// Ensure Mockery is closed after each test to prevent state pollution.
afterEach(function () {
    Mockery::close();
});

test('it calls next and does not interact with FileDisk or setConfig if database_created file does not exist', function () {
    // Arrange
    $request = Mockery::mock(Request::class);

    // We cannot mock Closure class; instead, use a real closure and wrap it for expectations.
    $called = false;
    $next = function ($passedRequest) use (&$called, $request) {
        $called = true;
        expect($passedRequest)->toBe($request);
        return 'response_from_next';
    };

    // Mock Storage facade to indicate 'database_created' file does not exist
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturnSelf();
    Storage::shouldReceive('has')
        ->with('database_created')
        ->andReturn(false);

    // Ensure that no static methods of FileDisk are called
    $fileDiskMock = Mockery::mock('overload:Crater\Models\FileDisk');
    $fileDiskMock->shouldNotReceive('find');
    $fileDiskMock->shouldNotReceive('whereSetAsDefault');

    $middleware = new ConfigMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($called)->toBeTrue();
    expect($response)->toBe('response_from_next');
});

test('it calls next and finds file disk by id and calls setConfig if database_created exists and request has file_disk_id', function () {
    // Arrange
    $fileDiskId = 123;
    $request = Mockery::mock(Request::class);

    $called = false;
    $next = function ($passedRequest) use (&$called, $request) {
        $called = true;
        expect($passedRequest)->toBe($request);
        return 'response_from_next';
    };

    $fileDisk = Mockery::mock(FileDisk::class);

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

    // Mock FileDisk::find to return a specific FileDisk instance
    $fileDiskMock = Mockery::mock('overload:Crater\Models\FileDisk');
    $fileDiskMock->shouldReceive('find')
        ->once()
        ->with($fileDiskId)
        ->andReturn($fileDisk);

    // Expect setConfig to be called on the found FileDisk instance
    $fileDisk->shouldReceive('setConfig')
        ->once();

    $middleware = new ConfigMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($called)->toBeTrue();
    expect($response)->toBe('response_from_next');
});

test('it calls next and tries to find file disk by id but does not call setConfig if file disk not found', function () {
    // Arrange
    $fileDiskId = 123;
    $request = Mockery::mock(Request::class);

    $called = false;
    $next = function ($passedRequest) use (&$called, $request) {
        $called = true;
        expect($passedRequest)->toBe($request);
        return 'response_from_next';
    };

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
    $fileDiskMock = Mockery::mock('overload:Crater\Models\FileDisk');
    $fileDiskMock->shouldReceive('find')
        ->once()
        ->with($fileDiskId)
        ->andReturn(null);

    $middleware = new ConfigMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($called)->toBeTrue();
    expect($response)->toBe('response_from_next');
});

test('it calls next and finds default file disk and calls setConfig if database_created exists and request has no file_disk_id', function () {
    // Arrange
    $request = Mockery::mock(Request::class);

    $called = false;
    $next = function ($passedRequest) use (&$called, $request) {
        $called = true;
        expect($passedRequest)->toBe($request);
        return 'response_from_next';
    };

    $fileDisk = Mockery::mock(FileDisk::class); // Mock for the FileDisk instance
    $queryBuilder = Mockery::mock();

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
    $fileDiskMock = Mockery::mock('overload:Crater\Models\FileDisk');
    $fileDiskMock->shouldReceive('whereSetAsDefault')
        ->once()
        ->with(true)
        ->andReturn($queryBuilder);

    $queryBuilder->shouldReceive('first')
        ->once()
        ->andReturn($fileDisk);

    // Expect setConfig to be called on the found FileDisk instance
    $fileDisk->shouldReceive('setConfig')
        ->once();

    $middleware = new ConfigMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($called)->toBeTrue();
    expect($response)->toBe('response_from_next');
});

test('it calls next and tries to find default file disk but does not call setConfig if default file disk not found', function () {
    // Arrange
    $request = Mockery::mock(Request::class);

    $called = false;
    $next = function ($passedRequest) use (&$called, $request) {
        $called = true;
        expect($passedRequest)->toBe($request);
        return 'response_from_next';
    };

    $queryBuilder = Mockery::mock();

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
    $fileDiskMock = Mockery::mock('overload:Crater\Models\FileDisk');
    $fileDiskMock->shouldReceive('whereSetAsDefault')
        ->once()
        ->with(true)
        ->andReturn($queryBuilder);

    $queryBuilder->shouldReceive('first')
        ->once()
        ->andReturn(null);

    $middleware = new ConfigMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($called)->toBeTrue();
    expect($response)->toBe('response_from_next');
}
);