<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Container\Container; // Required if Facade::clearResolvedInstances() is used and no app is booted


// Dummy class to use the trait for testing
class DummyMenuGeneratorClass
{
    use \Crater\Traits\GeneratesMenuTrait;

    // The trait is expected to call static methods on a class aliased as 'Menu'.
    // The original error "Facade does not implement getFacadeAccessor method" indicates
    // that `\Menu` was being resolved as a Laravel Facade, but an incomplete one.
    // To fix this, we provide a basic `Menu` class for Mockery's `alias` functionality
    // to target. This bypasses Laravel's Facade resolution logic that was causing the error.
}

// Define a simple, non-Laravel-Facade Menu class for Mockery to alias.
// This ensures that when the trait calls `\Menu::get()`, Mockery's alias mock is used,
// rather than Laravel's Facade resolution which requires `getFacadeAccessor`.
// This class will be replaced by Mockery's alias mock during the tests.
if (!class_exists('Menu')) {
    class Menu
    {
        // The static methods called by the trait must exist, even if they are empty
        // as Mockery will replace their implementation with its expectations.
        public static function get(string $key)
        {
            // This placeholder method will be intercepted by Mockery.
            return null;
        }
    }
}


// Dummy class to represent the Menu item structure expected by the trait
class DummyMenuItem
{
    public $title;
    public $link; // This will be an object with a path property
    public $data; // This will be an associative array

    public function __construct(string $title, string $url, string $icon, string $name, string $group)
    {
        $this->title = $title;
        $this->link = (object)['path' => ['url' => $url]];
        $this->data = [
            'icon' => $icon,
            'name' => $name,
            'group' => $group,
        ];
    }
}

// Dummy class to represent the user who checks access
class DummyUser
{
    public function checkAccess($data): bool
    {
        // This method will be mocked in tests
        return false;
    }
}

// Set up for each test to ensure a clean slate for mocks
beforeEach(function () {
    // While 'Menu' is mocked directly as an alias, `Facade::clearResolvedInstances()`
    // is a good general practice for test isolation in a Laravel context, clearing
    // any resolved instances of *other* facades that might be implicitly used.
    // No need to clear specific classes like 'Menu' if it's not a real Facade for this test.
    Facade::clearResolvedInstances();
});

// Clean up Mockery after each test
afterEach(function () {
    Mockery::close();
});

test('generateMenu returns an empty array when no menu items exist', function () {
    $generator = new DummyMenuGeneratorClass();
    $userMock = Mockery::mock(DummyUser::class);
    $key = 'main_menu';

    // Mock the \Menu alias to return an object with empty items
    Mockery::mock('alias:Menu')
        ->shouldReceive('get')
        ->with($key)
        ->andReturn((object)['items' => (object)['toArray' => []]])
        ->once();

    $result = $generator->generateMenu($key, $userMock);

    expect($result)->toBeArray()->toBeEmpty();
});

test('generateMenu returns an empty array when all items exist but none are accessible', function () {
    $generator = new DummyMenuGeneratorClass();
    $userMock = Mockery::mock(DummyUser::class);
    $key = 'main_menu';

    $item1 = new DummyMenuItem('Dashboard', '/dashboard', 'fa-home', 'dashboard', 'general');
    $item2 = new DummyMenuItem('Clients', '/clients', 'fa-users', 'clients', 'crm');

    $menuItems = [$item1, $item2];

    // Mock the \Menu alias
    Mockery::mock('alias:Menu')
        ->shouldReceive('get')
        ->with($key)
        ->andReturn((object)['items' => (object)['toArray' => $menuItems]])
        ->once();

    // Mock user->checkAccess to return false for all items
    $userMock->shouldReceive('checkAccess')
        ->with(Mockery::type(DummyMenuItem::class))
        ->andReturn(false)
        ->times(count($menuItems));

    $result = $generator->generateMenu($key, $userMock);

    expect($result)->toBeArray()->toBeEmpty();
});

test('generateMenu returns all items when all exist and are accessible', function () {
    $generator = new DummyMenuGeneratorClass();
    $userMock = Mockery::mock(DummyUser::class);
    $key = 'main_menu';

    $item1 = new DummyMenuItem('Dashboard', '/dashboard', 'fa-home', 'dashboard', 'general');
    $item2 = new DummyMenuItem('Clients', '/clients', 'fa-users', 'clients', 'crm');
    $item3 = new DummyMenuItem('Settings', '/settings', 'fa-cog', 'settings', 'admin');

    $menuItems = [$item1, $item2, $item3];

    // Mock the \Menu alias
    Mockery::mock('alias:Menu')
        ->shouldReceive('get')
        ->with($key)
        ->andReturn((object)['items' => (object)['toArray' => $menuItems]])
        ->once();

    // Mock user->checkAccess to return true for all items
    $userMock->shouldReceive('checkAccess')
        ->with(Mockery::type(DummyMenuItem::class))
        ->andReturn(true)
        ->times(count($menuItems));

    $expectedMenu = [
        [
            'title' => 'Dashboard',
            'link' => '/dashboard',
            'icon' => 'fa-home',
            'name' => 'dashboard',
            'group' => 'general',
        ],
        [
            'title' => 'Clients',
            'link' => '/clients',
            'icon' => 'fa-users',
            'name' => 'clients',
            'group' => 'crm',
        ],
        [
            'title' => 'Settings',
            'link' => '/settings',
            'icon' => 'fa-cog',
            'name' => 'settings',
            'group' => 'admin',
        ],
    ];

    $result = $generator->generateMenu($key, $userMock);

    expect($result)->toBeArray()->toHaveCount(3)->toEqual($expectedMenu);
});

test('generateMenu returns only accessible items when some are not accessible', function () {
    $generator = new DummyMenuGeneratorClass();
    $userMock = Mockery::mock(DummyUser::class);
    $key = 'main_menu';

    $item1 = new DummyMenuItem('Dashboard', '/dashboard', 'fa-home', 'dashboard', 'general'); // Accessible
    $item2 = new DummyMenuItem('Clients', '/clients', 'fa-users', 'clients', 'crm');         // Not Accessible
    $item3 = new DummyMenuItem('Invoices', '/invoices', 'fa-file-invoice', 'invoices', 'billing'); // Accessible
    $item4 = new DummyMenuItem('Reports', '/reports', 'fa-chart-bar', 'reports', 'admin');   // Not Accessible

    $menuItems = [$item1, $item2, $item3, $item4];

    // Mock the \Menu alias
    Mockery::mock('alias:Menu')
        ->shouldReceive('get')
        ->with($key)
        ->andReturn((object)['items' => (object)['toArray' => $menuItems]])
        ->once();

    // Mock user->checkAccess to return true for item1 and item3, false for item2 and item4
    $userMock->shouldReceive('checkAccess')
        ->andReturnUsing(function ($item) use ($item1, $item3) {
            return in_array($item, [$item1, $item3], true);
        })
        ->times(count($menuItems));

    $expectedMenu = [
        [
            'title' => 'Dashboard',
            'link' => '/dashboard',
            'icon' => 'fa-home',
            'name' => 'dashboard',
            'group' => 'general',
        ],
        [
            'title' => 'Invoices',
            'link' => '/invoices',
            'icon' => 'fa-file-invoice',
            'name' => 'invoices',
            'group' => 'billing',
        ],
    ];

    $result = $generator->generateMenu($key, $userMock);

    expect($result)->toBeArray()->toHaveCount(2)->toEqual($expectedMenu);
});

test('generateMenu correctly structures a single accessible item', function () {
    $generator = new DummyMenuGeneratorClass();
    $userMock = Mockery::mock(DummyUser::class);
    $key = 'single_item_menu';

    $item1 = new DummyMenuItem('Home', '/', 'fa-home', 'home_page', 'main');

    $menuItems = [$item1];

    Mockery::mock('alias:Menu')
        ->shouldReceive('get')
        ->with($key)
        ->andReturn((object)['items' => (object)['toArray' => $menuItems]])
        ->once();

    $userMock->shouldReceive('checkAccess')
        ->with($item1)
        ->andReturn(true)
        ->once();

    $expectedMenu = [
        [
            'title' => 'Home',
            'link' => '/',
            'icon' => 'fa-home',
            'name' => 'home_page',
            'group' => 'main',
        ],
    ];

    $result = $generator->generateMenu($key, $userMock);

    expect($result)->toBeArray()->toHaveCount(1)->toEqual($expectedMenu);
});

test('generateMenu calls checkAccess with the correct menu item objects', function () {
    $generator = new DummyMenuGeneratorClass();
    $userMock = Mockery::mock(DummyUser::class);
    $key = 'specific_call_menu';

    $item1 = new DummyMenuItem('First Item', '/first', 'fa-one', 'first', 'group1');
    $item2 = new DummyMenuItem('Second Item', '/second', 'fa-two', 'second', 'group2');

    $menuItems = [$item1, $item2];

    Mockery::mock('alias:Menu')
        ->shouldReceive('get')
        ->with($key)
        ->andReturn((object)['items' => (object)['toArray' => $menuItems]])
        ->once();

    // Expect checkAccess to be called exactly with item1 and item2 in sequence
    $userMock->shouldReceive('checkAccess')
        ->with(Mockery::on(fn ($arg) => $arg === $item1))
        ->andReturn(true)
        ->once();

    $userMock->shouldReceive('checkAccess')
        ->with(Mockery::on(fn ($arg) => $arg === $item2))
        ->andReturn(false) // Make second item inaccessible to verify filtered output
        ->once();

    $expectedMenu = [
        [
            'title' => 'First Item',
            'link' => '/first',
            'icon' => 'fa-one',
            'name' => 'first',
            'group' => 'group1',
        ]
    ];

    $result = $generator->generateMenu($key, $userMock);

    expect($result)->toBeArray()->toHaveCount(1)->toEqual($expectedMenu);
});

test('generateMenu handles different menu keys correctly', function () {
    $generator = new DummyMenuGeneratorClass();
    $userMock = Mockery::mock(DummyUser::class);
    $key1 = 'admin_panel';
    $key2 = 'user_settings';

    $item1_admin = new DummyMenuItem('Users', '/admin/users', 'fa-user', 'admin_users', 'admin');
    $item2_user = new DummyMenuItem('Profile', '/settings/profile', 'fa-user-circle', 'user_profile', 'settings');

    // Create a single Mockery alias mock instance for 'Menu'.
    // All expectations for static calls to 'Menu' must be set on this one instance.
    $menuMock = Mockery::mock('alias:Menu');

    // Set expectations for the first key
    $menuMock->shouldReceive('get')
        ->with($key1)
        ->andReturn((object)['items' => (object)['toArray' => [$item1_admin]]])
        ->once();
    $userMock->shouldReceive('checkAccess')
        ->with($item1_admin)
        ->andReturn(true)
        ->once();

    $result1 = $generator->generateMenu($key1, $userMock);
    expect($result1)->toHaveCount(1);
    expect($result1[0]['link'])->toBe('/admin/users');

    // Set expectations for the second key on the *same* mock instance.
    $menuMock->shouldReceive('get')
        ->with($key2)
        ->andReturn((object)['items' => (object)['toArray' => [$item2_user]]])
        ->once();
    $userMock->shouldReceive('checkAccess')
        ->with($item2_user)
        ->andReturn(true)
        ->once();

    $result2 = $generator->generateMenu($key2, $userMock);
    expect($result2)->toHaveCount(1);
    expect($result2[0]['link'])->toBe('/settings/profile');
});