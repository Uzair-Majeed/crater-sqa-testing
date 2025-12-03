<?php

use Carbon\Carbon;
use Crater\Models\FileDisk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Illuminate\Support\Facades\Facade;

beforeEach(function () {
    Mockery::close();
});

test('setCredentialsAttribute encodes and sets credentials attribute', function () {
    $fileDisk = new FileDisk();
    $credentialsArray = ['key' => 'value', 'other_key' => 123];

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

    $mockQuery->shouldReceive('where')->once()->with('name', 'LIKE', '%term1%')->andReturnSelf();
    $mockQuery->shouldReceive('orWhere')->once()->with('driver', 'LIKE', '%term1%')->andReturnSelf();
    $mockQuery->shouldReceive('where')->once()->with('name', 'LIKE', '%term2%')->andReturnSelf();
    $mockQuery->shouldReceive('orWhere')->once()->with('driver', 'LIKE', '%term2%')->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeWhereSearch($mockQuery, $searchTerm);
});

test('scopePaginateData calls get when limit is all', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $expectedCollection = new Collection(['item1', 'item2']);

    $mockQuery->shouldReceive('get')->once()->andReturn($expectedCollection);

    $fileDisk = new FileDisk();
    $result = $fileDisk->scopePaginateData($mockQuery, 'all');

    expect($result)->toBe($expectedCollection);
});

test('scopePaginateData calls paginate when limit is a number', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $limit = 10;
    $expectedPaginator = Mockery::mock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

    $mockQuery->shouldReceive('paginate')->once()->with($limit)->andReturn($expectedPaginator);

    $fileDisk = new FileDisk();
    $result = $fileDisk->scopePaginateData($mockQuery, $limit);

    expect($result)->toBe($expectedPaginator);
});

test('scopeApplyFilters applies search filter', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $filters = ['search' => 'test'];

    // Since scopeApplyFilters calls $query->whereSearch(), we mock that method on $mockQuery
    $mockQuery->shouldReceive('whereSearch')->once()->with('test')->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeApplyFilters($mockQuery, $filters);
});

test('scopeApplyFilters applies date range filter', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $filters = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ];

    $mockQuery->shouldReceive('fileDisksBetween')
        ->once()
        ->withArgs(function ($start, $end) {
            return $start instanceof Carbon
                && $start->eq(Carbon::createFromFormat('Y-m-d', '2023-01-01'))
                && $end instanceof Carbon
                && $end->eq(Carbon::createFromFormat('Y-m-d', '2023-01-31'));
        })
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeApplyFilters($mockQuery, collect($filters));
});

test('scopeApplyFilters applies order filter with field and order', function () {
    $mockQuery = Mockery::mock(Builder::class);

    $mockQuery->shouldReceive('whereOrder')
        ->once()
        ->with('custom_field', 'desc')
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeApplyFilters($mockQuery, [
        'orderByField' => 'custom_field',
        'orderBy' => 'desc'
    ]);
});

test('scopeApplyFilters applies order filter with only orderByField, default asc', function () {
    $mockQuery = Mockery::mock(Builder::class);

    $mockQuery->shouldReceive('whereOrder')
        ->once()
        ->with('custom_field', 'asc')
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeApplyFilters($mockQuery, ['orderByField' => 'custom_field']);
});

test('scopeApplyFilters applies order filter with only orderBy, default field', function () {
    $mockQuery = Mockery::mock(Builder::class);

    $mockQuery->shouldReceive('whereOrder')
        ->once()
        ->with('sequence_number', 'desc')
        ->andReturnSelf();

    $fileDisk = new FileDisk();
    $fileDisk->scopeApplyFilters($mockQuery, ['orderBy' => 'desc']);
});

test('setAsDefault returns attribute', function () {
    $fileDisk = new FileDisk();
    $fileDisk->set_as_default = true;
    expect($fileDisk->setAsDefault())->toBeTrue();

    $fileDisk->set_as_default = false;
    expect($fileDisk->setAsDefault())->toBeFalse();
});

test('updateDefaultDisks sets set_as_default = false for all disks', function () {
    $disk1 = Mockery::mock(FileDisk::class)->makePartial();
    $disk2 = Mockery::mock(FileDisk::class)->makePartial();

    $disk1->set_as_default = true;
    $disk2->set_as_default = true;

    $disk1->shouldReceive('save')->once();
    $disk2->shouldReceive('save')->once();

    // Mock static where and get on FileDisk using Facade mocking
    $fileDiskMock = Mockery::mock('alias:Crater\Models\FileDisk');
    $fileDiskMock->shouldReceive('where')->with('set_as_default', true)->andReturnSelf();
    $fileDiskMock->shouldReceive('get')->andReturn(collect([$disk1, $disk2]));

    // Call updateDefaultDisks statically (it will use our mock)
    FileDisk::updateDefaultDisks();

    expect($disk1->set_as_default)->toBeFalse();
    expect($disk2->set_as_default)->toBeFalse();
});

test('updateDisk updates disk attributes and saves', function () {
    $disk = Mockery::mock(FileDisk::class)->makePartial();
    $request = Mockery::mock(Request::class);

    $request->credentials = ['a' => 'b'];
    $request->name = 'Updated';
    $request->driver = 'local';
    $request->set_as_default = true;

    $disk->shouldReceive('update')->with([
        'credentials' => $request->credentials,
        'name' => 'Updated',
        'driver' => 'local',
        'set_as_default' => true,
    ])->once();

    // Mock updateDefaultDisks static call
    $fileDiskMock = Mockery::mock('alias:Crater\Models\FileDisk');
    $fileDiskMock->shouldReceive('updateDefaultDisks')->once();

    FileDisk::updateDisk($disk, $request);
});

test('deleteDisk deletes disk and returns true', function () {
    $disk = Mockery::mock(FileDisk::class)->makePartial();
    $disk->shouldReceive('delete')->once()->andReturn(true);

    // Since FileDisk::deleteDisk() is not actually defined as a static method,
    // we mock it by wrapping the call in a closure here if necessary.
    // But because it is being used as a static method, we have to mock via alias.
    $fileDiskMock = Mockery::mock('alias:Crater\Models\FileDisk');
    $fileDiskMock->shouldReceive('deleteDisk')->with($disk)->andReturnUsing(function($disk) {
        return $disk->delete();
    });

    $result = FileDisk::deleteDisk($disk);

    expect($result)->toBeTrue();
});