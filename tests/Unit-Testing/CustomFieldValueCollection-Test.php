<?php

class TestCustomFieldValueResource extends Illuminate\Http\Resources\Json\JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id ?? null,
            'name' => $this->name ?? null,
            'value' => $this->value ?? null,
            'request_param' => $request->get('param', 'default'),
            'type' => $this->type ?? 'unknown',
        ];
    }
}

test('toArray returns an empty array when the underlying collection is empty', function () {
    $request = Illuminate\Http\Request::create('/test', 'GET');
    $collection = new Illuminate\Support\Collection([]);

    // We use an anonymous class to simulate how CustomFieldValueCollection would be used
    // if it had a specific resource it collects, demonstrating the parent::toArray behavior.
    $testCollection = new class($collection) extends Crater\Http\Resources\CustomFieldValueCollection {
        public $collects = TestCustomFieldValueResource::class;
    };

    $result = $testCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toBeEmpty();
});

test('toArray transforms multiple items correctly using a defined resource type', function () {
    $request = Illuminate\Http\Request::create('/test', 'GET', ['param' => 'test_value']);
    $data = [
        (object)['id' => 1, 'name' => 'Item A', 'value' => 'Alpha'],
        (object)['id' => 2, 'name' => 'Item B', 'value' => 'Beta'],
    ];
    $collection = new Illuminate\Support\Collection($data);

    $testCollection = new class($collection) extends Crater\Http\Resources\CustomFieldValueCollection {
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
    $request1 = Illuminate\Http\Request::create('/test', 'GET', ['param' => 'request_one']);
    $request2 = Illuminate\Http\Request::create('/another', 'GET', ['param' => 'request_two']);

    $data = [(object)['id' => 3, 'value' => 'Gamma']];
    $collection = new Illuminate\Support\Collection($data);

    $testCollection = new class($collection) extends Crater\Http\Resources\CustomFieldValueCollection {
        public $collects = TestCustomFieldValueResource::class;
    };

    $result1 = $testCollection->toArray($request1);
    expect($result1[0]['request_param'])->toBe('request_one');

    $result2 = $testCollection->toArray($request2);
    expect($result2[0]['request_param'])->toBe('request_two');
});

test('toArray handles mixed data types in collection when using a defined resource', function () {
    $request = Illuminate\Http\Request::create('/test', 'GET');
    $data = [
        (object)['id' => 10, 'name' => 'Object Item'],
        ['id' => 20, 'name' => 'Array Item', 'value' => 'Twenty'], // Arrays are cast to objects by JsonResource
    ];
    $collection = new Illuminate\Support\Collection($data);

    $testCollection = new class($collection) extends Crater\Http\Resources\CustomFieldValueCollection {
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
    // CustomFieldValueCollection does not define `$collects`, so ResourceCollection
    // defaults to wrapping items in a generic Illuminate\Http\Resources\Json\JsonResource.
    $request = Illuminate\Http\Request::create('/default-test', 'GET');
    $data = [
        (object)['field1' => 'val1', 'field2' => 'val2'],
        ['fieldA' => 'valA', 'fieldB' => 'valB'],
        ['id' => 99, 'name' => 'Default Item'],
    ];
    $collection = new Illuminate\Support\Collection($data);

    // Instantiate CustomFieldValueCollection directly to test its actual default behavior
    $customCollection = new Crater\Http\Resources\CustomFieldValueCollection($collection);

    $result = $customCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toHaveCount(3);

    // Default JsonResource behavior for objects and arrays is to return them as-is
    expect($result[0])->toEqual(['field1' => 'val1', 'field2' => 'val2']);
    expect($result[1])->toEqual(['fieldA' => 'valA', 'fieldB' => 'valB']);
    expect($result[2])->toEqual(['id' => 99, 'name' => 'Default Item']);
});

test('toArray returns meta data when present in the collection instance', function () {
    $request = Illuminate\Http\Request::create('/meta', 'GET');
    $collection = new Illuminate\Support\Collection([
        (object)['id' => 1, 'name' => 'Item 1'],
    ]);

    $testCollection = new class($collection) extends Crater\Http\Resources\CustomFieldValueCollection {
        public $collects = TestCustomFieldValueResource::class;
        public function with($request)
        {
            return ['meta' => ['key' => 'value']];
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
    $request = Illuminate\Http\Request::create('/additional', 'GET');
    $collection = new Illuminate\Support\Collection([
        (object)['id' => 1, 'name' => 'Item 1'],
    ]);

    $testCollection = new class($collection) extends Crater\Http\Resources\CustomFieldValueCollection {
        public $collects = TestCustomFieldValueResource::class;
        public function with($request)
        {
            return ['additional_field' => 'extra_data'];
        }
    };

    $result = $testCollection->toArray($request);

    expect($result)->toBeArray()
                   ->toHaveKey('data')
                   ->toHaveKey('additional_field');

    expect($result['data'][0]['id'])->toBe(1);
    expect($result['additional_field'])->toBe('extra_data');
});
