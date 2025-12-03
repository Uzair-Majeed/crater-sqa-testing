<?php

use Crater\Console\Commands\UpdateCommand;
use Crater\Models\Setting;
use Crater\Space\Updater;
use Illuminate\Console\Command;
use Mockery as m;

beforeEach(function () {
    // Create a partial mock of the UpdateCommand.
    // This allows us to mock its own public methods (like getInstalledVersion, download, etc.)
    // while still executing the real logic of handle or other methods where not mocked.
    $this->command = m::mock(UpdateCommand::class)->makePartial();

    // Mock parent class methods for console output/interaction
    // Make them return self for chaining to avoid issues with return types, though not strictly necessary.
    $this->command->shouldReceive('info')->andReturnSelf();
    $this->command->shouldReceive('error')->andReturnSelf();
    $this->command->shouldReceive('line')->andReturnSelf();
    $this->command->shouldReceive('warn')->andReturnSelf(); // In case it's used elsewhere, good to include.

    // Mock internal static dependencies for tests targeting specific methods
    // (e.g., getInstalledVersion, getLatestVersionResponse when testing them in isolation).
    // For `handle` method tests, we mock the command's *own* methods.
    m::mock('alias:' . Setting::class);
    m::mock('alias:' . Updater::class);
});

afterEach(function () {
    m::close();
});

test('constructor creates command instance', function () {
    // The main purpose of the constructor here is to call the parent's constructor.
    // For unit testing, simply ensuring the object can be instantiated is usually sufficient.
    // Elaborate mocking of the parent constructor is often overkill unless it has complex side effects.
    $command = new UpdateCommand();
    expect($command)->toBeInstanceOf(UpdateCommand::class);
    expect($command->getSignature())->toBe('crater:update');
    expect($command->getDescription())->toBe('Automatically update your crater app');
});

test('getInstalledVersion returns the installed version from settings', function () {
    $expectedVersion = '1.0.0';
    Setting::shouldReceive('getSetting')
        ->once()
        ->with('version')
        ->andReturn($expectedVersion);

    $result = $this->command->getInstalledVersion();
    expect($result)->toBe($expectedVersion);
});

test('getLatestVersionResponse returns new version object if successful and all extensions are met', function () {
    $installedVersion = '1.0.0';
    $newVersionObject = (object) [
        'version' => '1.0.1',
        'extensions' => [
            'ext1' => true,
            'ext2' => true,
        ],
    ];
    $updaterApiResponse = (object) [
        'success' => true,
        'version' => $newVersionObject,
    ];

    $this->command->installed = $installedVersion; // Set property for `info` message
    $this->command->shouldReceive('info')->with('Your currently installed version is ' . $installedVersion)->once();
    $this->command->shouldReceive('line')->once();
    $this->command->shouldReceive('info')->with('Checking for update...')->once();
    $this->command->shouldReceive('info')->with('✅ ext1')->once();
    $this->command->shouldReceive('info')->with('✅ ext2')->once();

    Updater::shouldReceive('checkForUpdate')
        ->once()
        ->with($installedVersion)
        ->andReturn($updaterApiResponse);

    $result = $this->command->getLatestVersionResponse();
    expect($result)->toBe($newVersionObject);
});

test('getLatestVersionResponse returns extension_required string if successful but extensions are not met', function () {
    $installedVersion = '1.0.0';
    $newVersionObject = (object) [
        'version' => '1.0.1',
        'extensions' => [
            'ext1' => true,
            'ext2' => false, // This extension is not met
        ],
    ];
    $updaterApiResponse = (object) [
        'success' => true,
        'version' => $newVersionObject,
    ];

    $this->command->installed = $installedVersion;
    $this->command->shouldReceive('info')->with('Your currently installed version is ' . $installedVersion)->once();
    $this->command->shouldReceive('line')->once();
    $this->command->shouldReceive('info')->with('Checking for update...')->once();
    $this->command->shouldReceive('info')->with('✅ ext1')->once();
    $this->command->shouldReceive('info')->with('❌ ext2')->once();

    Updater::shouldReceive('checkForUpdate')
        ->once()
        ->with($installedVersion)
        ->andReturn($updaterApiResponse);

    $result = $this->command->getLatestVersionResponse();
    expect($result)->toBe('extension_required');
});

test('getLatestVersionResponse returns false if update check is not successful', function () {
    $installedVersion = '1.0.0';
    $updaterApiResponse = (object) [
        'success' => false,
        'version' => null, // No version info if not successful
    ];

    $this->command->installed = $installedVersion;
    $this->command->shouldReceive('info')->with('Your currently installed version is ' . $installedVersion)->once();
    $this->command->shouldReceive('line')->once();
    $this->command->shouldReceive('info')->with('Checking for update...')->once();

    Updater::shouldReceive('checkForUpdate')
        ->once()
        ->with($installedVersion)
        ->andReturn($updaterApiResponse);

    $result = $this->command->getLatestVersionResponse();
    expect($result)->toBeFalse();
});

test('getLatestVersionResponse returns false and calls error on exception during update check', function () {
    $installedVersion = '1.0.0';
    $errorMessage = 'Network error during update check';

    $this->command->installed = $installedVersion;
    $this->command->shouldReceive('info')->with('Your currently installed version is ' . $installedVersion)->once();
    $this->command->shouldReceive('line')->once();
    $this->command->shouldReceive('info')->with('Checking for update...')->once();
    $this->command->shouldReceive('error')->with($errorMessage)->once();

    Updater::shouldReceive('checkForUpdate')
        ->once()
        ->with($installedVersion)
        ->andThrow(new Exception($errorMessage));

    $result = $this->command->getLatestVersionResponse();
    expect($result)->toBeFalse();
});

test('download returns path on successful download', function () {
    $versionToDownload = '1.0.1';
    $expectedPath = '/tmp/crater-update-1.0.1.zip';

    $this->command->version = $versionToDownload; // Set property as it's used by the method
    $this->command->shouldReceive('info')->with('Downloading update...')->once();

    Updater::shouldReceive('download')
        ->once()
        ->with($versionToDownload, 1)
        ->andReturn($expectedPath);

    $result = $this->command->download();
    expect($result)->toBe($expectedPath);
});

test('download returns false and calls error if Updater::download returns non-string', function () {
    $versionToDownload = '1.0.1';
    $invalidReturn = true; // Not a string

    $this->command->version = $versionToDownload;
    $this->command->shouldReceive('info')->with('Downloading update...')->once();
    $this->command->shouldReceive('error')->with('Download exception')->once();

    Updater::shouldReceive('download')
        ->once()
        ->with($versionToDownload, 1)
        ->andReturn($invalidReturn);

    $result = $this->command->download();
    expect($result)->toBeFalse();
});

test('download returns false and calls error on exception during download', function () {
    $versionToDownload = '1.0.1';
    $errorMessage = 'Failed to fetch update package';

    $this->command->version = $versionToDownload;
    $this->command->shouldReceive('info')->with('Downloading update...')->once();
    $this->command->shouldReceive('error')->with($errorMessage)->once();

    Updater::shouldReceive('download')
        ->once()
        ->with($versionToDownload, 1)
        ->andThrow(new Exception($errorMessage));

    $result = $this->command->download();
    expect($result)->toBeFalse();
});

test('unzip returns path on successful unzipping', function () {
    $downloadedPath = '/tmp/crater-update-1.0.1.zip';
    $expectedUnzippedPath = '/tmp/crater-unzipped-1.0.1';

    $this->command->shouldReceive('info')->with('Unzipping update package...')->once();

    Updater::shouldReceive('unzip')
        ->once()
        ->with($downloadedPath)
        ->andReturn($expectedUnzippedPath);

    $result = $this->command->unzip($downloadedPath);
    expect($result)->toBe($expectedUnzippedPath);
});

test('unzip returns false and calls error if Updater::unzip returns non-string', function () {
    $downloadedPath = '/tmp/crater-update-1.0.1.zip';
    $invalidReturn = false; // Not a string

    $this->command->shouldReceive('info')->with('Unzipping update package...')->once();
    $this->command->shouldReceive('error')->with('Unzipping exception')->once();

    Updater::shouldReceive('unzip')
        ->once()
        ->with($downloadedPath)
        ->andReturn($invalidReturn);

    $result = $this->command->unzip($downloadedPath);
    expect($result)->toBeFalse();
});

test('unzip returns false and calls error on exception during unzipping', function () {
    $downloadedPath = '/tmp/crater-update-1.0.1.zip';
    $errorMessage = 'Failed to extract package';

    $this->command->shouldReceive('info')->with('Unzipping update package...')->once();
    $this->command->shouldReceive('error')->with($errorMessage)->once();

    Updater::shouldReceive('unzip')
        ->once()
        ->with($downloadedPath)
        ->andThrow(new Exception($errorMessage));

    $result = $this->command->unzip($downloadedPath);
    expect($result)->toBeFalse();
});

test('copyFiles returns true on successful file copying', function () {
    $unzippedPath = '/tmp/crater-unzipped-1.0.1';

    $this->command->shouldReceive('info')->with('Copying update files...')->once();

    Updater::shouldReceive('copyFiles')
        ->once()
        ->with($unzippedPath)
        ->andReturn(null); // Assuming it returns void or null on success

    $result = $this->command->copyFiles($unzippedPath);
    expect($result)->toBeTrue();
});

test('copyFiles returns false and calls error on exception during file copying', function () {
    $unzippedPath = '/tmp/crater-unzipped-1.0.1';
    $errorMessage = 'File copy permissions error';

    $this->command->shouldReceive('info')->with('Copying update files...')->once();
    $this->command->shouldReceive('error')->with($errorMessage)->once();

    Updater::shouldReceive('copyFiles')
        ->once()
        ->with($unzippedPath)
        ->andThrow(new Exception($errorMessage));

    $result = $this->command->copyFiles($unzippedPath);
    expect($result)->toBeFalse();
});

test('deleteFiles returns true on successful file deletion', function () {
    $filesToDelete = ['old_file.php', 'config/old.php'];

    $this->command->shouldReceive('info')->with('Deleting unused old files...')->once();

    Updater::shouldReceive('deleteFiles')
        ->once()
        ->with($filesToDelete)
        ->andReturn(null); // Assuming it returns void or null on success

    $result = $this->command->deleteFiles($filesToDelete);
    expect($result)->toBeTrue();
});

test('deleteFiles returns false and calls error on exception during file deletion', function () {
    $filesToDelete = ['old_file.php', 'config/old.php'];
    $errorMessage = 'Failed to delete some files';

    $this->command->shouldReceive('info')->with('Deleting unused old files...')->once();
    $this->command->shouldReceive('error')->with($errorMessage)->once();

    Updater::shouldReceive('deleteFiles')
        ->once()
        ->with($filesToDelete)
        ->andThrow(new Exception($errorMessage));

    $result = $this->command->deleteFiles($filesToDelete);
    expect($result)->toBeFalse();
});

test('migrateUpdate returns true on successful migration', function () {
    $this->command->shouldReceive('info')->with('Running Migrations...')->once();

    Updater::shouldReceive('migrateUpdate')
        ->once()
        ->andReturn(null); // Assuming it returns void or null on success

    $result = $this->command->migrateUpdate();
    expect($result)->toBeTrue();
});

test('migrateUpdate returns false and calls error on exception during migration', function () {
    $errorMessage = 'Database migration failed';

    $this->command->shouldReceive('info')->with('Running Migrations...')->once();
    $this->command->shouldReceive('error')->with($errorMessage)->once();

    Updater::shouldReceive('migrateUpdate')
        ->once()
        ->andThrow(new Exception($errorMessage));

    $result = $this->command->migrateUpdate();
    expect($result)->toBeFalse();
});

test('finish returns true on successful finalization', function () {
    $installedVersion = '1.0.0';
    $newVersion = '1.0.1';

    $this->command->installed = $installedVersion; // Set properties for the method call
    $this->command->version = $newVersion;
    $this->command->shouldReceive('info')->with('Finishing update...')->once();

    Updater::shouldReceive('finishUpdate')
        ->once()
        ->with($installedVersion, $newVersion)
        ->andReturn(null); // Assuming it returns void or null on success

    $result = $this->command->finish();
    expect($result)->toBeTrue();
});

test('finish returns false and calls error on exception during finalization', function () {
    $installedVersion = '1.0.0';
    $newVersion = '1.0.1';
    $errorMessage = 'Failed to finalize update records';

    $this->command->installed = $installedVersion;
    $this->command->version = $newVersion;
    $this->command->shouldReceive('info')->with('Finishing update...')->once();
    $this->command->shouldReceive('error')->with($errorMessage)->once();

    Updater::shouldReceive('finishUpdate')
        ->once()
        ->with($installedVersion, $newVersion)
        ->andThrow(new Exception($errorMessage));

    $result = $this->command->finish();
    expect($result)->toBeFalse();
});

// handle method tests
test('handle exits early if extension_required status is returned', function () {
    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn('1.0.0');
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn('extension_required');
    $this->command->shouldReceive('info')->with('Sorry! Your system does not meet the minimum requirements for this update.')->once();
    $this->command->shouldReceive('info')->with('Please retry after installing the required version/extensions.')->once();
    $this->command->shouldNotReceive('confirm'); // Should not prompt for confirmation

    $this->command->handle();
});

test('handle exits early if no update is available', function () {
    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn('1.0.0');
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn(false); // No update
    $this->command->shouldReceive('info')->with('No Update Available! You are already on the latest version.')->once();
    $this->command->shouldNotReceive('confirm'); // Should not prompt for confirmation

    $this->command->handle();
});

test('handle exits early if user declines the update', function () {
    $installedVersion = '1.0.0';
    $newVersionResponse = (object) [
        'version' => '1.0.1',
        'extensions' => ['php' => true],
    ];

    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn($installedVersion);
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn($newVersionResponse);
    $this->command->shouldReceive('confirm')->with("Do you wish to update to {$newVersionResponse->version}?")->once()->andReturn(false); // User declines
    $this->command->shouldNotReceive('download'); // Should not proceed to download

    $this->command->handle();
});

test('handle exits early if download fails', function () {
    $installedVersion = '1.0.0';
    $newVersionResponse = (object) [
        'version' => '1.0.1',
        'extensions' => ['php' => true],
    ];

    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn($installedVersion);
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn($newVersionResponse);
    $this->command->shouldReceive('confirm')->once()->andReturn(true); // User confirms
    $this->command->shouldReceive('download')->once()->andReturn(false); // Download fails
    $this->command->shouldNotReceive('unzip'); // Should not proceed to unzip

    $this->command->handle();
});

test('handle exits early if unzip fails', function () {
    $installedVersion = '1.0.0';
    $newVersionResponse = (object) [
        'version' => '1.0.1',
        'extensions' => ['php' => true],
    ];
    $downloadedPath = '/tmp/download.zip';

    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn($installedVersion);
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn($newVersionResponse);
    $this->command->shouldReceive('confirm')->once()->andReturn(true);
    $this->command->shouldReceive('download')->once()->andReturn($downloadedPath);
    $this->command->shouldReceive('unzip')->with($downloadedPath)->once()->andReturn(false); // Unzip fails
    $this->command->shouldNotReceive('copyFiles'); // Should not proceed to copy files

    $this->command->handle();
});

test('handle exits early if copyFiles fails', function () {
    $installedVersion = '1.0.0';
    $newVersionResponse = (object) [
        'version' => '1.0.1',
        'extensions' => ['php' => true],
    ];
    $downloadedPath = '/tmp/download.zip';
    $unzippedPath = '/tmp/unzipped_dir';

    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn($installedVersion);
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn($newVersionResponse);
    $this->command->shouldReceive('confirm')->once()->andReturn(true);
    $this->command->shouldReceive('download')->once()->andReturn($downloadedPath);
    $this->command->shouldReceive('unzip')->with($downloadedPath)->once()->andReturn($unzippedPath);
    $this->command->shouldReceive('copyFiles')->with($unzippedPath)->once()->andReturn(false); // Copy fails
    $this->command->shouldNotReceive('deleteFiles'); // Should not proceed to delete files

    $this->command->handle();
});

test('handle exits early if deleteFiles fails (when deleted_files are specified)', function () {
    $installedVersion = '1.0.0';
    $newVersionResponse = (object) [
        'version' => '1.0.1',
        'extensions' => ['php' => true],
        'deleted_files' => ['old_file.txt'],
    ];
    $downloadedPath = '/tmp/download.zip';
    $unzippedPath = '/tmp/unzipped_dir';

    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn($installedVersion);
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn($newVersionResponse);
    $this->command->shouldReceive('confirm')->once()->andReturn(true);
    $this->command->shouldReceive('download')->once()->andReturn($downloadedPath);
    $this->command->shouldReceive('unzip')->with($downloadedPath)->once()->andReturn($unzippedPath);
    $this->command->shouldReceive('copyFiles')->with($unzippedPath)->once()->andReturn(true);
    $this->command->shouldReceive('deleteFiles')->with($newVersionResponse->deleted_files)->once()->andReturn(false); // Delete fails
    $this->command->shouldNotReceive('migrateUpdate'); // Should not proceed to migrate

    $this->command->handle();
});

test('handle exits early if migrateUpdate fails', function () {
    $installedVersion = '1.0.0';
    $newVersionResponse = (object) [
        'version' => '1.0.1',
        'extensions' => ['php' => true],
        'deleted_files' => [], // No deleted files
    ];
    $downloadedPath = '/tmp/download.zip';
    $unzippedPath = '/tmp/unzipped_dir';

    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn($installedVersion);
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn($newVersionResponse);
    $this->command->shouldReceive('confirm')->once()->andReturn(true);
    $this->command->shouldReceive('download')->once()->andReturn($downloadedPath);
    $this->command->shouldReceive('unzip')->with($downloadedPath)->once()->andReturn($unzippedPath);
    $this->command->shouldReceive('copyFiles')->with($unzippedPath)->once()->andReturn(true);
    $this->command->shouldNotReceive('deleteFiles'); // Skipped because deleted_files is empty
    $this->command->shouldReceive('migrateUpdate')->once()->andReturn(false); // Migrate fails
    $this->command->shouldNotReceive('finish'); // Should not proceed to finish

    $this->command->handle();
});

test('handle exits early if finish fails', function () {
    $installedVersion = '1.0.0';
    $newVersionResponse = (object) [
        'version' => '1.0.1',
        'extensions' => ['php' => true],
    ]; // No deleted_files key
    $downloadedPath = '/tmp/download.zip';
    $unzippedPath = '/tmp/unzipped_dir';

    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn($installedVersion);
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn($newVersionResponse);
    $this->command->shouldReceive('confirm')->once()->andReturn(true);
    $this->command->shouldReceive('download')->once()->andReturn($downloadedPath);
    $this->command->shouldReceive('unzip')->with($downloadedPath)->once()->andReturn($unzippedPath);
    $this->command->shouldReceive('copyFiles')->with($unzippedPath)->once()->andReturn(true);
    $this->command->shouldNotReceive('deleteFiles'); // Skipped because 'deleted_files' key is not present
    $this->command->shouldReceive('migrateUpdate')->once()->andReturn(true);
    $this->command->shouldReceive('finish')->once()->andReturn(false); // Finish fails
    $this->command->shouldNotReceive('info')->with('Successfully updated'); // Should not reach final success message

    $this->command->handle();
});

test('handle successfully updates with deleted files specified', function () {
    $installedVersion = '1.0.0';
    $newVersionResponse = (object) [
        'version' => '1.0.1',
        'extensions' => ['php' => true],
        'deleted_files' => ['old_asset.css', 'old_script.js'],
    ];
    $downloadedPath = '/tmp/download.zip';
    $unzippedPath = '/tmp/unzipped_dir';

    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn($installedVersion);
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn($newVersionResponse);
    $this->command->shouldReceive('confirm')->with("Do you wish to update to {$newVersionResponse->version}?")->once()->andReturn(true);
    $this->command->shouldReceive('download')->once()->andReturn($downloadedPath);
    $this->command->shouldReceive('unzip')->with($downloadedPath)->once()->andReturn($unzippedPath);
    $this->command->shouldReceive('copyFiles')->with($unzippedPath)->once()->andReturn(true);
    $this->command->shouldReceive('deleteFiles')->with($newVersionResponse->deleted_files)->once()->andReturn(true);
    $this->command->shouldReceive('migrateUpdate')->once()->andReturn(true);
    $this->command->shouldReceive('finish')->once()->andReturn(true);
    $this->command->shouldReceive('info')->with('Successfully updated to ' . $newVersionResponse->version)->once();

    $this->command->handle();
});

test('handle successfully updates without deleted files specified (empty array)', function () {
    $installedVersion = '1.0.0';
    $newVersionResponse = (object) [
        'version' => '1.0.1',
        'extensions' => ['php' => true],
        'deleted_files' => [], // Empty array
    ];
    $downloadedPath = '/tmp/download.zip';
    $unzippedPath = '/tmp/unzipped_dir';

    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn($installedVersion);
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn($newVersionResponse);
    $this->command->shouldReceive('confirm')->with("Do you wish to update to {$newVersionResponse->version}?")->once()->andReturn(true);
    $this->command->shouldReceive('download')->once()->andReturn($downloadedPath);
    $this->command->shouldReceive('unzip')->with($downloadedPath)->once()->andReturn($unzippedPath);
    $this->command->shouldReceive('copyFiles')->with($unzippedPath)->once()->andReturn(true);
    $this->command->shouldNotReceive('deleteFiles'); // Not called when deleted_files is empty
    $this->command->shouldReceive('migrateUpdate')->once()->andReturn(true);
    $this->command->shouldReceive('finish')->once()->andReturn(true);
    $this->command->shouldReceive('info')->with('Successfully updated to ' . $newVersionResponse->version)->once();

    $this->command->handle();
});

test('handle successfully updates when deleted_files key is not present', function () {
    $installedVersion = '1.0.0';
    $newVersionResponse = (object) [
        'version' => '1.0.1',
        'extensions' => ['php' => true],
        // 'deleted_files' key is entirely absent
    ];
    $downloadedPath = '/tmp/download.zip';
    $unzippedPath = '/tmp/unzipped_dir';

    $this->command->shouldReceive('getInstalledVersion')->once()->andReturn($installedVersion);
    $this->command->shouldReceive('getLatestVersionResponse')->once()->andReturn($newVersionResponse);
    $this->command->shouldReceive('confirm')->with("Do you wish to update to {$newVersionResponse->version}?")->once()->andReturn(true);
    $this->command->shouldReceive('download')->once()->andReturn($downloadedPath);
    $this->command->shouldReceive('unzip')->with($downloadedPath)->once()->andReturn($unzippedPath);
    $this->command->shouldReceive('copyFiles')->with($unzippedPath)->once()->andReturn(true);
    $this->command->shouldNotReceive('deleteFiles'); // Not called when 'deleted_files' key is absent
    $this->command->shouldReceive('migrateUpdate')->once()->andReturn(true);
    $this->command->shouldReceive('finish')->once()->andReturn(true);
    $this->command->shouldReceive('info')->with('Successfully updated to ' . $newVersionResponse->version)->once();

    $this->command->handle();
});



