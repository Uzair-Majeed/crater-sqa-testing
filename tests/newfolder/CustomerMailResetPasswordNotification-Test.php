<?php

use Crater\Notifications\CustomerMailResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

// Mock URL generator for url() helper and config repository for config() helper
beforeEach(function () {
    // Mock the URL generator used by the global url() helper
    $urlGeneratorMock = Mockery::mock(\Illuminate\Contracts\Routing\UrlGenerator::class);
    $urlGeneratorMock->shouldReceive('to')->andReturnUsing(function ($path, $parameters = [], $secure = null) {
        return 'http://localhost/' . ltrim($path, '/');
    });

    app()->instance('url', $urlGeneratorMock);

    // GLOBAL config mock: match both config('auth.passwords.users.expire') and config('auth.passwords.users.expire', ...) signatures
    Config::shouldReceive('get')
        ->withArgs(function ($key, $default = null) {
            return $key === 'auth.passwords.users.expire';
        })
        ->andReturnUsing(function ($key, $default = null) {
            // Use default (if set), fallback to 60 otherwise
            return $default ?? 60;
        })
        ->byDefault();
});

test('constructor correctly sets the token via the parent class', function () {
    $token = 'test-token-123';
    $notification = new CustomerMailResetPasswordNotification($token);

    $reflection = new ReflectionClass($notification);
    $parentReflection = $reflection->getParentClass();
    $tokenProperty = $parentReflection->getProperty('token');
    $tokenProperty->setAccessible(true);

    expect($tokenProperty->getValue($notification))->toBe($token);
});

test('via method returns the mail channel as expected', function () {
    $notification = new CustomerMailResetPasswordNotification('some-token');
    $notifiable = Mockery::mock();

    expect($notification->via($notifiable))->toEqual(['mail']);
});

test('toMail method constructs a MailMessage with correct details for a standard case', function () {
    $token = 'secure-reset-token';
    $companySlug = 'my-awesome-company';
    $expireMinutes = 45; // Test with a specific expiry time

    $notifiable = (object) [
        'company' => (object) ['slug' => $companySlug],
    ];

    // Specific config mock for this test (match both with/without default value signature)
    Config::shouldReceive('get')
        ->withArgs(function ($key, $default = null) {
            return $key === 'auth.passwords.users.expire';
        })
        ->andReturn($expireMinutes)
        ->once();

    $notification = new CustomerMailResetPasswordNotification($token);
    $mailMessage = $notification->toMail($notifiable);

    $expectedLink = "http://localhost/{$companySlug}/customer/reset/password/{$token}";

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
        'company' => (object) ['slug' => null],
    ];
    $notifiableWithEmptySlug = (object) [
        'company' => (object) ['slug' => ''],
    ];

    // Specific config mocks for both method signatures (with and without default)
    Config::shouldReceive('get')
        ->withArgs(function ($key, $default = null) {
            return $key === 'auth.passwords.users.expire';
        })
        ->andReturn(60)
        ->twice();

    $notificationNullSlug = new CustomerMailResetPasswordNotification($token);
    $mailMessageNullSlug = $notificationNullSlug->toMail($notifiableWithNullSlug);
    $expectedLinkNullSlug = "http://localhost//customer/reset/password/{$token}";
    expect($mailMessageNullSlug->actionUrl)->toBe($expectedLinkNullSlug);

    $notificationEmptySlug = new CustomerMailResetPasswordNotification($token);
    $mailMessageEmptySlug = $notificationEmptySlug->toMail($notifiableWithEmptySlug);
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
        ->withArgs(function ($key, $default = null) {
            return $key === 'auth.passwords.users.expire';
        })
        ->andReturn(0)
        ->once();
    $notificationZeroExpire = new CustomerMailResetPasswordNotification($token);
    $mailMessageZeroExpire = $notificationZeroExpire->toMail($notifiable);
    expect($mailMessageZeroExpire->outroLines[0])->toBe("This password reset link will expire in 0 minutes");

    // Test with 1 minute expiry
    Config::shouldReceive('get')
        ->withArgs(function ($key, $default = null) {
            return $key === 'auth.passwords.users.expire';
        })
        ->andReturn(1)
        ->once();
    $notificationOneExpire = new CustomerMailResetPasswordNotification($token);
    $mailMessageOneExpire = $notificationOneExpire->toMail($notifiable);
    expect($mailMessageOneExpire->outroLines[0])->toBe("This password reset link will expire in 1 minutes");

    // Test with a very large expiry value
    Config::shouldReceive('get')
        ->withArgs(function ($key, $default = null) {
            return $key === 'auth.passwords.users.expire';
        })
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

    Config::shouldReceive('get')
        ->withArgs(function ($key, $default = null) {
            return $key === 'auth.passwords.users.expire';
        })
        ->andReturn(60)
        ->twice();

    $notification1 = new CustomerMailResetPasswordNotification($token1);
    $mailMessage1 = $notification1->toMail($notifiable);
    $expectedLink1 = "http://localhost/{$companySlug}/customer/reset/password/{$token1}";
    expect($mailMessage1->actionUrl)->toBe($expectedLink1);

    $notification2 = new CustomerMailResetPasswordNotification($token2);
    $mailMessage2 = $notification2->toMail($notifiable);
    $expectedLink2 = "http://localhost/{$companySlug}/customer/reset/password/{$token2}";
    expect($mailMessage2->actionUrl)->toBe($expectedLink2);
});

test('toArray method consistently returns an empty array', function () {
    $notification = new CustomerMailResetPasswordNotification('any-token');
    $notifiable = Mockery::mock();

    expect($notification->toArray($notifiable))->toEqual([]);
});

afterEach(function () {
    Mockery::close();
});