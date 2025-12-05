<?php

use Crater\Models\Estimate;
use Crater\Models\EstimateItem;
use Crater\Models\Item;
use Crater\Models\Tax;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ========== CLASS STRUCTURE TESTS ==========

test('EstimateItem can be instantiated', function () {
    $item = new EstimateItem();
    expect($item)->toBeInstanceOf(EstimateItem::class);
});

test('EstimateItem extends Model', function () {
    $item = new EstimateItem();
    expect($item)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
});

test('EstimateItem is in correct namespace', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Models');
});

test('EstimateItem is not abstract', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('EstimateItem is instantiable', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== GUARDED PROPERTIES TESTS ==========

test('EstimateItem has guarded properties', function () {
    $item = new EstimateItem();
    expect($item->getGuarded())->toBe(['id']);
});

test('guarded property prevents id from mass assignment', function () {
    $item = new EstimateItem();
    $guarded = $item->getGuarded();
    
    expect($guarded)->toContain('id');
});

// ========== CASTS TESTS ==========

test('price is cast to integer', function () {
    $item = new EstimateItem();
    $item->price = 10000;
    
    expect($item->price)->toBeInt()
        ->and($item->price)->toBe(10000);
});

test('total is cast to integer', function () {
    $item = new EstimateItem();
    $item->total = 20000;
    
    expect($item->total)->toBeInt()
        ->and($item->total)->toBe(20000);
});

test('discount is cast to float', function () {
    $item = new EstimateItem();
    $item->discount = 15.5;
    
    expect($item->discount)->toBeFloat()
        ->and($item->discount)->toBe(15.5);
});

test('quantity is cast to float', function () {
    $item = new EstimateItem();
    $item->quantity = 2.5;
    
    expect($item->quantity)->toBeFloat()
        ->and($item->quantity)->toBe(2.5);
});

test('discount_val is cast to integer', function () {
    $item = new EstimateItem();
    $item->discount_val = 500;
    
    expect($item->discount_val)->toBeInt()
        ->and($item->discount_val)->toBe(500);
});

test('tax is cast to integer', function () {
    $item = new EstimateItem();
    $item->tax = 100;
    
    expect($item->tax)->toBeInt()
        ->and($item->tax)->toBe(100);
});

// ========== STRING TO NUMERIC CASTING TESTS ==========

test('price casts string to integer', function () {
    $item = new EstimateItem();
    $item->price = "10000";
    
    expect($item->price)->toBeInt()
        ->and($item->price)->toBe(10000);
});

test('discount casts string to float', function () {
    $item = new EstimateItem();
    $item->discount = "15.5";
    
    expect($item->discount)->toBeFloat()
        ->and($item->discount)->toBe(15.5);
});

// ========== NULL CASTING TESTS ==========

test('price handles null values', function () {
    $item = new EstimateItem();
    $item->price = null;
    
    expect($item->price)->toBeNull();
});

test('total handles null values', function () {
    $item = new EstimateItem();
    $item->total = null;
    
    expect($item->total)->toBeNull();
});

test('discount handles null values', function () {
    $item = new EstimateItem();
    $item->discount = null;
    
    expect($item->discount)->toBeNull();
});

test('quantity handles null values', function () {
    $item = new EstimateItem();
    $item->quantity = null;
    
    expect($item->quantity)->toBeNull();
});

test('discount_val handles null values', function () {
    $item = new EstimateItem();
    $item->discount_val = null;
    
    expect($item->discount_val)->toBeNull();
});

test('tax handles null values', function () {
    $item = new EstimateItem();
    $item->tax = null;
    
    expect($item->tax)->toBeNull();
});

// ========== INVALID STRING CASTING TESTS ==========

test('price casts invalid string to zero', function () {
    $item = new EstimateItem();
    $item->price = "invalid_price";
    
    expect($item->price)->toBeInt()
        ->and($item->price)->toBe(0);
});

test('total casts invalid string to zero', function () {
    $item = new EstimateItem();
    $item->total = "invalid_total";
    
    expect($item->total)->toBeInt()
        ->and($item->total)->toBe(0);
});

test('discount casts invalid string to zero float', function () {
    $item = new EstimateItem();
    $item->discount = "invalid_discount";
    
    expect($item->discount)->toBeFloat()
        ->and($item->discount)->toBe(0.0);
});

test('quantity casts invalid string to zero float', function () {
    $item = new EstimateItem();
    $item->quantity = "invalid_quantity";
    
    expect($item->quantity)->toBeFloat()
        ->and($item->quantity)->toBe(0.0);
});

test('discount_val casts invalid string to zero', function () {
    $item = new EstimateItem();
    $item->discount_val = "invalid_discount_val";
    
    expect($item->discount_val)->toBeInt()
        ->and($item->discount_val)->toBe(0);
});

test('tax casts invalid string to zero', function () {
    $item = new EstimateItem();
    $item->tax = "invalid_tax";
    
    expect($item->tax)->toBeInt()
        ->and($item->tax)->toBe(0);
});

// ========== RELATIONSHIP TESTS ==========

test('estimate method exists', function () {
    $item = new EstimateItem();
    expect(method_exists($item, 'estimate'))->toBeTrue();
});

test('estimate relationship returns BelongsTo', function () {
    $item = new EstimateItem();
    $relation = $item->estimate();
    
    expect($relation)->toBeInstanceOf(BelongsTo::class);
});

test('estimate relationship is to Estimate model', function () {
    $item = new EstimateItem();
    $relation = $item->estimate();
    
    expect($relation->getRelated())->toBeInstanceOf(Estimate::class);
});

test('item method exists', function () {
    $item = new EstimateItem();
    expect(method_exists($item, 'item'))->toBeTrue();
});

test('item relationship returns BelongsTo', function () {
    $item = new EstimateItem();
    $relation = $item->item();
    
    expect($relation)->toBeInstanceOf(BelongsTo::class);
});

test('item relationship is to Item model', function () {
    $item = new EstimateItem();
    $relation = $item->item();
    
    expect($relation->getRelated())->toBeInstanceOf(Item::class);
});

test('taxes method exists', function () {
    $item = new EstimateItem();
    expect(method_exists($item, 'taxes'))->toBeTrue();
});

test('taxes relationship returns HasMany', function () {
    $item = new EstimateItem();
    $relation = $item->taxes();
    
    expect($relation)->toBeInstanceOf(HasMany::class);
});

test('taxes relationship is to Tax model', function () {
    $item = new EstimateItem();
    $relation = $item->taxes();
    
    expect($relation->getRelated())->toBeInstanceOf(Tax::class);
});

// ========== SCOPE TESTS ==========

test('scopeWhereCompany method exists', function () {
    $item = new EstimateItem();
    expect(method_exists($item, 'scopeWhereCompany'))->toBeTrue();
});

test('scopeWhereCompany is public', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    $method = $reflection->getMethod('scopeWhereCompany');
    
    expect($method->isPublic())->toBeTrue();
});

test('scopeWhereCompany accepts two parameters', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    $method = $reflection->getMethod('scopeWhereCompany');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('query')
        ->and($parameters[1]->getName())->toBe('company_id');
});

// ========== TRAITS TESTS ==========

test('EstimateItem uses HasFactory trait', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
});

test('EstimateItem uses HasCustomFieldsTrait', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Crater\Traits\HasCustomFieldsTrait');
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('all relationship methods are public', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    
    expect($reflection->getMethod('estimate')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('item')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('taxes')->isPublic())->toBeTrue();
});

test('all relationship methods are not static', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    
    expect($reflection->getMethod('estimate')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('item')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('taxes')->isStatic())->toBeFalse();
});

test('all relationship methods have no parameters', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    
    expect($reflection->getMethod('estimate')->getNumberOfParameters())->toBe(0)
        ->and($reflection->getMethod('item')->getNumberOfParameters())->toBe(0)
        ->and($reflection->getMethod('taxes')->getNumberOfParameters())->toBe(0);
});

// ========== INSTANCE TESTS ==========

test('multiple EstimateItem instances can be created', function () {
    $item1 = new EstimateItem();
    $item2 = new EstimateItem();
    
    expect($item1)->toBeInstanceOf(EstimateItem::class)
        ->and($item2)->toBeInstanceOf(EstimateItem::class)
        ->and($item1)->not->toBe($item2);
});

test('EstimateItem can be cloned', function () {
    $item = new EstimateItem();
    $clone = clone $item;
    
    expect($clone)->toBeInstanceOf(EstimateItem::class)
        ->and($clone)->not->toBe($item);
});

test('EstimateItem can be used in type hints', function () {
    $testFunction = function (EstimateItem $item) {
        return $item;
    };
    
    $item = new EstimateItem();
    $result = $testFunction($item);
    
    expect($result)->toBe($item);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('EstimateItem is not final', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('EstimateItem is not an interface', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('EstimateItem is not a trait', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('EstimateItem class is loaded', function () {
    expect(class_exists(EstimateItem::class))->toBeTrue();
});

// ========== CASTS CONFIGURATION TESTS ==========

test('all casts are properly configured', function () {
    $item = new EstimateItem();
    $casts = $item->getCasts();
    
    expect($casts)->toHaveKey('price')
        ->and($casts)->toHaveKey('total')
        ->and($casts)->toHaveKey('discount')
        ->and($casts)->toHaveKey('quantity')
        ->and($casts)->toHaveKey('discount_val')
        ->and($casts)->toHaveKey('tax');
});

test('integer casts are configured correctly', function () {
    $item = new EstimateItem();
    $casts = $item->getCasts();
    
    expect($casts['price'])->toBe('integer')
        ->and($casts['total'])->toBe('integer')
        ->and($casts['discount_val'])->toBe('integer')
        ->and($casts['tax'])->toBe('integer');
});

test('float casts are configured correctly', function () {
    $item = new EstimateItem();
    $casts = $item->getCasts();
    
    expect($casts['discount'])->toBe('float')
        ->and($casts['quantity'])->toBe('float');
});

// ========== ATTRIBUTE ASSIGNMENT TESTS ==========

test('can set and get price attribute', function () {
    $item = new EstimateItem();
    $item->price = 5000;
    
    expect($item->price)->toBe(5000);
});

test('can set and get total attribute', function () {
    $item = new EstimateItem();
    $item->total = 10000;
    
    expect($item->total)->toBe(10000);
});

test('can set and get discount attribute', function () {
    $item = new EstimateItem();
    $item->discount = 10.5;
    
    expect($item->discount)->toBe(10.5);
});

test('can set and get quantity attribute', function () {
    $item = new EstimateItem();
    $item->quantity = 3.5;
    
    expect($item->quantity)->toBe(3.5);
});

test('can set and get discount_val attribute', function () {
    $item = new EstimateItem();
    $item->discount_val = 1000;
    
    expect($item->discount_val)->toBe(1000);
});

test('can set and get tax attribute', function () {
    $item = new EstimateItem();
    $item->tax = 200;
    
    expect($item->tax)->toBe(200);
});

// ========== COMPLEX CASTING TESTS ==========

test('handles decimal string for integer cast', function () {
    $item = new EstimateItem();
    $item->price = "10000.99";
    
    expect($item->price)->toBeInt()
        ->and($item->price)->toBe(10000);
});

test('handles negative values for integer cast', function () {
    $item = new EstimateItem();
    $item->price = -5000;
    
    expect($item->price)->toBeInt()
        ->and($item->price)->toBe(-5000);
});

test('handles negative values for float cast', function () {
    $item = new EstimateItem();
    $item->discount = -10.5;
    
    expect($item->discount)->toBeFloat()
        ->and($item->discount)->toBe(-10.5);
});

test('handles zero values correctly', function () {
    $item = new EstimateItem();
    $item->price = 0;
    $item->discount = 0.0;
    
    expect($item->price)->toBe(0)
        ->and($item->discount)->toBe(0.0);
});

// ========== FILE STRUCTURE TESTS ==========

test('EstimateItem file has expected structure', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class EstimateItem extends Model')
        ->and($fileContent)->toContain('protected $guarded')
        ->and($fileContent)->toContain('protected $casts');
});

test('EstimateItem has compact implementation', function () {
    $reflection = new ReflectionClass(EstimateItem::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be concise (< 2000 bytes)
    expect(strlen($fileContent))->toBeLessThan(2000);
});