```php
<?php

use Crater\Mail\SendInvoiceMail;
use Crater\Models\EmailLog;
use Crater\Models\Invoice;
use Illuminate\Mail\Mailable;
use Vinkla\Hashids\Facades\Hashids;

// Define common test data
$testData = [
    'from' => 'sender@example.com',
    'to' => 'recipient@example.com',
    'subject' => 'Test Subject',
    'body' => 'Test Body Content',
    'invoice' => [
        'id' => 456,
        'invoice_number' => 'INV-123',
    ],
    'attach' => [
        'data' => null, // Default to no attachment
    ],
    // 'url' will be added during build by the build method itself
];

// Ensure mocks are closed after each test
beforeEach(function () {
    Mockery::close();
});

test('constructor sets data property correctly', function () use ($testData) {
    $mail = new SendInvoiceMail($testData);

    expect($mail->data)->toBe($testData);
});

test('build method creates email log and sets properties without attachment', function () use ($testData) {
    // Mock EmailLog::create
    // Use a partial mock for the EmailLog instance to allow attribute setting/getting,
    // which resolves the Mockery\Exception\BadMethodCallException for setAttribute.
    $emailLogMock = Mockery::mock(EmailLog::class)->makePartial();
    $emailLogMock->shouldReceive('save')->once();
    $emailLogMock->id = 123; // This will now call the real setAttribute on the partial mock
    $emailLogMock->token = null; // This will also call the real setAttribute

    Mockery::mock('alias:' . EmailLog::class)
        ->shouldReceive('create')
        ->once()
        ->with([
            'from' => $testData['from'],
            'to' => $testData['to'],
            'subject' => $testData['subject'],
            'body' => $testData['body'],
            'mailable_type' => Invoice::class,
            'mailable_id' => $testData['invoice']['id'],
        ])
        ->andReturn($emailLogMock);

    // Mock Hashids facade
    $hashedToken = 'hashed-log-token-123';
    $hashidsConnectionMock = Mockery::mock(\Vinkla\Hashids\HashidsManager::class);
    // When $emailLog->id is accessed, the partial mock will return the set 'id'.
    $hashidsConnectionMock->shouldReceive('encode')
        ->with($emailLogMock->id) // Use the mock's id property
        ->andReturn($hashedToken)
        ->once();

    Mockery::mock('alias:' . Hashids::class)
        ->shouldReceive('connection')
        ->with(EmailLog::class)
        ->andReturn($hashidsConnectionMock)
        ->once();

    // Mock global functions route and config
    test()->mock('route', function ($name, $parameters = []) use ($hashedToken) {
        expect($name)->toBe('invoice');
        expect($parameters)->toBe(['email_log' => $hashedToken]);
        return 'http://example.com/invoice/' . $hashedToken;
    });

    test()->mock('config', function ($key) {
        expect($key)->toBe('mail.from.name');
        return 'Crater App';
    });

    // Create a partial mock of SendInvoiceMail to mock Mailable methods
    $mail = Mockery::mock(SendInvoiceMail::class, [$testData])->makePartial();

    // Mock Mailable methods and ensure they return the instance for chaining
    $mail->shouldReceive('from')
        ->with($testData['from'], 'Crater App')
        ->andReturn($mail)
        ->once();

    $mail->shouldReceive('subject')
        ->with($testData['subject'])
        ->andReturn($mail)
        ->once();

    // Prepare expected data for markdown. The 'url' will be added by the build method.
    $expectedDataForMarkdown = $testData;
    $expectedDataForMarkdown['url'] = 'http://example.com/invoice/' . $hashedToken;

    // Fix: The markdown method expects an associative array for view variables, e.g., ['data' => $this->data].
    // The original test had ['data', $testData] which is not a proper key-value pair for view data.
    $mail->shouldReceive('markdown')
        ->with('emails.send.invoice', ['data' => $expectedDataForMarkdown])
        ->andReturn($mail)
        ->once();

    // Ensure attachData is NOT called for this test case
    $mail->shouldNotReceive('attachData');

    // Call the build method
    $result = $mail->build();

    // Assert that the token was set on the log object
    expect($emailLogMock->token)->toBe($hashedToken);

    // Assert that the 'url' was added to the data property
    expect($mail->data['url'])->toBe('http://example.com/invoice/' . $hashedToken);

    // Assert the build method returns the mail instance (or its mock)
    expect($result)->toBe($mail);
});

test('build method creates email log and sets properties with attachment', function () use ($testData) {
    // Modify testData to include attachment
    $attachmentContent = 'PDF_BINARY_DATA';
    // Mock the object that has the 'output()' method
    $attachmentFileMock = Mockery::mock(\stdClass::class);
    $attachmentFileMock->shouldReceive('output')
        ->andReturn($attachmentContent)
        ->once();

    $testDataWithAttachment = $testData;
    $testDataWithAttachment['attach']['data'] = $attachmentFileMock;

    // Mock EmailLog::create
    // Use a partial mock for the EmailLog instance to allow attribute setting/getting,
    // which resolves the Mockery\Exception\BadMethodCallException for setAttribute.
    $emailLogMock = Mockery::mock(EmailLog::class)->makePartial();
    $emailLogMock->shouldReceive('save')->once();
    $emailLogMock->id = 456; // This will now call the real setAttribute on the partial mock
    $emailLogMock->token = null; // This will also call the real setAttribute

    Mockery::mock('alias:' . EmailLog::class)
        ->shouldReceive('create')
        ->once()
        ->with([
            'from' => $testDataWithAttachment['from'],
            'to' => $testDataWithAttachment['to'],
            'subject' => $testDataWithAttachment['subject'],
            'body' => $testDataWithAttachment['body'],
            'mailable_type' => Invoice::class,
            'mailable_id' => $testDataWithAttachment['invoice']['id'],
        ])
        ->andReturn($emailLogMock);

    // Mock Hashids facade
    $hashedToken = 'hashed-log-token-456';
    $hashidsConnectionMock = Mockery::mock(\Vinkla\Hashids\HashidsManager::class);
    // When $emailLog->id is accessed, the partial mock will return the set 'id'.
    $hashidsConnectionMock->shouldReceive('encode')
        ->with($emailLogMock->id) // Use the mock's id property
        ->andReturn($hashedToken)
        ->once();

    Mockery::mock('alias:' . Hashids::class)
        ->shouldReceive('connection')
        ->with(EmailLog::class)
        ->andReturn($hashidsConnectionMock)
        ->once();

    // Mock global functions route and config
    test()->mock('route', function ($name, $parameters = []) use ($hashedToken) {
        expect($name)->toBe('invoice');
        expect($parameters)->toBe(['email_log' => $hashedToken]);
        return 'http://example.com/invoice/' . $hashedToken;
    });

    test()->mock('config', function ($key) {
        expect($key)->toBe('mail.from.name');
        return 'Crater App';
    });

    // Create a partial mock of SendInvoiceMail to mock Mailable methods
    $mail = Mockery::mock(SendInvoiceMail::class, [$testDataWithAttachment])->makePartial();

    // Mock Mailable methods and ensure they return the instance for chaining
    $mail->shouldReceive('from')
        ->with($testDataWithAttachment['from'], 'Crater App')
        ->andReturn($mail)
        ->once();

    $mail->shouldReceive('subject')
        ->with($testDataWithAttachment['subject'])
        ->andReturn($mail)
        ->once();

    // Prepare expected data for markdown. The 'url' will be added by the build method.
    $expectedDataForMarkdown = $testDataWithAttachment;
    $expectedDataForMarkdown['url'] = 'http://example.com/invoice/' . $hashedToken;

    // Fix: The markdown method expects an associative array for view variables, e.g., ['data' => $this->data].
    // The original test had ['data', $testDataWithAttachment] which is not a proper key-value pair for view data.
    $mail->shouldReceive('markdown')
        ->with('emails.send.invoice', ['data' => $expectedDataForMarkdown])
        ->andReturn($mail)
        ->once();

    // Ensure attachData IS called for this test case
    $mail->shouldReceive('attachData')
        ->with($attachmentContent, $testDataWithAttachment['invoice']['invoice_number'] . '.pdf')
        ->andReturn($mail)
        ->once();

    // Call the build method
    $result = $mail->build();

    // Assert that the token was set on the log object
    expect($emailLogMock->token)->toBe($hashedToken);

    // Assert that the 'url' was added to the data property
    expect($mail->data['url'])->toBe('http://example.com/invoice/' . $hashedToken);

    // Assert the build method returns the mail instance (or its mock)
    expect($result)->toBe($mail);
});

afterEach(function () {
    Mockery::close();
});
```