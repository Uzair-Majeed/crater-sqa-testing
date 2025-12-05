<?php

use Crater\Http\Resources\RoleCollection;
use Crater\Http\Requests\RoleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// ========== MERGED ROLE TESTS (2 CLASSES, 14 FUNCTIONAL TESTS) ==========

// --- RoleCollection Tests (6 tests: 3 structural + 3 FUNCTIONAL) ---

test('RoleCollection can be instantiated', function () {
    $collection = new RoleCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(RoleCollection::class);
});

test('RoleCollection extends ResourceCollection', function () {
    $collection = new RoleCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('RoleCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(RoleCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

// --- FUNCTIONAL TESTS ---

test('RoleCollection toArray returns empty array for empty collection', function () {
    $request = new Request();
    $collection = new RoleCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('RoleCollection toArray method accepts Request parameter', function () {
    $request = new Request(['test' => 'value']);
    $collection = new RoleCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray();
});

test('RoleCollection toArray delegates to parent implementation', function () {
    $request = new Request();
    $collection = new RoleCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    // Verify it returns an array (parent behavior)
    expect($result)->toBeArray();
});

// --- RoleRequest Tests (8 tests: 3 structural + 5 FUNCTIONAL) ---

test('RoleRequest can be instantiated', function () {
    $request = new RoleRequest();
    expect($request)->toBeInstanceOf(RoleRequest::class);
});

test('RoleRequest extends FormRequest', function () {
    $request = new RoleRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

test('RoleRequest is in correct namespace', function () {
    $reflection = new ReflectionClass(RoleRequest::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Requests');
});

// --- FUNCTIONAL TESTS ---

test('RoleRequest authorize returns true', function () {
    $request = new RoleRequest();
    
    $result = $request->authorize();
    
    expect($result)->toBeTrue();
});

test('RoleRequest rules returns array with name and abilities validation', function () {
    $request = new RoleRequest();
    $request->headers->set('company', '123');
    
    $rules = $request->rules();
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('name')
        ->and($rules)->toHaveKey('abilities')
        ->and($rules)->toHaveKey('abilities.*')
        ->and($rules['name'])->toContain('required')
        ->and($rules['name'])->toContain('string')
        ->and($rules['abilities'])->toContain('required')
        ->and($rules['abilities.*'])->toContain('required');
});

test('RoleRequest rules includes unique validation with company scope', function () {
    $request = new RoleRequest();
    $request->headers->set('company', '456');
    
    $rules = $request->rules();
    
    expect($rules['name'])->toBeArray()
        ->and($rules['name'])->toHaveCount(3); // required, string, unique rule
});

test('RoleRequest getRolePayload merges scope from company header', function () {
    $request = new RoleRequest();
    $request->headers->set('company', '789');
    $request->merge(['name' => 'Test Role', 'description' => 'Test Description', 'abilities' => ['read', 'write']]);
    
    $payload = $request->getRolePayload();
    
    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('scope')
        ->and($payload['scope'])->toBe('789')
        ->and($payload)->toHaveKey('name')
        ->and($payload['name'])->toBe('Test Role')
        ->and($payload)->toHaveKey('description')
        ->and($payload['description'])->toBe('Test Description')
        ->and($payload)->not->toHaveKey('abilities'); // abilities should be excluded
});

test('RoleRequest getRolePayload excludes abilities from payload', function () {
    $request = new RoleRequest();
    $request->headers->set('company', '999');
    $request->merge(['name' => 'Admin', 'abilities' => ['manage_all']]);
    
    $payload = $request->getRolePayload();
    
    expect($payload)->not->toHaveKey('abilities')
        ->and($payload)->toHaveKey('name')
        ->and($payload)->toHaveKey('scope');
});