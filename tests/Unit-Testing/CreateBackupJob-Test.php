<?php

use Crater\Jobs\CreateBackupJob;
use Crater\Models\FileDisk;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use Spatie\Backup\Tasks\Backup\BackupJob;
use Illuminate\Support\Arr;

// Pest's way of setting up the testing environment for each test.
beforeEach(function () {
    Mockery::close(); // Ensure mocks are cleaned up after each test

    // Reset config state for each test.
    // `this->currentConfig` makes it available within test closures.
    $this->currentConfig = [
        'backup' => [
            'backup' => [
                'destination' => [
                    'disks' => [],
                ],
            ],
        ],
    ];

    // Mock the global config() helper to simulate its behavior and state changes.
    override('config', function ($key, $value = null) {
        if (is_array($key)) {
            // This is a setter call, e.g., config(['backup.backup.destination.disks' => [...]]);
            foreach ($key as $k => $v) {
                Arr::set($this->currentConfig, $k, $v);
            }
            return null; // Setters typically return void or null
        } elseif ($value !== null) {
            // This is a getter call with a default, e.g., config('app.env', 'production');
            return Arr::get($this->currentConfig, $key, $value);
        } else {
            // This is a getter call, e.g., config('backup');
            return Arr::get($this->currentConfig, $key);
        }
    });

    // Initialize `envOverrides` for this test run.
    $this->envOverrides = [];

    // Mock the global env() helper.
    // Individual tests can set specific env variables by modifying `$this->envOverrides`.
    override('env', function ($key, $default = null) {
        if (array_key_exists($key, $this->envOverrides)) {
            return $this->envOverrides[$key];
        }
        // Default behavior for DYNAMIC_DISK_PREFIX if not explicitly overridden.
        if ($key === 'DYNAMIC_DISK_PREFIX') {
            return 'temp_';
        }
        return $default;
    });
});

// Test for the constructor to ensure data is correctly assigned.
test('it constructs with data', function () {
    $data = ['test' => 'data'];
    $job = new CreateBackupJob($data);
    $reflection = new ReflectionClass($job);
    $property = $reflection->getProperty('data');
    $property->setAccessible(true); // Access protected property
    expect($property->getValue($job))->toBe($data);
});

// Test `handle` method with default settings (no specific 'option').
test('handle creates a backup job with default settings when no option is provided', function () {
    // Mock `FileDisk` instance and its behavior.
    $mockFileDisk = Mockery::mock(FileDisk::class);
    $mockFileDisk->shouldReceive('setConfig')->once();
    $mockFileDisk->driver = 's3'; // Simulate a driver property

    // Mock static `FileDisk::find` method.
    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('find')
        ->with(1)
        ->once()
        ->andReturn($mockFileDisk);

    // Mock `BackupJob` and its expected method calls.
    $mockBackupJob = Mockery::mock(BackupJob::class);
    $mockBackupJob->shouldNotReceive('dontBackupFilesystem');
    $mockBackupJob->shouldNotReceive('dontBackupDatabases');
    $mockBackupJob->shouldNotReceive('setFilename'); // No option, so no custom filename
    $mockBackupJob->shouldReceive('run')->once();

    // Mock static `BackupJobFactory::createFromArray` method.
    Mockery::mock('alias:'.BackupJobFactory::class)
        ->shouldReceive('createFromArray')
        ->withArgs(function ($config) use ($mockFileDisk) {
            // Verify the configuration passed to `createFromArray` includes the dynamic disk.
            return isset($config['backup']['destination']['disks']) &&
                   $config['backup']['destination']['disks'] === ['temp_'.$mockFileDisk->driver];
        })
        ->once()
        ->andReturn($mockBackupJob);

    $data = [
        'file_disk_id' => 1,
        'option' => '', // No specific option
    ];

    $job = new CreateBackupJob($data);
    $job->handle();
});

// Test `handle` method with the 'only-db' option.
test('handle creates a backup job with "only-db" option', function () {
    $mockFileDisk = Mockery::mock(FileDisk::class);
    $mockFileDisk->shouldReceive('setConfig')->once();
    $mockFileDisk->driver = 'dropbox';

    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('find')
        ->with(2)
        ->once()
        ->andReturn($mockFileDisk);

    $mockBackupJob = Mockery::mock(BackupJob::class);
    $mockBackupJob->shouldReceive('dontBackupFilesystem')->once(); // Expect this call
    $mockBackupJob->shouldNotReceive('dontBackupDatabases');
    $mockBackupJob->shouldReceive('setFilename')
        ->once()
        ->with(Mockery::on(fn ($filename) => str_starts_with($filename, 'only-db-') && str_ends_with($filename, '.zip') &&
                             preg_match('/\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}/', $filename))); // Verify filename format
    $mockBackupJob->shouldReceive('run')->once();

    Mockery::mock('alias:'.BackupJobFactory::class)
        ->shouldReceive('createFromArray')
        ->withArgs(function ($config) use ($mockFileDisk) {
            return isset($config['backup']['destination']['disks']) &&
                   $config['backup']['destination']['disks'] === ['temp_'.$mockFileDisk->driver];
        })
        ->once()
        ->andReturn($mockBackupJob);

    $data = [
        'file_disk_id' => 2,
        'option' => 'only-db',
    ];

    $job = new CreateBackupJob($data);
    $job->handle();
});

// Test `handle` method with the 'only-files' option.
test('handle creates a backup job with "only-files" option', function () {
    $mockFileDisk = Mockery::mock(FileDisk::class);
    $mockFileDisk->shouldReceive('setConfig')->once();
    $mockFileDisk->driver = 'ftp';

    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('find')
        ->with(3)
        ->once()
        ->andReturn($mockFileDisk);

    $mockBackupJob = Mockery::mock(BackupJob::class);
    $mockBackupJob->shouldNotReceive('dontBackupFilesystem');
    $mockBackupJob->shouldReceive('dontBackupDatabases')->once(); // Expect this call
    $mockBackupJob->shouldReceive('setFilename')
        ->once()
        ->with(Mockery::on(fn ($filename) => str_starts_with($filename, 'only-files-') && str_ends_with($filename, '.zip') &&
                             preg_match('/\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}/', $filename))); // Verify filename format
    $mockBackupJob->shouldReceive('run')->once();

    Mockery::mock('alias:'.BackupJobFactory::class)
        ->shouldReceive('createFromArray')
        ->withArgs(function ($config) use ($mockFileDisk) {
            return isset($config['backup']['destination']['disks']) &&
                   $config['backup']['destination']['disks'] === ['temp_'.$mockFileDisk->driver];
        })
        ->once()
        ->andReturn($mockBackupJob);

    $data = [
        'file_disk_id' => 3,
        'option' => 'only-files',
    ];

    $job = new CreateBackupJob($data);
    $job->handle();
});

// Test `handle` method with a custom option (not 'only-db' or 'only-files').
test('handle creates a backup job with a custom option that is not only-db or only-files', function () {
    $mockFileDisk = Mockery::mock(FileDisk::class);
    $mockFileDisk->shouldReceive('setConfig')->once();
    $mockFileDisk->driver = 'local';

    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('find')
        ->with(4)
        ->once()
        ->andReturn($mockFileDisk);

    $mockBackupJob = Mockery::mock(BackupJob::class);
    $mockBackupJob->shouldNotReceive('dontBackupFilesystem');
    $mockBackupJob->shouldNotReceive('dontBackupDatabases');
    $mockBackupJob->shouldReceive('setFilename')
        ->once()
        ->with(Mockery::on(fn ($filename) => str_starts_with($filename, 'custom-option-') && str_ends_with($filename, '.zip') &&
                             preg_match('/\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}/', $filename))); // Verify filename format
    $mockBackupJob->shouldReceive('run')->once();

    Mockery::mock('alias:'.BackupJobFactory::class)
        ->shouldReceive('createFromArray')
        ->withArgs(function ($config) use ($mockFileDisk) {
            return isset($config['backup']['destination']['disks']) &&
                   $config['backup']['destination']['disks'] === ['temp_'.$mockFileDisk->driver];
        })
        ->once()
        ->andReturn($mockBackupJob);

    $data = [
        'file_disk_id' => 4,
        'option' => 'custom_option', // Custom option
    ];

    $job = new CreateBackupJob($data);
    $job->handle();
});

// Test error handling when `FileDisk::find` returns null.
test('handle throws exception if FileDisk not found', function () {
    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('find')
        ->with(99)
        ->once()
        ->andReturn(null); // Simulate FileDisk not found

    $data = [
        'file_disk_id' => 99,
        'option' => '',
    ];

    $job = new CreateBackupJob($data);

    // Expecting an Error because `setConfig()` is called on a null object.
    $this->expectException(Error::class);
    $this->expectExceptionMessage('Call to a member function setConfig() on null');

    $job->handle();
});

// Test `handle` method when `DYNAMIC_DISK_PREFIX` environment variable is not set.
// It should fall back to the default 'temp_'.
test('handle uses default DYNAMIC_DISK_PREFIX if env is not set', function () {
    $this->envOverrides['DYNAMIC_DISK_PREFIX'] = null; // Explicitly simulate env not being set

    $mockFileDisk = Mockery::mock(FileDisk::class);
    $mockFileDisk->shouldReceive('setConfig')->once();
    $mockFileDisk->driver = 'gdrive';

    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('find')
        ->with(5)
        ->once()
        ->andReturn($mockFileDisk);

    $mockBackupJob = Mockery::mock(BackupJob::class);
    $mockBackupJob->shouldNotReceive('dontBackupFilesystem');
    $mockBackupJob->shouldNotReceive('dontBackupDatabases');
    $mockBackupJob->shouldNotReceive('setFilename');
    $mockBackupJob->shouldReceive('run')->once();

    Mockery::mock('alias:'.BackupJobFactory::class)
        ->shouldReceive('createFromArray')
        ->withArgs(function ($config) use ($mockFileDisk) {
            // Verify config uses the default 'temp_' prefix.
            return isset($config['backup']['destination']['disks']) &&
                   $config['backup']['destination']['disks'] === ['temp_'.$mockFileDisk->driver];
        })
        ->once()
        ->andReturn($mockBackupJob);

    $data = [
        'file_disk_id' => 5,
        'option' => '',
    ];

    $job = new CreateBackupJob($data);
    $job->handle();
});

// Test `handle` method when `DYNAMIC_DISK_PREFIX` environment variable is custom.
test('handle uses custom DYNAMIC_DISK_PREFIX if env is set', function () {
    $this->envOverrides['DYNAMIC_DISK_PREFIX'] = 'custom_prefix_'; // Simulate a custom env prefix

    $mockFileDisk = Mockery::mock(FileDisk::class);
    $mockFileDisk->shouldReceive('setConfig')->once();
    $mockFileDisk->driver = 'aws';

    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('find')
        ->with(6)
        ->once()
        ->andReturn($mockFileDisk);

    $mockBackupJob = Mockery::mock(BackupJob::class);
    $mockBackupJob->shouldNotReceive('dontBackupFilesystem');
    $mockBackupJob->shouldNotReceive('dontBackupDatabases');
    $mockBackupJob->shouldNotReceive('setFilename');
    $mockBackupJob->shouldReceive('run')->once();

    Mockery::mock('alias:'.BackupJobFactory::class)
        ->shouldReceive('createFromArray')
        ->withArgs(function ($config) use ($mockFileDisk) {
            // Verify config uses the custom 'custom_prefix_'.
            return isset($config['backup']['destination']['disks']) &&
                   $config['backup']['destination']['disks'] === ['custom_prefix_'.$mockFileDisk->driver];
        })
        ->once()
        ->andReturn($mockBackupJob);

    $data = [
        'file_disk_id' => 6,
        'option' => '',
    ];

    $job = new CreateBackupJob($data);
    $job->handle();
});

// Test edge case: 'option' key is missing from the `$data` array.
// It should behave as if 'option' is an empty string, thus no specific backup options.
test('handle works when option key is missing from data array', function () {
    $mockFileDisk = Mockery::mock(FileDisk::class);
    $mockFileDisk->shouldReceive('setConfig')->once();
    $mockFileDisk->driver = 'azure';

    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('find')
        ->with(7)
        ->once()
        ->andReturn($mockFileDisk);

    $mockBackupJob = Mockery::mock(BackupJob::class);
    $mockBackupJob->shouldNotReceive('dontBackupFilesystem');
    $mockBackupJob->shouldNotReceive('dontBackupDatabases');
    $mockBackupJob->shouldNotReceive('setFilename'); // No option, so no custom filename
    $mockBackupJob->shouldReceive('run')->once();

    Mockery::mock('alias:'.BackupJobFactory::class)
        ->shouldReceive('createFromArray')
        ->withArgs(function ($config) use ($mockFileDisk) {
            return isset($config['backup']['destination']['disks']) &&
                   $config['backup']['destination']['disks'] === ['temp_'.$mockFileDisk->driver];
        })
        ->once()
        ->andReturn($mockBackupJob);

    $data = [
        'file_disk_id' => 7,
        // 'option' is intentionally missing
    ];

    $job = new CreateBackupJob($data);
    $job->handle();
});

// Test edge case: 'file_disk_id' key is missing from the `$data` array.
// This should lead to an error when trying to access an undefined array key.
test('handle throws error if file_disk_id key is missing from data array', function () {
    $data = [
        'option' => 'only-db',
        // 'file_disk_id' is intentionally missing
    ];

    $job = new CreateBackupJob($data);

    // Expecting an ErrorException for trying to access an undefined array key.
    $this->expectException(ErrorException::class);
    $this->expectExceptionMessageMatches('/Undefined array key "file_disk_id"/');

    $job->handle();
});

 

afterEach(function () {
    Mockery::close();
});
