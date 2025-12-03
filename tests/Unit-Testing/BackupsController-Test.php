<?php

use Carbon\Carbon;
use Crater\Http\Controllers\V1\Admin\Backup\BackupsController;
use Crater\Jobs\CreateBackupJob;
use Crater\Rules\Backup\PathToZip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Bus;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Helpers\Format;
use Illuminate\Contracts\Routing\ResponseFactory;

beforeEach(function () {
    Mockery::close();

    // Mock the ResponseFactory for `response()->json()` helper
    $this->responseFactory = Mockery::mock(ResponseFactory::class);
    $this->app->instance(ResponseFactory::class, $this->responseFactory);

    // Fake the Bus facade for dispatching jobs to allow assertions
    Bus::fake();

    // Create a partial mock for the BackupsController.
    // This allows testing the controller's methods while stubbing its inherited/trait behaviors.
    $this->controller = Mockery::mock(BackupsController::class)->makePartial();

    // Stub `authorize` and `respondSuccess` as they are inherited/trait methods
    // and not the primary focus of these unit tests.
    $this->controller->shouldReceive('authorize')
        ->zeroOrMoreTimes() // Can be called multiple times across tests
        ->with('manage backups')
        ->andReturn(true); // Always authorize for tests

    $this->controller->shouldReceive('respondSuccess')
        ->zeroOrMoreTimes()
        ->andReturn(new JsonResponse(['success' => true])); // Generic success response
});

// --- Test for index method ---
test('index method returns a list of backups and disks on success', function () {
    // Arrange
    $configuredDisks = ['s3', 'local'];
    $defaultFilesystem = 'local';
    $backupName = 'my-app';
    $requestFileDiskId = 'local'; // Used for cache key generation

    Config::shouldReceive('get')
        ->with('backup.backup.destination.disks')
        ->andReturn($configuredDisks)
        ->once();

    Config::shouldReceive('get')
        ->with('filesystems.default')
        ->andReturn($defaultFilesystem)
        ->once();

    Config::shouldReceive('get')
        ->with('backup.backup.name')
        ->andReturn($backupName)
        ->once();

    // Mock BackupDestination::create static method
    $mockBackupDestination = Mockery::mock(BackupDestination::class);
    BackupDestination::shouldReceive('create')
        ->with($defaultFilesystem, $backupName)
        ->andReturn($mockBackupDestination)
        ->once();

    // Prepare mock Backup objects for the collection
    $backup1 = Mockery::mock(Backup::class);
    $backup1->shouldReceive('path')->andReturn('path/to/backup1.zip')->once();
    $backup1->shouldReceive('date')->andReturn(Carbon::create(2023, 1, 1, 10, 0, 0))->once();
    $backup1->shouldReceive('size')->andReturn(50 * 1024 * 1024)->once(); // 50 MB

    $backup2 = Mockery::mock(Backup::class);
    $backup2->shouldReceive('path')->andReturn('path/to/backup2.zip')->once();
    $backup2->shouldReceive('date')->andReturn(Carbon::create(2023, 1, 2, 11, 0, 0))->once();
    $backup2->shouldReceive('size')->andReturn(100 * 1024 * 1024)->once(); // 100 MB

    // Mock the collection returned by backups() method of BackupDestination
    $mockBackupsCollection = new Collection([$backup1, $backup2]);
    $mockBackupDestination->shouldReceive('backups')->andReturn($mockBackupsCollection)->once();

    // Mock Format::humanReadableSize static method
    Mockery::mock('alias:' . Format::class)
        ->shouldReceive('humanReadableSize')
        ->with(50 * 1024 * 1024)
        ->andReturn('50 MB')
        ->once();
    Mockery::mock('alias:' . Format::class)
        ->shouldReceive('humanReadableSize')
        ->with(100 * 1024 * 1024)
        ->andReturn('100 MB')
        ->once();

    // Mock Cache::remember to execute its closure and return the result
    Cache::shouldReceive('remember')
        ->once()
        ->with("backups-{$requestFileDiskId}", Mockery::type(Carbon::class), Mockery::type(Closure::class))
        ->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback(); // Execute the closure to get actual data
        });

    $request = Request::create('/admin/backups', 'GET', ['file_disk_id' => $requestFileDiskId]);

    $expectedBackups = [
        [
            'path' => 'path/to/backup1.zip',
            'created_at' => '2023-01-01 10:00:00',
            'size' => '50 MB',
        ],
        [
            'path' => 'path/to/backup2.zip',
            'created_at' => '2023-01-02 11:00:00',
            'size' => '100 MB',
        ],
    ];

    $expectedJsonResponse = new JsonResponse([
        'backups' => $expectedBackups,
        'disks' => $configuredDisks,
    ]);

    // Expect the mocked responseFactory to be called to return the JSON response
    $this->responseFactory->shouldReceive('json')
        ->with([
            'backups' => $expectedBackups,
            'disks' => $configuredDisks,
        ])
        ->andReturn($expectedJsonResponse)
        ->once();

    // Act
    $response = $this->controller->index($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($expectedJsonResponse->getData(true));

    $this->controller->shouldHaveReceived('authorize')->with('manage backups')->once();
});

test('index method returns an empty list and disks if no backups are found', function () {
    // Arrange
    $configuredDisks = ['s3', 'local'];
    $defaultFilesystem = 'local';
    $backupName = 'my-app';
    $requestFileDiskId = 's3';

    Config::shouldReceive('get')
        ->with('backup.backup.destination.disks')
        ->andReturn($configuredDisks)
        ->once();

    Config::shouldReceive('get')
        ->with('filesystems.default')
        ->andReturn($defaultFilesystem)
        ->once();

    Config::shouldReceive('get')
        ->with('backup.backup.name')
        ->andReturn($backupName)
        ->once();

    $mockBackupDestination = Mockery::mock(BackupDestination::class);
    BackupDestination::shouldReceive('create')
        ->with($defaultFilesystem, $backupName)
        ->andReturn($mockBackupDestination)
        ->once();

    $mockBackupsCollection = new Collection([]); // Empty collection of backups
    $mockBackupDestination->shouldReceive('backups')->andReturn($mockBackupsCollection)->once();

    Cache::shouldReceive('remember')
        ->once()
        ->with("backups-{$requestFileDiskId}", Mockery::type(Carbon::class), Mockery::type(Closure::class))
        ->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback(); // Execute closure, it will return an empty array due to empty collection
        });

    $request = Request::create('/admin/backups', 'GET', ['file_disk_id' => $requestFileDiskId]);

    $expectedJsonResponse = new JsonResponse([
        'backups' => [],
        'disks' => $configuredDisks,
    ]);

    $this->responseFactory->shouldReceive('json')
        ->with([
            'backups' => [],
            'disks' => $configuredDisks,
        ])
        ->andReturn($expectedJsonResponse)
        ->once();

    // Act
    $response = $this->controller->index($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($expectedJsonResponse->getData(true));
    $this->controller->shouldHaveReceived('authorize')->with('manage backups')->once();
});

test('index method handles exception during backup destination creation gracefully', function () {
    // Arrange
    $configuredDisks = ['s3', 'local'];
    $defaultFilesystem = 'local';
    $backupName = 'my-app';
    $errorMessage = 'Invalid disk credentials provided.';

    Config::shouldReceive('get')
        ->with('backup.backup.destination.disks')
        ->andReturn($configuredDisks)
        ->once();

    Config::shouldReceive('get')
        ->with('filesystems.default')
        ->andReturn($defaultFilesystem)
        ->once();

    Config::shouldReceive('get')
        ->with('backup.backup.name')
        ->andReturn($backupName)
        ->once();

    // Force BackupDestination::create to throw an exception
    BackupDestination::shouldReceive('create')
        ->with($defaultFilesystem, $backupName)
        ->andThrow(new \Exception($errorMessage))
        ->once();

    Cache::shouldNotReceive('remember'); // Cache::remember should not be called in this error path

    $request = Request::create('/admin/backups', 'GET', ['file_disk_id' => 'local']);

    $expectedErrorJsonResponse = new JsonResponse([
        'backups' => [],
        'error' => 'invalid_disk_credentials',
        'error_message' => $errorMessage,
        'disks' => $configuredDisks,
    ]);

    $this->responseFactory->shouldReceive('json')
        ->with([
            'backups' => [],
            'error' => 'invalid_disk_credentials',
            'error_message' => $errorMessage,
            'disks' => $configuredDisks,
        ])
        ->andReturn($expectedErrorJsonResponse)
        ->once();

    // Act
    $response = $this->controller->index($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($expectedErrorJsonResponse->getData(true));
    $this->controller->shouldHaveReceived('authorize')->with('manage backups')->once();
});

// --- Test for store method ---
test('store method dispatches CreateBackupJob and returns success response', function () {
    // Arrange
    $requestData = ['option1' => 'value1', 'option2' => 'value2'];
    $queueName = 'backup_queue';

    Config::shouldReceive('get')
        ->with('backup.queue.name')
        ->andReturn($queueName)
        ->once();

    $request = Request::create('/admin/backups', 'POST', $requestData);

    // Act
    $response = $this->controller->store($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]); // From mocked respondSuccess

    $this->controller->shouldHaveReceived('authorize')->with('manage backups')->once();
    $this->controller->shouldHaveReceived('respondSuccess')->once();

    // Assert job was dispatched using Bus::fake()
    Bus::assertDispatched(CreateBackupJob::class, function ($job) use ($requestData, $queueName) {
        return $job->all() === $requestData && $job->queue === $queueName;
    });
});

// --- Test for destroy method ---
test('destroy method deletes the specified backup and returns success', function () {
    // Arrange
    $disk = 'local';
    $validatedPath = 'path/to/my_backup.zip';
    $defaultFilesystem = 'local';
    $backupName = 'my-app';

    // Mock the Request and its validate method
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('validate')
        ->once()
        ->with([
            'path' => ['required', Mockery::type(PathToZip::class)],
        ])
        ->andReturn(['path' => $validatedPath]); // Simulate successful validation

    Config::shouldReceive('get')
        ->with('filesystems.default')
        ->andReturn($defaultFilesystem)
        ->once();

    Config::shouldReceive('get')
        ->with('backup.backup.name')
        ->andReturn($backupName)
        ->once();

    // Mock BackupDestination::create static method
    $mockBackupDestination = Mockery::mock(BackupDestination::class);
    BackupDestination::shouldReceive('create')
        ->with($defaultFilesystem, $backupName)
        ->andReturn($mockBackupDestination)
        ->once();

    // Prepare mock Backup objects for the collection
    $matchingBackup = Mockery::mock(Backup::class);
    $matchingBackup->shouldReceive('path')->andReturn($validatedPath)->once(); // Used by first() filter
    $matchingBackup->shouldReceive('delete')->once(); // Expect delete to be called on this backup

    $otherBackup = Mockery::mock(Backup::class);
    $otherBackup->shouldReceive('path')->andReturn('path/to/other_backup.zip')->once();
    $otherBackup->shouldNotReceive('delete'); // Ensure delete is not called on non-matching backup

    // Mock the collection returned by backups()
    $mockBackupsCollection = new Collection([$otherBackup, $matchingBackup]);
    $mockBackupDestination->shouldReceive('backups')->andReturn($mockBackupsCollection)->once();

    // Act
    $response = $this->controller->destroy($disk, $request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]); // From mocked respondSuccess

    $this->controller->shouldHaveReceived('authorize')->with('manage backups')->once();
    $request->shouldHaveReceived('validate')->once();
    $matchingBackup->shouldHaveReceived('delete')->once();
    $this->controller->shouldHaveReceived('respondSuccess')->once();
});

test('destroy method throws TypeError if backup path is not found in the destination', function () {
    // Arrange
    $disk = 'local';
    $validatedPath = 'path/to/non_existent_backup.zip';
    $defaultFilesystem = 'local';
    $backupName = 'my-app';

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('validate')
        ->once()
        ->with([
            'path' => ['required', Mockery::type(PathToZip::class)],
        ])
        ->andReturn(['path' => $validatedPath]);

    Config::shouldReceive('get')
        ->with('filesystems.default')
        ->andReturn($defaultFilesystem)
        ->once();

    Config::shouldReceive('get')
        ->with('backup.backup.name')
        ->andReturn($backupName)
        ->once();

    $mockBackupDestination = Mockery::mock(BackupDestination::class);
    BackupDestination::shouldReceive('create')
        ->with($defaultFilesystem, $backupName)
        ->andReturn($mockBackupDestination)
        ->once();

    $otherBackup = Mockery::mock(Backup::class);
    $otherBackup->shouldReceive('path')->andReturn('path/to/actual_backup.zip')->once();
    $otherBackup->shouldNotReceive('delete'); // No delete on this one

    $mockBackupsCollection = new Collection([$otherBackup]); // No backup matching $validatedPath
    $mockBackupDestination->shouldReceive('backups')->andReturn($mockBackupsCollection)->once();

    // Act & Assert: Expect a TypeError because Collection::first() will return null,
    // and then ->delete() is called on null. This covers the internal logic path.
    expect(function () use ($disk, $request) {
        $this->controller->destroy($disk, $request);
    })->toThrow(TypeError::class);

    $this->controller->shouldHaveReceived('authorize')->with('manage backups')->once();
    $request->shouldHaveReceived('validate')->once();
    $otherBackup->shouldNotHaveReceived('delete'); // Confirm delete was not called on the non-matching backup.
    $this->controller->shouldNotHaveReceived('respondSuccess'); // Should not reach the success response.
});

 

afterEach(function () {
    Mockery::close();
});
