<?php

use Illuminate\Http\Request;
use Crater\Space\ModuleInstaller;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    Mockery::close();
});

test('it authorizes module management and downloads the module successfully', function () {
    $moduleName = 'test-module';
    $moduleVersion = '1.0.0';
    $downloadResponse = ['success' => true, 'message' => 'Module downloaded successfully'];

    $request = Mockery::mock(Request::class);
    $request->module = $moduleName;
    $request->version = $moduleVersion;

    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('download')
        ->once()
        ->with($moduleName, $moduleVersion)
        ->andReturn($downloadResponse);

    $controller = Mockery::mock(\Crater\Http\Controllers\V1\Admin\Modules\DownloadModuleController::class)
        ->makePartial();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true);

    $response = $controller->__invoke($request);

    expect($response)
        ->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);

    expect($response->getData(true))
        ->toEqual($downloadResponse);
});

test('it handles authorization failure', function () {
    $moduleName = 'test-module';
    $moduleVersion = '1.0.0';

    $request = Mockery::mock(Request::class);
    $request->module = $moduleName;
    $request->version = $moduleVersion;

    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldNotReceive('download');

    $controller = Mockery::mock(\Crater\Http\Controllers\V1\Admin\Modules\DownloadModuleController::class)
        ->makePartial();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andThrow(new AuthorizationException('Unauthorized.'));

    expect(function () use ($controller, $request) {
        $controller->__invoke($request);
    })->toThrow(AuthorizationException::class, 'Unauthorized.');
});

test('it handles module download failure', function () {
    $moduleName = 'broken-module';
    $moduleVersion = '1.0.0';
    $downloadErrorResponse = ['success' => false, 'message' => 'Failed to download module: connection error'];

    $request = Mockery::mock(Request::class);
    $request->module = $moduleName;
    $request->version = $moduleVersion;

    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('download')
        ->once()
        ->with($moduleName, $moduleVersion)
        ->andReturn($downloadErrorResponse);

    $controller = Mockery::mock(\Crater\Http\Controllers\V1\Admin\Modules\DownloadModuleController::class)
        ->makePartial();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true);

    $response = $controller->__invoke($request);

    expect($response)
        ->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);

    expect($response->getData(true))
        ->toEqual($downloadErrorResponse);
    expect($response->status())
        ->toBe(200);
});

test('it handles null module or version parameters gracefully', function () {
    $moduleName = null;
    $moduleVersion = null;
    $downloadResponse = ['success' => false, 'message' => 'Module name or version cannot be empty.'];

    $request = Mockery::mock(Request::class);
    $request->module = $moduleName;
    $request->version = $moduleVersion;

    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('download')
        ->once()
        ->with($moduleName, $moduleVersion)
        ->andReturn($downloadResponse);

    $controller = Mockery::mock(\Crater\Http\Controllers\V1\Admin\Modules\DownloadModuleController::class)
        ->makePartial();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true);

    $response = $controller->__invoke($request);

    expect($response)
        ->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getData(true))
        ->toEqual($downloadResponse);
});

test('it handles empty string module or version parameters gracefully', function () {
    $moduleName = '';
    $moduleVersion = '';
    $downloadResponse = ['success' => false, 'message' => 'Module name or version cannot be empty.'];

    $request = Mockery::mock(Request::class);
    $request->module = $moduleName;
    $request->version = $moduleVersion;

    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('download')
        ->once()
        ->with($moduleName, $moduleVersion)
        ->andReturn($downloadResponse);

    $controller = Mockery::mock(\Crater\Http\Controllers\V1\Admin\Modules\DownloadModuleController::class)
        ->makePartial();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true);

    $response = $controller->__invoke($request);

    expect($response)
        ->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getData(true))
        ->toEqual($downloadResponse);
});




afterEach(function () {
    Mockery::close();
});
