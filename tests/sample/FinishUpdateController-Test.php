<?php

use Crater\Http\Controllers\V1\Admin\Update\FinishUpdateController;
use Crater\Space\Updater;
use Illuminate\Http\Request;
use Mockery as m;

// Ensure Mockery mocks are closed after each test to prevent conflicts,
// especially important for static mocks.
afterEach(function () {
    m::close();
});

test('it returns unauthorized if no user is authenticated', function () {
    $request = m::mock(Request::class);
    $request->shouldReceive('user')->andReturn(null);

    $controller = new FinishUpdateController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toBe([
            'success' => false,
            'message' => 'You are not allowed to update this app.'
        ]);
});

test('it returns unauthorized if the authenticated user is not an owner', function () {
    $user = m::mock(); // Generic mock for user, only isOwner() is needed.
    $user->shouldReceive('isOwner')->andReturn(false);

    $request = m::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);

    $controller = new FinishUpdateController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toBe([
            'success' => false,
            'message' => 'You are not allowed to update this app.'
        ]);
});

test('it validates the request parameters for installed and version', function () {
    $user = m::mock();
    $user->shouldReceive('isOwner')->andReturn(true);

    $request = m::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);

    // Expect validate method to be called once with specific rules
    $request->shouldReceive('validate')
            ->once()
            ->with([
                'installed' => 'required',
                'version' => 'required',
            ]);

    // Set properties on the mock request object, as validate() would use them
    // or the controller would access them directly after validation.
    // If controller uses $validated['installed'], then `validate` mock would need to return it.
    // Assuming for now, controller accesses $request->installed directly.
    $request->installed = '2.0.0';
    $request->version = '2.0.1';

    // Mock the static Updater::finishUpdate method, as it will be called after validation
    // Use Mockery's alias mock syntax.
    m::mock('alias:' . Updater::class)
        ->shouldReceive('finishUpdate')
        ->once()
        ->with('2.0.0', '2.0.1')
        ->andReturn(['some_response' => 'data']); // Return some data to complete the call

    $controller = new FinishUpdateController();
    $controller->__invoke($request); // Just calling to trigger the validation expectation
});

test('it successfully processes the update request and returns updater response', function () {
    $user = m::mock();
    $user->shouldReceive('isOwner')->andReturn(true);

    $request = m::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);

    // Mock validate to pass. If the controller expects validated data,
    // this should return the expected array, e.g., ['installed' => '2.0.0', 'version' => '2.0.1'].
    // Given that properties are set directly on $request, it implies the controller
    // might access $request->installed after validation, so `andReturn([])` might be sufficient
    // to just bypass the validation logic and let the controller proceed.
    $request->shouldReceive('validate')->andReturn([]);

    // Set properties for the request that Updater::finishUpdate would use
    $request->installed = '2.0.0';
    $request->version = '2.0.1';

    $expectedUpdaterResponse = [
        'status' => 'success',
        'message' => 'Update completed successfully.',
        'new_version' => '2.0.1'
    ];

    // Mock the static Updater::finishUpdate method
    // Use Mockery's alias mock syntax.
    m::mock('alias:' . Updater::class)
        ->shouldReceive('finishUpdate')
        ->once()
        ->with('2.0.0', '2.0.1')
        ->andReturn($expectedUpdaterResponse);

    $controller = new FinishUpdateController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe($expectedUpdaterResponse);
});

test('it passes through an error response from the updater', function () {
    $user = m::mock();
    $user->shouldReceive('isOwner')->andReturn(true);

    $request = m::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);

    // Mock validate to pass
    $request->shouldReceive('validate')->andReturn([]);

    // Set properties for the request
    $request->installed = '2.0.0';
    $request->version = '2.0.1';

    $errorUpdaterResponse = [
        'status' => 'error',
        'message' => 'An error occurred during update processing.',
        'details' => 'Could not write to disk.'
    ];

    // Mock the static Updater::finishUpdate method to return an error
    // Use Mockery's alias mock syntax.
    m::mock('alias:' . Updater::class)
        ->shouldReceive('finishUpdate')
        ->once()
        ->with('2.0.0', '2.0.1')
        ->andReturn($errorUpdaterResponse);

    $controller = new FinishUpdateController();
    $response = $controller->__invoke($request);

    // The controller itself successfully returned a JSON response,
    // even if that JSON indicates an error from the updater.
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe($errorUpdaterResponse);
});