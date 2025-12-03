<?php

use Crater\Http\Controllers\V1\Modules\StyleController;
use Crater\Services\Module\ModuleFacade;
//use DateTime;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
 // This refers to the global alias for Illuminate\Http\Request
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

afterEach(fn () => Mockery::close());

test('invoke serves the requested stylesheet successfully with correct content and headers', function () {
    // Arrange
    $styleName = 'test-style';
    $styleContent = 'body { color: red; }';
    $timestamp = time();
    $formattedTimestamp = DateTime::createFromFormat('U', $timestamp)->format('D, d M Y H:i:s T');

    // Setup VFS for file operations
    $root = vfsStream::setup('root');
    $filePath = vfsStream::url('root/some_style.css');
    file_put_contents($filePath, $styleContent);

    // Set a fixed modification time for the VFS file to ensure predictable results for Last-Modified header
    $file = $root->getChild('some_style.css');
    $file->lastModified($timestamp);

    $stylesMap = [$styleName => $filePath];

    // Mock ModuleFacade::allStyles()
    Mockery::mock('alias:' . ModuleFacade::class)
        ->shouldReceive('allStyles')
        ->once()
        ->andReturn($stylesMap);

    // Mock Arr::get()
    Mockery::mock('alias:' . Arr::class)
        ->shouldReceive('get')
        ->once()
        ->with($stylesMap, $styleName)
        ->andReturn($filePath);

    // Mock the global Request object (it's passed but not used internally in __invoke, so a basic mock is enough)
    $mockRequest = Mockery::mock(Request::class);

    $controller = new StyleController();

    // Act
    $response = $controller($mockRequest, $styleName);

    // Assert
    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getContent())->toBe($styleContent);
    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('Content-Type'))->toBe('text/css');
    expect($response->headers->get('Last-Modified'))->toBe($formattedTimestamp);
});

test('invoke aborts with 404 if the style path is not found in the styles map', function () {
    // Arrange
    $styleName = 'non-existent-style';
    $stylesMap = ['existing-style' => '/path/to/existing.css'];

    // Mock ModuleFacade::allStyles()
    Mockery::mock('alias:' . ModuleFacade::class)
        ->shouldReceive('allStyles')
        ->once()
        ->andReturn($stylesMap);

    // Mock Arr::get() to simulate the style not being found
    Mockery::mock('alias:' . Arr::class)
        ->shouldReceive('get')
        ->once()
        ->with($stylesMap, $styleName)
        ->andReturn(null);

    // Mock the Request object
    $mockRequest = Mockery::mock(Request::class);

    $controller = new StyleController();

    // Act & Assert
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionCode(404);

    $controller($mockRequest, $styleName);
});

test('invoke aborts with 404 if module facade returns an empty styles map', function () {
    // Arrange
    $styleName = 'any-style';
    $stylesMap = []; // Empty styles map

    // Mock ModuleFacade::allStyles()
    Mockery::mock('alias:' . ModuleFacade::class)
        ->shouldReceive('allStyles')
        ->once()
        ->andReturn($stylesMap);

    // Arr::get will naturally return null when looking for any key in an empty array
    Mockery::mock('alias:' . Arr::class)
        ->shouldReceive('get')
        ->once()
        ->with($stylesMap, $styleName)
        ->andReturn(null);

    // Mock the Request object
    $mockRequest = Mockery::mock(Request::class);

    $controller = new StyleController();

    // Act & Assert
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionCode(404);

    $controller($mockRequest, $styleName);
});

test('invoke handles style names with special characters correctly when found', function () {
    // Arrange
    $styleName = 'my_custom-style.min';
    $styleContent = '/* special chars */ body { font-size: 12px; }';
    $timestamp = time();
    $formattedTimestamp = DateTime::createFromFormat('U', $timestamp)->format('D, d M Y H:i:s T');

    // Setup VFS
    $root = vfsStream::setup('root');
    $filePath = vfsStream::url('root/special_style.css');
    file_put_contents($filePath, $styleContent);
    $root->getChild('special_style.css')->lastModified($timestamp);

    $stylesMap = [$styleName => $filePath];

    // Mock ModuleFacade
    Mockery::mock('alias:' . ModuleFacade::class)
        ->shouldReceive('allStyles')
        ->once()
        ->andReturn($stylesMap);

    // Mock Arr
    Mockery::mock('alias:' . Arr::class)
        ->shouldReceive('get')
        ->once()
        ->with($stylesMap, $styleName)
        ->andReturn($filePath);

    $mockRequest = Mockery::mock(Request::class);
    $controller = new StyleController();

    // Act
    $response = $controller($mockRequest, $styleName);

    // Assert
    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getContent())->toBe($styleContent);
    expect($response->headers->get('Last-Modified'))->toBe($formattedTimestamp);
});

test('invoke handles style names with special characters correctly when not found', function () {
    // Arrange
    $styleName = 'my_custom-style.min'; // This specific style won't be in the map
    $stylesMap = ['other-style' => '/path/to/other.css'];

    // Mock ModuleFacade
    Mockery::mock('alias:' . ModuleFacade::class)
        ->shouldReceive('allStyles')
        ->once()
        ->andReturn($stylesMap);

    // Mock Arr
    Mockery::mock('alias:' . Arr::class)
        ->shouldReceive('get')
        ->once()
        ->with($stylesMap, $styleName)
        ->andReturn(null); // Simulate not found

    $mockRequest = Mockery::mock(Request::class);
    $controller = new StyleController();

    // Act & Assert
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionCode(404);

    $controller($mockRequest, $styleName);
});



