<?php

use Crater\Listeners\Updates\v3\Version311;
use Crater\Events\UpdateFinished;
uses(\Mockery::class);

beforeEach(function () {
    // Clear any previous mocks to ensure isolation between tests
    Mockery::close();

    // Re-alias facades/static methods for each test to ensure fresh mocks.
    // This is crucial when testing with facades/static calls as their state is global.
    Mockery::mock('alias:Artisan');
    Mockery::mock('alias:Crater\Models\Setting');
});

test('handle does nothing if the listener has already fired for the event', function () {
    $event = new UpdateFinished('3.1.0');

    // Create a partial mock of the Version311 listener to control its parent's protected method
    $listener = Mockery::mock(Version311::class)->makePartial();
    $listener->shouldAllowMockingProtectedMethods(); // Enable mocking protected methods

    // Expect 'isListenerFired' to be called once with the event and return true,
    // simulating that the update has already been handled.
    $listener->shouldReceive('isListenerFired')
             ->once()
             ->with($event)
             ->andReturn(true);

    // Assert that 'Artisan::call' is NOT called when the listener has already fired
    Artisan::shouldNotReceive('call');

    // Assert that 'Setting::setSetting' is NOT called when the listener has already fired
    Setting::shouldNotReceive('setSetting');

    // Call the handle method under test
    $listener->handle($event);
});

test('handle executes migrations and updates version if the listener has not fired', function () {
    $event = new UpdateFinished('3.1.0');

    // Create a partial mock of the Version311 listener
    $listener = Mockery::mock(Version311::class)->makePartial();
    $listener->shouldAllowMockingProtectedMethods(); // Enable mocking protected methods

    // Expect 'isListenerFired' to be called once with the event and return false,
    // simulating that the update needs to be handled.
    $listener->shouldReceive('isListenerFired')
             ->once()
             ->with($event)
             ->andReturn(false);

    // Expect 'Artisan::call' to be called once with the 'migrate --force' command
    Artisan::shouldReceive('call')
           ->once()
           ->with('migrate', ['--force' => true]);

    // Expect 'Setting::setSetting' to be called once to update the application version
    $expectedVersion = Version311::VERSION;
    Setting::shouldReceive('setSetting')
           ->once()
           ->with('version', $expectedVersion);

    // Call the handle method under test
    $listener->handle($event);
});
