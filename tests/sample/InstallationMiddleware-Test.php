<?php

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Disk;
use Crater\Models\Setting;
use Closure; // Ensure Closure is imported if using it for type hints, though not strictly needed for mocking here.

beforeEach(function () {
    Mockery::close(); // Clean up Mockery mocks after each test
});

test('it redirects to installation if database_created file does not exist', function () {
    $middleware = new \Crater\Http\Middleware\InstallationMiddleware();
    $request = Request::create('/');
    
    // FIX: Instead of mocking Closure::class (which is final), mock an invokable object.
    // Mockery::mock() without arguments creates a mock of an anonymous class that can be configured with __invoke.
    $next = Mockery::mock(); 
    $next->shouldNotReceive('__invoke'); // Ensure next is not called

    // Mock Storage facade and its disk method
    $diskMock = Mockery::mock(Disk::class);
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturn($diskMock)
        ->once();
    $diskMock->shouldReceive('has')
        ->with('database_created')
        ->andReturn(false) // database_created file does not exist
        ->once();

    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getTargetUrl())->toBe('/installation');
});

test('it redirects to installation if profile_complete setting is not completed, even if database_created exists', function () {
    $middleware = new \Crater\Http\Middleware\InstallationMiddleware();
    $request = Request::create('/');
    
    // FIX: Mock an invokable object.
    $next = Mockery::mock();
    $next->shouldNotReceive('__invoke');

    // Mock Storage facade to indicate database_created exists
    $diskMock = Mockery::mock(Disk::class);
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturn($diskMock)
        ->once();
    $diskMock->shouldReceive('has')
        ->with('database_created')
        ->andReturn(true)
        ->once();

    // Mock Setting::getSetting to return an incomplete status
    Mockery::mock('alias:'.Setting::class)
        ->shouldReceive('getSetting')
        ->with('profile_complete')
        ->andReturn('INCOMPLETE') // Any string that is not 'COMPLETED'
        ->once();

    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getTargetUrl())->toBe('/installation');
});

test('it calls the next middleware if all conditions are met', function () {
    $middleware = new \Crater\Http\Middleware\InstallationMiddleware();
    $request = Request::create('/');
    $expectedResponse = 'Next Middleware Response';
    
    // FIX: Mock an invokable object.
    $next = Mockery::mock();
    $next->shouldReceive('__invoke')
        ->with($request)
        ->andReturn($expectedResponse)
        ->once(); // Ensure next is called once

    // Mock Storage facade to indicate database_created exists
    $diskMock = Mockery::mock(Disk::class);
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturn($diskMock)
        ->once();
    $diskMock->shouldReceive('has')
        ->with('database_created')
        ->andReturn(true)
        ->once();

    // Mock Setting::getSetting to return 'COMPLETED'
    Mockery::mock('alias:'.Setting::class)
        ->shouldReceive('getSetting')
        ->with('profile_complete')
        ->andReturn('COMPLETED')
        ->once();

    $response = $middleware->handle($request, $next);

    expect($response)->toBe($expectedResponse);
});

test('it redirects to installation if profile_complete setting is null', function () {
    $middleware = new \Crater\Http\Middleware\InstallationMiddleware();
    $request = Request::create('/');
    
    // FIX: Mock an invokable object.
    $next = Mockery::mock();
    $next->shouldNotReceive('__invoke');

    // Mock Storage facade to indicate database_created exists
    $diskMock = Mockery::mock(Disk::class);
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturn($diskMock)
        ->once();
    $diskMock->shouldReceive('has')
        ->with('database_created')
        ->andReturn(true)
        ->once();

    // Mock Setting::getSetting to return null
    Mockery::mock('alias:'.Setting::class)
        ->shouldReceive('getSetting')
        ->with('profile_complete')
        ->andReturn(null)
        ->once();

    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getTargetUrl())->toBe('/installation');
});

test('it redirects to installation if profile_complete setting is an empty string', function () {
    $middleware = new \Crater\Http\Middleware\InstallationMiddleware();
    $request = Request::create('/');
    
    // FIX: Mock an invokable object.
    $next = Mockery::mock();
    $next->shouldNotReceive('__invoke');

    // Mock Storage facade to indicate database_created exists
    $diskMock = Mockery::mock(Disk::class);
    Storage::shouldReceive('disk')
        ->with('local')
        ->andReturn($diskMock)
        ->once();
    $diskMock->shouldReceive('has')
        ->with('database_created')
        ->andReturn(true)
        ->once();

    // Mock Setting::getSetting to return an empty string
    Mockery::mock('alias:'.Setting::class)
        ->shouldReceive('getSetting')
        ->with('profile_complete')
        ->andReturn('')
        ->once();

    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getTargetUrl())->toBe('/installation');
});

afterEach(function () {
    Mockery::close();
});