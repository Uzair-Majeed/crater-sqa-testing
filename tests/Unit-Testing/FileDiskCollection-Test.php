<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Crater\Http\Resources\FileDiskCollection;
use Crater\Http\Resources\FileDiskResource;

// Helper function to create dummy file disk with all required properties
function createDummyFileDisk($id, $name) {
    return new class($id, $name) {
        private $data;
        
        public function __construct($id, $name) {
            $this->data = [
                'id' => $id,
                'name' => $name,
                'driver' => 's3',
                'credentials' => ['key' => 'value'],
                'set_as_default' => false,
                'company_id' => 1,
                'type' => 'REMOTE',
                'created_at' => '2024-01-01 00:00:00',
            ];
        }
        
        public function __get($key) {
            return $this->data[$key] ?? null;
        }
    };
}

// ========== CLASS STRUCTURE TESTS ==========

test('FileDiskCollection can be instantiated', function () {
    $collection = new FileDiskCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(FileDiskCollection::class);
});

test('FileDiskCollection extends ResourceCollection', function () {
    $collection = new FileDiskCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('FileDiskCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('FileDiskCollection has toArray method', function () {
    $collection = new FileDiskCollection(new Collection([]));
    expect(method_exists($collection, 'toArray'))->toBeTrue();
});

test('toArray method is public', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue();
});

test('toArray method accepts request parameter', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $method = $reflection->getMethod('toArray');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

// ========== FUNCTIONAL TESTS ==========

test('handles empty collection', function () {
    $request = new Request();
    $collection = new FileDiskCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('transforms single file disk resource', function () {
    $request = new Request();
    $disk = createDummyFileDisk(1, 'S3 Disk');
    $resource = new FileDiskResource($disk);
    
    $collection = new FileDiskCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0])->toHaveKey('id')
        ->and($result[0]['id'])->toBe(1);
});

test('transforms multiple file disk resources', function () {
    $request = new Request();
    $disk1 = createDummyFileDisk(1, 'S3 Disk');
    $disk2 = createDummyFileDisk(2, 'Local Disk');
    
    $resource1 = new FileDiskResource($disk1);
    $resource2 = new FileDiskResource($disk2);
    
    $collection = new FileDiskCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[1]['id'])->toBe(2);
});

test('each transformed item has required fields', function () {
    $request = new Request();
    $disk = createDummyFileDisk(1, 'S3 Disk');
    $resource = new FileDiskResource($disk);
    
    $collection = new FileDiskCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0])->toHaveKeys([
        'id',
        'name',
        'driver'
    ]);
});

test('handles large collection of file disks', function () {
    $request = new Request();
    $resources = [];
    
    for ($i = 1; $i <= 50; $i++) {
        $disk = createDummyFileDisk($i, 'Disk ' . $i);
        $resources[] = new FileDiskResource($disk);
    }
    
    $collection = new FileDiskCollection(new Collection($resources));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(50)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[49]['id'])->toBe(50);
});

// ========== INHERITANCE TESTS ==========

test('toArray delegates to parent ResourceCollection', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

test('FileDiskCollection parent class is ResourceCollection', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

// ========== INSTANCE TESTS ==========

test('multiple FileDiskCollection instances can be created', function () {
    $collection1 = new FileDiskCollection(new Collection([]));
    $collection2 = new FileDiskCollection(new Collection([]));
    
    expect($collection1)->toBeInstanceOf(FileDiskCollection::class)
        ->and($collection2)->toBeInstanceOf(FileDiskCollection::class)
        ->and($collection1)->not->toBe($collection2);
});

test('FileDiskCollection can be cloned', function () {
    $collection = new FileDiskCollection(new Collection([]));
    $clone = clone $collection;
    
    expect($clone)->toBeInstanceOf(FileDiskCollection::class)
        ->and($clone)->not->toBe($collection);
});

test('FileDiskCollection can be used in type hints', function () {
    $testFunction = function (FileDiskCollection $collection) {
        return $collection;
    };
    
    $collection = new FileDiskCollection(new Collection([]));
    $result = $testFunction($collection);
    
    expect($result)->toBe($collection);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('FileDiskCollection is not abstract', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('FileDiskCollection is not final', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('FileDiskCollection is not an interface', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('FileDiskCollection is not a trait', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('FileDiskCollection class is loaded', function () {
    expect(class_exists(FileDiskCollection::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('FileDiskCollection uses ResourceCollection', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Illuminate\Http\Resources\Json\ResourceCollection');
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('toArray method is not static', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isStatic())->toBeFalse();
});

test('toArray method is not abstract', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isAbstract())->toBeFalse();
});

// ========== DATA INTEGRITY TESTS ==========

test('preserves file disk data integrity', function () {
    $request = new Request();
    $disk = createDummyFileDisk(42, 'Test Disk');
    $resource = new FileDiskResource($disk);
    
    $collection = new FileDiskCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0]['id'])->toBe(42)
        ->and($result[0]['name'])->toBe('Test Disk');
});

test('handles different disk names', function () {
    $request = new Request();
    
    $disk1 = createDummyFileDisk(1, 'S3 Storage');
    $disk2 = createDummyFileDisk(2, 'Local Storage');
    $disk3 = createDummyFileDisk(3, 'Dropbox Storage');
    
    $resource1 = new FileDiskResource($disk1);
    $resource2 = new FileDiskResource($disk2);
    $resource3 = new FileDiskResource($disk3);
    
    $collection = new FileDiskCollection(new Collection([$resource1, $resource2, $resource3]));
    $result = $collection->toArray($request);
    
    expect($result[0]['name'])->toBe('S3 Storage')
        ->and($result[1]['name'])->toBe('Local Storage')
        ->and($result[2]['name'])->toBe('Dropbox Storage');
});

test('handles different drivers', function () {
    $request = new Request();
    
    $disk1 = createDummyFileDisk(1, 'S3 Disk');
    $disk1->driver = 's3';
    
    $disk2 = createDummyFileDisk(2, 'Local Disk');
    $disk2->driver = 'local';
    
    $resource1 = new FileDiskResource($disk1);
    $resource2 = new FileDiskResource($disk2);
    
    $collection = new FileDiskCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result[0]['driver'])->toBe('s3')
        ->and($result[1]['driver'])->toBe('local');
});

// ========== FILE STRUCTURE TESTS ==========

test('FileDiskCollection file is simple and concise', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be small (< 1000 bytes for simple delegation)
    expect(strlen($fileContent))->toBeLessThan(1000);
});

test('FileDiskCollection has minimal line count', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(30);
});

// ========== COMPREHENSIVE FIELD TESTS ==========

test('transformed items include all disk fields', function () {
    $request = new Request();
    $disk = createDummyFileDisk(1, 'S3 Disk');
    $resource = new FileDiskResource($disk);
    
    $collection = new FileDiskCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result[0])->toHaveKeys([
        'id', 'name', 'driver'
    ]);
});

test('result is a valid array structure', function () {
    $request = new Request();
    $disk = createDummyFileDisk(1, 'S3 Disk');
    $resource = new FileDiskResource($disk);
    
    $collection = new FileDiskCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and(is_array($result))->toBeTrue();
});

// ========== DOCUMENTATION TESTS ==========

test('toArray method has documentation', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('toArray method has return type documentation', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $method = $reflection->getMethod('toArray');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@return');
});

test('toArray method has param documentation', function () {
    $reflection = new ReflectionClass(FileDiskCollection::class);
    $method = $reflection->getMethod('toArray');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@param');
});

// ========== SET AS DEFAULT TESTS ==========

test('handles disks with different set_as_default values', function () {
    $request = new Request();
    
    $disk1 = createDummyFileDisk(1, 'Default Disk');
    $disk1->set_as_default = true;
    
    $disk2 = createDummyFileDisk(2, 'Non-Default Disk');
    $disk2->set_as_default = false;
    
    $resource1 = new FileDiskResource($disk1);
    $resource2 = new FileDiskResource($disk2);
    
    $collection = new FileDiskCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result)->toHaveCount(2);
});