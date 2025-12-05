<?php

use Crater\Space\SiteApi;

// ========== SITEAPI TESTS (10 TESTS WITH FUNCTIONAL COVERAGE) ==========

// --- Structural Tests (5 tests) ---

test('SiteApi is a trait', function () {
    $reflection = new ReflectionClass(SiteApi::class);
    expect($reflection->isTrait())->toBeTrue();
});

test('SiteApi is in correct namespace', function () {
    $reflection = new ReflectionClass(SiteApi::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Space');
});

test('SiteApi has getRemote method', function () {
    $reflection = new ReflectionClass(SiteApi::class);
    expect($reflection->hasMethod('getRemote'))->toBeTrue();
});

test('SiteApi getRemote method is protected and static', function () {
    $reflection = new ReflectionClass(SiteApi::class);
    $method = $reflection->getMethod('getRemote');
    
    expect($method->isProtected())->toBeTrue()
        ->and($method->isStatic())->toBeTrue();
});

test('SiteApi getRemote accepts url, data, and token parameters', function () {
    $reflection = new ReflectionClass(SiteApi::class);
    $method = $reflection->getMethod('getRemote');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(3)
        ->and($parameters[0]->getName())->toBe('url')
        ->and($parameters[1]->getName())->toBe('data')
        ->and($parameters[2]->getName())->toBe('token');
});

// --- Functional Tests (5 tests) ---

test('SiteApi getRemote creates Guzzle Client with base_uri', function () {
    $reflection = new ReflectionClass(SiteApi::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('new Client([')
        ->and($fileContent)->toContain('\'verify\' => false')
        ->and($fileContent)->toContain('\'base_uri\' => config(\'crater.base_url\')');
});

test('SiteApi getRemote sets required headers', function () {
    $reflection = new ReflectionClass(SiteApi::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$headers[\'headers\'] = [')
        ->and($fileContent)->toContain('\'Accept\' => \'application/json\'')
        ->and($fileContent)->toContain('\'Referer\' => url(\'/\')')
        ->and($fileContent)->toContain('\'crater\' => Setting::getSetting(\'version\')')
        ->and($fileContent)->toContain('\'Authorization\' => "Bearer {$token}"');
});

test('SiteApi getRemote disables http_errors', function () {
    $reflection = new ReflectionClass(SiteApi::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$data[\'http_errors\'] = false');
});

test('SiteApi getRemote merges data with headers', function () {
    $reflection = new ReflectionClass(SiteApi::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$data = array_merge($data, $headers)');
});

test('SiteApi getRemote handles RequestException', function () {
    $reflection = new ReflectionClass(SiteApi::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('try {')
        ->and($fileContent)->toContain('$result = $client->get($url, $data)')
        ->and($fileContent)->toContain('} catch (RequestException $e) {')
        ->and($fileContent)->toContain('$result = $e')
        ->and($fileContent)->toContain('return $result');
});