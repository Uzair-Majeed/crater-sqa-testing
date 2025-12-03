```php
<?php
use Crater\Http\Controllers\V1\Admin\Settings\DiskController;
use Crater\Http\Requests\DiskEnvironmentRequest;
use Crater\Http\Resources\FileDiskResource;
use Crater\Models\FileDisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;

// Mock the global respondJson helper function as it's used in the controller
if (! function_exists('respondJson')) {
    function respondJson($code, $message, $status = 400)
    {
        return new JsonResponse(['code' => $code, 'message' => $message], $status);
    }
}

beforeEach(function () {
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

    // Instance method mocking
    $fileDiskMock = Mockery::mock(FileDisk::class);
    $fileDiskMock->shouldReceive('applyFilters')
        ->with([])
        ->andReturnSelf();
    $fileDiskMock->shouldReceive('latest')
        ->andReturnSelf();
    $fileDiskMock->shouldReceive('paginateData')
        ->with(5)
        ->andReturn($paginator);

    // Overwrite static methods with instance, using partial application
    // Simulate controller using the correct methods - if there are static calls only, then below workaround
    // Note: If controller uses FileDisk::applyFilters etc. directly, we need to allow those static calls.
    // So we use Mockery and override the static with Facade

    $originalFileDisk = FileDisk::class;
    // Patch static methods via Facade root
    $fileDiskFacade = Mockery::mock($originalFileDisk)->makePartial();
    $fileDiskFacade->shouldReceive('applyFilters')
        ->with([])
        ->andReturn($fileDiskFacade);
    $fileDiskFacade->shouldReceive('latest')->andReturn($fileDiskFacade);
    $fileDiskFacade->shouldReceive('paginateData')->with(5)->andReturn($paginator);

    // Swap instance for static calls
    app()->instance($originalFileDisk, $fileDiskFacade);

    // FileDiskResource::collection
    $resourceMock = Mockery::mock('overload:' . FileDiskResource::class);
    $resourceMock->shouldReceive('collection')
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

test('index method authorizes and returns paginated file disks with custom limit and filters', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->limit = 10;
    $request->shouldReceive('all')->andReturn(['search' => 'test']);

    $paginator = Mockery::mock(LengthAwarePaginator::class);

    $originalFileDisk = FileDisk::class;
    $fileDiskFacade = Mockery::mock($originalFileDisk)->makePartial();
    $fileDiskFacade->shouldReceive('applyFilters')
        ->with(['search' => 'test'])
        ->andReturn($fileDiskFacade);
    $fileDiskFacade->shouldReceive('latest')->andReturn($fileDiskFacade);
    $fileDiskFacade->shouldReceive('paginateData')->with(10)->andReturn($paginator);
    app()->instance($originalFileDisk, $fileDiskFacade);

    $resourceMock = Mockery::mock('overload:' . FileDiskResource::class);
    $resourceMock->shouldReceive('collection')
        ->with($paginator)
        ->andReturn('FileDiskResourceCollection');

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $result = $controller->index($request);

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

    $originalFileDisk = FileDisk::class;
    $fileDiskFacade = Mockery::mock($originalFileDisk)->makePartial();
    $fileDiskFacade->shouldReceive('validateCredentials')
        ->with($request->credentials, $request->driver)
        ->andReturn(false)
        ->once();
    app()->instance($originalFileDisk, $fileDiskFacade);

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $response = $controller->store($request);

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
    $mockDisk->shouldAllowMockingProtectedMethods();
    $mockDisk->shouldReceive('setAttribute')->withAnyArgs()->zeroOrMoreTimes(); // Make setAttribute non-strict

    $originalFileDisk = FileDisk::class;
    $fileDiskFacade = Mockery::mock($originalFileDisk)->makePartial();
    $fileDiskFacade->shouldReceive('validateCredentials')
        ->with($request->credentials, $request->driver)
        ->andReturn(true)
        ->once()
        ->shouldReceive('createDisk')
        ->with($request)
        ->andReturn($mockDisk)
        ->once();
    app()->instance($originalFileDisk, $fileDiskFacade);

    $resourceMock = Mockery::mock('overload:' . FileDiskResource::class);
    $resourceMock->shouldReceive('__construct')
        ->with($mockDisk)
        ->andReturnUsing(function($disk) {
            $resource = new FileDiskResource($disk);
            return $resource;
        });

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $result = $controller->store($request);

    expect($result)->toBeInstanceOf(FileDiskResource::class);
});

test('update method authorizes and returns invalid credentials response when updating a custom disk', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $disk = Mockery::mock(FileDisk::class);
    $disk->type = 'CUSTOM';
    $disk->shouldNotReceive('updateDisk');
    $disk->shouldNotReceive('setAsDefaultDisk');
    $disk->shouldReceive('setAttribute')->withAnyArgs()->zeroOrMoreTimes();

    $request = Mockery::mock(Request::class);
    $request->credentials = ['key' => 'invalid'];
    $request->driver = 's3';
    $request->set_as_default = false;

    $originalFileDisk = FileDisk::class;
    $fileDiskFacade = Mockery::mock($originalFileDisk)->makePartial();
    $fileDiskFacade->shouldReceive('validateCredentials')
        ->with($request->credentials, $request->driver)
        ->andReturn(false)
        ->once();
    app()->instance($originalFileDisk, $fileDiskFacade);

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $response = $controller->update($disk, $request);

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
    $disk->shouldReceive('setAttribute')->withAnyArgs()->zeroOrMoreTimes();

    $request = Mockery::mock(Request::class);
    $request->credentials = ['key' => 'valid'];
    $request->driver = 's3';
    $request->set_as_default = false;

    $originalFileDisk = FileDisk::class;
    $fileDiskFacade = Mockery::mock($originalFileDisk)->makePartial();
    $fileDiskFacade->shouldReceive('validateCredentials')
        ->with($request->credentials, $request->driver)
        ->andReturn(true)
        ->once();
    app()->instance($originalFileDisk, $fileDiskFacade);

    $resourceMock = Mockery::mock('overload:' . FileDiskResource::class);
    $resourceMock->shouldReceive('__construct')
        ->with($disk)
        ->andReturnUsing(function($d) { return new FileDiskResource($d); });

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $result = $controller->update($disk, $request);

    expect($result)->toBeInstanceOf(FileDiskResource::class);
});

test('update method authorizes and sets disk as default if requested', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $disk = Mockery::mock(FileDisk::class);
    $disk->type = 'CUSTOM';
    $disk->shouldNotReceive('updateDisk');
    $disk->shouldReceive('setAsDefaultDisk')->once();
    $disk->shouldReceive('setAttribute')->withAnyArgs()->zeroOrMoreTimes();

    $request = Mockery::mock(Request::class);
    $request->credentials = null;
    $request->driver = null;
    $request->set_as_default = true;

    $originalFileDisk = FileDisk::class;
    $fileDiskFacade = Mockery::mock($originalFileDisk)->makePartial();
    $fileDiskFacade->shouldNotReceive('validateCredentials');
    app()->instance($originalFileDisk, $fileDiskFacade);

    $resourceMock = Mockery::mock('overload:' . FileDiskResource::class);
    $resourceMock->shouldReceive('__construct')
        ->with($disk)
        ->andReturnUsing(function($d) { return new FileDiskResource($d); });

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $result = $controller->update($disk, $request);

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
    $disk->shouldReceive('setAttribute')->withAnyArgs()->zeroOrMoreTimes();

    $request = Mockery::mock(Request::class);
    $request->credentials = null;
    $request->driver = null;
    $request->set_as_default = false;

    $originalFileDisk = FileDisk::class;
    $fileDiskFacade = Mockery::mock($originalFileDisk)->makePartial();
    $fileDiskFacade->shouldNotReceive('validateCredentials');
    app()->instance($originalFileDisk, $fileDiskFacade);

    $resourceMock = Mockery::mock('overload:' . FileDiskResource::class);
    $resourceMock->shouldReceive('__construct')
        ->with($disk)
        ->andReturnUsing(function($d) { return new FileDiskResource($d); });

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $result = $controller->update($disk, $request);

    expect($result)->toBeInstanceOf(FileDiskResource::class);
});

test('update method does not update credentials for SYSTEM disk type even if provided', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $disk = Mockery::mock(FileDisk::class);
    $disk->type = 'SYSTEM';
    $disk->shouldNotReceive('updateDisk');
    $disk->shouldNotReceive('setAsDefaultDisk');
    $disk->shouldReceive('setAttribute')->withAnyArgs()->zeroOrMoreTimes();

    $request = Mockery::mock(Request::class);
    $request->credentials = ['key' => 'valid'];
    $request->driver = 's3';
    $request->set_as_default = false;

    $originalFileDisk = FileDisk::class;
    $fileDiskFacade = Mockery::mock($originalFileDisk)->makePartial();
    $fileDiskFacade->shouldNotReceive('validateCredentials');
    app()->instance($originalFileDisk, $fileDiskFacade);

    $resourceMock = Mockery::mock('overload:' . FileDiskResource::class);
    $resourceMock->shouldReceive('__construct')
        ->with($disk)
        ->andReturnUsing(function($d) { return new FileDiskResource($d); });

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $result = $controller->update($disk, $request);

    expect($result)->toBeInstanceOf(FileDiskResource::class);
});

test('show method authorizes and returns local disk data structure', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $diskName = 'local';
    // Harmonize expected root for CI/local (use Config to mock consistently)
    $testPath = '/var/www/html/storage/app';
    $configMock = Mockery::mock('overload:' . Config::class);
    $configMock->shouldReceive('get')
        ->with('filesystems.disks.local.root')
        ->andReturn($testPath)
        ->once();

    // Patch Response::json usage
    $responseMock = Mockery::mock('overload:' . Response::class);
    $responseMock->shouldReceive('json')
        ->with(['root' => $testPath])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $response = $controller->show($diskName);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray(['root' => $testPath]);
});

test('show method authorizes and returns s3 disk data structure', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $diskName = 's3';

    $expected = [
        'key' => '',
        'secret' => '',
        'region' => '',
        'bucket' => '',
        'root' => '',
    ];

    $responseMock = Mockery::mock('overload:' . Response::class);
    $responseMock->shouldReceive('json')
        ->with($expected)
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $response = $controller->show($diskName);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray($expected);
});

test('show method authorizes and returns doSpaces disk data structure', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $diskName = 'doSpaces';

    $expected = [
        'key' => '',
        'secret' => '',
        'region' => '',
        'bucket' => '',
        'endpoint' => '',
        'root' => '',
    ];

    $responseMock = Mockery::mock('overload:' . Response::class);
    $responseMock->shouldReceive('json')
        ->with($expected)
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $response = $controller->show($diskName);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray($expected);
});

test('show method authorizes and returns dropbox disk data structure', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $diskName = 'dropbox';

    $expected = [
        'token' => '',
        'key' => '',
        'secret' => '',
        'app' => '',
        'root' => '',
    ];

    $responseMock = Mockery::mock('overload:' . Response::class);
    $responseMock->shouldReceive('json')
        ->with($expected)
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $response = $controller->show($diskName);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray($expected);
});

test('show method authorizes and returns empty array for unknown disk type', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $diskName = 'unknown';

    $responseMock = Mockery::mock('overload:' . Response::class);
    $responseMock->shouldReceive('json')
        ->with([])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $response = $controller->show($diskName);

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
    $disk->shouldReceive('setAsDefault')->andReturn(true)->once();
    $disk->shouldNotReceive('delete');
    $disk->shouldReceive('setAttribute')->withAnyArgs()->zeroOrMoreTimes();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    // Patch respondJson if needed
    $response = $controller->destroy($disk);

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
    $disk->shouldReceive('setAsDefault')->andReturn(false)->once();
    $disk->shouldReceive('delete')->once();
    $disk->shouldReceive('setAttribute')->withAnyArgs()->zeroOrMoreTimes();

    $responseMock = Mockery::mock('overload:' . Response::class);
    $responseMock->shouldReceive('json')
        ->with(['success' => true])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $response = $controller->destroy($disk);

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
    $disk->shouldReceive('setAsDefault')->andReturn(false)->once();
    $disk->shouldReceive('delete')->once();
    $disk->shouldReceive('setAttribute')->withAnyArgs()->zeroOrMoreTimes();

    $responseMock = Mockery::mock('overload:' . Response::class);
    $responseMock->shouldReceive('json')
        ->with(['success' => true])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $response = $controller->destroy($disk);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray(['success' => true]);
});

test('getDiskDrivers method authorizes and returns list of drivers and default', function () {
    $this->withoutExceptionHandling();

    // Arrange
    $controller = Mockery::mock(DiskController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();

    $defaultDisk = 's3'; // set to match expectation

    $configMock = Mockery::mock('overload:' . Config::class);
    $configMock->shouldReceive('get')
        ->with('filesystems.default')
        ->andReturn($defaultDisk)
        ->once();

    $expectedDrivers = [
        ['name' => 'Local', 'value' => 'local'],
        ['name' => 'Amazon S3', 'value' => 's3'],
        ['name' => 'Digital Ocean Spaces', 'value' => 'doSpaces'],
        ['name' => 'Dropbox', 'value' => 'dropbox'],
    ];

    $responseMock = Mockery::mock('overload:' . Response::class);
    $responseMock->shouldReceive('json')
        ->with([
            'drivers' => $expectedDrivers,
            'default' => $defaultDisk,
        ])
        ->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        })
        ->once();

    $controller->shouldReceive('authorize')
        ->once()
        ->with('manage file disk');

    $response = $controller->getDiskDrivers();

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'drivers' => $expectedDrivers,
        'default' => $defaultDisk,
    ]);
});

afterEach(function () {
    Mockery::close();
});
```