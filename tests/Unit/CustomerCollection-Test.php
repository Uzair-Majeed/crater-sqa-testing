<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Collection; // To simulate Eloquent collections

beforeEach(function () {
    Mockery::close(); // Ensure no mocks from previous tests are lingering
});

test('toArray returns an empty array when initialized with an empty collection', function () {
    $request = Mockery::mock(Request::class);
    $collection = new Collection(); // Represents an empty Eloquent collection
    $customerCollection = new \Crater\Http\Resources\CustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    expect($result)->toBeArray()->toBeEmpty();
});

// For tests using Eloquent model mocks, use a stub real model to prevent DelegatesToResource issues
// CustomerResource tries to access $item->email etc. For correct test isolation, use a dummy object with all required properties.

function getDummyModel($overrides = []) {
    // Always provide id, name, email, as CustomerResource expects those
    $obj = new stdClass();
    $obj->id = $overrides['id'] ?? 999;
    $obj->name = $overrides['name'] ?? 'Dummy';
    $obj->email = $overrides['email'] ?? 'dummy@example.com';
    $obj->status = $overrides['status'] ?? 'active';
    return $obj;
}

test('toArray correctly transforms a collection of simple objects via default JsonResource behavior', function () {
    $request = Mockery::mock(Request::class);

    $model1 = getDummyModel([
        'id' => 1,
        'name' => 'Customer A',
        'email' => 'a@example.com',
        'status' => 'active',
    ]);
    $model2 = getDummyModel([
        'id' => 2,
        'name' => 'Customer B',
        'email' => 'b@example.com',
        'status' => 'inactive',
    ]);

    // Eloquent models usually have toArray() that returns their array representation.
    // Add toArray property to the stdClass.
    $model1->toArray = function() use ($model1) {
        return [
            'id' => $model1->id,
            'name' => $model1->name,
            'email' => $model1->email,
            'status' => $model1->status,
        ];
    };
    $model2->toArray = function() use ($model2) {
        return [
            'id' => $model2->id,
            'name' => $model2->name,
            'email' => $model2->email,
            'status' => $model2->status,
        ];
    };

    // Magic method for stdClass won't be called by JsonResource, so we need a real model or to hack things.
    // Instead, use an anonymous class with a real toArray() method.
    $model1obj = new class($model1) {
        public $id;
        public $name;
        public $email;
        public $status;
        public function __construct($model) {
            $this->id = $model->id;
            $this->name = $model->name;
            $this->email = $model->email;
            $this->status = $model->status;
        }
        public function toArray() {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'status' => $this->status,
            ];
        }
    };
    $model2obj = new class($model2) {
        public $id;
        public $name;
        public $email;
        public $status;
        public function __construct($model) {
            $this->id = $model->id;
            $this->name = $model->name;
            $this->email = $model->email;
            $this->status = $model->status;
        }
        public function toArray() {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'status' => $this->status,
            ];
        }
    };

    $collection = new Collection([$model1obj, $model2obj]);
    $customerCollection = new \Crater\Http\Resources\CustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    $expected = [
        [
            'id' => 1,
            'name' => 'Customer A',
            'email' => 'a@example.com',
            'status' => 'active',
        ],
        [
            'id' => 2,
            'name' => 'Customer B',
            'email' => 'b@example.com',
            'status' => 'inactive',
        ],
    ];

    // Remove extra keys from result for matching
    expect(array_map(function($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'email' => $item['email'],
            'status' => $item['status'],
        ];
    }, $result))->toBe($expected);
});

test('toArray correctly processes a collection of pre-existing JsonResource instances', function () {
    $request = Mockery::mock(Request::class);

    $dummy1 = getDummyModel([
        'id' => 101,
        'name' => 'Alpha Customer',
        'email' => 'alpha@example.com'
    ]);
    $dummy2 = getDummyModel([
        'id' => 102,
        'name' => 'Beta Customer',
        'email' => 'beta@example.com'
    ]);

    // Instead of mocking JsonResource, use real CustomerResource
    $mockResource1 = new \Crater\Http\Resources\CustomerResource($dummy1);
    $mockResource2 = new \Crater\Http\Resources\CustomerResource($dummy2);

    $collection = new Collection([$mockResource1, $mockResource2]);

    $customerCollection = new \Crater\Http\Resources\CustomerCollection($collection);

    $result = $customerCollection->toArray($request);

    $expected = [
        [
            'id' => 101,
            'name' => 'Alpha Customer',
            'email' => 'alpha@example.com',
            'status' => 'active',
        ],
        [
            'id' => 102,
            'name' => 'Beta Customer',
            'email' => 'beta@example.com',
            'status' => 'active',
        ],
    ];

    // Only assert relevant fields, ignore extras
    expect(array_map(function($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'email' => $item['email'],
            'status' => $item['status'],
        ];
    }, $result))->toBe($expected);
});

test('toArray correctly passes the request object to nested transformations when a custom resource type is used in parent', function () {
    // Simulate a CustomerResource that uses request data
    $customRequest = Mockery::mock(Request::class);
    $customRequest->shouldReceive('has')->with('append_param')->andReturn(true);
    $customRequest->shouldReceive('get')->with('append_param')->andReturn('extra_data');

    $dummy = getDummyModel([
        'id' => 1,
        'name' => 'Custom Customer',
        'email' => 'custom@example.com'
    ]);

    // Custom resource that appends request data if given
    $customResource = new class($dummy) extends JsonResource {
        public function toArray($request)
        {
            $base = [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'email' => $this->resource->email,
            ];
            if ($request->has('append_param')) {
                $base['appended'] = $request->get('append_param');
            }
            return $base;
        }
    };

    $collection = new Collection([$customResource]);

    $customerCollection = new \Crater\Http\Resources\CustomerCollection($collection);

    $result = $customerCollection->toArray($customRequest);

    $expected = [
        [
            'id' => 1,
            'name' => 'Custom Customer',
            'email' => 'custom@example.com',
            'appended' => 'extra_data',
        ],
    ];

    expect($result)->toBe($expected);
});

test('toArray handles an empty but valid request object', function () {
    $emptyRequest = Mockery::mock(Request::class);
    $emptyRequest->shouldReceive('all')->andReturn([]); // Simulate an empty request

    $dummy = getDummyModel([
        'id' => 3,
        'name' => 'Customer C',
        'email' => 'c@example.com'
    ]);

    $modelObj = new class($dummy) {
        public $id;
        public $name;
        public $email;
        public $status;
        public function __construct($model) {
            $this->id = $model->id;
            $this->name = $model->name;
            $this->email = $model->email;
            $this->status = $model->status;
        }
        public function toArray() {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'status' => $this->status,
            ];
        }
    };

    $collection = new Collection([$modelObj]);
    $customerCollection = new \Crater\Http\Resources\CustomerCollection($collection);

    $result = $customerCollection->toArray($emptyRequest);

    $expected = [
        [
            'id' => 3,
            'name' => 'Customer C',
            'email' => 'c@example.com',
            'status' => 'active',
        ],
    ];

    expect(array_map(function($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'email' => $item['email'],
            'status' => $item['status'],
        ];
    }, $result))->toBe($expected);
});

afterEach(function () {
    Mockery::close();
});