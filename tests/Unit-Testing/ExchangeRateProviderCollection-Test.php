<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Crater\Http\Resources\ExchangeRateProviderCollection;
use Crater\Http\Resources\ExchangeRateProviderResource;

// Helper function to create dummy exchange rate provider with all required properties
function createDummyExchangeRateProvider($id, $driver) {
    return new class($id, $driver) {
        private $data;
        
        public function __construct($id, $driver) {
            $this->data = [
                'id' => $id,
                'driver' => $driver,
                'key' => 'test_key_' . $id,
                'active' => true,
                'currencies' => ['USD', 'EUR'],
                'driver_config' => ['type' => 'PREMIUM'],
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
    };
}

// ========== CLASS STRUCTURE TESTS ==========

test('ExchangeRateProviderCollection can be instantiated', function () {
    $collection = new ExchangeRateProviderCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(ExchangeRateProviderCollection::class);
});

test('ExchangeRateProviderCollection extends ResourceCollection', function () {
    $collection = new ExchangeRateProviderCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('ExchangeRateProviderCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('ExchangeRateProviderCollection has toArray method', function () {
    $collection = new ExchangeRateProviderCollection(new Collection([]));
    expect(method_exists($collection, 'toArray'))->toBeTrue();
});

test('toArray method is public', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue();
});

test('toArray method accepts request parameter', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $method = $reflection->getMethod('toArray');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// ========== FUNCTIONAL TESTS ==========

test('handles empty collection', function () {
    $request = new Request();
    $collection = new ExchangeRateProviderCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('transforms single exchange rate provider resource', function () {
    $request = new Request();
    $provider = createDummyExchangeRateProvider(1, 'currency_freak');
    $resource = new ExchangeRateProviderResource($provider);
    
    $collection = new ExchangeRateProviderCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0])->toHaveKey('id')
        ->and($result[0]['id'])->toBe(1);
});

test('transforms multiple exchange rate provider resources', function () {
    $request = new Request();
    $provider1 = createDummyExchangeRateProvider(1, 'currency_freak');
    $provider2 = createDummyExchangeRateProvider(2, 'currency_layer');
    
    $resource1 = new ExchangeRateProviderResource($provider1);
    $resource2 = new ExchangeRateProviderResource($provider2);
    
    $collection = new ExchangeRateProviderCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[1]['id'])->toBe(2);
});

test('each transformed item has required fields', function () {
    $request = new Request();
    $provider = createDummyExchangeRateProvider(1, 'currency_freak');
    $resource = new ExchangeRateProviderResource($provider);
    
    $collection = new ExchangeRateProviderCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0])->toHaveKeys([
        'id',
        'driver',
        'key',
        'active',
        'currencies',
        'driver_config'
    ]);
});

test('handles large collection of exchange rate providers', function () {
    $request = new Request();
    $resources = [];
    
    for ($i = 1; $i <= 50; $i++) {
        $provider = createDummyExchangeRateProvider($i, 'currency_freak');
        $resources[] = new ExchangeRateProviderResource($provider);
    }
    
    $collection = new ExchangeRateProviderCollection(new Collection($resources));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(50)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[49]['id'])->toBe(50);
});

// ========== INHERITANCE TESTS ==========

test('toArray delegates to parent ResourceCollection', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

test('ExchangeRateProviderCollection parent class is ResourceCollection', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

// ========== INSTANCE TESTS ==========

test('multiple ExchangeRateProviderCollection instances can be created', function () {
    $collection1 = new ExchangeRateProviderCollection(new Collection([]));
    $collection2 = new ExchangeRateProviderCollection(new Collection([]));
    
    expect($collection1)->toBeInstanceOf(ExchangeRateProviderCollection::class)
        ->and($collection2)->toBeInstanceOf(ExchangeRateProviderCollection::class)
        ->and($collection1)->not->toBe($collection2);
});

test('ExchangeRateProviderCollection can be cloned', function () {
    $collection = new ExchangeRateProviderCollection(new Collection([]));
    $clone = clone $collection;
    
    expect($clone)->toBeInstanceOf(ExchangeRateProviderCollection::class)
        ->and($clone)->not->toBe($collection);
});

test('ExchangeRateProviderCollection can be used in type hints', function () {
    $testFunction = function (ExchangeRateProviderCollection $collection) {
        return $collection;
    };
    
    $collection = new ExchangeRateProviderCollection(new Collection([]));
    $result = $testFunction($collection);
    
    expect($result)->toBe($collection);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('ExchangeRateProviderCollection is not abstract', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('ExchangeRateProviderCollection is not final', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('ExchangeRateProviderCollection is not an interface', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('ExchangeRateProviderCollection is not a trait', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('ExchangeRateProviderCollection class is loaded', function () {
    expect(class_exists(ExchangeRateProviderCollection::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('ExchangeRateProviderCollection uses ResourceCollection', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Illuminate\Http\Resources\Json\ResourceCollection');
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('toArray method is not static', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isStatic())->toBeFalse();
});

test('toArray method is not abstract', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isAbstract())->toBeFalse();
});

// ========== DATA INTEGRITY TESTS ==========

test('preserves exchange rate provider data integrity', function () {
    $request = new Request();
    $provider = createDummyExchangeRateProvider(42, 'open_exchange_rate');
    $resource = new ExchangeRateProviderResource($provider);
    
    $collection = new ExchangeRateProviderCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0]['id'])->toBe(42)
        ->and($result[0]['driver'])->toBe('open_exchange_rate');
});

test('handles different drivers', function () {
    $request = new Request();
    
    $provider1 = createDummyExchangeRateProvider(1, 'currency_freak');
    $provider2 = createDummyExchangeRateProvider(2, 'currency_layer');
    $provider3 = createDummyExchangeRateProvider(3, 'open_exchange_rate');
    
    $resource1 = new ExchangeRateProviderResource($provider1);
    $resource2 = new ExchangeRateProviderResource($provider2);
    $resource3 = new ExchangeRateProviderResource($provider3);
    
    $collection = new ExchangeRateProviderCollection(new Collection([$resource1, $resource2, $resource3]));
    $result = $collection->toArray($request);
    
    expect($result[0]['driver'])->toBe('currency_freak')
        ->and($result[1]['driver'])->toBe('currency_layer')
        ->and($result[2]['driver'])->toBe('open_exchange_rate');
});

// ========== FILE STRUCTURE TESTS ==========

test('ExchangeRateProviderCollection file is simple and concise', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be small (< 1000 bytes for simple delegation)
    expect(strlen($fileContent))->toBeLessThan(1000);
});

test('ExchangeRateProviderCollection has minimal line count', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(30);
});

// ========== COMPREHENSIVE FIELD TESTS ==========

test('transformed items include all provider fields', function () {
    $request = new Request();
    $provider = createDummyExchangeRateProvider(1, 'currency_freak');
    $resource = new ExchangeRateProviderResource($provider);
    
    $collection = new ExchangeRateProviderCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0])->toHaveKeys([
        'id', 'driver', 'key', 'active', 'currencies', 'driver_config'
    ]);
});

test('result is a valid array structure', function () {
    $request = new Request();
    $provider = createDummyExchangeRateProvider(1, 'currency_freak');
    $resource = new ExchangeRateProviderResource($provider);
    
    $collection = new ExchangeRateProviderCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and(is_array($result))->toBeTrue();
});

// ========== DOCUMENTATION TESTS ==========

test('toArray method has documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('toArray method has return type documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $method = $reflection->getMethod('toArray');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@return');
});

test('toArray method has param documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderCollection::class);
    $method = $reflection->getMethod('toArray');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@param');
});

// ========== ACTIVE STATUS TESTS ==========

test('handles active and inactive providers', function () {
    $request = new Request();
    
    $provider1 = createDummyExchangeRateProvider(1, 'currency_freak');
    $provider1->active = true;
    
    $provider2 = createDummyExchangeRateProvider(2, 'currency_layer');
    $provider2->active = false;
    
    $resource1 = new ExchangeRateProviderResource($provider1);
    $resource2 = new ExchangeRateProviderResource($provider2);
    
    $collection = new ExchangeRateProviderCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result[0]['active'])->toBeTrue()
        ->and($result[1]['active'])->toBeFalse();
});

// ========== CURRENCIES TESTS ==========

test('handles different currency configurations', function () {
    $request = new Request();
    
    $provider1 = createDummyExchangeRateProvider(1, 'currency_freak');
    $provider1->currencies = ['USD', 'EUR'];
    
    $provider2 = createDummyExchangeRateProvider(2, 'currency_layer');
    $provider2->currencies = ['GBP', 'JPY', 'AUD'];
    
    $resource1 = new ExchangeRateProviderResource($provider1);
    $resource2 = new ExchangeRateProviderResource($provider2);
    
    $collection = new ExchangeRateProviderCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result[0]['currencies'])->toBe(['USD', 'EUR'])
        ->and($result[1]['currencies'])->toBe(['GBP', 'JPY', 'AUD']);
});