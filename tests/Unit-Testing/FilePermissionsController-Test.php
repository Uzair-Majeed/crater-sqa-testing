<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Crater\Http\Controllers\V1\Installation\FilePermissionsController;
use Crater\Space\FilePermissionChecker;
uses(\Mockery::class);

test('constructor injects FilePermissionChecker correctly', function () {
    $mockChecker = Mockery::mock(FilePermissionChecker::class);

    $controller = new FilePermissionsController($mockChecker);

    // Use reflection to access the protected 'permissions' property for white-box testing
    $reflectionProperty = new ReflectionProperty(FilePermissionsController::class, 'permissions');
    $reflectionProperty->setAccessible(true);

    expect($reflectionProperty->getValue($controller))->toBe($mockChecker);

    Mockery::close();
});

test('permissions method checks and returns file permissions successfully', function () {
    $mockChecker = Mockery::mock(FilePermissionChecker::class);

    // Define the expected configuration value that the controller will request
    $expectedConfigPermissions = [
        'storage' => [
            'folder' => 'storage',
            'permission' => '775',
            'type' => 'folder',
        ],
        'bootstrap_cache' => [
            'folder' => 'bootstrap/cache',
            'permission' => '775',
            'type' => 'folder',
        ],
    ];

    // Mock the Config facade to return our specific config for 'installer.permissions'
    Config::shouldReceive('get')
          ->with('installer.permissions')
          ->andReturn($expectedConfigPermissions)
          ->once();

    // Define the expected result from the FilePermissionChecker
    $checkerResult = [
        [
            'folder' => 'storage',
            'permission' => '775',
            'isWritable' => true,
            'isSet' => true,
            'status' => true,
        ],
        [
            'folder' => 'bootstrap/cache',
            'permission' => '775',
            'isWritable' => false, // Example of a failed permission check
            'isSet' => false,
            'status' => false,
        ],
    ];

    // Expect the FilePermissionChecker's 'check' method to be called with the config value
    // and return our predefined result
    $mockChecker->shouldReceive('check')
                ->with($expectedConfigPermissions)
                ->andReturn($checkerResult)
                ->once();

    $controller = new FilePermissionsController($mockChecker);
    $response = $controller->permissions();

    // Assert the response is a JsonResponse
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    // Assert the JSON data structure and content
    $responseData = $response->getData(true); // true to get an associative array
    expect($responseData)->toBeArray()
                         ->toHaveKey('permissions')
                         ->and($responseData['permissions'])->toBe($checkerResult);

    Mockery::close();
});

test('permissions method handles an empty configuration for permissions gracefully', function () {
    $mockChecker = Mockery::mock(FilePermissionChecker::class);

    // Simulate an empty configuration
    $expectedConfigPermissions = [];
    Config::shouldReceive('get')
          ->with('installer.permissions')
          ->andReturn($expectedConfigPermissions)
          ->once();

    // The checker should also return an empty array if given an empty config
    $checkerResult = [];
    $mockChecker->shouldReceive('check')
                ->with($expectedConfigPermissions)
                ->andReturn($checkerResult)
                ->once();

    $controller = new FilePermissionsController($mockChecker);
    $response = $controller->permissions();

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $responseData = $response->getData(true);
    expect($responseData)->toBeArray()
                         ->toHaveKey('permissions')
                         ->and($responseData['permissions'])->toBe($checkerResult);

    Mockery::close();
});

test('permissions method handles FilePermissionChecker returning an empty array', function () {
    $mockChecker = Mockery::mock(FilePermissionChecker::class);

    // Simulate a non-empty configuration passed to the checker
    $expectedConfigPermissions = ['dummy_path' => ['folder' => 'dummy', 'permission' => '777']];
    Config::shouldReceive('get')
          ->with('installer.permissions')
          ->andReturn($expectedConfigPermissions)
          ->once();

    // Simulate the checker returning an empty array even with input
    $checkerResult = [];
    $mockChecker->shouldReceive('check')
                ->with($expectedConfigPermissions)
                ->andReturn($checkerResult)
                ->once();

    $controller = new FilePermissionsController($mockChecker);
    $response = $controller->permissions();

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $responseData = $response->getData(true);
    expect($responseData)->toBeArray()
                         ->toHaveKey('permissions')
                         ->and($responseData['permissions'])->toBe($checkerResult);

    Mockery::close();
});

test('permissions method handles diverse permission configurations and results', function () {
    $mockChecker = Mockery::mock(FilePermissionChecker::class);

    // Simulate a diverse configuration including folders and files
    $expectedConfigPermissions = [
        'storage' => ['folder' => 'storage', 'permission' => '775', 'type' => 'folder'],
        'env_file' => ['file' => '.env', 'permission' => '644', 'type' => 'file'],
        'logs' => ['folder' => 'storage/logs', 'permission' => '775', 'type' => 'folder'],
    ];

    Config::shouldReceive('get')
          ->with('installer.permissions')
          ->andReturn($expectedConfigPermissions)
          ->once();

    // Simulate mixed results from the checker (success and failure)
    $checkerResult = [
        ['folder' => 'storage', 'permission' => '775', 'isWritable' => true, 'isSet' => true, 'status' => true],
        ['file' => '.env', 'permission' => '644', 'isWritable' => false, 'isSet' => true, 'status' => false], // .env not writable
        ['folder' => 'storage/logs', 'permission' => '775', 'isWritable' => true, 'isSet' => true, 'status' => true],
    ];

    $mockChecker->shouldReceive('check')
                ->with($expectedConfigPermissions)
                ->andReturn($checkerResult)
                ->once();

    $controller = new FilePermissionsController($mockChecker);
    $response = $controller->permissions();

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $responseData = $response->getData(true);
    expect($responseData)->toBeArray()
                         ->toHaveKey('permissions')
                         ->and($responseData['permissions'])->toBe($checkerResult);

    Mockery::close();
});
