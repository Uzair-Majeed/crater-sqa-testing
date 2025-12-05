<?php

use Crater\Providers\EventServiceProvider;
use Crater\Events\UpdateFinished;
use Illuminate\Auth\Events\Registered;

// ========== CLASS STRUCTURE TESTS ==========

test('EventServiceProvider can be instantiated', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    expect($provider)->toBeInstanceOf(EventServiceProvider::class);
});

test('EventServiceProvider extends ServiceProvider', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    expect($provider)->toBeInstanceOf(\Illuminate\Foundation\Support\Providers\EventServiceProvider::class);
});

test('EventServiceProvider is in correct namespace', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Providers');
});

test('EventServiceProvider is not abstract', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('EventServiceProvider is instantiable', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== LISTEN PROPERTY TESTS ==========

test('EventServiceProvider has listen property', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    expect($reflection->hasProperty('listen'))->toBeTrue();
});

test('listen property is protected', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $property = $reflection->getProperty('listen');
    
    expect($property->isProtected())->toBeTrue();
});

test('listen property contains event mappings', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    expect($listen)->toBeArray()
        ->and($listen)->not->toBeEmpty();
});

test('listen property maps UpdateFinished event', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    expect($listen)->toHaveKey(UpdateFinished::class);
});

test('listen property maps Registered event', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    expect($listen)->toHaveKey(Registered::class);
});

// ========== UPDATE FINISHED EVENT LISTENERS TESTS ==========

test('UpdateFinished event has correct listeners', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    expect($listen[UpdateFinished::class])->toBeArray()
        ->and($listen[UpdateFinished::class])->toHaveCount(8);
});

test('UpdateFinished event includes Version110 listener', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    expect($listen[UpdateFinished::class])->toContain(\Crater\Listeners\Updates\v1\Version110::class);
});

test('UpdateFinished event includes all v2 version listeners', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    expect($listen[UpdateFinished::class])->toContain(\Crater\Listeners\Updates\v2\Version200::class)
        ->and($listen[UpdateFinished::class])->toContain(\Crater\Listeners\Updates\v2\Version201::class)
        ->and($listen[UpdateFinished::class])->toContain(\Crater\Listeners\Updates\v2\Version202::class)
        ->and($listen[UpdateFinished::class])->toContain(\Crater\Listeners\Updates\v2\Version210::class);
});

test('UpdateFinished event includes all v3 version listeners', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    expect($listen[UpdateFinished::class])->toContain(\Crater\Listeners\Updates\v3\Version300::class)
        ->and($listen[UpdateFinished::class])->toContain(\Crater\Listeners\Updates\v3\Version310::class)
        ->and($listen[UpdateFinished::class])->toContain(\Crater\Listeners\Updates\v3\Version311::class);
});

// ========== REGISTERED EVENT LISTENERS TESTS ==========

test('Registered event has correct listeners', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    expect($listen[Registered::class])->toBeArray()
        ->and($listen[Registered::class])->toHaveCount(1);
});

test('Registered event includes SendEmailVerificationNotification listener', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    expect($listen[Registered::class])->toContain(\Illuminate\Auth\Listeners\SendEmailVerificationNotification::class);
});

// ========== BOOT METHOD TESTS ==========

test('EventServiceProvider has boot method', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    expect($reflection->hasMethod('boot'))->toBeTrue();
});

test('boot method is public', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $method = $reflection->getMethod('boot');
    
    expect($method->isPublic())->toBeTrue();
});

test('boot method has no parameters', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $method = $reflection->getMethod('boot');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

test('boot method is not static', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $method = $reflection->getMethod('boot');
    
    expect($method->isStatic())->toBeFalse();
});

test('boot method calls parent boot', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::boot()');
});

// ========== INSTANCE TESTS ==========

test('multiple EventServiceProvider instances can be created', function () {
    $app = app();
    $provider1 = new EventServiceProvider($app);
    $provider2 = new EventServiceProvider($app);
    
    expect($provider1)->toBeInstanceOf(EventServiceProvider::class)
        ->and($provider2)->toBeInstanceOf(EventServiceProvider::class)
        ->and($provider1)->not->toBe($provider2);
});

test('EventServiceProvider can be cloned', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    $clone = clone $provider;
    
    expect($clone)->toBeInstanceOf(EventServiceProvider::class)
        ->and($clone)->not->toBe($provider);
});

test('EventServiceProvider can be used in type hints', function () {
    $testFunction = function (EventServiceProvider $provider) {
        return $provider;
    };
    
    $app = app();
    $provider = new EventServiceProvider($app);
    $result = $testFunction($provider);
    
    expect($result)->toBe($provider);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('EventServiceProvider is not final', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('EventServiceProvider is not an interface', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('EventServiceProvider is not a trait', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('EventServiceProvider class is loaded', function () {
    expect(class_exists(EventServiceProvider::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('EventServiceProvider uses required classes', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Events\UpdateFinished')
        ->and($fileContent)->toContain('use Illuminate\Auth\Events\Registered')
        ->and($fileContent)->toContain('use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider');
});

test('EventServiceProvider imports all version listeners', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Listeners\Updates\v1\Version110')
        ->and($fileContent)->toContain('use Crater\Listeners\Updates\v2\Version200')
        ->and($fileContent)->toContain('use Crater\Listeners\Updates\v3\Version300');
});

// ========== FILE STRUCTURE TESTS ==========

test('EventServiceProvider file has expected structure', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class EventServiceProvider extends ServiceProvider')
        ->and($fileContent)->toContain('protected $listen')
        ->and($fileContent)->toContain('public function boot()');
});

test('EventServiceProvider has compact implementation', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be reasonably sized (< 2000 bytes)
    expect(strlen($fileContent))->toBeLessThan(2000);
});

test('EventServiceProvider has reasonable line count', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(100);
});

// ========== DOCUMENTATION TESTS ==========

test('listen property has documentation', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $property = $reflection->getProperty('listen');
    
    expect($property->getDocComment())->not->toBeFalse();
});

test('boot method has documentation', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $method = $reflection->getMethod('boot');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('boot method has return type documentation', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $method = $reflection->getMethod('boot');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@return');
});

// ========== LISTENER ORDER TESTS ==========

test('UpdateFinished listeners are in version order', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    $listeners = $listen[UpdateFinished::class];
    
    expect($listeners[0])->toBe(\Crater\Listeners\Updates\v1\Version110::class)
        ->and($listeners[1])->toBe(\Crater\Listeners\Updates\v2\Version200::class)
        ->and($listeners[7])->toBe(\Crater\Listeners\Updates\v3\Version311::class);
});

// ========== EVENT MAPPING COUNT TESTS ==========

test('listen property has exactly 2 event mappings', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    expect(count($listen))->toBe(2);
});

test('listen property keys are event classes', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('listen');
    $property->setAccessible(true);
    $listen = $property->getValue($provider);
    
    $keys = array_keys($listen);
    
    expect($keys)->toContain(UpdateFinished::class)
        ->and($keys)->toContain(Registered::class);
});

// ========== PARENT CLASS TESTS ==========

test('EventServiceProvider parent is ServiceProvider', function () {
    $reflection = new ReflectionClass(EventServiceProvider::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe('Illuminate\Foundation\Support\Providers\EventServiceProvider');
});

test('EventServiceProvider inherits from Laravel ServiceProvider', function () {
    $app = app();
    $provider = new EventServiceProvider($app);
    
    expect($provider)->toBeInstanceOf(\Illuminate\Support\ServiceProvider::class);
});