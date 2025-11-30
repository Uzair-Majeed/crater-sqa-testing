<?php

use Crater\Http\Middleware\TrustProxies;
use Fideloper\Proxy\TrustProxies as FideloperTrustProxies;
use Illuminate\Http\Request;

test('TrustProxies class exists and extends the correct parent middleware', function () {
    $middleware = new TrustProxies();
    expect($middleware)->toBeInstanceOf(FideloperTrustProxies::class);
});

test('TrustProxies has the expected initial value for the protected proxies property', function () {
    $middleware = new TrustProxies();
    $reflection = new ReflectionClass($middleware);
    $proxiesProperty = $reflection->getProperty('proxies');
    $proxiesProperty->setAccessible(true);

    // As per the class definition, $proxies is declared but not initialized,
    // so its default value in PHP will be null.
    expect($proxiesProperty->getValue($middleware))->toBeNull();
});

test('TrustProxies has the expected initial value for the protected headers property', function () {
    $middleware = new TrustProxies();
    $reflection = new ReflectionClass($middleware);
    $headersProperty = $reflection->getProperty('headers');
    $headersProperty->setAccessible(true);

    expect($headersProperty->getValue($middleware))->toBe(Request::HEADER_X_FORWARDED_ALL);
});

test('The protected proxies property can be dynamically set and retrieved via reflection', function () {
    $middleware = new TrustProxies();
    $reflection = new ReflectionClass($middleware);
    $proxiesProperty = $reflection->getProperty('proxies');
    $proxiesProperty->setAccessible(true);

    $testProxies = ['192.168.1.1', '10.0.0.0/8'];
    $proxiesProperty->setValue($middleware, $testProxies);

    expect($proxiesProperty->getValue($middleware))->toBe($testProxies)
        ->and($proxiesProperty->getValue($middleware))->toBeArray()
        ->and(count($proxiesProperty->getValue($middleware)))->toBe(2);

    $emptyProxies = [];
    $proxiesProperty->setValue($middleware, $emptyProxies);
    expect($proxiesProperty->getValue($middleware))->toBe($emptyProxies);
});

test('The protected headers property can be dynamically set and retrieved via reflection', function () {
    $middleware = new TrustProxies();
    $reflection = new ReflectionClass($middleware);
    $headersProperty = $reflection->getProperty('headers');
    $headersProperty->setAccessible(true);

    // Test with a different combination of headers
    $testHeaders = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST;
    $headersProperty->setValue($middleware, $testHeaders);

    expect($headersProperty->getValue($middleware))->toBe($testHeaders);

    // Test with a single header constant
    $singleHeader = Request::HEADER_X_FORWARDED_PROTO;
    $headersProperty->setValue($middleware, $singleHeader);

    expect($headersProperty->getValue($middleware))->toBe($singleHeader);
});

// Test that the `headers` property actually holds the value of the constant
test('The headers property stores the correct integer value for HEADER_X_FORWARDED_ALL', function () {
    $middleware = new TrustProxies();
    $reflection = new ReflectionClass($middleware);
    $headersProperty = $reflection->getProperty('headers');
    $headersProperty->setAccessible(true);

    expect($headersProperty->getValue($middleware))->toBe(Request::HEADER_X_FORWARDED_ALL)
        ->and(is_int($headersProperty->getValue($middleware)))->toBeTrue();
});
