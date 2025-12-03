<?php

use Crater\Events\ModuleDisabledEvent;

test('ModuleDisabledEvent constructor correctly assigns the module property with a string', function () {
    $moduleName = 'CRM_Module';
    $event = new ModuleDisabledEvent($moduleName);

    expect($event->module)->toBe($moduleName);
});

test('ModuleDisabledEvent constructor correctly assigns the module property with an integer', function () {
    $moduleId = 12345;
    $event = new ModuleDisabledEvent($moduleId);

    expect($event->module)->toBe($moduleId);
});

test('ModuleDisabledEvent constructor correctly assigns the module property with null', function () {
    $module = null;
    $event = new ModuleDisabledEvent($module);

    expect($event->module)->toBeNull();
});

test('ModuleDisabledEvent constructor correctly assigns the module property with an object', function () {
    $moduleObject = new stdClass();
    $moduleObject->id = 1;
    $moduleObject->name = 'Inventory_Module';

    $event = new ModuleDisabledEvent($moduleObject);

    expect($event->module)->toBe($moduleObject)
        ->and($event->module->id)->toBe(1)
        ->and($event->module->name)->toBe('Inventory_Module');
});

test('ModuleDisabledEvent public module property is accessible after construction', function () {
    $moduleValue = 'Settings_Module';
    $event = new ModuleDisabledEvent($moduleValue);

    $reflectionProperty = new ReflectionProperty(ModuleDisabledEvent::class, 'module');
    expect($reflectionProperty->isPublic())->toBeTrue();
    expect($event->module)->toBe($moduleValue);
});




afterEach(function () {
    // This is good practice if Mockery was used.
    // In this specific file, Mockery is not used, but keeping it doesn't harm.
    Mockery::close();
});