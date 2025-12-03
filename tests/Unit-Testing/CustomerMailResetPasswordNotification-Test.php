<?php

use Crater\Notifications\CustomerMailResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

// Mock URL generator for url() helper and config repository for config() helper
beforeEach(function () {
    // Mock the URL generator used by the global url() helper
    $urlGeneratorMock = Mockery::mock(\Illuminate\Contracts\Routing\UrlGenerator::class);
    $urlGeneratorMock->shouldReceive('to')->andReturnUsing(function ($path, $parameters = [], $secure = null) {
        // Simulate Laravel's url() helper behavior for testing
        // This simple mock assumes a base URL and concatenates the path.
        // `ltrim` handles cases where path might start with a slash or not.
        return 'http://localhost/' . ltrim($path, '/');
    });

    // Replace the URL generator in the container so the global `url()` helper uses our mock.
    app()->instance('url', $urlGeneratorMock);

    // Set a default mock for `config('auth.passwords.users.expire')`.
    // `byDefault()` ensures this expectation is loosely matched and can be overridden by specific tests.
    Config::shouldReceive('get')
        ->with('auth.passwords.users.expire')
        ->andReturn(60) // Default expiry for testing (60 minutes)
        ->byDefault();
});


test('constructor correctly sets the token via the parent class', function () {
    $token = 'test-token-123';
    $notification = new CustomerMailResetPasswordNotification($token);

    // Use reflection to access the protected `$token` property from the parent class
    // (`Illuminate\Auth\Notifications\ResetPassword`) for white-box verification.
    $reflection = new ReflectionClass($notification);
    $parentReflection = $reflection->getParentClass();
    $tokenProperty = $parentReflection->getProperty('token');
    $tokenProperty->setAccessible(true); // Make the protected property accessible for inspection

    expect($tokenProperty->getValue($notification))->toBe($token);
});

test('via method returns the mail channel as expected', function () {
    $notification = new CustomerMailResetPasswordNotification('some-token');
    // The `$notifiable` parameter is not used within the `via()` method, so a simple mock is sufficient.
    $notifiable = Mockery::mock();

    expect($notification->via($notifiable))->toEqual(['mail']);
});

test('toMail method constructs a MailMessage with correct details for a standard case', function () {
    $token = 'secure-reset-token';
    $companySlug = 'my-awesome-company';
    $expireMinutes = 45; // Test with a specific expiry time

    // Create a mock notifiable object with the expected structure.
    $notifiable = (object) [
        'company' => (object) ['slug' => $companySlug],
    ];

    // Override the default config mock for this specific test to use a different expiry.
    Config::shouldReceive('get')
        ->with('auth.passwords.users.expire')
        ->andReturn($expireMinutes)
        ->once();

    $notification = new CustomerMailResetPasswordNotification($token);
    $mailMessage = $notification->toMail($notifiable);

    // Construct the expected reset link based on our mocked `url()` helper.
    $expectedLink = "http://localhost/{$companySlug}/customer/reset/password/{$token}";

    // Assert the properties of the returned MailMessage instance.
    expect($mailMessage)->toBeInstanceOf(MailMessage::class)
        ->and($mailMessage->subject)->toBe('Reset Password Notification')
        ->and($mailMessage->introLines)->toEqual([
            "Hello! You are receiving this email because we received a password reset request for your account.",
        ])
        ->and($mailMessage->actionText)->toBe('Reset Password')
        ->and($mailMessage->actionUrl)->toBe($expectedLink)
        ->and($mailMessage->outroLines)->toEqual([
            "This password reset link will expire in {$expireMinutes} minutes",
            "If you did not request a password reset, no further action is required.",
        ]);
});

test('toMail method generates correct URL when company slug is null or an empty string', function () {
    $token = 'token-with-null-slug';
    $notifiableWithNullSlug = (object) [
        'company' => (object) ['slug' => null], // Simulate a null slug
    ];
    $notifiableWithEmptySlug = (object) [
        'company' => (object) ['slug' => ''], // Simulate an empty string slug
    ];

    // Expect the config to be retrieved twice for these two scenarios.
    Config::shouldReceive('get')
        ->with('auth.passwords.users.expire')
        ->andReturn(60)
        ->times(2);

    // Test with null slug
    $notificationNullSlug = new CustomerMailResetPasswordNotification($token);
    $mailMessageNullSlug = $notificationNullSlug->toMail($notifiableWithNullSlug);
    // When the slug is null, it concatenates as an empty string, leading to a double slash in the URL path.
    $expectedLinkNullSlug = "http://localhost//customer/reset/password/{$token}";
    expect($mailMessageNullSlug->actionUrl)->toBe($expectedLinkNullSlug);

    // Test with an empty string slug
    $notificationEmptySlug = new CustomerMailResetPasswordNotification($token);
    $mailMessageEmptySlug = $notificationEmptySlug->toMail($notifiableWithEmptySlug);
    // Similar to null, an empty string slug also leads to a double slash.
    $expectedLinkEmptySlug = "http://localhost//customer/reset/password/{$token}";
    expect($mailMessageEmptySlug->actionUrl)->toBe($expectedLinkEmptySlug);
});

test('toMail method correctly incorporates different expiry configurations into the message', function () {
    $token = 'config-expiry-token';
    $companySlug = 'config-expiry-company';
    $notifiable = (object) [
        'company' => (object) ['slug' => $companySlug],
    ];

    // Test with 0 minutes expiry
    Config::shouldReceive('get')
        ->with('auth.passwords.users.expire')
        ->andReturn(0)
        ->once();
    $notificationZeroExpire = new CustomerMailResetPasswordNotification($token);
    $mailMessageZeroExpire = $notificationZeroExpire->toMail($notifiable);
    expect($mailMessageZeroExpire->outroLines[0])->toBe("This password reset link will expire in 0 minutes");

    // Test with 1 minute expiry (edge case for singular/plural, as the SUT uses "minutes" universally)
    Config::shouldReceive('get')
        ->with('auth.passwords.users.expire')
        ->andReturn(1)
        ->once();
    $notificationOneExpire = new CustomerMailResetPasswordNotification($token);
    $mailMessageOneExpire = $notificationOneExpire->toMail($notifiable);
    // Confirming the string as literally written in the SUT, even if grammatically "1 minute" would be better.
    expect($mailMessageOneExpire->outroLines[0])->toBe("This password reset link will expire in 1 minutes");

    // Test with a very large expiry value
    Config::shouldReceive('get')
        ->with('auth.passwords.users.expire')
        ->andReturn(999)
        ->once();
    $notificationLargeExpire = new CustomerMailResetPasswordNotification($token);
    $mailMessageLargeExpire = $notificationLargeExpire->toMail($notifiable);
    expect($mailMessageLargeExpire->outroLines[0])->toBe("This password reset link will expire in 999 minutes");
});

test('toMail method uses the correct token provided during construction in the reset link', function () {
    $token1 = 'unique-token-alpha';
    $token2 = 'distinct-token-beta';
    $companySlug = 'token-test-company';

    $notifiable = (object) [
        'company' => (object) ['slug' => $companySlug],
    ];

    // Expect the config to be retrieved twice for these two scenarios.
    Config::shouldReceive('get')
        ->with('auth.passwords.users.expire')
        ->andReturn(60)
        ->times(2);

    // Test with the first token
    $notification1 = new CustomerMailResetPasswordNotification($token1);
    $mailMessage1 = $notification1->toMail($notifiable);
    $expectedLink1 = "http://localhost/{$companySlug}/customer/reset/password/{$token1}";
    expect($mailMessage1->actionUrl)->toBe($expectedLink1);

    // Test with the second token to ensure independence and correct token usage
    $notification2 = new CustomerMailResetPasswordNotification($token2);
    $mailMessage2 = $notification2->toMail($notifiable);
    $expectedLink2 = "http://localhost/{$companySlug}/customer/reset/password/{$token2}";
    expect($mailMessage2->actionUrl)->toBe($expectedLink2);
});

test('toArray method consistently returns an empty array', function () {
    $notification = new CustomerMailResetPasswordNotification('any-token');
    // The `$notifiable` parameter is not used within the `toArray()` method.
    $notifiable = Mockery::mock();

    expect($notification->toArray($notifiable))->toEqual([]);
});

 


afterEach(function () {
    Mockery::close();
});
