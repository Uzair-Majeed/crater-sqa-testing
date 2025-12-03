<?php

use Mockery as m;
use Crater\Http\Controllers\V1\Admin\Modules\UnzipModuleController;
use Crater\Http\Requests\UnzipUpdateRequest;
use Crater\Space\ModuleInstaller;
use Illuminate\Http\JsonResponse;

// Helper to mock static methods using Mockery's alias feature
function mockStatic(string $class)
{
    return m::mock('alias:' . $class);
}

// Ensure Mockery is closed after each test
afterEach(function () {
    m::close();
});

test('it successfully unzips a module and returns the path', function () {
    // Arrange
    $moduleName = 'test-module';
    $targetPath = '/tmp/modules';
    $expectedPath = $targetPath . '/' . $moduleName; // Simulate the installer returning a full path

    // Mock the UnzipUpdateRequest to provide input data
    $request = m::mock(UnzipUpdateRequest::class);
    $request->module = $moduleName;
    $request->path = $targetPath;

    // Mock ModuleInstaller::unzip static method
    $mockModuleInstaller = mockStatic(ModuleInstaller::class);
    $mockModuleInstaller->shouldReceive('unzip')
        ->once()
        ->with($moduleName, $targetPath)
        ->andReturn($expectedPath);

    // Mock the controller's `authorize` method (from the base Controller class)
    $controller = m::mock(UnzipModuleController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods()
               ->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true); // Simulate successful authorization

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    $responseData = $response->getData(true);
    expect($responseData)->toEqual([
        'success' => true,
        'path' => $expectedPath,
    ]);
});

test('it throws an exception if ModuleInstaller::unzip fails', function () {
    // Arrange
    $moduleName = 'bad-module';
    $targetPath = '/tmp/modules';
    $exceptionMessage = 'Failed to extract module zip file due to disk error.';

    // Mock the UnzipUpdateRequest
    $request = m::mock(UnzipUpdateRequest::class);
    $request->module = $moduleName;
    $request->path = $targetPath;

    // Mock ModuleInstaller::unzip to throw a RuntimeException
    $mockModuleInstaller = mockStatic(ModuleInstaller::class);
    $mockModuleInstaller->shouldReceive('unzip')
        ->once()
        ->with($moduleName, $targetPath)
        ->andThrow(new RuntimeException($exceptionMessage));

    // Mock the controller's `authorize` method to pass
    $controller = m::mock(UnzipModuleController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods()
               ->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true); // Simulate successful authorization

    // Act & Assert
    expect(function () use ($controller, $request) {
        $controller->__invoke($request);
    })->toThrow(RuntimeException::class, $exceptionMessage);
});

test('it handles a null path returned by ModuleInstaller::unzip', function () {
    // Arrange
    $moduleName = 'empty-module';
    $targetPath = '/tmp/modules';
    $returnedPath = null; // Simulate unzip returning null, indicating no specific path could be determined

    // Mock the UnzipUpdateRequest
    $request = m::mock(UnzipUpdateRequest::class);
    $request->module = $moduleName;
    $request->path = $targetPath;

    // Mock ModuleInstaller::unzip static method to return null
    $mockModuleInstaller = mockStatic(ModuleInstaller::class);
    $mockModuleInstaller->shouldReceive('unzip')
        ->once()
        ->with($moduleName, $targetPath)
        ->andReturn($returnedPath);

    // Mock the controller's `authorize` method
    $controller = m::mock(UnzipModuleController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods()
               ->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true); // Simulate successful authorization

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    $responseData = $response->getData(true);
    expect($responseData)->toEqual([
        'success' => true,
        'path' => $returnedPath, // Expect null in the response as returned by the mock
    ]);
});



