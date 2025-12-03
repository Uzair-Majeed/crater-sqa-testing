<?php

use Crater\Http\Controllers\V1\Admin\Mobile\AuthController;
use Crater\Http\Requests\LoginRequest;
use Crater\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

// Use Pest helpers for refreshing things if required.
// Make sure to properly isolate mocks.

// Reset Facades for each test to allow alias mocking.
beforeEach(function () {
    $this->controller = new AuthController();
    // Unset loaded facade aliases to allow Mockery alias mock
    // Necessary so alias mocks ('alias:...') can be re-created on each test
    $unsetFacade = function ($facade) {
        if (isset($GLOBALS['__phpunit_has_registered_' . $facade])) {
            unset($GLOBALS['__phpunit_has_registered_' . $facade]);
        }
        if (class_exists($facade, false)) {
            class_alias('stdClass', $facade);
        }
    };
    $unsetFacade('Crater\Models\User');
    $unsetFacade('Illuminate\Support\Facades\Hash');
    $unsetFacade('Illuminate\Support\Facades\Auth');
});

afterEach(function () {
    \Mockery::close();
});

// Test for login method
test('login with incorrect credentials throws validation exception when user not found', function () {
    // Fix: Mock static where() and first() calls using instance partials.
    $userPartial = \Mockery::mock('overload:Crater\Models\User');
    $userPartial->shouldReceive('where')
        ->with('email', 'nonexistent@example.com')
        ->andReturnSelf()
        ->once();
    $userPartial->shouldReceive('first')
        ->andReturn(null)
        ->once();

    // Use real request to avoid hidden magic or Eloquent mutator errors
    $request = new LoginRequest([
        'username' => 'nonexistent@example.com',
        'password' => 'password',
    ]);

    $this->controller->login($request);
})->throws(ValidationException::class, 'The given data was invalid.', function (ValidationException $e) {
    expect($e->errors())->toEqual(['email' => ['The provided credentials are incorrect.']]);
});

test('login with incorrect credentials throws validation exception when password incorrect', function () {
    // Hash needs to be mocked properly.
    $userPartial = \Mockery::mock('overload:Crater\Models\User');
    $userPartial->shouldReceive('where')
        ->with('email', 'test@example.com')
        ->andReturnSelf()
        ->once();
    $userPartial->shouldReceive('first')
        ->andReturnUsing(function () {
            $user = new User();
            $user->password = Hash::make('correct_password');
            $user->setAttribute('password', $user->password); // Set on Eloquent model correctly
            return $user;
        })
        ->once();

    // Mock the Hash::check method, but don't alias - use partial mocking
    Hash::shouldReceive('check')
        ->with('incorrect_password', \Mockery::type('string'))
        ->andReturn(false)
        ->once();

    $request = new LoginRequest([
        'username' => 'test@example.com',
        'password' => 'incorrect_password',
    ]);

    $this->controller->login($request);
})->throws(ValidationException::class, 'The given data was invalid.', function (ValidationException $e) {
    expect($e->errors())->toEqual(['email' => ['The provided credentials are incorrect.']]);
});

test('login with correct credentials returns a bearer token', function () {
    $plainTextToken = 'mock_plain_text_token';
    $tokenMock = (object)['plainTextToken' => $plainTextToken];

    // Mock User model's static calls using overload.
    $userPartial = \Mockery::mock('overload:Crater\Models\User');
    $userPartial->shouldReceive('where')
        ->with('email', 'test@example.com')
        ->andReturnSelf()
        ->once();
    $userPartial->shouldReceive('first')
        ->andReturnUsing(function () use ($tokenMock) {
            $user = \Mockery::mock(User::class)->makePartial();
            $user->password = Hash::make('correct_password');
            $user->setAttribute('password', $user->password);
            $user->shouldReceive('createToken')
                ->with('test_device')
                ->andReturn($tokenMock)
                ->once();
            return $user;
        })
        ->once();

    // Hash check for correct password
    Hash::shouldReceive('check')
        ->with('correct_password', \Mockery::type('string'))
        ->andReturn(true)
        ->once();

    $request = new LoginRequest([
        'username' => 'test@example.com',
        'password' => 'correct_password',
        'device_name' => 'test_device',
    ]);

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
    // Overload facade so the Auth::check global call goes to the mock
    $authMock = \Mockery::mock('overload:Illuminate\Support\Facades\Auth');
    $authMock->shouldReceive('check')
        ->andReturn(true)
        ->once();

    $result = $this->controller->check();

    expect($result)->toBeTrue();
});

test('check returns false when user is not authenticated', function () {
    $authMock = \Mockery::mock('overload:Illuminate\Support\Facades\Auth');
    $authMock->shouldReceive('check')
        ->andReturn(false)
        ->once();

    $result = $this->controller->check();

    expect($result)->toBeFalse();
});