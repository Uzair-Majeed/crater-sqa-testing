<?php

use Crater\Http\Requests\UploadExpenseReceiptRequest;
use Crater\Rules\Base64Mime;

it('authorizes the request', function () {
    $request = new UploadExpenseReceiptRequest();
    expect($request->authorize())->toBeTrue();
});

it('provides the correct validation rules for attachment_receipt', function () {
    $request = new UploadExpenseReceiptRequest();
    $rules = $request->rules();

    expect($rules)->toBeArray()
                  ->toHaveKey('attachment_receipt');

    $attachmentRules = $rules['attachment_receipt'];
    expect($attachmentRules)->toBeArray()
                            ->toContain('nullable');

    // Find the Base64Mime rule instance
    $base64MimeRule = null;
    foreach ($attachmentRules as $rule) {
        if ($rule instanceof Base64Mime) {
            $base64MimeRule = $rule;
            break;
        }
    }

    expect($base64MimeRule)->not->toBeNull('Base64Mime rule instance not found in attachment_receipt rules.');
    expect($base64MimeRule)->toBeInstanceOf(Base64Mime::class);

    // White-box check: Use Reflection to inspect the private property storing the allowed mimes.
    // This assumes Base64Mime stores the constructor argument in a private property named `mimes`.
    // If Base64Mime has a different internal structure (e.g., a protected property or a different name),
    // this part of the test would need adjustment based on its actual implementation.
    try {
        $reflection = new ReflectionClass($base64MimeRule);
        $mimesProperty = $reflection->getProperty('mimes');
        $mimesProperty->setAccessible(true);
        $actualMimes = $mimesProperty->getValue($base64MimeRule);

        expect($actualMimes)->toEqual(['gif', 'jpg', 'png']);
    } catch (ReflectionException $e) {
        $this->fail("Failed to access 'mimes' property of Base64Mime. Ensure Base64Mime has a private/protected property named 'mimes' that stores the allowed mimes, or provide a public getter for it. Original exception: {$e->getMessage()}");
    }
});




afterEach(function () {
    Mockery::close();
});
