<?php
use Crater\Http\Requests\CompanyLogoRequest;
use Crater\Rules\Base64Mime;

it('authorizes the user to make the request', function () {
    // Instantiate the FormRequest directly for unit testing its internal logic.
    $request = new CompanyLogoRequest();
    expect($request->authorize())->toBeTrue();
});

it('returns the correct validation rules for the company_logo field', function () {
    $request = new CompanyLogoRequest();
    $rules = $request->rules();

    // Assert the main structure of the returned rules array.
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('company_logo');

    $companyLogoRules = $rules['company_logo'];
    expect($companyLogoRules)->toBeArray()
        ->and($companyLogoRules)->toContain('nullable'); // Ensure 'nullable' rule is present.

    // Find the Base64Mime rule instance within the array.
    $base64MimeRule = null;
    foreach ($companyLogoRules as $rule) {
        if ($rule instanceof Base64Mime) {
            $base64MimeRule = $rule;
            break;
        }
    }

    // Assert that a Base64Mime rule instance was found and is of the correct type.
    expect($base64MimeRule)
        ->not->toBeNull('Expected Base64Mime rule to be present in the rules array.')
        ->and($base64MimeRule)->toBeInstanceOf(Base64Mime::class);

    // White-box testing: Use Reflection to access and verify the private 'allowedMimes' property
    // of the Base64Mime instance, ensuring it was constructed with the correct arguments.
    $reflectionProperty = new ReflectionProperty(Base64Mime::class, 'allowedMimes');
    $reflectionProperty->setAccessible(true); // Make the private property accessible.

    $allowedMimes = $reflectionProperty->getValue($base64MimeRule);

    // Verify the specific allowed mime types passed to the Base64Mime constructor.
    expect($allowedMimes)->toBe(['gif', 'jpg', 'png']);
});
