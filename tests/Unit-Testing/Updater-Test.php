<?php

namespace Crater\Space;

use Artisan;
use Crater\Events\UpdateFinished;
use Crater\Space\Updater;
use File;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;
use ZipArchive;

// Mock global functions/helpers
// These need to be globally available for Pest, so they are defined here.
// They use static variables to allow test-specific overrides or state management.

// Global state for env
static $env_data = ['APP_ENV' => 'production']; // Default to production
function env($key, $default = null)
{
    global $env_data;
    if (func_num_args() === 1) {
        return $env_data[$key] ?? null;
    }
    // If setting a value, return it so it can be chained or used directly after setting.
    return $env_data[$key] = $default;
}

function storage_path($path = '')
{
    return '/mock/storage/path' . ($path ? '/' . $path : '');
}

function base_path($path = '')
{
    return '/mock/base/path' . ($path ? '/' . $path : '');
}

// Global state for phpversion
static $mocked_phpversion_extensions = [];
function phpversion($extension = null)
{
    global $mocked_phpversion_extensions;
    if (array_key_exists($extension, $mocked_phpversion_extensions)) {
        return $mocked_phpversion_extensions[$extension];
    }
    // Default values if not specifically mocked
    if ($extension === 'openssl') { return '1.1.1k'; }
    if ($extension === 'zip') { return '1.15.6'; }
    return false;
}

// Helper to set mocked phpversion for specific extensions or for the overall PHP version (null extension)
function set_mocked_phpversion_extension($extension, $version) {
    global $mocked_phpversion_extensions;
    $mocked_phpversion_extensions[$extension] = $version;
}
function reset_mocked_phpversion_extensions() {
    global $mocked_phpversion_extensions;
    $mocked_phpversion_extensions = [];
}

// Global state for version_compare
static $mocked_version_compare_callable = null;
function version_compare($version1, $version2, $operator = null)
{
    global $mocked_version_compare_callable;
    if ($mocked_version_compare_callable) {
        return call_user_func($mocked_version_compare_callable, $version1, $version2, $operator);
    }
    // Default behavior, use actual version_compare for robustness
    if ($operator) {
        return \version_compare($version1, $version2, $operator);
    }
    return \version_compare($version1, $version2);
}

// Helper to set mocked version_compare
function set_mocked_version_compare_callable(\Closure $callable) {
    global $mocked_version_compare_callable;
    $mocked_version_compare_callable = $callable;
}
function reset_mocked_version_compare_callable() {
    global $mocked_version_compare_callable;
    $mocked_version_compare_callable = null;
}

// Global state for file_put_contents
static $mocked_file_put_contents_return = null;
function file_put_contents($filename, $data)
{
    global $mocked_file_put_contents_return;
    if ($mocked_file_put_contents_return !== null) {
        // Use the mocked return value, then reset it if it's a one-time override
        $return = $mocked_file_put_contents_return;
        $mocked_file_put_contents_return = null; // Reset for next call if not specifically set again
        return $return;
    }
    return strlen($data); // Default success: return length of content
}

// Helper to set mocked file_put_contents
function set_mocked_file_put_contents_return($returnValue) {
    global $mocked_file_put_contents_return;
    $mocked_file_put_contents_return = $returnValue;
}
function reset_mocked_file_put_contents_return() {
    global $mocked_file_put_contents_return;
    $mocked_file_put_contents_return = null;
}


// Global state for file_exists
static $mocked_files_exist = [];
function file_exists($filename)
{
    global $mocked_files_exist;
    return in_array($filename, $mocked_files_exist);
}

// Helper to set mocked file existence
function set_mocked_file_exists($filename, $exists = true) {
    global $mocked_files_exist;
    if ($exists) {
        if (!in_array($filename, $mocked_files_exist)) {
            $mocked_files_exist[] = $filename;
        }
    } else {
        $mocked_files_exist = array_filter($mocked_files_exist, fn($f) => $f !== $filename);
    }
}
function reset_mocked_file_exists() {
    global $mocked_files_exist;
    $mocked_files_exist = [];
}

beforeEach(function () {
    // Reset global state for mocked functions
    global $env_data;
    $env_data = ['APP_ENV' => 'production']; // Reset env to default production

    reset_mocked_phpversion_extensions();
    reset_mocked_version_compare_callable();
    reset_mocked_file_put_contents_return();
    reset_mocked_file_exists();

    // Mock facade File with zeroOrMoreTimes for flexibility
    Mockery::mock('alias:File')
        ->shouldReceive('isDirectory')->zeroOrMoreTimes()->andReturn(false) // Default: directories don't exist
        ->shouldReceive('makeDirectory')->zeroOrMoreTimes()->andReturn(true)
        ->shouldReceive('delete')->zeroOrMoreTimes()->andReturn(true)
        ->shouldReceive('copyDirectory')->zeroOrMoreTimes()->andReturn(true)
        ->shouldReceive('deleteDirectory')->zeroOrMoreTimes()->andReturn(true);

    // Mock Artisan facade
    Mockery::mock('alias:Artisan')
        ->shouldReceive('call')->zeroOrMoreTimes()->andReturn(0); // 0 typically means success

    // Mock Event facade
    Event::fake();
});


test('checkForUpdate returns null if getRemote fails or returns non-200', function () {
    // Mock getRemote to return null
    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->withAnyArgs()
        ->once()
        ->andReturn(null);

    $result = Updater::checkForUpdate('1.0.0');
    expect($result)->toBeNull();
});

test('checkForUpdate returns null if getRemote returns non-200', function () {
    // Mock getRemote to return a non-200 response
    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->withAnyArgs()
        ->once()
        ->andReturn(new Response(404));

    $result = Updater::checkForUpdate('1.0.0');
    expect($result)->toBeNull();
});

test('checkForUpdate constructs correct URL for development environment', function () {
    env('APP_ENV', 'development'); // Set env for this test

    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->with('downloads/check/latest/1.0.0?type=update&is_dev=1', Mockery::subset(['timeout' => 100]))
        ->andReturn(new Response(200, [], json_encode(['success' => false])));

    Updater::checkForUpdate('1.0.0');
});

test('checkForUpdate constructs correct URL for local environment', function () {
    env('APP_ENV', 'local'); // Set env for this test

    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->with('downloads/check/latest/1.0.0?type=update&is_dev=1', Mockery::subset(['timeout' => 100]))
        ->andReturn(new Response(200, [], json_encode(['success' => false])));

    Updater::checkForUpdate('1.0.0');
});

test('checkForUpdate constructs correct URL for production environment', function () {
    // env('APP_ENV') defaults to 'production' in beforeEach
    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->with('downloads/check/latest/1.0.0?type=update', Mockery::subset(['timeout' => 100]))
        ->andReturn(new Response(200, [], json_encode(['success' => false])));

    Updater::checkForUpdate('1.0.0');
});

test('checkForUpdate processes successful response with extensions and php version check', function () {
    // Mock phpversion for specific extensions and for the current PHP version check
    set_mocked_phpversion_extension('openssl', '1.1.1k');
    set_mocked_phpversion_extension('zip', '1.15.6');
    set_mocked_phpversion_extension('json', false); // Simulate missing extension
    set_mocked_phpversion_extension(null, '8.1.0'); // Mock current system PHP version

    // Use real version_compare for accurate comparison
    set_mocked_version_compare_callable(fn($v1, $v2, $op) => \version_compare($v1, $v2, $op));

    $mockedResponseData = [
        'success' => true,
        'version' => [
            'version' => '2.0.0',
            'minimum_php_version' => '8.0',
            'extensions' => json_encode(['openssl', 'zip', 'json']),
        ],
    ];

    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->andReturn(new Response(200, [], json_encode($mockedResponseData)));

    $result = Updater::checkForUpdate('1.0.0');

    expect($result)->toBeObject()
        ->and($result->success)->toBeTrue()
        ->and($result->version)->toBeObject()
        ->and($result->version->version)->toBe('2.0.0')
        ->and($result->version->minimum_php_version)->toBe('8.0')
        ->and($result->version->extensions)->toBeArray()
        ->and($result->version->extensions['openssl'])->toBeTrue() // phpversion('openssl') returns '1.1.1k' which is true
        ->and($result->version->extensions['zip'])->toBeTrue()     // phpversion('zip') returns '1.15.6' which is true
        ->and($result->version->extensions['json'])->toBeFalse()   // phpversion('json') returns false
        ->and($result->version->extensions['php(8.0)'])->toBeTrue(); // Current PHP (8.1.0) >= Minimum PHP (8.0) is true

    // Test a scenario where php version is too low (e.g., current PHP 7.4, minimum 8.0)
    reset_mocked_phpversion_extensions(); // Reset previous mocks
    set_mocked_phpversion_extension(null, '7.4.0'); // Mock current phpversion to be low

    $mockedResponseData['version']['minimum_php_version'] = '8.0'; // Minimum required 8.0

    Mockery::mock('alias:'.Updater::class) // Re-mock for a fresh call
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->andReturn(new Response(200, [], json_encode($mockedResponseData)));

    $result = Updater::checkForUpdate('1.0.0');
    expect($result->version->extensions['php(8.0)'])->toBeFalse(); // Current PHP (7.4.0) >= Minimum PHP (8.0) is false
});

test('checkForUpdate handles response without extensions property', function () {
    $mockedResponseData = [
        'success' => true,
        'version' => [
            'version' => '2.0.0',
            'minimum_php_version' => '8.0',
            // No 'extensions' property
        ],
    ];

    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->andReturn(new Response(200, [], json_encode($mockedResponseData)));

    $result = Updater::checkForUpdate('1.0.0');

    expect($result)->toBeObject()
        ->and($result->success)->toBeTrue()
        ->and($result->version)->toBeObject()
        ->and($result->version->version)->toBe('2.0.0')
        ->and(property_exists($result->version, 'extensions'))->toBeFalse(); // Extensions should not be added
});

test('checkForUpdate handles response with empty extensions array', function () {
    set_mocked_phpversion_extension(null, '8.1.0'); // For the php version check

    $mockedResponseData = [
        'success' => true,
        'version' => [
            'version' => '2.0.0',
            'minimum_php_version' => '8.0',
            'extensions' => json_encode([]), // Empty extensions list
        ],
    ];

    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->andReturn(new Response(200, [], json_encode($mockedResponseData)));

    $result = Updater::checkForUpdate('1.0.0');

    expect($result)->toBeObject()
        ->and($result->success)->toBeTrue()
        ->and($result->version)->toBeObject()
        ->and($result->version->extensions)->toBeArray()
        ->and($result->version->extensions)->toHaveCount(1) // Only PHP version check will be added
        ->and(array_key_exists('php(8.0)', $result->version->extensions))->toBeTrue();
});

test('checkForUpdate handles malformed JSON response', function () {
    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->andReturn(new Response(200, [], 'this is not json'));

    $result = Updater::checkForUpdate('1.0.0');

    expect($result)->toBeNull(); // json_decode will return null for malformed JSON
});

test('download constructs correct URL for development environment', function () {
    env('APP_ENV', 'development');

    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->with('downloads/file/2.0.0?type=update&is_dev=1&is_cmd=0', Mockery::subset(['timeout' => 100]))
        ->andReturn(new Response(200, [], 'zip_file_content'));

    // File::isDirectory defaults to false, so makeDirectory will be called.
    File::shouldReceive('isDirectory')->once()->andReturn(false);
    File::shouldReceive('makeDirectory')->once()->andReturn(true);

    $zipFilePath = Updater::download('2.0.0', 0);
    expect($zipFilePath)->toBeString();
    expect($zipFilePath)->toContain('upload.zip');
});

test('download constructs correct URL for production environment with is_cmd', function () {
    // env('APP_ENV') defaults to 'production'
    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->with('downloads/file/2.0.0?type=update&is_cmd=1', Mockery::subset(['timeout' => 100]))
        ->andReturn(new Response(200, [], 'zip_file_content'));

    File::shouldReceive('isDirectory')->once()->andReturn(false);
    File::shouldReceive('makeDirectory')->once()->andReturn(true);

    $zipFilePath = Updater::download('2.0.0', 1);
    expect($zipFilePath)->toBeString();
    expect($zipFilePath)->toContain('upload.zip');
});

test('download handles RequestException from getRemote', function () {
    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->andReturn(new RequestException('Download error', new \GuzzleHttp\Psr7\Request('GET', 'test')));

    $result = Updater::download('2.0.0');
    expect($result)->toBeArray()
        ->and($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('Download Exception');
});

test('download returns false if getRemote fails', function () {
    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->withAnyArgs()
        ->once()
        ->andReturn(null);

    $result = Updater::download('2.0.0');
    expect($result)->toBeFalse();
});

test('download returns false if getRemote returns non-200', function () {
    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->withAnyArgs()
        ->once()
        ->andReturn(new Response(404));

    $result = Updater::download('2.0.0');
    expect($result)->toBeFalse();
});

test('download creates temp directory if it does not exist', function () {
    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->andReturn(new Response(200, [], 'zip_file_content'));

    File::shouldReceive('isDirectory')->once()->andReturn(false);
    File::shouldReceive('makeDirectory')->once()->andReturn(true);

    $zipFilePath = Updater::download('2.0.0');
    expect($zipFilePath)->toBeString();
});

test('download does not create temp directory if it already exists', function () {
    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->andReturn(new Response(200, [], 'zip_file_content'));

    File::shouldReceive('isDirectory')->once()->andReturn(true);
    File::shouldNotReceive('makeDirectory'); // Should not be called if directory exists

    $zipFilePath = Updater::download('2.0.0');
    expect($zipFilePath)->toBeString();
});

test('download returns false if file_put_contents fails', function () {
    Mockery::mock('alias:'.Updater::class)
        ->makePartial()
        ->shouldReceive('getRemote')
        ->once()
        ->andReturn(new Response(200, [], 'zip_file_content'));

    File::shouldReceive('isDirectory')->andReturn(true); // Ensure no makeDirectory mock interferes

    set_mocked_file_put_contents_return(false); // Override global mock for this test

    $result = Updater::download('2.0.0');
    expect($result)->toBeFalse();
});

test('unzip throws exception if zip file does not exist', function () {
    // file_exists defaults to false due to reset_mocked_file_exists()
    expect(fn () => Updater::unzip('/non/existent/path/upload.zip'))
        ->toThrow(\Exception::class, 'Zip file not found');
});

test('unzip creates extract directory if it does not exist', function () {
    $zipFilePath = '/mock/storage/path/temp-123/upload.zip';
    set_mocked_file_exists($zipFilePath); // Make the file exist for file_exists()

    // Mock ZipArchive using Mockery's overload
    $zipArchiveMock = Mockery::mock('overload:' . ZipArchive::class);
    $zipArchiveMock->shouldReceive('open')->with($zipFilePath)->once()->andReturn(true);
    $zipArchiveMock->shouldReceive('extractTo')->once()->andReturn(true);
    $zipArchiveMock->shouldReceive('close')->once()->andReturn(true);

    File::shouldReceive('isDirectory')->once()->andReturn(false); // For temp_extract_dir
    File::shouldReceive('makeDirectory')->once()->andReturn(true);
    File::shouldReceive('delete')->once()->with($zipFilePath)->andReturn(true);

    $extractedDir = Updater::unzip($zipFilePath);
    expect($extractedDir)->toBeString();
    expect($extractedDir)->toContain('/mock/storage/path/temp2-');
});

test('unzip does not create extract directory if it already exists', function () {
    $zipFilePath = '/mock/storage/path/temp-123/upload.zip';
    set_mocked_file_exists($zipFilePath);

    $zipArchiveMock = Mockery::mock('overload:' . ZipArchive::class);
    $zipArchiveMock->shouldReceive('open')->with($zipFilePath)->once()->andReturn(true);
    $zipArchiveMock->shouldReceive('extractTo')->once()->andReturn(true);
    $zipArchiveMock->shouldReceive('close')->once()->andReturn(true);

    File::shouldReceive('isDirectory')->once()->andReturn(true); // For temp_extract_dir
    File::shouldNotReceive('makeDirectory'); // Should not be called if directory exists
    File::shouldReceive('delete')->once()->with($zipFilePath)->andReturn(true);

    $extractedDir = Updater::unzip($zipFilePath);
    expect($extractedDir)->toBeString();
});

test('unzip deletes the original zip file', function () {
    $zipFilePath = '/mock/storage/path/temp-123/upload.zip';
    set_mocked_file_exists($zipFilePath);

    $zipArchiveMock = Mockery::mock('overload:' . ZipArchive::class);
    $zipArchiveMock->shouldReceive('open')->andReturn(true);
    $zipArchiveMock->shouldReceive('extractTo')->andReturn(true);
    $zipArchiveMock->shouldReceive('close')->andReturn(true);

    File::shouldReceive('delete')->once()->with($zipFilePath)->andReturn(true);

    Updater::unzip($zipFilePath);
});

test('unzip handles ZipArchive open failure gracefully', function () {
    $zipFilePath = '/mock/storage/path/temp-123/upload.zip';
    set_mocked_file_exists($zipFilePath);

    $zipArchiveMock = Mockery::mock('overload:' . ZipArchive::class);
    $zipArchiveMock->shouldReceive('open')->with($zipFilePath)->once()->andReturn(false); // Simulate open failure
    $zipArchiveMock->shouldNotReceive('extractTo'); // Should not be called
    $zipArchiveMock->shouldReceive('close')->once()->andReturn(true); // Close is still called

    File::shouldReceive('delete')->once()->with($zipFilePath)->andReturn(true); // Zip file is still deleted

    $extractedDir = Updater::unzip($zipFilePath);
    expect($extractedDir)->toBeString(); // It will still return the temp dir path
});

test('copyFiles returns true on successful copy and directory deletion', function () {
    $tempExtractDir = '/mock/storage/path/temp2-123';

    File::shouldReceive('copyDirectory')
        ->once()
        ->with($tempExtractDir . '/Crater', '/mock/base/path')
        ->andReturn(true);

    File::shouldReceive('deleteDirectory')
        ->once()
        ->with($tempExtractDir)
        ->andReturn(true);

    $result = Updater::copyFiles($tempExtractDir);
    expect($result)->toBeTrue();
});

test('copyFiles returns false if copyDirectory fails', function () {
    $tempExtractDir = '/mock/storage/path/temp2-123';

    File::shouldReceive('copyDirectory')
        ->once()
        ->with($tempExtractDir . '/Crater', '/mock/base/path')
        ->andReturn(false);

    File::shouldNotReceive('deleteDirectory'); // Should not be called if copy fails

    $result = Updater::copyFiles($tempExtractDir);
    expect($result)->toBeFalse();
});

test('deleteFiles deletes all specified files', function () {
    $filesToDelete = ['file1.txt', 'dir/file2.php'];
    $json = json_encode($filesToDelete);

    File::shouldReceive('delete')
        ->with('/mock/base/path/file1.txt')
        ->once()
        ->andReturn(true);

    File::shouldReceive('delete')
        ->with('/mock/base/path/dir/file2.php')
        ->once()
        ->andReturn(true);

    $result = Updater::deleteFiles($json);
    expect($result)->toBeTrue();
});

test('deleteFiles handles empty file list', function () {
    $json = json_encode([]);

    File::shouldNotReceive('delete'); // No files to delete

    $result = Updater::deleteFiles($json);
    expect($result)->toBeTrue();
});

test('deleteFiles handles non-existent files gracefully', function () {
    $filesToDelete = ['non_existent.txt'];
    $json = json_encode($filesToDelete);

    // File::delete should still be called, and typically returns true even if file doesn't exist
    File::shouldReceive('delete')
        ->with('/mock/base/path/non_existent.txt')
        ->once()
        ->andReturn(true);

    $result = Updater::deleteFiles($json);
    expect($result)->toBeTrue();
});

test('deleteFiles handles malformed JSON input', function () {
    $json = 'this is not json';

    // json_decode will return null, foreach loop won't execute
    File::shouldNotReceive('delete');

    $result = Updater::deleteFiles($json);
    expect($result)->toBeTrue(); // The method always returns true
});


test('migrateUpdate calls Artisan migrate command', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('migrate --force');

    $result = Updater::migrateUpdate();
    expect($result)->toBeTrue();
});

test('finishUpdate dispatches UpdateFinished event and returns success response', function () {
    $installedVersion = '1.0.0';
    $newVersion = '2.0.0';

    $result = Updater::finishUpdate($installedVersion, $newVersion);

    Event::assertDispatched(UpdateFinished::class, function ($event) use ($installedVersion, $newVersion) {
        return $event->installed === $installedVersion && $event->version === $newVersion;
    });

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result['error'])->toBeFalse()
        ->and($result['data'])->toBeArray()->toBeEmpty();
});




afterEach(function () {
    Mockery::close();
});
