<?php

use Crater\Http\Controllers\V1\Customer\Auth\ResetPasswordController;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Str; // Use the Facade explicitly

// Use an anonymous class to expose protected methods for direct testing
// This is a common pattern for white-box testing protected methods
class TestResetPasswordController extends ResetPasswordController
{
    public function publicBroker()
    {
        return $this->broker();
    }

    public function publicSendResetResponse(Request $request, $response)
    {
        return $this->sendResetResponse($request, $response);
    }

    public function publicResetPassword(CanResetPassword $user, $password)
    {
        return $this->resetPassword($user, $password);
    }

    public function publicSendResetFailedResponse(Request $request, $response)
    {
        return $this->sendResetFailedResponse($request, $response);
    }
}

beforeEach(function () {
    // Clear mocks before each test
    Mockery::close();
});

test('broker method returns the customer password broker', function () {
    $mockPasswordBroker = Mockery::mock(\Illuminate\Auth\Passwords\PasswordBroker::class);

    // Mock the Password facade to return our mock broker when `broker('customers')` is called
    Password::shouldReceive('broker')
        ->once()
        ->with('customers')
        ->andReturn($mockPasswordBroker);

    $controller = new TestResetPasswordController();
    $result = $controller->publicBroker();

    expect($result)->toBe($mockPasswordBroker);
});

test('sendResetResponse calls response()->json with correct message and returns its result', function () {
    $request = Mockery::mock(Request::class);
    $responseString = 'irrelevant_response_string'; // The method uses a hardcoded message

    $mockJsonResponse = Mockery::mock(JsonResponse::class);

    // Mock the ResponseFactory that the global `response()` helper resolves from the container
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['message' => 'Password reset successfully.'])
        ->andReturn($mockJsonResponse);

    app()->instance(ResponseFactory::class, $mockResponseFactory);

    $controller = new TestResetPasswordController();
    $result = $controller->publicSendResetResponse($request, $responseString);

    expect($result)->toBe($mockJsonResponse);
});

test('sendResetFailedResponse calls response() with correct message and status, and returns its result', function () {
    $request = Mockery::mock(Request::class);
    $responseString = 'irrelevant_response_string'; // The method uses a hardcoded message

    $mockResponse = Mockery::mock(Response::class);

    // Mock the ResponseFactory that the global `response()` helper resolves from the container
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    // FIX: The `response('...', 403)` helper internally calls `make($content, $status, $headers)`
    // where $headers is an empty array by default if not provided.
    $mockResponseFactory->shouldReceive('make') // For `response('...', 403)`
        ->once()
        ->with('Failed, Invalid Token.', 403, []) // Added [] for the headers argument
        ->andReturn($mockResponse);

    app()->instance(ResponseFactory::class, $mockResponseFactory);

    $controller = new TestResetPasswordController();
    $result = $controller->publicSendResetFailedResponse($request, $responseString);

    expect($result)->toBe($mockResponse);
});

afterEach(function () {
    Mockery::close();
});