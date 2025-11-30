<?php

use Carbon\Carbon;
use Crater\Models\FileDisk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

beforeEach(function () {
    // Ensure all mocks are cleaned up between tests
    Mockery::close();
});

test('setCredentialsAttribute encodes and sets credentials attribute', function () {
    $fileDisk = new FileDisk();
    $credentialsArray = ['key' => 'value', 'other_key' => 123];

    // Use reflection to access the protected 'attributes' property
    $reflection = new ReflectionClass($fileDisk);
    $property = $reflection->getProperty('attributes');
    $property->setAccessible(true);

    $fileDisk->setCredentialsAttribute($credentialsArray);

    $attributes = $property->getValue($fileDisk);
    expect($attributes['credentials'])->toBe(json_encode($credentialsArray));
});

test('scopeWhereOrder applies orderBy to the query', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $orderByField = 'name';
    $orderBy = 'desc';

    $mockQuery->shouldReceive('orderBy')
        ->once()
        ->with($orderByField, $orderBy)
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeWhereOrder($mockQuery, $orderByField, $orderBy);
});

test('scopeFileDisksBetween applies whereBetween to the query', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $startDate = Carbon::parse('2023-01-01');
    $endDate = Carbon::parse('2023-01-31');

    $mockQuery->shouldReceive('whereBetween')
        ->once()
        ->with('file_disks.created_at', ['2023-01-01', '2023-01-31'])
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $result = $fileDisk->scopeFileDisksBetween($mockQuery, $startDate, $endDate);

    expect($result)->toBe($mockQuery);
});

test('scopeWhereSearch applies where and orWhere for a single term', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $searchTerm = 'searchterm';

    $mockQuery->shouldReceive('where')
        ->once()
        ->with('name', 'LIKE', '%searchterm%')
        ->andReturnSelf();

    $mockQuery->shouldReceive('orWhere')
        ->once()
        ->with('driver', 'LIKE', '%searchterm%')
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeWhereSearch($mockQuery, $searchTerm);
});

test('scopeWhereSearch applies where and orWhere for multiple terms', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $searchTerm = 'term1 term2';

    // Expectations for 'term1'
    $mockQuery->shouldReceive('where')
        ->once()
        ->with('name', 'LIKE', '%term1%')
        ->andReturnSelf();
    $mockQuery->shouldReceive('orWhere')
        ->once()
        ->with('driver', 'LIKE', '%term1%')
        ->andReturnSelf();

    // Expectations for 'term2'
    $mockQuery->shouldReceive('where')
        ->once()
        ->with('name', 'LIKE', '%term2%')
        ->andReturnSelf();
    $mockQuery->shouldReceive('orWhere')
        ->once()
        ->with('driver', 'LIKE', '%term2%')
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeWhereSearch($mockQuery, $searchTerm);
});

test('scopePaginateData calls get when limit is all', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $expectedCollection = new Collection(['item1', 'item2']);

    $mockQuery->shouldReceive('get')
        ->once()
        ->andReturn($expectedCollection);

    $fileDisk = new FileDisk();
    $result = $fileDisk->scopePaginateData($mockQuery, 'all');

    expect($result)->toBe($expectedCollection);
});

test('scopePaginateData calls paginate when limit is a number', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $limit = 10;
    $expectedPaginator = Mockery::mock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

    $mockQuery->shouldReceive('paginate')
        ->once()
        ->with($limit)
        ->andReturn($expectedPaginator);

    $fileDisk = new FileDisk();
    $result = $fileDisk->scopePaginateData($mockQuery, $limit);

    expect($result)->toBe($expectedPaginator);
});

test('scopeApplyFilters applies search filter', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $filters = ['search' => 'test'];

    $mockQuery->shouldReceive('whereSearch')
        ->once()
        ->with('test')
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeApplyFilters($mockQuery, $filters);
});

test('scopeApplyFilters applies date range filter', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $filters = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ];

    // Mock Carbon::createFromFormat to return mock Carbon instances
    Mockery::mock('alias:'.Carbon::class)
        ->shouldReceive('createFromFormat')
        ->with('Y-m-d', '2023-01-01')
        ->andReturn(Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('2023-01-01')->getMock())
        ->getMock();
    Mockery::mock('alias:'.Carbon::class)
        ->shouldReceive('createFromFormat')
        ->with('Y-m-d', '2023-01-31')
        ->andReturn(Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('2023-01-31')->getMock())
        ->getMock();

    $mockQuery->shouldReceive('fileDisksBetween')
        ->once()
        ->withArgs(function ($start, $end) {
            return $start instanceof Carbon && $end instanceof Carbon;
        })
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeApplyFilters($mockQuery, $filters);
});

test('scopeApplyFilters applies order filter with field and order', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $filters = [
        'orderByField' => 'custom_field',
        'orderBy' => 'desc',
    ];

    $mockQuery->shouldReceive('whereOrder')
        ->once()
        ->with('custom_field', 'desc')
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeApplyFilters($mockQuery, $filters);
});

test('scopeApplyFilters applies order filter with only orderByField, uses default order', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $filters = ['orderByField' => 'custom_field'];

    $mockQuery->shouldReceive('whereOrder')
        ->once()
        ->with('custom_field', 'asc') // Default 'asc'
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeApplyFilters($mockQuery, $filters);
});

test('scopeApplyFilters applies order filter with only orderBy, uses default field', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $filters = ['orderBy' => 'desc'];

    $mockQuery->shouldReceive('whereOrder')
        ->once()
        ->with('sequence_number', 'desc') // Default 'sequence_number'
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeApplyFilters($mockQuery, $filters);
});

test('scopeApplyFilters applies no filters if none are provided', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $filters = [];

    $mockQuery->shouldNotReceive('whereSearch');
    $mockQuery->shouldNotReceive('fileDisksBetween');
    $mockQuery->shouldNotReceive('whereOrder');

    $fileDisk = new FileDisk();
    $fileDisk->scopeApplyFilters($mockQuery, $filters);
});

test('setConfig calls setFilesystem with correct credentials and driver', function () {
    $fileDisk = Mockery::mock(FileDisk::class)->makePartial();
    $fileDisk->driver = 's3';
    $fileDisk->credentials = json_encode(['access' => 'key', 'secret' => 'value']);

    // Mock the static method `setFilesystem` on the class under test
    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('setFilesystem')
        ->once()
        ->withArgs(function ($credentials, $driver) {
            return $credentials instanceof Collection &&
                $credentials->toArray() === ['access' => 'key', 'secret' => 'value'] &&
                $driver === 's3';
        });

    $fileDisk->setConfig();
});

test('setAsDefault returns the set_as_default attribute', function () {
    $fileDisk = new FileDisk();
    $fileDisk->set_as_default = true;
    expect($fileDisk->setAsDefault())->toBeTrue();

    $fileDisk->set_as_default = false;
    expect($fileDisk->setAsDefault())->toBeFalse();
});

test('setFilesystem configures default disk and merges credentials', function () {
    $credentials = new Collection(['key1' => 'val1', 'key3' => 'val3_new']);
    $driver = 'test_driver';
    $prefix = 'my_prefix_';

    // Mock env() helper
    Mockery::mock('alias:Illuminate\Support\Facades\App')
        ->shouldReceive('runningInConsole')
        ->andReturn(false); // To prevent issues with Pest's env helper overriding Mockery
    $_ENV['DYNAMIC_DISK_PREFIX'] = $prefix;

    // Mock Config::get() for initial disks
    Config::shouldReceive('get')
        ->with('filesystems.disks.' . $driver)
        ->andReturn([
            'key1' => 'default_val1',
            'key2' => 'default_val2',
            'key3' => 'default_val3',
        ]);

    // Expectations for Config::set()
    Config::shouldReceive('set')
        ->with('filesystems.default', $prefix . $driver)
        ->once();
    Config::shouldReceive('set')
        ->with('filesystems.disks.' . $prefix . $driver, [
            'key1' => 'val1', // Overridden
            'key2' => 'default_val2', // Kept
            'key3' => 'val3_new', // Overridden
        ])
        ->once();

    FileDisk::setFilesystem($credentials, $driver);

    unset($_ENV['DYNAMIC_DISK_PREFIX']); // Clean up global env
});

test('setFilesystem handles empty credentials correctly', function () {
    $credentials = new Collection([]);
    $driver = 'test_driver';
    $prefix = 'my_prefix_';

    $_ENV['DYNAMIC_DISK_PREFIX'] = $prefix;

    Config::shouldReceive('get')
        ->with('filesystems.disks.' . $driver)
        ->andReturn([
            'key1' => 'default_val1',
            'key2' => 'default_val2',
        ]);

    Config::shouldReceive('set')
        ->with('filesystems.default', $prefix . $driver)
        ->once();
    Config::shouldReceive('set')
        ->with('filesystems.disks.' . $prefix . $driver, [
            'key1' => 'default_val1',
            'key2' => 'default_val2',
        ])
        ->once();

    FileDisk::setFilesystem($credentials, $driver);

    unset($_ENV['DYNAMIC_DISK_PREFIX']);
});

test('validateCredentials returns true on successful validation', function () {
    $credentials = ['token' => 'test_token'];
    $disk = 's3';
    $prefix = 'temp_';

    $_ENV['DYNAMIC_DISK_PREFIX'] = $prefix;

    // Mock the static method `setFilesystem`
    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('setFilesystem')
        ->once()
        ->withArgs(function ($creds, $driver) use ($credentials, $disk) {
            return $creds instanceof Collection && $creds->toArray() === $credentials && $driver === $disk;
        });

    // Mock Storage facade
    Storage::shouldReceive('disk')
        ->with($prefix . $disk)
        ->andReturnSelf(); // Allow chaining .put()
    Storage::shouldReceive('put')
        ->with('crater_temp.text', 'Check Credentials')
        ->once()
        ->andReturn(true);
    Storage::shouldReceive('exists')
        ->with('crater_temp.text')
        ->once()
        ->andReturn(true);
    Storage::shouldReceive('delete')
        ->with('crater_temp.text')
        ->once()
        ->andReturn(true);

    $result = FileDisk::validateCredentials($credentials, $disk);
    expect($result)->toBeTrue();

    unset($_ENV['DYNAMIC_DISK_PREFIX']);
});

test('validateCredentials returns false on exception', function () {
    $credentials = ['token' => 'test_token'];
    $disk = 's3';
    $prefix = 'temp_';

    $_ENV['DYNAMIC_DISK_PREFIX'] = $prefix;

    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('setFilesystem')
        ->once();

    Storage::shouldReceive('disk')
        ->with($prefix . $disk)
        ->andReturnSelf();
    Storage::shouldReceive('put')
        ->andThrow(new Exception('Failed to put file')); // Simulate a failure

    $result = FileDisk::validateCredentials($credentials, $disk);
    expect($result)->toBeFalse();

    unset($_ENV['DYNAMIC_DISK_PREFIX']);
});

test('validateCredentials returns false if put succeeds but exists fails', function () {
    $credentials = ['token' => 'test_token'];
    $disk = 's3';
    $prefix = 'temp_';

    $_ENV['DYNAMIC_DISK_PREFIX'] = $prefix;

    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('setFilesystem')
        ->once();

    Storage::shouldReceive('disk')
        ->with($prefix . $disk)
        ->andReturnSelf();
    Storage::shouldReceive('put')
        ->with('crater_temp.text', 'Check Credentials')
        ->once()
        ->andReturn(true);
    Storage::shouldReceive('exists')
        ->with('crater_temp.text')
        ->once()
        ->andReturn(false); // File does not exist after put
    Storage::shouldNotReceive('delete'); // Delete should not be called if exists is false

    $result = FileDisk::validateCredentials($credentials, $disk);
    expect($result)->toBeFalse();

    unset($_ENV['DYNAMIC_DISK_PREFIX']);
});

test('validateCredentials handles dropbox root path correctly', function () {
    $credentials = ['token' => 'test_token', 'root' => '/my/app/path'];
    $disk = 'dropbox';
    $prefix = 'temp_';
    $expectedRoot = '/my/app/path/'; // dropbox specific root with trailing slash

    $_ENV['DYNAMIC_DISK_PREFIX'] = $prefix;

    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('setFilesystem')
        ->once();

    Storage::shouldReceive('disk')
        ->with($prefix . $disk)
        ->andReturnSelf();
    Storage::shouldReceive('put')
        ->with($expectedRoot . 'crater_temp.text', 'Check Credentials')
        ->once()
        ->andReturn(true);
    Storage::shouldReceive('exists')
        ->with($expectedRoot . 'crater_temp.text')
        ->once()
        ->andReturn(true);
    Storage::shouldReceive('delete')
        ->with($expectedRoot . 'crater_temp.text')
        ->once()
        ->andReturn(true);

    $result = FileDisk::validateCredentials($credentials, $disk);
    expect($result)->toBeTrue();

    unset($_ENV['DYNAMIC_DISK_PREFIX']);
});

test('createDisk updates default disks if set_as_default is true', function () {
    $request = Mockery::mock(Request::class);
    $request->set_as_default = true;
    $request->credentials = ['cred_key' => 'cred_val'];
    $request->name = 'My Disk';
    $request->driver = 'local';
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn(1);

    $createdDisk = Mockery::mock(FileDisk::class);

    // Mock static `updateDefaultDisks`
    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('updateDefaultDisks')
        ->once()
        ->andReturn(true);

    // Mock static `create`
    FileDisk::shouldReceive('create')
        ->once()
        ->with([
            'credentials' => $request->credentials,
            'name' => $request->name,
            'driver' => $request->driver,
            'set_as_default' => $request->set_as_default,
            'company_id' => 1,
        ])
        ->andReturn($createdDisk);

    $result = FileDisk::createDisk($request);

    expect($result)->toBe($createdDisk);
});

test('createDisk does not update default disks if set_as_default is false', function () {
    $request = Mockery::mock(Request::class);
    $request->set_as_default = false;
    $request->credentials = ['cred_key' => 'cred_val'];
    $request->name = 'My Disk';
    $request->driver = 'local';
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn(1);

    $createdDisk = Mockery::mock(FileDisk::class);

    // Mock static `updateDefaultDisks`
    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('updateDefaultDisks')
        ->never();

    // Mock static `create`
    FileDisk::shouldReceive('create')
        ->once()
        ->with([
            'credentials' => $request->credentials,
            'name' => $request->name,
            'driver' => $request->driver,
            'set_as_default' => $request->set_as_default,
            'company_id' => 1,
        ])
        ->andReturn($createdDisk);

    $result = FileDisk::createDisk($request);

    expect($result)->toBe($createdDisk);
});

test('updateDefaultDisks sets set_as_default to false and saves for all disks', function () {
    $disk1 = Mockery::mock(FileDisk::class);
    $disk1->set_as_default = true;
    $disk1->shouldReceive('save')->once();
    $disk1->shouldReceive('setAttribute')->with('set_as_default', false)->once()->andSet('set_as_default', false);

    $disk2 = Mockery::mock(FileDisk::class);
    $disk2->set_as_default = true;
    $disk2->shouldReceive('save')->once();
    $disk2->shouldReceive('setAttribute')->with('set_as_default', false)->once()->andSet('set_as_default', false);

    $disksCollection = new Collection([$disk1, $disk2]);

    // Mock static `get` method
    FileDisk::shouldReceive('get')
        ->once()
        ->andReturn($disksCollection);

    $result = FileDisk::updateDefaultDisks();

    expect($result)->toBeTrue();
    // Verify properties were set on mocks
    expect($disk1->set_as_default)->toBeFalse();
    expect($disk2->set_as_default)->toBeFalse();
});

test('updateDefaultDisks handles no disks gracefully', function () {
    $disksCollection = new Collection([]);

    FileDisk::shouldReceive('get')
        ->once()
        ->andReturn($disksCollection);

    $result = FileDisk::updateDefaultDisks();

    expect($result)->toBeTrue();
});

test('updateDisk updates disk without changing set_as_default if it is already default', function () {
    $fileDisk = Mockery::mock(FileDisk::class)->makePartial();
    $fileDisk->shouldAllowMockingProtectedMethods(); // For setAsDefault, which accesses a property

    $request = Mockery::mock(Request::class);
    $request->credentials = ['new_cred' => 'val'];
    $request->name = 'Updated Name';
    $request->driver = 's3';
    $request->set_as_default = false; // Request says false, but current is default

    $fileDisk->shouldReceive('setAsDefault')
        ->once()
        ->andReturn(true); // Current disk is default

    $fileDisk->shouldReceive('updateDefaultDisks')->never(); // Should not be called
    $fileDisk->shouldReceive('update')
        ->once()
        ->with([
            'credentials' => $request->credentials,
            'name' => $request->name,
            'driver' => $request->driver,
        ]); // set_as_default should NOT be in data if current is default

    $result = $fileDisk->updateDisk($request);

    expect($result)->toBe($fileDisk);
});

test('updateDisk updates disk and updates default disks if new set_as_default is true', function () {
    $fileDisk = Mockery::mock(FileDisk::class)->makePartial();
    $fileDisk->shouldAllowMockingProtectedMethods();

    $request = Mockery::mock(Request::class);
    $request->credentials = ['new_cred' => 'val'];
    $request->name = 'Updated Name';
    $request->driver = 's3';
    $request->set_as_default = true; // Request says true

    $fileDisk->shouldReceive('setAsDefault')
        ->once()
        ->andReturn(false); // Current disk is NOT default

    // Mock the static method `updateDefaultDisks`
    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('updateDefaultDisks')
        ->once()
        ->andReturn(true);

    $fileDisk->shouldReceive('update')
        ->once()
        ->with([
            'credentials' => $request->credentials,
            'name' => $request->name,
            'driver' => $request->driver,
            'set_as_default' => true,
        ]);

    $result = $fileDisk->updateDisk($request);

    expect($result)->toBe($fileDisk);
});

test('updateDisk updates disk and sets set_as_default to false if new set_as_default is false', function () {
    $fileDisk = Mockery::mock(FileDisk::class)->makePartial();
    $fileDisk->shouldAllowMockingProtectedMethods();

    $request = Mockery::mock(Request::class);
    $request->credentials = ['new_cred' => 'val'];
    $request->name = 'Updated Name';
    $request->driver = 's3';
    $request->set_as_default = false; // Request says false

    $fileDisk->shouldReceive('setAsDefault')
        ->once()
        ->andReturn(false); // Current disk is NOT default

    $fileDisk->shouldReceive('updateDefaultDisks')->never(); // Not called if $request->set_as_default is false

    $fileDisk->shouldReceive('update')
        ->once()
        ->with([
            'credentials' => $request->credentials,
            'name' => $request->name,
            'driver' => $request->driver,
            'set_as_default' => false,
        ]);

    $result = $fileDisk->updateDisk($request);

    expect($result)->toBe($fileDisk);
});

test('setAsDefaultDisk updates default disks, sets itself as default and saves', function () {
    $fileDisk = Mockery::mock(FileDisk::class)->makePartial();
    $fileDisk->set_as_default = false; // Initial state

    // Mock static `updateDefaultDisks`
    Mockery::mock('alias:'.FileDisk::class)
        ->shouldReceive('updateDefaultDisks')
        ->once()
        ->andReturn(true);

    $fileDisk->shouldReceive('setAttribute')->with('set_as_default', true)->once()->andSet('set_as_default', true);
    $fileDisk->shouldReceive('save')->once();

    $result = $fileDisk->setAsDefaultDisk();

    expect($result)->toBe($fileDisk);
    expect($fileDisk->set_as_default)->toBeTrue();
});

test('isSystem returns true if type is SYSTEM', function () {
    $fileDisk = new FileDisk();
    $fileDisk->type = FileDisk::DISK_TYPE_SYSTEM;
    expect($fileDisk->isSystem())->toBeTrue();
});

test('isSystem returns false if type is not SYSTEM', function () {
    $fileDisk = new FileDisk();
    $fileDisk->type = FileDisk::DISK_TYPE_REMOTE;
    expect($fileDisk->isSystem())->toBeFalse();

    $fileDisk->type = 'OTHER';
    expect($fileDisk->isSystem())->toBeFalse();
});

test('isRemote returns true if type is REMOTE', function () {
    $fileDisk = new FileDisk();
    $fileDisk->type = FileDisk::DISK_TYPE_REMOTE;
    expect($fileDisk->isRemote())->toBeTrue();
});

test('isRemote returns false if type is not REMOTE', function () {
    $fileDisk = new FileDisk();
    $fileDisk->type = FileDisk::DISK_TYPE_SYSTEM;
    expect($fileDisk->isRemote())->toBeFalse();

    $fileDisk->type = 'OTHER';
    expect($fileDisk->isRemote())->toBeFalse();
});
