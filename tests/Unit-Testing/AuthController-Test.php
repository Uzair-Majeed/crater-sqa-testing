<?php

use Crater\Http\Controllers\V1\Admin\Mobile\AuthController;
use Crater\Http\Requests\LoginRequest;
use Crater\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// Ensure Mockery is available and cleaned up after each test.
// This is typically handled by `uses(RefreshDatabase::class, ...)` or a `TestCase` setup.
// We'll use fully qualified Mockery calls to avoid requiring a 'uses(\Mockery::class);' statement.

beforeEach(function () {
    $this->controller = new AuthController();
});

// Test for login method
test('login with incorrect credentials throws validation exception when user not found', function () {
    \Mockery::mock('alias:Crater\Models\User')
        ->shouldReceive('where->first')
        ->andReturn(null) // User not found
        ->once();

    $request = \Mockery::mock(LoginRequest::class);
    $request->username = 'nonexistent@example.com';
    $request->password = 'password';

    $this->controller->login($request);
})->throws(ValidationException::class, 'The given data was invalid.', function (ValidationException $e) {
    expect($e->errors())->toEqual(['email' => ['The provided credentials are incorrect.']]);
});

test('login with incorrect credentials throws validation exception when password incorrect', function () {
    $userMock = \Mockery::mock(User::class);
    $userMock->password = \Illuminate\Support\Facades\Hash::make('correct_password'); // Mock a hashed password

    \Mockery::mock('alias:Crater\Models\User')
        ->shouldReceive('where->first')
        ->andReturn($userMock) // User found
        ->once();

    \Mockery::mock('alias:Illuminate\Support\Facades\Hash')
        ->shouldReceive('check')
        ->with('incorrect_password', $userMock->password)
        ->andReturn(false) // Password check fails
        ->once();

    $request = \Mockery::mock(LoginRequest::class);
    $request->username = 'test@example.com';
    $request->password = 'incorrect_password';

    $this->controller->login($request);
})->throws(ValidationException::class, 'The given data was invalid.', function (ValidationException $e) {
    expect($e->errors())->toEqual(['email' => ['The provided credentials are incorrect.']]);
});

test('login with correct credentials returns a bearer token', function () {
    $plainTextToken = 'mock_plain_text_token';
    $tokenMock = (object)['plainTextToken' => $plainTextToken];

    $userMock = \Mockery::mock(User::class);
    $userMock->password = \Illuminate\Support\Facades\Hash::make('correct_password'); // Mock a hashed password
    $userMock->shouldReceive('createToken')
        ->with('test_device')
        ->andReturn($tokenMock)
        ->once();

    \Mockery::mock('alias:Crater\Models\User')
        ->shouldReceive('where->first')
        ->andReturn($userMock) // User found
        ->once();

    \Mockery::mock('alias:Illuminate\Support\Facades\Hash')
        ->shouldReceive('check')
        ->with('correct_password', $userMock->password)
        ->andReturn(true) // Password check passes
        ->once();

    $request = \Mockery::mock(LoginRequest::class);
    $request->username = 'test@example.com';
    $request->password = 'correct_password';
    $request->device_name = 'test_device';

    $response = $this->controller->login($request);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBeJson();
    $responseData = json_decode($response->getContent(), true);
    expect($responseData)->toEqual([
        'type' => 'Bearer',
        'token' => $plainTextToken,
    ]);
});

// Test for logout method
test('logout successfully deletes current access token and returns success', function () {
    $accessTokenMock = \Mockery::mock();
    $accessTokenMock->shouldReceive('delete')->once();

    $userMock = \Mockery::mock(User::class);
    $userMock->shouldReceive('currentAccessToken')->andReturn($accessTokenMock)->once();

    $request = \Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($userMock)->once();

    $response = $this->controller->logout($request);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBeJson();
    $responseData = json_decode($response->getContent(), true);
    expect($responseData)->toEqual(['success' => true]);
});

// Test for check method
test('check returns true when user is authenticated', function () {
    \Mockery::mock('alias:Illuminate\Support\Facades\Auth')
        ->shouldReceive('check')
        ->andReturn(true)
        ->once();

    $result = $this->controller->check();

    expect($result)->toBeTrue();
});

test('check returns false when user is not authenticated', function () {
    \Mockery::mock('alias:Illuminate\Support\Facades\Auth')
        ->shouldReceive('check')
        ->andReturn(false)
        ->once();

    $result = $this->controller->check();

    expect($result)->toBeFalse();
});
