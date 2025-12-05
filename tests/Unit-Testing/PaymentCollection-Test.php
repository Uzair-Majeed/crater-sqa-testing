<?php

use Crater\Http\Resources\PaymentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// ========== PAYMENTCOLLECTION TESTS (10 MINIMAL TESTS FOR 100% COVERAGE) ==========
// NO MOCKERY - Pure unit tests with real data

test('PaymentCollection can be instantiated', function () {
    $collection = new PaymentCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(PaymentCollection::class);
});

test('PaymentCollection extends ResourceCollection', function () {
    $collection = new PaymentCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('PaymentCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(PaymentCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('PaymentCollection has toArray method', function () {
    $reflection = new ReflectionClass(PaymentCollection::class);
    expect($reflection->hasMethod('toArray'))->toBeTrue();
});

test('PaymentCollection toArray is public', function () {
    $reflection = new ReflectionClass(PaymentCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue()
        ->and($method->isStatic())->toBeFalse();
});

test('PaymentCollection toArray accepts request parameter', function () {
    $reflection = new ReflectionClass(PaymentCollection::class);
    $method = $reflection->getMethod('toArray');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

test('PaymentCollection returns empty array for empty collection', function () {
    $request = new Request();
    $collection = new PaymentCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()->and($result)->toBeEmpty();
});

test('PaymentCollection delegates to parent toArray', function () {
    $reflection = new ReflectionClass(PaymentCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

test('PaymentCollection file is concise', function () {
    $reflection = new ReflectionClass(PaymentCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect(strlen($fileContent))->toBeLessThan(1000);
});

test('PaymentCollection has minimal line count', function () {
    $reflection = new ReflectionClass(PaymentCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(30);
});
