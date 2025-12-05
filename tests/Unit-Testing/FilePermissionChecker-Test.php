<?php

use Crater\Space\FilePermissionChecker;

// Helper to access private methods via Reflection
function callPrivateMethod($object, $methodName, array $parameters = [])
{
    $reflection = new ReflectionClass($object);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
}

// Helper to get private properties via Reflection
function getPrivateProperty($object, $propertyName)
{
    $reflection = new ReflectionClass($object);
    $property = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    return $property->getValue($object);
}

// ========== CLASS STRUCTURE TESTS ==========

test('FilePermissionChecker can be instantiated', function () {
    $checker = new FilePermissionChecker();
    expect($checker)->toBeInstanceOf(FilePermissionChecker::class);
});

test('FilePermissionChecker is in correct namespace', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Space');
});

test('FilePermissionChecker is not abstract', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('FilePermissionChecker is instantiable', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== CONSTRUCTOR TESTS ==========

test('constructor initializes results property with empty permissions', function () {
    $checker = new FilePermissionChecker();
    $results = getPrivateProperty($checker, 'results');
    
    expect($results)->toBeArray()
        ->and($results)->toHaveKey('permissions')
        ->and($results['permissions'])->toBeArray()
        ->and($results['permissions'])->toBeEmpty();
});

test('constructor initializes results property with null errors', function () {
    $checker = new FilePermissionChecker();
    $results = getPrivateProperty($checker, 'results');
    
    expect($results)->toHaveKey('errors')
        ->and($results['errors'])->toBeNull();
});

// ========== METHOD EXISTENCE TESTS ==========

test('FilePermissionChecker has check method', function () {
    $checker = new FilePermissionChecker();
    expect(method_exists($checker, 'check'))->toBeTrue();
});

test('FilePermissionChecker has getPermission method', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    expect($reflection->hasMethod('getPermission'))->toBeTrue();
});

test('FilePermissionChecker has addFile method', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    expect($reflection->hasMethod('addFile'))->toBeTrue();
});

test('FilePermissionChecker has addFileAndSetErrors method', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    expect($reflection->hasMethod('addFileAndSetErrors'))->toBeTrue();
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('check method is public', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('check');
    
    expect($method->isPublic())->toBeTrue();
});

test('getPermission method is private', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('getPermission');
    
    expect($method->isPrivate())->toBeTrue();
});

test('addFile method is private', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('addFile');
    
    expect($method->isPrivate())->toBeTrue();
});

test('addFileAndSetErrors method is private', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('addFileAndSetErrors');
    
    expect($method->isPrivate())->toBeTrue();
});

// ========== CHECK METHOD TESTS ==========

test('check method handles empty folders array', function () {
    $checker = new FilePermissionChecker();
    $results = $checker->check([]);
    
    expect($results)->toBeArray()
        ->and($results['errors'])->toBeNull()
        ->and($results['permissions'])->toBeEmpty();
});

test('check method returns array with permissions and errors keys', function () {
    $checker = new FilePermissionChecker();
    $results = $checker->check([]);
    
    expect($results)->toHaveKeys(['permissions', 'errors']);
});

// ========== ADDFILE METHOD TESTS ==========

test('addFile adds entry with isSet true', function () {
    $checker = new FilePermissionChecker();
    callPrivateMethod($checker, 'addFile', ['test_folder', '0755', true]);
    
    $results = getPrivateProperty($checker, 'results');
    
    expect($results['permissions'])->toHaveCount(1)
        ->and($results['permissions'][0])->toMatchArray([
            'folder' => 'test_folder',
            'permission' => '0755',
            'isSet' => true,
        ]);
});

test('addFile adds entry with isSet false', function () {
    $checker = new FilePermissionChecker();
    callPrivateMethod($checker, 'addFile', ['test_folder', '0644', false]);
    
    $results = getPrivateProperty($checker, 'results');
    
    expect($results['permissions'])->toHaveCount(1)
        ->and($results['permissions'][0])->toMatchArray([
            'folder' => 'test_folder',
            'permission' => '0644',
            'isSet' => false,
        ]);
});

test('addFile adds multiple entries correctly', function () {
    $checker = new FilePermissionChecker();
    callPrivateMethod($checker, 'addFile', ['folder_a', '0755', true]);
    callPrivateMethod($checker, 'addFile', ['folder_b', '0644', false]);
    callPrivateMethod($checker, 'addFile', ['folder_c', '0777', true]);
    
    $results = getPrivateProperty($checker, 'results');
    
    expect($results['permissions'])->toHaveCount(3)
        ->and($results['permissions'][0]['folder'])->toBe('folder_a')
        ->and($results['permissions'][1]['folder'])->toBe('folder_b')
        ->and($results['permissions'][2]['folder'])->toBe('folder_c');
});

// ========== ADDFILEANDSETERRORS METHOD TESTS ==========

test('addFileAndSetErrors adds file entry', function () {
    $checker = new FilePermissionChecker();
    callPrivateMethod($checker, 'addFileAndSetErrors', ['error_folder', '0777', false]);
    
    $results = getPrivateProperty($checker, 'results');
    
    expect($results['permissions'])->toHaveCount(1)
        ->and($results['permissions'][0])->toMatchArray([
            'folder' => 'error_folder',
            'permission' => '0777',
            'isSet' => false,
        ]);
});

test('addFileAndSetErrors sets errors to true', function () {
    $checker = new FilePermissionChecker();
    $initialResults = getPrivateProperty($checker, 'results');
    expect($initialResults['errors'])->toBeNull();
    
    callPrivateMethod($checker, 'addFileAndSetErrors', ['error_folder', '0777', false]);
    
    $results = getPrivateProperty($checker, 'results');
    expect($results['errors'])->toBeTrue();
});

test('addFileAndSetErrors keeps errors true on subsequent calls', function () {
    $checker = new FilePermissionChecker();
    callPrivateMethod($checker, 'addFileAndSetErrors', ['folder_1', '0777', false]);
    callPrivateMethod($checker, 'addFileAndSetErrors', ['folder_2', '0755', false]);
    
    $results = getPrivateProperty($checker, 'results');
    
    expect($results['permissions'])->toHaveCount(2)
        ->and($results['errors'])->toBeTrue();
});

// ========== INSTANCE TESTS ==========

test('multiple FilePermissionChecker instances can be created', function () {
    $checker1 = new FilePermissionChecker();
    $checker2 = new FilePermissionChecker();
    
    expect($checker1)->toBeInstanceOf(FilePermissionChecker::class)
        ->and($checker2)->toBeInstanceOf(FilePermissionChecker::class)
        ->and($checker1)->not->toBe($checker2);
});

test('FilePermissionChecker can be cloned', function () {
    $checker = new FilePermissionChecker();
    $clone = clone $checker;
    
    expect($clone)->toBeInstanceOf(FilePermissionChecker::class)
        ->and($clone)->not->toBe($checker);
});

test('FilePermissionChecker can be used in type hints', function () {
    $testFunction = function (FilePermissionChecker $checker) {
        return $checker;
    };
    
    $checker = new FilePermissionChecker();
    $result = $testFunction($checker);
    
    expect($result)->toBe($checker);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('FilePermissionChecker is not final', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('FilePermissionChecker is not an interface', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('FilePermissionChecker is not a trait', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('FilePermissionChecker class is loaded', function () {
    expect(class_exists(FilePermissionChecker::class))->toBeTrue();
});

// ========== PROPERTIES TESTS ==========

test('FilePermissionChecker has results property', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    expect($reflection->hasProperty('results'))->toBeTrue();
});

test('results property is protected', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $property = $reflection->getProperty('results');
    
    expect($property->isProtected())->toBeTrue();
});

// ========== FILE STRUCTURE TESTS ==========

test('FilePermissionChecker file has expected structure', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class FilePermissionChecker')
        ->and($fileContent)->toContain('protected $results')
        ->and($fileContent)->toContain('public function __construct()')
        ->and($fileContent)->toContain('public function check(array $folders)');
});

test('FilePermissionChecker has reasonable line count', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeGreaterThan(50)
        ->and($lineCount)->toBeLessThan(150);
});

// ========== IMPLEMENTATION TESTS ==========

test('check method uses foreach loop', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('foreach ($folders as $folder => $permission)');
});

test('check method calls getPermission', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->getPermission($folder)');
});

test('check method calls addFileAndSetErrors when permission not met', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->addFileAndSetErrors');
});

test('check method calls addFile when permission is met', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->addFile($folder, $permission, true)');
});

test('check method returns results', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('return $this->results');
});

test('getPermission uses fileperms function', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('fileperms');
});

test('getPermission uses base_path function', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('base_path($folder)');
});

test('getPermission uses sprintf with %o format', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('sprintf(\'%o\'');
});

test('getPermission uses substr to get last 4 characters', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('substr')
        ->and($fileContent)->toContain('-4');
});

test('addFile uses array_push', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('array_push($this->results[\'permissions\']');
});

test('addFileAndSetErrors calls addFile', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->addFile($folder, $permission, $isSet)');
});

test('addFileAndSetErrors implementation sets errors property to true', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->results[\'errors\'] = true');
});

// ========== DOCUMENTATION TESTS ==========

test('constructor has documentation', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('__construct');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('check method has documentation', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('check');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('getPermission method has documentation', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('getPermission');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('addFile method has documentation', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('addFile');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('addFileAndSetErrors method has documentation', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('addFileAndSetErrors');
    
    expect($method->getDocComment())->not->toBeFalse();
});

// ========== METHOD PARAMETERS TESTS ==========

test('check method accepts array parameter', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('check');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('folders');
});

test('getPermission method accepts folder parameter', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('getPermission');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('folder');
});

test('addFile method accepts three parameters', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('addFile');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(3)
        ->and($parameters[0]->getName())->toBe('folder')
        ->and($parameters[1]->getName())->toBe('permission')
        ->and($parameters[2]->getName())->toBe('isSet');
});

test('addFileAndSetErrors method accepts three parameters', function () {
    $reflection = new ReflectionClass(FilePermissionChecker::class);
    $method = $reflection->getMethod('addFileAndSetErrors');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(3)
        ->and($parameters[0]->getName())->toBe('folder')
        ->and($parameters[1]->getName())->toBe('permission')
        ->and($parameters[2]->getName())->toBe('isSet');
});

// ========== DATA INTEGRITY TESTS ==========

test('different instances have independent results', function () {
    $checker1 = new FilePermissionChecker();
    $checker2 = new FilePermissionChecker();
    
    callPrivateMethod($checker1, 'addFile', ['folder_1', '0755', true]);
    callPrivateMethod($checker2, 'addFile', ['folder_2', '0644', false]);
    
    $results1 = getPrivateProperty($checker1, 'results');
    $results2 = getPrivateProperty($checker2, 'results');
    
    expect($results1['permissions'][0]['folder'])->toBe('folder_1')
        ->and($results2['permissions'][0]['folder'])->toBe('folder_2')
        ->and($results1['permissions'][0]['folder'])->not->toBe($results2['permissions'][0]['folder']);
});