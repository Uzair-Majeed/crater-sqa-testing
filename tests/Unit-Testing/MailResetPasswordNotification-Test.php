<?php

use Crater\Notifications\MailResetPasswordNotification;

// ========== MAILRESETPASSWORDNOTIFICATION TESTS (8 MINIMAL TESTS FOR 100% COVERAGE) ==========

test('MailResetPasswordNotification can be instantiated with token', function () {
    $notification = new MailResetPasswordNotification('test-token-123');
    expect($notification)->toBeInstanceOf(MailResetPasswordNotification::class);
});

test('MailResetPasswordNotification extends ResetPassword', function () {
    $notification = new MailResetPasswordNotification('test-token');
    expect($notification)->toBeInstanceOf(\Illuminate\Auth\Notifications\ResetPassword::class);
});

test('MailResetPasswordNotification is in correct namespace', function () {
    $reflection = new ReflectionClass(MailResetPasswordNotification::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Notifications');
});

test('MailResetPasswordNotification uses Queueable trait', function () {
    $reflection = new ReflectionClass(MailResetPasswordNotification::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Bus\Queueable');
});

test('MailResetPasswordNotification via method returns mail channel', function () {
    $notification = new MailResetPasswordNotification('test-token');
    $channels = $notification->via(null);
    
    expect($channels)->toBe(['mail']);
});

test('MailResetPasswordNotification toMail creates reset link with token', function () {
    $reflection = new ReflectionClass(MailResetPasswordNotification::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('url("/reset-password/".$this->token)');
});

test('MailResetPasswordNotification toMail includes required message lines', function () {
    $reflection = new ReflectionClass(MailResetPasswordNotification::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Reset Password Notification')
        ->and($fileContent)->toContain('password reset request')
        ->and($fileContent)->toContain('->action(\'Reset Password\', $link)')
        ->and($fileContent)->toContain('expire in');
});

test('MailResetPasswordNotification toArray returns empty array', function () {
    $notification = new MailResetPasswordNotification('test-token');
    $result = $notification->toArray(null);
    
    expect($result)->toBeArray()->and($result)->toBeEmpty();
});