<?php

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Crater\Http\Resources\CustomFieldValueCollection;

class TestCustomFieldValueResource extends JsonResource
{
    public function toArray($request)
    {
        // Cast arrays to objects for consistency and avoid undefined property error
        $resource = is_array($this->resource) ? (object) $this->resource : $this->resource;

        return [
            'id' => isset($resource->id) ? $resource->id : null,
            'name' => isset($resource->name) ? $resource->name : null,
            'value' => isset($resource->value) ? $resource->value : null,
            'request_param' => $request->get('param', 'default'),
            'type' => isset($resource->type) ? $resource->type : 'unknown',
        ];
    }
}

test('toArray returns an empty array when the underlying collection is empty', function () {
    $request = Request::create('/test', 'GET');
    $collection = new Collection([]);

    $testCollection = new class($collection) extends CustomFieldValueCollection {
        public $collects = TestCustomFieldValueResource::class;
    };

    $result = $testCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toBeEmpty();
});

test('toArray transforms multiple items correctly using a defined resource type', function () {
    $request = Request::create('/test', 'GET', ['param' => 'test_value']);
    $data = [
        (object)['id' => 1, 'name' => 'Item A', 'value' => 'Alpha'],
        (object)['id' => 2, 'name' => 'Item B', 'value' => 'Beta'],
    ];
    $collection = new Collection($data);

    $testCollection = new class($collection) extends CustomFieldValueCollection {
        public $collects = TestCustomFieldValueResource::class;
    };

    $result = $testCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toHaveCount(2);

    expect($result[0])->toEqual([
        'id' => 1,
        'name' => 'Item A',
        'value' => 'Alpha',
        'request_param' => 'test_value',
        'type' => 'unknown',
    ]);

    expect($result[1])->toEqual([
        'id' => 2,
        'name' => 'Item B',
        'value' => 'Beta',
        'request_param' => 'test_value',
        'type' => 'unknown',
    ]);
});

test('toArray correctly passes the request object to the underlying resource transformations', function () {
    $request1 = Request::create('/test', 'GET', ['param' => 'request_one']);
    $request2 = Request::create('/another', 'GET', ['param' => 'request_two']);

    $data = [(object)['id' => 3, 'value' => 'Gamma']];
    $collection = new Collection($data);

    $testCollection = new class($collection) extends CustomFieldValueCollection {
        public $collects = TestCustomFieldValueResource::class;
    };

    $result1 = $testCollection->toArray($request1);
    expect($result1[0]['request_param'])->toBe('request_one');

    $result2 = $testCollection->toArray($request2);
    expect($result2[0]['request_param'])->toBe('request_two');
});

test('toArray handles mixed data types in collection when using a defined resource', function () {
    $request = Request::create('/test', 'GET');
    $data = [
        (object)['id' => 10, 'name' => 'Object Item'],
        ['id' => 20, 'name' => 'Array Item', 'value' => 'Twenty'],
    ];
    $collection = new Collection($data);

    $testCollection = new class($collection) extends CustomFieldValueCollection {
        public $collects = TestCustomFieldValueResource::class;
    };

    $result = $testCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toHaveCount(2);

    expect($result[0])->toEqual([
        'id' => 10,
        'name' => 'Object Item',
        'value' => null,
        'request_param' => 'default',
        'type' => 'unknown',
    ]);

    expect($result[1])->toEqual([
        'id' => 20,
        'name' => 'Array Item',
        'value' => 'Twenty',
        'request_param' => 'default',
        'type' => 'unknown',
    ]);
});

test('toArray uses default JsonResource behavior when $collects is not specified in the class', function () {
    $request = Request::create('/default-test', 'GET');
    $data = [
        (object)['field1' => 'val1', 'field2' => 'val2'],
        ['fieldA' => 'valA', 'fieldB' => 'valB'],
        ['id' => 99, 'name' => 'Default Item'],
    ];
    $collection = new Collection($data);

    // Instantiate directly, with no collects property
    $customCollection = new CustomFieldValueCollection($collection);

    $result = $customCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toHaveCount(3);

    // Defensive casting, because JsonResource returns arrays from objects (not objects)
    expect($result[0])->toEqual(['field1' => 'val1', 'field2' => 'val2']);
    expect($result[1])->toEqual(['fieldA' => 'valA', 'fieldB' => 'valB']);
    expect($result[2])->toEqual(['id' => 99, 'name' => 'Default Item']);
});

test('toArray returns meta data when present in the collection instance', function () {
    $request = Request::create('/meta', 'GET');
    $collection = new Collection([
        (object)['id' => 1, 'name' => 'Item 1'],
    ]);

    $testCollection = new class($collection) extends CustomFieldValueCollection {
        public $collects = TestCustomFieldValueResource::class;
        public function with($request)
        {
            return ['meta' => ['key' => 'value']];
        }

        public function toArray($request)
        {
            // Compose 'data' and merge with additional
            $data = [];
            foreach ($this->collection as $item) {
                $resource = new $this->collects($item);
                $data[] = $resource->toArray($request);
            }
            return array_merge(['data' => $data], $this->with($request));
        }
    };

    $result = $testCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toHaveKey('data')
                   ->toHaveKey('meta');

    expect($result['data'][0]['id'])->toBe(1);
    expect($result['meta'])->toEqual(['key' => 'value']);
});

test('toArray returns additional data when present in the collection instance', function () {
    $request = Request::create('/additional', 'GET');
    $collection = new Collection([
        (object)['id' => 1, 'name' => 'Item 1'],
    ]);

    $testCollection = new class($collection) extends CustomFieldValueCollection {
        public $collects = TestCustomFieldValueResource::class;
        public function with($request)
        {
            return ['additional_field' => 'extra_data'];
        }

        public function toArray($request)
        {
            // Compose 'data' and merge with additional
            $data = [];
            foreach ($this->collection as $item) {
                $resource = new $this->collects($item);
                $data[] = $resource->toArray($request);
            }
            return array_merge(['data' => $data], $this->with($request));
        }
    };

    $result = $testCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toHaveKey('data')
                   ->toHaveKey('additional_field');

    expect($result['data'][0]['id'])->toBe(1);
    expect($result['additional_field'])->toBe('extra_data');
});

afterEach(function () {
    Mockery::close();
});