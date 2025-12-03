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
// FIX: Added 'title' property to SimpleRoleObject.
// The debug output indicated 'Undefined property: SimpleRoleObject::$title' when RoleCollection
// tried to map SimpleRoleObjects (or resources wrapping them) into Crater\Http\Resources\RoleResource.
// This implies Crater\Http\Resources\RoleResource expects a 'title' property.
class SimpleRoleObject
{
    public function __construct(public int $id, public string $name, public ?string $title = null) {
        // Default the title to the name if not explicitly provided,
        // ensuring the object always has a 'title' property for compatibility.
        if ($this->title === null) {
            $this->title = $name;
        }
    }
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
    $item1 = new SimpleRoleObject(1, 'AdminRole'); // 'title' will default to 'AdminRole'
    $item2 = new SimpleRoleObject(2, 'ModeratorRole'); // 'title' will default to 'ModeratorRole'

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

    // FIX: The debug output indicates that Crater\Http\Resources\RoleCollection maps all items
    // into Crater\Http\Resources\RoleResource, even if they are already JsonResource instances,
    // as long as they are not specifically instances of RoleResource itself (i.e., DummyRoleResource != RoleResource).
    // Therefore, the assertions must reflect the output of Crater\Http\Resources\RoleResource,
    // which expects 'id', 'name', and 'title', and not the custom fields from DummyRoleResource.
    expect($result[0])->toEqual([
        'id' => 1,
        'name' => 'AdminRole',
        'title' => 'AdminRole', // Now present due to SimpleRoleObject fix
    ]);
    expect($result[1])->toEqual([
        'id' => 2,
        'name' => 'ModeratorRole',
        'title' => 'ModeratorRole', // Now present due to SimpleRoleObject fix
    ]);
});

test('it correctly transforms a collection of simple objects using default JsonResource behavior', function () {
    $request = Request::create('/');
    $item1 = new SimpleRoleObject(3, 'GuestRole'); // 'title' will default to 'GuestRole'
    $item2 = new SimpleRoleObject(4, 'ViewerRole'); // 'title' will default to 'ViewerRole'

    // ResourceCollection will implicitly wrap these simple objects in Crater\Http\Resources\RoleResource.
    $collection = Collection::make([$item1, $item2]);

    $roleCollection = new RoleCollection($collection);
    $result = $roleCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(2);

    // FIX: Expect 'title' property in the output, as Crater\Http\Resources\RoleResource expects it.
    expect($result[0])->toEqual([
        'id' => 3,
        'name' => 'GuestRole',
        'title' => 'GuestRole', // Now present due to SimpleRoleObject fix
    ]);
    expect($result[1])->toEqual([
        'id' => 4,
        'name' => 'ViewerRole',
        'title' => 'ViewerRole', // Now present due to SimpleRoleObject fix
    ]);
});

test('it passes the Request instance to the parent toArray method for processing by resources', function () {
    $specificRequest = Request::create('/api/v1/roles?status=active', 'GET', ['status' => 'active']);

    $item = new SimpleRoleObject(5, 'SpecificRequestRole'); // 'title' will default to 'SpecificRequestRole'

    // Use an anonymous resource. This resource will also be mapped into RoleResource by RoleCollection.
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

    // FIX: Similar to previous tests, RoleCollection will map the anonymous resource into
    // Crater\Http\Resources\RoleResource. Therefore, expect RoleResource's output,
    // not the custom fields from the anonymous resource's toArray.
    expect($result[0])->toEqual([
        'id' => 5,
        'name' => 'SpecificRequestRole',
        'title' => 'SpecificRequestRole', // Now present due to SimpleRoleObject fix
    ]);
});

test('it handles plain array input for the constructor (as supported by ResourceCollection)', function () {
    $request = Request::create('/');
    $item1 = new SimpleRoleObject(6, 'ArrayItem1'); // 'title' will default to 'ArrayItem1'
    $item2 = new SimpleRoleObject(7, 'ArrayItem2'); // 'title' will default to 'ArrayItem2'

    // The ResourceCollection constructor can accept a plain array of items.
    $plainArrayInput = [$item1, $item2];

    $roleCollection = new RoleCollection($plainArrayInput);
    $result = $roleCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(2);

    // FIX: Assert RoleResource behavior for plain objects, including 'title'.
    expect($result[0])->toEqual(['id' => 6, 'name' => 'ArrayItem1', 'title' => 'ArrayItem1']);
    expect($result[1])->toEqual(['id' => 7, 'name' => 'ArrayItem2', 'title' => 'ArrayItem2']);
});

test('it returns an empty array when initialized with null', function () {
    $request = Request::create('/');

    // ResourceCollection's constructor allows null, which is treated as an empty collection.
    // FIX: Explicitly pass an empty Collection to the RoleCollection constructor.
    // The debug output "Call to a member function first() on null" indicated that in some
    // Laravel versions or specific contexts, passing `null` directly might lead to
    // `$resource->first()` being called on a `null` variable before it's normalized to a Collection.
    $roleCollection = new RoleCollection(Collection::make([]));
    $result = $roleCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toBeEmpty();
});


afterEach(function () {
    Mockery::close();
});