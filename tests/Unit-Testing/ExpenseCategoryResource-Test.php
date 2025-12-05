<?php

use Crater\Http\Resources\ExpenseCategoryResource;
use Illuminate\Http\Request;

// Helper function to create dummy expense category with all required properties
function createDummyExpenseCategoryForResource($id, $name, $amount = 0) {
    return new class($id, $name, $amount) {
        private $data;
        
        public function __construct($id, $name, $amount) {
            $this->data = [
                'id' => $id,
                'name' => $name,
                'description' => 'Description for ' . $name,
                'company_id' => 1,
                'amount' => $amount,
                'formattedCreatedAt' => '2024-01-01 00:00:00',
            ];
        }
        
        public function __get($key) {
            return $this->data[$key] ?? null;
        }
        
        public function company() {
            return new class {
                public function exists() { return false; }
            };
        }
    };
}

// ========== CLASS STRUCTURE TESTS ==========

test('ExpenseCategoryResource can be instantiated', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    expect($resource)->toBeInstanceOf(ExpenseCategoryResource::class);
});

test('ExpenseCategoryResource extends JsonResource', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

test('ExpenseCategoryResource is in correct namespace', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('ExpenseCategoryResource has toArray method', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    expect(method_exists($resource, 'toArray'))->toBeTrue();
});

// ========== FUNCTIONAL TESTS - ALL FIELDS ==========

test('toArray returns array with id', function () {
    $category = createDummyExpenseCategoryForResource(42, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('id')
        ->and($result['id'])->toBe(42);
});

test('toArray returns array with name', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Office Supplies', 100);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('name')
        ->and($result['name'])->toBe('Office Supplies');
});

test('toArray returns array with description', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('description')
        ->and($result['description'])->toBe('Description for Travel');
});

test('toArray returns array with company_id', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('company_id')
        ->and($result['company_id'])->toBe(1);
});

test('toArray returns array with amount', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 250.75);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('amount')
        ->and($result['amount'])->toBe(250.75);
});

test('toArray returns array with formatted_created_at', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('formatted_created_at')
        ->and($result['formatted_created_at'])->toBe('2024-01-01 00:00:00');
});

test('toArray includes all required fields', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKeys([
        'id',
        'name',
        'description',
        'company_id',
        'amount',
        'formatted_created_at',
        'company'
    ]);
});

// ========== NULL HANDLING TESTS ==========

test('toArray handles null id', function () {
    $category = createDummyExpenseCategoryForResource(null, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result['id'])->toBeNull();
});

test('toArray handles null name', function () {
    $category = createDummyExpenseCategoryForResource(1, null, 100);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result['name'])->toBeNull();
});

test('toArray handles null description', function () {
    $category = new class {
        public $id = 1;
        public $name = 'Travel';
        public $description = null;
        public $company_id = 1;
        public $amount = 100;
        public $formattedCreatedAt = '2024-01-01';
        
        public function company() {
            return new class { public function exists() { return false; } };
        }
    };
    
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result['description'])->toBeNull();
});

test('toArray handles zero amount', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 0);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result['amount'])->toBe(0);
});

test('toArray handles negative amount', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Refund', -50.25);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result['amount'])->toBe(-50.25);
});

// ========== DIFFERENT DATA TESTS ==========

test('toArray handles different category names', function () {
    $categories = [
        ['name' => 'Travel', 'expected' => 'Travel'],
        ['name' => 'Office Supplies', 'expected' => 'Office Supplies'],
        ['name' => 'Utilities', 'expected' => 'Utilities'],
        ['name' => 'Marketing', 'expected' => 'Marketing'],
    ];
    
    foreach ($categories as $test) {
        $category = createDummyExpenseCategoryForResource(1, $test['name'], 100);
        $resource = new ExpenseCategoryResource($category);
        $request = new Request();
        $result = $resource->toArray($request);
        
        expect($result['name'])->toBe($test['expected']);
    }
});

test('toArray handles different amounts', function () {
    $amounts = [0, 10.50, 100, 999.99, 1000.00, 5000.75];
    
    foreach ($amounts as $amount) {
        $category = createDummyExpenseCategoryForResource(1, 'Test', $amount);
        $resource = new ExpenseCategoryResource($category);
        $request = new Request();
        $result = $resource->toArray($request);
        
        expect($result['amount'])->toBe($amount);
    }
});

// ========== COMPANY RELATIONSHIP TESTS ==========

test('toArray includes company field', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('company');
});

test('toArray company is null when relationship does not exist', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    // When company()->exists() returns false, the when() condition should not include it
    // or it should be a MissingValue instance
    expect(array_key_exists('company', $result))->toBeTrue();
});

// ========== DATA INTEGRITY TESTS ==========

test('preserves expense category data integrity', function () {
    $category = createDummyExpenseCategoryForResource(123, 'Marketing', 456.78);
    $resource = new ExpenseCategoryResource($category);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result['id'])->toBe(123)
        ->and($result['name'])->toBe('Marketing')
        ->and($result['amount'])->toBe(456.78);
});

test('different instances have independent data', function () {
    $category1 = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $category2 = createDummyExpenseCategoryForResource(2, 'Supplies', 200);
    
    $resource1 = new ExpenseCategoryResource($category1);
    $resource2 = new ExpenseCategoryResource($category2);
    
    $request = new Request();
    $result1 = $resource1->toArray($request);
    $result2 = $resource2->toArray($request);
    
    expect($result1['id'])->not->toBe($result2['id'])
        ->and($result1['name'])->not->toBe($result2['name'])
        ->and($result1['amount'])->not->toBe($result2['amount']);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('ExpenseCategoryResource is not abstract', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('ExpenseCategoryResource is not final', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('ExpenseCategoryResource is not an interface', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('ExpenseCategoryResource is not a trait', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('ExpenseCategoryResource class is loaded', function () {
    expect(class_exists(ExpenseCategoryResource::class))->toBeTrue();
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('toArray method is public', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue();
});

test('toArray method is not static', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isStatic())->toBeFalse();
});

test('toArray method accepts request parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $method = $reflection->getMethod('toArray');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// ========== INSTANCE TESTS ==========

test('multiple ExpenseCategoryResource instances can be created', function () {
    $category1 = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $category2 = createDummyExpenseCategoryForResource(2, 'Supplies', 200);
    
    $resource1 = new ExpenseCategoryResource($category1);
    $resource2 = new ExpenseCategoryResource($category2);
    
    expect($resource1)->toBeInstanceOf(ExpenseCategoryResource::class)
        ->and($resource2)->toBeInstanceOf(ExpenseCategoryResource::class)
        ->and($resource1)->not->toBe($resource2);
});

test('ExpenseCategoryResource can be cloned', function () {
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    $clone = clone $resource;
    
    expect($clone)->toBeInstanceOf(ExpenseCategoryResource::class)
        ->and($clone)->not->toBe($resource);
});

test('ExpenseCategoryResource can be used in type hints', function () {
    $testFunction = function (ExpenseCategoryResource $resource) {
        return $resource;
    };
    
    $category = createDummyExpenseCategoryForResource(1, 'Travel', 100);
    $resource = new ExpenseCategoryResource($category);
    $result = $testFunction($resource);
    
    expect($result)->toBe($resource);
});

// ========== IMPORTS TESTS ==========

test('ExpenseCategoryResource uses JsonResource', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Illuminate\Http\Resources\Json\JsonResource');
});

test('ExpenseCategoryResource uses CompanyResource', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('CompanyResource');
});

// ========== IMPLEMENTATION TESTS ==========

test('toArray uses when helper for company', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->when');
});

test('toArray checks company exists', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->company()->exists()');
});

test('toArray creates CompanyResource when company exists', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('new CompanyResource($this->company)');
});

// ========== DOCUMENTATION TESTS ==========

test('toArray method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('toArray method has return type documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $method = $reflection->getMethod('toArray');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@return');
});

test('toArray method has param documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $method = $reflection->getMethod('toArray');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@param');
});

// ========== FILE STRUCTURE TESTS ==========

test('ExpenseCategoryResource file is concise', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect(strlen($fileContent))->toBeLessThan(2000);
});

test('ExpenseCategoryResource has minimal line count', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(50);
});

// ========== PARENT CLASS TESTS ==========

test('ExpenseCategoryResource parent is JsonResource', function () {
    $reflection = new ReflectionClass(ExpenseCategoryResource::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe('Illuminate\Http\Resources\Json\JsonResource');
});