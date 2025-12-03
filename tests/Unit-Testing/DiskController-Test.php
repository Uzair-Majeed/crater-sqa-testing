<?php
use Crater\Http\Controllers\V1\Admin\Settings\DiskController;
use Crater\Http\Requests\DiskEnvironmentRequest;
use Crater\Http\Resources\FileDiskResource;
use Crater\Models\FileDisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

// Mock the global respondJson helper function as it's used in the controller
if (! function_exists('respondJson')) {
    function respondJson($code, $message, $status = 400)
    {
        return new JsonResponse(['code' => $code, 'message' => $message], $status);
    }
}

beforeEach(function () {
    // Ensure Mockery expectations are cleared before each test
    Mockery::close();
});

test('index method authorizes and returns paginated file disks with default limit', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('all')->andReturn([]);

    $paginator = Mockery::mock(LengthAwarePaginator::class);

    // Mock FileDisk static methods for chaining
    Mockery::mock('alias:' . FileDisk::class)
        ->shouldReceive('applyFilters')
        ->with([])
        ->andReturnSelf()
        ->shouldReceive('latest')
        ->andReturnSelf()
        ->shouldReceive('paginateData')
        ->with(5) // Default limit
        ->andReturn($paginator);

    // Mock FileDiskResource::collection
    Mockery::mock('alias:' . FileDiskResource::class)
        ->shouldReceive('collection')
        ->with($paginator)
        ->andReturn('FileDiskResourceCollection'); // Simple return to confirm call

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $result = $controller->index($request);

    // Assert
    expect($result)->toBe('FileDiskResourceCollection');
});

test('index method authorizes and returns paginated file disks with custom limit and filters', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->limit = 10; // Set custom limit
    $request->shouldReceive('all')->andReturn(['search' => 'test']);

    $paginator = Mockery::mock(LengthAwarePaginator::class);

    // Mock FileDisk static methods for chaining
    Mockery::mock('alias:' . FileDisk::class)
        ->shouldReceive('applyFilters')
        ->with(['search' => 'test'])
        ->andReturnSelf()
        ->shouldReceive('latest')
        ->andReturnSelf()
        ->shouldReceive('paginateData')
        ->with(10) // Custom limit
        ->andReturn($paginator);

    // Mock FileDiskResource::collection
    Mockery::mock('alias:' . FileDiskResource::class)
        ->shouldReceive('collection')
        ->with($paginator)
        ->andReturn('FileDiskResourceCollection');

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $result = $controller->index($request);

    // Assert
    expect($result)->toBe('FileDiskResourceCollection');
});

test('store method authorizes and returns invalid credentials response', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $request = Mockery::mock(DiskEnvironmentRequest::class);
    $request->credentials = ['key' => 'invalid'];
    $request->driver = 's3';

    // Mock FileDisk static methods
    Mockery::mock('alias:' . FileDisk::class)
        ->shouldReceive('validateCredentials')
        ->with($request->credentials, $request->driver)
        ->andReturn(false)
        ->once();
    Mockery::mock('alias:' . FileDisk::class)->shouldNotReceive('createDisk'); // Should not create disk

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $response = $controller->store($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'code' => 'invalid_credentials',
        'message' => 'Invalid Credentials.'
    ]);
    expect($response->getStatusCode())->toBe(400);
});

test('store method authorizes and creates a new file disk', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $request = Mockery::mock(DiskEnvironmentRequest::class);
    $request->credentials = ['key' => 'valid'];
    $request->driver = 's3';

    $mockDisk = Mockery::mock(FileDisk::class);

    // Mock FileDisk static methods
    Mockery::mock('alias:' . FileDisk::class)
        ->shouldReceive('validateCredentials')
        ->with($request->credentials, $request->driver)
        ->andReturn(true)
        ->once()
        ->shouldReceive('createDisk')
        ->with($request)
        ->andReturn($mockDisk)
        ->once();

    // Mock FileDiskResource constructor
    Mockery::mock('alias:' . FileDiskResource::class)
        ->shouldReceive('__construct')
        ->with($mockDisk)
        ->andReturn(new FileDiskResource($mockDisk)); // Return an actual resource instance

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $result = $controller->store($request);

    // Assert
    expect($result)->toBeInstanceOf(FileDiskResource::class);
});

test('update method authorizes and returns invalid credentials response when updating a custom disk', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $disk = Mockery::mock(FileDisk::class);
    $disk->type = 'CUSTOM'; // Not 'SYSTEM'
    $disk->shouldNotReceive('updateDisk');
    $disk->shouldNotReceive('setAsDefaultDisk');

    $request = Mockery::mock(Request::class);
    $request->credentials = ['key' => 'invalid'];
    $request->driver = 's3';
    $request->set_as_default = false;

    // Mock FileDisk static methods
    Mockery::mock('alias:' . FileDisk::class)
        ->shouldReceive('validateCredentials')
        ->with($request->credentials, $request->driver)
        ->andReturn(false)
        ->once();

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $response = $controller->update($disk, $request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'code' => 'invalid_credentials',
        'message' => 'Invalid Credentials.'
    ]);
    expect($response->getStatusCode())->toBe(400);
});

test('update method authorizes and updates disk with valid credentials for a custom disk', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $disk = Mockery::mock(FileDisk::class);
    $disk->type = 'CUSTOM';
    $disk->shouldReceive('updateDisk')->with(Mockery::type(Request::class))->once();
    $disk->shouldNotReceive('setAsDefaultDisk');

    $request = Mockery::mock(Request::class);
    $request->credentials = ['key' => 'valid'];
    $request->driver = 's3';
    $request->set_as_default = false;

    // Mock FileDisk static methods
    Mockery::mock('alias:' . FileDisk::class)
        ->shouldReceive('validateCredentials')
        ->with($request->credentials, $request->driver)
        ->andReturn(true)
        ->once();

    // Mock FileDiskResource constructor
    Mockery::mock('alias:' . FileDiskResource::class)
        ->shouldReceive('__construct')
        ->with($disk)
        ->andReturn(new FileDiskResource($disk));

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $result = $controller->update($disk, $request);

    // Assert
    expect($result)->toBeInstanceOf(FileDiskResource::class);
});

test('update method authorizes and sets disk as default if requested', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $disk = Mockery::mock(FileDisk::class);
    $disk->type = 'CUSTOM'; // Type doesn't matter here for set_as_default branch
    $disk->shouldNotReceive('updateDisk');
    $disk->shouldReceive('setAsDefaultDisk')->once();

    $request = Mockery::mock(Request::class);
    $request->credentials = null; // No credentials or driver
    $request->driver = null;
    $request->set_as_default = true;

    // Ensure validateCredentials is not called
    Mockery::mock('alias:' . FileDisk::class)
        ->shouldNotReceive('validateCredentials');

    // Mock FileDiskResource constructor
    Mockery::mock('alias:' . FileDiskResource::class)
        ->shouldReceive('__construct')
        ->with($disk)
        ->andReturn(new FileDiskResource($disk));

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $result = $controller->update($disk, $request);

    // Assert
    expect($result)->toBeInstanceOf(FileDiskResource::class);
});

test('update method authorizes and returns disk without modification if no relevant update fields are provided', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $disk = Mockery::mock(FileDisk::class);
    $disk->type = 'CUSTOM';
    $disk->shouldNotReceive('updateDisk');
    $disk->shouldNotReceive('setAsDefaultDisk');

    $request = Mockery::mock(Request::class);
    $request->credentials = null;
    $request->driver = null;
    $request->set_as_default = false;

    // Ensure validateCredentials is not called
    Mockery::mock('alias:' . FileDisk::class)
        ->shouldNotReceive('validateCredentials');

    // Mock FileDiskResource constructor
    Mockery::mock('alias:' . FileDiskResource::class)
        ->shouldReceive('__construct')
        ->with($disk)
        ->andReturn(new FileDiskResource($disk));

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $result = $controller->update($disk, $request);

    // Assert
    expect($result)->toBeInstanceOf(FileDiskResource::class);
});

test('update method does not update credentials for SYSTEM disk type even if provided', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $disk = Mockery::mock(FileDisk::class);
    $disk->type = 'SYSTEM'; // This will prevent credentials update
    $disk->shouldNotReceive('updateDisk');
    $disk->shouldNotReceive('setAsDefaultDisk'); // set_as_default is false in this test

    $request = Mockery::mock(Request::class);
    $request->credentials = ['key' => 'valid'];
    $request->driver = 's3';
    $request->set_as_default = false;

    // Ensure validateCredentials is NOT called because type is 'SYSTEM'
    Mockery::mock('alias:' . FileDisk::class)
        ->shouldNotReceive('validateCredentials');

    // Mock FileDiskResource constructor
    Mockery::mock('alias:' . FileDiskResource::class)
        ->shouldReceive('__construct')
        ->with($disk)
        ->andReturn(new FileDiskResource($disk));

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $result = $controller->update($disk, $request);

    // Assert
    expect($result)->toBeInstanceOf(FileDiskResource::class);
});


test('show method authorizes and returns local disk data structure', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $diskName = 'local';
    $localRoot = '/var/www/html/storage/app';

    // Mock config helper
    Mockery::mock('alias:Config')
        ->shouldReceive('get')
        ->with('filesystems.disks.local.root')
        ->andReturn($localRoot)
        ->once();

    // Mock response helper
    Mockery::mock('alias:Response')
        ->shouldReceive('json')
        ->with(['root' => $localRoot])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $response = $controller->show($diskName);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray(['root' => $localRoot]);
});

test('show method authorizes and returns s3 disk data structure', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $diskName = 's3';

    // Mock response helper
    Mockery::mock('alias:Response')
        ->shouldReceive('json')
        ->with([
            'key' => '',
            'secret' => '',
            'region' => '',
            'bucket' => '',
            'root' => '',
        ])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $response = $controller->show($diskName);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'key' => '',
        'secret' => '',
        'region' => '',
        'bucket' => '',
        'root' => '',
    ]);
});

test('show method authorizes and returns doSpaces disk data structure', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $diskName = 'doSpaces';

    // Mock response helper
    Mockery::mock('alias:Response')
        ->shouldReceive('json')
        ->with([
            'key' => '',
            'secret' => '',
            'region' => '',
            'bucket' => '',
            'endpoint' => '',
            'root' => '',
        ])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $response = $controller->show($diskName);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'key' => '',
        'secret' => '',
        'region' => '',
        'bucket' => '',
        'endpoint' => '',
        'root' => '',
    ]);
});

test('show method authorizes and returns dropbox disk data structure', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $diskName = 'dropbox';

    // Mock response helper
    Mockery::mock('alias:Response')
        ->shouldReceive('json')
        ->with([
            'token' => '',
            'key' => '',
            'secret' => '',
            'app' => '',
            'root' => '',
        ])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $response = $controller->show($diskName);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'token' => '',
        'key' => '',
        'secret' => '',
        'app' => '',
        'root' => '',
    ]);
});

test('show method authorizes and returns empty array for unknown disk type', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $diskName = 'unknown';

    // Mock response helper
    Mockery::mock('alias:Response')
        ->shouldReceive('json')
        ->with([])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $response = $controller->show($diskName);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([]);
});

test('destroy method authorizes and returns not allowed response for default system disk', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $disk = Mockery::mock(FileDisk::class);
    $disk->type = 'SYSTEM';
    $disk->shouldReceive('setAsDefault')->andReturn(true)->once(); // True means it IS the default
    $disk->shouldNotReceive('delete'); // Should not delete

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $response = $controller->destroy($disk);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'code' => 'not_allowed',
        'message' => 'Not Allowed'
    ]);
    expect($response->getStatusCode())->toBe(400);
});

test('destroy method authorizes and successfully deletes a non-system disk', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $disk = Mockery::mock(FileDisk::class);
    $disk->type = 'CUSTOM';
    $disk->shouldReceive('setAsDefault')->andReturn(false)->once(); // Not the default
    $disk->shouldReceive('delete')->once();

    // Mock response helper
    Mockery::mock('alias:Response')
        ->shouldReceive('json')
        ->with(['success' => true])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $response = $controller->destroy($disk);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray(['success' => true]);
});

test('destroy method authorizes and successfully deletes a system disk that is not default', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $disk = Mockery::mock(FileDisk::class);
    $disk->type = 'SYSTEM';
    $disk->shouldReceive('setAsDefault')->andReturn(false)->once(); // Not the default
    $disk->shouldReceive('delete')->once();

    // Mock response helper
    Mockery::mock('alias:Response')
        ->shouldReceive('json')
        ->with(['success' => true])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $response = $controller->destroy($disk);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray(['success' => true]);
});

test('getDiskDrivers method authorizes and returns list of drivers and default', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $defaultDisk = 's3';

    // Mock config helper
    Mockery::mock('alias:Config')
        ->shouldReceive('get')
        ->with('filesystems.default')
        ->andReturn($defaultDisk)
        ->once();

    $expectedDrivers = [
        ['name' => 'Local', 'value' => 'local'],
        ['name' => 'Amazon S3', 'value' => 's3'],
        ['name' => 'Digital Ocean Spaces', 'value' => 'doSpaces'],
        ['name' => 'Dropbox', 'value' => 'dropbox'],
    ];

    // Mock response helper
    Mockery::mock('alias:Response')
        ->shouldReceive('json')
        ->with([
            'drivers' => $expectedDrivers,
            'default' => $defaultDisk,
        ])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    // Expect authorization check
    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Act
    $response = $controller->getDiskDrivers();

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'drivers' => $expectedDrivers,
        'default' => $defaultDisk,
    ]);
});




afterEach(function () {
    Mockery::close();
});
