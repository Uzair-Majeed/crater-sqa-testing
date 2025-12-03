<?php

use Crater\Mail\EstimateViewedMail;

test('constructor correctly assigns data property', function () {
    $dummyData = ['name' => 'John Doe', 'amount' => 100.50];
    $mail = new EstimateViewedMail($dummyData);

    expect($mail->data)->toBe($dummyData);
});

test('constructor handles empty array data', function () {
    $emptyData = [];
    $mail = new EstimateViewedMail($emptyData);
    expect($mail->data)->toBe($emptyData);
});

test('constructor handles null data', function () {
    $nullData = null;
    $mail = new EstimateViewedMail($nullData);
    expect($mail->data)->toBe($nullData);
});

test('build method configures mail with correct sender and view data', function () {
    $mockFromAddress = 'notifications@crater.app';
    $mockFromName = 'Crater App';
    $dummyMailData = ['estimate_number' => 'EST-001', 'client_name' => 'Acme Corp'];

    // Mock the config helper function to control its return values
    $this->mock('config')
        ->shouldReceive('get')
        ->with('mail.from.address')
        ->andReturn($mockFromAddress)
        ->once();

    $this->mock('config')
        ->shouldReceive('get')
        ->with('mail.from.name')
        ->andReturn($mockFromName)
        ->once();

    // Create a spy for the Mailable instance itself to assert method calls
    // We use a spy to observe calls on 'from' and 'markdown' methods which are chained
    $mail = Mockery::spy(new EstimateViewedMail($dummyMailData));

    // Set expectations for chained methods, ensuring they return the spy for continued chaining
    $mail->shouldReceive('from')
        ->with($mockFromAddress, $mockFromName)
        ->andReturn($mail) // Crucial for method chaining
        ->once();

    // The markdown method in the original code uses ['data', $this->data]
    // We test this exact structure.
    $mail->shouldReceive('markdown')
        ->with('emails.viewed.estimate', ['data', $dummyMailData])
        ->andReturn($mail) // Crucial for method chaining
        ->once();

    // Call the build method
    $result = $mail->build();

    // Assert that the result is the mail instance itself, verifying chaining
    expect($result)->toBe($mail);

    // Verify all expectations on the spy were met
    $mail->shouldHaveReceived('from');
    $mail->shouldHaveReceived('markdown');
});

test('build method uses different config values for mail settings', function () {
    $customFromAddress = 'custom@example.com';
    $customFromName = 'Custom Sender';
    $customMailData = ['product_name' => 'Widget', 'quantity' => 5];

    $this->mock('config')
        ->shouldReceive('get')
        ->with('mail.from.address')
        ->andReturn($customFromAddress)
        ->once();

    $this->mock('config')
        ->shouldReceive('get')
        ->with('mail.from.name')
        ->andReturn($customFromName)
        ->once();

    $mail = Mockery::spy(new EstimateViewedMail($customMailData));

    $mail->shouldReceive('from')
        ->with($customFromAddress, $customFromName)
        ->andReturn($mail)
        ->once();

    $mail->shouldReceive('markdown')
        ->with('emails.viewed.estimate', ['data', $customMailData])
        ->andReturn($mail)
        ->once();

    $mail->build();

    $mail->shouldHaveReceived('from');
    $mail->shouldHaveReceived('markdown');
});




afterEach(function () {
    Mockery::close();
});
