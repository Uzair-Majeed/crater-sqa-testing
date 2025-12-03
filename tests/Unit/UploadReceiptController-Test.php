<?php

use Crater\Http\Controllers\V1\Admin\Expense\UploadReceiptController;
use Crater\Http\Requests\UploadExpenseReceiptRequest;
use Crater\Models\Expense;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

// Ensure Mockery is closed after each test to prevent test interference.

test('it successfully uploads a new expense receipt when type is create', function () {
    // Arrange
    $controller = Mockery::mock(UploadReceiptController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $mockRequest = Mockery::mock(UploadExpenseReceiptRequest::class);
    $mockExpense = Mockery::mock(Expense::class);

    $base64ImageData = 'mockBase64ImageData';
    $filename = 'test-receipt.jpg';
    $attachmentReceiptJson = json_encode(['data' => $base64ImageData, 'name' => $filename]);

    // FIX: Change to mock 'input' method. Laravel's Request magic __get() often delegates to input().
    $mockRequest->shouldReceive('input')
        ->with('attachment_receipt')
        ->andReturn($attachmentReceiptJson)
        ->once();
    $mockRequest->shouldReceive('input')
        ->with('type')
        ->andReturn('create') // Not 'edit', so clearMediaCollection should not be called
        ->once();

    $controller->shouldReceive('authorize')
        ->with('update', $mockExpense)
        ->once()
        ->andReturn(true);

    // Mock media library chain
    $mockMediaAdder = Mockery::mock(FileAdder::class);
    $mockExpense->shouldReceive('addMediaFromBase64')
        ->with($base64ImageData)
        ->once()
        ->andReturn($mockMediaAdder);
    $mockMediaAdder->shouldReceive('usingFileName')
        ->with($filename)
        ->once()
        ->andReturn($mockMediaAdder);
    $mockMediaAdder->shouldReceive('toMediaCollection')
        ->with('receipts')
        ->once()
        ->andReturn(Mockery::mock(Media::class));

    // Ensure clearMediaCollection is NOT called for 'create' type
    $mockExpense->shouldNotReceive('clearMediaCollection');

    // Act
    $response = $controller->__invoke($mockRequest, $mockExpense);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['success' => 'Expense receipts uploaded successfully']);
});

test('it successfully replaces an existing expense receipt when type is edit', function () {
    // Arrange
    $controller = Mockery::mock(UploadReceiptController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $mockRequest = Mockery::mock(UploadExpenseReceiptRequest::class);
    $mockExpense = Mockery::mock(Expense::class);

    $base64ImageData = 'anotherMockBase64Data';
    $filename = 'edited-receipt.png';
    $attachmentReceiptJson = json_encode(['data' => $base64ImageData, 'name' => $filename]);

    // FIX: Change to mock 'input' method.
    $mockRequest->shouldReceive('input')
        ->with('attachment_receipt')
        ->andReturn($attachmentReceiptJson)
        ->once();
    $mockRequest->shouldReceive('input')
        ->with('type')
        ->andReturn('edit') // This should trigger clearMediaCollection
        ->once();

    $controller->shouldReceive('authorize')
        ->with('update', $mockExpense)
        ->once()
        ->andReturn(true);

    // clearMediaCollection should be called for 'edit' type
    $mockExpense->shouldReceive('clearMediaCollection')
        ->with('receipts')
        ->once();

    // Mock media library chain
    $mockMediaAdder = Mockery::mock(FileAdder::class);
    $mockExpense->shouldReceive('addMediaFromBase64')
        ->with($base64ImageData)
        ->once()
        ->andReturn($mockMediaAdder);
    $mockMediaAdder->shouldReceive('usingFileName')
        ->with($filename)
        ->once()
        ->andReturn($mockMediaAdder);
    $mockMediaAdder->shouldReceive('toMediaCollection')
        ->with('receipts')
        ->once()
        ->andReturn(Mockery::mock(Media::class));

    // Act
    $response = $controller->__invoke($mockRequest, $mockExpense);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['success' => 'Expense receipts uploaded successfully']);
});

test('it handles no attachment data gracefully', function () {
    // Arrange
    $controller = Mockery::mock(UploadReceiptController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $mockRequest = Mockery::mock(UploadExpenseReceiptRequest::class);
    $mockExpense = Mockery::mock(Expense::class);

    // FIX: Change to mock 'input' method.
    $mockRequest->shouldReceive('input')
        ->with('attachment_receipt')
        ->andReturn(null) // No attachment data, json_decode will return null
        ->once();

    $controller->shouldReceive('authorize')
        ->with('update', $mockExpense)
        ->once()
        ->andReturn(true);

    // Ensure no media library calls are made
    $mockExpense->shouldNotReceive('clearMediaCollection');
    $mockExpense->shouldNotReceive('addMediaFromBase64');
    // FIX: If attachment_receipt is null, controller logic should not proceed to read 'type'.
    $mockRequest->shouldNotReceive('input')->with('type');

    // Act
    $response = $controller->__invoke($mockRequest, $mockExpense);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['success' => 'Expense receipts uploaded successfully']);
});

test('it handles empty attachment data gracefully', function () {
    // Arrange
    $controller = Mockery::mock(UploadReceiptController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $mockRequest = Mockery::mock(UploadExpenseReceiptRequest::class);
    $mockExpense = Mockery::mock(Expense::class);

    // FIX: Change to mock 'input' method.
    // Empty string or malformed JSON that results in json_decode returning null/false
    $mockRequest->shouldReceive('input')
        ->with('attachment_receipt')
        ->andReturn('') // This will make json_decode return null
        ->once();

    $controller->shouldReceive('authorize')
        ->with('update', $mockExpense)
        ->once()
        ->andReturn(true);

    // Ensure no media library calls are made
    $mockExpense->shouldNotReceive('clearMediaCollection');
    $mockExpense->shouldNotReceive('addMediaFromBase64');
    // FIX: If attachment_receipt is empty/malformed, controller logic should not proceed to read 'type'.
    $mockRequest->shouldNotReceive('input')->with('type');

    // Act
    $response = $controller->__invoke($mockRequest, $mockExpense);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['success' => 'Expense receipts uploaded successfully']);
});

test('it throws authorization exception when not authorized', function () {
    // Arrange
    $controller = Mockery::mock(UploadReceiptController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $mockRequest = Mockery::mock(UploadExpenseReceiptRequest::class);
    $mockExpense = Mockery::mock(Expense::class);

    // Simulate authorization failure
    $controller->shouldReceive('authorize')
        ->with('update', $mockExpense)
        ->once()
        ->andThrow(new AuthorizationException('Unauthorized.'));

    // Ensure no other methods are called prior to the exception
    // FIX: Update shouldNotReceive for request data to use 'input'
    $mockRequest->shouldNotReceive('input')->with('attachment_receipt');
    $mockRequest->shouldNotReceive('input')->with('type');
    $mockExpense->shouldNotReceive('clearMediaCollection');
    $mockExpense->shouldNotReceive('addMediaFromBase64');

    // Act & Assert
    expect(fn () => $controller->__invoke($mockRequest, $mockExpense))
        ->toThrow(AuthorizationException::class, 'Unauthorized.');
});

test('it handles attachment data with missing name field', function () {
    // Arrange
    $controller = Mockery::mock(UploadReceiptController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $mockRequest = Mockery::mock(UploadExpenseReceiptRequest::class);
    $mockExpense = Mockery::mock(Expense::class);

    $base64ImageData = 'mockBase64DataOnly';
    $attachmentReceiptJson = json_encode(['data' => $base64ImageData]); // Missing 'name' field

    // FIX: Change to mock 'input' method.
    $mockRequest->shouldReceive('input')
        ->with('attachment_receipt')
        ->andReturn($attachmentReceiptJson)
        ->once();
    $mockRequest->shouldReceive('input')
        ->with('type')
        ->andReturn('create')
        ->once();

    $controller->shouldReceive('authorize')
        ->with('update', $mockExpense)
        ->once()
        ->andReturn(true);

    // Mock media library chain, expecting null for filename as $data->name would be null.
    // Spatie's `usingFileName(null)` internally generates a UUID, so passing null here is correct.
    $mockMediaAdder = Mockery::mock(FileAdder::class);
    $mockExpense->shouldReceive('addMediaFromBase64')
        ->with($base64ImageData)
        ->once()
        ->andReturn($mockMediaAdder);
    $mockMediaAdder->shouldReceive('usingFileName')
        ->with(null) // Expecting null here
        ->once()
        ->andReturn($mockMediaAdder);
    $mockMediaAdder->shouldReceive('toMediaCollection')
        ->with('receipts')
        ->once()
        ->andReturn(Mockery::mock(Media::class));

    // Act
    $response = $controller->__invoke($mockRequest, $mockExpense);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['success' => 'Expense receipts uploaded successfully']);
});

test('it throws FileCannotBeAdded exception when attachment data field is missing', function () {
    // Arrange
    $controller = Mockery::mock(UploadReceiptController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $mockRequest = Mockery::mock(UploadExpenseReceiptRequest::class);
    $mockExpense = Mockery::mock(Expense::class);

    $filename = 'receipt.txt';
    $attachmentReceiptJson = json_encode(['name' => $filename]); // Missing 'data' field

    // FIX: Change to mock 'input' method.
    $mockRequest->shouldReceive('input')
        ->with('attachment_receipt')
        ->andReturn($attachmentReceiptJson)
        ->once();
    $mockRequest->shouldReceive('input')
        ->with('type')
        ->andReturn('create')
        ->once();

    $controller->shouldReceive('authorize')
        ->with('update', $mockExpense)
        ->once()
        ->andReturn(true);

    // Expect FileCannotBeAdded exception because $data->data would be null,
    // and `addMediaFromBase64(null)` will lead to this exception in Spatie Media Library.
    $mockExpense->shouldReceive('addMediaFromBase64')
        ->with(null) // $data->data is null
        ->once()
        // FIX: Instantiate FileCannotBeAdded correctly using 'new'
        ->andThrow(new FileCannotBeAdded());

    // Act & Assert
    expect(fn () => $controller->__invoke($mockRequest, $mockExpense))
        ->toThrow(FileCannotBeAdded::class);
});


afterEach(function () {
    Mockery::close();
});