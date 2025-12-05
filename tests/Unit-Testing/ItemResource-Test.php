<?php

use Crater\Http\Resources\ItemResource;
use Illuminate\Http\Request;

// ========== ITEMRESOURCE TESTS (10 MINIMAL TESTS) ==========

test('ItemResource can be instantiated', function () {
    $resource = new ItemResource((object)['id' => 1]);
    expect($resource)->toBeInstanceOf(ItemResource::class);
});

test('ItemResource extends JsonResource', function () {
    $resource = new ItemResource((object)['id' => 1]);
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

test('ItemResource is in correct namespace', function () {
    $reflection = new ReflectionClass(ItemResource::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('ItemResource has toArray method', function () {
    $reflection = new ReflectionClass(ItemResource::class);
    expect($reflection->hasMethod('toArray'))->toBeTrue();
});

test('ItemResource toArray includes basic item fields', function () {
    $reflection = new ReflectionClass(ItemResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'id\' => $this->id')
        ->and($fileContent)->toContain('\'name\' => $this->name')
        ->and($fileContent)->toContain('\'description\' => $this->description')
        ->and($fileContent)->toContain('\'price\' => $this->price');
});

test('ItemResource includes relationship IDs', function () {
    $reflection = new ReflectionClass(ItemResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'unit_id\' => $this->unit_id')
        ->and($fileContent)->toContain('\'company_id\' => $this->company_id')
        ->and($fileContent)->toContain('\'creator_id\' => $this->creator_id')
        ->and($fileContent)->toContain('\'currency_id\' => $this->currency_id');
});

test('ItemResource includes timestamps', function () {
    $reflection = new ReflectionClass(ItemResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'created_at\' => $this->created_at')
        ->and($fileContent)->toContain('\'updated_at\' => $this->updated_at')
        ->and($fileContent)->toContain('\'formatted_created_at\'');
});

test('ItemResource uses when() for conditional relationships', function () {
    $reflection = new ReflectionClass(ItemResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->when(');
});

test('ItemResource includes unit relationship', function () {
    $reflection = new ReflectionClass(ItemResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'unit\' =>')
        ->and($fileContent)->toContain('UnitResource');
});

test('ItemResource includes company and currency relationships', function () {
    $reflection = new ReflectionClass(ItemResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'company\' =>')
        ->and($fileContent)->toContain('CompanyResource')
        ->and($fileContent)->toContain('\'currency\' =>')
        ->and($fileContent)->toContain('CurrencyResource');
});
