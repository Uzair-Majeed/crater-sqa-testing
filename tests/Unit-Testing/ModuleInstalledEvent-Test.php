<?php

use Crater\Events\ModuleInstalledEvent;

test('module installed event constructor correctly assigns the module property', function () {
    // Test with a string module name
    $moduleName = 'invoice-generator';
    $event = new ModuleInstalledEvent($moduleName);
    expect($event->module)->toBe($moduleName);
    expect($event->module)->toBeString();

    // Test with an object representing a module (e.g., an Eloquent model or a DTO)
    $moduleObject = new class {
        public string $name = 'payments';
        public string $version = '1.0.0';
        public bool $active = true;
    };
    $event = new ModuleInstalledEvent($moduleObject);
    expect($event->module)->toBe($moduleObject);
    expect($event->module)->toBeObject();
    expect($event->module->name)->toBe('payments');

    // Test with an associative array as module data
    $moduleArray = [
        'id' => 123,
        'slug' => 'inventory-management',
        'status' => 'installed'
    ];
    $event = new ModuleInstalledEvent($moduleArray);
    expect($event->module)->toBe($moduleArray);
    expect($event->module)->toBeArray();
    expect($event->module['slug'])->toBe('inventory-management');

    // Test with a null module (edge case, though unlikely in practice for an installed module)
    $event = new ModuleInstalledEvent(null);
    expect($event->module)->toBeNull();

    // Test with an empty string module
    $event = new ModuleInstalledEvent('');
    expect($event->module)->toBe('');

    // Test with a numeric module (edge case)
    $moduleId = 456;
    $event = new ModuleInstalledEvent($moduleId);
    expect($event->module)->toBe($moduleId);
    expect($event->module)->toBeInt();
});

// For this simple event class, there are no other public, protected, or private methods
// with complex logic, branches, conditions, or external dependencies to mock.
// The traits (Dispatchable, InteractsWithSockets, SerializesModels) are framework-level concerns
// and their internal workings are assumed to be tested by the framework itself.
// This test comprehensively covers the constructor's assignment logic and various input types.
