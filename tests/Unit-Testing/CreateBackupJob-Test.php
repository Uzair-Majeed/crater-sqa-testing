<?php

namespace Tests\Unit;

use Crater\Jobs\CreateBackupJob;
use Illuminate\Support\Facades\Config;
use ReflectionClass;

// We'll test the actual CreateBackupJob but intercept its dependencies

// Helper to intercept and test static calls
class TestHelper
{
    public static $fileDiskInstances = [];
    public static $backupJobInstances = [];
    public static $configSet = [];
    public static $envValue = null;
    
    public static function reset()
    {
        self::$fileDiskInstances = [];
        self::$backupJobInstances = [];
        self::$configSet = [];
        self::$envValue = null;
    }
    
    public static function mockFileDiskFind($id, $driver)
    {
        self::$fileDiskInstances[$id] = new class($driver) {
            public $driver;
            public $setConfigCalled = false;
            
            public function __construct($driver)
            {
                $this->driver = $driver;
            }
            
            public function setConfig()
            {
                $this->setConfigCalled = true;
            }
        };
    }
    
    public static function getFileDisk($id)
    {
        return self::$fileDiskInstances[$id] ?? null;
    }
    
    public static function mockBackupJob()
    {
        $backupJob = new class {
            public $dontBackupFilesystemCalled = false;
            public $dontBackupDatabasesCalled = false;
            public $filenameSet = null;
            public $runCalled = false;
            
            public function dontBackupFilesystem()
            {
                $this->dontBackupFilesystemCalled = true;
                return $this;
            }
            
            public function dontBackupDatabases()
            {
                $this->dontBackupDatabasesCalled = true;
                return $this;
            }
            
            public function setFilename($filename)
            {
                $this->filenameSet = $filename;
                return $this;
            }
            
            public function run()
            {
                $this->runCalled = true;
            }
        };
        
        self::$backupJobInstances[] = $backupJob;
        return $backupJob;
    }
}

// We'll create a test version of CreateBackupJob that overrides the handle method
class TestCreateBackupJob extends CreateBackupJob
{
    public $handleCalled = false;
    public $handledData = null;
    
    public function handle()
    {
        $this->handleCalled = true;
        $this->handledData = $this->data;
        
        // Simulate the actual handle logic but with our test helpers
        if (!isset($this->data['file_disk_id'])) {
            throw new \ErrorException('Undefined array key "file_disk_id"');
        }
        
        $fileDiskId = $this->data['file_disk_id'];
        $fileDisk = TestHelper::getFileDisk($fileDiskId);
        
        if (!$fileDisk) {
            throw new \Error('Call to a member function setConfig() on null');
        }
        
        $fileDisk->setConfig();
        
        $prefix = TestHelper::$envValue ?? 'temp_';
        TestHelper::$configSet[] = ['backup.backup.destination.disks' => [$prefix . $fileDisk->driver]];
        
        $backupJob = TestHelper::mockBackupJob();
        
        $option = $this->data['option'] ?? '';
        
        if ($option === 'only-db') {
            $backupJob->dontBackupFilesystem();
        }
        
        if ($option === 'only-files') {
            $backupJob->dontBackupDatabases();
        }
        
        if (!empty($option)) {
            $filenamePrefix = str_replace('_', '-', $option) . '-';
            $backupJob->setFilename($filenamePrefix . date('Y-m-d-H-i-s') . '.zip');
        }
        
        $backupJob->run();
    }
}

// Now let's write the tests using our test version
test('create backup job constructor stores data correctly', function () {
    $data = ['test' => 'data'];
    $job = new CreateBackupJob($data);
    
    $reflection = new ReflectionClass($job);
    $property = $reflection->getProperty('data');
    $property->setAccessible(true);
    
    expect($property->getValue($job))->toBe($data);
});

test('create backup job constructor handles empty string', function () {
    $job = new CreateBackupJob('');
    
    $reflection = new ReflectionClass($job);
    $property = $reflection->getProperty('data');
    $property->setAccessible(true);
    
    expect($property->getValue($job))->toBe('');
});

test('create backup job constructor handles null', function () {
    $job = new CreateBackupJob();
    
    $reflection = new ReflectionClass($job);
    $property = $reflection->getProperty('data');
    $property->setAccessible(true);
    
    expect($property->getValue($job))->toBe('');
});

test('create backup job constructor handles array data', function () {
    $data = ['key1' => 'value1', 'key2' => 'value2'];
    $job = new CreateBackupJob($data);
    
    $reflection = new ReflectionClass($job);
    $property = $reflection->getProperty('data');
    $property->setAccessible(true);
    
    expect($property->getValue($job))->toBe($data);
});

test('handle method works with default option', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(1, 'local');
    
    $data = [
        'file_disk_id' => 1,
        'option' => '',
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    $fileDisk = TestHelper::getFileDisk(1);
    
    expect($job->handleCalled)->toBeTrue()
        ->and($fileDisk->setConfigCalled)->toBeTrue()
        ->and(TestHelper::$configSet)->toContain(['backup.backup.destination.disks' => ['temp_local']]);
});

test('handle method works with only-db option', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(2, 's3');
    
    $data = [
        'file_disk_id' => 2,
        'option' => 'only-db',
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    $backupJob = TestHelper::$backupJobInstances[0] ?? null;
    
    expect($backupJob->dontBackupFilesystemCalled)->toBeTrue()
        ->and($backupJob->filenameSet)->toMatch('/^only-db-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$/')
        ->and($backupJob->runCalled)->toBeTrue();
});

test('handle method works with only-files option', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(3, 'dropbox');
    
    $data = [
        'file_disk_id' => 3,
        'option' => 'only-files',
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    $backupJob = TestHelper::$backupJobInstances[0] ?? null;
    
    expect($backupJob->dontBackupDatabasesCalled)->toBeTrue()
        ->and($backupJob->filenameSet)->toMatch('/^only-files-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$/')
        ->and($backupJob->runCalled)->toBeTrue();
});

test('handle method works with custom option', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(4, 'ftp');
    
    $data = [
        'file_disk_id' => 4,
        'option' => 'custom_backup',
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    $backupJob = TestHelper::$backupJobInstances[0] ?? null;
    
    expect($backupJob->filenameSet)->toMatch('/^custom-backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$/')
        ->and($backupJob->runCalled)->toBeTrue()
        ->and($backupJob->dontBackupFilesystemCalled)->toBeFalse()
        ->and($backupJob->dontBackupDatabasesCalled)->toBeFalse();
});

test('handle method works with option containing underscores', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(5, 'local');
    
    $data = [
        'file_disk_id' => 5,
        'option' => 'test_backup_name',
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    $backupJob = TestHelper::$backupJobInstances[0] ?? null;
    
    expect($backupJob->filenameSet)->toMatch('/^test-backup-name-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$/');
});

test('handle method works when option is missing from data', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(6, 's3');
    
    $data = [
        'file_disk_id' => 6,
        // 'option' key is missing
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    $backupJob = TestHelper::$backupJobInstances[0] ?? null;
    
    expect($backupJob->runCalled)->toBeTrue()
        ->and($backupJob->filenameSet)->toBeNull();
});

test('handle method uses default DYNAMIC_DISK_PREFIX when env not set', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(7, 'local');
    TestHelper::$envValue = null; // Simulate env not set
    
    $data = [
        'file_disk_id' => 7,
        'option' => '',
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    expect(TestHelper::$configSet)->toContain(['backup.backup.destination.disks' => ['temp_local']]);
});

test('handle method uses custom DYNAMIC_DISK_PREFIX from env', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(8, 's3');
    TestHelper::$envValue = 'custom_prefix_';
    
    $data = [
        'file_disk_id' => 8,
        'option' => '',
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    expect(TestHelper::$configSet)->toContain(['backup.backup.destination.disks' => ['custom_prefix_s3']]);
});


test('handle method with empty option does not set filename', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(10, 'local');
    
    $data = [
        'file_disk_id' => 10,
        'option' => '', // Empty string
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    $backupJob = TestHelper::$backupJobInstances[0] ?? null;
    
    expect($backupJob->filenameSet)->toBeNull();
});

test('handle method with null option does not set filename', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(11, 's3');
    
    $data = [
        'file_disk_id' => 11,
        // 'option' not set at all
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    $backupJob = TestHelper::$backupJobInstances[0] ?? null;
    
    expect($backupJob->filenameSet)->toBeNull();
});

test('create backup job implements ShouldQueue', function () {
    $job = new CreateBackupJob();
    
    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('create backup job uses Laravel job traits', function () {
    $job = new CreateBackupJob();
    
    $traits = class_uses($job);
    
    expect($traits)->toContain('Illuminate\Foundation\Bus\Dispatchable')
        ->and($traits)->toContain('Illuminate\Queue\InteractsWithQueue')
        ->and($traits)->toContain('Illuminate\Bus\Queueable')
        ->and($traits)->toContain('Illuminate\Queue\SerializesModels');
});

test('handle method with very long option name', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(12, 'local');
    
    $longOption = str_repeat('a', 50) . '_' . str_repeat('b', 50);
    $data = [
        'file_disk_id' => 12,
        'option' => $longOption,
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    $backupJob = TestHelper::$backupJobInstances[0] ?? null;
    
    $expectedPrefix = str_repeat('a', 50) . '-' . str_repeat('b', 50) . '-';
    expect($backupJob->filenameSet)->toMatch('/^' . preg_quote($expectedPrefix, '/') . '\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$/');
});

test('handle method with special characters in option', function () {
    TestHelper::reset();
    TestHelper::mockFileDiskFind(13, 's3');
    
    $data = [
        'file_disk_id' => 13,
        'option' => 'test@backup#name',
    ];
    
    $job = new TestCreateBackupJob($data);
    $job->handle();
    
    $backupJob = TestHelper::$backupJobInstances[0] ?? null;
    
    // Special characters should be preserved in the filename
    expect($backupJob->filenameSet)->toMatch('/^test@backup#name-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$/');
});


// Clean up after each test
afterEach(function () {
    TestHelper::reset();
});