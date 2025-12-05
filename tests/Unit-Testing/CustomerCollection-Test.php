<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Collection;

class TestCustomerModel
{
    public $id;
    public $name;
    public $email;
    public $status;
    public $phone;

    public function __construct($overrides = [])
    {
        $this->id = $overrides['id'] ?? 999;
        $this->name = array_key_exists('name', $overrides) ? $overrides['name'] : 'Dummy';
        $this->email = array_key_exists('email', $overrides) ? $overrides['email'] : 'dummy@example.com';
        $this->status = array_key_exists('status', $overrides) ? $overrides['status'] : 'active';
        $this->phone = array_key_exists('phone', $overrides) ? $overrides['phone'] : null;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'phone' => $this->phone,
        ];
    }
}

class TestCustomerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'status' => $this->resource->status,
            'phone' => $this->resource->phone,
        ];
    }
}

class TestCustomerCollection extends JsonResource
{
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request)
    {
        $result = [];
        foreach ($this->resource as $item) {
            if ($item instanceof JsonResource) {
                $result[] = $item->toArray($request);
            } elseif (is_object($item) && method_exists($item, 'toArray')) {
                $result[] = $item->toArray();
            } elseif (is_array($item)) {
                $result[] = $item;
            } elseif (is_object($item)) {
                // stdClass or other object
                $result[] = get_object_vars($item);
            } elseif ($item === null) {
                $result[] = null;
            } else {
                $result[] = (array) $item;
            }
        }
        return $result;
    }
}

class DummyRequest extends Request
{
    protected $params = [];

    public function __construct($params = [])
    {
        parent::__construct([], [], [], [], [], [], null);
        $this->params = $params;
    }

    public function has($key)
    {
        return array_key_exists($key, $this->params);
    }

    public function get($key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function all($keys = null)
    {
        if ($keys === null) {
            return $this->params;
        }
        $result = [];
        foreach ((array)$keys as $key) {
            $result[$key] = $this->params[$key] ?? null;
        }
        return $result;
    }
}

beforeEach(function () {
    // No mocking needed
});

test('toArray returns an empty array when initialized with an empty collection', function () {
    $request = new DummyRequest();
    $collection = new Collection();
    $customerCollection = new TestCustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

test('toArray correctly transforms a collection of simple objects via default JsonResource behavior', function () {
    $request = new DummyRequest();

    $model1 = new TestCustomerModel([
        'id' => 1,
        'name' => 'Customer A',
        'email' => 'a@example.com',
        'status' => 'active',
        'phone' => '1234567890',
    ]);
    $model2 = new TestCustomerModel([
        'id' => 2,
        'name' => 'Customer B',
        'email' => 'b@example.com',
        'status' => 'inactive',
        'phone' => '0987654321',
    ]);

    $collection = new Collection([$model1, $model2]);
    $customerCollection = new TestCustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    $expected = [
        [
            'id' => 1,
            'name' => 'Customer A',
            'email' => 'a@example.com',
            'status' => 'active',
            'phone' => '1234567890',
        ],
        [
            'id' => 2,
            'name' => 'Customer B',
            'email' => 'b@example.com',
            'status' => 'inactive',
            'phone' => '0987654321',
        ],
    ];

    expect(array_map(function($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'email' => $item['email'],
            'status' => $item['status'],
            'phone' => $item['phone'],
        ];
    }, $result))->toBe($expected);
});

test('toArray correctly processes a collection of pre-existing JsonResource instances', function () {
    $request = new DummyRequest();

    $dummy1 = new TestCustomerModel([
        'id' => 101,
        'name' => 'Alpha Customer',
        'email' => 'alpha@example.com',
        'status' => 'active',
        'phone' => '1111111111',
    ]);
    $dummy2 = new TestCustomerModel([
        'id' => 102,
        'name' => 'Beta Customer',
        'email' => 'beta@example.com',
        'status' => 'active',
        'phone' => '2222222222',
    ]);

    $mockResource1 = new TestCustomerResource($dummy1);
    $mockResource2 = new TestCustomerResource($dummy2);

    $collection = new Collection([$mockResource1, $mockResource2]);

    $customerCollection = new TestCustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    $expected = [
        [
            'id' => 101,
            'name' => 'Alpha Customer',
            'email' => 'alpha@example.com',
            'status' => 'active',
            'phone' => '1111111111',
        ],
        [
            'id' => 102,
            'name' => 'Beta Customer',
            'email' => 'beta@example.com',
            'status' => 'active',
            'phone' => '2222222222',
        ],
    ];

    expect(array_map(function($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'email' => $item['email'],
            'status' => $item['status'],
            'phone' => $item['phone'],
        ];
    }, $result))->toBe($expected);
});

test('toArray correctly passes the request object to nested transformations when a custom resource type is used in parent', function () {
    $customRequest = new DummyRequest(['append_param' => 'extra_data']);

    $dummy = new TestCustomerModel([
        'id' => 1,
        'name' => 'Custom Customer',
        'email' => 'custom@example.com',
        'status' => 'active',
        'phone' => '5555555555',
    ]);

    $customResource = new class($dummy) extends JsonResource {
        public function toArray($request)
        {
            $base = [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'status' => $this->resource->status,
                'phone' => $this->resource->phone,
            ];
            if ($request->has('append_param')) {
                $base['appended'] = $request->get('append_param');
            }
            return $base;
        }
    };

    $collection = new Collection([$customResource]);

    $customerCollection = new TestCustomerCollection($collection);

    $result = $customerCollection->toArray($customRequest);

    $expected = [
        [
            'id' => 1,
            'name' => 'Custom Customer',
            'email' => 'custom@example.com',
            'status' => 'active',
            'phone' => '5555555555',
            'appended' => 'extra_data',
        ],
    ];

    expect($result)->toBe($expected);
});

test('toArray handles an empty but valid request object', function () {
    $emptyRequest = new DummyRequest();

    $dummy = new TestCustomerModel([
        'id' => 3,
        'name' => 'Customer C',
        'email' => 'c@example.com',
        'status' => 'active',
        'phone' => '3333333333',
    ]);

    $modelObj = new TestCustomerModel([
        'id' => $dummy->id,
        'name' => $dummy->name,
        'email' => $dummy->email,
        'status' => $dummy->status,
        'phone' => $dummy->phone,
    ]);

    $collection = new Collection([$modelObj]);
    $customerCollection = new TestCustomerCollection($collection);

    $result = $customerCollection->toArray($emptyRequest);

    $expected = [
        [
            'id' => 3,
            'name' => 'Customer C',
            'email' => 'c@example.com',
            'status' => 'active',
            'phone' => '3333333333',
        ],
    ];

    expect(array_map(function($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'email' => $item['email'],
            'status' => $item['status'],
            'phone' => $item['phone'],
        ];
    }, $result))->toBe($expected);
});

test('toArray handles edge case: collection of arrays', function () {
    $request = new DummyRequest();

    $arr1 = [
        'id' => 10,
        'name' => 'Arr Customer',
        'email' => 'arr@example.com',
        'status' => 'active',
        'phone' => '4444444444',
    ];
    $arr2 = [
        'id' => 11,
        'name' => 'Arr Customer 2',
        'email' => 'arr2@example.com',
        'status' => 'inactive',
        'phone' => '5555555555',
    ];

    $collection = new Collection([$arr1, $arr2]);
    $customerCollection = new TestCustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    $expected = [$arr1, $arr2];

    expect($result)->toBe($expected);
});

test('toArray handles edge case: collection of stdClass', function () {
    $request = new DummyRequest();

    $obj1 = new stdClass();
    $obj1->id = 20;
    $obj1->name = 'Std Customer';
    $obj1->email = 'std@example.com';
    $obj1->status = 'active';
    $obj1->phone = '6666666666';

    $obj2 = new stdClass();
    $obj2->id = 21;
    $obj2->name = 'Std Customer 2';
    $obj2->email = 'std2@example.com';
    $obj2->status = 'inactive';
    $obj2->phone = '7777777777';

    $collection = new Collection([$obj1, $obj2]);
    $customerCollection = new TestCustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    $expected = [
        [
            'id' => 20,
            'name' => 'Std Customer',
            'email' => 'std@example.com',
            'status' => 'active',
            'phone' => '6666666666',
        ],
        [
            'id' => 21,
            'name' => 'Std Customer 2',
            'email' => 'std2@example.com',
            'status' => 'inactive',
            'phone' => '7777777777',
        ],
    ];

    expect($result)->toBe($expected);
});

test('toArray handles edge case: collection of mixed types', function () {
    $request = new DummyRequest();

    $model = new TestCustomerModel([
        'id' => 30,
        'name' => 'Model Customer',
        'email' => 'model@example.com',
        'status' => 'active',
        'phone' => '8888888888',
    ]);

    $array = [
        'id' => 31,
        'name' => 'Array Customer',
        'email' => 'array@example.com',
        'status' => 'inactive',
        'phone' => '9999999999',
    ];

    $obj = new stdClass();
    $obj->id = 32;
    $obj->name = 'StdClass Customer';
    $obj->email = 'stdclass@example.com';
    $obj->status = 'active';
    $obj->phone = '0000000000';

    $resource = new TestCustomerResource(new TestCustomerModel([
        'id' => 33,
        'name' => 'Resource Customer',
        'email' => 'resource@example.com',
        'status' => 'active',
        'phone' => '1212121212',
    ]));

    $collection = new Collection([$model, $array, $obj, $resource]);
    $customerCollection = new TestCustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    $expected = [
        [
            'id' => 30,
            'name' => 'Model Customer',
            'email' => 'model@example.com',
            'status' => 'active',
            'phone' => '8888888888',
        ],
        [
            'id' => 31,
            'name' => 'Array Customer',
            'email' => 'array@example.com',
            'status' => 'inactive',
            'phone' => '9999999999',
        ],
        [
            'id' => 32,
            'name' => 'StdClass Customer',
            'email' => 'stdclass@example.com',
            'status' => 'active',
            'phone' => '0000000000',
        ],
        [
            'id' => 33,
            'name' => 'Resource Customer',
            'email' => 'resource@example.com',
            'status' => 'active',
            'phone' => '1212121212',
        ],
    ];

    expect($result)->toBe($expected);
});

test('toArray handles edge case: collection with null values', function () {
    $request = new DummyRequest();

    $model = new TestCustomerModel([
        'id' => 40,
        'name' => null,
        'email' => null,
        'status' => null,
        'phone' => null,
    ]);

    $array = [
        'id' => 41,
        'name' => null,
        'email' => null,
        'status' => null,
        'phone' => null,
    ];

    $obj = new stdClass();
    $obj->id = 42;
    $obj->name = null;
    $obj->email = null;
    $obj->status = null;
    $obj->phone = null;

    $collection = new Collection([$model, $array, $obj]);
    $customerCollection = new TestCustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    $expected = [
        [
            'id' => 40,
            'name' => null,
            'email' => null,
            'status' => null,
            'phone' => null,
        ],
        [
            'id' => 41,
            'name' => null,
            'email' => null,
            'status' => null,
            'phone' => null,
        ],
        [
            'id' => 42,
            'name' => null,
            'email' => null,
            'status' => null,
            'phone' => null,
        ],
    ];

    expect($result)->toBe($expected);
});

test('toArray handles edge case: collection with explicit nulls', function () {
    $request = new DummyRequest();

    $collection = new Collection([null, null]);
    $customerCollection = new TestCustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    expect($result)->toBe([null, null]);
});

test('toArray handles edge case: collection with mixed null and valid', function () {
    $request = new DummyRequest();

    $model = new TestCustomerModel([
        'id' => 50,
        'name' => 'Null Mix',
        'email' => 'nullmix@example.com',
        'status' => 'active',
        'phone' => '5555555555',
    ]);
    $collection = new Collection([null, $model, null]);
    $customerCollection = new TestCustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    expect($result[0])->toBeNull();
    expect($result[1])->toBe([
        'id' => 50,
        'name' => 'Null Mix',
        'email' => 'nullmix@example.com',
        'status' => 'active',
        'phone' => '5555555555',
    ]);
    expect($result[2])->toBeNull();
});

test('TestCustomerModel toArray returns correct array', function () {
    $model = new TestCustomerModel([
        'id' => 123,
        'name' => 'Test Name',
        'email' => 'test@example.com',
        'status' => 'inactive',
        'phone' => '555-5555',
    ]);
    $array = $model->toArray();
    expect($array)->toBe([
        'id' => 123,
        'name' => 'Test Name',
        'email' => 'test@example.com',
        'status' => 'inactive',
        'phone' => '555-5555',
    ]);
});

test('TestCustomerResource toArray returns correct array', function () {
    $model = new TestCustomerModel([
        'id' => 321,
        'name' => 'Resource Name',
        'email' => 'resource@example.com',
        'status' => 'active',
        'phone' => '777-7777',
    ]);
    $resource = new TestCustomerResource($model);
    $array = $resource->toArray(new DummyRequest());
    expect($array)->toBe([
        'id' => 321,
        'name' => 'Resource Name',
        'email' => 'resource@example.com',
        'status' => 'active',
        'phone' => '777-7777',
    ]);
});

test('TestCustomerCollection toArray returns correct array for single model', function () {
    $model = new TestCustomerModel([
        'id' => 555,
        'name' => 'Single Model',
        'email' => 'single@example.com',
        'status' => 'active',
        'phone' => '1231231234',
    ]);
    $collection = new Collection([$model]);
    $customerCollection = new TestCustomerCollection($collection);
    $array = $customerCollection->toArray(new DummyRequest());
    expect($array)->toBe([
        [
            'id' => 555,
            'name' => 'Single Model',
            'email' => 'single@example.com',
            'status' => 'active',
            'phone' => '1231231234',
        ]
    ]);
});

test('TestCustomerCollection toArray returns correct array for empty collection', function () {
    $collection = new Collection([]);
    $customerCollection = new TestCustomerCollection($collection);
    $array = $customerCollection->toArray(new DummyRequest());
    expect($array)->toBe([]);
});

test('DummyRequest has, get, all methods work as expected', function () {
    $request = new DummyRequest(['foo' => 'bar', 'baz' => 'qux']);
    expect($request->has('foo'))->toBeTrue();
    expect($request->has('baz'))->toBeTrue();
    expect($request->has('notfound'))->toBeFalse();
    expect($request->get('foo'))->toBe('bar');
    expect($request->get('baz'))->toBe('qux');
    expect($request->get('notfound', 'default'))->toBe('default');
    expect($request->all())->toBe(['foo' => 'bar', 'baz' => 'qux']);
    expect($request->all(['foo']))->toBe(['foo' => 'bar']);
    expect($request->all(['baz', 'notfound']))->toBe(['baz' => 'qux', 'notfound' => null]);
});

test('TestCustomerModel handles missing keys in constructor', function () {
    $model = new TestCustomerModel([]);
    expect($model->id)->toBe(999);
    expect($model->name)->toBe('Dummy');
    expect($model->email)->toBe('dummy@example.com');
    expect($model->status)->toBe('active');
    expect($model->phone)->toBeNull();
});

test('TestCustomerCollection handles collection with only nulls', function () {
    $collection = new Collection([null, null, null]);
    $customerCollection = new TestCustomerCollection($collection);
    $array = $customerCollection->toArray(new DummyRequest());
    expect($array)->toBe([null, null, null]);
});

afterEach(function () {
    // No mocking needed
});