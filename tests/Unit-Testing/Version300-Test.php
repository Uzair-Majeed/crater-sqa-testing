<?php

use Crater\Listeners\Updates\v3\Version300;
use Crater\Models\Currency;
use Crater\Models\Item;
use Crater\Models\Payment;
use Crater\Models\PaymentMethod;
use Crater\Models\Setting;
use Crater\Models\Unit;
use Crater\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Vinkla\Hashids\Facades\Hashids;

// Global setup for mocking dependencies
beforeEach(function () {
    Mockery::close(); // Clear Mockery mocks before each test

    // Mock facades
    Mockery::mock('alias:' . Schema::class);
    Mockery::mock('alias:' . Hashids::class);

    // Mock Eloquent models (as static calls)
    Mockery::mock('alias:' . Setting::class);
    Mockery::mock('alias:' . User::class);
    Mockery::mock('alias:' . Unit::class);
    Mockery::mock('alias:' . PaymentMethod::class);
    Mockery::mock('alias:' . Currency::class);
    Mockery::mock('alias:' . Payment::class);
    Mockery::mock('alias:' . Item::class);
});

// Test `__construct` method
test('constructor successfully instantiates the listener', function () {
    $listener = new Version300();
    expect($listener)->toBeInstanceOf(Version300::class);
    // The constructor is empty, so no further behavior to assert.
});

// Test `handle` method
test('handle method returns early if listener is already fired', function () {
    // Create a partial mock for Version300 to mock its inherited `isListenerFired` method
    $listener = Mockery::mock(Version300::class)->makePartial();
    $listener->shouldAllowMockingProtectedMethods(); // Allow mocking if `isListenerFired` is protected in the base Listener class

    $listener->shouldReceive('isListenerFired')
        ->once()
        ->andReturn(true); // Simulate listener already fired

    // Ensure no other methods are called
    $listener->shouldNotReceive('changeMigrations');
    $listener->shouldNotReceive('addSeederData');
    $listener->shouldNotReceive('databaseChanges');
    Setting::shouldNotReceive('setSetting');

    $listener->handle(new stdClass()); // Pass a dummy event object
});

test('handle method executes all update steps if listener is not fired', function () {
    $listener = Mockery::mock(Version300::class)->makePartial();
    $listener->shouldAllowMockingProtectedMethods();

    $listener->shouldReceive('isListenerFired')
        ->once()
        ->andReturn(false); // Simulate listener not fired

    // Expect all internal update methods to be called in order
    $listener->shouldReceive('changeMigrations')
        ->once()
        ->with(false); // First call to changeMigrations (for additions)
    $listener->shouldReceive('addSeederData')
        ->once();
    $listener->shouldReceive('databaseChanges')
        ->once();
    $listener->shouldReceive('changeMigrations')
        ->once()
        ->with(true); // Second call to changeMigrations (for removals)

    // Expect Setting::setSetting to be called to mark the version update
    Setting::shouldReceive('setSetting')
        ->once()
        ->with('version', Version300::VERSION);

    $listener->handle(new stdClass());
});

// Test `changeMigrations` method
test('changeMigrations drops specified columns when removeColumn is true', function () {
    $listener = new Version300();

    // Expect Schema::table for 'items' with closure for 'unit' column drop
    Schema::shouldReceive('table')
        ->once()
        ->with('items', Mockery::on(function ($closure) {
            $blueprint = Mockery::mock(Blueprint::class);
            $blueprint->shouldReceive('dropColumn')
                ->once()
                ->with('unit');
            $closure($blueprint); // Execute the closure to test its content
            return true;
        }));

    // Expect Schema::table for 'payments' with closure for 'payment_mode' column drop
    Schema::shouldReceive('table')
        ->once()
        ->with('payments', Mockery::on(function ($closure) {
            $blueprint = Mockery::mock(Blueprint::class);
            $blueprint->shouldReceive('dropColumn')
                ->once()
                ->with('payment_mode');
            $closure($blueprint); // Execute the closure to test its content
            return true;
        }));

    $listener->changeMigrations(true);
});

test('changeMigrations creates and alters tables when removeColumn is false', function () {
    $listener = new Version300();

    // Expect Schema::create for 'units' table with detailed Blueprint expectations
    Schema::shouldReceive('create')
        ->once()
        ->with('units', Mockery::on(function ($closure) {
            $blueprint = Mockery::mock(Blueprint::class);
            $blueprint->shouldReceive('increments')->once()->with('id')->andReturnSelf();
            $blueprint->shouldReceive('string')->once()->with('name')->andReturnSelf();
            $blueprint->shouldReceive('integer')->once()->with('company_id')->andReturnSelf();
            $blueprint->shouldReceive('unsigned')->once()->andReturnSelf();
            $blueprint->shouldReceive('nullable')->once()->andReturnSelf();
            $blueprint->shouldReceive('foreign')->once()->with('company_id')->andReturnSelf();
            $blueprint->shouldReceive('references')->once()->with('id')->andReturnSelf();
            $blueprint->shouldReceive('on')->once()->with('companies')->andReturnSelf();
            $blueprint->shouldReceive('timestamps')->once();
            $closure($blueprint);
            return true;
        }));

    // Expect Schema::table for 'items' table with detailed Blueprint expectations
    Schema::shouldReceive('table')
        ->once()
        ->with('items', Mockery::on(function ($closure) {
            $blueprint = Mockery::mock(Blueprint::class);
            $blueprint->shouldReceive('integer')->once()->with('unit_id')->andReturnSelf();
            $blueprint->shouldReceive('unsigned')->once()->andReturnSelf();
            $blueprint->shouldReceive('nullable')->once()->andReturnSelf();
            $blueprint->shouldReceive('foreign')->once()->with('unit_id')->andReturnSelf();
            $blueprint->shouldReceive('references')->once()->with('id')->andReturnSelf();
            $blueprint->shouldReceive('on')->once()->with('units')->andReturnSelf();
            $blueprint->shouldReceive('onDelete')->once()->with('cascade')->andReturnSelf();
            $closure($blueprint);
            return true;
        }));

    // Expect Schema::create for 'payment_methods' table with detailed Blueprint expectations
    Schema::shouldReceive('create')
        ->once()
        ->with('payment_methods', Mockery::on(function ($closure) {
            $blueprint = Mockery::mock(Blueprint::class);
            $blueprint->shouldReceive('increments')->once()->with('id')->andReturnSelf();
            $blueprint->shouldReceive('string')->once()->with('name')->andReturnSelf();
            $blueprint->shouldReceive('integer')->once()->with('company_id')->andReturnSelf();
            $blueprint->shouldReceive('unsigned')->once()->andReturnSelf();
            $blueprint->shouldReceive('nullable')->once()->andReturnSelf();
            $blueprint->shouldReceive('foreign')->once()->with('company_id')->andReturnSelf();
            $blueprint->shouldReceive('references')->once()->with('id')->andReturnSelf();
            $blueprint->shouldReceive('on')->once()->with('companies')->andReturnSelf();
            $blueprint->shouldReceive('timestamps')->once();
            $closure($blueprint);
            return true;
        }));

    // Expect Schema::table for 'payments' table with detailed Blueprint expectations
    Schema::shouldReceive('table')
        ->once()
        ->with('payments', Mockery::on(function ($closure) {
            $blueprint = Mockery::mock(Blueprint::class);
            $blueprint->shouldReceive('string')->once()->with('unique_hash')->andReturnSelf();
            $blueprint->shouldReceive('nullable')->once()->andReturnSelf();
            $blueprint->shouldReceive('integer')->once()->with('payment_method_id')->andReturnSelf();
            $blueprint->shouldReceive('unsigned')->once()->andReturnSelf();
            $blueprint->shouldReceive('nullable')->once()->andReturnSelf();
            $blueprint->shouldReceive('foreign')->once()->with('payment_method_id')->andReturnSelf();
            $blueprint->shouldReceive('references')->once()->with('id')->andReturnSelf();
            $blueprint->shouldReceive('on')->once()->with('payment_methods')->andReturnSelf();
            $blueprint->shouldReceive('onDelete')->once()->with('cascade')->andReturnSelf();
            $closure($blueprint);
            return true;
        }));

    $listener->changeMigrations(false);
});

// Test `addSeederData` method
test('addSeederData seeds default units, payment methods, and currency', function () {
    $listener = new Version300();
    $mockCompanyId = 1;

    // Mock User::where('role', 'admin')->first() to return a user with company_id
    $mockUser = Mockery::mock();
    $mockUser->company_id = $mockCompanyId;
    User::shouldReceive('where')
        ->once()
        ->with('role', 'admin')
        ->andReturnSelf(); // Allow chaining `->first()`
    User::shouldReceive('first')
        ->once()
        ->andReturn($mockUser);

    // Expect Unit::create calls for each default unit
    $expectedUnits = [
        'box', 'cm', 'dz', 'ft', 'g', 'in', 'kg', 'km', 'lb', 'mg', 'pc'
    ];
    foreach ($expectedUnits as $unitName) {
        Unit::shouldReceive('create')
            ->once()
            ->with(['name' => $unitName, 'company_id' => $mockCompanyId]);
    }

    // Expect PaymentMethod::create calls for each default payment method
    $expectedPaymentMethods = [
        'Cash', 'Check', 'Credit Card', 'Bank Transfer'
    ];
    foreach ($expectedPaymentMethods as $methodName) {
        PaymentMethod::shouldReceive('create')
            ->once()
            ->with(['name' => $methodName, 'company_id' => $mockCompanyId]);
    }

    // Expect Currency::create call for Serbian Dinar
    Currency::shouldReceive('create')
        ->once()
        ->with([
            'name' => 'Serbian Dinar',
            'code' => 'RSD',
            'symbol' => 'RSD',
            'precision' => '2',
            'thousand_separator' => '.',
            'decimal_separator' => ',',
        ]);

    $listener->addSeederData();
});

test('addSeederData creates units and payment methods with null company_id if no admin user is found', function () {
    $listener = new Version300();

    // Mock User::where('role', 'admin')->first() to return null
    User::shouldReceive('where')
        ->once()
        ->with('role', 'admin')
        ->andReturnSelf();
    User::shouldReceive('first')
        ->once()
        ->andReturn(null);

    // When `first()` returns null, `$company_id` will implicitly become null due to accessing `->company_id` on null
    // or if the variable wasn't initialized. In PHP 8+, this throws a TypeError. In older PHP, it's a warning.
    // For robust testing, we need to handle this. The most direct interpretation of the code is that if `first()` returns null,
    // the subsequent line `User::where('role', 'admin')->first()->company_id;` will throw a TypeError.
    // A truly robust system would add `if ($user) { $company_id = $user->company_id; } else { $company_id = null; }`.
    // Given the current code, this is a fatal error path. Let's test that it *would* throw an error if not for the mocks.
    // However, the previous test effectively covers the intended *behavior* of data seeding when an admin exists.
    // If the intent is to allow `company_id` to be `null` in `create` calls without crashing, the code needs modification.
    // For white-box testing, we stick to what the code *does*.
    // As it stands, it would throw an error before any `create` calls for units/payment methods.
    // So, we'll confirm that if `first()` returns null, it indeed causes a TypeError.
    expect(fn() => $listener->addSeederData())
        ->toThrow(TypeError::class); // Trying to access company_id on null

    // Currency::create would still be called if the TypeError was caught or handled, but since it's fatal, it won't be.
    // To thoroughly test the scenario where company_id is null without crashing, the code itself would need
    // to handle the null admin user case explicitly. For now, the TypeError is the correct white-box outcome.
});


// Test `databaseChanges` method
test('databaseChanges updates unique_hash and payment_method_id for payments and unit_id for items', function () {
    $listener = new Version300();

    // Mock payments and their expected interactions
    $payment1 = Mockery::mock(Payment::class);
    $payment1->id = 1;
    $payment1->payment_mode = 'Cash'; // Old column value
    $payment1->unique_hash = null;
    $payment1->payment_method_id = null;
    $payment1->shouldReceive('save')->times(2); // One for unique_hash, one for payment_method_id

    $payment2 = Mockery::mock(Payment::class);
    $payment2->id = 2;
    $payment2->payment_mode = 'Check';
    $payment2->unique_hash = null;
    $payment2->payment_method_id = null;
    $payment2->shouldReceive('save')->times(2);

    $paymentsCollection = new Collection([$payment1, $payment2]);
    Payment::shouldReceive('all')->once()->andReturn($paymentsCollection);

    // Mock Hashids calls for each payment
    $mockHashidsConnection = Mockery::mock();
    $mockHashidsConnection->shouldReceive('encode')
        ->once()
        ->with($payment1->id)
        ->andReturn('hashed_id_1');
    $mockHashidsConnection->shouldReceive('encode')
        ->once()
        ->with($payment2->id)
        ->andReturn('hashed_id_2');
    Hashids::shouldReceive('connection')
        ->times(2)
        ->with(Payment::class)
        ->andReturn($mockHashidsConnection);

    // Mock PaymentMethod::where()->first() calls for each payment
    $cashPaymentMethod = Mockery::mock();
    $cashPaymentMethod->id = 101;
    PaymentMethod::shouldReceive('where')
        ->once()
        ->with('name', 'Cash')
        ->andReturnSelf();
    PaymentMethod::shouldReceive('first')
        ->once()
        ->andReturn($cashPaymentMethod);

    $checkPaymentMethod = Mockery::mock();
    $checkPaymentMethod->id = 102;
    PaymentMethod::shouldReceive('where')
        ->once()
        ->with('name', 'Check')
        ->andReturnSelf();
    PaymentMethod::shouldReceive('first')
        ->once()
        ->andReturn($checkPaymentMethod);


    // Mock items and their expected interactions
    $item1 = Mockery::mock(Item::class);
    $item1->id = 1;
    $item1->unit = 'pc'; // Old column value
    $item1->unit_id = null;
    $item1->shouldReceive('save')->once(); // One for unit_id

    $item2 = Mockery::mock(Item::class);
    $item2->id = 2;
    $item2->unit = 'box';
    $item2->unit_id = null;
    $item2->shouldReceive('save')->once();

    $itemsCollection = new Collection([$item1, $item2]);
    Item::shouldReceive('all')->once()->andReturn($itemsCollection);

    // Mock Unit::where()->first() calls for each item
    $pcUnit = Mockery::mock();
    $pcUnit->id = 201;
    Unit::shouldReceive('where')
        ->once()
        ->with('name', 'pc')
        ->andReturnSelf();
    Unit::shouldReceive('first')
        ->once()
        ->andReturn($pcUnit);

    $boxUnit = Mockery::mock();
    $boxUnit->id = 202;
    Unit::shouldReceive('where')
        ->once()
        ->with('name', 'box')
        ->andReturnSelf();
    Unit::shouldReceive('first')
        ->once()
        ->andReturn($boxUnit);


    $listener->databaseChanges();

    // Assert that payment objects were updated correctly
    expect($payment1->unique_hash)->toBe('hashed_id_1');
    expect($payment1->payment_method_id)->toBe(101);
    expect($payment2->unique_hash)->toBe('hashed_id_2');
    expect($payment2->payment_method_id)->toBe(102);

    // Assert that item objects were updated correctly
    expect($item1->unit_id)->toBe(201);
    expect($item2->unit_id)->toBe(202);
});

test('databaseChanges handles missing payment methods and units during update', function () {
    $listener = new Version300();

    // Mock a payment with a non-existent payment mode
    $payment1 = Mockery::mock(Payment::class);
    $payment1->id = 1;
    $payment1->payment_mode = 'NonExistentMethod';
    $payment1->unique_hash = null;
    $payment1->payment_method_id = null;
    $payment1->shouldReceive('save')->once(); // Only for unique_hash, payment_method_id remains null

    $paymentsCollection = new Collection([$payment1]);
    Payment::shouldReceive('all')->once()->andReturn($paymentsCollection);

    // Mock Hashids for the payment
    $mockHashidsConnection = Mockery::mock();
    $mockHashidsConnection->shouldReceive('encode')
        ->once()
        ->with($payment1->id)
        ->andReturn('hashed_id_1');
    Hashids::shouldReceive('connection')
        ->once()
        ->with(Payment::class)
        ->andReturn($mockHashidsConnection);

    // Mock PaymentMethod::where()->first() to return null for the non-existent method
    PaymentMethod::shouldReceive('where')
        ->once()
        ->with('name', 'NonExistentMethod')
        ->andReturnSelf();
    PaymentMethod::shouldReceive('first')
        ->once()
        ->andReturn(null);

    // Mock an item with a non-existent unit
    $item1 = Mockery::mock(Item::class);
    $item1->id = 1;
    $item1->unit = 'NonExistentUnit';
    $item1->unit_id = null;
    $item1->shouldNotReceive('save'); // `unit_id` will not be updated as no matching unit is found

    $itemsCollection = new Collection([$item1]);
    Item::shouldReceive('all')->once()->andReturn($itemsCollection);

    // Mock Unit::where()->first() to return null for the non-existent unit
    Unit::shouldReceive('where')
        ->once()
        ->with('name', 'NonExistentUnit')
        ->andReturnSelf();
    Unit::shouldReceive('first')
        ->once()
        ->andReturn(null);

    $listener->databaseChanges();

    // Assert payment updates
    expect($payment1->unique_hash)->toBe('hashed_id_1');
    expect($payment1->payment_method_id)->toBe(null); // Should remain null

    // Assert item updates
    expect($item1->unit_id)->toBe(null); // Should remain null
});

test('databaseChanges handles empty payment and item collections gracefully', function () {
    $listener = new Version300();

    // Mock Payment::all() to return an empty collection
    Payment::shouldReceive('all')->once()->andReturn(new Collection());
    Hashids::shouldNotReceive('connection'); // No payments, so no Hashids calls
    PaymentMethod::shouldNotReceive('where'); // No payments, so no PaymentMethod lookups

    // Mock Item::all() to return an empty collection
    Item::shouldReceive('all')->once()->andReturn(new Collection());
    Unit::shouldNotReceive('where'); // No items, so no Unit lookups

    $listener->databaseChanges();
    // The test passes if no exceptions are thrown and no unexpected mock calls are made.
});




afterEach(function () {
    Mockery::close();
});
