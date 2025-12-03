<?php

use Crater\Http\Controllers\V1\Admin\Modules\UploadModuleController;
use Crater\Http\Requests\UploadModuleRequest;
use Crater\Space\ModuleInstaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Access\AuthorizationException;

// Set up Mockery for static methods
beforeEach(function () {
    Mockery::mock('alias:' . ModuleInstaller::class);
});


test('invoke method successfully uploads module and returns json response', function () {
    // Arrange
    $mockRequest = Mockery::mock(UploadModuleRequest::class);
    
    // Create a partial mock of the controller to mock its inherited 'authorize' method
    $controller = Mockery::mock(UploadModuleController::class)->makePartial();
    
    // Expect authorize to be called with 'manage modules' and succeed
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    // Define the expected response from ModuleInstaller::upload
    $mockInstallerResponse = ['success' => true, 'message' => 'Module uploaded successfully'];
    
    // Expect ModuleInstaller::upload to be called with the request and return the mock response
    ModuleInstaller::shouldReceive('upload')
                   ->once()
                   ->with($mockRequest)
                   ->andReturn($mockInstallerResponse);

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($mockInstallerResponse);
    expect($response->getStatusCode())->toBe(200);
});

test('invoke method throws authorization exception if user cannot manage modules', function () {
    // Arrange
    $mockRequest = Mockery::mock(UploadModuleRequest::class);
    
    // Create a partial mock of the controller to mock its inherited 'authorize' method
    $controller = Mockery::mock(UploadModuleController::class)->makePartial();
    
    // Expect authorize to be called and throw an AuthorizationException
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andThrow(new AuthorizationException());

    // Ensure ModuleInstaller::upload is NOT called if authorization fails
    ModuleInstaller::shouldNotReceive('upload');

    // Act & Assert
    // Expect the AuthorizationException to be thrown when invoking the controller
    expect(fn () => $controller->__invoke($mockRequest))
        ->throws(AuthorizationException::class);
});

test('invoke method handles empty array response from module installer', function () {
    // Arrange
    $mockRequest = Mockery::mock(UploadModuleRequest::class);
    $controller = Mockery::mock(UploadModuleController::class)->makePartial();
    
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    // ModuleInstaller returns an empty array
    $mockInstallerResponse = [];
    ModuleInstaller::shouldReceive('upload')
                   ->once()
                   ->with($mockRequest)
                   ->andReturn($mockInstallerResponse);

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($mockInstallerResponse);
    expect($response->getStatusCode())->toBe(200);
});

test('invoke method handles complex data response from module installer', function () {
    // Arrange
    $mockRequest = Mockery::mock(UploadModuleRequest::class);
    $controller = Mockery::mock(UploadModuleController::class)->makePartial();
    
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    // ModuleInstaller returns a complex data structure
    $mockInstallerResponse = [
        'success' => false,
        'errors' => [
            'file' => 'Invalid module package.',
            'code' => 'MODULE_ERROR_001'
        ],
        'data' => null
    ];
    ModuleInstaller::shouldReceive('upload')
                   ->once()
                   ->with($mockRequest)
                   ->andReturn($mockInstallerResponse);

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($mockInstallerResponse);
    expect($response->getStatusCode())->toBe(200);
});

test('invoke method ensures ModuleInstaller upload is called with the correct request object', function () {
    // Arrange
    $mockRequest = Mockery::mock(UploadModuleRequest::class);
    $controller = Mockery::mock(UploadModuleController::class)->makePartial();
    
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    // Use a spy to capture the argument passed to ModuleInstaller::upload
    $spy = Mockery::spy();
    ModuleInstaller::shouldReceive('upload')
                   ->once()
                   ->with(Mockery::capture($spy)) // Capture the argument
                   ->andReturn(['status' => 'ok']);

    // Act
    $controller->__invoke($mockRequest);

    // Assert
    expect($spy)->toBe($mockRequest); // Verify that the captured argument is the mock request
});

test('invoke method handles null response from module installer by returning a null json response', function () {
    // Arrange
    $mockRequest = Mockery::mock(UploadModuleRequest::class);
    $controller = Mockery::mock(UploadModuleController::class)->makePartial();
    
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    // ModuleInstaller returns null
    ModuleInstaller::shouldReceive('upload')
                   ->once()
                   ->with($mockRequest)
                   ->andReturn(null);

    // Act
    $response = $controller->__invoke($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getContent())->toBe('null'); // Laravel's json() helper serializes null to the string "null"
    expect($response->getStatusCode())->toBe(200);
});




afterEach(function () {
    Mockery::close();
});
