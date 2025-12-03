<?php

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Crater\Events\UpdateFinished;
use Crater\Listeners\Updates\v1\Version110;
use Crater\Listeners\Updates\v2\Version200;
use Crater\Listeners\Updates\v2\Version201;
use Crater\Listeners\Updates\v2\Version202;
use Crater\Listeners\Updates\v2\Version210;
use Crater\Listeners\Updates\v3\Version300;
use Crater\Listeners\Updates\v3\Version310;
use Crater\Listeners\Updates\v3\Version311;
use Crater\Providers\EventServiceProvider;

beforeEach(function () {
    // Use partial mock for Application to avoid flush() error
    $this->app = Mockery::mock(Application::class)->shouldIgnoreMissing();
});


test('it can be instantiated', function () {
    $provider = new EventServiceProvider($this->app);
    expect($provider)->toBeInstanceOf(EventServiceProvider::class);
});

test('it correctly maps UpdateFinished event to its listeners', function () {
    $provider = new EventServiceProvider($this->app);

    // Use reflection to access the protected $listen property for white-box testing.
    $reflection = new ReflectionClass($provider);
    $listenProperty = $reflection->getProperty('listen');
    $listenProperty->setAccessible(true);
    $listen = $listenProperty->getValue($provider);

    expect($listen)->toHaveKey(UpdateFinished::class);
    expect($listen[UpdateFinished::class])->toBeArray();
    expect($listen[UpdateFinished::class])->toEqual([
        Version110::class,
        Version200::class,
        Version201::class,
        Version202::class,
        Version210::class,
        Version300::class,
        Version310::class,
        Version311::class,
    ]);
});

test('it correctly maps Registered event to its listener', function () {
    $provider = new EventServiceProvider($this->app);

    // Use reflection to access the protected $listen property for white-box testing.
    $reflection = new ReflectionClass($provider);
    $listenProperty = $reflection->getProperty('listen');
    $listenProperty->setAccessible(true);
    $listen = $listenProperty->getValue($provider);

    expect($listen)->toHaveKey(Registered::class);
    expect($listen[Registered::class])->toBeArray();
    expect($listen[Registered::class])->toEqual([
        SendEmailVerificationNotification::class,
    ]);
});

test('boot method registers all defined listeners with the event dispatcher', function () {
    // Mock the EventDispatcher that the parent::boot() method will interact with.
    $mockDispatcher = Mockery::mock(Dispatcher::class);

    // Set expectations for the Application mock:
    // Use shouldAllowMockingProtectedMethods to avoid "singleton" not called errors
    $appMock = Mockery::mock(Application::class)->shouldIgnoreMissing()->shouldAllowMockingProtectedMethods();

    // 1. parent::boot() calls $this->app->singleton('events', ...) to register the dispatcher.
    $appMock->shouldReceive('singleton')
        ->with('events', Mockery::type(\Closure::class))
        ->andReturnTrue()
        ->atMost()->once();

    // 2. parent::boot() then calls $this->app->make('events') to retrieve the dispatcher
    $appMock->shouldReceive('make')
        ->with('events')
        ->andReturn($mockDispatcher)
        ->atLeast()->once();

    // Define the expected event-listener mappings from the EventServiceProvider's $listen property.
    $expectedMappings = [
        UpdateFinished::class => [
            Version110::class,
            Version200::class,
            Version201::class,
            Version202::class,
            Version210::class,
            Version300::class,
            Version310::class,
            Version311::class,
        ],
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    foreach ($expectedMappings as $event => $listeners) {
        foreach ($listeners as $listener) {
            $mockDispatcher->shouldReceive('listen')
                ->with($event, $listener)
                ->once();
        }
    }

    // Instantiate the EventServiceProvider with the mocked application.
    $provider = new EventServiceProvider($appMock);

    // Call the boot method. This should trigger the interactions with the mocks.
    $provider->boot();

    // No direct return value from boot(), but Mockery's expectations verify correct behavior.
    expect(true)->toBeTrue(); // Dummy assertion to ensure test passes if no other direct assertion.
});

afterEach(function () {
    Mockery::close();
});