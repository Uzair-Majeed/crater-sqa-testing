<?php

use Crater\Providers\AppServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\Paginator;

function getPartialMockedServiceProvider()
{
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('runningInConsole')->andReturnFalse();
    $app->shouldReceive('basePath')->andReturn(dirname(__DIR__));
    return Mockery::mock(AppServiceProvider::class, [$app])->makePartial();
}

beforeEach(function () {
    // Instead of mocking Paginator (which can't be mocked),
    // we'll stub the global function to prevent side effects.
    // Since Paginator::useBootstrapThree() is static and not mockable, call real method.
    // This is safe, as it's idempotent and does not create side effects in tests.
    Paginator::useBootstrapThree();
});

test('register method does nothing', function () {
    $provider = getPartialMockedServiceProvider();
    expect(fn() => $provider->register())->not->toThrow(Exception::class);
});

test('it calls loadJsonTranslationsFrom with correct path', function () {
    Storage::shouldReceive('disk')->with('local')->andReturnSelf();
    Storage::shouldReceive('has')->with('database_created')->andReturnFalse();
    Schema::shouldReceive('hasTable')->with('abilities')->andReturnFalse();

    $provider = getPartialMockedServiceProvider();
    $provider->shouldReceive('loadJsonTranslationsFrom')
        ->once()
        ->with(resource_path('scripts/locales'));
    $provider->shouldReceive('addMenus')->never();

    $provider->boot();
});

test('it calls addMenus when database_created exists and abilities table exists', function () {
    Storage::shouldReceive('disk')->with('local')->andReturnSelf();
    Storage::shouldReceive('has')->with('database_created')->andReturnTrue();
    Schema::shouldReceive('hasTable')->with('abilities')->andReturnTrue();

    $provider = getPartialMockedServiceProvider();
    $provider->shouldReceive('loadJsonTranslationsFrom')->once();
    $provider->shouldReceive('addMenus')->once();

    $provider->boot();
});

test('it does not call addMenus when database_created does not exist', function () {
    Storage::shouldReceive('disk')->with('local')->andReturnSelf();
    Storage::shouldReceive('has')->with('database_created')->andReturnFalse();
    Schema::shouldReceive('hasTable')->with('abilities')->andReturnTrue();

    $provider = getPartialMockedServiceProvider();
    $provider->shouldReceive('loadJsonTranslationsFrom')->once();
    $provider->shouldReceive('addMenus')->never();

    $provider->boot();
});

test('it does not call addMenus when abilities table does not exist', function () {
    Storage::shouldReceive('disk')->with('local')->andReturnSelf();
    Storage::shouldReceive('has')->with('database_created')->andReturnTrue();
    Schema::shouldReceive('hasTable')->with('abilities')->andReturnFalse();

    $provider = getPartialMockedServiceProvider();
    $provider->shouldReceive('loadJsonTranslationsFrom')->once();
    $provider->shouldReceive('addMenus')->never();

    $provider->boot();
});

test('it does not call addMenus when both conditions are false', function () {
    Storage::shouldReceive('disk')->with('local')->andReturnSelf();
    Storage::shouldReceive('has')->with('database_created')->andReturnFalse();
    Schema::shouldReceive('hasTable')->with('abilities')->andReturnFalse();

    $provider = getPartialMockedServiceProvider();
    $provider->shouldReceive('loadJsonTranslationsFrom')->once();
    $provider->shouldReceive('addMenus')->never();

    $provider->boot();
});

$dummyMenuItem = [
    'title' => 'Dashboard',
    'link' => '/dashboard',
    'icon' => 'icon-dashboard',
    'name' => 'dashboard',
    'owner_only' => false,
    'ability' => 'view_dashboard',
    'model' => null,
    'group' => 'main',
];

$dummyMenuItemTwo = [
    'title' => 'Settings',
    'link' => '/settings',
    'icon' => 'icon-settings',
    'name' => 'settings',
    'owner_only' => true,
    'ability' => 'manage_settings',
    'model' => 'Setting',
    'group' => 'admin',
];

test('it registers main_menu and calls generateMenu for each item', function () use ($dummyMenuItem, $dummyMenuItemTwo) {
    Config::set('crater.main_menu', [$dummyMenuItem, $dummyMenuItemTwo]);
    Config::set('crater.setting_menu', []);
    Config::set('crater.customer_menu', []);

    $provider = getPartialMockedServiceProvider();
    $provider->shouldReceive('generateMenu')
        ->twice()
        ->withArgs(function ($menu, $data) use ($dummyMenuItem, $dummyMenuItemTwo) {
            return ($data === $dummyMenuItem || $data === $dummyMenuItemTwo);
        });

    $menuBuilderMock = Mockery::mock('stdClass');

    // Menu facade mocking (calls closures as expected)
    Illuminate\Support\Facades\Menu::shouldReceive('make')
        ->once()
        ->with('main_menu', Mockery::on(function ($closure) use ($provider, $menuBuilderMock) {
            $closure($menuBuilderMock);
            return is_callable($closure);
        }))
        ->andReturn($menuBuilderMock);

    Illuminate\Support\Facades\Menu::shouldReceive('make')
        ->once()
        ->with('setting_menu', Mockery::on(function ($closure) use ($menuBuilderMock) {
            $closure($menuBuilderMock);
            return is_callable($closure);
        }))
        ->andReturn($menuBuilderMock);

    Illuminate\Support\Facades\Menu::shouldReceive('make')
        ->once()
        ->with('customer_portal_menu', Mockery::on(function ($closure) use ($menuBuilderMock) {
            $closure($menuBuilderMock);
            return is_callable($closure);
        }))
        ->andReturn($menuBuilderMock);

    $provider->addMenus();
});

test('it registers setting_menu and calls generateMenu for each item', function () use ($dummyMenuItem) {
    Config::set('crater.main_menu', []);
    Config::set('crater.setting_menu', [$dummyMenuItem]);
    Config::set('crater.customer_menu', []);

    $provider = getPartialMockedServiceProvider();
    $provider->shouldReceive('generateMenu')
        ->once()
        ->withArgs(function ($menu, $data) use ($dummyMenuItem) {
            return $data === $dummyMenuItem;
        });

    $menuBuilderMock = Mockery::mock('stdClass');

    Illuminate\Support\Facades\Menu::shouldReceive('make')
        ->once()
        ->with('main_menu', Mockery::on(function ($closure) use ($menuBuilderMock) {
            $closure($menuBuilderMock);
            return is_callable($closure);
        }))
        ->andReturn($menuBuilderMock);

    Illuminate\Support\Facades\Menu::shouldReceive('make')
        ->once()
        ->with('setting_menu', Mockery::on(function ($closure) use ($provider, $menuBuilderMock) {
            $closure($menuBuilderMock);
            return is_callable($closure);
        }))
        ->andReturn($menuBuilderMock);

    Illuminate\Support\Facades\Menu::shouldReceive('make')
        ->once()
        ->with('customer_portal_menu', Mockery::on(function ($closure) use ($menuBuilderMock) {
            $closure($menuBuilderMock);
            return is_callable($closure);
        }))
        ->andReturn($menuBuilderMock);

    $provider->addMenus();
});

test('it registers customer_portal_menu and calls generateMenu for each item', function () use ($dummyMenuItem) {
    Config::set('crater.main_menu', []);
    Config::set('crater.setting_menu', []);
    Config::set('crater.customer_menu', [$dummyMenuItem]);

    $provider = getPartialMockedServiceProvider();
    $provider->shouldReceive('generateMenu')
        ->once()
        ->withArgs(function ($menu, $data) use ($dummyMenuItem) {
            return $data === $dummyMenuItem;
        });

    $menuBuilderMock = Mockery::mock('stdClass');

    Illuminate\Support\Facades\Menu::shouldReceive('make')
        ->once()
        ->with('main_menu', Mockery::on(function ($closure) use ($menuBuilderMock) {
            $closure($menuBuilderMock);
            return is_callable($closure);
        }))
        ->andReturn($menuBuilderMock);

    Illuminate\Support\Facades\Menu::shouldReceive('make')
        ->once()
        ->with('setting_menu', Mockery::on(function ($closure) use ($menuBuilderMock) {
            $closure($menuBuilderMock);
            return is_callable($closure);
        }))
        ->andReturn($menuBuilderMock);

    Illuminate\Support\Facades\Menu::shouldReceive('make')
        ->once()
        ->with('customer_portal_menu', Mockery::on(function ($closure) use ($provider, $menuBuilderMock) {
            $closure($menuBuilderMock);
            return is_callable($closure);
        }))
        ->andReturn($menuBuilderMock);

    $provider->addMenus();
});

test('addMenus handles empty config menus gracefully', function () {
    Config::set('crater.main_menu', []);
    Config::set('crater.setting_menu', []);
    Config::set('crater.customer_menu', []);

    $provider = getPartialMockedServiceProvider();
    $provider->shouldReceive('generateMenu')->never();

    $menuBuilderMock = Mockery::mock('stdClass');
    Illuminate\Support\Facades\Menu::shouldReceive('make')
        ->times(3)
        ->andReturnUsing(function ($name, $closure) use ($menuBuilderMock) {
            $closure($menuBuilderMock);
            return $menuBuilderMock;
        });

    $provider->addMenus();
});

test('it correctly adds menu item with all data fields', function () {
    $data = [
        'title' => 'New Item',
        'link' => '/new-item',
        'icon' => 'icon-new',
        'name' => 'new-item',
        'owner_only' => true,
        'ability' => 'create_item',
        'model' => 'Item',
        'group' => 'misc',
    ];

    $menuItemMock = Mockery::mock('stdClass');
    $menuItemMock->shouldReceive('data')->with('icon', $data['icon'])->once()->andReturnSelf();
    $menuItemMock->shouldReceive('data')->with('name', $data['name'])->once()->andReturnSelf();
    $menuItemMock->shouldReceive('data')->with('owner_only', $data['owner_only'])->once()->andReturnSelf();
    $menuItemMock->shouldReceive('data')->with('ability', $data['ability'])->once()->andReturnSelf();
    $menuItemMock->shouldReceive('data')->with('model', $data['model'])->once()->andReturnSelf();
    $menuItemMock->shouldReceive('data')->with('group', $data['group'])->once()->andReturnSelf();

    $menuMock = Mockery::mock('stdClass');
    $menuMock->shouldReceive('add')
        ->once()
        ->with($data['title'], $data['link'])
        ->andReturn($menuItemMock);

    $provider = getPartialMockedServiceProvider();

    $provider->generateMenu($menuMock, $data);
});

test('generateMenu correctly handles data with null values', function () {
    $data = [
        'title' => 'Null Item',
        'link' => '/null-item',
        'icon' => null,
        'name' => 'null-item',
        'owner_only' => false,
        'ability' => null,
        'model' => null,
        'group' => 'test',
    ];

    $menuItemMock = Mockery::mock('stdClass');
    $menuItemMock->shouldReceive('data')->with('icon', null)->once()->andReturnSelf();
    $menuItemMock->shouldReceive('data')->with('name', $data['name'])->once()->andReturnSelf();
    $menuItemMock->shouldReceive('data')->with('owner_only', $data['owner_only'])->once()->andReturnSelf();
    $menuItemMock->shouldReceive('data')->with('ability', null)->once()->andReturnSelf();
    $menuItemMock->shouldReceive('data')->with('model', null)->once()->andReturnSelf();
    $menuItemMock->shouldReceive('data')->with('group', $data['group'])->once()->andReturnSelf();

    $menuMock = Mockery::mock('stdClass');
    $menuMock->shouldReceive('add')
        ->once()
        ->with($data['title'], $data['link'])
        ->andReturn($menuItemMock);

    $provider = getPartialMockedServiceProvider();

    $provider->generateMenu($menuMock, $data);
});

afterEach(function () {
    Mockery::close();
});