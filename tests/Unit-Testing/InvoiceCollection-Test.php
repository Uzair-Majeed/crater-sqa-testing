<?php

use Crater\Http\Resources\InvoiceCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// ========== MINIMAL UNIT TESTS (NO MOCKERY, 100% COVERAGE) ==========

test('InvoiceCollection can be instantiated', function () {
    $collection = new InvoiceCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(InvoiceCollection::class);
});

test('InvoiceCollection extends ResourceCollection', function () {
    $collection = new InvoiceCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('InvoiceCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(InvoiceCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('InvoiceCollection has toArray method', function () {
    $collection = new InvoiceCollection(new Collection([]));
    expect(method_exists($collection, 'toArray'))->toBeTrue();
});

test('toArray returns empty array for empty collection', function () {
    $request = new Request();
    $collection = new InvoiceCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('toArray delegates to parent ResourceCollection', function () {
    $reflection = new ReflectionClass(InvoiceCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

test('toArray method is public', function () {
    $reflection = new ReflectionClass(InvoiceCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue()
        ->and($method->isStatic())->toBeFalse();
});

test('toArray method accepts request parameter', function () {
    $reflection = new ReflectionClass(InvoiceCollection::class);
    $method = $reflection->getMethod('toArray');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

test('InvoiceCollection file is concise', function () {
    $reflection = new ReflectionClass(InvoiceCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect(strlen($fileContent))->toBeLessThan(1000);
});

test('InvoiceCollection has minimal line count', function () {
    $reflection = new ReflectionClass(InvoiceCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(30);
});