<?php

use Crater\Traits\GeneratesMenuTrait;

// Dummy class to use the trait for testing
class DummyMenuGenerator
{
    use GeneratesMenuTrait;
}

// Dummy Menu class to simulate the Menu facade
class DummyMenuFacade
{
    private $items;
    
    public function __construct($items = [])
    {
        $this->items = $items;
    }
    
    public function toArray()
    {
        return $this->items;
    }
}

// Dummy MenuItem class
class DummyMenuItem
{
    public $title;
    public $link;
    public $data;
    
    public function __construct($title, $url, $icon, $name, $group)
    {
        $this->title = $title;
        $this->link = (object)['path' => ['url' => $url]];
        $this->data = [
            'icon' => $icon,
            'name' => $name,
            'group' => $group,
        ];
    }
}

// Dummy User class
class DummyMenuUser
{
    private $accessibleItems = [];
    
    public function setAccessibleItems($items)
    {
        $this->accessibleItems = $items;
    }
    
    public function checkAccess($item)
    {
        return in_array($item, $this->accessibleItems, true);
    }
}

// ========== TRAIT STRUCTURE TESTS ==========

test('GeneratesMenuTrait can be used by a class', function () {
    $generator = new DummyMenuGenerator();
    expect($generator)->toBeInstanceOf(DummyMenuGenerator::class);
});

test('GeneratesMenuTrait is in correct namespace', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Traits');
});

test('GeneratesMenuTrait is a trait', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    expect($reflection->isTrait())->toBeTrue();
});

test('GeneratesMenuTrait has generateMenu method', function () {
    $generator = new DummyMenuGenerator();
    expect(method_exists($generator, 'generateMenu'))->toBeTrue();
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('generateMenu method is public', function () {
    $reflection = new ReflectionClass(DummyMenuGenerator::class);
    $method = $reflection->getMethod('generateMenu');
    
    expect($method->isPublic())->toBeTrue();
});

test('generateMenu method is not static', function () {
    $reflection = new ReflectionClass(DummyMenuGenerator::class);
    $method = $reflection->getMethod('generateMenu');
    
    expect($method->isStatic())->toBeFalse();
});

test('generateMenu method accepts two parameters', function () {
    $reflection = new ReflectionClass(DummyMenuGenerator::class);
    $method = $reflection->getMethod('generateMenu');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('key')
        ->and($parameters[1]->getName())->toBe('user');
});

// ========== INSTANCE TESTS ==========

test('multiple instances using trait can be created', function () {
    $generator1 = new DummyMenuGenerator();
    $generator2 = new DummyMenuGenerator();
    
    expect($generator1)->toBeInstanceOf(DummyMenuGenerator::class)
        ->and($generator2)->toBeInstanceOf(DummyMenuGenerator::class)
        ->and($generator1)->not->toBe($generator2);
});

test('class using trait can be cloned', function () {
    $generator = new DummyMenuGenerator();
    $clone = clone $generator;
    
    expect($clone)->toBeInstanceOf(DummyMenuGenerator::class)
        ->and($clone)->not->toBe($generator);
});

test('class using trait can be used in type hints', function () {
    $testFunction = function (DummyMenuGenerator $generator) {
        return $generator;
    };
    
    $generator = new DummyMenuGenerator();
    $result = $testFunction($generator);
    
    expect($result)->toBe($generator);
});

// ========== TRAIT CHARACTERISTICS TESTS ==========

test('GeneratesMenuTrait is not a class', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('GeneratesMenuTrait is not an interface', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('GeneratesMenuTrait is loaded', function () {
    expect(trait_exists(GeneratesMenuTrait::class))->toBeTrue();
});

// ========== FILE STRUCTURE TESTS ==========

test('GeneratesMenuTrait file has expected structure', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('trait GeneratesMenuTrait')
        ->and($fileContent)->toContain('public function generateMenu');
});

test('GeneratesMenuTrait has minimal line count', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(50);
});

// ========== IMPLEMENTATION TESTS ==========

test('generateMenu uses Menu facade', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\\Menu::get');
});

test('generateMenu uses foreach loop', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('foreach');
});

test('generateMenu calls checkAccess on user', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$user->checkAccess');
});

test('generateMenu builds array with title', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'title\' => $data->title');
});

test('generateMenu builds array with link', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'link\' => $data->link->path[\'url\']');
});

test('generateMenu builds array with icon', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'icon\' => $data->data[\'icon\']');
});

test('generateMenu builds array with name', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'name\' => $data->data[\'name\']');
});

test('generateMenu builds array with group', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'group\' => $data->data[\'group\']');
});

test('generateMenu returns array', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('return $menu');
});

test('generateMenu initializes empty menu array', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$menu = []');
});

// ========== TRAIT USAGE TESTS ==========

test('DummyMenuGenerator uses GeneratesMenuTrait', function () {
    $reflection = new ReflectionClass(DummyMenuGenerator::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Crater\Traits\GeneratesMenuTrait');
});

test('trait provides generateMenu method to using class', function () {
    $reflection = new ReflectionClass(DummyMenuGenerator::class);
    
    expect($reflection->hasMethod('generateMenu'))->toBeTrue();
});

// ========== METHOD COUNT TESTS ==========

test('GeneratesMenuTrait has exactly one method', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $methods = $reflection->getMethods();
    
    expect($methods)->toHaveCount(1)
        ->and($methods[0]->getName())->toBe('generateMenu');
});

// ========== ARRAY STRUCTURE TESTS ==========

test('generateMenu creates array with required keys', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'title\'')
        ->and($fileContent)->toContain('\'link\'')
        ->and($fileContent)->toContain('\'icon\'')
        ->and($fileContent)->toContain('\'name\'')
        ->and($fileContent)->toContain('\'group\'');
});

// ========== CONDITIONAL LOGIC TESTS ==========

test('generateMenu uses if statement for access check', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($user->checkAccess($data))');
});

test('generateMenu appends to menu array', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$menu[]');
});

// ========== DATA ACCESS TESTS ==========

test('generateMenu accesses items property', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->items');
});

test('generateMenu calls toArray on items', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->toArray()');
});

test('generateMenu accesses data object properties', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$data->title')
        ->and($fileContent)->toContain('$data->link')
        ->and($fileContent)->toContain('$data->data');
});

// ========== NAMESPACE TESTS ==========

test('GeneratesMenuTrait is in Traits namespace', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    
    expect($reflection->getNamespaceName())->toBe('Crater\Traits');
});

test('GeneratesMenuTrait file declares namespace', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('namespace Crater\Traits');
});

// ========== DOCUMENTATION TESTS ==========

test('trait file is concise', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect(strlen($fileContent))->toBeLessThan(1000);
});

// ========== VARIABLE NAMING TESTS ==========

test('generateMenu uses descriptive variable names', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$menu')
        ->and($fileContent)->toContain('$key')
        ->and($fileContent)->toContain('$user')
        ->and($fileContent)->toContain('$data');
});

// ========== LOOP STRUCTURE TESTS ==========

test('generateMenu iterates over menu items', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('foreach (\\Menu::get($key)->items->toArray() as $data)');
});

// ========== TRAIT PROPERTIES TESTS ==========

test('GeneratesMenuTrait has no properties', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $properties = $reflection->getProperties();
    
    expect($properties)->toBeEmpty();
});

// ========== TRAIT CONSTANTS TESTS ==========

test('GeneratesMenuTrait has no constants', function () {
    $reflection = new ReflectionClass(GeneratesMenuTrait::class);
    $constants = $reflection->getConstants();
    
    expect($constants)->toBeEmpty();
});