<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Crater\Http\Resources\EstimateItemCollection;
use Crater\Http\Resources\EstimateItemResource;

// Helper function to create a dummy estimate item with all required properties
function createDummyEstimateItem($id, $name, $price) {
    return (object) [
        'id' => $id,
        'name' => $name,
        'description' => 'Description for ' . $name,
        'discount_type' => 'fixed',
        'quantity' => 1.0,
        'unit_name' => 'piece',
        'discount' => 0.0,
        'discount_val' => 0,
        'price' => $price,
        'tax' => 0,
        'total' => $price,
        'item_id' => $id,
        'estimate_id' => 1,
        'company_id' => 1,
        'exchange_rate' => 1.0,
        'base_discount_val' => 0,
        'base_price' => $price,
        'base_tax' => 0,
        'base_total' => $price,
    ];
}

// Helper to create estimate item with methods
function createEstimateItemWithMethods($id, $name, $price) {
    $item = createDummyEstimateItem($id, $name, $price);
    
    return new class($item) {
        private $data;
        
        public function __construct($data) {
            $this->data = $data;
        }
        
        public function __get($key) {
            return $this->data->$key ?? null;
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
}

// ========== CLASS STRUCTURE TESTS ==========

test('EstimateItemCollection can be instantiated', function () {
    $collection = new EstimateItemCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(EstimateItemCollection::class);
});

test('EstimateItemCollection extends ResourceCollection', function () {
    $collection = new EstimateItemCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('EstimateItemCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('EstimateItemCollection has toArray method', function () {
    $collection = new EstimateItemCollection(new Collection([]));
    expect(method_exists($collection, 'toArray'))->toBeTrue();
});

test('toArray method is public', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue();
});

test('toArray method accepts request parameter', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    $method = $reflection->getMethod('toArray');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// ========== FUNCTIONAL TESTS ==========

test('handles empty collection', function () {
    $request = new Request();
    $collection = new EstimateItemCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('transforms single estimate item resource', function () {
    $request = new Request();
    $item = createEstimateItemWithMethods(1, 'Item 1', 1000);
    $resource = new EstimateItemResource($item);
    
    $collection = new EstimateItemCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0])->toHaveKey('id')
        ->and($result[0]['id'])->toBe(1)
        ->and($result[0])->toHaveKey('name')
        ->and($result[0]['name'])->toBe('Item 1');
});

test('transforms multiple estimate item resources', function () {
    $request = new Request();
    $item1 = createEstimateItemWithMethods(1, 'Item 1', 1000);
    $item2 = createEstimateItemWithMethods(2, 'Item 2', 2000);
    
    $resource1 = new EstimateItemResource($item1);
    $resource2 = new EstimateItemResource($item2);
    
    $collection = new EstimateItemCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[1]['id'])->toBe(2);
});

test('each transformed item has required fields', function () {
    $request = new Request();
    $item = createEstimateItemWithMethods(1, 'Item 1', 1000);
    $resource = new EstimateItemResource($item);
    
    $collection = new EstimateItemCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0])->toHaveKeys([
        'id',
        'name',
        'description',
        'discount_type',
        'quantity',
        'price',
        'total',
        'tax'
    ]);
});

test('handles large collection of estimate items', function () {
    $request = new Request();
    $resources = [];
    
    for ($i = 1; $i <= 50; $i++) {
        $item = createEstimateItemWithMethods($i, 'Item ' . $i, $i * 100);
        $resources[] = new EstimateItemResource($item);
    }
    
    $collection = new EstimateItemCollection(new Collection($resources));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(50)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[49]['id'])->toBe(50);
});

// ========== INHERITANCE TESTS ==========

test('toArray delegates to parent ResourceCollection', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    $method = $reflection->getMethod('toArray');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

test('EstimateItemCollection parent class is ResourceCollection', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

// ========== INSTANCE TESTS ==========

test('multiple EstimateItemCollection instances can be created', function () {
    $collection1 = new EstimateItemCollection(new Collection([]));
    $collection2 = new EstimateItemCollection(new Collection([]));
    
    expect($collection1)->toBeInstanceOf(EstimateItemCollection::class)
        ->and($collection2)->toBeInstanceOf(EstimateItemCollection::class)
        ->and($collection1)->not->toBe($collection2);
});

test('EstimateItemCollection can be cloned', function () {
    $collection = new EstimateItemCollection(new Collection([]));
    $clone = clone $collection;
    
    expect($clone)->toBeInstanceOf(EstimateItemCollection::class)
        ->and($clone)->not->toBe($collection);
});

test('EstimateItemCollection can be used in type hints', function () {
    $testFunction = function (EstimateItemCollection $collection) {
        return $collection;
    };
    
    $collection = new EstimateItemCollection(new Collection([]));
    $result = $testFunction($collection);
    
    expect($result)->toBe($collection);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('EstimateItemCollection is not abstract', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('EstimateItemCollection is not final', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('EstimateItemCollection is not an interface', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('EstimateItemCollection is not a trait', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('EstimateItemCollection class is loaded', function () {
    expect(class_exists(EstimateItemCollection::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('EstimateItemCollection uses ResourceCollection', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Illuminate\Http\Resources\Json\ResourceCollection');
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('toArray method is not static', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isStatic())->toBeFalse();
});

test('toArray method is not abstract', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isAbstract())->toBeFalse();
});

// ========== DATA INTEGRITY TESTS ==========

test('preserves item data integrity', function () {
    $request = new Request();
    $item = createEstimateItemWithMethods(42, 'Special Item', 5000);
    $resource = new EstimateItemResource($item);
    
    $collection = new EstimateItemCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0]['id'])->toBe(42)
        ->and($result[0]['name'])->toBe('Special Item')
        ->and($result[0]['price'])->toBe(5000)
        ->and($result[0]['total'])->toBe(5000);
});

test('handles different discount types', function () {
    $request = new Request();
    
    $item1 = createEstimateItemWithMethods(1, 'Item 1', 1000);
    $item1->discount_type = 'fixed';
    
    $item2 = createEstimateItemWithMethods(2, 'Item 2', 2000);
    $item2->discount_type = 'percentage';
    
    $resource1 = new EstimateItemResource($item1);
    $resource2 = new EstimateItemResource($item2);
    
    $collection = new EstimateItemCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result[0]['discount_type'])->toBe('fixed')
        ->and($result[1]['discount_type'])->toBe('percentage');
});

test('handles different quantities', function () {
    $request = new Request();
    
    $item1 = createEstimateItemWithMethods(1, 'Item 1', 1000);
    $item1->quantity = 1.0;
    
    $item2 = createEstimateItemWithMethods(2, 'Item 2', 2000);
    $item2->quantity = 2.5;
    
    $resource1 = new EstimateItemResource($item1);
    $resource2 = new EstimateItemResource($item2);
    
    $collection = new EstimateItemCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result[0]['quantity'])->toBe(1.0)
        ->and($result[1]['quantity'])->toBe(2.5);
});

// ========== FILE STRUCTURE TESTS ==========

test('EstimateItemCollection file is simple and concise', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be small (< 1000 bytes for simple delegation)
    expect(strlen($fileContent))->toBeLessThan(1000);
});

test('EstimateItemCollection has minimal line count', function () {
    $reflection = new ReflectionClass(EstimateItemCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(30);
});

// ========== COMPREHENSIVE FIELD TESTS ==========

test('transformed items include all base fields', function () {
    $request = new Request();
    $item = createEstimateItemWithMethods(1, 'Complete Item', 1500);
    $resource = new EstimateItemResource($item);
    
    $collection = new EstimateItemCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0])->toHaveKeys([
        'id', 'name', 'description', 'discount_type', 'quantity',
        'unit_name', 'discount', 'discount_val', 'price', 'tax',
        'total', 'item_id', 'estimate_id', 'company_id',
        'exchange_rate', 'base_discount_val', 'base_price',
        'base_tax', 'base_total'
    ]);
});