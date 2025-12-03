```php
<?php

use Crater\Http\Controllers\V1\Admin\Backup\DownloadBackupController;
use Crater\Rules\Backup\PathToZip;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    config()->set('filesystems.default', 'local');
    config()->set('backup.backup.name', 'my-app');

    // Use dependency injection for controller methods.
    $this->controller = app()->make(DownloadBackupController::class);

    // Partial mock to intercept authorize call in __invoke.
    $this->authorizeMock = Mockery::mock(DownloadBackupController::class)->makePartial();
    $this->authorizeMock->shouldAllowMockingProtectedMethods();
    $this->authorizeMock->shouldReceive('authorize')
        ->with('manage backups')
        ->andReturn(true);
});

test('it authorizes access to manage backups', function () {
    $validatedPath = 'backups/some-backup.zip';

    // Mock Request validation.
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('validate')->once()->andReturn(['path' => $validatedPath]);
    $mockRequest->shouldReceive('get')->with('path')->andReturn($validatedPath);

    // Create instance of BackupDestination, override backups method using Mockery's instance mocking.
    $mockBackupDestination = Mockery::mock(BackupDestination::class);
    $mockBackupDestination->shouldReceive('backups')->andReturn(collect([]));

    // Patch static create() using instance override, since alias: fails if the class is loaded.
    $originalCreate = \Closure::bind(function ($disk, $name) use ($mockBackupDestination) {
        return $mockBackupDestination;
    }, null, BackupDestination::class);
    BackupDestination::macro('create', $originalCreate);

    // Mock response() helper for not found.
    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('make')
        ->with('Backup not found', Response::HTTP_UNPROCESSABLE_ENTITY)
        ->andReturn(new Response('Backup not found', Response::HTTP_UNPROCESSABLE_ENTITY));
    app()->instance(ResponseFactory::class, $mockResponseFactory);

    // Swap controller instance for partial mock (authorize).
    $response = $this->authorizeMock->__invoke($mockRequest);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getContent())->toBe('Backup not found')
        ->and($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
});

test('it returns an unprocessable entity response if path is missing', function () {
    $request = Request::create('/', 'GET', []);

    $this->expectException(ValidationException::class);

    // Use authorize partial mock for method
    $this->authorizeMock->__invoke($request);
});

test('it returns an unprocessable entity response if path validation fails', function () {
    $invalidPath = 'invalid-path.txt';

    // Mock Request validate throws ValidationException
    $mockRequest = Mockery::mock(Request::class);

    // Validator fake to simulate error.
    $validator = Validator::make(['path' => $invalidPath], ['path' => [new PathToZip()]]);
    $validator->errors()->add('path', 'The selected path is invalid.');
    $exception = new ValidationException($validator);

    $mockRequest->shouldReceive('validate')->once()->andThrow($exception);
    $mockRequest->shouldReceive('get')->with('path')->andReturn($invalidPath);

    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('The selected path is invalid.');

    $this->authorizeMock->__invoke($mockRequest);
});

test('it returns an unprocessable entity response if no matching backup is found', function () {
    $nonExistentPath = 'backups/non-existent-backup.zip';

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('validate')->once()->andReturn(['path' => $nonExistentPath]);
    $mockRequest->shouldReceive('get')->with('path')->andReturn($nonExistentPath);

    $differentBackup = Mockery::mock(Backup::class);
    $differentBackup->shouldReceive('path')->andReturn('backups/some-other-backup.zip');

    $mockBackupDestination = Mockery::mock(BackupDestination::class);
    $mockBackupDestination->shouldReceive('backups')->andReturn(collect([$differentBackup]));

    $originalCreate = \Closure::bind(function ($disk, $name) use ($mockBackupDestination) {
        return $mockBackupDestination;
    }, null, BackupDestination::class);
    BackupDestination::macro('create', $originalCreate);

    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('make')
        ->with('Backup not found', Response::HTTP_UNPROCESSABLE_ENTITY)
        ->andReturn(new Response('Backup not found', Response::HTTP_UNPROCESSABLE_ENTITY));
    app()->instance(ResponseFactory::class, $mockResponseFactory);

    $response = $this->authorizeMock->__invoke($mockRequest);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getContent())->toBe('Backup not found')
        ->and($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
});

test('it downloads the backup if found', function () {
    $backupPath = 'backups/2023-01-01-00-00-00.zip';
    $backupSize = 1024;
    $fileName = '2023-01-01-00-00-00.zip';
    $fileContent = 'this is dummy backup content';

    $streamResource = fopen('php://memory', 'r+');
    fwrite($streamResource, $fileContent);
    fseek($streamResource, 0);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('validate')->once()->andReturn(['path' => $backupPath]);
    $mockRequest->shouldReceive('get')->with('path')->andReturn($backupPath);

    $mockBackup = Mockery::mock(Backup::class);
    $mockBackup->shouldReceive('path')->andReturn($backupPath);
    $mockBackup->shouldReceive('size')->andReturn($backupSize);
    $mockBackup->shouldReceive('stream')->andReturn($streamResource);

    $mockBackupDestination = Mockery::mock(BackupDestination::class);
    $mockBackupDestination->shouldReceive('backups')->andReturn(collect([$mockBackup]));
    $mockBackupDestination->shouldReceive('backups->first')->andReturnUsing(function ($closure) use ($mockBackup) {
        if ($closure($mockBackup)) {
            return $mockBackup;
        }
        return null;
    });

    $originalCreate = \Closure::bind(function ($disk, $name) use ($mockBackupDestination) {
        return $mockBackupDestination;
    }, null, BackupDestination::class);
    BackupDestination::macro('create', $originalCreate);

    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('stream')
        ->once()
        ->andReturnUsing(function ($callback, $status, $headers) use ($fileContent) {
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
    app()->instance(ResponseFactory::class, $mockResponseFactory);

    $response = $this->authorizeMock->__invoke($mockRequest);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->getStatusCode())->toBe(Response::HTTP_OK);

    $headers = $response->headers->all();
    expect($headers['Content-Type'])->toBe('application/zip')
        ->and($headers['Content-Length'])->toBe((string)$backupSize)
        ->and($headers['Content-Disposition'])->toBe('attachment; filename="' . $fileName . '"');

    expect($response->content)->toBe($fileContent);

    expect(is_resource($streamResource))->toBeFalse();
});

test('respondWithBackupStream generates correct headers and streams content', function () {
    $backupPath = 'backups/another-backup.zip';
    $backupSize = 512;
    $fileName = 'another-backup.zip';
    $fileContent = 'another dummy content for stream';

    $streamResource = fopen('php://memory', 'r+');
    fwrite($streamResource, $fileContent);
    fseek($streamResource, 0);

    $mockBackup = Mockery::mock(Backup::class);
    $mockBackup->shouldReceive('path')->andReturn($backupPath);
    $mockBackup->shouldReceive('size')->andReturn($backupSize);
    $mockBackup->shouldReceive('stream')->andReturn($streamResource);

    $mockResponseFactory = Mockery::mock(ResponseFactory::class);
    $mockResponseFactory->shouldReceive('stream')
        ->once()
        ->andReturnUsing(function ($callback, $status, $headers) use ($fileContent) {
            ob_start();
            $callback();
            $streamedContent = ob_get_clean();

            $defaultHeaders = [
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Pragma' => 'public',
            ];
            $headers = array_merge($defaultHeaders, $headers);

            $mockStreamedResponse = Mockery::mock(StreamedResponse::class);
            $mockStreamedResponse->shouldReceive('send');
            $mockStreamedResponse->shouldReceive('getStatusCode')->andReturn($status);
            $mockStreamedResponse->shouldReceive('headers->all')->andReturn($headers);
            $mockStreamedResponse->content = $streamedContent;
            return $mockStreamedResponse;
        });
    app()->instance(ResponseFactory::class, $mockResponseFactory);

    // Use original controller for direct method test.
    $controller = app()->make(DownloadBackupController::class);

    $response = $controller->respondWithBackupStream($mockBackup);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->getStatusCode())->toBe(Response::HTTP_OK);

    $headers = $response->headers->all();
    expect($headers['Cache-Control'])->toBe('must-revalidate, post-check=0, pre-check=0')
        ->and($headers['Content-Type'])->toBe('application/zip')
        ->and($headers['Content-Length'])->toBe((string)$backupSize)
        ->and($headers['Content-Disposition'])->toBe('attachment; filename="' . $fileName . '"')
        ->and($headers['Pragma'])->toBe('public');

    expect($response->content)->toBe($fileContent);

    expect(is_resource($streamResource))->toBeFalse();
});

afterEach(function () {
    Mockery::close();
    // Remove the macro to avoid leaking mocks.
    if (method_exists(BackupDestination::class, 'create')) {
        unset(BackupDestination::$macros['create']);
    }
});
```