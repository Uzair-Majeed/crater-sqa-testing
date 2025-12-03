<?php

use Crater\Http\Controllers\V1\Admin\Modules\CopyModuleController;
use Crater\Space\ModuleInstaller;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Mockery\MockInterface;
use Mockery;

// Pest does not provide a global "mock()" helper; use Mockery::mock instead

test('it successfully copies module files when user is authorized', function () {
    // Arrange
    /** @var MockInterface|Request $request */
    $request = Mockery::mock(Request::class);
    $request->module = 'test-module-name';
    $request->path = '/var/www/html/crater/modules';

    // Mock the static ModuleInstaller class to control its `copyFiles` method
    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('copyFiles')
        ->once()
        ->with('test-module-name', '/var/www/html/crater/modules')
        ->andReturn(true); // Simulate a successful copy operation

    // Mock the controller itself to intercept the `authorize` method call
    /** @var MockInterface|CopyModuleController $controller */
    $controller = Mockery::mock(CopyModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true); // Simulate successful authorization

    // Act
    $response = $controller->__invoke($request);

    // Assert
    $response->assertSuccessful(); // HTTP status code 200
    $response->assertJson(['success' => true]);
});

test('it indicates failure when module file copy fails but user is authorized', function () {
    // Arrange
    /** @var MockInterface|Request $request */
    $request = Mockery::mock(Request::class);
    $request->module = 'another-module';
    $request->path = '/app/data/modules';

    // Mock the static ModuleInstaller class to return false, simulating a copy failure
    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('copyFiles')
        ->once()
        ->with('another-module', '/app/data/modules')
        ->andReturn(false); // Simulate a failed copy operation

    // Mock the controller and its authorize method
    /** @var MockInterface|CopyModuleController $controller */
    $controller = Mockery::mock(CopyModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true); // User is still authorized

    // Act
    $response = $controller->__invoke($request);

    // Assert
    $response->assertSuccessful(); // HTTP response is still 200, the 'success' flag in JSON indicates the operation's outcome
    $response->assertJson(['success' => false]);
});

test('it throws AuthorizationException if user is not authorized to manage modules', function () {
    // Arrange
    /** @var MockInterface|Request $request */
    $request = Mockery::mock(Request::class);
    $request->module = 'unauthorized-module';
    $request->path = '/tmp/modules';

    // Ensure ModuleInstaller::copyFiles is NOT called if authorization fails before it
    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldNotReceive('copyFiles');

    // Mock the controller to simulate an authorization failure by throwing an exception
    /** @var MockInterface|CopyModuleController $controller */
    $controller = Mockery::mock(CopyModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andThrow(new AuthorizationException('Unauthorized to manage modules.'));

    // Act & Assert
    expect(function () use ($controller, $request) {
        $controller->__invoke($request);
    })->toThrow(AuthorizationException::class, 'Unauthorized to manage modules.');
});

test('it passes null module and path parameters to ModuleInstaller::copyFiles', function () {
    // Arrange
    /** @var MockInterface|Request $request */
    $request = Mockery::mock(Request::class);
    $request->module = null; // Simulate a missing 'module' parameter
    $request->path = null;   // Simulate a missing 'path' parameter

    // Mock ModuleInstaller to expect nulls and return true
    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('copyFiles')
        ->once()
        ->with(null, null)
        ->andReturn(true);

    // Mock controller for successful authorization
    /** @var MockInterface|CopyModuleController $controller */
    $controller = Mockery::mock(CopyModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true);

    // Act
    $response = $controller->__invoke($request);

    // Assert
    $response->assertSuccessful();
    $response->assertJson(['success' => true]);
});

test('it passes empty string module and path parameters to ModuleInstaller::copyFiles', function () {
    // Arrange
    /** @var MockInterface|Request $request */
    $request = Mockery::mock(Request::class);
    $request->module = ''; // Simulate an empty string 'module' parameter
    $request->path = '';   // Simulate an empty string 'path' parameter

    // Mock ModuleInstaller to expect empty strings and return false
    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('copyFiles')
        ->once()
        ->with('', '')
        ->andReturn(false);

    // Mock controller for successful authorization
    /** @var MockInterface|CopyModuleController $controller */
    $controller = Mockery::mock(CopyModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true);

    // Act
    $response = $controller->__invoke($request);

    // Assert
    $response->assertSuccessful();
    $response->assertJson(['success' => false]);
});

afterEach(function () {
    Mockery::close();
});