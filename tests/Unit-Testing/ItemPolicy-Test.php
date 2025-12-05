<?php

use Crater\Policies\ItemPolicy;
use Crater\Models\User;
use Crater\Models\Item;

// ========== ITEMPOLICY TESTS (12 MINIMAL TESTS) ==========

test('ItemPolicy can be instantiated', function () {
    $policy = new ItemPolicy();
    expect($policy)->toBeInstanceOf(ItemPolicy::class);
});

test('ItemPolicy is in correct namespace', function () {
    $reflection = new ReflectionClass(ItemPolicy::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Policies');
});

test('ItemPolicy uses HandlesAuthorization trait', function () {
    $reflection = new ReflectionClass(ItemPolicy::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Auth\Access\HandlesAuthorization');
});

test('ItemPolicy has all authorization methods', function () {
    $reflection = new ReflectionClass(ItemPolicy::class);
    
    expect($reflection->hasMethod('viewAny'))->toBeTrue()
        ->and($reflection->hasMethod('view'))->toBeTrue()
        ->and($reflection->hasMethod('create'))->toBeTrue()
        ->and($reflection->hasMethod('update'))->toBeTrue()
        ->and($reflection->hasMethod('delete'))->toBeTrue()
        ->and($reflection->hasMethod('restore'))->toBeTrue()
        ->and($reflection->hasMethod('forceDelete'))->toBeTrue()
        ->and($reflection->hasMethod('deleteMultiple'))->toBeTrue();
});

test('ItemPolicy viewAny method accepts User parameter', function () {
    $reflection = new ReflectionClass(ItemPolicy::class);
    $method = $reflection->getMethod('viewAny');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('user')
        ->and($parameters[0]->getType()->getName())->toContain('User');
});

test('ItemPolicy view method accepts User and Item parameters', function () {
    $reflection = new ReflectionClass(ItemPolicy::class);
    $method = $reflection->getMethod('view');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('user')
        ->and($parameters[1]->getName())->toBe('item');
});

test('ItemPolicy methods use BouncerFacade for authorization', function () {
    $reflection = new ReflectionClass(ItemPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('BouncerFacade::can(')
        ->and($fileContent)->toContain('view-item')
        ->and($fileContent)->toContain('create-item')
        ->and($fileContent)->toContain('edit-item')
        ->and($fileContent)->toContain('delete-item');
});

test('ItemPolicy view method checks company ownership', function () {
    $reflection = new ReflectionClass(ItemPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$user->hasCompany($item->company_id)');
});

test('ItemPolicy all methods return boolean', function () {
    $reflection = new ReflectionClass(ItemPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('return true')
        ->and($fileContent)->toContain('return false');
});

test('ItemPolicy create method checks create-item permission', function () {
    $reflection = new ReflectionClass(ItemPolicy::class);
    $method = $reflection->getMethod('create');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('BouncerFacade::can(\'create-item\', Item::class)');
});

test('ItemPolicy update method checks edit-item permission', function () {
    $reflection = new ReflectionClass(ItemPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('BouncerFacade::can(\'edit-item\', $item)');
});

test('ItemPolicy deleteMultiple method checks delete-item permission', function () {
    $reflection = new ReflectionClass(ItemPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('BouncerFacade::can(\'delete-item\', Item::class)');
});