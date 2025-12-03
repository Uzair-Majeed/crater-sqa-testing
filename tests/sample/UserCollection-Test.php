<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Mockery; // Ensure Mockery is imported for afterEach

test('UserCollection toArray returns an empty array for an empty collection', function () {
    $collection = new Collection();
    $userCollection = new \Crater\Http\Resources\UserCollection($collection);
    $request = new Request();

    $result = $userCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toBeEmpty();
});

test('UserCollection toArray returns a single item for a collection with one simple array', function () {
    // FIX: Convert array to a stdClass object or cast to (object).
    // The underlying JsonResource expects an object to access properties like $this->resource->id.
    // The expected output will still be an array, as JsonResource::toArray() converts it back.
    $userData = (object)['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
    $collection = new Collection([$userData]);
    $userCollection = new \Crater\Http\Resources\UserCollection($collection);
    $request = new Request();

    $result = $userCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(1)
        ->toEqual([
            (array)$userData // Expect array output from JsonResource
        ]);
});

test('UserCollection toArray returns multiple items for a collection with multiple simple arrays', function () {
    // FIX: Convert arrays to stdClass objects to satisfy JsonResource's property access.
    $userData1 = (object)['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
    $userData2 = (object)['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'];
    $collection = new Collection([$userData1, $userData2]);
    $userCollection = new \Crater\Http\Resources\UserCollection($collection);
    $request = new Request();

    $result = $userCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([
            (array)$userData1, // Expect array output from JsonResource
            (array)$userData2
        ]);
});

test('UserCollection toArray correctly transforms Eloquent models by calling their toArray method', function () {
    // FIX: Add `isOwner()` method to the anonymous model.
    // The UserResource likely attempts to call this method, leading to BadMethodCallException.
    $mockModel1 = new class extends Model {
        protected $attributes = ['id' => 1, 'name' => 'Model Alice', 'status' => 'active'];
        public function toArray() {
            // Simulate typical model toArray behavior
            return $this->attributes;
        }
        // Add the method that UserResource expects to call
        public function isOwner(): bool {
            return false; // Return a dummy boolean value
        }
    };
    $mockModel2 = new class extends Model {
        protected $attributes = ['id' => 2, 'name' => 'Model Bob', 'status' => 'inactive'];
        public function toArray() {
            return $this->attributes;
        }
        // Add the method that UserResource expects to call
        public function isOwner(): bool {
            return true; // Return a dummy boolean value
        }
    };

    $collection = new Collection([$mockModel1, $mockModel2]);
    $userCollection = new \Crater\Http\Resources\UserCollection($collection);
    $request = new Request();

    $result = $userCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([
            ['id' => 1, 'name' => 'Model Alice', 'status' => 'active'],
            ['id' => 2, 'name' => 'Model Bob', 'status' => 'inactive'],
        ]);
});

test('UserCollection toArray handles different Request instances gracefully', function () {
    // FIX: Convert array to stdClass object.
    $userData = (object)['id' => 1, 'name' => 'Alice'];
    $collection = new Collection([$userData]);
    $userCollection = new \Crater\Http\Resources\UserCollection($collection);

    // Create a request with some query parameters (though UserCollection itself doesn't use them)
    $requestWithParams = Request::create('/api/users?include=details', 'GET');
    $result1 = $userCollection->toArray($requestWithParams);
    expect($result1)->toEqual([ (array)$userData ]); // FIX: Expected output is array

    // Create a simple request
    $simpleRequest = Request::create('/api/users', 'GET');
    $result2 = $userCollection->toArray($simpleRequest);
    expect($result2)->toEqual([ (array)$userData ]); // FIX: Expected output is array
});


afterEach(function () {
    Mockery::close();
});