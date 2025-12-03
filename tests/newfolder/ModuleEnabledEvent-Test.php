<?php

use Crater\Events\ModuleEnabledEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

test('ModuleEnabledEvent can be instantiated with a string module name', function () {
    $moduleName = 'blog-module';
    $event = new ModuleEnabledEvent($moduleName);

    expect($event)->toBeInstanceOf(ModuleEnabledEvent::class)
        ->and($event->module)->toBe($moduleName);
});

test('ModuleEnabledEvent can be instantiated with an empty string module name', function () {
    $moduleName = '';
    $event = new ModuleEnabledEvent($moduleName);

    expect($event)->toBeInstanceOf(ModuleEnabledEvent::class)
        ->and($event->module)->toBe($moduleName);
});

test('ModuleEnabledEvent can be instantiated with a numeric module name', function () {
    $moduleName = 123;
    $event = new ModuleEnabledEvent($moduleName);

    expect($event)->toBeInstanceOf(ModuleEnabledEvent::class)
        ->and($event->module)->toBe($moduleName);
});

test('ModuleEnabledEvent can be instantiated with a null module name', function () {
    $moduleName = null;
    $event = new ModuleEnabledEvent($moduleName);

    expect($event)->toBeInstanceOf(ModuleEnabledEvent::class)
        ->and($event->module)->toBeNull();
});

test('ModuleEnabledEvent can be instantiated with an object as module name', function () {
    $moduleName = new stdClass();
    $moduleName->name = 'object-module';
    $event = new ModuleEnabledEvent($moduleName);

    expect($event)->toBeInstanceOf(ModuleEnabledEvent::class)
        ->and($event->module)->toBe($moduleName);
});

test('ModuleEnabledEvent uses the Dispatchable trait', function () {
    $uses = class_uses(ModuleEnabledEvent::class);
    expect($uses)->toContain(Dispatchable::class);
});

test('ModuleEnabledEvent uses the InteractsWithSockets trait', function () {
    $uses = class_uses(ModuleEnabledEvent::class);
    expect($uses)->toContain(InteractsWithSockets::class);
});

test('ModuleEnabledEvent uses the SerializesModels trait', function () {
    $uses = class_uses(ModuleEnabledEvent::class);
    expect($uses)->toContain(SerializesModels::class);
});




afterEach(function () {
    Mockery::close();
});