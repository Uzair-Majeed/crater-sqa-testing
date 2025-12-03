<?php

use Crater\Jobs\CreateBackupJob;
use Crater\Models\FileDisk;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use Spatie\Backup\Tasks\Backup\BackupJob;
use Illuminate\Support\Arr;

// ---- HELPER FUNCTIONS ---- //
// Used to monkey patch config() and env() dynamically for test isolation.

function setConfigForTest($testcase)
{
    // Remove any previous config patching.
    if (! function_exists('config_pest_original')) {
        // Backup original config function if not already done.
        if (function_exists('config')) {
            runkit_function_rename('config', 'config_pest_original');
        }
    }

    if (! function_exists('config')) {
        // Define config() if missing
        function config($key = null, $value = null) {
            return config_pest_proxy($key, $value);
        }
    }

    // Proxy config() to $testcase->currentConfig.
    $GLOBALS['config_pest_proxy'] = function ($key, $value = null) use ($testcase) {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                Arr::set($testcase->currentConfig, $k, $v);
            }
            return null;
        } elseif ($value !== null) {
            return Arr::get($testcase->currentConfig, $key, $value);
        } else {
            return Arr::get($testcase->currentConfig, $key);
        }
    };
}

function setEnvForTest($testcase)
{
    // Remove any previous env patching.
    if (! function_exists('env_pest_original')) {
        // Backup original env function if not already done.
        if (function_exists('env')) {
            runkit_function_rename('env', 'env_pest_original');
        }
    }

    if (! function_exists('env')) {
        // Define env() if missing
        function env($key = null, $default = null) {
            return env_pest_proxy($key, $default);
        }
    }

    // Proxy env() to $testcase->envOverrides.
    $GLOBALS['env_pest_proxy'] = function ($key, $default = null) use ($testcase) {
        if (array_key_exists($key, $testcase->envOverrides) && $testcase->envOverrides[$key] !== null) {
            return $testcase->envOverrides[$key];
        }
        // Default behavior for DYNAMIC_DISK_PREFIX if not explicitly overridden
        if ($key === 'DYNAMIC_DISK_PREFIX') {
            return 'temp_';
        }
        return $default;
    };
}

// Define proxy function for runkit renaming (for monkey patching)
if (! function_exists('config_pest_proxy')) {
    function config_pest_proxy($key, $value = null) {
        return $GLOBALS['config_pest_proxy']($key, $value);
    }
}
if (! function_exists('env_pest_proxy')) {
    function env_pest_proxy($key, $default = null) {
        return $GLOBALS['env_pest_proxy']($key, $default);
    }
}

// ---- TEST LIFECYCLE ---- //

beforeEach(function () {
    Mockery::close();

    $this->currentConfig = [
        'backup' => [
            'backup' => [
                'destination' => [
                    'disks' => [],
                ],
            ],
        ],
    ];

    $this->envOverrides = [];

    // Set up config & env monkey patch for this test.
    setConfigForTest($this);
    setEnvForTest($this);
});

// ---- TESTS ---- //

test('it constructs with data', function () {
    $data = ['test' => 'data'];
    $job = new CreateBackupJob($data);
    $reflection = new ReflectionClass($job);
    $property = $reflection->getProperty('data');
    $property->setAccessible(true);
    expect($property->getValue($job))->toBe($data);
});

test('handle creates a backup job with default settings when no option is provided', function () {
    $mockFileDisk = Mockery::mock(FileDisk::class);
    $mockFileDisk->shouldReceive('setConfig')->once();
    $mockFileDisk->driver = 's3';

    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('find')
        ->with(1)
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
            return isset($config['backup']['destination']['disks']) &&
                $config['backup']['destination']['disks'] === ['temp_'.$mockFileDisk->driver];
        })
        ->once()
        ->andReturn($mockBackupJob);

    $data = [
        'file_disk_id' => 1,
        'option' => '',
    ];
    $job = new CreateBackupJob($data);
    $job->handle();
});

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
    $mockBackupJob->shouldReceive('dontBackupFilesystem')->once();
    $mockBackupJob->shouldNotReceive('dontBackupDatabases');
    $mockBackupJob->shouldReceive('setFilename')
        ->once()
        ->with(Mockery::on(function ($filename) {
            return str_starts_with($filename, 'only-db-') &&
                str_ends_with($filename, '.zip') &&
                preg_match('/\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}/', $filename);
        }));
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
    $mockBackupJob->shouldReceive('dontBackupDatabases')->once();
    $mockBackupJob->shouldReceive('setFilename')
        ->once()
        ->with(Mockery::on(function ($filename) {
            return str_starts_with($filename, 'only-files-') &&
                str_ends_with($filename, '.zip') &&
                preg_match('/\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}/', $filename);
        }));
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
        ->with(Mockery::on(function ($filename) {
            return str_starts_with($filename, 'custom-option-') &&
                str_ends_with($filename, '.zip') &&
                preg_match('/\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}/', $filename);
        }));
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
        'option' => 'custom_option',
    ];
    $job = new CreateBackupJob($data);
    $job->handle();
});

test('handle throws exception if FileDisk not found', function () {
    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('find')
        ->with(99)
        ->once()
        ->andReturn(null);

    $data = [
        'file_disk_id' => 99,
        'option' => '',
    ];
    $job = new CreateBackupJob($data);

    $this->expectException(Error::class);
    $this->expectExceptionMessage('Call to a member function setConfig() on null');

    $job->handle();
});

test('handle uses default DYNAMIC_DISK_PREFIX if env is not set', function () {
    $this->envOverrides['DYNAMIC_DISK_PREFIX'] = null;

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

test('handle uses custom DYNAMIC_DISK_PREFIX if env is set', function () {
    $this->envOverrides['DYNAMIC_DISK_PREFIX'] = 'custom_prefix_';

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
    $mockBackupJob->shouldNotReceive('setFilename');
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
        // 'option' missing
    ];
    $job = new CreateBackupJob($data);
    $job->handle();
});

test('handle throws error if file_disk_id key is missing from data array', function () {
    $data = [
        'option' => 'only-db',
        // 'file_disk_id' missing
    ];
    $job = new CreateBackupJob($data);

    $this->expectException(ErrorException::class);
    $this->expectExceptionMessageMatches('/Undefined array key "file_disk_id"/');

    $job->handle();
});

afterEach(function () {
    Mockery::close();
});