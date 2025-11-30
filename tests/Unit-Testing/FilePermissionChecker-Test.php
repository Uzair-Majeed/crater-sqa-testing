<?php

use Crater\Space\FilePermissionChecker;
use Mockery as m;

// Helper to access private methods via Reflection
function callPrivateMethod($object, $methodName, array $parameters = [])
{
    $reflection = new ReflectionClass($object);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
}

// Helper to get private properties via Reflection
function getPrivateProperty($object, $propertyName)
{
    $reflection = new ReflectionClass($object);
    $property = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    return $property->getValue($object);
}

// Mock base_path function if it doesn't exist (e.g., when not running in a full Laravel app)
// This allows `FilePermissionChecker` to operate in isolation without requiring Laravel's boot.
// For the purpose of testing `FilePermissionChecker`, we just need a path string that `fileperms` can use.
if (!function_exists('base_path')) {
    function base_path($path = '')
    {
        // In this test context, assume base_path just returns the path itself,
        // or effectively concatenates with the current working directory if it's relative.
        // For absolute paths passed to `getPermission` tests, it works directly.
        return $path;
    }
}

// Global Pest setup for this test file
uses()
    ->beforeEach(function () {
        // Close any Mockery expectations to ensure a clean state for each test
        m::close();
    })
    ->group('FilePermissionChecker')
    ->in(__DIR__); // Assumes the test file is located in a relevant test directory

// Test `__construct` method
test('constructor initializes results property with empty permissions and null errors', function () {
    $checker = new FilePermissionChecker();

    $results = getPrivateProperty($checker, 'results');

    expect($results)->toBeArray()
        ->and($results)->toHaveKey('permissions')
        ->and($results['permissions'])->toBeArray()->toBeEmpty()
        ->and($results)->toHaveKey('errors')
        ->and($results['errors'])->toBeNull();
});

// Test `check` method: success scenario
test('check method sets isSet to true for all folders when permissions are met', function () {
    // Create a partial mock to replace the private `getPermission` method
    $checker = m::mock(FilePermissionChecker::class)->makePartial();
    $checker->shouldAllowMockingProtectedMethods()->shouldReceive('getPermission')
        ->andReturnValues(['0755', '0644', '0777']); // Mocked permissions for each folder

    $folders = [
        'path/to/folder1' => '0755', // Required 755, has 755 -> PASS
        'path/to/folder2' => '0644', // Required 644, has 644 -> PASS
        'path/to/folder3' => '0777', // Required 777, has 777 -> PASS
    ];

    $results = $checker->check($folders);

    expect($results)->toBeArray()
        ->and($results['errors'])->toBeNull()
        ->and($results['permissions'])->toHaveCount(3);

    expect($results['permissions'][0])->toMatchArray(['folder' => 'path/to/folder1', 'permission' => '0755', 'isSet' => true]);
    expect($results['permissions'][1])->toMatchArray(['folder' => 'path/to/folder2', 'permission' => '0644', 'isSet' => true]);
    expect($results['permissions'][2])->toMatchArray(['folder' => 'path/to/folder3', 'permission' => '0777', 'isSet' => true]);

    $checker->shouldHaveReceived('getPermission', ['path/to/folder1'])->once();
    $checker->shouldHaveReceived('getPermission', ['path/to/folder2'])->once();
    $checker->shouldHaveReceived('getPermission', ['path/to/folder3'])->once();
});

// Test `check` method: failure scenario
test('check method sets isSet to false and errors to true when some permissions are not met', function () {
    $checker = m::mock(FilePermissionChecker::class)->makePartial();
    $checker->shouldAllowMockingProtectedMethods()->shouldReceive('getPermission')
        ->andReturnValues(['0750', '0644', '0700']); // Mocked permissions: Fail, Pass, Fail

    $folders = [
        'path/to/folder1' => '0755', // Required 755, has 750 -> FAIL
        'path/to/folder2' => '0644', // Required 644, has 644 -> PASS
        'path/to/folder3' => '0777', // Required 777, has 700 -> FAIL
    ];

    $results = $checker->check($folders);

    expect($results)->toBeArray()
        ->and($results['errors'])->toBeTrue()
        ->and($results['permissions'])->toHaveCount(3);

    expect($results['permissions'][0])->toMatchArray(['folder' => 'path/to/folder1', 'permission' => '0755', 'isSet' => false]);
    expect($results['permissions'][1])->toMatchArray(['folder' => 'path/to/folder2', 'permission' => '0644', 'isSet' => true]);
    expect($results['permissions'][2])->toMatchArray(['folder' => 'path/to/folder3', 'permission' => '0777', 'isSet' => false]);

    $checker->shouldHaveReceived('getPermission', ['path/to/folder1'])->once();
    $checker->shouldHaveReceived('getPermission', ['path/to/folder2'])->once();
    $checker->shouldHaveReceived('getPermission', ['path/to/folder3'])->once();
});

// Test `check` method: empty input array
test('check method handles an empty folders array gracefully', function () {
    $checker = m::mock(FilePermissionChecker::class)->makePartial();
    // Ensure getPermission is not called for empty input
    $checker->shouldAllowMockingProtectedMethods()->shouldNotReceive('getPermission');

    $folders = [];
    $results = $checker->check($folders);

    expect($results)->toBeArray()
        ->and($results['errors'])->toBeNull()
        ->and($results['permissions'])->toBeArray()->toBeEmpty();
});

// Test `check` method: single folder, sufficient permission
test('check method handles a single folder with sufficient permission', function () {
    $checker = m::mock(FilePermissionChecker::class)->makePartial();
    $checker->shouldAllowMockingProtectedMethods()->shouldReceive('getPermission')
        ->with('single_pass_folder')
        ->andReturn('0777'); // Has more than required

    $folders = ['single_pass_folder' => '0755'];
    $results = $checker->check($folders);

    expect($results['errors'])->toBeNull()
        ->and($results['permissions'])->toHaveCount(1)
        ->and($results['permissions'][0])->toMatchArray(['folder' => 'single_pass_folder', 'permission' => '0755', 'isSet' => true]);
});

// Test `check` method: single folder, insufficient permission
test('check method handles a single folder with insufficient permission', function () {
    $checker = m::mock(FilePermissionChecker::class)->makePartial();
    $checker->shouldAllowMockingProtectedMethods()->shouldReceive('getPermission')
        ->with('single_fail_folder')
        ->andReturn('0750'); // Has less than required

    $folders = ['single_fail_folder' => '0755'];
    $results = $checker->check($folders);

    expect($results['errors'])->toBeTrue()
        ->and($results['permissions'])->toHaveCount(1)
        ->and($results['permissions'][0])->toMatchArray(['folder' => 'single_fail_folder', 'permission' => '0755', 'isSet' => false]);
});

// Test `check` method: required permission is 0000 (always passes)
test('check method correctly handles zero required permission', function () {
    $checker = m::mock(FilePermissionChecker::class)->makePartial();
    $checker->shouldAllowMockingProtectedMethods()->shouldReceive('getPermission')
        ->with('any_folder')
        ->andReturn('0000'); // Minimum possible permission

    $folders = ['any_folder' => '0000']; // Required 0000
    $results = $checker->check($folders);

    expect($results['errors'])->toBeNull()
        ->and($results['permissions'])->toHaveCount(1)
        ->and($results['permissions'][0])->toMatchArray(['folder' => 'any_folder', 'permission' => '0000', 'isSet' => true]);
});

// Test `check` method: required permission is 0777 (rarely passes)
test('check method handles high required permission that is not met', function () {
    $checker = m::mock(FilePermissionChecker::class)->makePartial();
    $checker->shouldAllowMockingProtectedMethods()->shouldReceive('getPermission')
        ->with('secure_folder')
        ->andReturn('0755'); // Standard permission

    $folders = ['secure_folder' => '0777']; // Required 0777, has 0755 -> FAIL
    $results = $checker->check($folders);

    expect($results['errors'])->toBeTrue()
        ->and($results['permissions'])->toHaveCount(1)
        ->and($results['permissions'][0])->toMatchArray(['folder' => 'secure_folder', 'permission' => '0777', 'isSet' => false]);
});

// Test `getPermission` method (private)
test('getPermission returns correct octal string for a directory with known permissions', function () {
    $checker = new FilePermissionChecker();

    // Create a temporary directory with specific permissions
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('test_dir_');
    mkdir($tempDir, 0755, true);
    chmod($tempDir, 0755);

    try {
        $permission = callPrivateMethod($checker, 'getPermission', [$tempDir]);
        // fileperms returns a value like 040755 for directories, substr(-4) extracts '0755'
        expect($permission)->toBe('0755');
    } finally {
        // Clean up
        rmdir($tempDir);
    }
});

test('getPermission returns correct octal string for a file with known permissions', function () {
    $checker = new FilePermissionChecker();

    // Create a temporary file with specific permissions
    $tempFile = tempnam(sys_get_temp_dir(), 'test_file_');
    file_put_contents($tempFile, 'test content');
    chmod($tempFile, 0644);

    try {
        $permission = callPrivateMethod($checker, 'getPermission', [$tempFile]);
        // fileperms returns a value like 0100644 for files, substr(-4) extracts '0644'
        expect($permission)->toBe('0644');
    } finally {
        // Clean up
        unlink($tempFile);
    }
});

test('getPermission returns 0000 for a non-existent path', function () {
    $checker = new FilePermissionChecker();
    $nonExistentPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('non_existent_');

    // When fileperms is called on a non-existent path, it returns false.
    // sprintf('%o', false) converts false to '0'.
    // substr('0', -4) correctly returns '0'.
    // The class then pads this with leading zeros to '0000' implicitly when comparing.
    $permission = callPrivateMethod($checker, 'getPermission', [$nonExistentPath]);
    expect($permission)->toBe('0000');
});

// Test `addFile` method (private)
test('addFile adds a file entry to the permissions array with isSet as true', function () {
    $checker = new FilePermissionChecker();
    $initialResults = getPrivateProperty($checker, 'results');
    expect($initialResults['permissions'])->toBeArray()->toBeEmpty();

    callPrivateMethod($checker, 'addFile', ['folder_a', '0755', true]);

    $updatedResults = getPrivateProperty($checker, 'results');
    expect($updatedResults['permissions'])->toHaveCount(1)
        ->and($updatedResults['permissions'][0])->toMatchArray([
            'folder' => 'folder_a',
            'permission' => '0755',
            'isSet' => true,
        ]);
});

test('addFile adds a file entry to the permissions array with isSet as false', function () {
    $checker = new FilePermissionChecker();
    callPrivateMethod($checker, 'addFile', ['folder_b', '0644', false]);

    $updatedResults = getPrivateProperty($checker, 'results');
    expect($updatedResults['permissions'])->toHaveCount(1)
        ->and($updatedResults['permissions'][0])->toMatchArray([
            'folder' => 'folder_b',
            'permission' => '0644',
            'isSet' => false,
        ]);
});

test('addFile adds multiple file entries correctly', function () {
    $checker = new FilePermissionChecker();
    callPrivateMethod($checker, 'addFile', ['folder_a', '0755', true]);
    callPrivateMethod($checker, 'addFile', ['folder_b', '0644', false]);

    $updatedResults = getPrivateProperty($checker, 'results');
    expect($updatedResults['permissions'])->toHaveCount(2)
        ->and($updatedResults['permissions'][0]['folder'])->toBe('folder_a')
        ->and($updatedResults['permissions'][1]['folder'])->toBe('folder_b');
});


// Test `addFileAndSetErrors` method (private)
test('addFileAndSetErrors adds a file entry and sets errors to true', function () {
    $checker = new FilePermissionChecker();
    $initialResults = getPrivateProperty($checker, 'results');
    expect($initialResults['permissions'])->toBeArray()->toBeEmpty()
        ->and($initialResults['errors'])->toBeNull();

    callPrivateMethod($checker, 'addFileAndSetErrors', ['folder_c', '0777', false]);

    $updatedResults = getPrivateProperty($checker, 'results');
    expect($updatedResults['permissions'])->toHaveCount(1)
        ->and($updatedResults['permissions'][0])->toMatchArray([
            'folder' => 'folder_c',
            'permission' => '0777',
            'isSet' => false,
        ])
        ->and($updatedResults['errors'])->toBeTrue();
});

test('addFileAndSetErrors sets errors to true and keeps it true on subsequent calls', function () {
    $checker = new FilePermissionChecker();
    callPrivateMethod($checker, 'addFileAndSetErrors', ['folder_c', '0777', false]);
    callPrivateMethod($checker, 'addFileAndSetErrors', ['folder_d', '0700', false]);

    $updatedResults = getPrivateProperty($checker, 'results');
    expect($updatedResults['permissions'])->toHaveCount(2)
        ->and($updatedResults['errors'])->toBeTrue();
});
