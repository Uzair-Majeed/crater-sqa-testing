<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Crater\Http\Resources\RoleCollection;

// Define a simple dummy resource for precise testing outcomes.
// This allows us to control the `toArray` output when ResourceCollection processes it.
class DummyRoleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'processed_by_dummy_resource' => true,
            'request_path_used' => $request->path(),
        ];
    }
}

// Define a simple model/object that ResourceCollection would wrap with a default JsonResource.
class SimpleRoleObject
{
    public function __construct(public int $id, public string $name) {}
}

test('it correctly transforms an empty collection', function () {
    $request = Request::create('/');
    $collection = Collection::make([]);

    $roleCollection = new RoleCollection($collection);
    $result = $roleCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toBeEmpty();
});

test('it correctly transforms a collection of custom JsonResource instances', function () {
    $request = Request::create('/test-path');
    $item1 = new SimpleRoleObject(1, 'AdminRole');
    $item2 = new SimpleRoleObject(2, 'ModeratorRole');

    // Pre-wrap items with our custom DummyRoleResource
    $collection = Collection::make([
        new DummyRoleResource($item1),
        new DummyRoleResource($item2),
    ]);

    $roleCollection = new RoleCollection($collection);
    $result = $roleCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(2);

    expect($result[0])->toEqual([
        'id' => 1,
        'name' => 'AdminRole',
        'processed_by_dummy_resource' => true,
        'request_path_used' => 'test-path',
    ]);
    expect($result[1])->toEqual([
        'id' => 2,
        'name' => 'ModeratorRole',
        'processed_by_dummy_resource' => true,
        'request_path_used' => 'test-path',
    ]);
});

test('it correctly transforms a collection of simple objects using default JsonResource behavior', function () {
    $request = Request::create('/');
    $item1 = new SimpleRoleObject(3, 'GuestRole');
    $item2 = new SimpleRoleObject(4, 'ViewerRole');

    // ResourceCollection will implicitly wrap these simple objects in a default JsonResource.
    // The default JsonResource::toArray() typically returns public properties of the underlying object.
    $collection = Collection::make([$item1, $item2]);

    $roleCollection = new RoleCollection($collection);
    $result = $roleCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(2);

    expect($result[0])->toEqual([
        'id' => 3,
        'name' => 'GuestRole',
    ]);
    expect($result[1])->toEqual([
        'id' => 4,
        'name' => 'ViewerRole',
    ]);
});

test('it passes the Request instance to the parent toArray method for processing by resources', function () {
    // This test verifies that the `$request` parameter is correctly passed down
    // to the underlying resources during collection transformation.
    $specificRequest = Request::create('/api/v1/roles?status=active', 'GET', ['status' => 'active']);

    $item = new SimpleRoleObject(5, 'SpecificRequestRole');

    // Use an anonymous resource to demonstrate the request being used
    $requestAwareResource = new class($item) extends JsonResource {
        public function toArray($request)
        {
            return [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'request_uri' => $request->getUri(),
                'request_query_status' => $request->query('status'),
            ];
        }
    };

    $collection = Collection::make([$requestAwareResource]);

    $roleCollection = new RoleCollection($collection);
    $result = $roleCollection->toArray($specificRequest);

    expect($result)
        ->toBeArray()
        ->toHaveCount(1);

    expect($result[0])->toEqual([
        'id' => 5,
        'name' => 'SpecificRequestRole',
        'request_uri' => 'http://localhost/api/v1/roles?status=active',
        'request_query_status' => 'active',
    ]);
});

test('it handles plain array input for the constructor (as supported by ResourceCollection)', function () {
    $request = Request::create('/');
    $item1 = new SimpleRoleObject(6, 'ArrayItem1');
    $item2 = new SimpleRoleObject(7, 'ArrayItem2');

    // The ResourceCollection constructor can accept a plain array of items.
    $plainArrayInput = [$item1, $item2];

    $roleCollection = new RoleCollection($plainArrayInput);
    $result = $roleCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(2);

    // Assert default JsonResource behavior for plain objects
    expect($result[0])->toEqual(['id' => 6, 'name' => 'ArrayItem1']);
    expect($result[1])->toEqual(['id' => 7, 'name' => 'ArrayItem2']);
});

test('it returns an empty array when initialized with null', function () {
    $request = Request::create('/');

    // ResourceCollection's constructor allows null, which is treated as an empty collection.
    $roleCollection = new RoleCollection(null);
    $result = $roleCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toBeEmpty();
});
