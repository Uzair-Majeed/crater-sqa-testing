<?php

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
uses(\Mockery::class);
use Nwidart\Modules\Facades\Module as ModuleFacade;
use Crater\Events\ModuleEnabledEvent;
use Crater\Models\Module as ModelsModule;
use Crater\Http\Controllers\V1\Admin\Modules\EnableModuleController;

// Helper trait to mock the `authorize` method commonly found in controllers
// that use Illuminate\Foundation\Auth\Access\AuthorizesRequests.
trait MocksAuthorizesRequests
{
    public function authorize($ability, $arguments = [])
    {
        // For unit tests, we primarily ensure it's called.
        // Returning true here prevents an AuthorizationException during execution.
        return true;
    }
}

// Create a testable version of the controller that includes our mock trait
class EnableModuleControllerTestable extends EnableModuleController
{
    use MocksAuthorizesRequests;
}

// Set up common fakes for events before each test
beforeEach(fn () => Event::fake());

test('it successfully enables a module and dispatches an event', function () {
    // Arrange
    $moduleName = 'InvoiceModule';

    // 1. Mock the ModelsModule instance that would be retrieved from the database
    $mockModelsModule = Mockery::mock(ModelsModule::class);
    $mockModelsModule->shouldReceive('update')
                     ->once()
                     ->with(['enabled' => true])
                     ->andReturn(true); // update typically returns true/false
    $mockModelsModule->name = $moduleName; // Set property for facade and event
    $mockModelsModule->id = 1; // Set ID property for potential event usage or consistency

    // 2. Mock the static call chain: ModelsModule::where('name', $moduleName)->first()
    ModelsModule::shouldReceive('where')
                ->once()
                ->with('name', $moduleName)
                ->andReturnSelf(); // Allows chaining to ->first()
    ModelsModule::shouldReceive('first')
                ->once()
                ->andReturn($mockModelsModule); // Returns our mocked DB model

    // 3. Mock the installed Nwidart\Modules\Module instance
    $mockInstalledModule = Mockery::mock(\Nwidart\Modules\Module::class);
    $mockInstalledModule->shouldReceive('enable')
                        ->once()
                        ->andReturnSelf(); // enable typically returns the module itself

    // 4. Mock the Nwidart\Modules\Facades\Module facade's `find` method
    ModuleFacade::shouldReceive('find')
                ->once()
                ->with($moduleName)
                ->andReturn($mockInstalledModule); // Returns our mocked installed module

    // Create a dummy Request instance
    $request = Request::create('/api/v1/admin/modules/' . $moduleName . '/enable');

    // Act
    $controller = new EnableModuleControllerTestable();
    $response = $controller($request, $moduleName);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);

    // Assert that the ModuleEnabledEvent was dispatched with the correct module
    Event::assertDispatched(ModuleEnabledEvent::class, function ($event) use ($mockModelsModule) {
        return $event->module === $mockModelsModule;
    });

    Mockery::close(); // Clean up mocks
});

test('it calls the authorize method with "manage modules" permission', function () {
    // Arrange
    $moduleName = 'TestModuleForAuth';

    // Minimal mocks for other dependencies, as the focus is on the `authorize` call
    $mockModelsModule = Mockery::mock(ModelsModule::class);
    $mockModelsModule->shouldReceive('update')->zeroOrMoreTimes();
    $mockModelsModule->name = $moduleName;
    $mockModelsModule->id = 2;

    ModelsModule::shouldReceive('where')->andReturnSelf();
    ModelsModule::shouldReceive('first')->andReturn($mockModelsModule);

    $mockInstalledModule = Mockery::mock(\Nwidart\Modules\Module::class);
    $mockInstalledModule->shouldReceive('enable')->zeroOrMoreTimes();

    ModuleFacade::shouldReceive('find')->andReturn($mockInstalledModule);

    $request = Request::create('/api/v1/admin/modules/' . $moduleName . '/enable');

    // Use a partial mock for the controller to assert on its own method call
    $controller = Mockery::mock(EnableModuleControllerTestable::class)->makePartial();
    // The authorize method is public from the trait, so shouldAllowMockingProtectedMethods is not strictly needed here
    $controller->shouldReceive('authorize')
               ->once()
               ->with('manage modules')
               ->andReturn(true); // Ensure it's called and doesn't throw an exception

    // Act
    $response = $controller($request, $moduleName);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);

    Mockery::close(); // Clean up partial mock
});

test('it throws an error if the module is not found in the database', function () {
    // Arrange
    $moduleName = 'NonExistentDBModule';

    // Mock ModelsModule::where()->first() to return null
    ModelsModule::shouldReceive('where')
                ->once()
                ->with('name', $moduleName)
                ->andReturnSelf();
    ModelsModule::shouldReceive('first')
                ->once()
                ->andReturn(null); // Simulate module not found in the DB

    // Create a dummy Request instance
    $request = Request::create('/api/v1/admin/modules/' . $moduleName . '/enable');

    // Act
    $controller = new EnableModuleControllerTestable();

    // Assert that calling update() on null throws an Error
    $this->expectException(Error::class);
    $this->expectExceptionMessage('Call to a member function update() on null');

    $controller($request, $moduleName);

    // Assert that no event was dispatched since the process failed early
    Event::assertNotDispatched(ModuleEnabledEvent::class);

    Mockery::close();
});

test('it throws an error if the installed module is not found via Nwidart facade', function () {
    // Arrange
    $moduleName = 'DBModuleExistsButNwidartFails';

    // Mock the ModelsModule instance to be found in the database
    $mockModelsModule = Mockery::mock(ModelsModule::class);
    $mockModelsModule->shouldReceive('update')
                     ->once() // update() on the DB model still happens
                     ->with(['enabled' => true]);
    $mockModelsModule->name = $moduleName;
    $mockModelsModule->id = 3;

    ModelsModule::shouldReceive('where')
                ->once()
                ->with('name', $moduleName)
                ->andReturnSelf();
    ModelsModule::shouldReceive('first')
                ->once()
                ->andReturn($mockModelsModule); // Module found in DB

    // Mock Nwidart\Modules\Facades\Module::find() to return null
    ModuleFacade::shouldReceive('find')
                ->once()
                ->with($moduleName)
                ->andReturn(null); // Simulate installed module not found by the facade

    // Create a dummy Request instance
    $request = Request::create('/api/v1/admin/modules/' . $moduleName . '/enable');

    // Act
    $controller = new EnableModuleControllerTestable();

    // Assert that calling enable() on null throws an Error
    $this->expectException(Error::class);
    $this->expectExceptionMessage('Call to a member function enable() on null');

    $controller($request, $moduleName);

    // Assert that no event was dispatched since the process failed before event dispatch
    Event::assertNotDispatched(ModuleEnabledEvent::class);

    Mockery::close();
});
