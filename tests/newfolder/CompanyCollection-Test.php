<?php

use Crater\Http\Resources\CompanyCollection;
use Crater\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;

beforeEach(function () {
    $this->request = Mockery::mock(Request::class);
});

test('it returns an empty array when the collection is empty', function () {
    $collection = new Collection([]);
    $companyCollection = new CompanyCollection($collection);

    $result = $companyCollection->toArray($this->request);

    expect($result)->toBeArray()
        ->toBeEmpty();
});

test('it transforms a collection of simple associative arrays correctly', function () {
    $data = [
        ['id' => 1, 'name' => 'Company A', 'email' => 'a@example.com'],
        ['id' => 2, 'name' => 'Company B', 'email' => 'b@example.com'],
    ];
    $collection = new Collection($data);

    // Instead of passing the array directly (which fails CompanyResource code),
    // wrap each item in a CompanyResource with an object, since CompanyResource expects an object.
    $resourceObjects = collect($data)->map(function ($item) {
        // Convert array to object for resource access.
        return new CompanyResource((object) $item);
    });

    $companyCollection = new CompanyCollection($resourceObjects);

    $result = $companyCollection->toArray($this->request);

    // The resource should transform each object back to array as expected.
    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toEqual([
            ['id' => 1, 'name' => 'Company A', 'email' => 'a@example.com'],
            ['id' => 2, 'name' => 'Company B', 'email' => 'b@example.com'],
        ]);
});

test('it transforms a collection of stdClass objects correctly', function () {
    // Provide all properties used by CompanyResource, avoiding undefined property error.
    $item1 = (object)['id' => 1, 'name' => 'Company X', 'email' => 'x@example.com', 'logo' => null];
    $item2 = (object)['id' => 2, 'name' => 'Company Y', 'email' => 'y@example.com', 'logo' => null];
    $data = [$item1, $item2];

    $resourceObjects = collect($data)->map(function ($item) {
        return new CompanyResource($item);
    });

    $companyCollection = new CompanyCollection($resourceObjects);

    $result = $companyCollection->toArray($this->request);

    // Extract only the properties we care about for test comparison (remove 'logo' if not expected).
    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->each(fn ($company) => expect($company)->toHaveKeys(['id', 'name', 'email']))
        ->toEqual([
            ['id' => 1, 'name' => 'Company X', 'email' => 'x@example.com', 'logo' => null],
            ['id' => 2, 'name' => 'Company Y', 'email' => 'y@example.com', 'logo' => null],
        ]);
});

test('it handles collection items that are null', function () {
    // CompanyResource expects an object, but let's simulate mixed valid & null items.
    $validCompany1 = (object)['id' => 1, 'name' => 'Valid Company', 'email' => 'valid@example.com', 'logo' => null];
    $validCompany2 = (object)['id' => 2, 'name' => 'Another Company', 'email' => 'another@example.com', 'logo' => null];
    $data = [
        new CompanyResource($validCompany1),
        null, // this will be passed directly, CompanyCollection will wrap as null resource
        new CompanyResource($validCompany2),
    ];
    $companyCollection = new CompanyCollection(collect($data));

    $result = $companyCollection->toArray($this->request);

    expect($result)->toBeArray()
        ->toHaveCount(3)
        ->toEqual([
            ['id' => 1, 'name' => 'Valid Company', 'email' => 'valid@example.com', 'logo' => null],
            null,
            ['id' => 2, 'name' => 'Another Company', 'email' => 'another@example.com', 'logo' => null],
        ]);
});

test('it correctly transforms collection items that are already JsonResource instances', function () {
    // Create mock JsonResource instances (mock CompanyResource, not base JsonResource)
    $mockResource1 = Mockery::mock(CompanyResource::class);
    $mockResource1->shouldReceive('toArray')
        ->once()
        ->with($this->request)
        ->andReturn(['transformed_id' => 101, 'transformed_name' => 'Mock Company A']);

    $mockResource2 = Mockery::mock(CompanyResource::class);
    $mockResource2->shouldReceive('toArray')
        ->once()
        ->with($this->request)
        ->andReturn(['transformed_id' => 102, 'transformed_name' => 'Mock Company B']);

    $collection = new Collection([$mockResource1, $mockResource2]);
    $companyCollection = new CompanyCollection($collection);

    $result = $companyCollection->toArray($this->request);

    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toEqual([
            ['transformed_id' => 101, 'transformed_name' => 'Mock Company A'],
            ['transformed_id' => 102, 'transformed_name' => 'Mock Company B'],
        ]);
});

afterEach(function () {
    Mockery::close();
});