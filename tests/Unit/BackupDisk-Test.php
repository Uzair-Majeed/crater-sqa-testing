<?php

use Crater\Rules\Backup\BackupDisk;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Validation\Rule;

test('it can be instantiated', function () {
    $rule = new BackupDisk();
    expect($rule)->toBeInstanceOf(BackupDisk::class);
    expect($rule)->toBeInstanceOf(Rule::class);
});

test('it returns the correct validation error message', function () {
    $rule = new BackupDisk();
    expect($rule->message())->toBe('This disk is not configured as a backup disk.');
});

test('passes returns true if the disk value is in the configured backup disks', function () {
    Config::set('backup.backup.destination.disks', ['local', 's3', 'ftp']);

    $rule = new BackupDisk();

    expect($rule->passes('disk_attribute', 'local'))->toBeTrue();
    expect($rule->passes('disk_attribute', 's3'))->toBeTrue();
    expect($rule->passes('disk_attribute', 'ftp'))->toBeTrue();
});

test('passes returns false if the disk value is not in the configured backup disks', function () {
    Config::set('backup.backup.destination.disks', ['local', 's3']);

    $rule = new BackupDisk();

    expect($rule->passes('disk_attribute', 'ftp'))->toBeFalse();
    expect($rule->passes('disk_attribute', 'dropbox'))->toBeFalse();
    expect($rule->passes('disk_attribute', 'unknown_disk'))->toBeFalse();
});

test('passes returns false when configured backup disks are empty', function () {
    Config::set('backup.backup.destination.disks', []);

    $rule = new BackupDisk();

    expect($rule->passes('disk_attribute', 'local'))->toBeFalse();
    expect($rule->passes('disk_attribute', 's3'))->toBeFalse();
    expect($rule->passes('disk_attribute', 'any_disk'))->toBeFalse();
});

test('passes handles case sensitivity correctly based on in_array default behavior', function () {
    Config::set('backup.backup.destination.disks', ['local', 's3', 'FTP']);

    $rule = new BackupDisk();

    expect($rule->passes('disk_attribute', 's3'))->toBeTrue();  // Matches 's3'
    expect($rule->passes('disk_attribute', 'S3'))->toBeFalse(); // Does not match 's3'
    expect($rule->passes('disk_attribute', 'FTP'))->toBeTrue(); // Matches 'FTP'
    expect($rule->passes('disk_attribute', 'ftp'))->toBeFalse(); // Does not match 'FTP'
});

test('passes handles null or empty string disk values', function () {
    Config::set('backup.backup.destination.disks', ['local', 's3', '']);

    $rule = new BackupDisk();

    expect($rule->passes('disk_attribute', ''))->toBeTrue(); // Matches if empty string is configured

    // The in_array(null, [...]) is TRUE if '' is in config, because null == '', so we need to adjust the expectation.
    // This matches the internal in_array behavior when strict=false.
    expect($rule->passes('disk_attribute', null))->toBeTrue(); // Null matches '' when '' is configured and strict=false

    Config::set('backup.backup.destination.disks', ['local', 's3']); // No empty string configured
    expect($rule->passes('disk_attribute', ''))->toBeFalse(); // Fails if empty string is not configured
    expect($rule->passes('disk_attribute', null))->toBeFalse(); // Fails for null if '' isn't in array
});

test('passes works with different types of values in configured disks if applicable', function () {
    // in_array uses loose comparison by default if types differ.
    // For string comparisons, it's strict unless comparing a number to a numeric string.
    Config::set('backup.backup.destination.disks', ['disk1', 123, 'disk2']);

    $rule = new BackupDisk();

    expect($rule->passes('disk_attribute', 'disk1'))->toBeTrue();
    expect($rule->passes('disk_attribute', 'disk2'))->toBeTrue();
    expect($rule->passes('disk_attribute', 123))->toBeTrue(); // Integer matches integer

    // in_array('123', [123]) is TRUE when strict=false (default), so we should expect TRUE.
    expect($rule->passes('disk_attribute', '123'))->toBeTrue(); // String '123' loosely matches integer 123 in array

    expect($rule->passes('disk_attribute', 'disk3'))->toBeFalse();
});


afterEach(function () {
    Mockery::close();
});