<?php

use Crater\Http\Controllers\V1\Installation\DatabaseConfigurationController;
use Crater\Http\Requests\DatabaseEnvironmentRequest;
use Crater\Space\EnvironmentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
uses(\Mockery::class);
use Illuminate\Support\Arr; // For array_key_exists check for `Arr` alternative if needed, but not directly used in controller logic.

// Use a common setup for all tests in this file
beforeEach(function () {
    // Mock the EnvironmentManager dependency
    $this->environmentManager = Mockery::mock(EnvironmentManager::class);

    // Create an instance of the controller with the mocked dependency
    $this->controller = new DatabaseConfigurationController($this->environmentManager);

    // Mock Artisan facade using a spy to track calls
    Artisan::spy();
});

// Test the constructor
test('constructor correctly assigns EnvironmentManager', function () {
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    $controller = new DatabaseConfigurationController($environmentManager);

    // Use reflection to access the protected property and assert its value
    $reflectionProperty = new ReflectionProperty(DatabaseConfigurationController::class, 'environmentManager');
    $reflectionProperty->setAccessible(true);
    $assignedManager = $reflectionProperty->getValue($controller);

    expect($assignedManager)->toBe($environmentManager);
});

// Test saveDatabaseEnvironment method - success path
test('saveDatabaseEnvironment handles successful database configuration', function () {
    // Mock DatabaseEnvironmentRequest
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);

    // Configure the environment manager mock to return a success result
    $this->environmentManager->shouldReceive('saveDatabaseVariables')
                             ->with($request)
                             ->once()
                             ->andReturn(['success' => true, 'message' => 'Database configured successfully']);

    // Expect initial Artisan calls
    Artisan::shouldReceive('call')->once()->with('config:clear');
    Artisan::shouldReceive('call')->once()->with('cache:clear');

    // Expect subsequent Artisan calls for the success path (when 'success' key exists in results)
    Artisan::shouldReceive('call')->once()->with('key:generate --force');
    Artisan::shouldReceive('call')->once()->with('optimize:clear');
    Artisan::shouldReceive('call')->once()->with('config:clear'); // Called again
    Artisan::shouldReceive('call')->once()->with('cache:clear'); // Called again
    Artisan::shouldReceive('call')->once()->with('storage:link');
    Artisan::shouldReceive('call')->once()->with('migrate --seed --force');

    // Call the method
    $response = $this->controller->saveDatabaseEnvironment($request);

    // Assert the response is a JsonResponse and its content
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)['success' => true, 'message' => 'Database configured successfully']);
});

// Test saveDatabaseEnvironment method - failure path
test('saveDatabaseEnvironment handles failed database configuration', function () {
    // Mock DatabaseEnvironmentRequest
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);

    // Configure the environment manager mock to return a failure result (no 'success' key)
    $this->environmentManager->shouldReceive('saveDatabaseVariables')
                             ->with($request)
                             ->once()
                             ->andReturn(['error' => 'Failed to write .env file', 'details' => 'Permission denied']);

    // Expect only initial Artisan calls
    Artisan::shouldReceive('call')->once()->with('config:clear');
    Artisan::shouldReceive('call')->once()->with('cache:clear');

    // Ensure subsequent Artisan calls for the success path are NOT made
    Artisan::shouldNotReceive('call')->with('key:generate --force');
    Artisan::shouldNotReceive('call')->with('optimize:clear');
    Artisan::shouldNotReceive('call')->with('storage:link');
    Artisan::shouldNotReceive('call')->with('migrate --seed --force');

    // Call the method
    $response = $this->controller->saveDatabaseEnvironment($request);

    // Assert the response is a JsonResponse and its content
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)['error' => 'Failed to write .env file', 'details' => 'Permission denied']);
});

// Test getDatabaseEnvironment method - sqlite connection
test('getDatabaseEnvironment returns correct data for sqlite connection', function () {
    // Mock Request object and set its connection property
    $request = Mockery::mock(Request::class);
    $request->connection = 'sqlite';

    // Call the method
    $response = $this->controller->getDatabaseEnvironment($request);

    // Assert the response structure and content for sqlite
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)[
        'config' => (object)[
            'database_connection' => 'sqlite',
            'database_name' => database_path('database.sqlite'), // Uses the actual Laravel helper in test env
        ],
        'success' => true,
    ]);
});

// Test getDatabaseEnvironment method - pgsql connection
test('getDatabaseEnvironment returns correct data for pgsql connection', function () {
    // Mock Request object and set its connection property
    $request = Mockery::mock(Request::class);
    $request->connection = 'pgsql';

    // Call the method
    $response = $this->controller->getDatabaseEnvironment($request);

    // Assert the response structure and content for pgsql
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)[
        'config' => (object)[
            'database_connection' => 'pgsql',
            'database_host' => '127.0.0.1',
            'database_port' => 5432,
        ],
        'success' => true,
    ]);
});

// Test getDatabaseEnvironment method - mysql connection
test('getDatabaseEnvironment returns correct data for mysql connection', function () {
    // Mock Request object and set its connection property
    $request = Mockery::mock(Request::class);
    $request->connection = 'mysql';

    // Call the method
    $response = $this->controller->getDatabaseEnvironment($request);

    // Assert the response structure and content for mysql
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)[
        'config' => (object)[
            'database_connection' => 'mysql',
            'database_host' => '127.0.0.1',
            'database_port' => 3306,
        ],
        'success' => true,
    ]);
});

// Test getDatabaseEnvironment method - unknown connection type
test('getDatabaseEnvironment returns empty config for unknown connection type', function () {
    // Mock Request object with an unsupported connection type
    $request = Mockery::mock(Request::class);
    $request->connection = 'unsupported_db';

    // Call the method
    $response = $this->controller->getDatabaseEnvironment($request);

    // Assert that the 'config' array is empty for unknown connections
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)[
        'config' => (object)[],
        'success' => true,
    ]);
});

// Test getDatabaseEnvironment method - null connection type
test('getDatabaseEnvironment returns empty config when connection is null', function () {
    // Mock Request object with connection explicitly set to null
    $request = Mockery::mock(Request::class);
    $request->connection = null;

    // Call the method
    $response = $this->controller->getDatabaseEnvironment($request);

    // Assert that the 'config' array is empty when connection is null
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)[
        'config' => (object)[],
        'success' => true,
    ]);
});

// Test getDatabaseEnvironment method - empty string connection type
test('getDatabaseEnvironment returns empty config when connection is an empty string', function () {
    // Mock Request object with connection explicitly set to an empty string
    $request = Mockery::mock(Request::class);
    $request->connection = '';

    // Call the method
    $response = $this->controller->getDatabaseEnvironment($request);

    // Assert that the 'config' array is empty when connection is an empty string
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData())->toEqual((object)[
        'config' => (object)[],
        'success' => true,
    ]);
});
