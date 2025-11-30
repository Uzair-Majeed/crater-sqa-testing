<?php

use Pest\TestSuite;
use Mockery as m;
use Illuminate\Support\Facades\Storage;
use Crater\Providers\DropboxServiceProvider;
use Spatie\Dropbox\Client as DropboxClient;
use Spatie\FlysystemDropbox\DropboxAdapter;
use League\Flysystem\Filesystem;
use Illuminate\Container\Container;

// Clear Mockery expectations after each test to ensure a clean slate
beforeEach(function () {
    m::close();
});

// Test that the `register` method, which is empty, does not cause any errors.
test('register method does nothing', function () {
    // Create a mock application container, though it's not strictly used by register.
    $app = m::mock(Container::class);
    $provider = new DropboxServiceProvider($app);

    // Call the register method.
    $provider->register();

    // The method is empty, so we just assert that the test completed without errors.
    $this->assertTrue(true);
});

// Test the `boot` method's primary functionality: extending Storage with the Dropbox driver.
test('boot method extends Storage with dropbox driver and correctly configures filesystem', function () {
    // Define a dummy API token for the Dropbox client.
    $configToken = 'mock-dropbox-api-token';
    $config = ['token' => $configToken];

    // Mock the final Filesystem instance that the closure is expected to return.
    $mockFilesystemInstance = m::mock(Filesystem::class);

    // Mock the Spatie\Dropbox\Client class constructor.
    // We use `overload:` to intercept the `new DropboxClient(...)` call inside the closure.
    // We expect it to be instantiated once with the correct token.
    $mockDropboxClient = m::mock('overload:' . DropboxClient::class);
    $mockDropboxClient->shouldReceive('__construct')
                      ->once()
                      ->with($configToken);

    // Mock the Spatie\FlysystemDropbox\DropboxAdapter class constructor.
    // We expect it to be instantiated once with an instance of DropboxClient.
    $mockDropboxAdapter = m::mock('overload:' . DropboxAdapter::class);
    $mockDropboxAdapter->shouldReceive('__construct')
                       ->once()
                       ->with(m::type(DropboxClient::class)); // Ensure it receives a DropboxClient instance

    // Mock the League\Flysystem\Filesystem class constructor.
    // We expect it to be instantiated once with an instance of DropboxAdapter.
    // Crucially, we make its constructor return our `$mockFilesystemInstance`.
    $mockFilesystem = m::mock('overload:' . Filesystem::class);
    $mockFilesystem->shouldReceive('__construct')
                   ->once()
                   ->with(m::type(DropboxAdapter::class)) // Ensure it receives a DropboxAdapter instance
                   ->andReturn($mockFilesystemInstance);

    // Mock the Storage facade's `extend` method.
    // We expect it to be called once with 'dropbox' and a Closure.
    Storage::shouldReceive('extend')
           ->once()
           ->with('dropbox', m::type('Closure'))
           ->andReturnUsing(function ($driverName, $closure) use ($config, $mockFilesystemInstance) {
               // Simulate the application container (not directly used by the closure, but part of the signature).
               $app = m::mock(Container::class);

               // Execute the closure, passing the dummy $app and the $config.
               $returnedFilesystem = $closure($app, $config);

               // Assert that the closure returned our expected mock Filesystem instance.
               expect($returnedFilesystem)->toBe($mockFilesystemInstance);
           });

    // Instantiate and boot the service provider with a mock application instance.
    $app = m::mock(Container::class);
    $provider = new DropboxServiceProvider($app);
    $provider->boot();

    // Mockery automatically verifies all `shouldReceive` expectations when the test finishes.
});

// Test an edge case where the 'token' key is missing in the configuration array.
test('boot method throws ErrorException when dropbox token is missing in config', function () {
    // Prepare configuration without the 'token' key.
    $config = []; // Missing 'token'

    // We still need to overload the dependency classes to prevent them from being
    // instantiated as real objects before the error occurs in the closure.
    m::mock('overload:' . DropboxClient::class);
    m::mock('overload:' . DropboxAdapter::class);
    m::mock('overload:' . Filesystem::class);

    // Mock the Storage facade's `extend` method.
    Storage::shouldReceive('extend')
           ->once()
           ->with('dropbox', m::type('Closure'))
           ->andReturnUsing(function ($driverName, $closure) use ($config) {
               $app = m::mock(Container::class);

               // We expect an ErrorException (specifically "Undefined array key")
               // to be thrown when the closure attempts to access `$config['token']`.
               $this->expectException(ErrorException::class);
               $this->expectExceptionMessage('Undefined array key "token"');

               $closure($app, $config);
           });

    // Instantiate and boot the service provider.
    $app = m::mock(Container::class);
    $provider = new DropboxServiceProvider($app);
    $provider->boot();
});

// Test edge cases where the 'token' key exists but its value is null or an empty string.
// This assumes the `DropboxClient` constructor would throw an `InvalidArgumentException`
// for such invalid token values.
test('boot method handles invalid dropbox token values (null or empty string)', function ($config, $expectedErrorMessage) {
    // Reset mocks for each dataset iteration provided by `->with()`.
    m::close();

    // Mock the DropboxClient constructor to throw an InvalidArgumentException.
    $mockDropboxClient = m::mock('overload:' . DropboxClient::class);
    $mockDropboxClient->shouldReceive('__construct')
                      ->once()
                      ->with($config['token']) // This is called with the invalid token
                      ->andThrow(new InvalidArgumentException($expectedErrorMessage));

    // Overload other classes to prevent their real instantiation.
    m::mock('overload:' . DropboxAdapter::class);
    m::mock('overload:' . Filesystem::class);

    // Mock the Storage facade's `extend` method.
    Storage::shouldReceive('extend')
           ->once()
           ->with('dropbox', m::type('Closure'))
           ->andReturnUsing(function ($driverName, $closure) use ($config, $expectedErrorMessage) {
               $app = m::mock(Container::class);

               // Expect the specific InvalidArgumentException thrown by our mocked DropboxClient.
               $this->expectException(InvalidArgumentException::class);
               $this->expectExceptionMessage($expectedErrorMessage);

               $closure($app, $config);
           });

    // Instantiate and boot the service provider.
    $app = m::mock(Container::class);
    $provider = new DropboxServiceProvider($app);
    $provider->boot();

})->with([
    'null token' => [['token' => null], 'Dropbox token cannot be null'],
    'empty string token' => [['token' => ''], 'Dropbox token cannot be an empty string'],
]);
