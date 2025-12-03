<?php

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Nwidart\Modules\Facades\Module as ModuleFacade;
use Crater\Events\ModuleEnabledEvent;
use Crater\Models\Module as ModelsModule;
use Crater\Http\Controllers\V1\Admin\Modules\EnableModuleController;
use Mockery as m;

// Disable exception handler for raw controller calls
beforeEach(function () {
    Event::fake();
    m::close();

    app()->instance(
        Illuminate\Contracts\Debug\ExceptionHandler::class,
        new class implements Illuminate\Contracts\Debug\ExceptionHandler {
            public function report(Throwable $e) {}
            public function shouldReport(Throwable $e) { return false; }
            public function render($request, Throwable $e) { throw $e; }
            public function renderForConsole($output, Throwable $e) { throw $e; }
        }
    );
});

afterEach(function () {
    m::close();
});

// Helper trait for authorization
trait MocksAuthorizesRequests
{
    public function authorize($ability, $arguments = [])
    {
        return true;
    }
}

// Controller override
class EnableModuleControllerTestable extends EnableModuleController
{
    use MocksAuthorizesRequests;
}

// Create a testable fake "Module" model that correctly matches Eloquent signatures
function makeFakeModule($name, $id)
{
    return new class($name, $id) extends ModelsModule {
        public $name;
        public $id;
        public $attributes = [];

        public function __construct($name, $id)
        {
            $this->name = $name;
            $this->id   = $id;
        }

        // MUST match Eloquent's signature
        public function update(array $attributes = [], array $options = [])
        {
            foreach ($attributes as $key => $value) {
                $this->$key = $value;
                $this->attributes[$key] = $value;
            }
            return true;
        }
    };
}

test('it successfully enables a module and dispatches an event', function () {
    $moduleName = 'InvoiceModule';
    $mockModelsModule = makeFakeModule($moduleName, 1);

    m::mock('alias:' . ModelsModule::class)
        ->shouldReceive('where')
        ->once()
        ->with('name', $moduleName)
        ->andReturnSelf();

    ModelsModule::shouldReceive('first')
        ->once()
        ->andReturn($mockModelsModule);

    $mockInstalledModule = m::mock(\Nwidart\Modules\Module::class);
    $mockInstalledModule->shouldReceive('enable')->once();

    ModuleFacade::shouldReceive('find')
        ->once()
        ->with($moduleName)
        ->andReturn($mockInstalledModule);

    $request = Request::create('/api/v1/admin/modules/' . $moduleName . '/enable');
    $controller = new EnableModuleControllerTestable();

    $response = $controller($request, $moduleName);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);

    Event::assertDispatched(ModuleEnabledEvent::class, function ($event) use ($mockModelsModule) {
        return $event->module === $mockModelsModule;
    });
});

test('it calls authorize with correct permission', function () {
    $moduleName = 'TestModuleForAuth';
    $mockModelsModule = makeFakeModule($moduleName, 2);

    m::mock('alias:' . ModelsModule::class)
        ->shouldReceive('where')->andReturnSelf();

    ModelsModule::shouldReceive('first')
        ->andReturn($mockModelsModule);

    $mockInstalledModule = m::mock(\Nwidart\Modules\Module::class);
    $mockInstalledModule->shouldReceive('enable');

    ModuleFacade::shouldReceive('find')->andReturn($mockInstalledModule);

    $request = Request::create('/api/v1/admin/modules/' . $moduleName . '/enable');

    $controller = m::mock(EnableModuleControllerTestable::class)->makePartial();
    $controller->shouldReceive('authorize')->once()->with('manage modules');

    $response = $controller($request, $moduleName);

    expect($response)->toBeInstanceOf(JsonResponse::class);
});

test('throws error if DB module not found', function () {
    $moduleName = 'NonExistentDBModule';

    m::mock('alias:' . ModelsModule::class)
        ->shouldReceive('where')->once()->andReturnSelf();

    ModelsModule::shouldReceive('first')->once()->andReturn(null);

    $request = Request::create('/api/v1/admin/modules/' . $moduleName . '/enable');
    $controller = new EnableModuleControllerTestable();

    expect(fn () => $controller($request, $moduleName))
        ->toThrow(Error::class);

    Event::assertNotDispatched(ModuleEnabledEvent::class);
});

test('throws error if installed module not found', function () {
    $moduleName = 'DBModuleExistsButNwidartFails';
    $fake = makeFakeModule($moduleName, 3);

    m::mock('alias:' . ModelsModule::class)
        ->shouldReceive('where')->with('name', $moduleName)->andReturnSelf();

    ModelsModule::shouldReceive('first')->andReturn($fake);

    ModuleFacade::shouldReceive('find')->with($moduleName)->andReturn(null);

    $request = Request::create('/api/v1/admin/modules/' . $moduleName . '/enable');
    $controller = new EnableModuleControllerTestable();

    expect(fn () => $controller($request, $moduleName))
        ->toThrow(Error::class);

    Event::assertNotDispatched(ModuleEnabledEvent::class);
});
