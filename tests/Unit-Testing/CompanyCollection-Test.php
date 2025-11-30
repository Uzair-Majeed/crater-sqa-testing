<?php

use Crater\Http\Resources\CompanyCollection;
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
    $companyCollection = new CompanyCollection($collection);

    $result = $companyCollection->toArray($this->request);

    // parent::toArray will internally call JsonResource::make($item)->toArray($request) for each item.
    // For simple associative arrays, JsonResource::toArray() effectively returns the original array.
    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toEqual($data);
});

test('it transforms a collection of stdClass objects correctly', function () {
    $item1 = (object)['id' => 1, 'name' => 'Company X'];
    $item2 = (object)['id' => 2, 'name' => 'Company Y'];
    $data = [$item1, $item2];
    $collection = new Collection($data);
    $companyCollection = new CompanyCollection($collection);

    $result = $companyCollection->toArray($this->request);

    // JsonResource::toArray() for stdClass objects converts them to associative arrays.
    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toEqual([
            ['id' => 1, 'name' => 'Company X'],
            ['id' => 2, 'name' => 'Company Y'],
        ]);
});

test('it handles collection items that are null', function () {
    $data = [
        ['id' => 1, 'name' => 'Valid Company'],
        null, // An invalid item
        ['id' => 2, 'name' => 'Another Company'],
    ];
    $collection = new Collection($data);
    $companyCollection = new CompanyCollection($collection);

    $result = $companyCollection->toArray($this->request);

    // JsonResource::make(null)->toArray($request) will return null
    expect($result)->toBeArray()
        ->toHaveCount(3)
        ->toEqual([
            ['id' => 1, 'name' => 'Valid Company'],
            null, // Expected transformation of null
            ['id' => 2, 'name' => 'Another Company'],
        ]);
});

test('it correctly transforms collection items that are already JsonResource instances', function () {
    // Create mock JsonResource instances that would typically be returned by CompanyResource
    $mockResource1 = Mockery::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
        ->once()
        ->with($this->request)
        ->andReturn(['transformed_id' => 101, 'transformed_name' => 'Mock Company A']);

    $mockResource2 = Mockery::mock(JsonResource::class);
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
