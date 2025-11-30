<?php

use Crater\Notifications\MailResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
uses(\Mockery::class);

test('constructor correctly sets the token via parent', function () {
    $token = 'some_random_token_for_constructor';
    $notification = new MailResetPasswordNotification($token);

    // Use reflection to access the protected $token property from the parent class (Illuminate\Auth\Notifications\ResetPassword)
    $reflectionProperty = new ReflectionProperty(get_parent_class($notification), 'token');
    $reflectionProperty->setAccessible(true);
    $storedToken = $reflectionProperty->getValue($notification);

    expect($storedToken)->toBe($token);
});

test('via method returns the correct delivery channels', function () {
    $notification = new MailResetPasswordNotification('dummy_token_via');
    $notifiable = (object)['email' => 'via@example.com']; // Dummy notifiable

    expect($notification->via($notifiable))->toEqual(['mail']);
});

test('toMail method generates correct MailMessage content with default expiration', function () {
    $token = 'test_token_default_expire';
    $notification = new MailResetPasswordNotification($token);
    $notifiable = (object)['email' => 'default@example.com'];

    // Mock config helper
    Config::shouldReceive('get')
        ->with('auth.passwords.users.expire')
        ->andReturn(60) // Default expiration
        ->once();

    // Mock url helper, which often maps to URL::to() for simple paths
    URL::shouldReceive('to')
        ->with("/reset-password/{$token}")
        ->andReturn("http://localhost/reset-password/{$token}")
        ->once();

    /** @var MailMessage $mailMessage */
    $mailMessage = $notification->toMail($notifiable);

    expect($mailMessage)->toBeInstanceOf(MailMessage::class);

    // Use reflection to access protected properties of MailMessage to verify content
    $getProtectedProperty = function ($object, $property) {
        $reflectionProperty = new ReflectionProperty(MailMessage::class, $property);
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->getValue($object);
    };

    expect($getProtectedProperty($mailMessage, 'subject'))->toBe('Reset Password Notification');
    expect($getProtectedProperty($mailMessage, 'introLines'))->toEqual([
        "Hello! You are receiving this email because we received a password reset request for your account.",
    ]);
    expect($getProtectedProperty($mailMessage, 'actionText'))->toBe('Reset Password');
    expect($getProtectedProperty($mailMessage, 'actionUrl'))->toBe("http://localhost/reset-password/{$token}");
    expect($getProtectedProperty($mailMessage, 'outroLines'))->toEqual([
        "This password reset link will expire in 60 minutes",
        "If you did not request a password reset, no further action is required.",
    ]);
});

test('toMail method generates correct MailMessage content with custom expiration', function () {
    $token = 'test_token_custom_expire';
    $notification = new MailResetPasswordNotification($token);
    $notifiable = (object)['email' => 'custom@example.com'];

    // Mock config helper
    Config::shouldReceive('get')
        ->with('auth.passwords.users.expire')
        ->andReturn(30) // Custom expiration
        ->once();

    // Mock url helper
    URL::shouldReceive('to')
        ->with("/reset-password/{$token}")
        ->andReturn("http://localhost/reset-password/{$token}")
        ->once();

    /** @var MailMessage $mailMessage */
    $mailMessage = $notification->toMail($notifiable);

    expect($mailMessage)->toBeInstanceOf(MailMessage::class);

    $getProtectedProperty = function ($object, $property) {
        $reflectionProperty = new ReflectionProperty(MailMessage::class, $property);
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->getValue($object);
    };

    expect($getProtectedProperty($mailMessage, 'outroLines'))->toEqual([
        "This password reset link will expire in 30 minutes",
        "If you did not request a password reset, no further action is required.",
    ]);
});

test('toArray method returns an empty array', function () {
    $notification = new MailResetPasswordNotification('dummy_token_to_array');
    $notifiable = (object)['email' => 'toarray@example.com']; // Dummy notifiable

    expect($notification->toArray($notifiable))->toEqual([]);
});
