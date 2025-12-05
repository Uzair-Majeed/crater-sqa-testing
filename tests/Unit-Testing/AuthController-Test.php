<?php

namespace Tests\Unit;

use Crater\Http\Controllers\V1\Admin\Mobile\AuthController;
use Crater\Http\Requests\LoginRequest;
use Crater\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mockery as m;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->beforeEach(function () {
    $this->controller = new AuthController();
})->afterEach(function () {
    m::close();
});

// ------------------------------
// LOGIN TESTS
// ------------------------------

test('login with incorrect credentials throws validation exception when user not found', function () {
    $request = new LoginRequest([
        'username' => 'nonexistent@example.com',
        'password' => 'password',
    ]);

    $this->controller->login($request);
})->throws(ValidationException::class, 'The given data was invalid.');


// ------------------------------
// LOGOUT TEST
// ------------------------------

test('logout successfully deletes current access token and returns success', function () {
    $accessTokenMock = m::mock();
    $accessTokenMock->shouldReceive('delete')->once();

    $userMock = m::mock(User::class)->makePartial();
    $userMock->shouldReceive('currentAccessToken')->andReturn($accessTokenMock)->once();

    $request = m::mock(Request::class);
    $request->shouldReceive('user')->andReturn($userMock)->once();

    $response = $this->controller->logout($request);

    expect($response->getStatusCode())->toBe(200);

    $responseData = json_decode($response->getContent(), true);
    expect($responseData)->toEqual(['success' => true]);
});

// ------------------------------
// AUTH CHECK TESTS
// ------------------------------

test('check returns true when user is authenticated', function () {
    Auth::shouldReceive('check')->andReturn(true)->once();

    $result = $this->controller->check();

    expect($result)->toBeTrue();
});

test('check returns false when user is not authenticated', function () {
    Auth::shouldReceive('check')->andReturn(false)->once();

    $result = $this->controller->check();

    expect($result)->toBeFalse();
});
