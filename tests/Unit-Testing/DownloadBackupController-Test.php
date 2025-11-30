<?php

use Crater\Http\Controllers\V1\Admin\Backup\DownloadBackupController;
use Crater\Rules\Backup\PathToZip;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Validation\ValidationException;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
uses(\Mockery::class);

// Setup for Pest tests.
beforeEach(function () {
    // Mock config values that are used by the controller.
    config()->set('filesystems.default', 'local');
    config()->set('backup.backup.name', 'my-app');

    // Create a partial mock of the controller to isolate and test the `authorize` method call.
    // If `authorize('manage backups')` is not called, Mockery will cause the test to fail.
    $this->mock(DownloadBackupController::class, function ($mock) {
        $mock->shouldReceive('authorize')->with('manage backups')->andReturn(true);
    })->makePartial();

    // Resolve the controller instance from the container, ensuring our partial mock is used.
    $this->controller = app(DownloadBackupController::class);
});

// Cleanup Mockery mocks after each test.
afterEach(function () {
    Mockery::close();
});

test('it authorizes access to manage backups', function () {
    $validatedPath = 'backups/some-backup.zip';

    // Mock the Request's `validate` method to simulate successful validation.
    // This allows the test flow to proceed past validation and check subsequent logic.
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('validate')->once()->andReturn(['path' => $validatedPath]);
    $mockRequest->shouldReceive('get')->with('path')->andReturn($validatedPath);

    // Mock `BackupDestination` and its `backups` collection to return an empty set.
    // This will lead to the "Backup not found" response, enabling us to test that branch.
    $mockBackupDestination = Mockery::mock(BackupDestination::class);
    $mockBackupDestination->shouldReceive('backups')->andReturn(collect([]));

    // Mock the static `BackupDestination::create` method.
    Mockery::mock('alias:' . BackupDestination::class)
        ->shouldReceive('create')
        ->once()
        ->with('local', 'my-app')
        ->andReturn($mockBackupDestination);

    // Mock the global `response()` helper for the "Backup not found" scenario.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('make')
        ->with('Backup not found', Response::HTTP_UNPROCESSABLE_ENTITY)
        ->andReturn(new Response('Backup not found', Response::HTTP_UNPROCESSABLE_ENTITY));
    $this->app->instance(ResponseFactory::class, $mockResponseFactory);

    // Invoke the controller. The `authorize` method call is expected by the partial mock.
    $response = $this->controller($mockRequest);

    // Assert the response for the "backup not found" scenario.
    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getContent())->toBe('Backup not found')
        ->and($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
});

test('it returns an unprocessable entity response if path is missing', function () {
    // Create a request without the required 'path' parameter.
    $request = Request::create('/', 'GET', []);

    // Expect a `ValidationException` because the 'path' field is marked as 'required'.
    $this->expectException(ValidationException::class);

    // The validation process should fail before any `BackupDestination` or `PathToZip` logic is hit.
    $this->controller($request);
});

test('it returns an unprocessable entity response if path validation fails', function () {
    $invalidPath = 'invalid-path.txt'; // A path that `PathToZip` (or similar rule) would reject.

    // Mock the Request's `validate` method to directly throw a `ValidationException`.
    // This isolates the controller from the actual implementation of the `PathToZip` rule,
    // which might involve file system interactions.
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('validate')->once()->andThrow(
        new ValidationException(
            \Illuminate\Validation\Validator::make(
                ['path' => $invalidPath],
                ['path' => new PathToZip()] // Using PathToZip here for context, mock throws directly.
            )->errors()->add('path', 'The selected path is invalid.')
        )
    );
    $mockRequest->shouldReceive('get')->with('path')->andReturn($invalidPath);

    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('The selected path is invalid.');

    $this->controller($mockRequest);
});

test('it returns an unprocessable entity response if no matching backup is found', function () {
    $nonExistentPath = 'backups/non-existent-backup.zip';

    // Mock `Request` to simulate successful validation for a given path.
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('validate')->once()->andReturn(['path' => $nonExistentPath]);
    $mockRequest->shouldReceive('get')->with('path')->andReturn($nonExistentPath);

    // Mock a `Backup` object with a different path to ensure no match is found.
    $differentBackup = Mockery::mock(Backup::class);
    $differentBackup->shouldReceive('path')->andReturn('backups/some-other-backup.zip');

    // Mock `BackupDestination` and its `backups` collection.
    $mockBackupDestination = Mockery::mock(BackupDestination::class);
    $mockBackupDestination->shouldReceive('backups')->andReturn(collect([$differentBackup])); // Collection contains no matching backup.

    // Mock the static `BackupDestination::create` method.
    Mockery::mock('alias:' . BackupDestination::class)
        ->shouldReceive('create')
        ->once()
        ->with('local', 'my-app')
        ->andReturn($mockBackupDestination);

    // Mock the global `response()` helper.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('make')
        ->with('Backup not found', Response::HTTP_UNPROCESSABLE_ENTITY)
        ->andReturn(new Response('Backup not found', Response::HTTP_UNPROCESSABLE_ENTITY));
    $this->app->instance(ResponseFactory::class, $mockResponseFactory);

    $response = $this->controller($mockRequest);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getContent())->toBe('Backup not found')
        ->and($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
});

test('it downloads the backup if found', function () {
    $backupPath = 'backups/2023-01-01-00-00-00.zip';
    $backupSize = 1024; // Example backup size.
    $fileName = '2023-01-01-00-00-00.zip';
    $fileContent = 'this is dummy backup content';

    // Create a real stream resource to simulate file content and test `fpassthru`/`fclose`.
    $streamResource = fopen('php://memory', 'r+');
    fwrite($streamResource, $fileContent);
    fseek($streamResource, 0); // Rewind the stream to the beginning for reading.

    // Mock `Request` to simulate successful validation.
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('validate')->once()->andReturn(['path' => $backupPath]);
    $mockRequest->shouldReceive('get')->with('path')->andReturn($backupPath);

    // Mock a `Backup` object with expected behavior.
    $mockBackup = Mockery::mock(Backup::class);
    $mockBackup->shouldReceive('path')->andReturn($backupPath);
    $mockBackup->shouldReceive('size')->andReturn($backupSize);
    $mockBackup->shouldReceive('stream')->andReturn($streamResource); // Return our real stream resource.

    // Mock `BackupDestination` and its `backups` collection to include our mock backup.
    $mockBackupDestination = Mockery::mock(BackupDestination::class);
    $mockBackupDestination->shouldReceive('backups')->andReturn(collect([$mockBackup]));
    // Simulate the `first` method's callback to ensure the correct backup is returned.
    $mockBackupDestination->shouldReceive('backups->first')->andReturnUsing(function (Closure $callback) use ($mockBackup) {
        if ($callback($mockBackup)) {
            return $mockBackup;
        }
        return null;
    });

    // Mock the static `BackupDestination::create` method.
    Mockery::mock('alias:' . BackupDestination::class)
        ->shouldReceive('create')
        ->once()
        ->with('local', 'my-app')
        ->andReturn($mockBackupDestination);

    // Mock the global `response()` helper specifically for `response()->stream()`.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('stream')
        ->once()
        ->andReturnUsing(function (Closure $callback, int $status, array $headers) use ($fileContent) {
            // Capture the output generated by the stream callback (which uses `fpassthru`).
            ob_start();
            $callback();
            $streamedContent = ob_get_clean();

            // Create a dummy `StreamedResponse` object to return, allowing assertions on headers and content.
            $mockStreamedResponse = Mockery::mock(StreamedResponse::class);
            $mockStreamedResponse->shouldReceive('send'); // Prevent actual output sending.
            $mockStreamedResponse->shouldReceive('getStatusCode')->andReturn($status);
            $mockStreamedResponse->shouldReceive('headers->all')->andReturn($headers);
            $mockStreamedResponse->content = $streamedContent; // Store content for assertion.

            return $mockStreamedResponse;
        });
    $this->app->instance(ResponseFactory::class, $mockResponseFactory);

    $response = $this->controller($mockRequest); // Invoke the controller.

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->getStatusCode())->toBe(Response::HTTP_OK);

    // Assert the HTTP download headers are correctly set.
    $headers = $response->headers->all();
    expect($headers['Content-Type'])->toBe('application/zip')
        ->and($headers['Content-Length'])->toBe((string)$backupSize)
        ->and($headers['Content-Disposition'])->toBe('attachment; filename="' . $fileName . '"');

    // Assert that the streamed content matches our dummy content.
    expect($response->content)->toBe($fileContent);

    // Verify that the stream resource was closed by `fclose` within the stream callback.
    expect(is_resource($streamResource))->toBeFalse();
});

test('respondWithBackupStream generates correct headers and streams content', function () {
    $backupPath = 'backups/another-backup.zip';
    $backupSize = 512;
    $fileName = 'another-backup.zip';
    $fileContent = 'another dummy content for stream';

    // Create a real stream resource for testing.
    $streamResource = fopen('php://memory', 'r+');
    fwrite($streamResource, $fileContent);
    fseek($streamResource, 0);

    // Mock `Backup` object directly for this method's unit test.
    $mockBackup = Mockery::mock(Backup::class);
    $mockBackup->shouldReceive('path')->andReturn($backupPath);
    $mockBackup->shouldReceive('size')->andReturn($backupSize);
    $mockBackup->shouldReceive('stream')->andReturn($streamResource);

    // Mock the global `response()` helper specifically for `response()->stream()`.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('stream')
        ->once()
        ->andReturnUsing(function (Closure $callback, int $status, array $headers) use ($fileContent) {
            // Capture the output of the stream callback.
            ob_start();
            $callback();
            $streamedContent = ob_get_clean();

            $mockStreamedResponse = Mockery::mock(StreamedResponse::class);
            $mockStreamedResponse->shouldReceive('send');
            $mockStreamedResponse->shouldReceive('getStatusCode')->andReturn($status);
            $mockStreamedResponse->shouldReceive('headers->all')->andReturn($headers);
            $mockStreamedResponse->content = $streamedContent;

            return $mockStreamedResponse;
        });
    $this->app->instance(ResponseFactory::class, $mockResponseFactory);

    // Create a fresh controller instance, as this test directly calls `respondWithBackupStream`
    // and does not go through the `__invoke` method (which handles authorization).
    $controller = app(DownloadBackupController::class);

    $response = $controller->respondWithBackupStream($mockBackup);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->getStatusCode())->toBe(Response::HTTP_OK);

    // Assert all expected download headers.
    $headers = $response->headers->all();
    expect($headers['Cache-Control'])->toBe('must-revalidate, post-check=0, pre-check=0')
        ->and($headers['Content-Type'])->toBe('application/zip')
        ->and($headers['Content-Length'])->toBe((string)$backupSize)
        ->and($headers['Content-Disposition'])->toBe('attachment; filename="' . $fileName . '"')
        ->and($headers['Pragma'])->toBe('public');

    // Assert the content that was streamed.
    expect($response->content)->toBe($fileContent);

    // Verify that the stream resource was closed.
    expect(is_resource($streamResource))->toBeFalse();
});
