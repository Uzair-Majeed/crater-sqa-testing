<?php

use Illuminate\Http\Request;
use Crater\Http\Resources\Customer\CustomerResource;
use Crater\Models\Currency;
use Crater\Models\Module;
use Crater\Http\Controllers\V1\Customer\General\BootstrapController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource; // Used for reflection into `additional` property


// Helper function to create mock menu items for testing purposes
function createMockMenuItem(string $title, string $url): stdClass
{
    $item = new stdClass();
    $item->title = $title;
    $item->link = new stdClass();
    $item->link->path = ['url' => $url];
    return $item;
}

beforeEach(function () {
    // Close Mockery expectations from previous tests
    Mockery::close();

    // Swap the Auth facade with a partial mock to allow specific `shouldReceive` calls
    // while keeping its base functionality or avoiding errors if not all methods are mocked.
    Auth::swap(Mockery::mock('Auth')->makePartial());

    // Mock the global `\Menu` facade/helper. Assuming it acts like a facade for mocking.
    // If `\Menu` is not a facade or is not globally available, this mock strategy might need adjustment
    // (e.g., mocking a service container binding if it's a package like Spatie/Laravel-Menu).
    if (!class_exists('\Menu')) {
        // Define a dummy class if \Menu doesn't exist, to allow Mockery to work.
        // This is a workaround if \Menu is purely a global helper or not loaded in tests.
        // In a typical Laravel setup, facades are resolved.
        class_alias(Mockery::mock()->getClassName(), '\Menu');
    }
});

test('it returns customer data and meta information for an authenticated customer with menu items', function () {
    // Arrange
    $customer = (object)[
        'id' => 1,
        'name' => 'John Doe',
        'currency_id' => 10,
        'email' => 'john@example.com',
        'phone' => '123-456-7890',
    ];
    $currency = (object)['id' => 10, 'code' => 'USD', 'symbol' => '$'];
    $modules = new Collection(['Sales', 'Purchases']);

    Auth::shouldReceive('guard')->once()->with('customer')->andReturnSelf();
    Auth::shouldReceive('user')->once()->andReturn($customer);

    $mockMenuGet = Mockery::mock();
    $mockMenuGet->items = collect([
        createMockMenuItem('Dashboard', '/customer/dashboard'),
        createMockMenuItem('Invoices', '/customer/invoices'),
    ]);
    \Menu::shouldReceive('get')->once()->with('customer_portal_menu')->andReturn($mockMenuGet);

    Mockery::mock('alias:' . Currency::class)
        ->shouldReceive('find')->once()->with($customer->currency_id)->andReturn($currency);

    Mockery::mock('alias:' . Module::class)
        ->shouldReceive('where')->once()->with('enabled', true)->andReturnSelf()
        ->shouldReceive('pluck')->once()->with('name')->andReturn($modules);

    // Act
    $controller = new BootstrapController();
    $request = Request::create('/');
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(CustomerResource::class);

    // Use reflection to access the protected `additional` data property of the JsonResource base class
    $reflection = new ReflectionProperty(JsonResource::class, 'additional');
    $reflection->setAccessible(true);
    $additionalData = $reflection->getValue($response);

    expect($additionalData)->toHaveKey('meta');
    expect($additionalData['meta'])->toHaveKeys(['menu', 'current_customer_currency', 'modules']);

    expect($additionalData['meta']['menu'])->toEqual([
        ['title' => 'Dashboard', 'link' => '/customer/dashboard'],
        ['title' => 'Invoices', 'link' => '/customer/invoices'],
    ]);
    expect($additionalData['meta']['current_customer_currency'])->toEqual($currency);
    expect($additionalData['meta']['modules'])->toEqual($modules);
});

test('it throws an Undefined variable error for unauthenticated customer when menu items exist (BUG)', function () {
    // Arrange
    Auth::shouldReceive('guard')->once()->with('customer')->andReturnSelf();
    Auth::shouldReceive('user')->once()->andReturn(null); // Unauthenticated customer

    $mockMenuGet = Mockery::mock();
    $mockMenuGet->items = collect([
        createMockMenuItem('Dashboard', '/customer/dashboard'),
    ]);
    \Menu::shouldReceive('get')->once()->with('customer_portal_menu')->andReturn($mockMenuGet);

    // Act & Assert
    $controller = new BootstrapController();
    $request = Request::create('/');

    // The code as provided will throw an `Undefined variable: menu` error if `$customer` is null
    // because `$menu` is only initialized inside the `if ($customer)` block.
    $this->expectException(Error::class);
    $this->expectExceptionMessageMatches('/Undefined variable: menu/');
    $controller($request);
});

test('it returns an empty menu array when no menu items are available for authenticated customer', function () {
    // Arrange
    $customer = (object)[
        'id' => 1,
        'name' => 'John Doe',
        'currency_id' => 10,
    ];
    $currency = (object)['id' => 10, 'code' => 'USD', 'symbol' => '$'];
    $modules = new Collection(['Sales', 'Purchases']);

    Auth::shouldReceive('guard')->once()->with('customer')->andReturnSelf();
    Auth::shouldReceive('user')->once()->andReturn($customer);

    $mockMenuGet = Mockery::mock();
    $mockMenuGet->items = collect([]); // No menu items
    \Menu::shouldReceive('get')->once()->with('customer_portal_menu')->andReturn($mockMenuGet);

    Mockery::mock('alias:' . Currency::class)
        ->shouldReceive('find')->once()->with($customer->currency_id)->andReturn($currency);

    Mockery::mock('alias:' . Module::class)
        ->shouldReceive('where')->once()->with('enabled', true)->andReturnSelf()
        ->shouldReceive('pluck')->once()->with('name')->andReturn($modules);

    // Act
    $controller = new BootstrapController();
    $request = Request::create('/');
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(CustomerResource::class);

    $reflection = new ReflectionProperty(JsonResource::class, 'additional');
    $reflection->setAccessible(true);
    $additionalData = $reflection->getValue($response);

    expect($additionalData)->toHaveKey('meta');
    expect($additionalData['meta'])->toHaveKeys(['menu', 'current_customer_currency', 'modules']);

    expect($additionalData['meta']['menu'])->toEqual([]); // Should be an empty array
    expect($additionalData['meta']['current_customer_currency'])->toEqual($currency);
    expect($additionalData['meta']['modules'])->toEqual($modules);
});

test('it handles null currency when currency_id is not found for authenticated customer', function () {
    // Arrange
    $customer = (object)[
        'id' => 1,
        'name' => 'John Doe',
        'currency_id' => 99, // Non-existent currency ID
    ];
    $modules = new Collection(['Sales', 'Purchases']);

    Auth::shouldReceive('guard')->once()->with('customer')->andReturnSelf();
    Auth::shouldReceive('user')->once()->andReturn($customer);

    $mockMenuGet = Mockery::mock();
    $mockMenuGet->items = collect([
        createMockMenuItem('Dashboard', '/customer/dashboard'),
    ]);
    \Menu::shouldReceive('get')->once()->with('customer_portal_menu')->andReturn($mockMenuGet);

    Mockery::mock('alias:' . Currency::class)
        ->shouldReceive('find')->once()->with($customer->currency_id)->andReturn(null); // Currency not found

    Mockery::mock('alias:' . Module::class)
        ->shouldReceive('where')->once()->with('enabled', true)->andReturnSelf()
        ->shouldReceive('pluck')->once()->with('name')->andReturn($modules);

    // Act
    $controller = new BootstrapController();
    $request = Request::create('/');
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(CustomerResource::class);

    $reflection = new ReflectionProperty(JsonResource::class, 'additional');
    $reflection->setAccessible(true);
    $additionalData = $reflection->getValue($response);

    expect($additionalData)->toHaveKey('meta');
    expect($additionalData['meta'])->toHaveKeys(['menu', 'current_customer_currency', 'modules']);

    expect($additionalData['meta']['menu'])->toEqual([
        ['title' => 'Dashboard', 'link' => '/customer/dashboard'],
    ]);
    expect($additionalData['meta']['current_customer_currency'])->toBeNull(); // Should be null
    expect($additionalData['meta']['modules'])->toEqual($modules);
});

test('it returns an empty modules array when no enabled modules exist for authenticated customer', function () {
    // Arrange
    $customer = (object)[
        'id' => 1,
        'name' => 'John Doe',
        'currency_id' => 10,
    ];
    $currency = (object)['id' => 10, 'code' => 'USD', 'symbol' => '$'];
    $modules = new Collection([]); // No enabled modules

    Auth::shouldReceive('guard')->once()->with('customer')->andReturnSelf();
    Auth::shouldReceive('user')->once()->andReturn($customer);

    $mockMenuGet = Mockery::mock();
    $mockMenuGet->items = collect([
        createMockMenuItem('Dashboard', '/customer/dashboard'),
    ]);
    \Menu::shouldReceive('get')->once()->with('customer_portal_menu')->andReturn($mockMenuGet);

    Mockery::mock('alias:' . Currency::class)
        ->shouldReceive('find')->once()->with($customer->currency_id)->andReturn($currency);

    Mockery::mock('alias:' . Module::class)
        ->shouldReceive('where')->once()->with('enabled', true)->andReturnSelf()
        ->shouldReceive('pluck')->once()->with('name')->andReturn($modules); // No enabled modules

    // Act
    $controller = new BootstrapController();
    $request = Request::create('/');
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(CustomerResource::class);

    $reflection = new ReflectionProperty(JsonResource::class, 'additional');
    $reflection->setAccessible(true);
    $additionalData = $reflection->getValue($response);

    expect($additionalData)->toHaveKey('meta');
    expect($additionalData['meta'])->toHaveKeys(['menu', 'current_customer_currency', 'modules']);

    expect($additionalData['meta']['menu'])->toEqual([
        ['title' => 'Dashboard', 'link' => '/customer/dashboard'],
    ]);
    expect($additionalData['meta']['current_customer_currency'])->toEqual($currency);
    expect($additionalData['meta']['modules'])->toEqual($modules);
});

 

afterEach(function () {
    Mockery::close();
});
