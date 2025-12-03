<?php

use Illuminate\Support\Facades\Broadcast;
use Crater\Providers\BroadcastServiceProvider;
use Mockery as m;

beforeEach(function () {
    // Remove any pre-existing Mockery alias for the facade (avoid class already exists error)
    if (class_exists('Mockery_Proxy_Illuminate_Support_Facades_Broadcast', false)) {
        unset($GLOBALS['Mockery_Proxy_Illuminate_Support_Facades_Broadcast']);
    }
    // Use shouldReceive directly on the facade in test instead of alias mocking here.
    // Facades in Laravel cannot be re-mocked with 'alias:' once loaded.
});

test('the service provider registers broadcast routes and includes channels file', function () {
    // Arrange: Define expectations for the Broadcast facade.

    // Expect Broadcast::routes() to be called once with no arguments.
    Broadcast::shouldReceive('routes')
        ->once()
        ->withNoArgs();

    // Expect Broadcast::routes(["middleware" => 'api.auth']) to be called once
    Broadcast::shouldReceive('routes')
        ->once()
        ->with(['middleware' => 'api.auth']);

    // Mock the Application contract for the service provider
    $app = m::mock('Illuminate\Contracts\Foundation\Application');

    // To avoid the require of channels.php file crashing, ensure the file exists
    // Create the file in a temp dir and swap base_path() for test duration
    $routesDir = sys_get_temp_dir() . '/routes_for_testing_' . uniqid();
    if (!file_exists($routesDir)) {
        mkdir($routesDir, 0777, true);
    }
    $channelsFile = $routesDir . '/channels.php';
    file_put_contents($channelsFile, "<?php // test channel file");

    // Swap base_path() function for this test to point to temp dir
    // This ensures 'require base_path('routes/channels.php')' will succeed.
    // We can do this with namespaced function override, if in global NS:
    // "use function base_path;" is not required for Laravel helpers.
    // We'll patch the global base_path function with Pest helper:
    pest()->swap('base_path', fn($path = '') => $routesDir . ($path ? '/' . ltrim($path, '/') : ''));

    // Act: Create an instance of the service provider and call its boot method.
    $provider = new BroadcastServiceProvider($app);
    $provider->boot();

    // Clean up: Remove the temporary channels.php file and dir
    @unlink($channelsFile);
    @rmdir($routesDir);

    // The assertion of Broadcast::shouldReceive('routes')->once() is handled by Mockery.
});

afterEach(function () {
    m::close();
    // Restore original base_path after test, if swapped
    pest()->restore('base_path');
});