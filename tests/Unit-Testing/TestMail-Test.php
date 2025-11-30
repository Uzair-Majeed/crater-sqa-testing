<?php

use Crater\Mail\TestMail;
uses(\Mockery::class);

test('TestMail can be instantiated and properties are set correctly', function () {
    $subject = 'Test Subject Line';
    $message = 'This is the body of the test message.';

    $mail = new TestMail($subject, $message);

    expect($mail->subject)->toBe($subject);
    expect($mail->message)->toBe($message);
});

test('TestMail properties can be empty strings', function () {
    $subject = '';
    $message = '';

    $mail = new TestMail($subject, $message);

    expect($mail->subject)->toBe($subject);
    expect($mail->message)->toBe($message);
});

test('TestMail properties can be long strings', function () {
    $subject = str_repeat('A', 255); // Maximum typical subject length
    $message = str_repeat('B', 4096); // A long message body

    $mail = new TestMail($subject, $message);

    expect($mail->subject)->toBe($subject);
    expect($mail->message)->toBe($message);
});

test('TestMail build method configures the mailable correctly with valid inputs', function () {
    $mailSubject = 'Configured Subject';
    $mailMessage = 'Content for the email body.';

    // Create a partial mock of TestMail. This allows its constructor to run normally
    // while we can mock the fluent methods (subject, markdown, with) inherited from Mailable.
    $mailMock = Mockery::mock(TestMail::class . '[subject, markdown, with]', [$mailSubject, $mailMessage]);

    // Expect the 'subject' method to be called exactly once with the subject set in the constructor.
    // It should return the mock instance itself to allow method chaining.
    $mailMock->shouldReceive('subject')
             ->once()
             ->with($mailSubject)
             ->andReturn($mailMock);

    // Expect the 'markdown' method to be called exactly once with the specified view name.
    // It should also return the mock instance for chaining.
    $mailMock->shouldReceive('markdown')
             ->once()
             ->with('emails.test')
             ->andReturn($mailMock);

    // Expect the 'with' method to be called exactly once with the data array containing 'my_message'.
    // It should return the mock instance for chaining.
    $mailMock->shouldReceive('with')
             ->once()
             ->with(['my_message' => $mailMessage])
             ->andReturn($mailMock);

    // Call the actual build method on the partially mocked instance.
    $result = $mailMock->build();

    // Verify that the build method returns the instance itself, enabling fluent interface.
    expect($result)->toBe($mailMock);

    // Ensure all mock expectations were satisfied.
    Mockery::close();
});

test('TestMail build method works correctly with empty subject and message', function () {
    $mailSubject = '';
    $mailMessage = '';

    $mailMock = Mockery::mock(TestMail::class . '[subject, markdown, with]', [$mailSubject, $mailMessage]);

    $mailMock->shouldReceive('subject')
             ->once()
             ->with($mailSubject)
             ->andReturn($mailMock);

    $mailMock->shouldReceive('markdown')
             ->once()
             ->with('emails.test')
             ->andReturn($mailMock);

    $mailMock->shouldReceive('with')
             ->once()
             ->with(['my_message' => $mailMessage])
             ->andReturn($mailMock);

    $result = $mailMock->build();

    expect($result)->toBe($mailMock);

    Mockery::close();
});

test('TestMail build method uses correct view name regardless of input content', function () {
    $mailSubject = 'Any Subject';
    $mailMessage = 'Any Message';

    $mailMock = Mockery::mock(TestMail::class . '[subject, markdown, with]', [$mailSubject, $mailMessage]);

    $mailMock->shouldReceive('subject')->andReturn($mailMock);
    $mailMock->shouldReceive('markdown')
             ->once()
             ->with('emails.test') // Assert view name is always 'emails.test'
             ->andReturn($mailMock);
    $mailMock->shouldReceive('with')->andReturn($mailMock);

    $mailMock->build();

    Mockery::close();
});
