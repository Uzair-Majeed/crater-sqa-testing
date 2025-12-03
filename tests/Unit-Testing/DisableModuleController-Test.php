<?php

use Crater\Events\ModuleDisabledEvent;
use Crater\Http\Controllers\V1\Admin\Modules\DisableModuleController;
use Crater\Models\Module as ModelsModule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Nwidart\Modules\Facades\Module;
use Mockery\MockInterface;

// Ensure Mockery expectations are cleared before each test
beforeEach(function () {
    Mockery::close();
});

test('it successfully disables a module and dispatches an event', function () {
    $moduleName = 'TestModule';

    // 1. Mock the ModelsModule instance that will be found in the database
    $modelsModuleMock = Mockery::mock(ModelsModule::class);
    $modelsModuleMock->name = $moduleName; // Set the name property
    $modelsModuleMock->shouldReceive('update')
                     ->once()
                     ->with(['enabled' => false])
                     ->andReturn(true); // Simulate successful update

    // 2. Mock the query builder for ModelsModule::where()->first()
    $queryBuilderMock = Mockery::mock();
    $queryBuilderMock->shouldReceive('first')
                     ->once()
                     ->andReturn($modelsModuleMock);

    // 3. Mock the static ModelsModule::where method
    ModelsModule::shouldReceive('where')
                ->once()
                ->with('name', $moduleName)
                ->andReturn($queryBuilderMock);

    // 4. Mock the installed Nwidart module instance
    $installedModuleMock = Mockery::mock();
    $installedModuleMock->shouldReceive('disable')
                        ->once()
                        ->andReturn(null); // Assuming disable returns void

    // 5. Mock the Nwidart\Modules\Facades\Module::find method
    Module::shouldReceive('find')
          ->once()
          ->with($moduleName)
          ->andReturn($installedModuleMock);

    // 6. Mock the ModuleDisabledEvent::dispatch method
    ModuleDisabledEvent::shouldReceive('dispatch')
                       ->once()
                       ->with(Mockery::on(function ($arg) use ($modelsModuleMock) {
                           // Ensure the event is dispatched with the correct ModelsModule instance
                           return $arg === $modelsModuleMock;
                       }))
                       ->andReturn(null); // Assuming dispatch returns void

    // 7. Create a mock Request object
    $request = Request::create('/test', 'POST');

    // 8. Create a partial mock of the controller to simulate successful authorization
    $controller = Mockery::mock(DisableModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true); // Simulate successful authorization

    // Call the __invoke method
    $response = $controller->__invoke($request, $moduleName);

    // Assert the response
    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true))->toEqual(['success' => true]);

    // Verify all Mockery expectations were met
    Mockery::close();
});

test('it throws AuthorizationException if the user is not authorized', function () {
    $moduleName = 'AnyModule';
    $request = Request::create('/test', 'POST');

    // Create a partial mock of the controller and force authorize to throw an exception
    $controller = Mockery::mock(DisableModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andThrow(new AuthorizationException('User not authorized to manage modules.'));

    // Expect an AuthorizationException to be thrown
    expect(fn () => $controller->__invoke($request, $moduleName))
        ->toThrow(AuthorizationException::class, 'User not authorized to manage modules.');

    // Ensure no other module-related methods are called if authorization fails
    ModelsModule::shouldNotReceive('where');
    Module::shouldNotReceive('find');
    ModuleDisabledEvent::shouldNotReceive('dispatch');

    Mockery::close();
});

test('it throws TypeError if ModelsModule not found in the database', function () {
    $moduleName = 'NonExistentModule';
    $request = Request::create('/test', 'POST');

    // 1. Mock the query builder for ModelsModule::where()->first() to return null
    $queryBuilderMock = Mockery::mock();
    $queryBuilderMock->shouldReceive('first')
                     ->once()
                     ->andReturn(null); // Module not found

    // 2. Mock the static ModelsModule::where method
    ModelsModule::shouldReceive('where')
                ->once()
                ->with('name', $moduleName)
                ->andReturn($queryBuilderMock);

    // Create a partial mock of the controller to simulate successful authorization
    $controller = Mockery::mock(DisableModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    // Expect a TypeError because the code attempts to call ->update() on null
    expect(fn () => $controller->__invoke($request, $moduleName))
        ->toThrow(TypeError::class, "Attempt to read property 'update' on null");

    // Ensure Nwidart module operations and event dispatch are not attempted
    Module::shouldNotReceive('find');
    ModuleDisabledEvent::shouldNotReceive('dispatch');

    Mockery::close();
});

test('it throws TypeError if Nwidart module cannot be found, even if database model exists', function () {
    $moduleName = 'ExistingDbModuleButMissingNwidart';
    $request = Request::create('/test', 'POST');

    // 1. Mock the ModelsModule instance that will be found in the database
    $modelsModuleMock = Mockery::mock(ModelsModule::class);
    $modelsModuleMock->name = $moduleName;
    $modelsModuleMock->shouldReceive('update')
                     ->once()
                     ->with(['enabled' => false])
                     ->andReturn(true);

    // 2. Mock the query builder for ModelsModule::where()->first()
    $queryBuilderMock = Mockery::mock();
    $queryBuilderMock->shouldReceive('first')
                     ->once()
                     ->andReturn($modelsModuleMock);

    // 3. Mock the static ModelsModule::where method
    ModelsModule::shouldReceive('where')
                ->once()
                ->with('name', $moduleName)
                ->andReturn($queryBuilderMock);

    // 4. Mock the Nwidart\Modules\Facades\Module::find method to return null
    Module::shouldReceive('find')
          ->once()
          ->with($moduleName)
          ->andReturn(null); // Nwidart module not found

    // Create a partial mock of the controller to simulate successful authorization
    $controller = Mockery::mock(DisableModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true);

    // Expect a TypeError because the code attempts to call ->disable() on null
    expect(fn () => $controller->__invoke($request, $moduleName))
        ->toThrow(TypeError::class, "Attempt to call method \"disable\" on null");

    // Ensure the event is NOT dispatched in this failure scenario
    ModuleDisabledEvent::shouldNotReceive('dispatch');

    Mockery::close();
});




afterEach(function () {
    Mockery::close();
});
