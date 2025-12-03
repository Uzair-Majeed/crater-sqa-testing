<?php

use Mockery as m;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Crater\Http\Resources\AbilityCollection;

beforeEach(function () {
    m::close();
});

test('it extends resource collection', function () {
    $collection = new Collection();
    $abilityCollection = new AbilityCollection($collection);

    expect($abilityCollection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('to_array_returns_empty_array_for_empty_collection', function () {
    $collection = new Collection();
    $request = m::mock(Request::class);

    $abilityCollection = new AbilityCollection($collection);
    $result = $abilityCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

test('to_array_transforms_resources_correctly', function () {
    $request = m::mock(Request::class);

    $mockResource1 = m::mock(JsonResource::class);
    $mockResource1->shouldReceive('toArray')
                  ->with($request)
                  ->once()
                  ->andReturn(['id' => 1, 'name' => 'Ability 1']);

    $mockResource2 = m::mock(JsonResource::class);
    $mockResource2->shouldReceive('toArray')
                  ->with($request)
                  ->once()
                  ->andReturn(['id' => 2, 'name' => 'Ability 2']);

    $collection = new Collection([$mockResource1, $mockResource2]);

    $abilityCollection = new AbilityCollection($collection);
    $result = $abilityCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toHaveCount(2)
                   ->toEqual([
                       ['id' => 1, 'name' => 'Ability 1'],
                       ['id' => 2, 'name' => 'Ability 2'],
                   ]);
});

test('to_array_passes_correct_request_object_to_resources', function () {
    $request = m::mock(Request::class);

    $mockResource = m::mock(JsonResource::class);
    $mockResource->shouldReceive('toArray')
                  ->with(m::on(function ($arg) use ($request) {
                      return $arg === $request; // Verify identity of the request object
                  }))
                  ->once()
                  ->andReturn(['test_data' => 'ok']);

    $collection = new Collection([$mockResource]);

    $abilityCollection = new AbilityCollection($collection);
    $abilityCollection->toArray($request);
});

test('to_array_handles_different_types_of_resources_implementing_to_array', function () {
    $request = m::mock(Request::class);

    $customResource = new class {
        public function toArray($req) {
            return ['custom' => 'data', 'request_spl_id' => spl_object_id($req)];
        }
    };

    $mockResource = m::mock(JsonResource::class);
    $mockResource->shouldReceive('toArray')
                 ->with($request)
                 ->once()
                 ->andReturn(['id' => 1, 'name' => 'JsonResource']);

    $collection = new Collection([$mockResource, $customResource]);

    $abilityCollection = new AbilityCollection($collection);
    $result = $abilityCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toHaveCount(2)
                   ->toEqual([
                       ['id' => 1, 'name' => 'JsonResource'],
                       ['custom' => 'data', 'request_spl_id' => spl_object_id($request)],
                   ]);
});




afterEach(function () {
    Mockery::close();
});
