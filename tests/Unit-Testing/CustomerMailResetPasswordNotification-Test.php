<?php

use Crater\Notifications\CustomerMailResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;

// Test constructor correctly sets the token
test('constructor correctly sets the token via the parent class', function () {
    $token = 'test-token-123';
    $notification = new CustomerMailResetPasswordNotification($token);

    // Use reflection to access the protected token property from parent class
    $reflection = new ReflectionClass($notification);
    $parentReflection = $reflection->getParentClass();
    $tokenProperty = $parentReflection->getProperty('token');
    $tokenProperty->setAccessible(true);

    expect($tokenProperty->getValue($notification))->toBe($token);
});

// Test constructor with different token formats
test('constructor handles various token formats', function () {
    $tokens = [
        'simple-token',
        'token_with_underscores',
        'token-with-dashes',
        'TokenWithMixedCase123',
        '1234567890',
        'very-long-token-string-with-many-characters-to-test-length-handling',
    ];

    foreach ($tokens as $token) {
        $notification = new CustomerMailResetPasswordNotification($token);
        
        $reflection = new ReflectionClass($notification);
        $parentReflection = $reflection->getParentClass();
        $tokenProperty = $parentReflection->getProperty('token');
        $tokenProperty->setAccessible(true);

        expect($tokenProperty->getValue($notification))->toBe($token);
    }
});

// Test via method returns mail channel
test('via method returns the mail channel as expected', function () {
    $notification = new CustomerMailResetPasswordNotification('some-token');
    
    // Create a simple notifiable object
    $notifiable = new stdClass();

    $channels = $notification->via($notifiable);
    
    expect($channels)->toBeArray()
        ->and($channels)->toHaveCount(1)
        ->and($channels)->toContain('mail')
        ->and($channels[0])->toBe('mail');
});

// Test toMail method constructs a MailMessage with correct details
test('toMail method constructs a MailMessage with correct details for a standard case', function () {
    $token = 'secure-reset-token';
    $companySlug = 'my-awesome-company';

    // Create a notifiable object with company
    $notifiable = new stdClass();
    $notifiable->company = new stdClass();
    $notifiable->company->slug = $companySlug;

    $notification = new CustomerMailResetPasswordNotification($token);
    $mailMessage = $notification->toMail($notifiable);

    // Verify it's a MailMessage instance
    expect($mailMessage)->toBeInstanceOf(MailMessage::class);
    
    // Verify subject
    expect($mailMessage->subject)->toBe('Reset Password Notification');
    
    // Verify intro lines
    expect($mailMessage->introLines)->toBeArray()
        ->and($mailMessage->introLines)->toHaveCount(1)
        ->and($mailMessage->introLines[0])->toBe("Hello! You are receiving this email because we received a password reset request for your account.");
    
    // Verify action text
    expect($mailMessage->actionText)->toBe('Reset Password');
    
    // Verify action URL contains the token and company slug
    expect($mailMessage->actionUrl)->toContain($token)
        ->and($mailMessage->actionUrl)->toContain($companySlug)
        ->and($mailMessage->actionUrl)->toContain('customer/reset/password');
    
    // Verify outro lines
    expect($mailMessage->outroLines)->toBeArray()
        ->and($mailMessage->outroLines)->toHaveCount(2)
        ->and($mailMessage->outroLines[0])->toContain('password reset link will expire')
        ->and($mailMessage->outroLines[0])->toContain('minutes')
        ->and($mailMessage->outroLines[1])->toBe("If you did not request a password reset, no further action is required.");
});

// Test toMail with different company slugs
test('toMail method generates correct URL with different company slugs', function () {
    $token = 'test-token';
    $companySlugs = [
        'company-one',
        'company_two',
        'CompanyThree',
        '123company',
        'my-test-company-slug',
    ];

    foreach ($companySlugs as $slug) {
        $notifiable = new stdClass();
        $notifiable->company = new stdClass();
        $notifiable->company->slug = $slug;

        $notification = new CustomerMailResetPasswordNotification($token);
        $mailMessage = $notification->toMail($notifiable);

        expect($mailMessage->actionUrl)->toContain($slug)
            ->and($mailMessage->actionUrl)->toContain($token)
            ->and($mailMessage->actionUrl)->toContain('customer/reset/password');
    }
});

// Test toMail with empty company slug
test('toMail method handles empty company slug', function () {
    $token = 'token-with-empty-slug';
    
    $notifiable = new stdClass();
    $notifiable->company = new stdClass();
    $notifiable->company->slug = '';

    $notification = new CustomerMailResetPasswordNotification($token);
    $mailMessage = $notification->toMail($notifiable);

    // Verify the URL is still generated and contains the token
    expect($mailMessage->actionUrl)->toContain($token)
        ->and($mailMessage->actionUrl)->toContain('customer/reset/password');
});

// Test toMail with null company slug
test('toMail method handles null company slug', function () {
    $token = 'token-with-null-slug';
    
    $notifiable = new stdClass();
    $notifiable->company = new stdClass();
    $notifiable->company->slug = null;

    $notification = new CustomerMailResetPasswordNotification($token);
    $mailMessage = $notification->toMail($notifiable);

    // Verify the URL is still generated and contains the token
    expect($mailMessage->actionUrl)->toContain($token)
        ->and($mailMessage->actionUrl)->toContain('customer/reset/password');
});

// Test toMail with different tokens
test('toMail method uses the correct token in the reset link', function () {
    $tokens = [
        'unique-token-alpha',
        'distinct-token-beta',
        'another-token-gamma',
    ];
    
    $companySlug = 'token-test-company';

    foreach ($tokens as $token) {
        $notifiable = new stdClass();
        $notifiable->company = new stdClass();
        $notifiable->company->slug = $companySlug;

        $notification = new CustomerMailResetPasswordNotification($token);
        $mailMessage = $notification->toMail($notifiable);

        expect($mailMessage->actionUrl)->toContain($token)
            ->and($mailMessage->actionUrl)->toContain($companySlug);
    }
});

// Test that each notification instance is independent
test('multiple notification instances maintain independent tokens', function () {
    $token1 = 'token-instance-1';
    $token2 = 'token-instance-2';
    
    $notification1 = new CustomerMailResetPasswordNotification($token1);
    $notification2 = new CustomerMailResetPasswordNotification($token2);

    $notifiable = new stdClass();
    $notifiable->company = new stdClass();
    $notifiable->company->slug = 'test-company';

    $mailMessage1 = $notification1->toMail($notifiable);
    $mailMessage2 = $notification2->toMail($notifiable);

    expect($mailMessage1->actionUrl)->toContain($token1)
        ->and($mailMessage1->actionUrl)->not->toContain($token2)
        ->and($mailMessage2->actionUrl)->toContain($token2)
        ->and($mailMessage2->actionUrl)->not->toContain($token1);
});

// Test toArray method returns an empty array
test('toArray method consistently returns an empty array', function () {
    $notification = new CustomerMailResetPasswordNotification('any-token');
    $notifiable = new stdClass();

    $result = $notification->toArray($notifiable);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty()
        ->and($result)->toEqual([]);
});

// Test toArray with different notifiable objects
test('toArray method returns empty array regardless of notifiable', function () {
    $notification = new CustomerMailResetPasswordNotification('test-token');
    
    // Test with different notifiable objects
    $notifiables = [
        new stdClass(),
        (object) ['id' => 1, 'email' => 'test@example.com'],
        (object) ['company' => (object) ['slug' => 'test']],
    ];

    foreach ($notifiables as $notifiable) {
        $result = $notification->toArray($notifiable);
        expect($result)->toBeArray()->and($result)->toBeEmpty();
    }
});

// Test MailMessage structure completeness
test('toMail returns a complete MailMessage with all required properties', function () {
    $token = 'complete-test-token';
    $companySlug = 'complete-company';

    $notifiable = new stdClass();
    $notifiable->company = new stdClass();
    $notifiable->company->slug = $companySlug;

    $notification = new CustomerMailResetPasswordNotification($token);
    $mailMessage = $notification->toMail($notifiable);

    // Verify all essential properties are set
    expect($mailMessage->subject)->not->toBeNull()
        ->and($mailMessage->subject)->not->toBeEmpty()
        ->and($mailMessage->actionText)->not->toBeNull()
        ->and($mailMessage->actionText)->not->toBeEmpty()
        ->and($mailMessage->actionUrl)->not->toBeNull()
        ->and($mailMessage->actionUrl)->not->toBeEmpty()
        ->and($mailMessage->introLines)->not->toBeEmpty()
        ->and($mailMessage->outroLines)->not->toBeEmpty();
});

// Test that the notification extends the correct parent class
test('notification extends ResetPassword class', function () {
    $notification = new CustomerMailResetPasswordNotification('test-token');
    
    expect($notification)->toBeInstanceOf(\Illuminate\Auth\Notifications\ResetPassword::class)
        ->and($notification)->toBeInstanceOf(\Illuminate\Notifications\Notification::class);
});

// Test via method with different notifiable types
test('via method returns mail channel for different notifiable types', function () {
    $notification = new CustomerMailResetPasswordNotification('test-token');
    
    $notifiables = [
        new stdClass(),
        (object) ['email' => 'test@example.com'],
        (object) ['company' => (object) ['slug' => 'test']],
    ];

    foreach ($notifiables as $notifiable) {
        $channels = $notification->via($notifiable);
        expect($channels)->toBe(['mail']);
    }
});

// Test that expiry time is included in the message
test('toMail includes expiry time in outro lines', function () {
    $token = 'expiry-test-token';
    $companySlug = 'expiry-company';

    $notifiable = new stdClass();
    $notifiable->company = new stdClass();
    $notifiable->company->slug = $companySlug;

    $notification = new CustomerMailResetPasswordNotification($token);
    $mailMessage = $notification->toMail($notifiable);

    // First outro line should contain expiry information
    expect($mailMessage->outroLines[0])->toContain('expire')
        ->and($mailMessage->outroLines[0])->toContain('minutes')
        ->and($mailMessage->outroLines[0])->toMatch('/\d+/'); // Contains a number
});

// Test URL structure
test('toMail generates URL with correct structure', function () {
    $token = 'url-structure-token';
    $companySlug = 'url-company';

    $notifiable = new stdClass();
    $notifiable->company = new stdClass();
    $notifiable->company->slug = $companySlug;

    $notification = new CustomerMailResetPasswordNotification($token);
    $mailMessage = $notification->toMail($notifiable);

    // Verify URL structure
    expect($mailMessage->actionUrl)->toMatch('/\/customer\/reset\/password\//')
        ->and($mailMessage->actionUrl)->toEndWith($token);
});

// Test that intro message is user-friendly
test('toMail contains user-friendly intro message', function () {
    $notification = new CustomerMailResetPasswordNotification('test-token');
    
    $notifiable = new stdClass();
    $notifiable->company = new stdClass();
    $notifiable->company->slug = 'test';

    $mailMessage = $notification->toMail($notifiable);

    expect($mailMessage->introLines[0])->toContain('Hello')
        ->and($mailMessage->introLines[0])->toContain('password reset request')
        ->and($mailMessage->introLines[0])->toContain('account');
});
