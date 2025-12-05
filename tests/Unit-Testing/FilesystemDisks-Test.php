<?php

use Crater\Rules\Backup\FilesystemDisks;
use Illuminate\Support\Facades\Config;


test('it can be instantiated', function () {
    $rule = new FilesystemDisks();
    expect($rule)->toBeInstanceOf(FilesystemDisks::class);
});

test('passes returns true if the disk is configured in filesystem disks', function () {
    // The config() helper internally calls app('config')->get($key, $default = null).
    // Mockery needs to expect the second, optional `null` argument.
    Config::shouldReceive('get')
        ->with('filesystem.disks', null) // Added null to match the default behavior of config()
        ->andReturn([
            'local',
            's3',
            'my_custom_disk',
        ]);

    $rule = new FilesystemDisks();

    expect($rule->passes('disk', 'local'))->toBeTrue();
    expect($rule->passes('disk', 's3'))->toBeTrue();
    expect($rule->passes('disk', 'my_custom_disk'))->toBeTrue();
});

test('passes returns false if the disk is not configured in filesystem disks', function () {
    // The config() helper internally calls app('config')->get($key, $default = null).
    // Mockery needs to expect the second, optional `null` argument.
    Config::shouldReceive('get')
        ->with('filesystem.disks', null) // Added null to match the default behavior of config()
        ->andReturn([
            'local',
            's3',
        ]);

    $rule = new FilesystemDisks();

    expect($rule->passes('disk', 'non_existent_disk'))->toBeFalse();
    expect($rule->passes('disk', 'ftp'))->toBeFalse();
    expect($rule->passes('disk', 'my_custom_disk'))->toBeFalse();
});

test('passes returns false when the configured disks array is empty', function () {
    // The config() helper internally calls app('config')->get($key, $default = null).
    // Mockery needs to expect the second, optional `null` argument.
    Config::shouldReceive('get')
        ->with('filesystem.disks', null) // Added null to match the default behavior of config()
        ->andReturn([]);

    $rule = new FilesystemDisks();

    expect($rule->passes('disk', 'local'))->toBeFalse();
    expect($rule->passes('disk', 's3'))->toBeFalse();
    expect($rule->passes('disk', 'any_disk'))->toBeFalse();
});

test('passes throws a TypeError if configured disks is null (misconfiguration)', function () {
    // This simulates 'filesystem.disks' not being set at all, leading to config('...') returning null.
    // In PHP 8+, in_array with a null haystack throws a TypeError if not handled by the rule.
    // The config() helper internally calls app('config')->get($key, $default = null).
    // Mockery needs to expect the second, optional `null` argument.
    Config::shouldReceive('get')
        ->with('filesystem.disks', null) // Added null to match the default behavior of config()
        ->andReturn(null);

    $rule = new FilesystemDisks();

    // Expecting the TypeError to be thrown by the passes method when in_array is called without null-coalescing.
    expect(fn () => $rule->passes('disk', 'local'))
        ->toThrow(TypeError::class);
}); // The Pest helper `->throws(TypeError::class)` on the test function is redundant when using `expect(...)->toThrow(...)`

test('message returns the correct validation error message', function () {
    $rule = new FilesystemDisks();
    expect($rule->message())->toBe('This disk is not configured as a filesystem disk.');
});


afterEach(function () {
    Mockery::close();
});