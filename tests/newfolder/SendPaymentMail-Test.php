```php
<?php

use Crater\Mail\SendPaymentMail;
use Crater\Models\EmailLog;
use Crater\Models\Payment;
use Illuminate\Mail\Mailable;
use Vinkla\Hashids\Facades\Hashids;

// Setup common mocks for all tests. These will be re-applied for each test due to beforeEach and afterEach.
beforeEach(function () {
    // Mock `route` helper via URL facade
    Mockery::mock('alias:Illuminate\Support\Facades\URL')
        ->shouldReceive('route')
        ->andReturnUsing(function ($name, $parameters = [], $absolute = true) {
            if ($name === 'payment' && isset($parameters['email_log'])) {
                return "http://test-app.com/payment/{$parameters['email_log']}";
            }
            return "http://test-app.com/{$name}";
        })
        ->byDefault(); // Allow multiple calls, default behavior

    // Mock `config` helper via Config facade
    Mockery::mock('alias:Illuminate\Support\Facades\Config')
        ->shouldReceive('get')
        ->andReturnUsing(function ($key, $default = null) {
            if ($key === 'mail.from.name') {
                return 'Crater App';
            }
            return $default;
        })
        ->byDefault();

    // Mock Hashids facade
    Mockery::mock('alias:' . Hashids::class)
        ->shouldReceive('connection')
        ->with(EmailLog::class)
        ->andReturn(
            Mockery::mock()
                ->shouldReceive('encode')
                ->andReturnUsing(function ($id) {
                    return "HASHED_$id";
                })
                ->byDefault()
                ->getMock()
        )
        ->byDefault();
});

// Clean up mocks after each test

// Use a common test data structure
$commonTestData = [
    'from' => 'sender@example.com',
    'to' => 'recipient@example.com',
    'subject' => 'Test Subject',
    'body' => 'Test Body',
    'payment' => [
        'id' => 1,
        'payment_number' => 'PAY001',
    ],
    'attach' => [
        'data' => null, // Default no attachment
    ],
];

test('constructor correctly sets data property', function () use ($commonTestData) {
    $mail = new SendPaymentMail($commonTestData);

    expect($mail->data)->toEqual($commonTestData);
});

test('build method handles no attachment successfully', function () use ($commonTestData) {
    $emailLogId = 100;
    $encodedToken = "HASHED_$emailLogId";
    $expectedUrl = "http://test-app.com/payment/$encodedToken";
    $expectedFromName = 'Crater App';

    // Mock EmailLog::create and its returned instance
    $mockEmailLog = Mockery::mock(EmailLog::class)->makePartial();
    $mockEmailLog->id = $emailLogId;
    $mockEmailLog->shouldReceive('save')->once();
    $mockEmailLog->token = null; // Ensure it's null before setting

    // FIX: Use 'overload:' for concrete classes to prevent "class already exists" errors
    Mockery::mock('overload:' . EmailLog::class)
        ->shouldReceive('create')
        ->once()
        ->with([
            'from' => $commonTestData['from'],
            'to' => $commonTestData['to'],
            'subject' => $commonTestData['subject'],
            'body' => $commonTestData['body'],
            'mailable_type' => Payment::class,
            'mailable_id' => $commonTestData['payment']['id'],
        ])
        ->andReturn($mockEmailLog);

    // Mock the Mailable chain methods
    $finalMailableMock = Mockery::mock(Mailable::class); // Represents the Mailable returned by markdown
    $finalMailableMock->shouldNotReceive('attachData'); // No attachment in this test case

    $mail = Mockery::mock(SendPaymentMail::class, [$commonTestData])->makePartial();
    $mail->shouldReceive('from')
        ->once()
        ->with($commonTestData['from'], $expectedFromName)
        ->andReturnSelf();
    $mail->shouldReceive('subject')
        ->once()
        ->with($commonTestData['subject'])
        ->andReturnSelf();

    // FIX: Correct markdown arguments. A Laravel Mailable's markdown method
    // typically takes (view_name, array $data). If the Mailable passes
    // its internal $this->data property wrapped in a 'data' key,
    // the expectation should match that structure.
    $mail->shouldReceive('markdown')
        ->once()
        ->with('emails.send.payment', ['data' => Mockery::subset([
            'from' => $commonTestData['from'],
            'to' => $commonTestData['to'],
            'subject' => $commonTestData['subject'],
            'body' => $commonTestData['body'],
            'payment' => [
                'id' => $commonTestData['payment']['id'],
                'payment_number' => $commonTestData['payment']['payment_number'],
            ],
            'attach' => ['data' => null],
            'url' => $expectedUrl,
        ])])
        ->andReturn($finalMailableMock);


    $result = $mail->build();

    expect($result)->toBe($finalMailableMock); // Ensure the correct Mailable instance is returned
    expect($mockEmailLog->token)->toBe($encodedToken); // Verify token was set and saved (via save method mock)
    expect($mail->data['url'])->toBe($expectedUrl); // Verify URL was added to data
});

test('build method handles attachment successfully', function () use ($commonTestData) {
    $emailLogId = 200;
    $encodedToken = "HASHED_$emailLogId";
    $expectedUrl = "http://test-app.com/payment/$encodedToken";
    $expectedFromName = 'Crater App';

    // Mock an attachment object with an output() method
    $mockAttachmentData = Mockery::mock();
    $mockAttachmentData->shouldReceive('output')->once()->andReturn('PDF_RAW_DATA');

    $attachmentTestData = $commonTestData;
    $attachmentTestData['attach']['data'] = $mockAttachmentData;

    // Mock EmailLog::create and its returned instance
    $mockEmailLog = Mockery::mock(EmailLog::class)->makePartial();
    $mockEmailLog->id = $emailLogId;
    $mockEmailLog->shouldReceive('save')->once();
    $mockEmailLog->token = null;

    // FIX: Use 'overload:' for concrete classes
    Mockery::mock('overload:' . EmailLog::class)
        ->shouldReceive('create')
        ->once()
        ->with([
            'from' => $attachmentTestData['from'],
            'to' => $attachmentTestData['to'],
            'subject' => $attachmentTestData['subject'],
            'body' => $attachmentTestData['body'],
            'mailable_type' => Payment::class,
            'mailable_id' => $attachmentTestData['payment']['id'],
        ])
        ->andReturn($mockEmailLog);

    // Mock the Mailable chain methods
    $finalMailableMock = Mockery::mock(Mailable::class); // Represents the Mailable returned by markdown
    $finalMailableMock->shouldReceive('attachData')
        ->once()
        ->with('PDF_RAW_DATA', $attachmentTestData['payment']['payment_number'].'.pdf');

    $mail = Mockery::mock(SendPaymentMail::class, [$attachmentTestData])->makePartial();
    $mail->shouldReceive('from')
        ->once()
        ->with($attachmentTestData['from'], $expectedFromName)
        ->andReturnSelf();
    $mail->shouldReceive('subject')
        ->once()
        ->with($attachmentTestData['subject'])
        ->andReturnSelf();

    // FIX: Correct markdown arguments
    $mail->shouldReceive('markdown')
        ->once()
        ->with('emails.send.payment', ['data' => Mockery::subset([
            'from' => $attachmentTestData['from'],
            'to' => $attachmentTestData['to'],
            'subject' => $attachmentTestData['subject'],
            'body' => $attachmentTestData['body'],
            'payment' => [
                'id' => $attachmentTestData['payment']['id'],
                'payment_number' => $attachmentTestData['payment']['payment_number'],
            ],
            'attach' => ['data' => $mockAttachmentData], // Ensure the mock attachment data is passed
            'url' => $expectedUrl,
        ])])
        ->andReturn($finalMailableMock);


    $result = $mail->build();

    expect($result)->toBe($finalMailableMock);
    expect($mockEmailLog->token)->toBe($encodedToken);
    expect($mail->data['url'])->toBe($expectedUrl);
});

// Test edge case where config('mail.from.name') returns null or empty string
test('build method handles missing mail.from.name config', function () use ($commonTestData) {
    $emailLogId = 300;
    $encodedToken = "HASHED_$emailLogId";
    $expectedUrl = "http://test-app.com/payment/$encodedToken";
    $expectedFromName = null; // Expect null if config is missing

    // Override the config mock for this specific test case.
    // The `beforeEach` mock will be present, but this re-call modifies its expectations.
    // This `alias:` mock here will take precedence for the specific `with` call.
    Mockery::mock('alias:Illuminate\Support\Facades\Config')
        ->shouldReceive('get')
        ->once()
        ->with('mail.from.name')
        ->andReturn(null);

    $mockEmailLog = Mockery::mock(EmailLog::class)->makePartial();
    $mockEmailLog->id = $emailLogId;
    $mockEmailLog->shouldReceive('save')->once();
    $mockEmailLog->token = null;

    // FIX: Use 'overload:' for concrete classes
    Mockery::mock('overload:' . EmailLog::class)
        ->shouldReceive('create')
        ->once()
        ->andReturn($mockEmailLog);

    $finalMailableMock = Mockery::mock(Mailable::class);
    $finalMailableMock->shouldNotReceive('attachData');

    $mail = Mockery::mock(SendPaymentMail::class, [$commonTestData])->makePartial();
    $mail->shouldReceive('from')
        ->once()
        ->with($commonTestData['from'], $expectedFromName) // Expect null for from name
        ->andReturnSelf();
    $mail->shouldReceive('subject')
        ->once()
        ->with($commonTestData['subject'])
        ->andReturnSelf();
    $mail->shouldReceive('markdown')
        ->once()
        ->andReturn($finalMailableMock); // Simplified assertion for markdown args, focus is on 'from' name

    $result = $mail->build();

    expect($result)->toBe($finalMailableMock);
    expect($mockEmailLog->token)->toBe($encodedToken);
    expect($mail->data['url'])->toBe($expectedUrl);
});

test('build method throws error if critical data is missing (e.g., payment id)', function () use ($commonTestData) {
    $incompleteData = $commonTestData;
    unset($incompleteData['payment']['id']); // Missing payment ID

    // FIX: Use 'overload:' for concrete classes
    Mockery::mock('overload:' . EmailLog::class)
        ->shouldReceive('create')
        ->once()
        ->with([
            'from' => $incompleteData['from'],
            'to' => $incompleteData['to'],
            'subject' => $incompleteData['subject'],
            'body' => $incompleteData['body'],
            'mailable_type' => Payment::class,
            'mailable_id' => null, // Expect null here due to missing id
        ])
        ->andThrow(new \Exception('Simulated database error for missing payment ID')); // Simulate database error

    $mail = Mockery::mock(SendPaymentMail::class, [$incompleteData])->makePartial();

    // Expect an exception from the build method due to the mocked EmailLog::create
    expect(fn() => $mail->build())->toThrow(\Exception::class, 'Simulated database error for missing payment ID');
});

test('build method throws error if essential data like "from" is missing', function () use ($commonTestData) {
    $incompleteData = $commonTestData;
    unset($incompleteData['from']); // Missing 'from'

    // FIX: Use 'overload:' for concrete classes
    Mockery::mock('overload:' . EmailLog::class)
        ->shouldReceive('create')
        ->once()
        ->with([
            'from' => null, // Expect null for 'from'
            'to' => $incompleteData['to'],
            'subject' => $incompleteData['subject'],
            'body' => $incompleteData['body'],
            'mailable_type' => Payment::class,
            'mailable_id' => $incompleteData['payment']['id'],
        ])
        ->andThrow(new \Exception('Simulated database error for missing sender'));

    $mail = new SendPaymentMail($incompleteData); // No partial mock needed for the exception check here

    expect(fn() => $mail->build())->toThrow(\Exception::class, 'Simulated database error for missing sender');
});
```