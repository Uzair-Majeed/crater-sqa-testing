<?php

use Mockery as m;
use Crater\Http\Requests\MailEnvironmentRequest;

/**
 * Helper function to create a partial mock of MailEnvironmentRequest
 * and control the return value of its `get` method for 'mail_driver'.
 */
function createMockMailEnvironmentRequest(string $mailDriver = null): MailEnvironmentRequest
{
    $request = m::mock(MailEnvironmentRequest::class)->makePartial();

    if ($mailDriver !== null) {
        $request->shouldReceive('get')
            ->with('mail_driver')
            ->andReturn($mailDriver);
    } else {
        // If no driver specified or null, simulate the default behavior of get()
        // which would return null if the key is not present.
        $request->shouldReceive('get')
            ->with('mail_driver')
            ->andReturn(null);
    }

    return $request;
}

test('authorize method always returns true', function () {
    $request = new MailEnvironmentRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct rules for "smtp" mail driver', function () {
    $request = createMockMailEnvironmentRequest('smtp');

    $expectedRules = [
        'mail_driver' => ['required', 'string'],
        'mail_host' => ['required', 'string'],
        'mail_port' => ['required'],
        'mail_encryption' => ['required', 'string'],
        'from_name' => ['required', 'string'],
        'from_mail' => ['required', 'string'],
    ];

    expect($request->rules())->toEqual($expectedRules);
});

test('rules method returns correct rules for "mailgun" mail driver', function () {
    $request = createMockMailEnvironmentRequest('mailgun');

    $expectedRules = [
        'mail_driver' => ['required', 'string'],
        'mail_mailgun_domain' => ['required', 'string'],
        'mail_mailgun_secret' => ['required', 'string'],
        'mail_mailgun_endpoint' => ['required', 'string'],
        'from_name' => ['required', 'string'],
        'from_mail' => ['required', 'string'],
    ];

    expect($request->rules())->toEqual($expectedRules);
});

test('rules method returns correct rules for "ses" mail driver', function () {
    $request = createMockMailEnvironmentRequest('ses');

    $expectedRules = [
        'mail_driver' => ['required', 'string'],
        'mail_host' => ['required', 'string'],
        'mail_port' => ['required'],
        'mail_ses_key' => ['required', 'string'],
        'mail_ses_secret' => ['required', 'string'],
        'mail_encryption' => ['nullable', 'string'], // Note: nullable for SES
        'from_name' => ['required', 'string'],
        'from_mail' => ['required', 'string'],
    ];

    expect($request->rules())->toEqual($expectedRules);
});

test('rules method returns correct rules for "mail" mail driver', function () {
    $request = createMockMailEnvironmentRequest('mail');

    $expectedRules = [
        'from_name' => ['required', 'string'],
        'from_mail' => ['required', 'string'],
    ];

    expect($request->rules())->toEqual($expectedRules);
});

test('rules method returns correct rules for "sendmail" mail driver', function () {
    $request = createMockMailEnvironmentRequest('sendmail');

    $expectedRules = [
        'from_name' => ['required', 'string'],
        'from_mail' => ['required', 'string'],
    ];

    expect($request->rules())->toEqual($expectedRules);
});

test('rules method returns an empty array for an unknown mail driver', function () {
    $request = createMockMailEnvironmentRequest('unknown_driver');
    expect($request->rules())->toEqual([]);
});

test('rules method returns an empty array when mail_driver is null (not provided)', function () {
    $request = createMockMailEnvironmentRequest(null); // Simulate $this->get('mail_driver') returning null
    expect($request->rules())->toEqual([]);
});

test('rules method returns an empty array when mail_driver is an empty string', function () {
    $request = createMockMailEnvironmentRequest('');
    expect($request->rules())->toEqual([]);
});

// Clean up Mockery after each test to prevent memory leaks and ensure test isolation.
afterEach(function () {
    m::close();
});



