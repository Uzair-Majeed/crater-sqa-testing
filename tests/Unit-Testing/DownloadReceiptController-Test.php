<?php

use Crater\Http\Controllers\V1\PDF\DownloadReceiptController;
use Crater\Models\Expense;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Auth\Access\AuthorizationException;
uses(\Mockery::class);
use phpmock\phpunit\GlobalFunctionMocker;

uses(Tests\TestCase::class); // Assuming tests are within the standard Laravel `Tests` namespace. Adjust if necessary.

// Reset Mockery after each test
afterEach(function () {
    Mockery::close();
});

it('downloads the receipt when found and no output buffering exists', function () {
    // Arrange
    $expense = Mockery::mock(Expense::class);
    $media = Mockery::mock(Media::class);
    $downloadResponse = Mockery::mock(\Symfony\Component\HttpFoundation\StreamedResponse::class);

    $media->shouldReceive('getPath')->once()->andReturn('/path/to/receipt.jpg');
    $media->shouldReceive('file_name')->once()->andReturn('receipt.jpg');

    $expense->shouldReceive('getFirstMedia')->with('receipts')->once()->andReturn($media);

    // Create a partial mock of the controller to mock its protected `authorize` method
    $controller = Mockery::mock(DownloadReceiptController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('view', $expense)->once()->andReturn(true);

    ResponseFacade::shouldReceive('download')
        ->once()
        ->with('/path/to/receipt.jpg', 'receipt.jpg')
        ->andReturn($downloadResponse);

    // Act
    $response = $controller($expense);

    // Assert
    expect($response)->toBe($downloadResponse);
});

it('downloads the receipt and cleans output buffer if contents exist', function () {
    // Arrange
    $expense = Mockery::mock(Expense::class);
    $media = Mockery::mock(Media::class);
    $downloadResponse = Mockery::mock(\Symfony\Component\HttpFoundation\StreamedResponse::class);

    $media->shouldReceive('getPath')->once()->andReturn('/path/to/receipt_with_ob.png');
    $media->shouldReceive('file_name')->once()->andReturn('receipt_with_ob.png');

    $expense->shouldReceive('getFirstMedia')->with('receipts')->once()->andReturn($media);

    $controller = Mockery::mock(DownloadReceiptController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('view', $expense)->once()->andReturn(true);

    ResponseFacade::shouldReceive('download')
        ->once()
        ->with('/path/to/receipt_with_ob.png', 'receipt_with_ob.png')
        ->andReturn($downloadResponse);

    // Mock global functions ob_get_contents and ob_end_clean
    // The namespace for mocking global functions is the namespace of the calling class.
    $mockerObGetContents = new GlobalFunctionMocker('Crater\Http\Controllers\V1\PDF', 'ob_get_contents');
    $mockerObEndClean = new GlobalFunctionMocker('Crater\Http\Controllers\V1\PDF', 'ob_end_clean');

    $mockerObGetContents->expects($this)->once()->willReturn('some content');
    $mockerObEndClean->expects($this)->once()->willReturn(true);

    // Act
    $response = $controller($expense);

    // Assert
    expect($response)->toBe($downloadResponse);
});

it('returns error JSON response when receipt not found', function () {
    // Arrange
    $expense = Mockery::mock(Expense::class);

    $expense->shouldReceive('getFirstMedia')->with('receipts')->once()->andReturn(null);

    $controller = Mockery::mock(DownloadReceiptController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('view', $expense)->once()->andReturn(true);

    ResponseFacade::shouldNotReceive('download');

    // Mock the Illuminate\Contracts\Routing\ResponseFactory to control response()->json()
    $mockResponseFactory = Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
    $jsonResponse = Mockery::mock(JsonResponse::class);
    $jsonResponse->shouldReceive('getData')->andReturn((object)['error' => 'receipt_not_found']);
    $jsonResponse->shouldReceive('getStatusCode')->andReturn(200);

    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => 'receipt_not_found'])
        ->andReturn($jsonResponse);

    $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $mockResponseFactory);

    // Act
    $response = $controller($expense);

    // Assert
    expect($response)
        ->toBe($jsonResponse);
});

it('throws authorization exception when user is not authorized to view expense', function () {
    // Arrange
    $expense = Mockery::mock(Expense::class);

    $controller = Mockery::mock(DownloadReceiptController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->with('view', $expense)
        ->once()
        ->andThrow(new AuthorizationException('Unauthorized to view this expense.'));

    $expense->shouldNotReceive('getFirstMedia');
    ResponseFacade::shouldNotReceive('download');

    // Assert
    $this->expectException(AuthorizationException::class);
    $this->expectExceptionMessage('Unauthorized to view this expense.');

    // Act
    $controller($expense);
});
