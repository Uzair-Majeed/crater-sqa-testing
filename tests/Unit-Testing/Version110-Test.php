<?php

use Crater\Events\UpdateFinished;
use Crater\Listeners\Updates\v1\Version110;
use Crater\Models\Currency;
use Crater\Models\Setting;

// Ensure Mockery expectations are closed after each test to prevent interference.
beforeEach(function () {
    Mockery::close();
});
test('it can be instantiated', function () {
        $listener = new Version110();
        expect($listener)->toBeInstanceOf(Version110::class);
    });
test('it does not add currencies or update version if the listener has already fired', function () {
            $event = Mockery::mock(UpdateFinished::class);

            // Create a partial mock of Version110 to control the protected `isListenerFired` method.
            $listener = Mockery::mock(Version110::class)->makePartial();
            $listener->shouldAllowMockingProtectedMethods();
            $listener->shouldReceive('isListenerFired')
                     ->with($event)
                     ->once()
                     ->andReturn(true);

            // Mock static methods of Setting and Currency to ensure they are NOT called.
            Mockery::mock('alias:' . Setting::class)
                    ->shouldNotReceive('setSetting');

            Mockery::mock('alias:' . Currency::class)
                    ->shouldNotReceive('updateOrCreate');

            Mockery::mock('alias:' . Currency::class)
                    ->shouldNotReceive('create');

            $listener->handle($event);

            // Mockery::close() in beforeEach will verify no unexpected calls.
            // No additional explicit assertions are required here.
        });

        test('it adds currencies and updates the app version if the listener has not fired', function () {
            $event = Mockery::mock(UpdateFinished::class);

            // Create a partial mock of Version110 to control the protected `isListenerFired` method.
            $listener = Mockery::mock(Version110::class)->makePartial();
            $listener->shouldAllowMockingProtectedMethods();
            $listener->shouldReceive('isListenerFired')
                     ->with($event)
                     ->once()
                     ->andReturn(false);

            // Mock static method Setting::setSetting to expect it to be called.
            Mockery::mock('alias:' . Setting::class)
                    ->shouldReceive('setSetting')
                    ->with('version', Version110::VERSION)
                    ->once()
                    ->andReturn(null); // Assuming it returns void or null.

            // Mock static methods Currency::updateOrCreate and Currency::create.
            $currencyMock = Mockery::mock('alias:' . Currency::class);

            $currenciesToUpdate = [
                '13' => ['symbol' => 'S$'],
                '16' => ['symbol' => '₫'],
                '17' => ['symbol' => 'Fr.'],
                '21' => ['symbol' => '฿'],
                '22' => ['symbol' => '₦'],
                '26' => ['symbol' => 'HK$'],
                '35' => ['symbol' => 'NAƒ'],
                '38' => ['symbol' => 'GH₵'],
                '39' => ['symbol' => 'Лв.'],
                '42' => ['symbol' => 'RON'],
                '44' => ['symbol' => 'SِAR'],
                '46' => ['symbol' => 'Rf'],
                '47' => ['symbol' => '₡'],
                '54' => ['symbol' => '‎د.ت'],
                '55' => ['symbol' => '₽'],
                '57' => ['symbol' => 'ر.ع.'],
                '58' => ['symbol' => '₴'],
            ];

            foreach ($currenciesToUpdate as $id => $data) {
                $currencyMock->shouldReceive('updateOrCreate')
                             ->with(['id' => $id], $data)
                             ->once()
                             ->andReturn(Mockery::mock(Currency::class)); // Return a mock instance.
            }

            $currencyMock->shouldReceive('create')
                         ->with([
                             'name' => 'Kuwaiti Dinar',
                             'code' => 'KWD',
                             'symbol' => 'KWD ',
                             'precision' => '3',
                             'thousand_separator' => ',',
                             'decimal_separator' => '.',
                         ])
                         ->once()
                         ->andReturn(Mockery::mock(Currency::class)); // Return a mock instance.

            $listener->handle($event);

            // Mockery::close() in beforeEach will verify all expectations.
        });
  test('it correctly calls updateOrCreate for predefined currencies and creates the Kuwaiti Dinar currency', function () {
            $listener = new Version110();

            // Mock static methods Currency::updateOrCreate and Currency::create.
            $currencyMock = Mockery::mock('alias:' . Currency::class);

            $currenciesToUpdate = [
                '13' => ['symbol' => 'S$'],
                '16' => ['symbol' => '₫'],
                '17' => ['symbol' => 'Fr.'],
                '21' => ['symbol' => '฿'],
                '22' => ['symbol' => '₦'],
                '26' => ['symbol' => 'HK$'],
                '35' => ['symbol' => 'NAƒ'],
                '38' => ['symbol' => 'GH₵'],
                '39' => ['symbol' => 'Лв.'],
                '42' => ['symbol' => 'RON'],
                '44' => ['symbol' => 'SِAR'],
                '46' => ['symbol' => 'Rf'],
                '47' => ['symbol' => '₡'],
                '54' => ['symbol' => '‎د.ت'],
                '55' => ['symbol' => '₽'],
                '57' => ['symbol' => 'ر.ع.'],
                '58' => ['symbol' => '₴'],
            ];

            foreach ($currenciesToUpdate as $id => $data) {
                $currencyMock->shouldReceive('updateOrCreate')
                             ->with(['id' => $id], $data)
                             ->once()
                             ->andReturn(Mockery::mock(Currency::class));
            }

            $currencyMock->shouldReceive('create')
                         ->with([
                             'name' => 'Kuwaiti Dinar',
                             'code' => 'KWD',
                             'symbol' => 'KWD ',
                             'precision' => '3',
                             'thousand_separator' => ',',
                             'decimal_separator' => '.',
                         ])
                         ->once()
                         ->andReturn(Mockery::mock(Currency::class));

            // Use reflection to call the private method directly.
            $reflection = new ReflectionMethod(Version110::class, 'addCurrencies');
            $reflection->setAccessible(true);
            $reflection->invoke($listener);

            // Mockery::close() in beforeEach will verify all expectations.
        });




afterEach(function () {
    Mockery::close();
});
