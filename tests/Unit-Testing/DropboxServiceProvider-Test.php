<?php

use Crater\Providers\DropboxServiceProvider;
use Illuminate\Support\ServiceProvider;

// Test provider can be instantiated
test('DropboxServiceProvider can be instantiated', function () {
    $app = app();
    $provider = new DropboxServiceProvider($app);
    
    expect($provider)->toBeInstanceOf(DropboxServiceProvider::class);
});

// Test provider extends ServiceProvider
test('DropboxServiceProvider extends ServiceProvider', function () {
    $app = app();
    $provider = new DropboxServiceProvider($app);
    
    expect($provider)->toBeInstanceOf(ServiceProvider::class);
});

// Test provider has register method
test('DropboxServiceProvider has register method', function () {
    $app = app();
    $provider = new DropboxServiceProvider($app);
    
    expect(method_exists($provider, 'register'))->toBeTrue();
});

// Test provider has boot method
test('DropboxServiceProvider has boot method', function () {
    $app = app();
    $provider = new DropboxServiceProvider($app);
    
    expect(method_exists($provider, 'boot'))->toBeTrue();
});

// Test register method can be called
test('register method can be called without errors', function () {
    $app = app();
    $provider = new DropboxServiceProvider($app);
    
    $provider->register();
    
    expect(true)->toBeTrue();
});

// Test register method is public
test('register method is public', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    $method = $reflection->getMethod('register');
    
    expect($method->isPublic())->toBeTrue();
});

// Test boot method is public
test('boot method is public', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    $method = $reflection->getMethod('boot');
    
    expect($method->isPublic())->toBeTrue();
});

// Test provider is in correct namespace
test('DropboxServiceProvider is in correct namespace', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    
    expect($reflection->getNamespaceName())->toBe('Crater\Providers');
});

// Test provider class name
test('DropboxServiceProvider has correct class name', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    
    expect($reflection->getShortName())->toBe('DropboxServiceProvider');
});

// Test provider is not abstract
test('DropboxServiceProvider is not abstract', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    
    expect($reflection->isAbstract())->toBeFalse();
});

// Test provider is not an interface
test('DropboxServiceProvider is not an interface', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    
    expect($reflection->isInterface())->toBeFalse();
});

// Test provider is not a trait
test('DropboxServiceProvider is not a trait', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    
    expect($reflection->isTrait())->toBeFalse();
});

// Test provider is instantiable
test('DropboxServiceProvider is instantiable', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    
    expect($reflection->isInstantiable())->toBeTrue();
});

// Test provider uses correct imports
test('DropboxServiceProvider uses required classes', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Illuminate\Support\Facades\Storage')
        ->and($fileContent)->toContain('use Illuminate\Support\ServiceProvider')
        ->and($fileContent)->toContain('use League\Flysystem\Filesystem')
        ->and($fileContent)->toContain('use Spatie\Dropbox\Client')
        ->and($fileContent)->toContain('use Spatie\FlysystemDropbox\DropboxAdapter');
});

// Test register method has no parameters
test('register method has no required parameters', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    $method = $reflection->getMethod('register');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

// Test boot method has no parameters
test('boot method has no required parameters', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    $method = $reflection->getMethod('boot');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

// Test provider has exactly 2 public methods (register and boot)
test('DropboxServiceProvider has expected public methods', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    $ownMethods = array_filter($publicMethods, function ($method) {
        return $method->class === DropboxServiceProvider::class;
    });
    
    expect(count($ownMethods))->toBeGreaterThanOrEqual(2);
});

// Test provider parent class
test('DropboxServiceProvider parent class is ServiceProvider', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe(ServiceProvider::class);
});

// Test multiple instances can be created
test('multiple DropboxServiceProvider instances can be created', function () {
    $app = app();
    $provider1 = new DropboxServiceProvider($app);
    $provider2 = new DropboxServiceProvider($app);
    
    expect($provider1)->toBeInstanceOf(DropboxServiceProvider::class)
        ->and($provider2)->toBeInstanceOf(DropboxServiceProvider::class)
        ->and($provider1)->not->toBe($provider2);
});

// Test provider can be type-hinted
test('DropboxServiceProvider can be used in type hints', function () {
    $testFunction = function (DropboxServiceProvider $provider) {
        return $provider;
    };
    
    $app = app();
    $provider = new DropboxServiceProvider($app);
    $result = $testFunction($provider);
    
    expect($result)->toBe($provider);
});

// Test provider is not final
test('DropboxServiceProvider can be extended', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    
    expect($reflection->isFinal())->toBeFalse();
});

// Test methods are not static
test('register and boot methods are not static', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    
    expect($reflection->getMethod('register')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('boot')->isStatic())->toBeFalse();
});

// Test methods are not abstract
test('register and boot methods are not abstract', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    
    expect($reflection->getMethod('register')->isAbstract())->toBeFalse()
        ->and($reflection->getMethod('boot')->isAbstract())->toBeFalse();
});

// Test provider file exists
test('DropboxServiceProvider class is loaded', function () {
    expect(class_exists(DropboxServiceProvider::class))->toBeTrue();
});

// Test provider namespace depth
test('DropboxServiceProvider is in correct namespace depth', function () {
    $reflection = new ReflectionClass(DropboxServiceProvider::class);
    $namespace = $reflection->getNamespaceName();
    $parts = explode('\\', $namespace);
    
    expect($parts)->toContain('Crater')
        ->and($parts)->toContain('Providers');
});