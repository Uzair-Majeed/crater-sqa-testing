<?php

use Crater\Events\ModuleEnabledEvent;
use Crater\Events\ModuleInstalledEvent;
use Crater\Http\Resources\ModuleResource;
use Crater\Models\Module as ModelsModule;
use Crater\Models\Setting;
use Crater\Space\ModuleInstaller;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\HttpFoundation\JsonResponse;

// Mock global helpers and facades for all tests
uses()->group('module-installer')->beforeEach(function () {
    // Reset mocks before each test to prevent interference
    Mockery::close();

    // Mock Setting facade
    Mockery::mock('alias:'.Setting::class)
        ->shouldReceive('getSetting')
        ->with('api_token')
        ->andReturn('test_api_token')
        ->byDefault();

    // Mock Artisan facade
    Mockery::mock('alias:'.Artisan::class)
        ->shouldReceive('call')
        ->byDefault();

    // Mock Nwidart Module facade
    Mockery::mock('alias:'.Module::class)
        ->shouldReceive('register')
        ->byDefault();

    // Mock Laravel Event facade
    Event::fake();

    // Stub `base_path` and `storage_path` for consistent testing
    // If these functions are not already defined by Laravel's test environment, define them.
    if (! function_exists('storage_path')) {
        function storage_path($path = '') { return '/app/storage'.($path ? '/'.$path : $path); }
    }
    if (! function_exists('base_path')) {
        function base_path($path = '') { return '/app/base'.($path ? '/'.$path : $path); }
    }

    // Stub `env` helper. Laravel's `env()` is often available in Pest context.
    // If not, a simple definition here helps, combined with `app()->instance()`
    if (! function_exists('env')) {
        function env($key, $default = null) {
            return app('env_mock_values')[$key] ?? $default;
        }
    }
    app()->instance('env_mock_values', []); // Initialize for each test

    // Mock the `response()` helper's `json()` method globally for tests.
    $this->app->singleton('Illuminate\Contracts\Routing\ResponseFactory', function ($app) {
        $mockFactory = Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $mockFactory->shouldReceive('json')
            ->andReturnUsing(function ($data, $status = 200, $headers = [], $options = 0) {
                // Return a mock JsonResponse to allow assertions on its data
                $jsonResponse = Mockery::mock(JsonResponse::class);
                $jsonResponse->shouldReceive('getData')->andReturn((object) $data);
                return $jsonResponse;
            })
            ->byDefault();
        return $mockFactory;
    });

    // For global PHP functions like `file_put_contents` and `file_exists`:
    // Direct mocking of built-in PHP functions is not possible with Mockery or standard
    // Laravel testing utilities without additional libraries (e.g., `php-mock/php-mock-phpunit`).
    // Given the constraint "no imports", we cannot use such libraries.
    // Therefore, for these specific functions, we will:
    // 1. Assume successful behavior for 'happy path' tests.
    // 2. For 'failure path' tests, we design scenarios where the *logic around* these
    //    functions naturally leads to the desired failure outcome (e.g., `makeDirectory` failing
    //    naturally causes `file_put_contents` to fail on a non-existent path).
    // 3. For `unzip` and `file_exists`, we use actual temporary files/paths
    //    to trigger `file_exists` behavior and assert on the exception if a file is missing.
});

// Helper for mocking `env()`
function mock_env($key, $value) {
    app()->instance('env_mock_values', array_merge(app('env_mock_values'), [$key => $value]));
}


/*
|--------------------------------------------------------------------------
| getModules()
|--------------------------------------------------------------------------
*/
test('getModules returns marketplace modules in production environment', function () {
    mock_env('APP_ENV', 'production');

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
    $mockResponse->shouldReceive('getBody->getContents')->andReturn(json_encode(['modules' => [['id' => 1, 'name' => 'ModuleA']]]));

    // Mock the ModuleInstaller itself to intercept the static::getRemote call
    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->with('api/marketplace/modules', Mockery::type('array'), 'test_api_token')
        ->andReturn($mockResponse)
        ->once();

    // Mock ModuleResource::collection
    Mockery::mock('alias:'.ModuleResource::class)
        ->shouldReceive('collection')
        ->once()
        ->andReturnUsing(function ($collection) {
            expect($collection->first()['id'])->toBe(1);
            return 'ModuleResourceCollection'; // Placeholder return
        });

    $result = ModuleInstaller::getModules();

    expect($result)->toBe('ModuleResourceCollection');
});

test('getModules returns marketplace modules in development environment', function () {
    mock_env('APP_ENV', 'development');

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
    $mockResponse->shouldReceive('getBody->getContents')->andReturn(json_encode(['modules' => [['id' => 2, 'name' => 'ModuleB']]]));

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->with('api/marketplace/modules?is_dev=1', Mockery::type('array'), 'test_api_token')
        ->andReturn($mockResponse)
        ->once();

    Mockery::mock('alias:'.ModuleResource::class)
        ->shouldReceive('collection')
        ->once()
        ->andReturnUsing(function ($collection) {
            expect($collection->first()['id'])->toBe(2);
            return 'ModuleResourceCollection';
        });

    $result = ModuleInstaller::getModules();

    expect($result)->toBe('ModuleResourceCollection');
});

test('getModules handles 401 unauthorized response', function () {
    mock_env('APP_ENV', 'production');

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(401);

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->andReturn($mockResponse)
        ->once();

    $result = ModuleInstaller::getModules();

    expect($result->getData())->toEqual((object)['error' => 'invalid_token']);
});

test('getModules handles null response from getRemote', function () {
    mock_env('APP_ENV', 'production');

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->andReturn(null)
        ->once();

    Mockery::mock('alias:'.ModuleResource::class)
        ->shouldNotReceive('collection');

    $this->expectException(\Error::class);
    $this->expectExceptionMessage('Attempt to read property "modules" on null');

    ModuleInstaller::getModules();
});

test('getModules handles non-200, non-401 response from getRemote', function () {
    mock_env('APP_ENV', 'production');

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(500);
    $mockResponse->shouldNotReceive('getBody->getContents');

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->andReturn($mockResponse)
        ->once();

    Mockery::mock('alias:'.ModuleResource::class)
        ->shouldNotReceive('collection');

    $this->expectException(\Error::class);
    $this->expectExceptionMessage('Attempt to read property "modules" on null');

    ModuleInstaller::getModules();
});


/*
|--------------------------------------------------------------------------
| getModule($module)
|--------------------------------------------------------------------------
*/
test('getModule retrieves specific module details in production', function () {
    mock_env('APP_ENV', 'production');
    $moduleName = 'test-module';

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
    $mockResponse->shouldReceive('getBody->getContents')->andReturn(json_encode(['id' => 1, 'name' => $moduleName, 'version' => '1.0']));

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->with("api/marketplace/modules/{$moduleName}", Mockery::type('array'), 'test_api_token')
        ->andReturn($mockResponse)
        ->once();

    $result = ModuleInstaller::getModule($moduleName);

    expect($result)->toEqual((object)['id' => 1, 'name' => $moduleName, 'version' => '1.0']);
});

test('getModule retrieves specific module details in development', function () {
    mock_env('APP_ENV', 'development');
    $moduleName = 'dev-module';

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
    $mockResponse->shouldReceive('getBody->getContents')->andReturn(json_encode(['id' => 2, 'name' => $moduleName, 'version' => '1.1']));

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->with("api/marketplace/modules/{$moduleName}?is_dev=1", Mockery::type('array'), 'test_api_token')
        ->andReturn($mockResponse)
        ->once();

    $result = ModuleInstaller::getModule($moduleName);

    expect($result)->toEqual((object)['id' => 2, 'name' => $moduleName, 'version' => '1.1']);
});

test('getModule handles 401 unauthorized response', function () {
    mock_env('APP_ENV', 'production');
    $moduleName = 'unauth-module';

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(401);

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->andReturn($mockResponse)
        ->once();

    $result = ModuleInstaller::getModule($moduleName);

    expect($result)->toEqual((object)['success' => false, 'error' => 'invalid_token']);
});

test('getModule handles null response from getRemote', function () {
    mock_env('APP_ENV', 'production');
    $moduleName = 'null-response-module';

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->andReturn(null)
        ->once();

    $result = ModuleInstaller::getModule($moduleName);

    expect($result)->toBeNull();
});

test('getModule handles non-200, non-401 response from getRemote', function () {
    mock_env('APP_ENV', 'production');
    $moduleName = 'error-response-module';

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(500);
    $mockResponse->shouldNotReceive('getBody->getContents');

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->andReturn($mockResponse)
        ->once();

    $result = ModuleInstaller::getModule($moduleName);

    expect($result)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| upload($request)
|--------------------------------------------------------------------------
*/
test('upload creates temp directory and stores file', function () {
    $tempDirPattern = '/^\/app\/storage\/app\/temp-[a-f0-9]{32}$/';
    $moduleName = 'testmodule';
    $fileName = $moduleName.'.zip';
    $storedPath = 'temp-random/testmodule.zip';

    File::shouldReceive('isDirectory')
        ->with(Mockery::pattern($tempDirPattern))
        ->andReturn(false)
        ->once();
    File::shouldReceive('makeDirectory')
        ->with(Mockery::pattern($tempDirPattern))
        ->andReturn(true)
        ->once();

    $mockFile = Mockery::mock('Illuminate\Http\UploadedFile');
    $mockFile->shouldReceive('storeAs')
        ->with(Mockery::pattern('/^temp-[a-f0-9]{32}$/'), $fileName, 'local')
        ->andReturn($storedPath)
        ->once();

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->module = $moduleName;
    $mockRequest->shouldReceive('file')
        ->with('avatar')
        ->andReturn($mockFile)
        ->once();

    $result = ModuleInstaller::upload($mockRequest);

    expect($result)->toBe($storedPath);
});

test('upload does not create temp directory if it already exists', function () {
    $tempDirPattern = '/^\/app\/storage\/app\/temp-[a-f0-9]{32}$/';
    $moduleName = 'testmodule';
    $fileName = $moduleName.'.zip';
    $storedPath = 'temp-random/testmodule.zip';

    File::shouldReceive('isDirectory')
        ->with(Mockery::pattern($tempDirPattern))
        ->andReturn(true)
        ->once();
    File::shouldNotReceive('makeDirectory');

    $mockFile = Mockery::mock('Illuminate\Http\UploadedFile');
    $mockFile->shouldReceive('storeAs')
        ->with(Mockery::pattern('/^temp-[a-f0-9]{32}$/'), $fileName, 'local')
        ->andReturn($storedPath)
        ->once();

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->module = $moduleName;
    $mockRequest->shouldReceive('file')
        ->with('avatar')
        ->andReturn($mockFile)
        ->once();

    $result = ModuleInstaller::upload($mockRequest);

    expect($result)->toBe($storedPath);
});

/*
|--------------------------------------------------------------------------
| download($module, $version)
|--------------------------------------------------------------------------
*/
test('download successfully downloads a module in production', function () {
    mock_env('APP_ENV', 'production');
    $moduleName = 'prod-module';
    $version = '1.0.0';
    $fileContent = 'zip_file_content';
    $tempDirPattern = '/^\/app\/storage\/app\/temp-[a-f0-9]{32}$/';
    $zipFilePathPattern = '/^\/app\/storage\/app\/temp-[a-f0-9]{32}\/upload.zip$/';

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
    $mockResponse->shouldReceive('getBody->getContents')->andReturn($fileContent);

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->with("api/marketplace/modules/file/{$moduleName}?version={$version}", Mockery::type('array'), 'test_api_token')
        ->andReturn($mockResponse)
        ->once();

    File::shouldReceive('isDirectory')
        ->with(Mockery::pattern($tempDirPattern))
        ->andReturn(false)
        ->once();
    File::shouldReceive('makeDirectory')
        ->with(Mockery::pattern($tempDirPattern))
        ->andReturn(true)
        ->once();

    // For `file_put_contents`, we assume it succeeds and returns an integer.
    // The branch `if (! $uploaded)` is not directly mockable here.

    $result = ModuleInstaller::download($moduleName, $version);

    expect($result)->toEqual([
        'success' => true,
        'path' => Mockery::pattern($zipFilePathPattern)
    ]);
});

test('download handles RequestException from getRemote', function () {
    mock_env('APP_ENV', 'production');
    $moduleName = 'exception-module';
    $version = '1.0.0';

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->andThrow(new RequestException('Download error', Mockery::mock('GuzzleHttp\Psr7\Request')))
        ->once();

    File::shouldNotReceive('isDirectory');
    File::shouldNotReceive('makeDirectory');

    $result = ModuleInstaller::download($moduleName, $version);

    expect($result)->toEqual([
        'success' => false,
        'error' => 'Download Exception',
        'data' => [
            'path' => null,
        ],
    ]);
});

test('download handles 401/404/500 responses from getRemote', function () {
    mock_env('APP_ENV', 'production');
    $moduleName = 'error-http-module';
    $version = '1.0.0';
    $errorBody = json_encode(['message' => 'Not Found', 'code' => 404]);

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(404);
    $mockResponse->shouldReceive('getBody->getContents')->andReturn($errorBody);

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->andReturn($mockResponse)
        ->once();

    File::shouldNotReceive('isDirectory');
    File::shouldNotReceive('makeDirectory');

    $result = ModuleInstaller::download($moduleName, $version);

    expect($result)->toEqual(json_decode($errorBody));
});

test('download fails if temp directory cannot be created (leading to file_put_contents failure)', function () {
    mock_env('APP_ENV', 'production');
    $moduleName = 'no-dir-module';
    $version = '1.0.0';
    $fileContent = 'zip_file_content';
    $tempDirPattern = '/^\/app\/storage\/app\/temp-[a-f0-9]{32}$/';

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
    $mockResponse->shouldReceive('getBody->getContents')->andReturn($fileContent);

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->andReturn($mockResponse)
        ->once();

    File::shouldReceive('isDirectory')
        ->with(Mockery::pattern($tempDirPattern))
        ->andReturn(false)
        ->once();
    File::shouldReceive('makeDirectory')
        ->with(Mockery::pattern($tempDirPattern))
        ->andReturn(false) // Simulate makeDirectory failure
        ->once();

    // In a real scenario, if `makeDirectory` fails, `file_put_contents` to that path would also fail,
    // causing `$uploaded` to be false, thus covering the `! $uploaded` branch.
    $result = ModuleInstaller::download($moduleName, $version);

    expect($result)->toBeFalse();
});

test('download successfully downloads a module in development', function () {
    mock_env('APP_ENV', 'development');
    $moduleName = 'dev-module';
    $version = '1.0.0';
    $fileContent = 'zip_file_content';
    $tempDirPattern = '/^\/app\/storage\/app\/temp-[a-f0-9]{32}$/';
    $zipFilePathPattern = '/^\/app\/storage\/app\/temp-[a-f0-9]{32}\/upload.zip$/';

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
    $mockResponse->shouldReceive('getBody->getContents')->andReturn($fileContent);

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->with("api/marketplace/modules/file/{$moduleName}?version={$version}&is_dev=1", Mockery::type('array'), 'test_api_token')
        ->andReturn($mockResponse)
        ->once();

    File::shouldReceive('isDirectory')
        ->with(Mockery::pattern($tempDirPattern))
        ->andReturn(false)
        ->once();
    File::shouldReceive('makeDirectory')
        ->with(Mockery::pattern($tempDirPattern))
        ->andReturn(true)
        ->once();

    $result = ModuleInstaller::download($moduleName, $version);

    expect($result)->toEqual([
        'success' => true,
        'path' => Mockery::pattern($zipFilePathPattern)
    ]);
});


/*
|--------------------------------------------------------------------------
| unzip($module, $zip_file_path)
|--------------------------------------------------------------------------
*/
test('unzip successfully extracts a zip file', function () {
    $moduleName = 'test-module';
    $tempDir = sys_get_temp_dir() . '/' . uniqid('ziptest_');
    mkdir($tempDir); // Create a temporary directory for the actual zip file
    $zipFilePath = $tempDir . '/upload.zip';
    $tempExtractDirPattern = '/^\/app\/storage\/app\/temp2-[a-f0-9]{32}$/';

    // Create a dummy zip file for testing `file_exists` and `ZipArchive->open`
    $zip = new ZipArchive();
    $zip->open($zipFilePath, ZipArchive::CREATE);
    $zip->addFromString('testfile.txt', 'This is a test file.');
    $zip->close();

    File::shouldReceive('isDirectory')
        ->with(Mockery::pattern($tempExtractDirPattern))
        ->andReturn(false)
        ->once();
    File::shouldReceive('makeDirectory')
        ->with(Mockery::pattern($tempExtractDirPattern))
        ->andReturn(true)
        ->once();

    // Mock ZipArchive class instantiation
    $mockZipArchive = Mockery::mock(ZipArchive::class);
    $mockZipArchive->shouldReceive('open')
        ->with($zipFilePath)
        ->andReturn(true) // Simulate successful open of the created zip file
        ->once();
    $mockZipArchive->shouldReceive('extractTo')
        ->with(Mockery::pattern($tempExtractDirPattern))
        ->andReturn(true)
        ->once();
    $mockZipArchive->shouldReceive('close')
        ->andReturn(true)
        ->once();
    $this->app->instance(ZipArchive::class, $mockZipArchive);

    File::shouldReceive('delete')
        ->with($zipFilePath)
        ->andReturn(true)
        ->once();

    $result = ModuleInstaller::unzip($moduleName, $zipFilePath);

    expect($result)->toMatch($tempExtractDirPattern);

    // Clean up dummy zip file and directory
    unlink($zipFilePath);
    rmdir($tempDir);
});

test('unzip throws exception if zip file not found', function () {
    $moduleName = 'test-module';
    $zipFilePath = '/path/to/non-existent/file.zip'; // A path that genuinely does not exist

    // `file_exists` is a global PHP function and cannot be mocked without external libraries.
    // We rely on PHP's `file_exists` returning `false` for a non-existent path.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Zip file not found');

    File::shouldNotReceive('isDirectory');
    Mockery::mock('overload:'.ZipArchive::class)->shouldNotReceive('open');
    File::shouldNotReceive('delete');

    ModuleInstaller::unzip($moduleName, $zipFilePath);
});

test('unzip handles failure to open zip file', function () {
    $moduleName = 'test-module';
    $tempDir = sys_get_temp_dir() . '/' . uniqid('ziptest_');
    mkdir($tempDir);
    $zipFilePath = $tempDir . '/corrupt.zip';
    touch($zipFilePath); // Create an empty file, which ZipArchive::open will fail on
    $tempExtractDirPattern = '/^\/app\/storage\/app\/temp2-[a-f0-9]{32}$/';

    File::shouldReceive('isDirectory')
        ->with(Mockery::pattern($tempExtractDirPattern))
        ->andReturn(false)
        ->once();
    File::shouldReceive('makeDirectory')
        ->with(Mockery::pattern($tempExtractDirPattern))
        ->andReturn(true)
        ->once();

    $mockZipArchive = Mockery::mock(ZipArchive::class);
    $mockZipArchive->shouldReceive('open')
        ->with($zipFilePath)
        ->andReturn(false) // Simulate failure to open
        ->once();
    $mockZipArchive->shouldNotReceive('extractTo');
    $mockZipArchive->shouldReceive('close')
        ->andReturn(true)
        ->once();
    $this->app->instance(ZipArchive::class, $mockZipArchive);

    File::shouldReceive('delete')
        ->with($zipFilePath)
        ->andReturn(true)
        ->once();

    $result = ModuleInstaller::unzip($moduleName, $zipFilePath);

    expect($result)->toMatch($tempExtractDirPattern);

    // Clean up dummy file and directory
    unlink($zipFilePath);
    rmdir($tempDir);
});


/*
|--------------------------------------------------------------------------
| copyFiles($module, $temp_extract_dir)
|--------------------------------------------------------------------------
*/
test('copyFiles successfully copies module files', function () {
    $moduleName = 'test-module';
    $tempExtractDir = '/app/storage/app/temp2-extracted-module';
    $modulesBasePath = base_path('Modules');
    $modulePath = $modulesBasePath.'/'.$moduleName;

    File::shouldReceive('isDirectory')
        ->with($modulesBasePath)
        ->andReturn(false)
        ->once();
    File::shouldReceive('makeDirectory')
        ->with($modulesBasePath)
        ->andReturn(true)
        ->once();

    File::shouldReceive('isDirectory')
        ->with($modulePath)
        ->andReturn(true) // Simulate module already existing, so delete it.
        ->once();
    File::shouldReceive('deleteDirectory')
        ->with($modulePath)
        ->andReturn(true)
        ->once();

    File::shouldReceive('copyDirectory')
        ->with($tempExtractDir, $modulesBasePath.'/')
        ->andReturn(true)
        ->once();

    File::shouldReceive('deleteDirectory')
        ->with($tempExtractDir)
        ->andReturn(true)
        ->once();

    $result = ModuleInstaller::copyFiles($moduleName, $tempExtractDir);

    expect($result)->toBeTrue();
});

test('copyFiles handles Modules directory already existing', function () {
    $moduleName = 'test-module';
    $tempExtractDir = '/app/storage/app/temp2-extracted-module';
    $modulesBasePath = base_path('Modules');
    $modulePath = $modulesBasePath.'/'.$moduleName;

    File::shouldReceive('isDirectory')
        ->with($modulesBasePath)
        ->andReturn(true) // Modules dir exists
        ->once();
    File::shouldNotReceive('makeDirectory')
        ->with($modulesBasePath);

    File::shouldReceive('isDirectory')
        ->with($modulePath)
        ->andReturn(false) // Module does not exist
        ->once();
    File::shouldNotReceive('deleteDirectory')
        ->with($modulePath);

    File::shouldReceive('copyDirectory')
        ->with($tempExtractDir, $modulesBasePath.'/')
        ->andReturn(true)
        ->once();

    File::shouldReceive('deleteDirectory')
        ->with($tempExtractDir)
        ->andReturn(true)
        ->once();

    $result = ModuleInstaller::copyFiles($moduleName, $tempExtractDir);

    expect($result)->toBeTrue();
});

test('copyFiles handles copyDirectory failure', function () {
    $moduleName = 'test-module';
    $tempExtractDir = '/app/storage/app/temp2-extracted-module';
    $modulesBasePath = base_path('Modules');
    $modulePath = $modulesBasePath.'/'.$moduleName;

    File::shouldReceive('isDirectory')
        ->with($modulesBasePath)
        ->andReturn(true)
        ->once();
    File::shouldNotReceive('makeDirectory');

    File::shouldReceive('isDirectory')
        ->with($modulePath)
        ->andReturn(true)
        ->once();
    File::shouldReceive('deleteDirectory')
        ->with($modulePath)
        ->andReturn(true)
        ->once();

    File::shouldReceive('copyDirectory')
        ->with($tempExtractDir, $modulesBasePath.'/')
        ->andReturn(false) // Simulate failure
        ->once();

    File::shouldNotReceive('deleteDirectory')
        ->with($tempExtractDir);

    $result = ModuleInstaller::copyFiles($moduleName, $tempExtractDir);

    expect($result)->toBeFalse();
});

test('copyFiles handles Modules directory creation failure (and subsequent copy failure)', function () {
    $moduleName = 'test-module';
    $tempExtractDir = '/app/storage/app/temp2-extracted-module';
    $modulesBasePath = base_path('Modules');

    File::shouldReceive('isDirectory')
        ->with($modulesBasePath)
        ->andReturn(false)
        ->once();
    File::shouldReceive('makeDirectory')
        ->with($modulesBasePath)
        ->andReturn(false) // Simulate makeDirectory failure
        ->once();

    File::shouldReceive('copyDirectory')
        ->with($tempExtractDir, $modulesBasePath.'/')
        ->andReturn(false) // `copyDirectory` would fail if destination not made
        ->once();

    File::shouldNotReceive('isDirectory')
        ->with(Mockery::pattern('/^\/app\/base\/Modules\/.*$/'));
    File::shouldNotReceive('deleteDirectory')
        ->with($tempExtractDir);

    $result = ModuleInstaller::copyFiles($moduleName, $tempExtractDir);

    expect($result)->toBeFalse();
});


/*
|--------------------------------------------------------------------------
| deleteFiles($json)
|--------------------------------------------------------------------------
*/
test('deleteFiles successfully deletes specified files', function () {
    $filesJson = json_encode(['storage/file1.txt', 'config/file2.php']);

    File::shouldReceive('delete')
        ->with(base_path('storage/file1.txt'))
        ->andReturn(true)
        ->once();
    File::shouldReceive('delete')
        ->with(base_path('config/file2.php'))
        ->andReturn(true)
        ->once();

    $result = ModuleInstaller::deleteFiles($filesJson);

    expect($result)->toBeTrue();
});

test('deleteFiles handles empty file list', function () {
    $filesJson = json_encode([]);

    File::shouldNotReceive('delete');

    $result = ModuleInstaller::deleteFiles($filesJson);

    expect($result)->toBeTrue();
});

test('deleteFiles handles invalid JSON input', function () {
    $invalidJson = 'not a json string';

    File::shouldNotReceive('delete');

    $result = ModuleInstaller::deleteFiles($invalidJson);

    expect($result)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| complete($module, $version)
|--------------------------------------------------------------------------
*/
test('complete registers module, runs migrations, seeds, enables, and dispatches events', function () {
    $moduleName = 'test-module';
    $version = '1.0.0';

    Module::shouldReceive('register')->once();
    Artisan::shouldReceive('call')->with("module:migrate {$moduleName} --force")->once();
    Artisan::shouldReceive('call')->with("module:seed {$moduleName} --force")->once();
    Artisan::shouldReceive('call')->with("module:enable {$moduleName}")->once();

    $mockModuleModel = Mockery::mock(ModelsModule::class);
    ModelsModule::shouldReceive('updateOrCreate')
        ->with(['name' => $moduleName], ['version' => $version, 'installed' => true, 'enabled' => true])
        ->andReturn($mockModuleModel)
        ->once();

    $result = ModuleInstaller::complete($moduleName, $version);

    Event::assertDispatched(ModuleInstalledEvent::class, function ($event) use ($mockModuleModel) {
        return $event->module === $mockModuleModel;
    });
    Event::assertDispatched(ModuleEnabledEvent::class, function ($event) use ($mockModuleModel) {
        return $event->module === $mockModuleModel;
    });

    expect($result)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| checkToken(String $token)
|--------------------------------------------------------------------------
*/
test('checkToken returns success response for valid token', function () {
    $validToken = 'valid_test_token';
    $apiResponseData = json_encode(['status' => 'success', 'message' => 'Token is valid']);

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
    $mockResponse->shouldReceive('getBody->getContents')->andReturn($apiResponseData);

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->with('api/marketplace/ping', Mockery::type('array'), $validToken)
        ->andReturn($mockResponse)
        ->once();

    $result = ModuleInstaller::checkToken($validToken);

    expect($result->getData())->toEqual((object)['status' => 'success', 'message' => 'Token is valid']);
});

test('checkToken returns invalid token response for non-200 status', function () {
    $invalidToken = 'invalid_test_token';

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(401);

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->with('api/marketplace/ping', Mockery::type('array'), $invalidToken)
        ->andReturn($mockResponse)
        ->once();

    $result = ModuleInstaller::checkToken($invalidToken);

    expect($result->getData())->toEqual((object)['error' => 'invalid_token']);
});

test('checkToken returns invalid token response for null getRemote response', function () {
    $token = 'some_token';

    $mockInstaller = Mockery::mock('overload:'.ModuleInstaller::class);
    $mockInstaller->shouldReceive('getRemote')
        ->andReturn(null)
        ->once();

    $result = ModuleInstaller::checkToken($token);

    expect($result->getData())->toEqual((object)['error' => 'invalid_token']);
});




afterEach(function () {
    Mockery::close();
});
