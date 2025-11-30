<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

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
    $userData = ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
    $collection = new Collection([$userData]);
    $userCollection = new \Crater\Http\Resources\UserCollection($collection);
    $request = new Request();

    $result = $userCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(1)
        ->toEqual([$userData]);
});

test('UserCollection toArray returns multiple items for a collection with multiple simple arrays', function () {
    $userData1 = ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
    $userData2 = ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'];
    $collection = new Collection([$userData1, $userData2]);
    $userCollection = new \Crater\Http\Resources\UserCollection($collection);
    $request = new Request();

    $result = $userCollection->toArray($request);

    expect($result)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([$userData1, $userData2]);
});

test('UserCollection toArray correctly transforms Eloquent models by calling their toArray method', function () {
    // We create an anonymous class that extends Model to simulate an Eloquent model
    // without needing a database or actual model class.
    $mockModel1 = new class extends Model {
        protected $attributes = ['id' => 1, 'name' => 'Model Alice', 'status' => 'active'];
        public function toArray() {
            // Simulate typical model toArray behavior
            return $this->attributes;
        }
    };
    $mockModel2 = new class extends Model {
        protected $attributes = ['id' => 2, 'name' => 'Model Bob', 'status' => 'inactive'];
        public function toArray() {
            return $this->attributes;
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
    $userData = ['id' => 1, 'name' => 'Alice'];
    $collection = new Collection([$userData]);
    $userCollection = new \Crater\Http\Resources\UserCollection($collection);

    // Create a request with some query parameters (though UserCollection itself doesn't use them)
    $requestWithParams = Request::create('/api/users?include=details', 'GET');
    $result1 = $userCollection->toArray($requestWithParams);
    expect($result1)->toEqual([$userData]);

    // Create a simple request
    $simpleRequest = Request::create('/api/users', 'GET');
    $result2 = $userCollection->toArray($simpleRequest);
    expect($result2)->toEqual([$userData]);
});
