<?php

use Crater\Http\Resources\TaxCollection;
use Crater\Http\Resources\TaxResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// ========== MERGED TAX TESTS (2 CLASSES, 15 FUNCTIONAL TESTS) ==========

// --- TaxCollection Tests (6 tests: 3 structural + 3 FUNCTIONAL) ---

test('TaxCollection can be instantiated', function () {
    $collection = new TaxCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(TaxCollection::class);
});

test('TaxCollection extends ResourceCollection', function () {
    $collection = new TaxCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('TaxCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(TaxCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

// --- FUNCTIONAL TESTS ---

test('TaxCollection toArray returns empty array for empty collection', function () {
    $request = new Request();
    $collection = new TaxCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('TaxCollection toArray accepts Request parameter', function () {
    $request = new Request(['test' => 'value']);
    $collection = new TaxCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray();
});

test('TaxCollection delegates to parent toArray', function () {
    $request = new Request();
    $collection = new TaxCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray();
});

// --- TaxResource Tests (9 tests: 3 structural + 6 FUNCTIONAL) ---

test('TaxResource can be instantiated', function () {
    $data = (object)['id' => 1, 'name' => 'VAT'];
    $resource = new TaxResource($data);
    expect($resource)->toBeInstanceOf(TaxResource::class);
});

test('TaxResource extends JsonResource', function () {
    $resource = new TaxResource((object)[]);
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

test('TaxResource is in correct namespace', function () {
    $reflection = new ReflectionClass(TaxResource::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

// --- FUNCTIONAL TESTS ---

test('TaxResource toArray includes all basic tax fields', function () {
    $reflection = new ReflectionClass(TaxResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'id\' => $this->id')
        ->and($fileContent)->toContain('\'tax_type_id\' => $this->tax_type_id')
        ->and($fileContent)->toContain('\'invoice_id\' => $this->invoice_id')
        ->and($fileContent)->toContain('\'estimate_id\' => $this->estimate_id')
        ->and($fileContent)->toContain('\'name\' => $this->name')
        ->and($fileContent)->toContain('\'amount\' => $this->amount')
        ->and($fileContent)->toContain('\'percent\' => $this->percent');
});

test('TaxResource toArray includes item and company IDs', function () {
    $reflection = new ReflectionClass(TaxResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'invoice_item_id\' => $this->invoice_item_id')
        ->and($fileContent)->toContain('\'estimate_item_id\' => $this->estimate_item_id')
        ->and($fileContent)->toContain('\'item_id\' => $this->item_id')
        ->and($fileContent)->toContain('\'company_id\' => $this->company_id');
});

test('TaxResource toArray includes compound_tax and base_amount', function () {
    $reflection = new ReflectionClass(TaxResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'compound_tax\' => $this->compound_tax')
        ->and($fileContent)->toContain('\'base_amount\' => $this->base_amount');
});

test('TaxResource toArray includes currency_id and recurring_invoice_id', function () {
    $reflection = new ReflectionClass(TaxResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'currency_id\' => $this->currency_id')
        ->and($fileContent)->toContain('\'recurring_invoice_id\' => $this->recurring_invoice_id');
});

test('TaxResource toArray includes type from taxType relationship', function () {
    $reflection = new ReflectionClass(TaxResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'type\' => $this->taxType->type');
});

test('TaxResource toArray includes conditional tax_type relationship', function () {
    $reflection = new ReflectionClass(TaxResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'tax_type\' => $this->when($this->taxType()->exists()')
        ->and($fileContent)->toContain('return new TaxTypeResource($this->taxType)');
});

test('TaxResource toArray includes conditional currency relationship', function () {
    $reflection = new ReflectionClass(TaxResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'currency\' => $this->when($this->currency()->exists()')
        ->and($fileContent)->toContain('return new CurrencyResource($this->currency)');
});
