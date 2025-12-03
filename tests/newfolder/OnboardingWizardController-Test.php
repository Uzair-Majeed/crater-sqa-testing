<?php

beforeEach(function () {
    Mockery::close();
});

test('getStep returns profile_complete 0 if database_created file does not exist', function () {
    // Arrange
    Illuminate\Support\Facades\Storage::shouldReceive('disk')
        ->with('local')
        ->andReturnSelf();

    Illuminate\Support\Facades\Storage::shouldReceive('has')
        ->with('database_created')
        ->andReturn(false); // Simulate file not existing

    // Fix: Mockery alias for static methods on Eloquent model
    Mockery::mock('alias:' . Crater\Models\Setting::class);

    // We don't expect Setting::getSetting to be called in this branch
    Crater\Models\Setting::shouldNotReceive('getSetting');

    $controller = new Crater\Http\Controllers\V1\Installation\OnboardingWizardController();
    $request = new Illuminate\Http\Request();

    // Act
    $response = $controller->getStep($request);

    // Assert
    $response->assertExactJson([
        'profile_complete' => 0,
    ]);
});

test('getStep returns profile_complete from settings if database_created file exists', function () {
    // Arrange
    $expectedProfileComplete = 'STEP_1_COMPLETED';

    Illuminate\Support\Facades\Storage::shouldReceive('disk')
        ->with('local')
        ->andReturnSelf();

    Illuminate\Support\Facades\Storage::shouldReceive('has')
        ->with('database_created')
        ->andReturn(true); // Simulate file existing

    // Fix: Mockery alias for static methods on Eloquent model
    Mockery::mock('alias:' . Crater\Models\Setting::class);

    Crater\Models\Setting::shouldReceive('getSetting')
        ->with('profile_complete')
        ->once()
        ->andReturn($expectedProfileComplete);

    $controller = new Crater\Http\Controllers\V1\Installation\OnboardingWizardController();
    $request = new Illuminate\Http\Request();

    // Act
    $response = $controller->getStep($request);

    // Assert
    $response->assertExactJson([
        'profile_complete' => $expectedProfileComplete,
    ]);
});

test('updateStep returns completed status if profile is already completed', function () {
    // Arrange
    $completedStatus = 'COMPLETED';

    // Fix: Mockery alias for static methods on Eloquent model
    Mockery::mock('alias:' . Crater\Models\Setting::class);

    Crater\Models\Setting::shouldReceive('getSetting')
        ->with('profile_complete')
        ->once()
        ->andReturn($completedStatus);

    // Ensure setSetting is not called as the profile is already completed
    Crater\Models\Setting::shouldNotReceive('setSetting');

    $controller = new Crater\Http\Controllers\V1\Installation\OnboardingWizardController();
    $request = new Illuminate\Http\Request(['profile_complete' => 'ANY_NEW_STEP_VALUE_SHOULD_BE_IGNORED']);

    // Act
    $response = $controller->updateStep($request);

    // Assert
    $response->assertExactJson([
        'profile_complete' => $completedStatus,
    ]);
});

test('updateStep updates profile_complete setting if not already completed', function () {
    // Arrange
    $initialStatus = 'STEP_1_COMPLETED';
    $newStatus = 'STEP_2_COMPLETED';

    // Fix: Mockery alias for static methods on Eloquent model
    Mockery::mock('alias:' . Crater\Models\Setting::class);

    Crater\Models\Setting::shouldReceive('getSetting')
        ->with('profile_complete')
        ->ordered()
        ->once()
        ->andReturn($initialStatus); // First call to check current status

    Crater\Models\Setting::shouldReceive('setSetting')
        ->with('profile_complete', $newStatus)
        ->once(); // Expect setSetting to be called

    Crater\Models\Setting::shouldReceive('getSetting')
        ->with('profile_complete')
        ->ordered()
        ->once()
        ->andReturn($newStatus); // Second call to get the updated status for response

    $controller = new Crater\Http\Controllers\V1\Installation\OnboardingWizardController();
    $request = new Illuminate\Http\Request(['profile_complete' => $newStatus]);

    // Act
    $response = $controller->updateStep($request);

    // Assert
    $response->assertExactJson([
        'profile_complete' => $newStatus,
    ]);
});

test('updateStep updates profile_complete to empty string if request parameter is empty', function () {
    // Arrange
    $initialStatus = 'STEP_1_COMPLETED';
    $newStatus = ''; // Simulating an empty string from request

    // Fix: Mockery alias for static methods on Eloquent model
    Mockery::mock('alias:' . Crater\Models\Setting::class);

    Crater\Models\Setting::shouldReceive('getSetting')
        ->with('profile_complete')
        ->ordered()
        ->once()
        ->andReturn($initialStatus); // First call to check current status

    Crater\Models\Setting::shouldReceive('setSetting')
        ->with('profile_complete', $newStatus)
        ->once(); // Expect setSetting to be called

    Crater\Models\Setting::shouldReceive('getSetting')
        ->with('profile_complete')
        ->ordered()
        ->once()
        ->andReturn($newStatus); // Second call to get the updated status for response

    $controller = new Crater\Http\Controllers\V1\Installation\OnboardingWizardController();
    $request = new Illuminate\Http\Request(['profile_complete' => $newStatus]);

    // Act
    $response = $controller->updateStep($request);

    // Assert
    $response->assertExactJson([
        'profile_complete' => $newStatus,
    ]);
});

test('updateStep handles missing profile_complete in request when not completed', function () {
    // Arrange
    $initialStatus = 'STEP_1_COMPLETED';
    $newStatus = null; // When 'profile_complete' is not in the request, $request->profile_complete evaluates to null.

    // Fix: Mockery alias for static methods on Eloquent model
    Mockery::mock('alias:' . Crater\Models\Setting::class);

    Crater\Models\Setting::shouldReceive('getSetting')
        ->with('profile_complete')
        ->ordered()
        ->once()
        ->andReturn($initialStatus); // First call to check current status

    Crater\Models\Setting::shouldReceive('setSetting')
        ->with('profile_complete', $newStatus) // Expect setSetting to be called with null
        ->once();

    Crater\Models\Setting::shouldReceive('getSetting')
        ->with('profile_complete')
        ->ordered()
        ->once()
        ->andReturn($newStatus); // Second call to get the updated status for response

    $controller = new Crater\Http\Controllers\V1\Installation\OnboardingWizardController();
    $request = new Illuminate\Http\Request([]); // Request without profile_complete parameter

    // Act
    $response = $controller->updateStep($request);

    // Assert
    $response->assertExactJson([
        'profile_complete' => $newStatus,
    ]);
});


afterEach(function () {
    Mockery::close();
});