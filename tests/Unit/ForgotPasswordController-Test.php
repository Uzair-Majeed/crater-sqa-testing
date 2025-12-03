
<?php

use Crater\Http\Controllers\V1\Customer\Auth\ForgotPasswordController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\Exceptions\HttpResponseException; // Added for completeness, though not directly used due to observed behavior

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
    // Mockery::close() is handled by afterEach hook.
});

test('sendResetLinkResponse returns a successful JSON response', function () {
    // Arrange
    // The debug output indicates BadMethodCallException, suggesting sendResetLinkResponse
    // is not a public method on ForgotPasswordController (likely protected, coming from a trait or base class).
    // We create an anonymous class to expose it publicly for testing purposes.
    $controller = new class extends ForgotPasswordController {
        public function publicSendResetLinkResponse($request, $response) {
            return parent::sendResetLinkResponse($request, $response);
        }
    };

    // Simulate a JSON request by adding the 'Accept: application/json' header.
    // This ensures that Laravel's internal logic (e.g., $request->wantsJson()) correctly
    // triggers the JSON response path, aligning with the expected JsonResponse assertion.
    $request = Request::create('/test-url', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $responseString = 'passwords.sent'; // This would typically be a language key or a token

    // Act
    $response = $controller->publicSendResetLinkResponse($request, $responseString);

    // Assert
    // The test expects a JsonResponse with a specific message and a 'data' key.
    // This indicates that Crater's ForgotPasswordController likely overrides the default
    // implementation from Laravel's SendsPasswordResetEmails trait to include the 'data' key.
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toHaveKey('message', 'Password reset email sent.')
        ->and($response->getData(true))->toHaveKey('data', $responseString);
});

test('sendResetLinkFailedResponse returns a failed response with 403 status', function () {
    // Arrange
    // Similar to the success response, sendResetLinkFailedResponse is likely protected.
    // We use an anonymous class to make it callable for the test.
    $controller = new class extends ForgotPasswordController {
        public function publicSendResetLinkFailedResponse($request, $response) {
            return parent::sendResetLinkFailedResponse($request, $response);
        }
    };

    // Simulate a JSON request. Note that Laravel's default SendsPasswordResetEmails trait
    // would throw an HttpResponseException containing a JsonResponse for failed JSON requests.
    // However, this test expects a direct Response object with specific content,
    // which implies Crater's controller overrides this behavior significantly.
    $request = Request::create('/test-url', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $responseString = 'passwords.user'; // The original message from the trait for user not found, etc.

    // Act
    $response = $controller->publicSendResetLinkFailedResponse($request, $responseString);

    // Assert
    // The test expects a standard Illuminate\Http\Response (not JsonResponse)
    // with a 403 status and specific content, which is a customization in Crater's controller.
    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toBe('Email could not be sent to this email address.');
});


afterEach(function () {
    Mockery::close();
});