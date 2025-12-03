<?php
use Crater\Http\Controllers\V1\Admin\Update\CheckVersionController;
use Illuminate\Http\Request;
use function Pest\Laravel\assertJson;

beforeEach(function () {
    // Ensure Mockery is cleaned up before each test to prevent mock expectation errors
    Mockery::close();
});

test('it returns 401 if the user is not logged in', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn(null)->once(); // User is not authenticated

    $controller = new CheckVersionController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(401);
    assertJson([
        'success' => false,
        'message' => 'You are not allowed to update this app.'
    ], $response->getContent());
});

test('it returns 401 if the logged-in user is not an owner', function () {
    $user = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(false)->once(); // User is authenticated but not an owner

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user)->once();

    $controller = new CheckVersionController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(401);
    assertJson([
        'success' => false,
        'message' => 'You are not allowed to update this app.'
    ], $response->getContent());
});

test('it successfully checks for updates when the user is an owner', function () {
    // Mock an authenticated owner user
    $user = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(true)->once();

    // Mock the Request to return the owner user
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user)->once();

    // Mock the static call to Setting::getSetting
    Mockery::mock('alias:\Crater\Models\Setting')
        ->shouldReceive('getSetting')
        ->with('version')
        ->andReturn('1.0.0') // Simulate current app version
        ->once();

    // Mock the static call to Updater::checkForUpdate
    $updateResult = ['new_version' => '1.0.1', 'status' => 'update_available', 'notes' => 'Some release notes.'];
    Mockery::mock('alias:\Crater\Space\Updater')
        ->shouldReceive('checkForUpdate')
        ->with('1.0.0') // Expect it to be called with the current version
        ->andReturn($updateResult)
        ->once();

    $controller = new CheckVersionController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(200);
    assertJson($updateResult, $response->getContent());
});

test('it handles updater returning an error response gracefully', function () {
    // Mock an authenticated owner user
    $user = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(true)->once();

    // Mock the Request to return the owner user
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user)->once();

    // Mock the static call to Setting::getSetting
    Mockery::mock('alias:\Crater\Models\Setting')
        ->shouldReceive('getSetting')
        ->with('version')
        ->andReturn('1.0.0')
        ->once();

    // Mock the static call to Updater::checkForUpdate to simulate an error
    $errorResult = ['status' => 'error', 'message' => 'Could not connect to update server.'];
    Mockery::mock('alias:\Crater\Space\Updater')
        ->shouldReceive('checkForUpdate')
        ->with('1.0.0')
        ->andReturn($errorResult)
        ->once();

    $controller = new CheckVersionController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(200); // The HTTP request itself succeeded, but the update check reported an internal error
    assertJson($errorResult, $response->getContent());
});

 

afterEach(function () {
    Mockery::close();
});
