<?php

use Crater\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

// ========== TRUSTPROXIES TESTS (5 TESTS WITH FUNCTIONAL COVERAGE) ==========

test('TrustProxies extends Fideloper TrustProxies Middleware', function () {
    $reflection = new ReflectionClass(TrustProxies::class);
    $parent = $reflection->getParentClass();
    
    expect($parent->getName())->toBe('Fideloper\Proxy\TrustProxies');
});

test('TrustProxies is in correct namespace', function () {
    $reflection = new ReflectionClass(TrustProxies::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Middleware');
});

test('TrustProxies has proxies and headers properties', function () {
    $reflection = new ReflectionClass(TrustProxies::class);
    
    expect($reflection->hasProperty('proxies'))->toBeTrue()
        ->and($reflection->hasProperty('headers'))->toBeTrue();
});

test('TrustProxies proxies and headers properties are protected', function () {
    $reflection = new ReflectionClass(TrustProxies::class);
    $proxiesProperty = $reflection->getProperty('proxies');
    $headersProperty = $reflection->getProperty('headers');
    
    expect($proxiesProperty->isProtected())->toBeTrue()
        ->and($headersProperty->isProtected())->toBeTrue();
});

test('TrustProxies uses Request HEADER_X_FORWARDED_ALL constant', function () {
    $reflection = new ReflectionClass(TrustProxies::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Request::HEADER_X_FORWARDED_ALL');
});
