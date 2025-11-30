<?php

use Crater\Events\UpdateFinished;
use Crater\Listeners\Updates\v2\Version210;
use Crater\Models\CompanySetting;
use Crater\Models\Setting;
use Illuminate\Support\Facades\Auth;
uses(\Mockery::class);

// Use Laravel's TestCase for automatic Mockery setup and teardown,
// and other Laravel testing utilities.

test('it_can_be_instantiated', function () {
    $listener = new Version210();
    expect($listener)->toBeInstanceOf(Version210::class);
});

test('handle_returns_early_if_listener_already_fired', function () {
    // Partial mock of Version210 to control the inherited isListenerFired method
    $listener = Mockery::mock(Version210::class)->makePartial();
    $listener->shouldAllowMockingProtectedMethods() // Required for mocking protected/private methods on partial mocks
             ->shouldReceive('isListenerFired')
             ->once()
             ->andReturn(true);

    // Expect the private method addAutoGenerateSettings NOT to be called
    $listener->shouldNotReceive('addAutoGenerateSettings');

    // Mock the Setting model alias to ensure its static method setSetting is NOT called
    Mockery::mock('alias:' . Setting::class)
        ->shouldNotReceive('setSetting');

    $event = new UpdateFinished();
    $listener->handle($event);
});

test('handle_adds_auto_generate_settings_and_updates_version_when_listener_not_fired', function () {
    $mockCompanyId = 99;

    // Mock Auth facade and its chain (Auth::user()->company->id)
    $mockCompany = new class { public $id; }; // Anonymous class for company
    $mockCompany->id = $mockCompanyId;

    $mockUser = new class { public $company; }; // Anonymous class for user
    $mockUser->company = $mockCompany;

    Auth::shouldReceive('user')->once()->andReturn($mockUser);

    // Partial mock of Version210 to control the inherited isListenerFired method
    // and to allow addAutoGenerateSettings to be called and executed.
    $listener = Mockery::mock(Version210::class)->makePartial();
    $listener->shouldAllowMockingProtectedMethods()
             ->shouldReceive('isListenerFired')
             ->once()
             ->andReturn(false);
    
    // We expect addAutoGenerateSettings to be called and its logic to execute (passthru).
    // The assertions for its side effects (CompanySetting::setSetting) are below.
    $listener->shouldReceive('addAutoGenerateSettings')->once()->passthru();

    // Mock Setting model alias to expect the version update call
    Mockery::mock('alias:' . Setting::class)
        ->shouldReceive('setSetting')
        ->once()
        ->with('version', Version210::VERSION);

    // Mock CompanySetting model alias for the addAutoGenerateSettings calls
    $expectedSettings = [
        'invoice_auto_generate' => 'YES',
        'invoice_prefix' => 'INV',
        'estimate_prefix' => 'EST',
        'estimate_auto_generate' => 'YES',
        'payment_prefix' => 'PAY',
        'payment_auto_generate' => 'YES',
    ];

    $companySettingMock = Mockery::mock('alias:' . CompanySetting::class);
    foreach ($expectedSettings as $key => $value) {
        $companySettingMock->shouldReceive('setSetting')
                           ->once()
                           ->with($key, $value, $mockCompanyId);
    }

    $event = new UpdateFinished();
    $listener->handle($event);
});

test('add_auto_generate_settings_stores_correct_values_for_authenticated_company_via_reflection', function () {
    $mockCompanyId = 123;

    // Mock Auth facade and its chain (Auth::user()->company->id)
    $mockCompany = new class { public $id; };
    $mockCompany->id = $mockCompanyId;

    $mockUser = new class { public $company; };
    $mockUser->company = $mockCompany;

    Auth::shouldReceive('user')->once()->andReturn($mockUser);

    // Mock CompanySetting model alias for the calls
    $expectedSettings = [
        'invoice_auto_generate' => 'YES',
        'invoice_prefix' => 'INV',
        'estimate_prefix' => 'EST',
        'estimate_auto_generate' => 'YES',
        'payment_prefix' => 'PAY',
        'payment_auto_generate' => 'YES',
    ];

    $companySettingMock = Mockery::mock('alias:' . CompanySetting::class);
    foreach ($expectedSettings as $key => $value) {
        $companySettingMock->shouldReceive('setSetting')
                           ->once()
                           ->with($key, $value, $mockCompanyId);
    }

    $listener = new Version210();

    // Use reflection to call the private method addAutoGenerateSettings
    $reflection = new ReflectionMethod($listener, 'addAutoGenerateSettings');
    $reflection->setAccessible(true); // Make private method accessible
    $reflection->invoke($listener); // Call the private method
});
