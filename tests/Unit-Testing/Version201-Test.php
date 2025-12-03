<?php

use Crater\Events\UpdateFinished;
use Crater\Listeners\Updates\v2\Version201;
use Crater\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Foundation\Application;

beforeEach(function () {
    // Reset mocks before each test
    Mockery::close();
});

test('constructor exists and does not throw an error', function () {
    $listener = new Version201();
    expect($listener)->toBeInstanceOf(Version201::class);
});

test('handle method does nothing if listener is already fired', function () {
    // Create a partial mock for Version201 to mock its protected method isListenerFired
    $listener = Mockery::mock(Version201::class)->makePartial();
    $listener->shouldAllowMockingProtectedMethods()->shouldReceive('isListenerFired')->once()->andReturn(true);

    // Ensure private methods are NOT called
    $listener->shouldNotReceive('removeLanguageFiles');
    $listener->shouldNotReceive('changeMigrations');

    // Mock Setting::setSetting statically
    Mockery::mock('alias:'.Setting::class)
        ->shouldNotReceive('setSetting');

    $event = new UpdateFinished(Version201::VERSION);
    $listener->handle($event);

    // Mockery expectations will verify calls.
});

test('handle method performs updates if listener is not fired', function () {
    // Create a partial mock for Version201 to mock its protected method isListenerFired
    $listener = Mockery::mock(Version201::class)->makePartial();
    $listener->shouldAllowMockingProtectedMethods()->shouldReceive('isListenerFired')->once()->andReturn(false);

    // Ensure private methods ARE called
    $listener->shouldReceive('removeLanguageFiles')->once();
    $listener->shouldReceive('changeMigrations')->once();

    // Mock Setting::setSetting statically
    Mockery::mock('alias:'.Setting::class)
        ->shouldReceive('setSetting')
        ->once()
        ->with('version', Version201::VERSION);

    $event = new UpdateFinished(Version201::VERSION);
    $listener->handle($event);

    // Mockery expectations will verify calls.
});

test('removeLanguageFiles deletes existing language files', function () {
    // Create a temporary directory for resource_path
    $tempDir = sys_get_temp_dir() . '/crater_test_resources_' . uniqid();
    File::makeDirectory($tempDir . '/assets/js/plugins', 0777, true); // Ensure plugin dir exists

    // Create dummy language files
    File::put($tempDir . '/assets/js/plugins/en.js', 'content');
    File::put($tempDir . '/assets/js/plugins/es.js', 'content');
    File::put($tempDir . '/assets/js/plugins/fr.js', 'content');

    // Mock the `resource_path` helper function via the Application container
    $appMock = Mockery::mock(Application::class);
    $appMock->shouldReceive('resourcePath')
            ->andReturnUsing(function ($path = '') use ($tempDir) {
                return $tempDir . ($path ? '/' . $path : '');
            });
    app()->instance(Application::class, $appMock);
    
    // Test the private method using reflection
    $listener = new Version201();
    $method = new ReflectionMethod(Version201::class, 'removeLanguageFiles');
    $method->setAccessible(true);
    $method->invoke($listener);

    // Assert files are deleted
    expect(File::exists($tempDir . '/assets/js/plugins/en.js'))->toBeFalse();
    expect(File::exists($tempDir . '/assets/js/plugins/es.js'))->toBeFalse();
    expect(File::exists($tempDir . '/assets/js/plugins/fr.js'))->toBeFalse();

    // Clean up temporary directory
    File::deleteDirectory($tempDir);
});

test('removeLanguageFiles only deletes existing language files', function () {
    // Create a temporary directory for resource_path
    $tempDir = sys_get_temp_dir() . '/crater_test_resources_' . uniqid();
    File::makeDirectory($tempDir . '/assets/js/plugins', 0777, true); // Ensure plugin dir exists

    // Create only one dummy language file
    File::put($tempDir . '/assets/js/plugins/en.js', 'content');

    // Mock the `resource_path` helper function via the Application container
    $appMock = Mockery::mock(Application::class);
    $appMock->shouldReceive('resourcePath')
            ->andReturnUsing(function ($path = '') use ($tempDir) {
                return $tempDir . ($path ? '/' . $path : '');
            });
    app()->instance(Application::class, $appMock);

    // Test the private method using reflection
    $listener = new Version201();
    $method = new ReflectionMethod(Version201::class, 'removeLanguageFiles');
    $method->setAccessible(true);
    $method->invoke($listener);

    // Assert only the existing file is deleted, others remain non-existent
    expect(File::exists($tempDir . '/assets/js/plugins/en.js'))->toBeFalse();
    expect(File::exists($tempDir . '/assets/js/plugins/es.js'))->toBeFalse(); // Still non-existent
    expect(File::exists($tempDir . '/assets/js/plugins/fr.js'))->toBeFalse(); // Still non-existent

    // Clean up temporary directory
    File::deleteDirectory($tempDir);
});

test('removeLanguageFiles does nothing if no language files exist', function () {
    // Create a temporary directory for resource_path, ensure no files exist
    $tempDir = sys_get_temp_dir() . '/crater_test_resources_' . uniqid();
    File::makeDirectory($tempDir . '/assets/js/plugins', 0777, true); // Ensure plugin dir exists but is empty

    // Mock the `resource_path` helper function via the Application container
    $appMock = Mockery::mock(Application::class);
    $appMock->shouldReceive('resourcePath')
            ->andReturnUsing(function ($path = '') use ($tempDir) {
                return $tempDir . ($path ? '/' . $path : '');
            });
    app()->instance(Application::class, $appMock);

    // Test the private method using reflection
    $listener = new Version201();
    $method = new ReflectionMethod(Version201::class, 'removeLanguageFiles');
    $method->setAccessible(true);
    $method->invoke($listener);

    // Assert files still do not exist
    expect(File::exists($tempDir . '/assets/js/plugins/en.js'))->toBeFalse();
    expect(File::exists($tempDir . '/assets/js/plugins/es.js'))->toBeFalse();
    expect(File::exists($tempDir . '/assets/js/plugins/fr.js'))->toBeFalse();

    // Clean up temporary directory
    File::deleteDirectory($tempDir);
});

test('changeMigrations updates schema correctly', function () {
    // Mock the Schema facade
    Schema::shouldReceive('table')
        ->with('invoices', Mockery::type(Closure::class))
        ->once()
        ->andReturnUsing(function ($table, $callback) {
            $blueprint = Mockery::mock(Blueprint::class);
            $decimalMock = Mockery::mock();
            $decimalMock->shouldReceive('nullable')->once()->andReturnSelf();
            $decimalMock->shouldReceive('change')->once()->andReturnSelf();
            $blueprint->shouldReceive('decimal')->once()->with('discount', 15, 2)->andReturn($decimalMock);
            $callback($blueprint);
        });

    Schema::shouldReceive('table')
        ->with('estimates', Mockery::type(Closure::class))
        ->once()
        ->andReturnUsing(function ($table, $callback) {
            $blueprint = Mockery::mock(Blueprint::class);
            $decimalMock = Mockery::mock();
            $decimalMock->shouldReceive('nullable')->once()->andReturnSelf();
            $decimalMock->shouldReceive('change')->once()->andReturnSelf();
            $blueprint->shouldReceive('decimal')->once()->with('discount', 15, 2)->andReturn($decimalMock);
            $callback($blueprint);
        });

    Schema::shouldReceive('table')
        ->with('invoice_items', Mockery::type(Closure::class))
        ->once()
        ->andReturnUsing(function ($table, $callback) {
            $blueprint = Mockery::mock(Blueprint::class);
            $decimalQuantityMock = Mockery::mock();
            $decimalQuantityMock->shouldReceive('change')->once()->andReturnSelf();
            $blueprint->shouldReceive('decimal')->once()->with('quantity', 15, 2)->andReturn($decimalQuantityMock);

            $decimalDiscountMock = Mockery::mock();
            $decimalDiscountMock->shouldReceive('nullable')->once()->andReturnSelf();
            $decimalDiscountMock->shouldReceive('change')->once()->andReturnSelf();
            $blueprint->shouldReceive('decimal')->once()->with('discount', 15, 2)->andReturn($decimalDiscountMock);
            $callback($blueprint);
        });

    Schema::shouldReceive('table')
        ->with('estimate_items', Mockery::type(Closure::class))
        ->once()
        ->andReturnUsing(function ($table, $callback) {
            $blueprint = Mockery::mock(Blueprint::class);
            $decimalQuantityMock = Mockery::mock();
            $decimalQuantityMock->shouldReceive('change')->once()->andReturnSelf();
            $blueprint->shouldReceive('decimal')->once()->with('quantity', 15, 2)->andReturn($decimalQuantityMock);

            $decimalDiscountMock = Mockery::mock();
            $decimalDiscountMock->shouldReceive('nullable')->once()->andReturnSelf();
            $decimalDiscountMock->shouldReceive('change')->once()->andReturnSelf();
            $blueprint->shouldReceive('decimal')->once()->with('discount', 15, 2)->andReturn($decimalDiscountMock);

            $unsignedBigIntegerMock = Mockery::mock();
            $unsignedBigIntegerMock->shouldReceive('nullable')->once()->andReturnSelf();
            $unsignedBigIntegerMock->shouldReceive('change')->once()->andReturnSelf();
            $blueprint->shouldReceive('unsignedBigInteger')->once()->with('discount_val')->andReturn($unsignedBigIntegerMock);
            $callback($blueprint);
        });

    // Test the private method using reflection
    $listener = new Version201();
    $method = new ReflectionMethod(Version201::class, 'changeMigrations');
    $method->setAccessible(true);
    $method->invoke($listener);

    // Mockery expectations will verify calls.
});

// A cleanup after all tests in the file or group
afterAll(function () {
    Mockery::close();
    // Reset the application instance to avoid affecting other tests if the app instance was mocked
    if (app()->bound(Application::class) && app()->instance(Application::class) instanceof Mockery\MockInterface) {
        app()->forgetInstance(Application::class);
    }
});




afterEach(function () {
    Mockery::close();
});
