<?php

use Crater\Mail\EstimateViewedMail;
use Illuminate\Support\Facades\Config;

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

    // Use Config::shouldReceive to properly mock config() calls with default argument
    Config::shouldReceive('get')
        ->with('mail.from.address', null)
        ->andReturn($mockFromAddress)
        ->once();

    Config::shouldReceive('get')
        ->with('mail.from.name', null)
        ->andReturn($mockFromName)
        ->once();

    // Spy the Mailable for method chains
    $mail = Mockery::spy(EstimateViewedMail::class)->makePartial();
    $mail->data = $dummyMailData;

    $mail->shouldAllowMockingProtectedMethods();

    $mail->shouldReceive('from')
        ->with($mockFromAddress, $mockFromName)
        ->andReturn($mail)
        ->once();

    $mail->shouldReceive('markdown')
        ->with('emails.viewed.estimate', ['data', $dummyMailData])
        ->andReturn($mail)
        ->once();

    $result = $mail->build();

    expect($result)->toBe($mail);

    $mail->shouldHaveReceived('from');
    $mail->shouldHaveReceived('markdown');
});

test('build method uses different config values for mail settings', function () {
    $customFromAddress = 'custom@example.com';
    $customFromName = 'Custom Sender';
    $customMailData = ['product_name' => 'Widget', 'quantity' => 5];

    Config::shouldReceive('get')
        ->with('mail.from.address', null)
        ->andReturn($customFromAddress)
        ->once();

    Config::shouldReceive('get')
        ->with('mail.from.name', null)
        ->andReturn($customFromName)
        ->once();

    $mail = Mockery::spy(EstimateViewedMail::class)->makePartial();
    $mail->data = $customMailData;

    $mail->shouldAllowMockingProtectedMethods();

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
    Config::spy()->shouldReceive('get')->zeroInteractions();
});