<?php
use Mockery as m;
use Crater\Http\Controllers\V1\Admin\Modules\CompleteModuleInstallationController;
use Crater\Space\ModuleInstaller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    m::close();
});

test('it authorizes module management', function () {
    $request = m::mock(Request::class);
    $request->module = 'test_module';
    $request->version = '1.0.0';

    Gate::shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true);

    m::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('complete')
        ->once()
        ->with('test_module', '1.0.0')
        ->andReturn(true);

    $controller = new CompleteModuleInstallationController();
    $response = $controller($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);
    expect($response->getStatusCode())->toBe(200);
});

test('it calls ModuleInstaller::complete with correct arguments and returns success true', function () {
    $request = m::mock(Request::class);
    $request->module = 'my-awesome-module';
    $request->version = '2.5.1';

    Gate::shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true);

    m::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('complete')
        ->once()
        ->with('my-awesome-module', '2.5.1')
        ->andReturn(true);

    $controller = new CompleteModuleInstallationController();
    $response = $controller($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);
    expect($response->getStatusCode())->toBe(200);
});

test('it calls ModuleInstaller::complete with correct arguments and returns success false on failure', function () {
    $request = m::mock(Request::class);
    $request->module = 'failing-module';
    $request->version = '1.0.0';

    Gate::shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true);

    m::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('complete')
        ->once()
        ->with('failing-module', '1.0.0')
        ->andReturn(false);

    $controller = new CompleteModuleInstallationController();
    $response = $controller($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => false]);
    expect($response->getStatusCode())->toBe(200);
});

test('it handles null or empty module and version gracefully', function () {
    $request = m::mock(Request::class);
    $request->module = null;
    $request->version = '';

    Gate::shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andReturn(true);

    m::mock('alias:' . ModuleInstaller::class)
        ->shouldReceive('complete')
        ->once()
        ->with(null, '')
        ->andReturn(true);

    $controller = new CompleteModuleInstallationController();
    $response = $controller($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);
    expect($response->getStatusCode())->toBe(200);
});

test('it throws AuthorizationException if gate authorization fails', function () {
    $request = m::mock(Request::class);
    $request->module = 'any-module';
    $request->version = 'any-version';

    Gate::shouldReceive('authorize')
        ->once()
        ->with('manage modules')
        ->andThrow(new AuthorizationException('User not authorized.'));

    m::mock('alias:' . ModuleInstaller::class)
        ->shouldNotReceive('complete');

    $controller = new CompleteModuleInstallationController();

    $this->expectException(AuthorizationException::class);
    $this->expectExceptionMessage('User not authorized.');

    $controller($request);
});

 

afterEach(function () {
    Mockery::close();
});
