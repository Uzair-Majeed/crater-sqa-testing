<?php

use Illuminate\Http\Request;
use Crater\Http\Resources\EstimateItemResource;

// Helper function to create estimate item with all properties and relationship methods
function createEstimateItemObject($id, $name, $price, $hasTaxes = false, $hasFields = false) {
    return new class($id, $name, $price, $hasTaxes, $hasFields) {
        private $id;
        private $name;
        private $price;
        private $hasTaxes;
        private $hasFields;
        private $taxesData = [];
        private $fieldsData = [];
        
        public function __construct($id, $name, $price, $hasTaxes, $hasFields) {
            $this->id = $id;
            $this->name = $name;
            $this->price = $price;
            $this->hasTaxes = $hasTaxes;
            $this->hasFields = $hasFields;
        }
        
        public function __get($key) {
            $properties = [
                'id' => $this->id,
                'name' => $this->name,
                'description' => 'Description for ' . $this->name,
                'discount_type' => 'fixed',
                'quantity' => 1.0,
                'unit_name' => 'piece',
                'discount' => 0.0,
                'discount_val' => 0,
                'price' => $this->price,
                'tax' => 0,
                'total' => $this->price,
                'item_id' => $this->id,
                'estimate_id' => 1,
                'company_id' => 1,
                'exchange_rate' => 1.0,
                'base_discount_val' => 0,
                'base_price' => $this->price,
                'base_tax' => 0,
                'base_total' => $this->price,
                'taxes' => $this->taxesData,
                'fields' => $this->fieldsData,
            ];
            
            return $properties[$key] ?? null;
        }
        
        public function taxes() {
            return new class($this->hasTaxes) {
                private $exists;
                public function __construct($exists) {
                    $this->exists = $exists;
                }
                public function exists() {
                    return $this->exists;
                }
            };
        }
        
        public function fields() {
            return new class($this->hasFields) {
                private $exists;
                public function __construct($exists) {
                    $this->exists = $exists;
                }
                public function exists() {
                    return $this->exists;
                }
            };
        }
        
        public function setTaxesData($data) {
            $this->taxesData = $data;
        }
        
        public function setFieldsData($data) {
            $this->fieldsData = $data;
        }
    };
}

// ========== CLASS STRUCTURE TESTS ==========

test('EstimateItemResource can be instantiated', function () {
    $item = createEstimateItemObject(1, 'Item 1', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    expect($resource)->toBeInstanceOf(EstimateItemResource::class);
});

test('EstimateItemResource extends JsonResource', function () {
    $item = createEstimateItemObject(1, 'Item 1', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

test('EstimateItemResource is in correct namespace', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('EstimateItemResource has toArray method', function () {
    $item = createEstimateItemObject(1, 'Item 1', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    expect(method_exists($resource, 'toArray'))->toBeTrue();
});

test('toArray method is public', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue();
});

test('toArray method accepts request parameter', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    $method = $reflection->getMethod('toArray');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// ========== FUNCTIONAL TESTS - BASIC TRANSFORMATION ==========

test('toArray transforms resource with all basic properties', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Test Item', 1500, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('id')
        ->and($result['id'])->toBe(1)
        ->and($result)->toHaveKey('name')
        ->and($result['name'])->toBe('Test Item')
        ->and($result)->toHaveKey('price')
        ->and($result['price'])->toBe(1500);
});

test('toArray includes all required fields', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKeys([
        'id', 'name', 'description', 'discount_type', 'quantity',
        'unit_name', 'discount', 'discount_val', 'price', 'tax',
        'total', 'item_id', 'estimate_id', 'company_id',
        'exchange_rate', 'base_discount_val', 'base_price',
        'base_tax', 'base_total', 'taxes', 'fields'
    ]);
});

test('toArray returns correct data types', function () {
    $request = new Request();
    $item = createEstimateItemObject(42, 'Typed Item', 2500, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result['id'])->toBeInt()
        ->and($result['name'])->toBeString()
        ->and($result['price'])->toBeInt()
        ->and($result['quantity'])->toBeFloat()
        ->and($result['discount'])->toBeFloat();
});

// ========== NULL HANDLING TESTS ==========

test('toArray handles null values gracefully', function () {
    $request = new Request();
    
    $item = new class {
        public function __get($key) {
            return null;
        }
        public function taxes() {
            return new class {
                public function exists() { return false; }
            };
        }
        public function fields() {
            return new class {
                public function exists() { return false; }
            };
        }
    };
    
    $resource = new EstimateItemResource($item);
    $result = $resource->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result['id'])->toBeNull()
        ->and($result['name'])->toBeNull()
        ->and($result['price'])->toBeNull();
});


// ========== DATA INTEGRITY TESTS ==========

test('preserves item data integrity', function () {
    $request = new Request();
    $item = createEstimateItemObject(99, 'Special Item', 7500, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result['id'])->toBe(99)
        ->and($result['name'])->toBe('Special Item')
        ->and($result['price'])->toBe(7500)
        ->and($result['total'])->toBe(7500);
});

test('includes description field', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item with Description', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('description')
        ->and($result['description'])->toBeString();
});

test('includes discount fields', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('discount_type')
        ->and($result)->toHaveKey('discount')
        ->and($result)->toHaveKey('discount_val');
});

test('includes base currency fields', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('base_price')
        ->and($result)->toHaveKey('base_tax')
        ->and($result)->toHaveKey('base_total')
        ->and($result)->toHaveKey('base_discount_val');
});

test('includes exchange rate field', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('exchange_rate');
});

test('includes item_id and estimate_id', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('item_id')
        ->and($result)->toHaveKey('estimate_id')
        ->and($result)->toHaveKey('company_id');
});

// ========== INSTANCE TESTS ==========

test('multiple EstimateItemResource instances can be created', function () {
    $item1 = createEstimateItemObject(1, 'Item 1', 1000, false, false);
    $item2 = createEstimateItemObject(2, 'Item 2', 2000, false, false);
    
    $resource1 = new EstimateItemResource($item1);
    $resource2 = new EstimateItemResource($item2);
    
    expect($resource1)->toBeInstanceOf(EstimateItemResource::class)
        ->and($resource2)->toBeInstanceOf(EstimateItemResource::class)
        ->and($resource1)->not->toBe($resource2);
});

test('EstimateItemResource can be cloned', function () {
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    $clone = clone $resource;
    
    expect($clone)->toBeInstanceOf(EstimateItemResource::class)
        ->and($clone)->not->toBe($resource);
});

test('EstimateItemResource can be used in type hints', function () {
    $testFunction = function (EstimateItemResource $resource) {
        return $resource;
    };
    
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    $result = $testFunction($resource);
    
    expect($result)->toBe($resource);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('EstimateItemResource is not abstract', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('EstimateItemResource is not final', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('EstimateItemResource is not an interface', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('EstimateItemResource is not a trait', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('EstimateItemResource class is loaded', function () {
    expect(class_exists(EstimateItemResource::class))->toBeTrue();
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('toArray method is not static', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isStatic())->toBeFalse();
});

test('toArray method is not abstract', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isAbstract())->toBeFalse();
});

// ========== ARRAY STRUCTURE TESTS ==========

test('toArray returns array with 21 keys', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect(count($result))->toBe(21);
});

test('toArray result is a valid array', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result)->toBeArray()
        ->and(is_array($result))->toBeTrue();
});

// ========== DIFFERENT VALUES TESTS ==========

test('handles different discount types', function () {
    $request = new Request();
    
    $item1 = createEstimateItemObject(1, 'Item 1', 1000, false, false);
    $resource1 = new EstimateItemResource($item1);
    $result1 = $resource1->toArray($request);
    
    expect($result1['discount_type'])->toBe('fixed');
});

test('handles different quantities', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result['quantity'])->toBe(1.0);
});

test('handles different unit names', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result['unit_name'])->toBe('piece');
});

// ========== FILE STRUCTURE TESTS ==========

test('EstimateItemResource file has expected structure', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class EstimateItemResource extends JsonResource')
        ->and($fileContent)->toContain('public function toArray');
});

test('EstimateItemResource uses when() for conditional relationships', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->when');
});

test('EstimateItemResource has compact implementation', function () {
    $reflection = new ReflectionClass(EstimateItemResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be reasonably sized (< 3000 bytes)
    expect(strlen($fileContent))->toBeLessThan(3000);
});

// ========== COMPREHENSIVE FIELD VALIDATION ==========

test('all numeric fields are present', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    $numericFields = ['id', 'quantity', 'discount', 'discount_val', 'price', 'tax', 'total',
                      'item_id', 'estimate_id', 'company_id', 'exchange_rate',
                      'base_discount_val', 'base_price', 'base_tax', 'base_total'];
    
    foreach ($numericFields as $field) {
        expect($result)->toHaveKey($field);
    }
});

test('all string fields are present', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    $stringFields = ['name', 'description', 'discount_type', 'unit_name'];
    
    foreach ($stringFields as $field) {
        expect($result)->toHaveKey($field);
    }
});

test('relationship fields are present', function () {
    $request = new Request();
    $item = createEstimateItemObject(1, 'Item', 1000, false, false);
    $resource = new EstimateItemResource($item);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('taxes')
        ->and($result)->toHaveKey('fields');
});