<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Crater\Http\Resources\ExpenseCategoryCollection;
use Crater\Http\Resources\ExpenseCategoryResource;

// Helper function to create dummy expense category with all required properties
function createDummyExpenseCategory($id, $name) {
    return new class($id, $name) {
        private $data;
        
        public function __construct($id, $name) {
            $this->data = [
                'id' => $id,
                'name' => $name,
                'description' => 'Description for ' . $name,
                'company_id' => 1,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ];
        }
        
        public function __get($key) {
            return $this->data[$key] ?? null;
        }
        
        public function company() {
            return new class { public function exists() { return false; } };
        }
        
        public function expenses() {
            return new class { public function sum($column) { return 0; } };
        }
    };
}

// ========== CLASS STRUCTURE TESTS ==========

test('ExpenseCategoryCollection can be instantiated', function () {
    $collection = new ExpenseCategoryCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(ExpenseCategoryCollection::class);
});

test('ExpenseCategoryCollection extends ResourceCollection', function () {
    $collection = new ExpenseCategoryCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('ExpenseCategoryCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('ExpenseCategoryCollection has toArray method', function () {
    $collection = new ExpenseCategoryCollection(new Collection([]));
    expect(method_exists($collection, 'toArray'))->toBeTrue();
});

test('toArray method is public', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue();
});

test('toArray method accepts request parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $method = $reflection->getMethod('toArray');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// ========== FUNCTIONAL TESTS ==========

test('handles empty collection', function () {
    $request = new Request();
    $collection = new ExpenseCategoryCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('transforms single expense category resource', function () {
    $request = new Request();
    $category = createDummyExpenseCategory(1, 'Travel');
    $resource = new ExpenseCategoryResource($category);
    
    $collection = new ExpenseCategoryCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0])->toHaveKey('id')
        ->and($result[0]['id'])->toBe(1);
});

test('transforms multiple expense category resources', function () {
    $request = new Request();
    $category1 = createDummyExpenseCategory(1, 'Travel');
    $category2 = createDummyExpenseCategory(2, 'Supplies');
    
    $resource1 = new ExpenseCategoryResource($category1);
    $resource2 = new ExpenseCategoryResource($category2);
    
    $collection = new ExpenseCategoryCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[1]['id'])->toBe(2);
});

test('each transformed item has required fields', function () {
    $request = new Request();
    $category = createDummyExpenseCategory(1, 'Travel');
    $resource = new ExpenseCategoryResource($category);
    
    $collection = new ExpenseCategoryCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0])->toHaveKeys([
        'id',
        'name',
        'description'
    ]);
});

test('handles large collection of expense categories', function () {
    $request = new Request();
    $resources = [];
    
    for ($i = 1; $i <= 50; $i++) {
        $category = createDummyExpenseCategory($i, 'Category ' . $i);
        $resources[] = new ExpenseCategoryResource($category);
    }
    
    $collection = new ExpenseCategoryCollection(new Collection($resources));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(50)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[49]['id'])->toBe(50);
});

// ========== INHERITANCE TESTS ==========

test('toArray delegates to parent ResourceCollection', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

test('ExpenseCategoryCollection parent class is ResourceCollection', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

// ========== INSTANCE TESTS ==========

test('multiple ExpenseCategoryCollection instances can be created', function () {
    $collection1 = new ExpenseCategoryCollection(new Collection([]));
    $collection2 = new ExpenseCategoryCollection(new Collection([]));
    
    expect($collection1)->toBeInstanceOf(ExpenseCategoryCollection::class)
        ->and($collection2)->toBeInstanceOf(ExpenseCategoryCollection::class)
        ->and($collection1)->not->toBe($collection2);
});

test('ExpenseCategoryCollection can be cloned', function () {
    $collection = new ExpenseCategoryCollection(new Collection([]));
    $clone = clone $collection;
    
    expect($clone)->toBeInstanceOf(ExpenseCategoryCollection::class)
        ->and($clone)->not->toBe($collection);
});

test('ExpenseCategoryCollection can be used in type hints', function () {
    $testFunction = function (ExpenseCategoryCollection $collection) {
        return $collection;
    };
    
    $collection = new ExpenseCategoryCollection(new Collection([]));
    $result = $testFunction($collection);
    
    expect($result)->toBe($collection);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('ExpenseCategoryCollection is not abstract', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('ExpenseCategoryCollection is not final', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('ExpenseCategoryCollection is not an interface', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('ExpenseCategoryCollection is not a trait', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('ExpenseCategoryCollection class is loaded', function () {
    expect(class_exists(ExpenseCategoryCollection::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('ExpenseCategoryCollection uses ResourceCollection', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Illuminate\Http\Resources\Json\ResourceCollection');
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('toArray method is not static', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isStatic())->toBeFalse();
});

test('toArray method is not abstract', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isAbstract())->toBeFalse();
});

// ========== DATA INTEGRITY TESTS ==========

test('preserves expense category data integrity', function () {
    $request = new Request();
    $category = createDummyExpenseCategory(42, 'Marketing');
    $resource = new ExpenseCategoryResource($category);
    
    $collection = new ExpenseCategoryCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0]['id'])->toBe(42)
        ->and($result[0]['name'])->toBe('Marketing');
});

test('handles different category names', function () {
    $request = new Request();
    
    $category1 = createDummyExpenseCategory(1, 'Travel');
    $category2 = createDummyExpenseCategory(2, 'Supplies');
    $category3 = createDummyExpenseCategory(3, 'Utilities');
    
    $resource1 = new ExpenseCategoryResource($category1);
    $resource2 = new ExpenseCategoryResource($category2);
    $resource3 = new ExpenseCategoryResource($category3);
    
    $collection = new ExpenseCategoryCollection(new Collection([$resource1, $resource2, $resource3]));
    $result = $collection->toArray($request);
    
    expect($result[0]['name'])->toBe('Travel')
        ->and($result[1]['name'])->toBe('Supplies')
        ->and($result[2]['name'])->toBe('Utilities');
});

// ========== FILE STRUCTURE TESTS ==========

test('ExpenseCategoryCollection file is simple and concise', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be small (< 1000 bytes for simple delegation)
    expect(strlen($fileContent))->toBeLessThan(1000);
});

test('ExpenseCategoryCollection has minimal line count', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(30);
});

// ========== COMPREHENSIVE FIELD TESTS ==========

test('transformed items include all category fields', function () {
    $request = new Request();
    $category = createDummyExpenseCategory(1, 'Travel');
    $resource = new ExpenseCategoryResource($category);
    
    $collection = new ExpenseCategoryCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0])->toHaveKeys([
        'id', 'name', 'description'
    ]);
});

test('result is a valid array structure', function () {
    $request = new Request();
    $category = createDummyExpenseCategory(1, 'Travel');
    $resource = new ExpenseCategoryResource($category);
    
    $collection = new ExpenseCategoryCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and(is_array($result))->toBeTrue();
});

// ========== DOCUMENTATION TESTS ==========

test('toArray method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('toArray method has return type documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $method = $reflection->getMethod('toArray');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@return');
});

test('toArray method has param documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryCollection::class);
    $method = $reflection->getMethod('toArray');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@param');
});

// ========== DESCRIPTION TESTS ==========

test('handles categories with different descriptions', function () {
    $request = new Request();
    
    $category1 = createDummyExpenseCategory(1, 'Travel');
    $category1->description = 'Business travel expenses';
    
    $category2 = createDummyExpenseCategory(2, 'Supplies');
    $category2->description = 'Office supplies and materials';
    
    $resource1 = new ExpenseCategoryResource($category1);
    $resource2 = new ExpenseCategoryResource($category2);
    
    $collection = new ExpenseCategoryCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result[0]['description'])->toBe('Business travel expenses')
        ->and($result[1]['description'])->toBe('Office supplies and materials');
});