<?php

use Crater\Http\Controllers\V1\Admin\Settings\MailConfigurationController;
use Crater\Http\Requests\MailEnvironmentRequest;
use Crater\Mail\TestMail;
use Crater\Models\Setting;
use Crater\Space\EnvironmentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
uses(\Mockery::class);
use Illuminate\Validation\ValidationException;
use Illuminate\Support\MessageBag;
use Illuminate\Contracts\Validation\Validator;

// Before each test, ensure mocks are clean.
beforeEach(function () {
    Mockery::close();
});

// Test for the constructor
test('constructor sets environment manager correctly', function () {
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    $controller = new MailConfigurationController($environmentManager);

    expect($controller)->environmentManager->toBe($environmentManager);
});

// Test for saveMailEnvironment
test('saveMailEnvironment updates environment and setting when profile is not complete', function () {
    $request = Mockery::mock(MailEnvironmentRequest::class);
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    
    // Create a partial mock of the controller to mock inherited/trait methods
    $controller = Mockery::mock(MailConfigurationController::class, [$environmentManager])->makePartial();
    $controller->shouldReceive('authorize')->with('manage email config')->once();

    // Mock Setting::getSetting
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('getSetting')
        ->with('profile_complete')
        ->once()
        ->andReturn('NOT_COMPLETED'); // Case: profile not complete

    // Mock EnvironmentManager::saveMailVariables
    $environmentManager->shouldReceive('saveMailVariables')
        ->with($request)
        ->once()
        ->andReturn(['success' => true, 'message' => 'Mail variables saved']);

    // Mock Setting::setSetting
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('setSetting')
        ->with('profile_complete', 4)
        ->once(); // Should be called because profile is not 'COMPLETED'

    $response = $controller->saveMailEnvironment($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true, 'message' => 'Mail variables saved']);
});

test('saveMailEnvironment updates environment but not setting when profile is complete', function () {
    $request = Mockery::mock(MailEnvironmentRequest::class);
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    
    // Create a partial mock of the controller to mock inherited/trait methods
    $controller = Mockery::mock(MailConfigurationController::class, [$environmentManager])->makePartial();
    $controller->shouldReceive('authorize')->with('manage email config')->once();

    // Mock Setting::getSetting
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('getSetting')
        ->with('profile_complete')
        ->once()
        ->andReturn('COMPLETED'); // Case: profile complete

    // Mock EnvironmentManager::saveMailVariables
    $environmentManager->shouldReceive('saveMailVariables')
        ->with($request)
        ->once()
        ->andReturn(['success' => true, 'message' => 'Mail variables saved']);

    // Mock Setting::setSetting - should NOT be called
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('setSetting')
        ->with('profile_complete', 4)
        ->never();

    $response = $controller->saveMailEnvironment($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true, 'message' => 'Mail variables saved']);
});

// Test for getMailEnvironment
test('getMailEnvironment returns mail configuration data', function () {
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    
    // Create a partial mock of the controller to mock inherited/trait methods
    $controller = Mockery::mock(MailConfigurationController::class, [$environmentManager])->makePartial();
    $controller->shouldReceive('authorize')->with('manage email config')->once();

    // Set config values
    Config::set('mail.driver', 'smtp');
    Config::set('mail.host', 'smtp.mailtrap.io');
    Config::set('mail.port', '2525');
    Config::set('mail.username', 'testuser');
    Config::set('mail.password', 'testpass');
    Config::set('mail.encryption', 'tls');
    Config::set('mail.from.name', 'Crater App');
    Config::set('mail.from.address', 'hello@crater.app');
    Config::set('services.mailgun.endpoint', 'api.mailgun.net');
    Config::set('services.mailgun.domain', 'mg.example.com');
    Config::set('services.mailgun.secret', 'mailgun_secret_key');
    Config::set('services.ses.key', 'ses_key');
    Config::set('services.ses.secret', 'ses_secret');

    $response = $controller->getMailEnvironment();

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)[
        'mail_driver' => 'smtp',
        'mail_host' => 'smtp.mailtrap.io',
        'mail_port' => '2525',
        'mail_username' => 'testuser',
        'mail_password' => 'testpass',
        'mail_encryption' => 'tls',
        'from_name' => 'Crater App',
        'from_mail' => 'hello@crater.app',
        'mail_mailgun_endpoint' => 'api.mailgun.net',
        'mail_mailgun_domain' => 'mg.example.com',
        'mail_mailgun_secret' => 'mailgun_secret_key',
        'mail_ses_key' => 'ses_key',
        'mail_ses_secret' => 'ses_secret',
    ]);
});

test('getMailEnvironment returns null for unset config values', function () {
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    
    // Create a partial mock of the controller to mock inherited/trait methods
    $controller = Mockery::mock(MailConfigurationController::class, [$environmentManager])->makePartial();
    $controller->shouldReceive('authorize')->with('manage email config')->once();

    // Unset all relevant config values
    Config::set('mail.driver', null);
    Config::set('mail.host', null);
    Config::set('mail.port', null);
    Config::set('mail.username', null);
    Config::set('mail.password', null);
    Config::set('mail.encryption', null);
    Config::set('mail.from.name', null);
    Config::set('mail.from.address', null);
    Config::set('services.mailgun.endpoint', null);
    Config::set('services.mailgun.domain', null);
    Config::set('services.mailgun.secret', null);
    Config::set('services.ses.key', null);
    Config::set('services.ses.secret', null);

    $response = $controller->getMailEnvironment();

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)[
        'mail_driver' => null,
        'mail_host' => null,
        'mail_port' => null,
        'mail_username' => null,
        'mail_password' => null,
        'mail_encryption' => null,
        'from_name' => null,
        'from_mail' => null,
        'mail_mailgun_endpoint' => null,
        'mail_mailgun_domain' => null,
        'mail_mailgun_secret' => null,
        'mail_ses_key' => null,
        'mail_ses_secret' => null,
    ]);
});

// Test for getMailDrivers
test('getMailDrivers returns list of mail drivers', function () {
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    
    // Create a partial mock of the controller to mock inherited/trait methods
    $controller = Mockery::mock(MailConfigurationController::class, [$environmentManager])->makePartial();
    $controller->shouldReceive('authorize')->with('manage email config')->once();

    $response = $controller->getMailDrivers();

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual([
        'smtp',
        'mail',
        'sendmail',
        'mailgun',
        'ses',
    ]);
});

// Test for testEmailConfig
test('testEmailConfig sends an email successfully', function () {
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    
    // Create a partial mock of the controller to mock inherited/trait methods
    $controller = Mockery::mock(MailConfigurationController::class, [$environmentManager])->makePartial();
    $controller->shouldReceive('authorize')->with('manage email config')->once();

    $request = Mockery::mock(Request::class);
    $request->to = 'test@example.com';
    $request->subject = 'Test Subject';
    $request->message = 'Test Message';

    // Mock the validate method (comes from ValidatesRequests trait)
    $controller->shouldReceive('validate')
        ->with($request, [
            'to' => 'required|email',
            'subject' => 'required',
            'message' => 'required',
        ])
        ->once()
        ->andReturn(true); // Simulate successful validation

    // Mock the Mail facade to chain `to()->send()`
    $mailerMock = Mockery::mock(\Illuminate\Mail\Mailer::class); // Represents the Mailer instance after `Mail::to()`
    $mailerMock->shouldReceive('send')
        ->once()
        ->with(Mockery::on(function ($mailable) use ($request) {
            // Assert the TestMail instance
            expect($mailable)->toBeInstanceOf(TestMail::class);
            expect($mailable->subject)->toEqual($request->subject);
            expect($mailable->message)->toEqual($request->message);
            return true;
        }));

    // Mock Mail::to() to return our mock Mailer instance
    Mockery::mock('alias:' . Mail::class)
        ->shouldReceive('to')
        ->once()
        ->with($request->to)
        ->andReturn($mailerMock);

    $response = $controller->testEmailConfig($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true]);
});

test('testEmailConfig throws validation exception if request is invalid', function () {
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    
    // Create a partial mock of the controller to mock inherited/trait methods
    $controller = Mockery::mock(MailConfigurationController::class, [$environmentManager])->makePartial();
    $controller->shouldReceive('authorize')->with('manage email config')->once();

    $request = Mockery::mock(Request::class);
    $request->to = 'invalid-email'; // Invalid email
    $request->subject = 'Test Subject';
    $request->message = 'Test Message';

    // Mock the validate method to throw a ValidationException
    $controller->shouldReceive('validate')
        ->with($request, [
            'to' => 'required|email',
            'subject' => 'required',
            'message' => 'required',
        ])
        ->once()
        ->andThrow(new ValidationException(
            Mockery::mock(Validator::class, function ($mock) {
                $mock->shouldReceive('errors')->andReturn(new MessageBag(['to' => ['The to field must be a valid email address.']]));
                $mock->shouldReceive('failed')->andReturn(['to' => ['email' => []]]);
            })
        ));

    // Mail facade should not be called if validation fails
    Mockery::mock('alias:' . Mail::class)
        ->shouldNotReceive('to');

    // Expect a ValidationException to be thrown
    $this->expectException(ValidationException::class);

    $controller->testEmailConfig($request);
});
