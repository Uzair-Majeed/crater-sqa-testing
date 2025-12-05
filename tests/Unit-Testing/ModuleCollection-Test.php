<?php

use Crater\Http\Resources\ModuleCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// ========== MODULECOLLECTION TESTS (7 MINIMAL TESTS FOR 100% COVERAGE) ==========

test('ModuleCollection can be instantiated', function () {
    $collection = new ModuleCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(ModuleCollection::class);
});

test('ModuleCollection extends ResourceCollection', function () {
    $collection = new ModuleCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('ModuleCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(ModuleCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('ModuleCollection has toArray method', function () {
    $reflection = new ReflectionClass(ModuleCollection::class);
    expect($reflection->hasMethod('toArray'))->toBeTrue();
});

test('ModuleCollection returns empty array for empty collection', function () {
    $request = new Request();
    $collection = new ModuleCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()->and($result)->toBeEmpty();
});

test('ModuleCollection delegates to parent toArray', function () {
    $reflection = new ReflectionClass(ModuleCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

test('ModuleCollection is concise', function () {
    $reflection = new ReflectionClass(ModuleCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect(strlen($fileContent))->toBeLessThan(1000);
});
