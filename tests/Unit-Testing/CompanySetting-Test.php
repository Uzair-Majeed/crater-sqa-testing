<?php

use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
uses(\Mockery::class);

// Helper function to create a mock company setting instance
function createMockCompanySetting(array $attributes = [])
{
    $mock = Mockery::mock(CompanySetting::class)->makePartial();
    foreach ($attributes as $key => $value) {
        $mock->{$key} = $value;
    }
    return $mock;
}

beforeEach(function () {
    // Clear mocks between tests
    Mockery::close();
});

test('company relationship returns belongsTo relationship instance', function () {
    $companySetting = new CompanySetting();
    $relation = $companySetting->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Company::class);
});

test('scopeWhereCompany applies company_id constraint to the query builder', function () {
    $companyId = 1;

    // Mock the query builder
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('where')
        ->once()
        ->with('company_id', $companyId)
        ->andReturnSelf(); // Ensure it returns itself for chaining

    // Create a CompanySetting instance and call the scope
    $companySetting = new CompanySetting();
    $companySetting->scopeWhereCompany($mockBuilder, $companyId);
});

test('setSettings updates or creates multiple settings for a company', function () {
    $settings = [
        'setting_key_1' => 'value1',
        'setting_key_2' => 'value2',
        'setting_key_3' => 'value3',
    ];
    $companyId = 1;

    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);

    // Expect updateOrCreate to be called for each setting with correct arguments
    foreach ($settings as $key => $value) {
        $mockCompanySetting->shouldReceive('updateOrCreate')
            ->once()
            ->with(
                ['option' => $key, 'company_id' => $companyId],
                ['option' => $key, 'company_id' => $companyId, 'value' => $value]
            )
            ->andReturn(Mockery::mock(CompanySetting::class)); // Return a dummy instance
    }

    CompanySetting::setSettings($settings, $companyId);
});

test('setSettings does nothing when an empty settings array is provided', function () {
    $settings = [];
    $companyId = 1;

    // Ensure updateOrCreate is never called
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldNotReceive('updateOrCreate');

    CompanySetting::setSettings($settings, $companyId);
});

test('getAllSettings retrieves all settings for a given company', function () {
    $companyId = 1;
    $mockSettingsData = [
        ['option' => 'app_name', 'value' => 'CraterApp'],
        ['option' => 'currency', 'value' => 'USD'],
    ];

    // Create mock CompanySetting objects
    $mockSetting1 = createMockCompanySetting($mockSettingsData[0]);
    $mockSetting2 = createMockCompanySetting($mockSettingsData[1]);

    // Mock the query builder chain
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([$mockSetting1, $mockSetting2]));

    // Mock the static whereCompany scope
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('whereCompany')
        ->once()
        ->with($companyId)
        ->andReturn($mockBuilder);

    $result = CompanySetting::getAllSettings($companyId);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->toArray())->toEqual([
            'app_name' => 'CraterApp',
            'currency' => 'USD',
        ]);
});

test('getAllSettings returns an empty collection if no settings are found for the company', function () {
    $companyId = 1;

    // Mock the query builder chain to return an empty collection
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([]));

    // Mock the static whereCompany scope
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('whereCompany')
        ->once()
        ->with($companyId)
        ->andReturn($mockBuilder);

    $result = CompanySetting::getAllSettings($companyId);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toBeEmpty();
});

test('getSettings retrieves only the specified settings for a company', function () {
    $companyId = 1;
    $requestedSettings = ['app_name', 'timezone'];
    $mockSettingsData = [
        ['option' => 'app_name', 'value' => 'CraterApp'],
        ['option' => 'timezone', 'value' => 'UTC'],
    ];

    // Create mock CompanySetting objects
    $mockSetting1 = createMockCompanySetting($mockSettingsData[0]);
    $mockSetting2 = createMockCompanySetting($mockSettingsData[1]);

    // Mock the query builder chain for get()
    $mockBuilderGet = Mockery::mock(Builder::class);
    $mockBuilderGet->shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([$mockSetting1, $mockSetting2]));

    // Mock the query builder chain for whereCompany()
    $mockBuilderWhereCompany = Mockery::mock(Builder::class);
    $mockBuilderWhereCompany->shouldReceive('whereCompany')
        ->once()
        ->with($companyId)
        ->andReturn($mockBuilderGet);

    // Mock the static whereIn method
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('whereIn')
        ->once()
        ->with('option', $requestedSettings)
        ->andReturn($mockBuilderWhereCompany);

    $result = CompanySetting::getSettings($requestedSettings, $companyId);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->toArray())->toEqual([
            'app_name' => 'CraterApp',
            'timezone' => 'UTC',
        ]);
});

test('getSettings returns only existing specified settings and ignores non-existent ones', function () {
    $companyId = 1;
    $requestedSettings = ['app_name', 'non_existent_setting'];
    $mockSettingsData = [
        ['option' => 'app_name', 'value' => 'CraterApp'],
    ];

    // Create mock CompanySetting objects (only 'app_name' exists in mock data)
    $mockSetting1 = createMockCompanySetting($mockSettingsData[0]);

    // Mock the query builder chain to return only 'app_name'
    $mockBuilderGet = Mockery::mock(Builder::class);
    $mockBuilderGet->shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([$mockSetting1]));

    // Mock the query builder chain for whereCompany()
    $mockBuilderWhereCompany = Mockery::mock(Builder::class);
    $mockBuilderWhereCompany->shouldReceive('whereCompany')
        ->once()
        ->with($companyId)
        ->andReturn($mockBuilderGet);

    // Mock the static whereIn method
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('whereIn')
        ->once()
        ->with('option', $requestedSettings)
        ->andReturn($mockBuilderWhereCompany);

    $result = CompanySetting::getSettings($requestedSettings, $companyId);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->toArray())->toEqual([
            'app_name' => 'CraterApp',
        ]);
});

test('getSettings returns an empty collection if none of the specified settings are found', function () {
    $companyId = 1;
    $requestedSettings = ['non_existent_setting_1', 'non_existent_setting_2'];

    // Mock the query builder chain to return an empty collection
    $mockBuilderGet = Mockery::mock(Builder::class);
    $mockBuilderGet->shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([]));

    // Mock the query builder chain for whereCompany()
    $mockBuilderWhereCompany = Mockery::mock(Builder::class);
    $mockBuilderWhereCompany->shouldReceive('whereCompany')
        ->once()
        ->with($companyId)
        ->andReturn($mockBuilderGet);

    // Mock the static whereIn method
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('whereIn')
        ->once()
        ->with('option', $requestedSettings)
        ->andReturn($mockBuilderWhereCompany);

    $result = CompanySetting::getSettings($requestedSettings, $companyId);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toBeEmpty();
});


test('getSetting retrieves a specific setting value for a company', function () {
    $key = 'app_name';
    $companyId = 1;
    $expectedValue = 'CraterApp';

    // Create a mock CompanySetting instance with the expected value
    $mockSetting = createMockCompanySetting(['value' => $expectedValue]);

    // Mock the query builder chain for first()
    $mockBuilderFirst = Mockery::mock(Builder::class);
    $mockBuilderFirst->shouldReceive('first')
        ->once()
        ->andReturn($mockSetting);

    // Mock the query builder chain for whereCompany()
    $mockBuilderWhereCompany = Mockery::mock(Builder::class);
    $mockBuilderWhereCompany->shouldReceive('whereCompany')
        ->once()
        ->with($companyId)
        ->andReturn($mockBuilderFirst);

    // Mock the static whereOption method (Laravel's dynamic scope)
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('whereOption')
        ->once()
        ->with($key)
        ->andReturn($mockBuilderWhereCompany);

    $result = CompanySetting::getSetting($key, $companyId);

    expect($result)->toEqual($expectedValue);
});

test('getSetting returns null if the specific setting does not exist for a company', function () {
    $key = 'non_existent_setting';
    $companyId = 1;

    // Mock the query builder chain to return null for first()
    $mockBuilderFirst = Mockery::mock(Builder::class);
    $mockBuilderFirst->shouldReceive('first')
        ->once()
        ->andReturn(null);

    // Mock the query builder chain for whereCompany()
    $mockBuilderWhereCompany = Mockery::mock(Builder::class);
    $mockBuilderWhereCompany->shouldReceive('whereCompany')
        ->once()
        ->with($companyId)
        ->andReturn($mockBuilderFirst);

    // Mock the static whereOption method
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('whereOption')
        ->once()
        ->with($key)
        ->andReturn($mockBuilderWhereCompany);

    $result = CompanySetting::getSetting($key, $companyId);

    expect($result)->toBeNull();
});
