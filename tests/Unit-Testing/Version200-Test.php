<?php

use Crater\Events\UpdateFinished;
use Crater\Listeners\Updates\v2\Version200;
use Crater\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;

// Mock global helper functions if they are called inside the class.
// For unit tests, we control their return values.
if (!function_exists('database_path')) {
    function database_path(string $path = '')
    {
        return '/mock/database/path/' . ltrim($path, '/');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = '')
    {
        return '/mock/app/path/' . ltrim($path, '/');
    }
}

beforeEach(function () {
    // Ensure Mockery is clean before each test to prevent cross-test contamination.
    Mockery::close();
});

test('it can be instantiated', function () {
    $listener = new Version200();
    expect($listener)->toBeInstanceOf(Version200::class);
});

test('handle method does nothing if listener is already fired', function () {
    $event = Mockery::mock(UpdateFinished::class);

    // Partial mock of Version200 to control the inherited `isListenerFired` method
    // and ensure no other update methods are called.
    $listener = Mockery::mock(Version200::class . '[isListenerFired, replaceStateAndCityName, dropForeignKey, dropSchemas, deleteFiles, updateVersion]');

    $listener->shouldReceive('isListenerFired')
             ->once()
             ->with($event)
             ->andReturn(true); // Simulate listener already fired

    // Assert that none of the private update methods are called
    $listener->shouldNotReceive('replaceStateAndCityName');
    $listener->shouldNotReceive('dropForeignKey');
    $listener->shouldNotReceive('dropSchemas');
    $listener->shouldNotReceive('deleteFiles');
    $listener->shouldNotReceive('updateVersion');

    $listener->handle($event);
});

test('handle method executes all update steps if listener is not fired', function () {
    $event = Mockery::mock(UpdateFinished::class);

    // Partial mock Version200 to control `isListenerFired` and assert calls to private methods.
    $listener = Mockery::mock(Version200::class . '[isListenerFired, replaceStateAndCityName, dropForeignKey, dropSchemas, deleteFiles, updateVersion]');

    $listener->shouldReceive('isListenerFired')
             ->once()
             ->with($event)
             ->andReturn(false); // Simulate listener not yet fired

    // Assert that all private update methods are called in the correct order
    $listener->shouldReceive('replaceStateAndCityName')
             ->once()
             ->ordered('update_steps');
    $listener->shouldReceive('dropForeignKey')
             ->once()
             ->ordered('update_steps');
    $listener->shouldReceive('dropSchemas')
             ->once()
             ->ordered('update_steps');
    $listener->shouldReceive('deleteFiles')
             ->once()
             ->ordered('update_steps');
    $listener->shouldReceive('updateVersion')
             ->once()
             ->ordered('update_steps');

    $listener->handle($event);
});

test('replaceStateAndCityName updates addresses with city and state names', function () {
    // Mock Schema facade to check the table modification
    Schema::shouldReceive('table')
          ->once()
          ->with('addresses', Mockery::on(function (Closure $callback) {
              $blueprintMock = Mockery::mock(Blueprint::class);
              // Assert adding nullable string columns for 'state' and 'city'
              $blueprintMock->shouldReceive('string')->with('state')->andReturnSelf();
              $blueprintMock->shouldReceive('nullable')->twice()->andReturnSelf(); // One for state, one for city
              $blueprintMock->shouldReceive('string')->with('city')->andReturnSelf();
              $callback($blueprintMock); // Execute the closure passed to Schema::table
              return true;
          }));

    // Mock Address model instances and their saves
    $address1 = Mockery::mock(\Crater\Models\Address::class);
    $address1->city_id = 1;
    $address1->state_id = 10;
    $address1->shouldReceive('save')->once(); // Will be called for each address

    $address2 = Mockery::mock(\Crater\Models\Address::class);
    $address2->city_id = 2;
    $address2->state_id = null; // Edge case: no state_id
    $address2->shouldReceive('save')->once();

    $address3 = Mockery::mock(\Crater\Models\Address::class);
    $address3->city_id = null; // Edge case: no city_id
    $address3->state_id = 20;
    $address3->shouldReceive('save')->once();

    $address4 = Mockery::mock(\Crater\Models\Address::class);
    $address4->city_id = 3; // Edge case: city not found
    $address4->state_id = 30; // Edge case: state not found
    $address4->shouldReceive('save')->once();

    $addressesCollection = collect([$address1, $address2, $address3, $address4]);

    // Mock the static `all()` method of Address model
    Mockery::mock('alias:\Crater\Models\Address')
        ->shouldReceive('all')
        ->once()
        ->andReturn($addressesCollection);

    // Mock City model's static `find()` method
    $city1 = (object)['name' => 'New York'];
    $city2 = (object)['name' => 'Los Angeles'];
    Mockery::mock('alias:\Crater\City')
        ->shouldReceive('find')
        ->with(1)->andReturn($city1);
    Mockery::mock('alias:\Crater\City')
        ->shouldReceive('find')
        ->with(2)->andReturn($city2);
    Mockery::mock('alias:\Crater\City')
        ->shouldReceive('find')
        ->with(3)->andReturn(null); // Simulate city not found
    Mockery::mock('alias:\Crater\City')
        ->shouldReceive('find')
        ->with(null)->andReturn(null); // Simulate finding with null ID

    // Mock State model's static `find()` method
    $state1 = (object)['name' => 'NY'];
    $state2 = (object)['name' => 'CA'];
    Mockery::mock('alias:\Crater\State')
        ->shouldReceive('find')
        ->with(10)->andReturn($state1);
    Mockery::mock('alias:\Crater\State')
        ->shouldReceive('find')
        ->with(20)->andReturn($state2);
    Mockery::mock('alias:\Crater\State')
        ->shouldReceive('find')
        ->with(30)->andReturn(null); // Simulate state not found
    Mockery::mock('alias:\Crater\State')
        ->shouldReceive('find')
        ->with(null)->andReturn(null); // Simulate finding with null ID

    // Use reflection to call the private method
    $listener = new Version200();
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('replaceStateAndCityName');
    $method->setAccessible(true);
    $method->invoke($listener);

    // Assert that the properties were set correctly on the mocked address objects
    expect($address1->city)->toBe('New York');
    expect($address1->state)->toBe('NY');
    expect($address2->city)->toBe('Los Angeles');
    expect($address2->state)->toBeNull(); // State_id was null
    expect($address3->city)->toBeNull(); // City_id was null
    expect($address3->state)->toBe('CA');
    expect($address4->city)->toBeNull(); // City was not found
    expect($address4->state)->toBeNull(); // State was not found
});

test('replaceStateAndCityName handles empty address collection gracefully', function () {
    // Mock Schema facade (still needs to be called to add columns)
    Schema::shouldReceive('table')
          ->once()
          ->with('addresses', Mockery::on(function (Closure $callback) {
              $blueprintMock = Mockery::mock(Blueprint::class);
              $blueprintMock->shouldReceive('string')->with('state')->andReturnSelf();
              $blueprintMock->shouldReceive('nullable')->twice()->andReturnSelf();
              $blueprintMock->shouldReceive('string')->with('city')->andReturnSelf();
              $callback($blueprintMock);
              return true;
          }));

    // Mock Address model to return an empty collection
    Mockery::mock('alias:\Crater\Models\Address')
        ->shouldReceive('all')
        ->once()
        ->andReturn(collect([]));

    // Ensure City and State find methods are NOT called if there are no addresses
    Mockery::mock('alias:\Crater\City')->shouldNotReceive('find');
    Mockery::mock('alias:\Crater\State')->shouldNotReceive('find');

    // Use reflection to call the private method
    $listener = new Version200();
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('replaceStateAndCityName');
    $method->setAccessible(true);
    $method->invoke($listener);
});

test('dropForeignKey drops state and city foreign keys and columns from addresses table', function () {
    // Mock Schema facade to check the table modification
    Schema::shouldReceive('table')
          ->once()
          ->with('addresses', Mockery::on(function (Closure $callback) {
              $blueprintMock = Mockery::mock(Blueprint::class);
              // Assert specific foreign keys and columns are dropped
              $blueprintMock->shouldReceive('dropForeign')->once()->with('addresses_state_id_foreign');
              $blueprintMock->shouldReceive('dropForeign')->once()->with('addresses_city_id_foreign');
              $blueprintMock->shouldReceive('dropColumn')->once()->with('state_id');
              $blueprintMock->shouldReceive('dropColumn')->once()->with('city_id');

              $callback($blueprintMock); // Execute the closure passed to Schema::table
              return true;
          }));

    // Use reflection to call the private method
    $listener = new Version200();
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('dropForeignKey');
    $method->setAccessible(true);
    $method->invoke($listener);
});

test('dropSchemas disables and enables foreign key constraints and drops states and cities tables', function () {
    // Mock Schema facade methods in their expected order
    Schema::shouldReceive('disableForeignKeyConstraints')->once()->ordered();
    Schema::shouldReceive('dropIfExists')->once()->with('states')->ordered();
    Schema::shouldReceive('dropIfExists')->once()->with('cities')->ordered();
    Schema::shouldReceive('enableForeignKeyConstraints')->once()->ordered();

    // Use reflection to call the private method
    $listener = new Version200();
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('dropSchemas');
    $method->setAccessible(true);
    $method->invoke($listener);
});

test('deleteFiles deletes specified migration and model files', function () {
    // Mock File facade to check the delete call with correct paths
    File::shouldReceive('delete')
        ->once()
        ->with([
            '/mock/database/path/migrations/2017_05_06_172817_create_cities_table.php',
            '/mock/database/path/migrations/2017_05_06_173711_create_states_table.php',
            '/mock/database/path/seeds/StatesTableSeeder.php',
            '/mock/database/path/seeds/CitiesTableSeeder.php',
            '/mock/app/path/City.php',
            '/mock/app/path/State.php',
        ])
        ->andReturn(true); // Simulate successful file deletion

    // Use reflection to call the private method
    $listener = new Version200();
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('deleteFiles');
    $method->setAccessible(true);
    $method->invoke($listener);
});

test('updateVersion sets the application version in settings', function () {
    // Mock Setting model's static `setSetting()` method
    Mockery::mock('alias:' . Setting::class)
           ->shouldReceive('setSetting')
           ->once()
           ->with('version', Version200::VERSION); // Assert correct key and value

    // Use reflection to call the private method
    $listener = new Version200();
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('updateVersion');
    $method->setAccessible(true);
    $method->invoke($listener);
});
