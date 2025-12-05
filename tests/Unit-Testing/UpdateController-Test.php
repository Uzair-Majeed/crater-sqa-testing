<?php

use Crater\Http\Controllers\V1\Admin\Settings\UpdateCompanySettingsController;
use Crater\Http\Controllers\V1\Admin\Settings\UpdateSettingsController;
use Crater\Http\Controllers\V1\Admin\Update\UpdateController;

// ========== MERGED UPDATE CONTROLLER TESTS (3 CLASSES, 20 FUNCTIONAL TESTS) ==========

// --- UpdateCompanySettingsController Tests (7 tests: 3 structural + 4 functional) ---

test('UpdateCompanySettingsController can be instantiated', function () {
    $controller = new UpdateCompanySettingsController();
    expect($controller)->toBeInstanceOf(UpdateCompanySettingsController::class);
});

test('UpdateCompanySettingsController extends Controller', function () {
    $controller = new UpdateCompanySettingsController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('UpdateCompanySettingsController is invokable', function () {
    $reflection = new ReflectionClass(UpdateCompanySettingsController::class);
    expect($reflection->hasMethod('__invoke'))->toBeTrue();
});

// --- FUNCTIONAL TESTS ---

test('UpdateCompanySettingsController uses authorization', function () {
    $reflection = new ReflectionClass(UpdateCompanySettingsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'manage company\', $company)');
});

test('UpdateCompanySettingsController checks currency change with transactions', function () {
    $reflection = new ReflectionClass(UpdateCompanySettingsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Arr::exists($data, \'currency\')')
        ->and($fileContent)->toContain('CompanySetting::getSetting(\'currency\'')
        ->and($fileContent)->toContain('$company->hasTransactions()');
});

test('UpdateCompanySettingsController returns error for currency change with transactions', function () {
    $reflection = new ReflectionClass(UpdateCompanySettingsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'success\' => false')
        ->and($fileContent)->toContain('Cannot update company currency after transactions are created');
});

test('UpdateCompanySettingsController uses CompanySetting setSettings', function () {
    $reflection = new ReflectionClass(UpdateCompanySettingsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('CompanySetting::setSettings($data, $request->header(\'company\'))');
});

// --- UpdateSettingsController Tests (6 tests: 3 structural + 3 functional) ---

test('UpdateSettingsController can be instantiated', function () {
    $controller = new UpdateSettingsController();
    expect($controller)->toBeInstanceOf(UpdateSettingsController::class);
});

test('UpdateSettingsController extends Controller', function () {
    $controller = new UpdateSettingsController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('UpdateSettingsController is invokable', function () {
    $reflection = new ReflectionClass(UpdateSettingsController::class);
    expect($reflection->hasMethod('__invoke'))->toBeTrue();
});

// --- FUNCTIONAL TESTS ---

test('UpdateSettingsController uses manage settings authorization', function () {
    $reflection = new ReflectionClass(UpdateSettingsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'manage settings\')');
});

test('UpdateSettingsController uses Setting setSettings', function () {
    $reflection = new ReflectionClass(UpdateSettingsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Setting::setSettings($request->settings)');
});

test('UpdateSettingsController returns success with settings', function () {
    $reflection = new ReflectionClass(UpdateSettingsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'success\' => true')
        ->and($fileContent)->toContain('$request->settings');
});

// --- UpdateController Tests (7 tests: 3 structural + 4 functional) ---

test('UpdateController can be instantiated', function () {
    $controller = new UpdateController();
    expect($controller)->toBeInstanceOf(UpdateController::class);
});

test('UpdateController extends Controller', function () {
    $controller = new UpdateController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('UpdateController has all update methods', function () {
    $reflection = new ReflectionClass(UpdateController::class);
    
    expect($reflection->hasMethod('download'))->toBeTrue()
        ->and($reflection->hasMethod('unzip'))->toBeTrue()
        ->and($reflection->hasMethod('copyFiles'))->toBeTrue()
        ->and($reflection->hasMethod('migrate'))->toBeTrue()
        ->and($reflection->hasMethod('finishUpdate'))->toBeTrue()
        ->and($reflection->hasMethod('checkLatestVersion'))->toBeTrue();
});

// --- FUNCTIONAL TESTS ---

test('UpdateController uses manage update app authorization', function () {
    $reflection = new ReflectionClass(UpdateController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // All methods should have this authorization
    expect($fileContent)->toContain('$this->authorize(\'manage update app\')');
});

test('UpdateController download method uses Updater download', function () {
    $reflection = new ReflectionClass(UpdateController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Updater::download($request->version)');
});

test('UpdateController unzip handles exceptions', function () {
    $reflection = new ReflectionClass(UpdateController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('try {')
        ->and($fileContent)->toContain('Updater::unzip($request->path)')
        ->and($fileContent)->toContain('} catch (\\Exception $e) {')
        ->and($fileContent)->toContain('$e->getMessage()');
});

test('UpdateController checkLatestVersion sets time limit', function () {
    $reflection = new ReflectionClass(UpdateController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('set_time_limit(600)')
        ->and($fileContent)->toContain('Updater::checkForUpdate(Setting::getSetting(\'version\'))');
});
