<?php
use Crater\Http\Requests\DiskEnvironmentRequest;

test('authorize method always returns true', function () {
    $request = new DiskEnvironmentRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns default rules when driver is not provided', function () {
    // Create a partial mock to control the `get` method
    $request = Mockery::mock(DiskEnvironmentRequest::class)->makePartial();
    $request->shouldReceive('get')->with('driver')->andReturn(null);

    $rules = $request->rules();

    expect($rules)->toHaveKeys(['name', 'driver']);
    expect($rules['name'])->toEqual(['required']);
    expect($rules['driver'])->toEqual(['required']);

    // Ensure no driver-specific rules are present
    expect($rules)->not->toHaveKeys([
        'credentials.key',
        'credentials.secret',
        'credentials.region',
        'credentials.bucket',
        'credentials.endpoint',
        'credentials.token',
        'credentials.app',
        'credentials.root',
    ]);
});

test('rules method returns default rules when driver is unknown', function () {
    // Create a partial mock to control the `get` method
    $request = Mockery::mock(DiskEnvironmentRequest::class)->makePartial();
    $request->shouldReceive('get')->with('driver')->andReturn('unknown_driver');

    $rules = $request->rules();

    expect($rules)->toHaveKeys(['name', 'driver']);
    expect($rules['name'])->toEqual(['required']);
    expect($rules['driver'])->toEqual(['required']);

    // Ensure no driver-specific rules are present
    expect($rules)->not->toHaveKeys([
        'credentials.key',
        'credentials.secret',
        'credentials.region',
        'credentials.bucket',
        'credentials.endpoint',
        'credentials.token',
        'credentials.app',
        'credentials.root',
    ]);
});

test('rules method returns s3 specific rules when driver is s3', function () {
    // Create a partial mock to control the `get` method
    $request = Mockery::mock(DiskEnvironmentRequest::class)->makePartial();
    $request->shouldReceive('get')->with('driver')->andReturn('s3');

    $rules = $request->rules();

    // Assert default rules are present
    expect($rules['name'])->toEqual(['required']);
    expect($rules['driver'])->toEqual(['required']);

    // Assert S3 specific rules are present and correct
    expect($rules)->toHaveKeys([
        'credentials.key',
        'credentials.secret',
        'credentials.region',
        'credentials.bucket',
        'credentials.root',
    ]);
    expect($rules['credentials.key'])->toEqual(['required', 'string']);
    expect($rules['credentials.secret'])->toEqual(['required', 'string']);
    expect($rules['credentials.region'])->toEqual(['required', 'string']);
    expect($rules['credentials.bucket'])->toEqual(['required', 'string']);
    expect($rules['credentials.root'])->toEqual(['required', 'string']);
});

test('rules method returns doSpaces specific rules when driver is doSpaces', function () {
    // Create a partial mock to control the `get` method
    $request = Mockery::mock(DiskEnvironmentRequest::class)->makePartial();
    $request->shouldReceive('get')->with('driver')->andReturn('doSpaces');

    $rules = $request->rules();

    // Assert default rules are present
    expect($rules['name'])->toEqual(['required']);
    expect($rules['driver'])->toEqual(['required']);

    // Assert doSpaces specific rules are present and correct
    expect($rules)->toHaveKeys([
        'credentials.key',
        'credentials.secret',
        'credentials.region',
        'credentials.bucket',
        'credentials.endpoint',
        'credentials.root',
    ]);
    expect($rules['credentials.key'])->toEqual(['required', 'string']);
    expect($rules['credentials.secret'])->toEqual(['required', 'string']);
    expect($rules['credentials.region'])->toEqual(['required', 'string']);
    expect($rules['credentials.bucket'])->toEqual(['required', 'string']);
    expect($rules['credentials.endpoint'])->toEqual(['required', 'string']);
    expect($rules['credentials.root'])->toEqual(['required', 'string']);
});

test('rules method returns dropbox specific rules when driver is dropbox', function () {
    // Create a partial mock to control the `get` method
    $request = Mockery::mock(DiskEnvironmentRequest::class)->makePartial();
    $request->shouldReceive('get')->with('driver')->andReturn('dropbox');

    $rules = $request->rules();

    // Assert default rules are present
    expect($rules['name'])->toEqual(['required']);
    expect($rules['driver'])->toEqual(['required']);

    // Assert Dropbox specific rules are present and correct
    expect($rules)->toHaveKeys([
        'credentials.token',
        'credentials.key',
        'credentials.secret',
        'credentials.app',
        'credentials.root',
    ]);
    expect($rules['credentials.token'])->toEqual(['required', 'string']);
    expect($rules['credentials.key'])->toEqual(['required', 'string']);
    expect($rules['credentials.secret'])->toEqual(['required', 'string']);
    expect($rules['credentials.app'])->toEqual(['required', 'string']);
    expect($rules['credentials.root'])->toEqual(['required', 'string']);
});




afterEach(function () {
    Mockery::close();
});
