<?php

test('tax type collection can be instantiated with a collection', function () {
    $collection = Illuminate\Support\Collection::make([
        ['id' => 1, 'name' => 'VAT'],
        ['id' => 2, 'name' => 'GST'],
    ]);
    $resourceCollection = new \Crater\Http\Resources\TaxTypeCollection($collection);
    expect($resourceCollection)->toBeInstanceOf(\Crater\Http\Resources\TaxTypeCollection::class);
    expect($resourceCollection->resource)->toBe($collection); // The original collection is stored in the 'resource' property
});

test('toArray returns an empty array when initialized with an empty collection', function () {
    $request = Illuminate\Http\Request::create('/');
    $collection = Illuminate\Support\Collection::make([]);
    $resourceCollection = new \Crater\Http\Resources\TaxTypeCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toBeEmpty();
});

test('toArray returns the collection items directly when initialized with simple array data and no specific resource set', function () {
    $request = Illuminate\Http\Request::create('/');
    $data = [
        ['id' => 1, 'name' => 'VAT', 'rate' => 20],
        ['id' => 2, 'name' => 'GST', 'rate' => 10],
    ];
    $collection = Illuminate\Support\Collection::make($data);
    $resourceCollection = new \Crater\Http\Resources\TaxTypeCollection($collection);

    $result = $resourceCollection->toArray($request);

    // Since TaxTypeCollection does not define a '$collects' property,
    // ResourceCollection::toArray simply returns the underlying collection's items as they are.
    expect($result)->toBeArray()
                   ->toEqual($data);
});

test('toArray returns the collection items converted to arrays when initialized with objects and no specific resource set', function () {
    $request = Illuminate\Http\Request::create('/');
    $data = [
        (object)['id' => 1, 'name' => 'VAT', 'rate' => 20],
        (object)['id' => 2, 'name' => 'GST', 'rate' => 10],
    ];
    $collection = Illuminate\Support\Collection::make($data);
    $resourceCollection = new \Crater\Http\Resources\TaxTypeCollection($collection);

    $result = $resourceCollection->toArray($request);

    // Objects in the collection are typically converted to arrays during the JSON serialization process.
    // ResourceCollection::toArray (without '$collects') maps each item. When it becomes part of the final array,
    // object properties are converted to array keys.
    $expectedArray = [
        ['id' => 1, 'name' => 'VAT', 'rate' => 20],
        ['id' => 2, 'name' => 'GST', 'rate' => 10],
    ];

    expect($result)->toBeArray()
                   ->toEqual($expectedArray);
});

test('toArray handles mixed data types in the collection gracefully without a specific resource set', function () {
    $request = Illuminate\Http\Request::create('/');
    $data = [
        ['id' => 1, 'name' => 'Tax A'],
        null,
        (object)['id' => 2, 'name' => 'Tax B', 'status' => true],
        'simple_string',
        123,
        ['nested' => ['value' => 456]],
    ];
    $collection = Illuminate\Support\Collection::make($data);
    $resourceCollection = new \Crater\Http\Resources\TaxTypeCollection($collection);

    $result = $resourceCollection->toArray($request);

    // The parent::toArray logic for ResourceCollection (when '$collects' is not set)
    // will just return the items as they are, with objects converted to arrays.
    $expected = [
        ['id' => 1, 'name' => 'Tax A'],
        null,
        ['id' => 2, 'name' => 'Tax B', 'status' => true],
        'simple_string',
        123,
        ['nested' => ['value' => 456]],
    ];

    expect($result)->toBeArray()
                   ->toEqual($expected);
});

test('toArray method always returns an array even if the initial collection is not an array-like structure (e.g., generator)', function () {
    $request = Illuminate\Http\Request::create('/');

    // Simulate a generator or an iterable that's not a direct array
    $generator = (function () {
        yield ['id' => 1];
        yield ['id' => 2];
    })();

    // ResourceCollection expects an instance of Illuminate\Support\Collection, so we must wrap it.
    $collection = Illuminate\Support\Collection::make($generator);
    $resourceCollection = new \Crater\Http\Resources\TaxTypeCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toEqual([['id' => 1], ['id' => 2]]);
});

test('toArray handles complex nested array data structures', function () {
    $request = Illuminate\Http\Request::create('/');
    $data = [
        ['id' => 1, 'details' => ['level' => 'high', 'active' => true]],
        ['id' => 2, 'details' => ['level' => 'low', 'active' => false, 'tags' => ['a', 'b']]],
    ];
    $collection = Illuminate\Support\Collection::make($data);
    $resourceCollection = new \Crater\Http\Resources\TaxTypeCollection($collection);

    $result = $resourceCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toEqual($data);
});




afterEach(function () {
    Mockery::close();
});
