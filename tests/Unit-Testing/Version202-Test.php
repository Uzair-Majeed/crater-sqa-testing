<?php

use Crater\Events\UpdateFinished;
use Crater\Listeners\Updates\v2\Version202;
use Crater\Models\Setting;

beforeEach(function () {
    // Mock the static facade for Setting
    Mockery::mock('alias:' . Setting::class);
});


test('it can be instantiated', function () {
    $listener = new Version202();
    expect($listener)->toBeInstanceOf(Version202::class);
});

test('handle does not update version if listener is already fired', function () {
    // Expect Setting::setSetting NOT to be called
    Setting::shouldNotReceive('setSetting');

    // Create a mock for the UpdateFinished event
    $event = Mockery::mock(UpdateFinished::class);

    // Create a partial mock of Version202 to control isListenerFired
    // This allows us to stub the parent method without affecting other logic.
    $listener = Mockery::mock(Version202::class . '[isListenerFired]');
    $listener->shouldReceive('isListenerFired')
             ->once()
             ->with($event)
             ->andReturn(true);

    // Call the handle method
    $listener->handle($event);
});

test('handle updates version if listener is not already fired', function () {
    // Expect Setting::setSetting to be called once with specific arguments
    Setting::shouldReceive('setSetting')
           ->once()
           ->with('version', Version202::VERSION);

    // Create a mock for the UpdateFinished event
    $event = Mockery::mock(UpdateFinished::class);

    // Create a partial mock of Version202 to control isListenerFired
    $listener = Mockery::mock(Version202::class . '[isListenerFired]');
    $listener->shouldReceive('isListenerFired')
             ->once()
             ->with($event)
             ->andReturn(false);

    // Call the handle method
    $listener->handle($event);
});




afterEach(function () {
    Mockery::close();
});
