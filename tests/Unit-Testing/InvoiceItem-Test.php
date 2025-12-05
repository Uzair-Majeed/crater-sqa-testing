<?php

use Crater\Models\InvoiceItem;
use Crater\Http\Resources\InvoiceItemResource;
use Crater\Http\Resources\InvoiceItemCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// ========== MERGED INVOICEITEM TESTS (3 CLASSES, MAX 10 TESTS) ==========

// --- InvoiceItem Model Tests (3 tests) ---

test('InvoiceItem model exists and has relationships', function () {
    $item = new InvoiceItem();
    
    expect($item)->toBeInstanceOf(InvoiceItem::class)
        ->and(method_exists($item, 'invoice'))->toBeTrue()
        ->and(method_exists($item, 'item'))->toBeTrue()
        ->and(method_exists($item, 'taxes'))->toBeTrue()
        ->and(method_exists($item, 'recurringInvoice'))->toBeTrue();
});

test('InvoiceItem has scope methods and casts', function () {
    $reflection = new ReflectionClass(InvoiceItem::class);
    
    expect($reflection->hasMethod('scopeWhereCompany'))->toBeTrue()
        ->and($reflection->hasMethod('scopeInvoicesBetween'))->toBeTrue()
        ->and($reflection->hasMethod('scopeApplyInvoiceFilters'))->toBeTrue()
        ->and($reflection->hasMethod('scopeItemAttributes'))->toBeTrue();
    
    $item = new InvoiceItem();
    $casts = $item->getCasts();
    
    expect($casts)->toHaveKey('price')
        ->and($casts)->toHaveKey('total')
        ->and($casts)->toHaveKey('quantity');
});

test('InvoiceItem uses HasCustomFieldsTrait', function () {
    $reflection = new ReflectionClass(InvoiceItem::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Crater\Traits\HasCustomFieldsTrait');
});

// --- InvoiceItemResource Tests (4 tests) ---

test('InvoiceItemResource toArray returns required fields', function () {
    $reflection = new ReflectionClass(InvoiceItemResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'id\' => $this->id')
        ->and($fileContent)->toContain('\'name\' => $this->name')
        ->and($fileContent)->toContain('\'price\' => $this->price')
        ->and($fileContent)->toContain('\'quantity\' => $this->quantity')
        ->and($fileContent)->toContain('\'total\' => $this->total');
});

test('InvoiceItemResource extends JsonResource', function () {
    $resource = new InvoiceItemResource((object)['id' => 1]);
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

test('InvoiceItemResource is in correct namespace', function () {
    $reflection = new ReflectionClass(InvoiceItemResource::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('InvoiceItemResource has toArray method', function () {
    $reflection = new ReflectionClass(InvoiceItemResource::class);
    expect($reflection->hasMethod('toArray'))->toBeTrue();
});

// --- InvoiceItemCollection Tests (3 tests) ---

test('InvoiceItemCollection extends ResourceCollection', function () {
    $collection = new InvoiceItemCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('InvoiceItemCollection returns empty array for empty collection', function () {
    $request = new Request();
    $collection = new InvoiceItemCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()->and($result)->toBeEmpty();
});

test('InvoiceItemCollection delegates to parent', function () {
    $reflection = new ReflectionClass(InvoiceItemCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});