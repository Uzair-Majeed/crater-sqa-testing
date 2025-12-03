<?php
 // Test case: handle() should return early if isListenerFired() is true
    test('handle returns early if listener is already fired', function () {
        // Create a mock for the UpdateFinished event
        $event = Mockery::mock(\Crater\Events\UpdateFinished::class);

        // Create a partial mock for Version310 to control its inherited protected method 'isListenerFired'
        $listener = Mockery::mock(\Crater\Listeners\Updates\v3\Version310::class)->makePartial();
        $listener->shouldAllowMockingProtectedMethods();

        // Configure isListenerFired to return true, indicating the listener has already run
        $listener->shouldReceive('isListenerFired')
                 ->with($event)
                 ->andReturn(true)
                 ->once();

        // Ensure that none of the update logic methods are called
        Mockery::mock('alias:' . \Crater\Models\Currency::class)->shouldNotReceive('firstOrCreate');
        Mockery::mock('alias:' . \Artisan::class)->shouldNotReceive('call');
        Mockery::mock('alias:' . \Crater\Models\Setting::class)->shouldNotReceive('setSetting');

        // Call the handle method
        $listener->handle($event);
    });

    // Test case: handle() should proceed with updates if isListenerFired() is false
    test('handle proceeds with updates if listener is not fired', function () {
        // Create a mock for the UpdateFinished event
        $event = Mockery::mock(\Crater\Events\UpdateFinished::class);

        // Create a partial mock for Version310 to control its inherited protected method 'isListenerFired'
        $listener = Mockery::mock(\Crater\Listeners\Updates\v3\Version310::class)->makePartial();
        $listener->shouldAllowMockingProtectedMethods();

        // Configure isListenerFired to return false, indicating the listener has not run yet
        $listener->shouldReceive('isListenerFired')
                 ->with($event)
                 ->andReturn(false)
                 ->once();

        // Mock Currency model and its static firstOrCreate method
        $currencyMock = Mockery::mock('alias:' . \Crater\Models\Currency::class);
        $currencyMock->shouldReceive('firstOrCreate')
                     ->once()
                     ->with(
                         [
                             'name' => 'Kyrgyzstani som',
                             'code' => 'KGS',
                         ],
                         [
                             'name' => 'Kyrgyzstani som',
                             'code' => 'KGS',
                             'symbol' => 'С̲ ',
                             'precision' => '2',
                             'thousand_separator' => '.',
                             'decimal_separator' => ',',
                         ]
                     )
                     ->andReturn(Mockery::mock(\Crater\Models\Currency::class)); // Return a mock Currency instance

        // Mock Artisan facade and its static call method
        $artisanMock = Mockery::mock('alias:' . \Artisan::class);
        $artisanMock->shouldReceive('call')
                    ->once()
                    ->with('migrate', ['--force' => true]);

        // Mock Setting model and its static setSetting method
        $settingMock = Mockery::mock('alias:' . \Crater\Models\Setting::class);
        $settingMock->shouldReceive('setSetting')
                    ->once()
                    ->with('version', \Crater\Listeners\Updates\v3\Version310::VERSION);

        // Call the handle method
        $listener->handle($event);
    });




afterEach(function () {
    Mockery::close();
});
