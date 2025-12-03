<?php

use Crater\Http\Resources\ModuleResource;
use Crater\Models\Module as ModelsModule;
use Crater\Models\Setting;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Nwidart\Modules\Facades\Module;

/*
|--------------------------------------------------------------------------
| Test Case Setup
|--------------------------------------------------------------------------
|
| This section sets up common mocks and configurations needed for the
| ModuleResource tests. This ensures isolation from external dependencies.
|
*/
beforeEach(function () {
    // Ensure Mockery is cleared between tests to prevent mock bleed-through.
    Mockery::close();

    // Mock the Setting model/facade for its static `getSetting` method.
    // A default return value is provided for 'version' to handle cases where
    // a specific version isn't crucial for the test or to prevent errors.
    $this->mock(Setting::class, function (MockInterface $mock) {
        $mock->shouldReceive('getSetting')
             ->with('version')
             ->andReturn('1.0.0') // Default Crater version
             ->byDefault();
    });

    // Mock the Nwidart\Modules\Facades\Module facade.
    // Default behavior is to assume the module does not exist or cannot be found,
    // which simplifies tests not focused on module disablement logic.
    $this->mock(Module::class, function (MockInterface $mock) {
        $mock->shouldReceive('has')
             ->andReturn(false)
             ->byDefault();
        $mock->shouldReceive('find')
             ->andReturn(null)
             ->byDefault();
    });

    // Crater\Models\Module (Eloquent model) static methods like `where()`
    // are typically mocked directly within individual tests due to chaining
    // requirements (e.g., `where()->first()`, `where()->update()`).
    // No global mock here to allow flexible per-test definitions.
});


/*
|--------------------------------------------------------------------------
| checkPurchased() Method Tests
|--------------------------------------------------------------------------
*/

test('checkPurchased returns true if resource is purchased and does not disable module', function () {
    $resource = new ModuleResource((object)['purchased' => true, 'module_name' => 'AnyModule']);

    $result = $resource->checkPurchased();

    expect($result)->toBeTrue();

    // Assert that no module disablement actions were attempted
    Module::shouldNotReceive('has');
    ModelsModule::shouldNotReceive('where');
});

test('checkPurchased returns false and does nothing if not purchased and module does not exist', function () {
    $moduleName = 'NonExistentModule';
    $resource = new ModuleResource((object)['purchased' => false, 'module_name' => $moduleName]);

    // Ensure our Module facade mock is correctly set up for `has`
    Module::shouldReceive('has')->with($moduleName)->once()->andReturn(false);

    $result = $resource->checkPurchased();

    expect($result)->toBeFalse();

    // Assert no further module actions were attempted
    Module::shouldNotReceive('find');
    ModelsModule::shouldNotReceive('where');
});

test('checkPurchased returns false and disables module if not purchased and module exists', function () {
    $moduleName = 'ExistingButUnpurchasedModule';
    $mockNwidartModule = Mockery::mock(stdClass::class);
    $mockNwidartModule->shouldReceive('disable')->once();

    // Override Module facade mock for this specific scenario
    $this->mock(Module::class, function (MockInterface $mock) use ($moduleName, $mockNwidartModule) {
        $mock->shouldReceive('has')->with($moduleName)->once()->andReturn(true);
        $mock->shouldReceive('find')->with($moduleName)->once()->andReturn($mockNwidartModule);
    });

    // Mock ModelsModule::where()->update() for the database update call
    $mockModelsModuleQueryBuilder = Mockery::mock();
    $mockModelsModuleQueryBuilder->shouldReceive('update')->with(['enabled' => false])->once();

    ModelsModule::shouldReceive('where')
        ->with('name', $moduleName)
        ->once()
        ->andReturn($mockModelsModuleQueryBuilder);

    $resource = new ModuleResource((object)['purchased' => false, 'module_name' => $moduleName]);

    $result = $resource->checkPurchased();

    expect($result)->toBeFalse();
});


/*
|--------------------------------------------------------------------------
| getInstalledModuleVersion() Method Tests
|--------------------------------------------------------------------------
*/

test('getInstalledModuleVersion returns version string when installed_module is installed', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => true, 'version' => '1.2.3']
    ]);

    expect($resource->getInstalledModuleVersion())->toBe('1.2.3');
});

test('getInstalledModuleVersion returns null when installed_module is not installed', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => false, 'version' => '1.2.3']
    ]);

    expect($resource->getInstalledModuleVersion())->toBeNull();
});

test('getInstalledModuleVersion returns null when installed_module property is not set', function () {
    $resource = new ModuleResource((object)[]);

    expect($resource->getInstalledModuleVersion())->toBeNull();
});


/*
|--------------------------------------------------------------------------
| getInstalledModuleUpdatedAt() Method Tests
|--------------------------------------------------------------------------
*/

test('getInstalledModuleUpdatedAt returns updated_at timestamp when installed_module is installed', function () {
    $now = now();
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => true, 'updated_at' => $now]
    ]);

    expect($resource->getInstalledModuleUpdatedAt())->toBe($now);
});

test('getInstalledModuleUpdatedAt returns null when installed_module is not installed', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => false, 'updated_at' => now()]
    ]);

    expect($resource->getInstalledModuleUpdatedAt())->toBeNull();
});

test('getInstalledModuleUpdatedAt returns null when installed_module property is not set', function () {
    $resource = new ModuleResource((object)[]);

    expect($resource->getInstalledModuleUpdatedAt())->toBeNull();
});


/*
|--------------------------------------------------------------------------
| moduleInstalled() Method Tests
|--------------------------------------------------------------------------
*/

test('moduleInstalled returns true when installed_module is installed', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => true]
    ]);

    expect($resource->moduleInstalled())->toBeTrue();
});

test('moduleInstalled returns false when installed_module is not installed', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => false]
    ]);

    expect($resource->moduleInstalled())->toBeFalse();
});

test('moduleInstalled returns false when installed_module property is not set', function () {
    $resource = new ModuleResource((object)[]);

    expect($resource->moduleInstalled())->toBeFalse();
});


/*
|--------------------------------------------------------------------------
| moduleEnabled() Method Tests
|--------------------------------------------------------------------------
*/

test('moduleEnabled returns true when installed_module is installed and enabled', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => true, 'enabled' => true]
    ]);

    expect($resource->moduleEnabled())->toBeTrue();
});

test('moduleEnabled returns false when installed_module is installed but not enabled', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => true, 'enabled' => false]
    ]);

    expect($resource->moduleEnabled())->toBeFalse();
});

test('moduleEnabled returns false when installed_module is not installed', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => false, 'enabled' => true]
    ]);

    expect($resource->moduleEnabled())->toBeFalse();
});

test('moduleEnabled returns false when installed_module property is not set', function () {
    $resource = new ModuleResource((object)[]);

    expect($resource->moduleEnabled())->toBeFalse();
});


/*
|--------------------------------------------------------------------------
| updateAvailable() Method Tests
|--------------------------------------------------------------------------
*/

test('updateAvailable returns false if installed_module is not set', function () {
    $resource = new ModuleResource((object)[]);

    expect($resource->updateAvailable())->toBeFalse();
});

test('updateAvailable returns false if installed_module is set but not installed', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => false]
    ]);

    expect($resource->updateAvailable())->toBeFalse();
});

test('updateAvailable returns false if latest_module_version is not set', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => true]
    ]);

    expect($resource->updateAvailable())->toBeFalse();
});

test('updateAvailable returns false if installed_module_version is greater than latest_module_version', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => true, 'version' => '2.0.0'],
        'latest_module_version' => (object)['module_version' => '1.9.0']
    ]);
    expect($resource->updateAvailable())->toBeFalse();
});

test('updateAvailable returns false if installed_module_version is equal to latest_module_version', function () {
    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => true, 'version' => '2.0.0'],
        'latest_module_version' => (object)['module_version' => '2.0.0']
    ]);
    expect($resource->updateAvailable())->toBeFalse();
});

test('updateAvailable returns false if current crater version is less than latest module required crater version', function () {
    // Override default Setting::getSetting('version') for this specific test
    $this->mock(Setting::class, function (MockInterface $mock) {
        $mock->shouldReceive('getSetting')->with('version')->once()->andReturn('1.0.0'); // Current Crater version
    });

    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => true, 'version' => '1.0.0'],
        'latest_module_version' => (object)['module_version' => '1.1.0', 'crater_version' => '1.1.0'] // Module requires 1.1.0
    ]);

    expect($resource->updateAvailable())->toBeFalse();
});

test('updateAvailable returns true if all conditions for an update are met', function () {
    // Override default Setting::getSetting('version') for this specific test
    $this->mock(Setting::class, function (MockInterface $mock) {
        $mock->shouldReceive('getSetting')->with('version')->once()->andReturn('2.0.0'); // Current Crater version
    });

    $resource = new ModuleResource((object)[
        'installed_module' => (object)['installed' => true, 'version' => '1.0.0'],
        'latest_module_version' => (object)['module_version' => '1.1.0', 'crater_version' => '1.5.0'] // Module requires 1.5.0 or higher
    ]);

    expect($resource->updateAvailable())->toBeTrue();
});


/*
|--------------------------------------------------------------------------
| toArray($request) Method Tests
|--------------------------------------------------------------------------
*/

test('toArray transforms resource into an array with all expected data when purchased and installed', function () {
    $moduleName = 'SampleModule';
    $now = now();

    // Mock ModelsModule::where()->first() for the internal `installed_module` property setting
    $mockInstalledModuleResult = (object)[
        'installed' => true,
        'version' => '1.0.0',
        'updated_at' => $now,
        'enabled' => true,
    ];
    $mockModelsModuleQueryForFirst = Mockery::mock();
    $mockModelsModuleQueryForFirst->shouldReceive('first')->once()->andReturn($mockInstalledModuleResult);

    ModelsModule::shouldReceive('where')
        ->with('name', $moduleName)
        ->once()
        ->andReturn($mockModelsModuleQueryForFirst);

    // Override Setting::getSetting('version') for `updateAvailable` logic
    $this->mock(Setting::class, function (MockInterface $mock) {
        $mock->shouldReceive('getSetting')->with('version')->once()->andReturn('2.0.0'); // Current Crater version is high enough
    });

    $resourceData = (object)[
        'id' => 1,
        'average_rating' => 4.5,
        'cover' => 'cover.jpg',
        'slug' => 'sample-module',
        'module_name' => $moduleName,
        'faq' => [['q' => 'Q1', 'a' => 'A1']],
        'highlights' => ['H1', 'H2'],
        'latest_module_version' => (object)['module_version' => '1.1.0', 'created_at' => $now->copy()->addDay(), 'crater_version' => '1.5.0'],
        'is_dev' => false,
        'license' => 'MIT',
        'long_description' => 'Long description here.',
        'monthly_price' => 10.00,
        'name' => 'Sample Module',
        'purchased' => true, // Purchased, so checkPurchased() does not disable anything
        'reviews' => [['rating' => 5, 'comment' => 'Great!']],
        'screenshots' => ['ss1.jpg', 'ss2.jpg'],
        'short_description' => 'Short description.',
        'type' => 'Addon',
        'yearly_price' => 100.00,
        'author' => (object)['name' => 'Author Name', 'avatar' => 'author.jpg'],
        'video_link' => 'video.com',
        'video_thumbnail' => 'video.jpg',
        'links' => [['url' => 'link.com', 'text' => 'Link']]
    ];

    $resource = new ModuleResource($resourceData);
    $request = Request::create('/'); // Dummy request, not used by resource internal logic

    $expected = [
        'id' => 1,
        'average_rating' => 4.5,
        'cover' => 'cover.jpg',
        'slug' => 'sample-module',
        'module_name' => $moduleName,
        'faq' => [['q' => 'Q1', 'a' => 'A1']],
        'highlights' => ['H1', 'H2'],
        'installed_module_version' => '1.0.0',
        'installed_module_version_updated_at' => $now,
        'latest_module_version' => '1.1.0',
        'latest_module_version_updated_at' => $now->copy()->addDay(),
        'is_dev' => false,
        'license' => 'MIT',
        'long_description' => 'Long description here.',
        'monthly_price' => 10.00,
        'name' => 'Sample Module',
        'purchased' => true,
        'reviews' => [['rating' => 5, 'comment' => 'Great!']],
        'screenshots' => ['ss1.jpg', 'ss2.jpg'],
        'short_description' => 'Short description.',
        'type' => 'Addon',
        'yearly_price' => 100.00,
        'author_name' => 'Author Name',
        'author_avatar' => 'author.jpg',
        'installed' => true,
        'enabled' => true,
        'update_available' => true,
        'video_link' => 'video.com',
        'video_thumbnail' => 'video.jpg',
        'links' => [['url' => 'link.com', 'text' => 'Link']]
    ];

    $result = $resource->toArray($request);

    expect($result)->toEqual($expected);
});

test('toArray handles null reviews property by returning an empty array', function () {
    $moduleName = 'AnotherModule';
    $now = now();

    // Mock ModelsModule::where()->first()
    $mockInstalledModuleResult = (object)[
        'installed' => true,
        'version' => '1.0.0',
        'updated_at' => $now,
        'enabled' => false, // Installed but not enabled
    ];
    $mockModelsModuleQueryForFirst = Mockery::mock();
    $mockModelsModuleQueryForFirst->shouldReceive('first')->once()->andReturn($mockInstalledModuleResult);

    ModelsModule::shouldReceive('where')
        ->with('name', $moduleName)
        ->once()
        ->andReturn($mockModelsModuleQueryForFirst);

    // Override Setting::getSetting('version') for `updateAvailable` to make it false
    $this->mock(Setting::class, function (MockInterface $mock) {
        $mock->shouldReceive('getSetting')->with('version')->once()->andReturn('1.0.0'); // Current version too low for update
    });

    $resourceData = (object)[
        'id' => 2,
        'average_rating' => 3.0,
        'cover' => null,
        'slug' => 'another-module',
        'module_name' => $moduleName,
        'faq' => [],
        'highlights' => [],
        'latest_module_version' => (object)['module_version' => '1.0.0', 'created_at' => $now->copy()->subDay(), 'crater_version' => '1.1.0'],
        'is_dev' => true,
        'license' => 'GPL',
        'long_description' => null,
        'monthly_price' => null,
        'name' => 'Another Module',
        'purchased' => false, // Not purchased, and Module::has() is false by default, so no disable call
        'reviews' => null, // The specific test case here
        'screenshots' => [],
        'short_description' => 'Another short description.',
        'type' => 'Theme',
        'yearly_price' => null,
        'author' => (object)['name' => 'Another Author', 'avatar' => null],
        'video_link' => null,
        'video_thumbnail' => null,
        'links' => []
    ];

    $resource = new ModuleResource($resourceData);
    $request = Request::create('/');

    $expected = [
        'id' => 2,
        'average_rating' => 3.0,
        'cover' => null,
        'slug' => 'another-module',
        'module_name' => $moduleName,
        'faq' => [],
        'highlights' => [],
        'installed_module_version' => '1.0.0',
        'installed_module_version_updated_at' => $now,
        'latest_module_version' => '1.0.0',
        'latest_module_version_updated_at' => $now->copy()->subDay(),
        'is_dev' => true,
        'license' => 'GPL',
        'long_description' => null,
        'monthly_price' => null,
        'name' => 'Another Module',
        'purchased' => false,
        'reviews' => [], // Should be an empty array
        'screenshots' => [],
        'short_description' => 'Another short description.',
        'type' => 'Theme',
        'yearly_price' => null,
        'author_name' => 'Another Author',
        'author_avatar' => null,
        'installed' => true,
        'enabled' => false,
        'update_available' => false,
        'video_link' => null,
        'video_thumbnail' => null,
        'links' => []
    ];

    $result = $resource->toArray($request);

    expect($result)->toEqual($expected);
});

test('toArray correctly processes unpurchased, existing module by disabling it', function () {
    $moduleName = 'UnpurchasedExistingModule';
    $now = now();

    // Mock ModelsModule::where() for both `update` (from checkPurchased) and `first` (from toArray)
    $mockModelsModuleQueryForUpdate = Mockery::mock();
    $mockModelsModuleQueryForUpdate->shouldReceive('update')->with(['enabled' => false])->once();

    $mockInstalledModuleResult = (object)[
        'installed' => true,
        'version' => '1.0.0',
        'updated_at' => $now,
        'enabled' => false, // This reflects the state AFTER being disabled
    ];
    $mockModelsModuleQueryForFirst = Mockery::mock();
    $mockModelsModuleQueryForFirst->shouldReceive('first')->once()->andReturn($mockInstalledModuleResult);

    // Use named ordering to ensure the two `where` calls are handled sequentially
    ModelsModule::shouldReceive('where')
        ->with('name', $moduleName)
        ->once()
        ->andReturn($mockModelsModuleQueryForUpdate)
        ->ordered('models_module_calls');

    ModelsModule::shouldReceive('where')
        ->with('name', $moduleName)
        ->once()
        ->andReturn($mockModelsModuleQueryForFirst)
        ->ordered('models_module_calls');

    // Mock Nwidart\Modules facade to simulate module existence and disablement
    $mockNwidartModule = Mockery::mock(stdClass::class);
    $mockNwidartModule->shouldReceive('disable')->once();
    $this->mock(Module::class, function (MockInterface $mock) use ($moduleName, $mockNwidartModule) {
        $mock->shouldReceive('has')->with($moduleName)->once()->andReturn(true);
        $mock->shouldReceive('find')->with($moduleName)->once()->andReturn($mockNwidartModule);
    });

    // Override Setting::getSetting('version') for `updateAvailable` logic
    $this->mock(Setting::class, function (MockInterface $mock) {
        $mock->shouldReceive('getSetting')->with('version')->once()->andReturn('2.0.0');
    });

    $resourceData = (object)[
        'id' => 3,
        'average_rating' => 4.0,
        'cover' => 'unpurchased.jpg',
        'slug' => 'unpurchased-existing-module',
        'module_name' => $moduleName,
        'faq' => [],
        'highlights' => [],
        'latest_module_version' => (object)['module_version' => '1.1.0', 'created_at' => $now->copy()->addDay(), 'crater_version' => '1.5.0'],
        'is_dev' => false,
        'license' => 'Proprietary',
        'long_description' => 'Unpurchased module description.',
        'monthly_price' => 5.00,
        'name' => 'Unpurchased Existing Module',
        'purchased' => false, // This triggers the disable logic in checkPurchased()
        'reviews' => [],
        'screenshots' => [],
        'short_description' => 'Unpurchased short description.',
        'type' => 'Addon',
        'yearly_price' => 50.00,
        'author' => (object)['name' => 'Module Author', 'avatar' => 'author.png'],
        'video_link' => null,
        'video_thumbnail' => null,
        'links' => []
    ];

    $resource = new ModuleResource($resourceData);
    $request = Request::create('/');

    $expected = [
        'id' => 3,
        'average_rating' => 4.0,
        'cover' => 'unpurchased.jpg',
        'slug' => 'unpurchased-existing-module',
        'module_name' => $moduleName,
        'faq' => [],
        'highlights' => [],
        'installed_module_version' => '1.0.0',
        'installed_module_version_updated_at' => $now,
        'latest_module_version' => '1.1.0',
        'latest_module_version_updated_at' => $now->copy()->addDay(),
        'is_dev' => false,
        'license' => 'Proprietary',
        'long_description' => 'Unpurchased module description.',
        'monthly_price' => 5.00,
        'name' => 'Unpurchased Existing Module',
        'purchased' => false,
        'reviews' => [],
        'screenshots' => [],
        'short_description' => 'Unpurchased short description.',
        'type' => 'Addon',
        'yearly_price' => 50.00,
        'author_name' => 'Module Author',
        'author_avatar' => 'author.png',
        'installed' => true,
        'enabled' => false, // Expected to be false due to disable logic
        'update_available' => true,
        'video_link' => null,
        'video_thumbnail' => null,
        'links' => []
    ];

    $result = $resource->toArray($request);

    expect($result)->toEqual($expected);
});

test('toArray handles case where installed_module is not found (null result from ModelsModule::where()->first())', function () {
    $moduleName = 'NonFoundModule';
    $now = now();

    // Mock ModelsModule::where()->first() to return null, simulating module not found
    $mockModelsModuleQueryForFirst = Mockery::mock();
    $mockModelsModuleQueryForFirst->shouldReceive('first')->once()->andReturn(null);

    ModelsModule::shouldReceive('where')
        ->with('name', $moduleName)
        ->once()
        ->andReturn($mockModelsModuleQueryForFirst);

    // Override Setting::getSetting('version') (not strictly necessary as updateAvailable will be false)
    $this->mock(Setting::class, function (MockInterface $mock) {
        $mock->shouldReceive('getSetting')->with('version')->andReturn('1.0.0');
    });

    $resourceData = (object)[
        'id' => 4,
        'average_rating' => 0,
        'cover' => null,
        'slug' => 'non-found-module',
        'module_name' => $moduleName,
        'faq' => [],
        'highlights' => [],
        'latest_module_version' => (object)['module_version' => '1.0.0', 'created_at' => $now->copy()->subWeek(), 'crater_version' => '1.0.0'],
        'is_dev' => false,
        'license' => null,
        'long_description' => null,
        'monthly_price' => null,
        'name' => 'Non Found Module',
        'purchased' => false,
        'reviews' => [],
        'screenshots' => [],
        'short_description' => null,
        'type' => 'Core',
        'yearly_price' => null,
        'author' => (object)['name' => 'System', 'avatar' => null],
        'video_link' => null,
        'video_thumbnail' => null,
        'links' => []
    ];

    $resource = new ModuleResource($resourceData);
    $request = Request::create('/');

    $expected = [
        'id' => 4,
        'average_rating' => 0,
        'cover' => null,
        'slug' => 'non-found-module',
        'module_name' => $moduleName,
        'faq' => [],
        'highlights' => [],
        'installed_module_version' => null, // Should be null because installed_module is null
        'installed_module_version_updated_at' => null, // Should be null
        'latest_module_version' => '1.0.0',
        'latest_module_version_updated_at' => $now->copy()->subWeek(),
        'is_dev' => false,
        'license' => null,
        'long_description' => null,
        'monthly_price' => null,
        'name' => 'Non Found Module',
        'purchased' => false,
        'reviews' => [],
        'screenshots' => [],
        'short_description' => null,
        'type' => 'Core',
        'yearly_price' => null,
        'author_name' => 'System',
        'author_avatar' => null,
        'installed' => false, // Should be false
        'enabled' => false, // Should be false
        'update_available' => false, // Should be false
        'video_link' => null,
        'video_thumbnail' => null,
        'links' => []
    ];

    $result = $resource->toArray($request);

    expect($result)->toEqual($expected);
});



afterEach(function () {
    Mockery::close();
});
