<?php

use Crater\Http\Controllers\V1\Customer\Auth\ForgotPasswordController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Passwords\PasswordBroker;
uses(\Mockery::class);

test('broker method returns the customer password broker', function () {
    // Arrange
    $mockBroker = Mockery::mock(PasswordBroker::class);
    Password::shouldReceive('broker')
        ->once()
        ->with('customers')
        ->andReturn($mockBroker);

    $controller = new ForgotPasswordController();

    // Act
    $result = $controller->broker();

    // Assert
    expect($result)->toBe($mockBroker);
    Mockery::close();
});

test('sendResetLinkResponse returns a successful JSON response', function () {
    // Arrange
    $controller = new ForgotPasswordController();
    $request = Request::create('/test-url', 'POST');
    $responseString = 'passwords.sent'; // This would typically be a language key or a token

    // Act
    $response = $controller->sendResetLinkResponse($request, $responseString);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toHaveKey('message', 'Password reset email sent.')
        ->and($response->getData(true))->toHaveKey('data', $responseString);
});

test('sendResetLinkFailedResponse returns a failed response with 403 status', function () {
    // Arrange
    $controller = new ForgotPasswordController();
    $request = Request::create('/test-url', 'POST');
    // The $response parameter is passed from the trait's sendResetLinkFailedResponse,
    // which is typically a language key like 'passwords.user' or 'passwords.throttle'.
    // However, our custom method ignores this parameter and returns a fixed message.
    $responseString = 'passwords.user';

    // Act
    $response = $controller->sendResetLinkFailedResponse($request, $responseString);

    // Assert
    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toBe('Email could not be sent to this email address.');
});
