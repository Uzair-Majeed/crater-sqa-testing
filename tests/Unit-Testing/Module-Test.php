<?php

use Crater\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
uses(\Mockery::class);

beforeEach(function () {
    // Ensure Mockery is closed after each test to prevent test pollution
    Mockery::close();
});

test('Module model exists and extends Eloquent Model', function () {
    $module = new Module();
    expect($module)->toBeInstanceOf(Module::class);
    expect($module)->toBeInstanceOf(Model::class);
});

test('Module model uses HasFactory trait', function () {
    // Directly check if the trait is used by the class
    expect(class_uses(Module::class))->toHaveKey(HasFactory::class);
});

test('Module model correctly configures "guarded" properties', function () {
    $module = new Module();
    // Use getGuarded() to access the protected property
    expect($module->getGuarded())->toBe(['id']);
    expect($module->getGuarded())->not->toContain('name'); // Ensure no other unexpected guarded properties
});

test('Module model allows mass assignment for non-guarded attributes', function () {
    $module = new Module();
    $attributes = [
        'name' => 'Test Module',
        'display_name' => 'Test Module Display',
        'enabled' => true,
        'order' => 1,
    ];
    $module->fill($attributes);

    expect($module->name)->toBe('Test Module');
    expect($module->display_name)->toBe('Test Module Display');
    expect($module->enabled)->toBeTrue();
    expect($module->order)->toBe(1);
});

test('Module model prevents mass assignment for guarded attributes like "id"', function () {
    $module = new Module();

    // Attempt to mass assign 'id' along with other attributes
    $module->fill(['id' => 999, 'name' => 'Test Module']);

    // Because 'id' is guarded, it should not be set by fill().
    // For a new model, 'id' remains null until saved to the database.
    expect($module->id)->toBeNull();
    expect($module->name)->toBe('Test Module');
});

test('Module model factory method returns a Factory instance for the correct model', function () {
    // Call the static factory method
    $factory = Module::factory();

    // Assert that it returns an instance of Laravel's Factory
    expect($factory)->toBeInstanceOf(Factory::class);
    // Assert that the factory is configured for the Module model
    expect($factory->modelName())->toBe(Module::class);
});
