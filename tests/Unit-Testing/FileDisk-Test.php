<?php

use Crater\Models\FileDisk;

// ========== CLASS STRUCTURE TESTS ==========

test('FileDisk can be instantiated', function () {
    $fileDisk = new FileDisk();
    expect($fileDisk)->toBeInstanceOf(FileDisk::class);
});

test('FileDisk extends Model', function () {
    $fileDisk = new FileDisk();
    expect($fileDisk)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
});

test('FileDisk is in correct namespace', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Models');
});

test('FileDisk is not abstract', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('FileDisk is instantiable', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== CONSTANTS TESTS ==========

test('FileDisk has DISK_TYPE_SYSTEM constant', function () {
    expect(FileDisk::DISK_TYPE_SYSTEM)->toBe('SYSTEM');
});

test('FileDisk has DISK_TYPE_REMOTE constant', function () {
    expect(FileDisk::DISK_TYPE_REMOTE)->toBe('REMOTE');
});

// ========== GUARDED PROPERTIES TESTS ==========

test('FileDisk has guarded properties', function () {
    $fileDisk = new FileDisk();
    $guarded = $fileDisk->getGuarded();
    
    expect($guarded)->toContain('id');
});

// ========== CASTS TESTS ==========

test('FileDisk casts set_as_default to boolean', function () {
    $fileDisk = new FileDisk();
    $casts = $fileDisk->getCasts();
    
    expect($casts)->toHaveKey('set_as_default')
        ->and($casts['set_as_default'])->toBe('boolean');
});

// ========== TRAITS TESTS ==========

test('FileDisk uses HasFactory trait', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
});

// ========== METHOD EXISTENCE TESTS ==========

test('FileDisk has setCredentialsAttribute method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'setCredentialsAttribute'))->toBeTrue();
});

test('FileDisk has scopeWhereOrder method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'scopeWhereOrder'))->toBeTrue();
});

test('FileDisk has scopeFileDisksBetween method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'scopeFileDisksBetween'))->toBeTrue();
});

test('FileDisk has scopeWhereSearch method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'scopeWhereSearch'))->toBeTrue();
});

test('FileDisk has scopePaginateData method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'scopePaginateData'))->toBeTrue();
});

test('FileDisk has scopeApplyFilters method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'scopeApplyFilters'))->toBeTrue();
});

test('FileDisk has setConfig method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'setConfig'))->toBeTrue();
});

test('FileDisk has setAsDefault method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'setAsDefault'))->toBeTrue();
});

test('FileDisk has setFilesystem static method', function () {
    expect(method_exists(FileDisk::class, 'setFilesystem'))->toBeTrue();
});

test('FileDisk has validateCredentials static method', function () {
    expect(method_exists(FileDisk::class, 'validateCredentials'))->toBeTrue();
});

test('FileDisk has createDisk static method', function () {
    expect(method_exists(FileDisk::class, 'createDisk'))->toBeTrue();
});

test('FileDisk has updateDefaultDisks static method', function () {
    expect(method_exists(FileDisk::class, 'updateDefaultDisks'))->toBeTrue();
});

test('FileDisk has updateDisk method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'updateDisk'))->toBeTrue();
});

test('FileDisk has setAsDefaultDisk method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'setAsDefaultDisk'))->toBeTrue();
});

test('FileDisk has isSystem method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'isSystem'))->toBeTrue();
});

test('FileDisk has isRemote method', function () {
    $fileDisk = new FileDisk();
    expect(method_exists($fileDisk, 'isRemote'))->toBeTrue();
});

// ========== MUTATOR TESTS ==========

test('setCredentialsAttribute encodes array to JSON', function () {
    $fileDisk = new FileDisk();
    $credentials = ['key' => 'value', 'secret' => '123'];
    
    $fileDisk->setCredentialsAttribute($credentials);
    
    $reflection = new ReflectionClass($fileDisk);
    $property = $reflection->getProperty('attributes');
    $property->setAccessible(true);
    $attributes = $property->getValue($fileDisk);
    
    expect($attributes['credentials'])->toBe(json_encode($credentials));
});

test('setCredentialsAttribute handles empty array', function () {
    $fileDisk = new FileDisk();
    $fileDisk->setCredentialsAttribute([]);
    
    $reflection = new ReflectionClass($fileDisk);
    $property = $reflection->getProperty('attributes');
    $property->setAccessible(true);
    $attributes = $property->getValue($fileDisk);
    
    expect($attributes['credentials'])->toBe('[]');
});

// ========== ACCESSOR TESTS ==========

test('setAsDefault returns set_as_default attribute', function () {
    $fileDisk = new FileDisk();
    $fileDisk->set_as_default = true;
    
    expect($fileDisk->setAsDefault())->toBeTrue();
});

test('setAsDefault returns false when not set', function () {
    $fileDisk = new FileDisk();
    $fileDisk->set_as_default = false;
    
    expect($fileDisk->setAsDefault())->toBeFalse();
});

// ========== isSystem TESTS ==========

test('isSystem returns true when type is SYSTEM', function () {
    $fileDisk = new FileDisk();
    $fileDisk->type = FileDisk::DISK_TYPE_SYSTEM;
    
    expect($fileDisk->isSystem())->toBeTrue();
});

test('isSystem returns false when type is not SYSTEM', function () {
    $fileDisk = new FileDisk();
    $fileDisk->type = FileDisk::DISK_TYPE_REMOTE;
    
    expect($fileDisk->isSystem())->toBeFalse();
});

// ========== isRemote TESTS ==========

test('isRemote returns true when type is REMOTE', function () {
    $fileDisk = new FileDisk();
    $fileDisk->type = FileDisk::DISK_TYPE_REMOTE;
    
    expect($fileDisk->isRemote())->toBeTrue();
});

test('isRemote returns false when type is not REMOTE', function () {
    $fileDisk = new FileDisk();
    $fileDisk->type = FileDisk::DISK_TYPE_SYSTEM;
    
    expect($fileDisk->isRemote())->toBeFalse();
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('all scope methods are public', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    
    expect($reflection->getMethod('scopeWhereOrder')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('scopeFileDisksBetween')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('scopeWhereSearch')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('scopePaginateData')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('scopeApplyFilters')->isPublic())->toBeTrue();
});

test('static methods are static', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    
    expect($reflection->getMethod('setFilesystem')->isStatic())->toBeTrue()
        ->and($reflection->getMethod('validateCredentials')->isStatic())->toBeTrue()
        ->and($reflection->getMethod('createDisk')->isStatic())->toBeTrue()
        ->and($reflection->getMethod('updateDefaultDisks')->isStatic())->toBeTrue();
});

test('instance methods are not static', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    
    expect($reflection->getMethod('setAsDefault')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('updateDisk')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('setAsDefaultDisk')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('isSystem')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('isRemote')->isStatic())->toBeFalse();
});

// ========== METHOD PARAMETERS TESTS ==========

test('scopeWhereOrder accepts three parameters', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $method = $reflection->getMethod('scopeWhereOrder');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(3)
        ->and($parameters[0]->getName())->toBe('query')
        ->and($parameters[1]->getName())->toBe('orderByField')
        ->and($parameters[2]->getName())->toBe('orderBy');
});

test('scopeFileDisksBetween accepts three parameters', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $method = $reflection->getMethod('scopeFileDisksBetween');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(3)
        ->and($parameters[0]->getName())->toBe('query')
        ->and($parameters[1]->getName())->toBe('start')
        ->and($parameters[2]->getName())->toBe('end');
});

test('scopeWhereSearch accepts two parameters', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $method = $reflection->getMethod('scopeWhereSearch');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('query')
        ->and($parameters[1]->getName())->toBe('search');
});

test('scopeApplyFilters accepts two parameters', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $method = $reflection->getMethod('scopeApplyFilters');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('query')
        ->and($parameters[1]->getName())->toBe('filters');
});

// ========== INSTANCE TESTS ==========

test('multiple FileDisk instances can be created', function () {
    $disk1 = new FileDisk();
    $disk2 = new FileDisk();
    
    expect($disk1)->toBeInstanceOf(FileDisk::class)
        ->and($disk2)->toBeInstanceOf(FileDisk::class)
        ->and($disk1)->not->toBe($disk2);
});

test('FileDisk can be cloned', function () {
    $disk = new FileDisk();
    $clone = clone $disk;
    
    expect($clone)->toBeInstanceOf(FileDisk::class)
        ->and($clone)->not->toBe($disk);
});

test('FileDisk can be used in type hints', function () {
    $testFunction = function (FileDisk $disk) {
        return $disk;
    };
    
    $disk = new FileDisk();
    $result = $testFunction($disk);
    
    expect($result)->toBe($disk);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('FileDisk is not final', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('FileDisk is not an interface', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('FileDisk is not a trait', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('FileDisk class is loaded', function () {
    expect(class_exists(FileDisk::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('FileDisk uses required classes', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Carbon')
        ->and($fileContent)->toContain('use Illuminate\Database\Eloquent\Factories\HasFactory')
        ->and($fileContent)->toContain('use Illuminate\Database\Eloquent\Model');
});

// ========== FILE STRUCTURE TESTS ==========

test('FileDisk file has expected structure', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class FileDisk extends Model')
        ->and($fileContent)->toContain('protected $guarded')
        ->and($fileContent)->toContain('protected $casts');
});

test('FileDisk has reasonable line count', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeGreaterThan(100)
        ->and($lineCount)->toBeLessThan(300);
});

// ========== IMPLEMENTATION TESTS ==========

test('scopeWhereSearch uses LIKE operator', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('LIKE')
        ->and($fileContent)->toContain('%\'.$term.\'%');
});

test('scopeWhereSearch searches name and driver', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->where(\'name\', \'LIKE\'')
        ->and($fileContent)->toContain('->orWhere(\'driver\', \'LIKE\'');
});

test('scopePaginateData handles all limit', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($limit == \'all\')')
        ->and($fileContent)->toContain('return $query->get()');
});

test('scopePaginateData uses paginate for numeric limit', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('return $query->paginate($limit)');
});

test('scopeApplyFilters uses collect helper', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('collect($filters)');
});

test('scopeApplyFilters checks search filter', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$filters->get(\'search\')')
        ->and($fileContent)->toContain('->whereSearch');
});

test('scopeApplyFilters checks date range filters', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$filters->get(\'from_date\')')
        ->and($fileContent)->toContain('$filters->get(\'to_date\')')
        ->and($fileContent)->toContain('->fileDisksBetween');
});

test('scopeApplyFilters checks order filters', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$filters->get(\'orderByField\')')
        ->and($fileContent)->toContain('$filters->get(\'orderBy\')')
        ->and($fileContent)->toContain('->whereOrder');
});


test('setFilesystem uses env helper', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('env(\'DYNAMIC_DISK_PREFIX\', \'temp_\')');
});

// ========== ATTRIBUTE TESTS ==========

test('can set and get name attribute', function () {
    $disk = new FileDisk();
    $disk->name = 'Test Disk';
    
    expect($disk->name)->toBe('Test Disk');
});

test('can set and get driver attribute', function () {
    $disk = new FileDisk();
    $disk->driver = 's3';
    
    expect($disk->driver)->toBe('s3');
});

test('can set and get set_as_default attribute', function () {
    $disk = new FileDisk();
    $disk->set_as_default = true;
    
    expect($disk->set_as_default)->toBeTrue();
});

test('can set and get type attribute', function () {
    $disk = new FileDisk();
    $disk->type = FileDisk::DISK_TYPE_SYSTEM;
    
    expect($disk->type)->toBe('SYSTEM');
});

// ========== DATA INTEGRITY TESTS ==========

test('different instances have independent data', function () {
    $disk1 = new FileDisk();
    $disk1->name = 'Disk 1';
    
    $disk2 = new FileDisk();
    $disk2->name = 'Disk 2';
    
    expect($disk1->name)->not->toBe($disk2->name)
        ->and($disk1->name)->toBe('Disk 1')
        ->and($disk2->name)->toBe('Disk 2');
});

// ========== PARENT CLASS TESTS ==========

test('FileDisk parent is Model', function () {
    $reflection = new ReflectionClass(FileDisk::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe('Illuminate\Database\Eloquent\Model');
});

// ========== MODEL FEATURES TESTS ==========

test('FileDisk inherits Model methods', function () {
    $disk = new FileDisk();
    
    expect(method_exists($disk, 'save'))->toBeTrue()
        ->and(method_exists($disk, 'fill'))->toBeTrue()
        ->and(method_exists($disk, 'toArray'))->toBeTrue();
});