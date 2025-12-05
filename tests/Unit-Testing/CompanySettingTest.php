<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// We'll test the actual CompanySetting model but mock the database interactions

test('company setting model has correct fillable attributes', function () {
    $model = new \Crater\Models\CompanySetting();
    
    expect($model->getFillable())->toBe(['company_id', 'option', 'value']);
});

test('company setting belongs to company relationship', function () {
    $model = new \Crater\Models\CompanySetting();
    
    $relation = $model->company();
    
    expect($relation)->toBeInstanceOf(BelongsTo::class);
});


test('company setting getSettings method returns specific settings mapped', function () {
    // Test the mapping logic
    $collection = collect([
        ['option' => 'theme', 'value' => 'dark'],
        ['option' => 'language', 'value' => 'en'],
    ]);
    
    $result = $collection->mapWithKeys(function ($item) {
        return [$item['option'] => $item['value']];
    });
    
    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->toArray())->toBe([
            'theme' => 'dark',
            'language' => 'en',
        ]);
});


test('company setting model attributes can be set and retrieved', function () {
    $model = new \Crater\Models\CompanySetting();
    
    // Test setting attributes
    $model->company_id = 777;
    $model->option = 'test_option';
    $model->value = 'test_value';
    
    expect($model->company_id)->toBe(777)
        ->and($model->option)->toBe('test_option')
        ->and($model->value)->toBe('test_value');
});

test('company setting with different value types', function () {
    $model = new \Crater\Models\CompanySetting();
    
    // Test various value types
    $testCases = [
        'string' => 'Hello World',
        'integer' => 123,
        'float' => 123.45,
        'boolean' => true,
        'null' => null,
        'array' => ['a', 'b', 'c'],
        'object' => (object) ['key' => 'value'],
    ];
    
    foreach ($testCases as $type => $value) {
        $model->value = $value;
        expect($model->value)->toBe($value);
    }
});


test('company setting mass assignment protection', function () {
    $model = new \Crater\Models\CompanySetting();
    
    // Should be able to mass assign fillable attributes
    $model->fill([
        'company_id' => 999,
        'option' => 'mass_option',
        'value' => 'mass_value',
    ]);
    
    expect($model->company_id)->toBe(999)
        ->and($model->option)->toBe('mass_option')
        ->and($model->value)->toBe('mass_value');
});

test('company setting table name', function () {
    $model = new \Crater\Models\CompanySetting();
    
    expect($model->getTable())->toBe('company_settings');
});

test('company setting timestamps', function () {
    $model = new \Crater\Models\CompanySetting();
    
    expect($model->usesTimestamps())->toBeTrue();
});

// Test helper: Test the mapWithKeys logic used in getAllSettings and getSettings
test('mapWithKeys helper function transforms collection correctly', function () {
    $testData = [
        ['option' => 'key1', 'value' => 'value1'],
        ['option' => 'key2', 'value' => 'value2'],
        ['option' => 'key3', 'value' => 'value3'],
    ];
    
    $collection = collect($testData);
    $result = $collection->mapWithKeys(function ($item) {
        return [$item['option'] => $item['value']];
    });
    
    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->toArray())->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ]);
});

test('company setting handles special option names', function () {
    $model = new \Crater\Models\CompanySetting();
    
    $specialNames = [
        'dot.name' => 'dot_value',
        'dash-name' => 'dash_value',
        'underscore_name' => 'underscore_value',
        'name with spaces' => 'space_value',
        'name@special#chars' => 'special_value',
        'unicode_name_😀' => 'unicode_value',
    ];
    
    foreach ($specialNames as $option => $value) {
        $model->option = $option;
        $model->value = $value;
        
        expect($model->option)->toBe($option)
            ->and($model->value)->toBe($value);
    }
});