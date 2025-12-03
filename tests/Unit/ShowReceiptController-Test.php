<?php

use Crater\Http\Controllers\V1\Admin\Expense\ShowReceiptController;
use Crater\Models\Expense;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Mockery as m;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// It's good practice to ensure Mockery is cleaned up after each test.
afterEach(function () {
    m::close();
});

test('it returns a file response when a receipt exists and is authorized', function () {
    // Arrange
    $controller = m::mock(ShowReceiptController::class)->makePartial();
    $expense = m::mock(Expense::class);
    $media = m::mock(\Spatie\MediaLibrary\MediaCollections\Models\Media::class); // Assuming Spatie Media Library for media handling

    $filePath = '/path/to/receipt.pdf';

    // Mock the `authorize` method from the parent Controller to simulate successful authorization.
    $controller->shouldReceive('authorize')
        ->once()
        ->with('view', $expense)
        ->andReturn(null); // Authorization succeeds

    // Mock the Expense model's `getFirstMedia` method to return a media object.
    $expense->shouldReceive('getFirstMedia')
        ->once()
        ->with('receipts')
        ->andReturn($media);

    // Mock the media object's `getPath` method to return a file path.
    $media->shouldReceive('getPath')
        ->once()
        ->andReturn($filePath);

    // Mock the `ResponseFactory` (which the `response()` helper uses) and its `file` method.
    $mockResponseFactory = m::mock(ResponseFactory::class);
    $binaryFileResponse = m::mock(BinaryFileResponse::class); // Mock the actual response object returned by `file()`

    $mockResponseFactory->shouldReceive('file')
        ->once()
        ->with($filePath)
        ->andReturn($binaryFileResponse);

    // Bind the mock `ResponseFactory` to the Laravel container so the `response()` helper resolves our mock.
    app()->instance(ResponseFactory::class, $mockResponseFactory);

    // Act
    $response = $controller->__invoke($expense);

    // Assert
    expect($response)->toBe($binaryFileResponse);
});

test('it returns a json response when no receipt exists but is authorized', function () {
    // Arrange
    $controller = m::mock(ShowReceiptController::class)->makePartial();
    $expense = m::mock(Expense::class);

    // Mock the `authorize` method to simulate successful authorization.
    $controller->shouldReceive('authorize')
        ->once()
        ->with('view', $expense)
        ->andReturn(null); // Authorization succeeds

    // Mock the Expense model's `getFirstMedia` method to return null, indicating no receipt.
    $expense->shouldReceive('getFirstMedia')
        ->once()
        ->with('receipts')
        ->andReturn(null); // No media found

    // Mock the `ResponseFactory` and its `json` method.
    // We assume the global `respondJson` helper function internally uses `response()->json()`.
    $mockResponseFactory = m::mock(ResponseFactory::class);
    $jsonResponse = m::mock(JsonResponse::class); // Mock the actual JsonResponse object

    $mockResponseFactory->shouldReceive('json')
        ->once()
        // Assuming default parameters for `respondJson` helper (empty data array, 200 status code)
        ->with([
            'key' => 'receipt_does_not_exist',
            'message' => 'Receipt does not exist.',
            'data' => [], // Default `data` from respondJson helper
        ], 200) // Default `status` from respondJson helper
        ->andReturn($jsonResponse);

    // Bind the mock `ResponseFactory` to the container.
    app()->instance(ResponseFactory::class, $mockResponseFactory);

    // Act
    $response = $controller->__invoke($expense);

    // Assert
    expect($response)->toBe($jsonResponse);
});

test('it throws an authorization exception if view permission is denied', function () {
    // Arrange
    $controller = m::mock(ShowReceiptController::class)->makePartial();
    $expense = m::mock(Expense::class);

    // Mock the `authorize` method to throw an `AuthorizationException`.
    $controller->shouldReceive('authorize')
        ->once()
        ->with('view', $expense)
        ->andThrow(new AuthorizationException('User not authorized to view this expense.'));

    // Act & Assert
    // The `__invoke` method should re-throw the authorization exception.
    $controller->__invoke($expense);
})->throws(AuthorizationException::class);



