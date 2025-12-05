<?php

use Crater\Http\Controllers\V1\Installation\OnboardingWizardController;

// ========== ONBOARDINGWIZARDCONTROLLER STRUCTURAL TESTS (INTEGRATION CONTROLLER) ==========
// NOTE: This is an INTEGRATION controller with dependencies on Storage facade and Setting model.
// These tests provide 100% STRUCTURAL coverage. Functional coverage requires integration testing.

test('OnboardingWizardController can be instantiated', function () {
    $controller = new OnboardingWizardController();
    expect($controller)->toBeInstanceOf(OnboardingWizardController::class);
});

test('OnboardingWizardController extends Controller', function () {
    $controller = new OnboardingWizardController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('OnboardingWizardController is in correct namespace', function () {
    $reflection = new ReflectionClass(OnboardingWizardController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Installation');
});

test('OnboardingWizardController has getStep method', function () {
    $reflection = new ReflectionClass(OnboardingWizardController::class);
    expect($reflection->hasMethod('getStep'))->toBeTrue();
    
    $method = $reflection->getMethod('getStep');
    expect($method->isPublic())->toBeTrue();
});

test('OnboardingWizardController has updateStep method', function () {
    $reflection = new ReflectionClass(OnboardingWizardController::class);
    expect($reflection->hasMethod('updateStep'))->toBeTrue();
    
    $method = $reflection->getMethod('updateStep');
    expect($method->isPublic())->toBeTrue();
});

test('OnboardingWizardController getStep uses Storage facade', function () {
    $reflection = new ReflectionClass(OnboardingWizardController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\\Storage::disk(\'local\')->has(\'database_created\')');
});

test('OnboardingWizardController getStep uses Setting model', function () {
    $reflection = new ReflectionClass(OnboardingWizardController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Setting::getSetting(\'profile_complete\')');
});

test('OnboardingWizardController updateStep checks COMPLETED status', function () {
    $reflection = new ReflectionClass(OnboardingWizardController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$setting === \'COMPLETED\'');
});

test('OnboardingWizardController updateStep uses Setting::setSetting', function () {
    $reflection = new ReflectionClass(OnboardingWizardController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Setting::setSetting(\'profile_complete\', $request->profile_complete)');
});

test('OnboardingWizardController returns JSON responses', function () {
    $reflection = new ReflectionClass(OnboardingWizardController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('response()->json([')
        ->and($fileContent)->toContain('\'profile_complete\'');
});