<?php

use Illuminate\Http\Request;
use Crater\Http\Resources\EstimateResource;

// Helper function to create estimate object with all properties
function createEstimateObject($id, $estimateNumber, $total) {
    return new class($id, $estimateNumber, $total) {
        private $data;
        
        public function __construct($id, $estimateNumber, $total) {
            $this->data = [
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
                'formattedExpiryDate' => '01/02/2024',
                'formattedEstimateDate' => '01/01/2024',
                'estimatePdfUrl' => 'http://example.com/estimate/' . $id,
                'sales_tax_type' => 'inclusive',
                'sales_tax_address_type' => 'billing',
            ];
        }
        
        public function __get($key) {
            return $this->data[$key] ?? null;
        }
        
        public function getNotes() {
            return $this->data['notes'];
        }
        
        public function items() {
            return new class { public function exists() { return false; } };
        }
        
        public function customer() {
            return new class { public function exists() { return false; } };
        }
        
        public function creator() {
            return new class { public function exists() { return false; } };
        }
        
        public function taxes() {
            return new class { public function exists() { return false; } };
        }
        
        public function fields() {
            return new class { public function exists() { return false; } };
        }
        
        public function company() {
            return new class { public function exists() { return false; } };
        }
        
        public function currency() {
            return new class { public function exists() { return false; } };
        }
    };
}

// ========== CLASS STRUCTURE TESTS ==========

test('EstimateResource can be instantiated', function () {
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    expect($resource)->toBeInstanceOf(EstimateResource::class);
});

test('EstimateResource extends JsonResource', function () {
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

test('EstimateResource is in correct namespace', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('EstimateResource has toArray method', function () {
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    expect(method_exists($resource, 'toArray'))->toBeTrue();
});

test('toArray method is public', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue();
});

test('toArray method accepts request parameter', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    $method = $reflection->getMethod('toArray');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// ========== FUNCTIONAL TESTS ==========

test('toArray transforms resource with basic properties', function () {
    $request = new Request();
    $estimate = createEstimateObject(1, 'EST-001', 1500);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('id')
        ->and($result['id'])->toBe(1)
        ->and($result)->toHaveKey('estimate_number')
        ->and($result['estimate_number'])->toBe('EST-001');
});

test('toArray includes all required fields', function () {
    $request = new Request();
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKeys([
        'id', 'estimate_date', 'expiry_date', 'estimate_number', 'status',
        'reference_number', 'tax_per_item', 'discount_per_item', 'notes',
        'discount', 'discount_type', 'discount_val', 'sub_total', 'total',
        'tax', 'unique_hash', 'creator_id', 'template_name', 'customer_id',
        'exchange_rate', 'base_discount_val', 'base_sub_total', 'base_total',
        'base_tax', 'sequence_number', 'currency_id', 'formatted_expiry_date',
        'formatted_estimate_date', 'estimate_pdf_url', 'sales_tax_type',
        'sales_tax_address_type'
    ]);
});

test('preserves estimate data integrity', function () {
    $request = new Request();
    $estimate = createEstimateObject(42, 'EST-042', 5000);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    expect($result['id'])->toBe(42)
        ->and($result['estimate_number'])->toBe('EST-042')
        ->and($result['total'])->toBe(5000)
        ->and($result['status'])->toBe('DRAFT');
});

// ========== INSTANCE TESTS ==========

test('multiple EstimateResource instances can be created', function () {
    $estimate1 = createEstimateObject(1, 'EST-001', 1000);
    $estimate2 = createEstimateObject(2, 'EST-002', 2000);
    
    $resource1 = new EstimateResource($estimate1);
    $resource2 = new EstimateResource($estimate2);
    
    expect($resource1)->toBeInstanceOf(EstimateResource::class)
        ->and($resource2)->toBeInstanceOf(EstimateResource::class)
        ->and($resource1)->not->toBe($resource2);
});

test('EstimateResource can be cloned', function () {
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    $clone = clone $resource;
    
    expect($clone)->toBeInstanceOf(EstimateResource::class)
        ->and($clone)->not->toBe($resource);
});

test('EstimateResource can be used in type hints', function () {
    $testFunction = function (EstimateResource $resource) {
        return $resource;
    };
    
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    $result = $testFunction($resource);
    
    expect($result)->toBe($resource);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('EstimateResource is not abstract', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('EstimateResource is not final', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('EstimateResource is not an interface', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('EstimateResource is not a trait', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('EstimateResource class is loaded', function () {
    expect(class_exists(EstimateResource::class))->toBeTrue();
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('toArray method is not static', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isStatic())->toBeFalse();
});

test('toArray method is not abstract', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isAbstract())->toBeFalse();
});

// ========== DATA INTEGRITY TESTS ==========

test('includes estimate dates', function () {
    $request = new Request();
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('estimate_date')
        ->and($result)->toHaveKey('expiry_date')
        ->and($result)->toHaveKey('formatted_estimate_date')
        ->and($result)->toHaveKey('formatted_expiry_date');
});

test('includes discount fields', function () {
    $request = new Request();
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('discount')
        ->and($result)->toHaveKey('discount_type')
        ->and($result)->toHaveKey('discount_val');
});

test('includes base currency fields', function () {
    $request = new Request();
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('base_discount_val')
        ->and($result)->toHaveKey('base_sub_total')
        ->and($result)->toHaveKey('base_total')
        ->and($result)->toHaveKey('base_tax');
});

test('includes tax configuration fields', function () {
    $request = new Request();
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('tax_per_item')
        ->and($result)->toHaveKey('discount_per_item')
        ->and($result)->toHaveKey('sales_tax_type')
        ->and($result)->toHaveKey('sales_tax_address_type');
});

test('includes PDF URL field', function () {
    $request = new Request();
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('estimate_pdf_url');
});

test('includes notes field', function () {
    $request = new Request();
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('notes');
});

// ========== FILE STRUCTURE TESTS ==========

test('EstimateResource file has expected structure', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class EstimateResource extends JsonResource')
        ->and($fileContent)->toContain('public function toArray');
});

test('EstimateResource uses when() for conditional relationships', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->when');
});

test('EstimateResource has comprehensive implementation', function () {
    $reflection = new ReflectionClass(EstimateResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be substantial (>2000 bytes for comprehensive resource)
    expect(strlen($fileContent))->toBeGreaterThan(2000);
});

// ========== COMPREHENSIVE FIELD VALIDATION ==========

test('all numeric fields are present', function () {
    $request = new Request();
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    $numericFields = ['id', 'discount', 'discount_val', 'sub_total', 'total', 'tax',
                      'creator_id', 'customer_id', 'exchange_rate', 'base_discount_val',
                      'base_sub_total', 'base_total', 'base_tax', 'sequence_number', 'currency_id'];
    
    foreach ($numericFields as $field) {
        expect($result)->toHaveKey($field);
    }
});

test('all string fields are present', function () {
    $request = new Request();
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    $stringFields = ['estimate_number', 'status', 'reference_number', 'discount_type',
                     'unique_hash', 'template_name'];
    
    foreach ($stringFields as $field) {
        expect($result)->toHaveKey($field);
    }
});

test('result is a valid array structure', function () {
    $request = new Request();
    $estimate = createEstimateObject(1, 'EST-001', 1000);
    $resource = new EstimateResource($estimate);
    
    $result = $resource->toArray($request);
    
    expect($result)->toBeArray()
        ->and(is_array($result))->toBeTrue()
        ->and(count($result))->toBeGreaterThan(20);
});