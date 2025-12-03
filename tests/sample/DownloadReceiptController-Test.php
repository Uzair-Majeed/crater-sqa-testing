<?php

use Crater\Http\Controllers\V1\PDF\DownloadReceiptController;
use Crater\Models\Expense;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Auth\Access\AuthorizationException;

use Mockery;
use Pest\Laravel;

/**
 * Helper to mock global functions in a namespace.
 */
function with_mocked_global_function($namespace, $function, $return)
{
    $fullFunction = "{$namespace}\\{$function}";
    if (!function_exists($fullFunction)) {
        eval("namespace {$namespace}; function {$function}() { return \\mocked_function_return_{$function}(); }");
    }
    app()->instance("mocked_function_return_{$function}", function() use ($return) { return $return; });
    $GLOBALS["mocked_function_return_{$function}"] = function() use ($return) { return $return; };
}

/**
 * Helper to remove mocked global function for clean-up.
 */
function remove_mocked_global_function($namespace, $function)
{
    $fullFunction = "{$namespace}\\{$function}";
    if (function_exists($fullFunction)) {
        // Not necessary as PHP does not allow function removal
    }
    unset($GLOBALS["mocked_function_return_{$function}"]);
}

it('downloads the receipt when found and no output buffering exists', function () {
    // Arrange
    $expense = Mockery::mock(Expense::class);
    $media = Mockery::mock(Media::class);
    $downloadResponse = Mockery::mock(\Symfony\Component\HttpFoundation\StreamedResponse::class);

    $media->shouldReceive('getPath')->once()->andReturn('/path/to/receipt.jpg');
    $media->shouldReceive('getAttribute')->with('file_name')->andReturn('receipt.jpg');
    $media->shouldReceive('file_name')->andReturn('receipt.jpg'); // In case called as property
    $expense->shouldReceive('getFirstMedia')->with('receipts')->once()->andReturn($media);

    $controller = Mockery::mock(DownloadReceiptController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('view', $expense)->once()->andReturn(true);

    // Ensure ob_get_contents returns empty for this test (simulate no output buffer)
    with_mocked_global_function('Crater\Http\Controllers\V1\PDF', 'ob_get_contents', '');
    with_mocked_global_function('Crater\Http\Controllers\V1\PDF', 'ob_end_clean', true);

    ResponseFacade::shouldReceive('download')
        ->once()
        ->with('/path/to/receipt.jpg', 'receipt.jpg')
        ->andReturn($downloadResponse);

    // Act
    $response = $controller($expense);

    // Assert
    expect($response)->toBe($downloadResponse);

    // Clean up global function mocks
    remove_mocked_global_function('Crater\Http\Controllers\V1\PDF', 'ob_get_contents');
    remove_mocked_global_function('Crater\Http\Controllers\V1\PDF', 'ob_end_clean');
});

it('downloads the receipt and cleans output buffer if contents exist', function () {
    // Arrange
    $expense = Mockery::mock(Expense::class);
    $media = Mockery::mock(Media::class);
    $downloadResponse = Mockery::mock(\Symfony\Component\HttpFoundation\StreamedResponse::class);

    $media->shouldReceive('getPath')->once()->andReturn('/path/to/receipt_with_ob.png');
    $media->shouldReceive('getAttribute')->with('file_name')->andReturn('receipt_with_ob.png');
    $media->shouldReceive('file_name')->andReturn('receipt_with_ob.png');
    $expense->shouldReceive('getFirstMedia')->with('receipts')->once()->andReturn($media);

    $controller = Mockery::mock(DownloadReceiptController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('view', $expense)->once()->andReturn(true);

    // Mock ob_get_contents to simulate existing buffer contents; ob_end_clean will be called.
    with_mocked_global_function('Crater\Http\Controllers\V1\PDF', 'ob_get_contents', 'some content');
    with_mocked_global_function('Crater\Http\Controllers\V1\PDF', 'ob_end_clean', true);

    ResponseFacade::shouldReceive('download')
        ->once()
        ->with('/path/to/receipt_with_ob.png', 'receipt_with_ob.png')
        ->andReturn($downloadResponse);

    // Act
    $response = $controller($expense);

    // Assert
    expect($response)->toBe($downloadResponse);

    // Clean up global function mocks
    remove_mocked_global_function('Crater\Http\Controllers\V1\PDF', 'ob_get_contents');
    remove_mocked_global_function('Crater\Http\Controllers\V1\PDF', 'ob_end_clean');
});

it('returns error JSON response when receipt not found', function () {
    // Arrange
    $expense = Mockery::mock(Expense::class);

    $expense->shouldReceive('getFirstMedia')->with('receipts')->once()->andReturn(null);

    $controller = Mockery::mock(DownloadReceiptController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('view', $expense)->once()->andReturn(true);

    // Don't mock ResponseFacade::shouldNotReceive(), just do not call download in this context.
    // Patch response()->json to return a specific JsonResponse
    $mockResponseFactory = Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
    $jsonResponse = Mockery::mock(JsonResponse::class);

    $jsonResponse->shouldReceive('getData')->andReturn((object)['error' => 'receipt_not_found']);
    $jsonResponse->shouldReceive('getStatusCode')->andReturn(200);

    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => 'receipt_not_found'])
        ->andReturn($jsonResponse);

    app()->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $mockResponseFactory);

    // Act
    $response = $controller($expense);

    // Assert
    expect($response)->toBe($jsonResponse);
});

it('throws authorization exception when user is not authorized to view expense', function () {
    // Arrange
    $expense = Mockery::mock(Expense::class);

    $controller = Mockery::mock(DownloadReceiptController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->with('view', $expense)
        ->once()
        ->andThrow(new AuthorizationException('Unauthorized to view this expense.'));

    // Don't mock ResponseFacade::shouldNotReceive(), just do not call download at all.

    // Assert
    expect(fn() => $controller($expense))
        ->toThrow(AuthorizationException::class, 'Unauthorized to view this expense.');
});

afterEach(function () {
    Mockery::close();
});