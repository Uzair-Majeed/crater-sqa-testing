<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Crater\Http\Resources\AbilityCollection;
use Crater\Http\Resources\AbilityResource;

test('it extends resource collection', function () {
    $collection = new Collection();
    $abilityCollection = new AbilityCollection($collection);

    expect($abilityCollection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('to_array_returns_empty_array_for_empty_collection', function () {
    $collection = new Collection();
    $request = new Request();

    $abilityCollection = new AbilityCollection($collection);
    $result = $abilityCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});