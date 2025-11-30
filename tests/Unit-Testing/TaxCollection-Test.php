<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Contracts\Support\Arrayable;
uses(\Mockery::class);
use Crater\Http\Resources\TaxCollection;

beforeEach(function () {
    Mockery::close(); // Ensure mocks are cleaned up before each test
});

afterEach(function () {
    Mockery::close(); // Ensure mocks are cleaned up after each test
});

test('toArray returns an empty array for an empty collection', function () {
    $collection = Collection::make([]);
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

test('toArray transforms JsonResource items correctly in a non-empty collection', function () {
    // Create mock JsonResource items using anonymous classes to simulate their behavior
    $mockResource1 = new class(['id' => 1, 'name' => 'Tax A', 'rate' => 0.05]) extends JsonResource {
        public function toArray($request)
        {
            return ['id' => $this->resource['id'], 'tax_name' => $this->resource['name'], 'rate_value' => $this->resource['rate']];
        }
    };
    $mockResource2 = new class(['id' => 2, 'name' => 'Tax B', 'rate' => 0.10]) extends JsonResource {
        public function toArray($request)
        {
            return ['id' => $this->resource['id'], 'tax_name' => $this->resource['name'], 'rate_value' => $this->resource['rate']];
        }
    };

    $collection = Collection::make([$mockResource1, $mockResource2]);
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    // Expect parent::toArray to call toArray on each JsonResource item
    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toEqual([
            ['id' => 1, 'tax_name' => 'Tax A', 'rate_value' => 0.05],
            ['id' => 2, 'tax_name' => 'Tax B', 'rate_value' => 0.10],
        ]);
});

test('toArray transforms Arrayable items correctly in a non-empty collection', function () {
    // Create mock Arrayable items using anonymous classes
    $mockArrayable1 = new class implements Arrayable {
        public function toArray()
        {
            return ['id' => 10, 'description' => 'Special Tax'];
        }
    };
    $mockArrayable2 = new class implements Arrayable {
        public function toArray()
        {
            return ['id' => 11, 'description' => 'Another Special Tax'];
        }
    };

    $collection = Collection::make([$mockArrayable1, $mockArrayable2]);
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    // Expect parent::toArray to call toArray on each Arrayable item
    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toEqual([
            ['id' => 10, 'description' => 'Special Tax'],
            ['id' => 11, 'description' => 'Another Special Tax'],
        ]);
});

test('toArray includes non-arrayable and non-resource items directly', function () {
    // Create items that are neither JsonResource nor Arrayable
    $plainObject1 = (object)['id' => 20, 'code' => 'P1'];
    $plainObject2 = (object)['id' => 21, 'code' => 'P2'];
    $plainString = 'just a string';
    $plainInt = 123;

    $collection = Collection::make([$plainObject1, $plainObject2, $plainString, $plainInt]);
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    // Expect parent::toArray to include these items directly without transformation
    expect($result)->toBeArray()
        ->toHaveCount(4)
        ->toEqual([
            $plainObject1,
            $plainObject2,
            $plainString,
            $plainInt,
        ]);
});

test('toArray handles a mixed collection of item types', function () {
    // Test a collection containing a mix of JsonResource, Arrayable, and plain items
    $mockResource = new class(['id' => 1, 'name' => 'Resource Item']) extends JsonResource {
        public function toArray($request)
        {
            return ['res_id' => $this->resource['id']];
        }
    };
    $mockArrayable = new class implements Arrayable {
        public function toArray()
        {
            return ['arr_id' => 2];
        }
    };
    $plainObject = (object)['plain_id' => 3, 'data' => 'xyz'];
    $plainScalar = 4;

    $collection = Collection::make([$mockResource, $mockArrayable, $plainObject, $plainScalar]);
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toBeArray()
        ->toHaveCount(4)
        ->toEqual([
            ['res_id' => 1],
            ['arr_id' => 2],
            $plainObject,
            $plainScalar,
        ]);
});

test('toArray handles a collection with a single item correctly', function () {
    $mockResource = new class(['id' => 5, 'name' => 'Single Tax']) extends JsonResource {
        public function toArray($request)
        {
            return ['id' => $this->resource['id'], 'tax_name' => $this->resource['name']];
        }
    };
    $collection = Collection::make([$mockResource]);
    $resource = new TaxCollection($collection);

    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toBeArray()
        ->toHaveCount(1)
        ->toEqual([
            ['id' => 5, 'tax_name' => 'Single Tax'],
        ]);
});
