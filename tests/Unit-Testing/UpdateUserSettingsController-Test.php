<?php

use Mockery as m;
use Crater\Http\Controllers\V1\Admin\Settings\UpdateUserSettingsController;
use Crater\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;

test('it successfully updates user settings with valid data', function () {
    // 1. Mock the User model
    // We'll mock a generic object and explicitly add the 'setSettings' method
    // as it's not part of a standard interface like Authenticatable.
    $mockUser = m::mock('User');
    $mockUser->shouldReceive('setSettings')
        ->once()
        ->with(['theme' => 'dark', 'notifications' => true])
        ->andReturnSelf(); // Allow method chaining if applicable, or just return nothing.

    // 2. Mock the UpdateSettingsRequest
    $mockRequest = m::mock(UpdateSettingsRequest::class);

    // Set expectations for the request's `user()` method
    $mockRequest->shouldReceive('user')
        ->once()
        ->andReturn($mockUser);

    // Set the 'settings' property on the mock request directly, as used by the controller
    // This simulates the request having a 'settings' property, likely from validated data.
    $mockRequest->settings = ['theme' => 'dark', 'notifications' => true];

    // 3. Create an instance of the controller
    $controller = new UpdateUserSettingsController();

    // 4. Invoke the controller's __invoke method
    $response = $controller($mockRequest); // __invoke allows calling the object like a function

    // 5. Assertions
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['success' => true]);
});

test('it successfully updates user settings with an empty settings array', function () {
    // 1. Mock the User model
    $mockUser = m::mock('User');
    $mockUser->shouldReceive('setSettings')
        ->once()
        ->with([]) // Expect an empty array to be passed
        ->andReturnSelf();

    // 2. Mock the UpdateSettingsRequest
    $mockRequest = m::mock(UpdateSettingsRequest::class);

    // Set expectations for the request's `user()` method
    $mockRequest->shouldReceive('user')
        ->once()
        ->andReturn($mockUser);

    // Set the 'settings' property on the mock request to an empty array
    $mockRequest->settings = [];

    // 3. Create an instance of the controller
    $controller = new UpdateUserSettingsController();

    // 4. Invoke the controller
    $response = $controller($mockRequest);

    // 5. Assertions
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toEqual(['success' => true]);
});

test('it throws a TypeError if request user is null', function () {
    // This is an edge case that would typically be prevented by authentication middleware
    // ensuring `request()->user()` is never null at this point. However, for
    // white-box unit testing, we explicitly test this internal path.

    // 1. Mock the UpdateSettingsRequest
    $mockRequest = m::mock(UpdateSettingsRequest::class);

    // Set expectations for the request's `user()` method to return null
    $mockRequest->shouldReceive('user')
        ->once()
        ->andReturn(null);

    // Set the 'settings' property on the mock, though it won't be used
    // before the TypeError occurs.
    $mockRequest->settings = ['theme' => 'dark'];

    // 2. Create an instance of the controller
    $controller = new UpdateUserSettingsController();

    // 3. Expect a TypeError when invoking the controller
    // PHP 8+ message: Attempt to call a method named setSettings on null
    // PHP 7 message: Call to a member function setSettings() on null
    $this->expectException(TypeError::class);
    $this->expectExceptionMessageMatches('/^Attempt to call a method named setSettings on null|Call to a member function setSettings\(\) on null$/');

    $controller($mockRequest);
});
