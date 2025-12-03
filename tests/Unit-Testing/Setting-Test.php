<?php

use Crater\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Mockery as m;

// Ensure Mockery closes mocks after each test to prevent interfering with subsequent tests.
afterEach(function () {
    m::close();
});

test('setSetting updates an existing setting', function () {
    // 1. Mock an *existing* setting instance. This is the model that `first()` will return.
    $existingSettingInstance = m::mock(Setting::class);
    $existingSettingInstance->shouldReceive('setAttribute')->with('value', 'updated_value')->once();
    $existingSettingInstance->shouldReceive('save')->once()->andReturn(true);

    // 2. Overload the Setting class. This mock will intercept both static method calls
    //    (like `whereOption`, `first`) and any `new Setting()` calls within the method.
    $mockSettingClass = m::mock('overload:' . Setting::class);

    // 3. Mock the static chain: `whereOption($key)->first()`
    $mockSettingClass->shouldReceive('whereOption')
                     ->with('existing_key')
                     ->once()
                     ->andReturn($mockSettingClass); // Self-return for method chaining

    // 4. `first()` should return our existing setting instance
    $mockSettingClass->shouldReceive('first')
                     ->once()
                     ->andReturn($existingSettingInstance);

    // 5. Call the method under test
    Setting::setSetting('existing_key', 'updated_value');

    // Mockery expectations handle the assertions
});

test('setSetting creates a new setting if it does not exist', function () {
    // 1. Overload the Setting class. This mock will intercept both static method calls
    //    and any `new Setting()` calls.
    $mockSettingClass = m::mock('overload:' . Setting::class);

    // 2. Mock the static chain: `whereOption($key)->first()`
    $mockSettingClass->shouldReceive('whereOption')
                     ->with('new_key')
                     ->once()
                     ->andReturn($mockSettingClass); // Self-return for method chaining

    // 3. `first()` should return null to simulate no existing setting
    $mockSettingClass->shouldReceive('first')
                     ->once()
                     ->andReturn(null);

    // 4. Since `new Setting()` will return `$mockSettingClass` itself due to overload,
    //    we expect property assignments and `save()` to be called on this same mock.
    $mockSettingClass->shouldReceive('setAttribute')->with('option', 'new_key')->once();
    $mockSettingClass->shouldReceive('setAttribute')->with('value', 'new_value')->once();
    $mockSettingClass->shouldReceive('save')->once()->andReturn(true);

    // 5. Call the method under test
    Setting::setSetting('new_key', 'new_value');

    // Mockery expectations handle the assertions
});

test('setSetting can set a null value for an existing setting', function () {
    $existingSettingInstance = m::mock(Setting::class);
    $existingSettingInstance->shouldReceive('setAttribute')->with('value', null)->once();
    $existingSettingInstance->shouldReceive('save')->once()->andReturn(true);

    $mockSettingClass = m::mock('overload:' . Setting::class);

    $mockSettingClass->shouldReceive('whereOption')
                     ->with('null_key')
                     ->once()
                     ->andReturn($mockSettingClass);
    $mockSettingClass->shouldReceive('first')
                     ->once()
                     ->andReturn($existingSettingInstance);

    Setting::setSetting('null_key', null);
});

test('setSetting can set a null value for a new setting', function () {
    $mockSettingClass = m::mock('overload:' . Setting::class);

    $mockSettingClass->shouldReceive('whereOption')
                     ->with('new_null_key')
                     ->once()
                     ->andReturn($mockSettingClass);
    $mockSettingClass->shouldReceive('first')
                     ->once()
                     ->andReturn(null);

    $mockSettingClass->shouldReceive('setAttribute')->with('option', 'new_null_key')->once();
    $mockSettingClass->shouldReceive('setAttribute')->with('value', null)->once();
    $mockSettingClass->shouldReceive('save')->once()->andReturn(true);

    Setting::setSetting('new_null_key', null);
});

test('setSettings updates or creates multiple settings', function () {
    $settings = [
        'key1' => 'value1',
        'key2' => 'value2',
        'key3' => null, // Test with a null value
    ];

    // Mock the static `updateOrCreate` method on the Setting class
    $settingMock = m::mock('alias:'.Setting::class);

    $settingMock->shouldReceive('updateOrCreate')
                ->with(['option' => 'key1'], ['option' => 'key1', 'value' => 'value1'])
                ->once()
                ->andReturn(m::mock(Setting::class)); // Return a dummy model instance

    $settingMock->shouldReceive('updateOrCreate')
                ->with(['option' => 'key2'], ['option' => 'key2', 'value' => 'value2'])
                ->once()
                ->andReturn(m::mock(Setting::class));

    $settingMock->shouldReceive('updateOrCreate')
                ->with(['option' => 'key3'], ['option' => 'key3', 'value' => null])
                ->once()
                ->andReturn(m::mock(Setting::class));

    Setting::setSettings($settings);
});

test('setSettings handles an empty array without calling updateOrCreate', function () {
    $settings = [];

    // Ensure `updateOrCreate` is never called
    $settingMock = m::mock('alias:'.Setting::class);
    $settingMock->shouldNotReceive('updateOrCreate');

    Setting::setSettings($settings);
});

test('getSetting returns value if setting exists', function () {
    // 1. Mock an existing setting instance with a value
    $existingSetting = m::mock(Setting::class);
    // Eloquent models handle property access for attributes, so direct assignment works for mocks
    $existingSetting->value = 'some_value';

    // 2. Mock the query builder chain (`whereOption($key)->first()`)
    $queryBuilderMock = m::mock();
    $queryBuilderMock->shouldReceive('first')->once()->andReturn($existingSetting);

    // 3. Mock the static `whereOption` call on the Setting class
    m::mock('alias:' . Setting::class)
        ->shouldReceive('whereOption')
        ->with('get_key')
        ->once()
        ->andReturn($queryBuilderMock);

    // 4. Call the method under test
    $result = Setting::getSetting('get_key');

    // 5. Assert the result
    expect($result)->toBe('some_value');
});

test('getSetting returns null if setting does not exist', function () {
    // 1. Mock the query builder chain to return null (no setting found)
    $queryBuilderMock = m::mock();
    $queryBuilderMock->shouldReceive('first')->once()->andReturn(null);

    // 2. Mock the static `whereOption` call on the Setting class
    m::mock('alias:' . Setting::class)
        ->shouldReceive('whereOption')
        ->with('non_existent_key')
        ->once()
        ->andReturn($queryBuilderMock);

    // 3. Call the method under test
    $result = Setting::getSetting('non_existent_key');

    // 4. Assert the result
    expect($result)->toBeNull();
});

test('getSetting returns null for an existing setting with a null value', function () {
    // 1. Mock an existing setting instance with a null value
    $existingSetting = m::mock(Setting::class);
    $existingSetting->value = null;

    // 2. Mock the query builder chain
    $queryBuilderMock = m::mock();
    $queryBuilderMock->shouldReceive('first')->once()->andReturn($existingSetting);

    // 3. Mock the static `whereOption` call
    m::mock('alias:' . Setting::class)
        ->shouldReceive('whereOption')
        ->with('null_value_key')
        ->once()
        ->andReturn($queryBuilderMock);

    // 4. Call the method under test
    $result = Setting::getSetting('null_value_key');

    // 5. Assert the result
    expect($result)->toBeNull();
});

test('getSettings returns all requested settings as an associative array', function () {
    $keys = ['key_a', 'key_b'];

    // 1. Create mock setting models that the collection will contain
    $settingA = m::mock(Setting::class);
    $settingA->option = 'key_a'; // Public property access for test
    $settingA->value = 'value_a';

    $settingB = m::mock(Setting::class);
    $settingB->option = 'key_b';
    $settingB->value = 'value_b';

    // 2. Create a real Eloquent Collection containing our mock settings
    $collectionMock = new Collection([$settingA, $settingB]);

    // 3. Mock the query builder chain (`whereIn()->get()`)
    $queryBuilderMock = m::mock();
    $queryBuilderMock->shouldReceive('get')->once()->andReturn($collectionMock);

    // 4. Mock the static `whereIn` call on the Setting class
    m::mock('alias:' . Setting::class)
        ->shouldReceive('whereIn')
        ->with('option', $keys)
        ->once()
        ->andReturn($queryBuilderMock);

    // 5. Call the method under test
    $result = Setting::getSettings($keys);

    // 6. Assert the transformed result
    expect($result)->toEqual([
        'key_a' => 'value_a',
        'key_b' => 'value_b',
    ]);
});

test('getSettings returns only found settings for multiple requested keys', function () {
    $keys = ['key_a', 'key_c']; // 'key_c' is requested but will not be in the returned collection

    $settingA = m::mock(Setting::class);
    $settingA->option = 'key_a';
    $settingA->value = 'value_a';

    // Simulate only 'key_a' being found
    $collectionMock = new Collection([$settingA]);

    $queryBuilderMock = m::mock();
    $queryBuilderMock->shouldReceive('get')->once()->andReturn($collectionMock);

    m::mock('alias:' . Setting::class)
        ->shouldReceive('whereIn')
        ->with('option', $keys)
        ->once()
        ->andReturn($queryBuilderMock);

    $result = Setting::getSettings($keys);

    expect($result)->toEqual(['key_a' => 'value_a']);
});

test('getSettings returns empty array if no settings are found for requested keys', function () {
    $keys = ['non_existent_key_1', 'non_existent_key_2'];

    // Simulate an empty collection being returned
    $collectionMock = new Collection([]);

    $queryBuilderMock = m::mock();
    $queryBuilderMock->shouldReceive('get')->once()->andReturn($collectionMock);

    m::mock('alias:' . Setting::class)
        ->shouldReceive('whereIn')
        ->with('option', $keys)
        ->once()
        ->andReturn($queryBuilderMock);

    $result = Setting::getSettings($keys);

    expect($result)->toEqual([]);
});

test('getSettings returns empty array for an empty input keys array', function () {
    $keys = [];

    // Eloquent's `whereIn` with an empty array of values typically results in no records.
    // We simulate this by returning an empty collection.
    $collectionMock = new Collection([]);

    $queryBuilderMock = m::mock();
    $queryBuilderMock->shouldReceive('get')->once()->andReturn($collectionMock);

    m::mock('alias:' . Setting::class)
        ->shouldReceive('whereIn')
        ->with('option', $keys) // Expect `whereIn` with an empty array
        ->once()
        ->andReturn($queryBuilderMock);

    $result = Setting::getSettings($keys);

    expect($result)->toEqual([]);
});



