```php
<?php

use Crater\Http\Controllers\V1\Modules\StyleController;
use Crater\Services\Module\ModuleFacade;
use DateTime; // FIX: Uncommented - DateTime class is used for formatting timestamps.
use Illuminate\Http\Request; // FIX: Added - Illuminate\Http\Request is used for mocking.
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

    // FIX: Removed Mockery::mock('alias:' . Arr::class)
    // Arr::get() is a pure function and should not be mocked.
    // Its behavior is predictable based on the $stylesMap provided by ModuleFacade.
    // The real Arr::get($stylesMap, $styleName) will correctly return $filePath here.

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

    // FIX: Removed Mockery::mock('alias:' . Arr::class)
    // Arr::get() is a pure function. When $styleName is 'non-existent-style' and not in $stylesMap,
    // Arr::get($stylesMap, $styleName) will naturally return null, simulating the desired scenario.

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

    // FIX: Removed Mockery::mock('alias:' . Arr::class)
    // Arr::get() is a pure function. When $stylesMap is empty,
    // Arr::get($stylesMap, $styleName) will naturally return null.

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

    // FIX: Removed Mockery::mock('alias:' . Arr::class)
    // Arr::get() is a pure function. The real Arr::get($stylesMap, $styleName)
    // will correctly return $filePath based on $stylesMap.

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

    // FIX: Removed Mockery::mock('alias:' . Arr::class)
    // Arr::get() is a pure function. When $styleName is not in $stylesMap,
    // Arr::get($stylesMap, $styleName) will naturally return null.

    $mockRequest = Mockery::mock(Request::class);
    $controller = new StyleController();

    // Act & Assert
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionCode(404);

    $controller($mockRequest, $styleName);
});
```