<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Crater\Http\Resources\EstimateCollection;
use Crater\Http\Resources\EstimateResource;

// Helper function to create a dummy estimate object with all required properties
function createDummyEstimate($id, $estimateNumber, $total) {
    return (object) [
        'id' => $id,
        'estimate_date' => '2024-01-01',
        'expiry_date' => '2024-02-01',
        'estimate_number' => $estimateNumber,
        'status' => 'DRAFT',
        'reference_number' => 'REF-' . $id,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'notes' => 'Test notes',
        'discount' => 0,
        'discount_type' => 'fixed',
        'discount_val' => 0,
        'sub_total' => $total,
        'total' => $total,
        'tax' => 0,
        'unique_hash' => 'hash-' . $id,
        'creator_id' => 1,
        'template_name' => 'template1',
        'customer_id' => 1,
        'exchange_rate' => 1,
        'base_discount_val' => 0,
        'base_sub_total' => $total,
        'base_total' => $total,
        'base_tax' => 0,
        'sequence_number' => $id,
        'currency_id' => 1,
        'formatted_expiry_date' => '01/02/2024',
        'formatted_estimate_date' => '01/01/2024',
        'estimate_pdf_url' => 'http://example.com/estimate/' . $id,
        'sales_tax_type' => 'inclusive',
        'sales_tax_address_type' => 'billing',
    ];
}

// Helper to create estimate with methods
function createEstimateWithMethods($id, $estimateNumber, $total) {
    $estimate = createDummyEstimate($id, $estimateNumber, $total);
    
    return new class($estimate) {
        private $data;
        
        public function __construct($data) {
            $this->data = $data;
        }
        
        public function __get($key) {
            return $this->data->$key ?? null;
        }
        
        public function getNotes() {
            return $this->data->notes;
        }
        
        public function items() {
            return new class {
                public function exists() { return false; }
            };
        }
        
        public function customer() {
            return new class {
                public function exists() { return false; }
            };
        }
        
        public function creator() {
            return new class {
                public function exists() { return false; }
            };
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
        
        public function company() {
            return new class {
                public function exists() { return false; }
            };
        }
        
        public function currency() {
            return new class {
                public function exists() { return false; }
            };
        }
    };
}

// ========== CLASS STRUCTURE TESTS ==========

test('EstimateCollection can be instantiated', function () {
    $collection = new EstimateCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(EstimateCollection::class);
});

test('EstimateCollection extends ResourceCollection', function () {
    $collection = new EstimateCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('EstimateCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('EstimateCollection has toArray method', function () {
    $collection = new EstimateCollection(new Collection([]));
    expect(method_exists($collection, 'toArray'))->toBeTrue();
});

test('toArray method is public', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue();
});

test('toArray method accepts request parameter', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    $method = $reflection->getMethod('toArray');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// ========== FUNCTIONAL TESTS ==========

test('handles empty collection', function () {
    $request = new Request();
    $collection = new EstimateCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('transforms single estimate resource', function () {
    $request = new Request();
    $estimate = createEstimateWithMethods(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $collection = new EstimateCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0])->toHaveKey('id')
        ->and($result[0]['id'])->toBe(1)
        ->and($result[0])->toHaveKey('estimate_number')
        ->and($result[0]['estimate_number'])->toBe('EST-001');
});

test('transforms multiple estimate resources', function () {
    $request = new Request();
    $estimate1 = createEstimateWithMethods(1, 'EST-001', 1000);
    $estimate2 = createEstimateWithMethods(2, 'EST-002', 2000);
    
    $resource1 = new EstimateResource($estimate1);
    $resource2 = new EstimateResource($estimate2);
    
    $collection = new EstimateCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[1]['id'])->toBe(2);
});

test('each transformed estimate has required fields', function () {
    $request = new Request();
    $estimate = createEstimateWithMethods(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $collection = new EstimateCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0])->toHaveKeys([
        'id',
        'estimate_date',
        'expiry_date',
        'estimate_number',
        'status',
        'total',
        'sub_total',
        'tax'
    ]);
});

test('handles large collection of estimates', function () {
    $request = new Request();
    $resources = [];
    
    for ($i = 1; $i <= 100; $i++) {
        $estimate = createEstimateWithMethods($i, 'EST-' . str_pad($i, 3, '0', STR_PAD_LEFT), $i * 100);
        $resources[] = new EstimateResource($estimate);
    }
    
    $collection = new EstimateCollection(new Collection($resources));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(100)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[99]['id'])->toBe(100);
});

// ========== INHERITANCE TESTS ==========

test('toArray delegates to parent ResourceCollection', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    $method = $reflection->getMethod('toArray');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

test('EstimateCollection parent class is ResourceCollection', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

// ========== INSTANCE TESTS ==========

test('multiple EstimateCollection instances can be created', function () {
    $collection1 = new EstimateCollection(new Collection([]));
    $collection2 = new EstimateCollection(new Collection([]));
    
    expect($collection1)->toBeInstanceOf(EstimateCollection::class)
        ->and($collection2)->toBeInstanceOf(EstimateCollection::class)
        ->and($collection1)->not->toBe($collection2);
});

test('EstimateCollection can be cloned', function () {
    $collection = new EstimateCollection(new Collection([]));
    $clone = clone $collection;
    
    expect($clone)->toBeInstanceOf(EstimateCollection::class)
        ->and($clone)->not->toBe($collection);
});

test('EstimateCollection can be used in type hints', function () {
    $testFunction = function (EstimateCollection $collection) {
        return $collection;
    };
    
    $collection = new EstimateCollection(new Collection([]));
    $result = $testFunction($collection);
    
    expect($result)->toBe($collection);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('EstimateCollection is not abstract', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('EstimateCollection is not final', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('EstimateCollection is not an interface', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('EstimateCollection is not a trait', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('EstimateCollection class is loaded', function () {
    expect(class_exists(EstimateCollection::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('EstimateCollection uses ResourceCollection', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Illuminate\Http\Resources\Json\ResourceCollection');
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('toArray method is not static', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isStatic())->toBeFalse();
});

test('toArray method is not abstract', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isAbstract())->toBeFalse();
});

// ========== DATA INTEGRITY TESTS ==========

test('preserves estimate data integrity', function () {
    $request = new Request();
    $estimate = createEstimateWithMethods(42, 'EST-042', 5000);
    $resource = new EstimateResource($estimate);
    
    $collection = new EstimateCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0]['id'])->toBe(42)
        ->and($result[0]['estimate_number'])->toBe('EST-042')
        ->and($result[0]['total'])->toBe(5000)
        ->and($result[0]['status'])->toBe('DRAFT');
});

test('handles different estimate statuses', function () {
    $request = new Request();
    
    $estimate1 = createEstimateWithMethods(1, 'EST-001', 1000);
    $estimate1->status = 'DRAFT';
    
    $estimate2 = createEstimateWithMethods(2, 'EST-002', 2000);
    $estimate2->status = 'SENT';
    
    $resource1 = new EstimateResource($estimate1);
    $resource2 = new EstimateResource($estimate2);
    
    $collection = new EstimateCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result[0]['status'])->toBe('DRAFT')
        ->and($result[1]['status'])->toBe('SENT');
});

// ========== FILE STRUCTURE TESTS ==========

test('EstimateCollection file is simple and concise', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be small (< 1000 bytes for simple delegation)
    expect(strlen($fileContent))->toBeLessThan(1000);
});

test('EstimateCollection has minimal line count', function () {
    $reflection = new ReflectionClass(EstimateCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(30);
});