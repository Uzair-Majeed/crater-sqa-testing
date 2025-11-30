<?php

use org\bovigo\vfs\vfsStream;
uses(\Mockery::class);
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Carbon\Carbon;
use Crater\Http\Controllers\V1\Modules\ScriptController;
use Crater\Services\Module\ModuleFacade;

beforeEach(function () {
    // Set up vfsStream root for each test to simulate a file system
    $this->root = vfsStream::setup('root');

    // Clear Mockery mocks before each test to prevent interference
    Mockery::close();
});

test('it successfully serves a script when the path is found', function () {
    // Arrange
    $scriptName = 'myScript.js';
    $scriptContent = 'console.log("Hello from module script!");';
    $scriptRelativePath = 'js/module/myscript.js'; // The relative path within the virtual file system
    $fullVfsPath = vfsStream::url('root/' . $scriptRelativePath);

    // Create a virtual file with content and a specific modification time
    $file = vfsStream::newFile($scriptRelativePath)
        ->at($this->root)
        ->setContent($scriptContent);

    // Set a specific modification time for the virtual file using native touch on the vfs path
    $mtime = Carbon::now()->subDays(5)->timestamp;
    touch($fullVfsPath, $mtime);

    // Mock ModuleFacade::allScripts() to return the virtual path for the requested script
    ModuleFacade::shouldReceive('allScripts')
        ->once()
        ->andReturn([$scriptName => $fullVfsPath]);

    $controller = new ScriptController();
    $request = Request::create('/scripts/' . $scriptName);

    // Act
    $response = $controller->__invoke($request, $scriptName);

    // Assertions for the successful response
    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe($scriptContent);
    expect($response->headers->get('Content-Type'))->toBe('application/javascript');

    // Compare the timestamp of the Last-Modified header's DateTime object with the original mtime
    expect($response->getLastModified()->getTimestamp())->toBe($mtime);

    // Ensure the response object has the correct Last-Modified header set in RFC 1123 format (GMT)
    $expectedLastModifiedHeader = Carbon::createFromTimestamp($mtime)
                                        ->setTimezone('GMT')
                                        ->format('D, d M Y H:i:s T');
    expect($response->headers->get('Last-Modified'))->toBe($expectedLastModifiedHeader);

})->group('script-controller');

test('it aborts with 404 when the script is not found', function () {
    // Arrange
    $scriptName = 'nonExistentScript.js';

    // Mock ModuleFacade::allScripts() to return an empty array, simulating the script not existing
    ModuleFacade::shouldReceive('allScripts')
        ->once()
        ->andReturn([]); // Arr::get will return null, triggering abort_if

    $controller = new ScriptController();
    $request = Request::create('/scripts/' . $scriptName);

    // Act & Assert: Expect a NotFoundHttpException with status code 404
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionCode(404);

    $controller->__invoke($request, $scriptName);

})->group('script-controller');

test('it aborts with 404 when script name is empty', function () {
    // Arrange
    $scriptName = ''; // Edge case: an empty script name

    // Mock ModuleFacade::allScripts(). The content doesn't matter as Arr::get will return null for an empty key.
    ModuleFacade::shouldReceive('allScripts')
        ->once()
        ->andReturn(['some_valid_script' => '/path/to/script.js']);

    $controller = new ScriptController();
    $request = Request::create('/scripts/'); // URL that would map to an empty script name

    // Act & Assert: Expect a NotFoundHttpException with status code 404
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionCode(404);

    $controller->__invoke($request, $scriptName);

})->group('script-controller');

test('it aborts with 404 if the path from allScripts is explicitly null for the given script', function () {
    // Arrange
    $scriptName = 'anotherScript.js';

    // Mock ModuleFacade::allScripts() to explicitly return null for the requested script
    ModuleFacade::shouldReceive('allScripts')
        ->once()
        ->andReturn([$scriptName => null]); // Explicitly null path, triggering abort_if

    $controller = new ScriptController();
    $request = Request::create('/scripts/' . $scriptName);

    // Act & Assert: Expect a NotFoundHttpException with status code 404
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionCode(404);

    $controller->__invoke($request, $scriptName);
})->group('script-controller');
