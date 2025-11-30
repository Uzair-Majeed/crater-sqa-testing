<?php

uses(\Mockery::class);
use Crater\Http\Controllers\V1\Admin\Modules\ModulesController;
use Crater\Space\ModuleInstaller;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    // Ensure Mockery is reset before each test to prevent interference
    Mockery::close();
});

test('invoke calls authorize and module installer, returning the modules', function () {
    // Arrange
    $expectedModules = [
        ['name' => 'Module A', 'version' => '1.0', 'installed' => true],
        ['name' => 'Module B', 'version' => '2.1', 'installed' => false],
    ];

    // Mock the static call to ModuleInstaller::getModules()
    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('getModules')
        ->once()
        ->andReturn($expectedModules);

    // Create a partial mock of the controller to intercept the authorize method
    $controller = Mockery::mock(ModulesController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true); // Simulate successful authorization

    $request = Mockery::mock(Request::class);

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBe($expectedModules);
});

test('invoke returns an empty array if no modules are found', function () {
    // Arrange
    $expectedModules = [];

    // Mock the static call to ModuleInstaller::getModules() to return an empty array
    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('getModules')
        ->once()
        ->andReturn($expectedModules);

    // Create a partial mock of the controller to intercept the authorize method
    $controller = Mockery::mock(ModulesController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true); // Simulate successful authorization

    $request = Mockery::mock(Request::class);

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBe($expectedModules);
});

test('invoke throws AuthorizationException if user is not authorized', function () {
    // Arrange
    // Expect an AuthorizationException to be thrown
    $this->expectException(AuthorizationException::class);
    $this->expectExceptionMessage('This action is unauthorized.');

    // ModuleInstaller::getModules() should NOT be called if authorization fails
    Mockery::mock('alias:' . ModuleInstaller::class)
        ->shouldNotReceive('getModules');

    // Create a partial mock of the controller to simulate authorization failure
    $controller = Mockery::mock(ModulesController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andThrow(new AuthorizationException('This action is unauthorized.')); // Simulate authorization failure

    $request = Mockery::mock(Request::class);

    // Act
    $controller->__invoke($request);

    // Assertions are handled by expectException
});
