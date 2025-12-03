<?php

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Nwidart\Modules\Facades\Module as ModuleFacade;
use Crater\Events\ModuleEnabledEvent;
use Crater\Models\Module as ModelsModule;
use Crater\Http\Controllers\V1\Admin\Modules\EnableModuleController;
use Mockery;

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

beforeEach(function () {
    Event::fake();
    // Clean Mockery before every test to ensure mocks are properly isolated.
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('it successfully enables a module and dispatches an event', function () {
    // Arrange
    $moduleName = 'InvoiceModule';

    // 1. Mock the ModelsModule instance that would be retrieved from the database
    $mockModelsModule = new class extends ModelsModule {
        public $name;
        public $id;
        public $attributes = [];

        public function update(array $attributes = [])
        {
            // Simulate update normally sets the attributes and returns true
            foreach ($attributes as $key => $value) {
                $this->$key = $value;
                $this->attributes[$key] = $value;
            }
            return true;
        }
    };
    $mockModelsModule->name = $moduleName;
    $mockModelsModule->id = 1;

    // 2. Mock the static call chain: ModelsModule::where('name', $moduleName)->first()
    Mockery::mock('alias:' . ModelsModule::class)
        ->shouldReceive('where')
        ->once()
        ->with('name', $moduleName)
        ->andReturnSelf();

    ModelsModule::shouldReceive('first')
        ->once()
        ->andReturn($mockModelsModule);

    // 3. Mock the installed Nwidart\Modules\Module instance
    $mockInstalledModule = Mockery::mock(\Nwidart\Modules\Module::class);
    $mockInstalledModule->shouldReceive('enable')
        ->once()
        ->andReturnSelf();

    // 4. Mock the Nwidart\Modules\Facades\Module facade's `find` method
    ModuleFacade::shouldReceive('find')
        ->once()
        ->with($moduleName)
        ->andReturn($mockInstalledModule);

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
});

test('it calls the authorize method with "manage modules" permission', function () {
    // Arrange
    $moduleName = 'TestModuleForAuth';

    // ModelsModule instance stub as a real model (not a Mockery mock to avoid __set issues)
    $mockModelsModule = new class extends ModelsModule {
        public $name;
        public $id;
        public $attributes = [];

        public function update(array $attributes = [])
        {
            foreach ($attributes as $key => $value) {
                $this->$key = $value;
                $this->attributes[$key] = $value;
            }
            return true;
        }
    };
    $mockModelsModule->name = $moduleName;
    $mockModelsModule->id = 2;

    Mockery::mock('alias:' . ModelsModule::class)
        ->shouldReceive('where')
        ->any()
        ->andReturnSelf();

    ModelsModule::shouldReceive('first')
        ->any()
        ->andReturn($mockModelsModule);

    $mockInstalledModule = Mockery::mock(\Nwidart\Modules\Module::class);
    $mockInstalledModule->shouldReceive('enable')->zeroOrMoreTimes();

    ModuleFacade::shouldReceive('find')->any()->andReturn($mockInstalledModule);

    $request = Request::create('/api/v1/admin/modules/' . $moduleName . '/enable');

    // Use a partial mock for the controller to assert on its own method call
    $controller = Mockery::mock(EnableModuleControllerTestable::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true);

    // Act
    $response = $controller($request, $moduleName);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);
});

test('it throws an error if the module is not found in the database', function () {
    // Arrange
    $moduleName = 'NonExistentDBModule';

    // Mock ModelsModule::where()->first() to return null
    Mockery::mock('alias:' . ModelsModule::class)
        ->shouldReceive('where')
        ->once()
        ->with('name', $moduleName)
        ->andReturnSelf();

    ModelsModule::shouldReceive('first')
        ->once()
        ->andReturn(null);

    // Create a dummy Request instance
    $request = Request::create('/api/v1/admin/modules/' . $moduleName . '/enable');

    // Act
    $controller = new EnableModuleControllerTestable();

    // Assert that calling update() on null throws an Error
    expect(fn () => $controller($request, $moduleName))
        ->toThrow(Error::class, 'Call to a member function update() on null');

    // Assert that no event was dispatched since the process failed early
    Event::assertNotDispatched(ModuleEnabledEvent::class);
});

test('it throws an error if the installed module is not found via Nwidart facade', function () {
    // Arrange
    $moduleName = 'DBModuleExistsButNwidartFails';

    $mockModelsModule = new class extends ModelsModule {
        public $name;
        public $id;
        public $attributes = [];

        public function update(array $attributes = [])
        {
            foreach ($attributes as $key => $value) {
                $this->$key = $value;
                $this->attributes[$key] = $value;
            }
            return true;
        }
    };
    $mockModelsModule->name = $moduleName;
    $mockModelsModule->id = 3;

    // Mock chain: ModelsModule::where('name', ...)->first()
    Mockery::mock('alias:' . ModelsModule::class)
        ->shouldReceive('where')
        ->once()
        ->with('name', $moduleName)
        ->andReturnSelf();

    ModelsModule::shouldReceive('first')
        ->once()
        ->andReturn($mockModelsModule);

    // ModuleFacade::find() returns null (facade fails to find module)
    ModuleFacade::shouldReceive('find')
        ->once()
        ->with($moduleName)
        ->andReturn(null);

    $request = Request::create('/api/v1/admin/modules/' . $moduleName . '/enable');

    $controller = new EnableModuleControllerTestable();

    // Assert that calling enable() on null throws an Error
    expect(fn () => $controller($request, $moduleName))
        ->toThrow(Error::class, 'Call to a member function enable() on null');

    // Assert that no event was dispatched since the process failed before event dispatch
    Event::assertNotDispatched(ModuleEnabledEvent::class);
});