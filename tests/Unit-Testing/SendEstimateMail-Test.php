<?php

use Crater\Mail\SendEstimateMail;
use Crater\Models\EmailLog;
use Crater\Models\Estimate;
use Pest\Laravel\Functions; // Using Pest\Laravel\Functions for global function mocking in a Laravel context

beforeEach(function () {
    // Clear Mockery expectations and mocks before each test
    Mockery::close();
});

// Test for the constructor
test('constructor sets data correctly', function () {
    $data = [
        'from' => 'test@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Test Subject',
        'body' => 'Test Body',
        'estimate' => ['id' => 1, 'estimate_number' => 'EST-001'],
        'attach' => ['data' => null],
    ];

    $mail = new SendEstimateMail($data);

    expect($mail->data)->toEqual($data);
});

// Test for the build method without attachment
test('build method creates email log, sets url, and sends mail without attachment', function () {
    // Mock the EmailLog model
    $emailLogMock = Mockery::mock(EmailLog::class)->makePartial();
    $emailLogMock->id = 123;
    $emailLogMock->token = null; // Will be set later by the SUT
    $emailLogMock->shouldReceive('save')->once(); // Expect save to be called

    Mockery::mock('overload:' . EmailLog::class)
        ->shouldReceive('create')
        ->once()
        ->with([
            'from' => 'test@example.com',
            'to' => 'recipient@example.com',
            'subject' => 'Test Subject',
            'body' => 'Test Body',
            'mailable_type' => Estimate::class,
            'mailable_id' => 1,
        ])
        ->andReturn($emailLogMock);

    // Mock Hashids facade
    $hashidsConnectionMock = Mockery::mock();
    $hashidsConnectionMock->shouldReceive('encode')
        ->once()
        ->with($emailLogMock->id)
        ->andReturn('hashed_log_token');

    Mockery::mock('overload:Vinkla\Hashids\Facades\Hashids')
        ->shouldReceive('connection')
        ->once()
        ->with(EmailLog::class)
        ->andReturn($hashidsConnectionMock);

    // Mock global functions
    Functions::expect('route')
        ->with('estimate', ['email_log' => 'hashed_log_token'])
        ->andReturn('http://example.com/estimate/hashed_log_token');

    Functions::expect('config')
        ->with('mail.from.name')
        ->andReturn('Crater App');

    $data = [
        'from' => 'test@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Test Subject',
        'body' => 'Test Body',
        'estimate' => ['id' => 1, 'estimate_number' => 'EST-001'],
        'attach' => ['data' => null], // No attachment
    ];

    // Create a partial mock of SendEstimateMail to spy on its Mailable methods
    $mailableMock = Mockery::mock(SendEstimateMail::class, [$data])->makePartial();

    // Expect Mailable methods to be called
    $mailableMock->shouldReceive('from')
        ->once()
        ->with($data['from'], 'Crater App')
        ->andReturnSelf(); // Return self for fluent interface

    $mailableMock->shouldReceive('subject')
        ->once()
        ->with($data['subject'])
        ->andReturnSelf();

    // Prepare the expected data for markdown, including the 'url' that gets added
    $expectedDataForMarkdown = $data;
    $expectedDataForMarkdown['url'] = 'http://example.com/estimate/hashed_log_token';

    $mailableMock->shouldReceive('markdown')
        ->once()
        ->with('emails.send.estimate', ['data', Mockery::subset($expectedDataForMarkdown)])
        ->andReturnSelf();

    // Ensure attachData is NOT called
    $mailableMock->shouldNotReceive('attachData');

    $result = $mailableMock->build();

    // Assert that the result is the mailable mock itself (fluent interface)
    expect($result)->toBe($mailableMock);

    // Assert the log token was set
    expect($emailLogMock->token)->toBe('hashed_log_token');
    // Assert the URL was set in $this->data
    expect($mailableMock->data['url'])->toBe('http://example.com/estimate/hashed_log_token');
});


// Test for the build method with attachment
test('build method creates email log, sets url, and sends mail with attachment', function () {
    // Mock the EmailLog model
    $emailLogMock = Mockery::mock(EmailLog::class)->makePartial();
    $emailLogMock->id = 456;
    $emailLogMock->token = null;
    $emailLogMock->shouldReceive('save')->once();

    Mockery::mock('overload:' . EmailLog::class)
        ->shouldReceive('create')
        ->once()
        ->with([
            'from' => 'another@example.com',
            'to' => 'another_recipient@example.com',
            'subject' => 'Another Subject',
            'body' => 'Another Body',
            'mailable_type' => Estimate::class,
            'mailable_id' => 2,
        ])
        ->andReturn($emailLogMock);

    // Mock Hashids facade
    $hashidsConnectionMock = Mockery::mock();
    $hashidsConnectionMock->shouldReceive('encode')
        ->once()
        ->with($emailLogMock->id)
        ->andReturn('another_hashed_token');

    Mockery::mock('overload:Vinkla\Hashids\Facades\Hashids')
        ->shouldReceive('connection')
        ->once()
        ->with(EmailLog::class)
        ->andReturn($hashidsConnectionMock);

    // Mock global functions
    Functions::expect('route')
        ->with('estimate', ['email_log' => 'another_hashed_token'])
        ->andReturn('http://example.com/estimate/another_hashed_token');

    Functions::expect('config')
        ->with('mail.from.name')
        ->andReturn('Crater App');

    // Mock the attachment data object
    $attachmentDataMock = Mockery::mock();
    $attachmentDataMock->shouldReceive('output')
        ->once()
        ->andReturn('mock_pdf_content');

    $data = [
        'from' => 'another@example.com',
        'to' => 'another_recipient@example.com',
        'subject' => 'Another Subject',
        'body' => 'Another Body',
        'estimate' => ['id' => 2, 'estimate_number' => 'EST-002'],
        'attach' => ['data' => $attachmentDataMock], // With attachment
    ];

    // Create a partial mock of SendEstimateMail to spy on its Mailable methods
    $mailableMock = Mockery::mock(SendEstimateMail::class, [$data])->makePartial();

    // Expect Mailable methods to be called
    $mailableMock->shouldReceive('from')
        ->once()
        ->with($data['from'], 'Crater App')
        ->andReturnSelf();

    $mailableMock->shouldReceive('subject')
        ->once()
        ->with($data['subject'])
        ->andReturnSelf();

    // Prepare the expected data for markdown, including the 'url' that gets added
    $expectedDataForMarkdown = $data;
    $expectedDataForMarkdown['url'] = 'http://example.com/estimate/another_hashed_token';

    $mailableMock->shouldReceive('markdown')
        ->once()
        ->with('emails.send.estimate', ['data', Mockery::subset($expectedDataForMarkdown)])
        ->andReturnSelf();

    // Ensure attachData IS called
    $mailableMock->shouldReceive('attachData')
        ->once()
        ->with('mock_pdf_content', 'EST-002.pdf')
        ->andReturnSelf();

    $result = $mailableMock->build();

    expect($result)->toBe($mailableMock);

    expect($emailLogMock->token)->toBe('another_hashed_token');
    expect($mailableMock->data['url'])->toBe('http://example.com/estimate/another_hashed_token');
});


// Test for build method when attachment data exists but is falsey (e.g., false, null, empty string, 0)
test('build method does not attach data if attachment data is falsey', function () {
    // Mock the EmailLog model
    $emailLogMock = Mockery::mock(EmailLog::class)->makePartial();
    $emailLogMock->id = 789;
    $emailLogMock->token = null;
    $emailLogMock->shouldReceive('save')->once();

    Mockery::mock('overload:' . EmailLog::class)
        ->shouldReceive('create')
        ->once()
        ->andReturn($emailLogMock);

    // Mock Hashids facade
    $hashidsConnectionMock = Mockery::mock();
    $hashidsConnectionMock->shouldReceive('encode')->andReturn('falsey_attach_token');
    Mockery::mock('overload:Vinkla\Hashids\Facades\Hashids')
        ->shouldReceive('connection')->andReturn($hashidsConnectionMock);

    // Mock global functions
    Functions::expect('route')->with('estimate', ['email_log' => 'falsey_attach_token'])->andReturn('http://example.com/estimate/falsey_attach_token');
    Functions::expect('config')->with('mail.from.name')->andReturn('Crater App');

    $data = [
        'from' => 'falsey@example.com',
        'to' => 'falsey_recipient@example.com',
        'subject' => 'Falsey Subject',
        'body' => 'Falsey Body',
        'estimate' => ['id' => 3, 'estimate_number' => 'EST-003'],
        'attach' => ['data' => false], // Falsey attachment data
    ];

    $mailableMock = Mockery::mock(SendEstimateMail::class, [$data])->makePartial();

    $mailableMock->shouldReceive('from')->with($data['from'], 'Crater App')->andReturnSelf();
    $mailableMock->shouldReceive('subject')->with($data['subject'])->andReturnSelf();

    $expectedDataForMarkdown = $data;
    $expectedDataForMarkdown['url'] = 'http://example.com/estimate/falsey_attach_token';

    $mailableMock->shouldReceive('markdown')
        ->once()
        ->with('emails.send.estimate', ['data', Mockery::subset($expectedDataForMarkdown)])
        ->andReturnSelf();

    // Ensure attachData is NOT called because 'data' is falsey
    $mailableMock->shouldNotReceive('attachData');

    $result = $mailableMock->build();

    expect($result)->toBe($mailableMock);
    expect($emailLogMock->token)->toBe('falsey_attach_token');
    expect($mailableMock->data['url'])->toBe('http://example.com/estimate/falsey_attach_token');
});

// Test for missing essential data keys in $this->data that would cause an error
test('build method throws error if essential data keys are missing', function () {
    // Test case 1: 'estimate' key is missing entirely
    $dataMissingEstimate = [
        'from' => 'test@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Test Subject',
        'body' => 'Test Body',
        // 'estimate' key is missing
        'attach' => ['data' => null],
    ];

    $mailMissingEstimate = new SendEstimateMail($dataMissingEstimate);

    // Expect an exception because 'estimate' key is missing and its subkeys are accessed
    expect(fn () => $mailMissingEstimate->build())->toThrow(\ErrorException::class, 'Undefined array key "estimate"');

    // Test case 2: 'id' key is missing within 'estimate'
    $dataMissingEstimateId = [
        'from' => 'test@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Test Subject',
        'body' => 'Test Body',
        'estimate' => ['estimate_number' => 'EST-001'], // 'id' key is missing
        'attach' => ['data' => null],
    ];

    $mailMissingEstimateId = new SendEstimateMail($dataMissingEstimateId);

    // Expect an exception because 'id' key is missing
    expect(fn () => $mailMissingEstimateId->build())->toThrow(\ErrorException::class, 'Undefined array key "id"');
});

// Test for build method handling missing mail from name in config
test('build method handles missing mail from name in config', function () {
    // Mock the EmailLog model
    $emailLogMock = Mockery::mock(EmailLog::class)->makePartial();
    $emailLogMock->id = 123;
    $emailLogMock->token = null;
    $emailLogMock->shouldReceive('save')->once();

    Mockery::mock('overload:' . EmailLog::class)
        ->shouldReceive('create')
        ->once()
        ->andReturn($emailLogMock);

    // Mock Hashids facade
    $hashidsConnectionMock = Mockery::mock();
    $hashidsConnectionMock->shouldReceive('encode')->andReturn('hashed_log_token');
    Mockery::mock('overload:Vinkla\Hashids\Facades\Hashids')
        ->shouldReceive('connection')->andReturn($hashidsConnectionMock);

    // Mock global functions
    Functions::expect('route')->with('estimate', ['email_log' => 'hashed_log_token'])->andReturn('http://example.com/estimate/hashed_log_token');

    // Mock config to return null for 'mail.from.name'
    Functions::expect('config')
        ->with('mail.from.name')
        ->andReturn(null); // Simulate missing config value

    $data = [
        'from' => 'test@example.com',
        'to' => 'recipient@example.com',
        'subject' => 'Test Subject',
        'body' => 'Test Body',
        'estimate' => ['id' => 1, 'estimate_number' => 'EST-001'],
        'attach' => ['data' => null],
    ];

    $mailableMock = Mockery::mock(SendEstimateMail::class, [$data])->makePartial();

    $mailableMock->shouldReceive('from')
        ->once()
        ->with($data['from'], null) // Expect null as the second argument
        ->andReturnSelf();

    $mailableMock->shouldReceive('subject')->with($data['subject'])->andReturnSelf();

    $expectedDataForMarkdown = $data;
    $expectedDataForMarkdown['url'] = 'http://example.com/estimate/hashed_log_token';

    $mailableMock->shouldReceive('markdown')
        ->once()
        ->with('emails.send.estimate', ['data', Mockery::subset($expectedDataForMarkdown)])
        ->andReturnSelf();
    $mailableMock->shouldNotReceive('attachData');

    $result = $mailableMock->build();

    expect($result)->toBe($mailableMock);
    expect($emailLogMock->token)->toBe('hashed_log_token');
    expect($mailableMock->data['url'])->toBe('http://example.com/estimate/hashed_log_token');
});




afterEach(function () {
    Mockery::close();
});
