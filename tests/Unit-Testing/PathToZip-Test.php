<?php

test('it can be instantiated', function () {
    $rule = new \Crater\Rules\Backup\PathToZip();
    expect($rule)->toBeInstanceOf(\Crater\Rules\Backup\PathToZip::class);
});

test('passes returns true for a valid zip file path', function () {
    $rule = new \Crater\Rules\Backup\PathToZip();

    // Standard valid paths
    expect($rule->passes('attribute', 'backup/my-backup.zip'))->toBeTrue();
    expect($rule->passes('attribute', 'archive.zip'))->toBeTrue();
    expect($rule->passes('attribute', 'path/to/dir/file.zip'))->toBeTrue();

    // Edge case: just the extension
    expect($rule->passes('attribute', '.zip'))->toBeTrue();

    // Path with numbers and special characters
    expect($rule->passes('attribute', 'backup-2023_01_01.zip'))->toBeTrue();
});

test('passes returns false for an invalid zip file path', function () {
    $rule = new \Crater\Rules\Backup\PathToZip();

    // Paths not ending with .zip
    expect($rule->passes('attribute', 'backup/my-backup.tar.gz'))->toBeFalse();
    expect($rule->passes('attribute', 'document.txt'))->toBeFalse();
    expect($rule->passes('attribute', 'image.jpg'))->toBeFalse();
    expect($rule->passes('attribute', 'folder/backup.zip.txt'))->toBeFalse(); // ends with .txt

    // Paths containing .zip but not at the end
    expect($rule->passes('attribute', 'backup.zip/folder'))->toBeFalse();
    expect($rule->passes('attribute', 'backup.zip.part'))->toBeFalse();

    // Case sensitivity (Str::endsWith is case-sensitive)
    expect($rule->passes('attribute', 'file.ZiP'))->toBeFalse();
    expect($rule->passes('attribute', 'file.ZIP'))->toBeFalse();

    // Empty string
    expect($rule->passes('attribute', ''))->toBeFalse();

    // String without any extension or with a different suffix
    expect($rule->passes('attribute', 'no_extension'))->toBeFalse();
    expect($rule->passes('attribute', 'backupzip'))->toBeFalse();
    expect($rule->passes('attribute', 'zip'))->toBeFalse();
    expect($rule->passes('attribute', 'my-backup.'))->toBeFalse();
});

test('message returns the correct validation error message', function () {
    $rule = new \Crater\Rules\Backup\PathToZip();
    expect($rule->message())->toBe('The given value must be a path to a zip file.');
});




afterEach(function () {
    Mockery::close();
});