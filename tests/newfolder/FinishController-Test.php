<?php

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Crater\Http\Controllers\V1\Installation\FinishController;

test('it successfully creates the database_created file and returns a success response', function () {
    // Arrange: Mock the Storage facade and its chained methods
    $diskMock = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);

    Storage::shouldReceive('disk')
           ->once()
           ->with('local')
           ->andReturn($diskMock);

    $diskMock->shouldReceive('put')
             ->once()
             ->with('database_created', 'database_created')
             ->andReturn(true); // Simulate successful file creation

    $controller = new FinishController();
    $request = new Request(); // The request object is not used in the current __invoke logic, but must be passed.

    // Act: Call the __invoke method
    $response = $controller($request);

    // Assert: Verify the response and that mocks were called as expected
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);

    // Mockery expectations are met and closed by the afterEach hook.
});

test('it returns success even if storage put operation fails (current implementation behavior)', function () {
    // Arrange: Mock the Storage facade to simulate a failed put operation
    $diskMock = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);

    Storage::shouldReceive('disk')
           ->once()
           ->with('local')
           ->andReturn($diskMock);

    $diskMock->shouldReceive('put')
             ->once()
             ->with('database_created', 'database_created')
             ->andReturn(false); // Simulate failed file creation

    $controller = new FinishController();
    $request = new Request();

    // Act: Call the __invoke method
    $response = $controller($request);

    // Assert: The current controller logic always returns success: true, regardless of `put`'s return value.
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);

    // Mockery expectations are met and closed by the afterEach hook.
});


afterEach(function () {
    Mockery::close();
});