<?php

use Mockery as m;
use Illuminate\Support\Facades\Storage;
use Crater\Providers\DropboxServiceProvider;
use Spatie\Dropbox\Client as DropboxClient;
use Spatie\FlysystemDropbox\DropboxAdapter;
use League\Flysystem\Filesystem;
use Illuminate\Container\Container;

// Ensure clean state after each test
beforeEach(function () {
    m::close();
});

// Test: register method does nothing
test('register method does nothing', function () {
    $app = m::mock(Container::class);
    $provider = new DropboxServiceProvider($app);
    $provider->register();
    $this->assertTrue(true);
});

// Test: boot method extends Storage with dropbox driver and correctly configures filesystem
test('boot method extends Storage with dropbox driver and correctly configures filesystem', function () {
    $configToken = 'mock-dropbox-api-token';
    $config = ['token' => $configToken];

    $mockFilesystemInstance = m::mock(Filesystem::class);

    // Create spies for DropboxClient, DropboxAdapter, Filesystem
    $created = [];
    $dropboxClientSpy = function ($token) use (&$created, $configToken) {
        expect($token)->toBe($configToken);
        $obj = new class {
        };
        $created['DropboxClient'] = $obj;
        return $obj;
    };
    $dropboxAdapterSpy = function ($client) use (&$created) {
        expect($client)->toBe($created['DropboxClient']);
        $obj = new class {
        };
        $created['DropboxAdapter'] = $obj;
        return $obj;
    };
    $filesystemSpy = function ($adapter) use (&$created, $mockFilesystemInstance) {
        expect($adapter)->toBe($created['DropboxAdapter']);
        $created['Filesystem'] = $mockFilesystemInstance;
        return $mockFilesystemInstance;
    };

    // Monkey-patch constructors via Pest's with added namespacing
    Pest\Laravel\swap(DropboxClient::class, (object)[
        '__construct' => $dropboxClientSpy,
    ]);
    Pest\Laravel\swap(DropboxAdapter::class, (object)[
        '__construct' => $dropboxAdapterSpy,
    ]);
    Pest\Laravel\swap(Filesystem::class, (object)[
        '__construct' => $filesystemSpy,
    ]);

    // Mock the Storage facade's extend
    Storage::shouldReceive('extend')
        ->once()
        ->with('dropbox', m::type('Closure'))
        ->andReturnUsing(function ($driverName, $closure) use ($config, $mockFilesystemInstance) {
            $app = m::mock(Container::class);
            $returnedFilesystem = $closure($app, $config);
            expect($returnedFilesystem)->toBe($mockFilesystemInstance);
        });

    $app = m::mock(Container::class);
    $provider = new DropboxServiceProvider($app);
    $provider->boot();
});

// Test: boot method throws ErrorException when dropbox token is missing in config
test('boot method throws ErrorException when dropbox token is missing in config', function () {
    $config = [];
    // Patch DropboxClient instantiation to immediately cause array access
    Pest\Laravel\swap(DropboxClient::class, (object)[
        '__construct' => function ($token) {
            // Will not reach here if config['token'] throws!
        }
    ]);
    Pest\Laravel\swap(DropboxAdapter::class, (object)[
        '__construct' => function ($client) {
        }
    ]);
    Pest\Laravel\swap(Filesystem::class, (object)[
        '__construct' => function ($adapter) {
        }
    ]);

    Storage::shouldReceive('extend')
        ->once()
        ->with('dropbox', m::type('Closure'))
        ->andReturnUsing(function ($driverName, $closure) use ($config) {
            $app = m::mock(Container::class);
            expect(fn() => $closure($app, $config))
                ->toThrow(ErrorException::class, 'Undefined array key "token"');
        });

    $app = m::mock(Container::class);
    $provider = new DropboxServiceProvider($app);
    $provider->boot();
});

// Test: boot method handles invalid dropbox token values (null or empty string)
test('boot method handles invalid dropbox token values (null or empty string)', function ($config, $expectedErrorMessage) {
    m::close();

    Pest\Laravel\swap(DropboxClient::class, (object)[
        '__construct' => function ($token) use ($expectedErrorMessage) {
            throw new InvalidArgumentException($expectedErrorMessage);
        }
    ]);
    Pest\Laravel\swap(DropboxAdapter::class, (object)[
        '__construct' => function ($client) {
        }
    ]);
    Pest\Laravel\swap(Filesystem::class, (object)[
        '__construct' => function ($adapter) {
        }
    ]);

    Storage::shouldReceive('extend')
        ->once()
        ->with('dropbox', m::type('Closure'))
        ->andReturnUsing(function ($driverName, $closure) use ($config, $expectedErrorMessage) {
            $app = m::mock(Container::class);
            expect(fn() => $closure($app, $config))
                ->toThrow(InvalidArgumentException::class, $expectedErrorMessage);
        });

    $app = m::mock(Container::class);
    $provider = new DropboxServiceProvider($app);
    $provider->boot();

})->with([
    'null token' => [['token' => null], 'Dropbox token cannot be null'],
    'empty string token' => [['token' => ''], 'Dropbox token cannot be an empty string'],
]);

afterEach(function () {
    m::close();
});