<?php

use Crater\Http\Controllers\V1\Admin\Modules\ModuleController;
use Crater\Http\Resources\ModuleResource;
use Crater\Space\ModuleInstaller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Mockery\MockInterface;

beforeEach(function () {
    // Ensure Mockery is closed before each test to prevent mock leakage
    Mockery::close();
});

test('invoke authorizes module management', function () {
    $request = Request::create('/');
    $moduleName = 'test-module';

    // Mock ModuleInstaller::getModule to return a success response,
    // so the code path proceeds to the authorization check.
    Mockery::mock('alias:'.ModuleInstaller::class)
        ->shouldReceive('getModule')
        ->with($moduleName)
        ->andReturn((object) ['success' => true, 'module' => (object)['id' => 1, 'name' => $moduleName], 'modules' => []]);

    // Use Mockery::spy on the controller to verify that the protected 'authorize' method is called.
    // We make a partial mock first to ensure `authorize` method is on the spy instance
    $controller = Mockery::mock(ModuleController::class)->makePartial();
    $spy = Mockery::spy($controller);

    // To prevent the test from failing due to an unmocked ModuleResource,
    // we set up a minimal overload mock for it.
    $mockModuleResourceInstance = Mockery::mock(ModuleResource::class);
    $mockModuleResourceInstance->shouldReceive('additional')->andReturnSelf(); // Allow chaining

    Mockery::mock('overload:'.ModuleResource::class)
        ->shouldReceive('__construct')
        ->andReturn($mockModuleResourceInstance);

    Mockery::mock('overload:'.ModuleResource::class)
        ->shouldReceive('collection')
        ->andReturn(Mockery::mock(Collection::class));

    // Call the method
    $spy->__invoke($request, $moduleName);

    // Assert that the 'authorize' method was called exactly once with the correct argument.
    $spy->shouldHaveReceived('authorize')->once()->with('manage modules');
});

test('invoke throws authorization exception if user cannot manage modules', function () {
    $request = Request::create('/');
    $moduleName = 'unauthorized-module';

    // Mock ModuleInstaller::getModule to return a success response,
    // ensuring the code path reaches the authorization check.
    Mockery::mock('alias:'.ModuleInstaller::class)
        ->shouldReceive('getModule')
        ->with($moduleName)
        ->andReturn((object) ['success' => true, 'module' => (object)['id' => 1, 'name' => $moduleName], 'modules' => []]);

    // Create a partial mock of the controller and configure its 'authorize' method
    // to throw an AuthorizationException when called.
    $controller = Mockery::mock(ModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andThrow(AuthorizationException::class);

    // Expect an AuthorizationException to be thrown when the __invoke method is called.
    expect(fn () => $controller->__invoke($request, $moduleName))
        ->toThrow(AuthorizationException::class);
});

test('invoke returns json response on module installer failure', function () {
    $request = Request::create('/');
    $moduleName = 'failed-module';
    // Define a mock error response for ModuleInstaller::getModule
    $errorResponse = (object) ['success' => false, 'message' => 'Module not found', 'code' => 404];

    // Mock the static method ModuleInstaller::getModule to return the failure response.
    Mockery::mock('alias:'.ModuleInstaller::class)
        ->shouldReceive('getModule')
        ->with($moduleName)
        ->andReturn($errorResponse);

    // Create a partial mock of the controller and configure 'authorize' to succeed,
    // so we can test the failure path of ModuleInstaller.
    $controller = Mockery::mock(ModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')->once()->with('manage modules')->andReturn(true);

    // Call the __invoke method.
    $response = $controller->__invoke($request, $moduleName);

    // Assert that the response is an instance of JsonResponse.
    expect($response)->toBeInstanceOf(JsonResponse::class);
    // Assert that the JSON content of the response matches the mocked error response.
    expect($response->getData())->toEqual($errorResponse);
});

test('invoke returns module resource on module installer success', function () {
    $request = Request::create('/');
    $moduleName = 'successful-module';
    // Define mock data for a successful module retrieval.
    $mockModule = (object)['id' => 1, 'name' => $moduleName, 'version' => '1.0.0'];
    $mockModules = [
        (object)['id' => 2, 'name' => 'other-module'],
        (object)['id' => 3, 'name' => 'another-module']
    ];
    $successResponse = (object) ['success' => true, 'module' => $mockModule, 'modules' => $mockModules];

    // Mock the static method ModuleInstaller::getModule to return the success response.
    Mockery::mock('alias:'.ModuleInstaller::class)
        ->shouldReceive('getModule')
        ->with($moduleName)
        ->andReturn($successResponse);

    // Create a partial mock of the controller and configure 'authorize' to succeed.
    $controller = Mockery::mock(ModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')->once()->with('manage modules')->andReturn(true);

    // Set up an overload mock for ModuleResource to control its instantiation and method calls.
    // First, mock an instance of ModuleResource that will be returned by the constructor.
    $mockModuleResourceInstance = Mockery::mock(ModuleResource::class);
    $mockModuleResourceInstance->shouldReceive('additional')
                               ->once()
                               // Verify the structure of the 'additional' data.
                               ->with(['meta' => ['modules' => Mockery::type(Collection::class)]])
                               ->andReturnSelf(); // Crucial for method chaining.

    // Mock the constructor of ModuleResource to return our specific mock instance.
    Mockery::mock('overload:'.ModuleResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($mockModule) // Ensure the correct module object is passed to the constructor.
        ->andReturn($mockModuleResourceInstance);

    // Mock the static 'collection' method of ModuleResource.
    Mockery::mock('overload:'.ModuleResource::class)
        ->shouldReceive('collection')
        ->once()
        // Verify that the collection method is called with a Collection containing the mock modules.
        ->withArgs(function ($arg) use ($mockModules) {
            return $arg instanceof Collection && $arg->toArray() === $mockModules;
        })
        ->andReturn(Mockery::mock(Collection::class)); // Return a mock Collection for the 'modules' meta data.

    // Call the __invoke method.
    $response = $controller->__invoke($request, $moduleName);

    // Assert that the response is an instance of ModuleResource.
    expect($response)->toBeInstanceOf(ModuleResource::class);
    // Assert that the returned response is our specific mocked instance.
    expect($response)->toBe($mockModuleResourceInstance);
});

test('invoke returns module resource on module installer success with empty modules list', function () {
    $request = Request::create('/');
    $moduleName = 'module-empty-list';
    // Define mock data for a successful module retrieval, with an empty 'modules' array.
    $mockModule = (object)['id' => 1, 'name' => $moduleName, 'version' => '1.0.0'];
    $mockModules = []; // Empty modules array
    $successResponse = (object) ['success' => true, 'module' => $mockModule, 'modules' => $mockModules];

    // Mock ModuleInstaller::getModule to return the success response with empty modules.
    Mockery::mock('alias:'.ModuleInstaller::class)
        ->shouldReceive('getModule')
        ->with($moduleName)
        ->andReturn($successResponse);

    // Create a partial mock of the controller and configure 'authorize' to succeed.
    $controller = Mockery::mock(ModuleController::class)->makePartial();
    $controller->shouldReceive('authorize')->once()->with('manage modules')->andReturn(true);

    // Set up an overload mock for ModuleResource.
    $mockModuleResourceInstance = Mockery::mock(ModuleResource::class);
    $mockModuleResourceInstance->shouldReceive('additional')
                               ->once()
                               ->with(['meta' => ['modules' => Mockery::type(Collection::class)]])
                               ->andReturnSelf();

    Mockery::mock('overload:'.ModuleResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($mockModule)
        ->andReturn($mockModuleResourceInstance);

    Mockery::mock('overload:'.ModuleResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($arg) use ($mockModules) {
            return $arg instanceof Collection && $arg->toArray() === $mockModules;
        })
        ->andReturn(Mockery::mock(Collection::class));

    // Call the __invoke method.
    $response = $controller->__invoke($request, $moduleName);

    // Assert that the response is an instance of ModuleResource and our specific mocked instance.
    expect($response)->toBeInstanceOf(ModuleResource::class);
    expect($response)->toBe($mockModuleResourceInstance);
});




afterEach(function () {
    Mockery::close();
});
