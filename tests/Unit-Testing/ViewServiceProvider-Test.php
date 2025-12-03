<?php

/** @var callable|null $getAppSettingMockCallback */
$getAppSettingMockCallback = null;

if (!function_exists('get_app_setting')) {
    function get_app_setting(string $key)
    {
        global $getAppSettingMockCallback;
        if (is_callable($getAppSettingMockCallback)) {
            return $getAppSettingMockCallback($key);
        }
        throw new \BadMethodCallException("Global function get_app_setting was called without a mock defined in the test context.");
    }
}

use Crater\Providers\ViewServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mockery\MockInterface;


test('register method does nothing', function () {
    $provider = new ViewServiceProvider(app());
    $provider->register();
    expect(true)->toBeTrue();
});

test('boot method shares view data when all conditions are met', function () {
    global $getAppSettingMockCallback;

    Storage::shouldReceive('disk')
        ->once()
        ->with('local')
        ->andReturn(
            Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class, function (MockInterface $mock) {
                $mock->shouldReceive('has')
                    ->once()
                    ->with('database_created')
                    ->andReturn(true);
            })
        );

    Schema::shouldReceive('hasTable')
        ->once()
        ->with('settings')
        ->andReturn(true);

    $expectedSettings = [
        'login_page_logo'       => 'test-logo.png',
        'login_page_heading'    => 'Test Login Heading',
        'login_page_description' => 'Test Login Description',
        'admin_page_title'      => 'Test Admin Title',
        'copyright_text'        => 'Test Copyright Text 2023',
    ];

    $getAppSettingMockCallback = function (string $key) use ($expectedSettings) {
        return $expectedSettings[$key] ?? null;
    };

    View::shouldReceive('share')
        ->once()->with('login_page_logo', $expectedSettings['login_page_logo']);
    View::shouldReceive('share')
        ->once()->with('login_page_heading', $expectedSettings['login_page_heading']);
    View::shouldReceive('share')
        ->once()->with('login_page_description', $expectedSettings['login_page_description']);
    View::shouldReceive('share')
        ->once()->with('admin_page_title', $expectedSettings['admin_page_title']);
    View::shouldReceive('share')
        ->once()->with('copyright_text', $expectedSettings['copyright_text']);

    $provider = new ViewServiceProvider(app());
    $provider->boot();

    $getAppSettingMockCallback = null;
});

test('boot method does not share view data when database_created file is missing', function () {
    global $getAppSettingMockCallback;

    Storage::shouldReceive('disk')
        ->once()
        ->with('local')
        ->andReturn(
            Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class, function (MockInterface $mock) {
                $mock->shouldReceive('has')
                    ->once()
                    ->with('database_created')
                    ->andReturn(false);
            })
        );

    Schema::shouldReceive('hasTable')
        ->never();

    View::shouldReceive('share')
        ->never();

    $getAppSettingMockCallback = function (string $key) {
        throw new \BadMethodCallException("get_app_setting should not be called in this test case.");
    };

    $provider = new ViewServiceProvider(app());
    $provider->boot();

    $getAppSettingMockCallback = null;
});

test('boot method does not share view data when settings table is missing', function () {
    global $getAppSettingMockCallback;

    Storage::shouldReceive('disk')
        ->once()
        ->with('local')
        ->andReturn(
            Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class, function (MockInterface $mock) {
                $mock->shouldReceive('has')
                    ->once()
                    ->with('database_created')
                    ->andReturn(true);
            })
        );

    Schema::shouldReceive('hasTable')
        ->once()
        ->with('settings')
        ->andReturn(false);

    View::shouldReceive('share')
        ->never();

    $getAppSettingMockCallback = function (string $key) {
        throw new \BadMethodCallException("get_app_setting should not be called in this test case.");
    };

    $provider = new ViewServiceProvider(app());
    $provider->boot();

    $getAppSettingMockCallback = null;
});

test('boot method does not share view data when both conditions are false', function () {
    global $getAppSettingMockCallback;

    Storage::shouldReceive('disk')
        ->once()
        ->with('local')
        ->andReturn(
            Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class, function (MockInterface $mock) {
                $mock->shouldReceive('has')
                    ->once()
                    ->with('database_created')
                    ->andReturn(false);
            })
        );

    Schema::shouldReceive('hasTable')
        ->never();

    View::shouldReceive('share')
        ->never();

    $getAppSettingMockCallback = function (string $key) {
        throw new \BadMethodCallException("get_app_setting should not be called in this test case.");
    };

    $provider = new ViewServiceProvider(app());
    $provider->boot();

    $getAppSettingMockCallback = null;
});




afterEach(function () {
    Mockery::close();
});
