<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Crater\Http\Resources\ExchangeRateLogCollection;
use Crater\Http\Resources\ExchangeRateLogResource;

// Helper function to create dummy exchange rate log with all required properties
function createDummyExchangeRateLog($id, $exchangeRate) {
    return new class($id, $exchangeRate) {
        private $data;
        
        public function __construct($id, $exchangeRate) {
            $this->data = [
                'id' => $id,
                'exchange_rate' => $exchangeRate,
                'company_id' => 1,
                'currency_id' => 1,
                'base_currency_id' => 2,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ];
        }
        
        public function __get($key) {
            return $this->data[$key] ?? null;
        }
        
        public function currency() {
            return new class { public function exists() { return false; } };
        }
        
        public function company() {
            return new class { public function exists() { return false; } };
        }
    };
}

// ========== CLASS STRUCTURE TESTS ==========

test('ExchangeRateLogCollection can be instantiated', function () {
    $collection = new ExchangeRateLogCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(ExchangeRateLogCollection::class);
});

test('ExchangeRateLogCollection extends ResourceCollection', function () {
    $collection = new ExchangeRateLogCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('ExchangeRateLogCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('ExchangeRateLogCollection has toArray method', function () {
    $collection = new ExchangeRateLogCollection(new Collection([]));
    expect(method_exists($collection, 'toArray'))->toBeTrue();
});

test('toArray method is public', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue();
});

test('toArray method accepts request parameter', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $method = $reflection->getMethod('toArray');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// ========== FUNCTIONAL TESTS ==========

test('handles empty collection', function () {
    $request = new Request();
    $collection = new ExchangeRateLogCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('transforms single exchange rate log resource', function () {
    $request = new Request();
    $log = createDummyExchangeRateLog(1, 1.5);
    $resource = new ExchangeRateLogResource($log);
    
    $collection = new ExchangeRateLogCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0])->toHaveKey('id')
        ->and($result[0]['id'])->toBe(1);
});

test('transforms multiple exchange rate log resources', function () {
    $request = new Request();
    $log1 = createDummyExchangeRateLog(1, 1.5);
    $log2 = createDummyExchangeRateLog(2, 2.0);
    
    $resource1 = new ExchangeRateLogResource($log1);
    $resource2 = new ExchangeRateLogResource($log2);
    
    $collection = new ExchangeRateLogCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[1]['id'])->toBe(2);
});

test('each transformed item has required fields', function () {
    $request = new Request();
    $log = createDummyExchangeRateLog(1, 1.5);
    $resource = new ExchangeRateLogResource($log);
    
    $collection = new ExchangeRateLogCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0])->toHaveKeys([
        'id',
        'exchange_rate',
        'company_id',
        'currency_id',
        'base_currency_id'
    ]);
});

test('handles large collection of exchange rate logs', function () {
    $request = new Request();
    $resources = [];
    
    for ($i = 1; $i <= 50; $i++) {
        $log = createDummyExchangeRateLog($i, $i * 0.1);
        $resources[] = new ExchangeRateLogResource($log);
    }
    
    $collection = new ExchangeRateLogCollection(new Collection($resources));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(50)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[49]['id'])->toBe(50);
});

// ========== INHERITANCE TESTS ==========

test('toArray delegates to parent ResourceCollection', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

test('ExchangeRateLogCollection parent class is ResourceCollection', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

// ========== INSTANCE TESTS ==========

test('multiple ExchangeRateLogCollection instances can be created', function () {
    $collection1 = new ExchangeRateLogCollection(new Collection([]));
    $collection2 = new ExchangeRateLogCollection(new Collection([]));
    
    expect($collection1)->toBeInstanceOf(ExchangeRateLogCollection::class)
        ->and($collection2)->toBeInstanceOf(ExchangeRateLogCollection::class)
        ->and($collection1)->not->toBe($collection2);
});

test('ExchangeRateLogCollection can be cloned', function () {
    $collection = new ExchangeRateLogCollection(new Collection([]));
    $clone = clone $collection;
    
    expect($clone)->toBeInstanceOf(ExchangeRateLogCollection::class)
        ->and($clone)->not->toBe($collection);
});

test('ExchangeRateLogCollection can be used in type hints', function () {
    $testFunction = function (ExchangeRateLogCollection $collection) {
        return $collection;
    };
    
    $collection = new ExchangeRateLogCollection(new Collection([]));
    $result = $testFunction($collection);
    
    expect($result)->toBe($collection);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('ExchangeRateLogCollection is not abstract', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('ExchangeRateLogCollection is not final', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('ExchangeRateLogCollection is not an interface', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('ExchangeRateLogCollection is not a trait', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('ExchangeRateLogCollection class is loaded', function () {
    expect(class_exists(ExchangeRateLogCollection::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('ExchangeRateLogCollection uses ResourceCollection', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Illuminate\Http\Resources\Json\ResourceCollection');
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('toArray method is not static', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isStatic())->toBeFalse();
});

test('toArray method is not abstract', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isAbstract())->toBeFalse();
});

// ========== DATA INTEGRITY TESTS ==========

test('preserves exchange rate log data integrity', function () {
    $request = new Request();
    $log = createDummyExchangeRateLog(42, 3.14);
    $resource = new ExchangeRateLogResource($log);
    
    $collection = new ExchangeRateLogCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0]['id'])->toBe(42)
        ->and($result[0]['exchange_rate'])->toBe(3.14);
});

test('handles different exchange rates', function () {
    $request = new Request();
    
    $log1 = createDummyExchangeRateLog(1, 1.0);
    $log2 = createDummyExchangeRateLog(2, 2.5);
    
    $resource1 = new ExchangeRateLogResource($log1);
    $resource2 = new ExchangeRateLogResource($log2);
    
    $collection = new ExchangeRateLogCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result[0]['exchange_rate'])->toBe(1.0)
        ->and($result[1]['exchange_rate'])->toBe(2.5);
});

// ========== FILE STRUCTURE TESTS ==========

test('ExchangeRateLogCollection file is simple and concise', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be small (< 1000 bytes for simple delegation)
    expect(strlen($fileContent))->toBeLessThan(1000);
});

test('ExchangeRateLogCollection has minimal line count', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(30);
});

// ========== COMPREHENSIVE FIELD TESTS ==========

test('transformed items include all fields', function () {
    $request = new Request();
    $log = createDummyExchangeRateLog(1, 1.5);
    $resource = new ExchangeRateLogResource($log);
    
    $collection = new ExchangeRateLogCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0])->toHaveKeys([
        'id', 'exchange_rate', 'company_id', 'currency_id', 'base_currency_id'
    ]);
});

test('result is a valid array structure', function () {
    $request = new Request();
    $log = createDummyExchangeRateLog(1, 1.5);
    $resource = new ExchangeRateLogResource($log);
    
    $collection = new ExchangeRateLogCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and(is_array($result))->toBeTrue();
});

// ========== DOCUMENTATION TESTS ==========

test('toArray method has documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('toArray method has return type documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $method = $reflection->getMethod('toArray');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@return');
});

test('toArray method has param documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateLogCollection::class);
    $method = $reflection->getMethod('toArray');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@param');
});