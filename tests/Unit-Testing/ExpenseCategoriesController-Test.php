<?php

use Crater\Http\Controllers\V1\Admin\Expense\ExpenseCategoriesController;

// ========== CLASS STRUCTURE TESTS ==========

test('ExpenseCategoriesController can be instantiated', function () {
    $controller = new ExpenseCategoriesController();
    expect($controller)->toBeInstanceOf(ExpenseCategoriesController::class);
});

test('ExpenseCategoriesController extends Controller', function () {
    $controller = new ExpenseCategoriesController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('ExpenseCategoriesController is in correct namespace', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\Expense');
});

test('ExpenseCategoriesController is not abstract', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('ExpenseCategoriesController is instantiable', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== METHOD EXISTENCE TESTS ==========

test('ExpenseCategoriesController has index method', function () {
    $controller = new ExpenseCategoriesController();
    expect(method_exists($controller, 'index'))->toBeTrue();
});

test('ExpenseCategoriesController has store method', function () {
    $controller = new ExpenseCategoriesController();
    expect(method_exists($controller, 'store'))->toBeTrue();
});

test('ExpenseCategoriesController has show method', function () {
    $controller = new ExpenseCategoriesController();
    expect(method_exists($controller, 'show'))->toBeTrue();
});

test('ExpenseCategoriesController has update method', function () {
    $controller = new ExpenseCategoriesController();
    expect(method_exists($controller, 'update'))->toBeTrue();
});

test('ExpenseCategoriesController has destroy method', function () {
    $controller = new ExpenseCategoriesController();
    expect(method_exists($controller, 'destroy'))->toBeTrue();
});

// ========== INDEX METHOD TESTS ==========

test('index method is public', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('index');
    
    expect($method->isPublic())->toBeTrue();
});

test('index method accepts Request parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('index');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

test('index method is not static', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('index');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== STORE METHOD TESTS ==========

test('store method is public', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('store');
    
    expect($method->isPublic())->toBeTrue();
});

test('store method accepts ExpenseCategoryRequest parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('store');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

test('store method is not static', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('store');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== SHOW METHOD TESTS ==========

test('show method is public', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('show');
    
    expect($method->isPublic())->toBeTrue();
});

test('show method accepts ExpenseCategory parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('show');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('category');
});

test('show method is not static', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('show');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== UPDATE METHOD TESTS ==========

test('update method is public', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('update');
    
    expect($method->isPublic())->toBeTrue();
});

test('update method accepts two parameters', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('update');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[1]->getName())->toBe('category');
});

test('update method is not static', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('update');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== DESTROY METHOD TESTS ==========

test('destroy method is public', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('destroy');
    
    expect($method->isPublic())->toBeTrue();
});

test('destroy method accepts ExpenseCategory parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('destroy');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('category');
});

test('destroy method is not static', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('destroy');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== INSTANCE TESTS ==========

test('multiple ExpenseCategoriesController instances can be created', function () {
    $controller1 = new ExpenseCategoriesController();
    $controller2 = new ExpenseCategoriesController();
    
    expect($controller1)->toBeInstanceOf(ExpenseCategoriesController::class)
        ->and($controller2)->toBeInstanceOf(ExpenseCategoriesController::class)
        ->and($controller1)->not->toBe($controller2);
});

test('ExpenseCategoriesController can be cloned', function () {
    $controller = new ExpenseCategoriesController();
    $clone = clone $controller;
    
    expect($clone)->toBeInstanceOf(ExpenseCategoriesController::class)
        ->and($clone)->not->toBe($controller);
});

test('ExpenseCategoriesController can be used in type hints', function () {
    $testFunction = function (ExpenseCategoriesController $controller) {
        return $controller;
    };
    
    $controller = new ExpenseCategoriesController();
    $result = $testFunction($controller);
    
    expect($result)->toBe($controller);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('ExpenseCategoriesController is not final', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('ExpenseCategoriesController is not an interface', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('ExpenseCategoriesController is not a trait', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('ExpenseCategoriesController class is loaded', function () {
    expect(class_exists(ExpenseCategoriesController::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('ExpenseCategoriesController uses required classes', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Http\Controllers\Controller')
        ->and($fileContent)->toContain('use Crater\Http\Requests\ExpenseCategoryRequest')
        ->and($fileContent)->toContain('use Crater\Http\Resources\ExpenseCategoryResource')
        ->and($fileContent)->toContain('use Crater\Models\ExpenseCategory')
        ->and($fileContent)->toContain('use Illuminate\Http\Request');
});

// ========== FILE STRUCTURE TESTS ==========

test('ExpenseCategoriesController file has expected structure', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class ExpenseCategoriesController extends Controller')
        ->and($fileContent)->toContain('public function index')
        ->and($fileContent)->toContain('public function store')
        ->and($fileContent)->toContain('public function show')
        ->and($fileContent)->toContain('public function update')
        ->and($fileContent)->toContain('public function destroy');
});

test('ExpenseCategoriesController has reasonable line count', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeGreaterThan(50)
        ->and($lineCount)->toBeLessThan(150);
});

// ========== DOCUMENTATION TESTS ==========

test('index method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('index');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('store method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('store');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('show method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('show');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('update method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('update');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('destroy method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $method = $reflection->getMethod('destroy');
    
    expect($method->getDocComment())->not->toBeFalse();
});

// ========== IMPLEMENTATION TESTS ==========

test('index method uses authorize', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'viewAny\', ExpenseCategory::class)');
});

test('index method uses applyFilters', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('ExpenseCategory::applyFilters');
});

test('index method uses whereCompany scope', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->whereCompany()');
});

test('index method uses latest', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->latest()');
});

test('index method uses paginateData', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->paginateData($limit)');
});

test('index method returns resource collection', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('ExpenseCategoryResource::collection');
});

test('store method uses getExpenseCategoryPayload', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$request->getExpenseCategoryPayload()');
});

test('store method creates ExpenseCategory', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('ExpenseCategory::create');
});

test('show method returns ExpenseCategoryResource', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('new ExpenseCategoryResource($category)');
});

test('update method calls update on category', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$category->update');
});

test('destroy method checks expenses count', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$category->expenses()')
        ->and($fileContent)->toContain('->count()');
});

test('destroy method calls delete', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$category->delete()');
});

test('destroy method returns success response', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'success\' => true');
});

// ========== AUTHORIZATION TESTS ==========

test('all methods use authorization', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'viewAny\'')
        ->and($fileContent)->toContain('$this->authorize(\'create\'')
        ->and($fileContent)->toContain('$this->authorize(\'view\'')
        ->and($fileContent)->toContain('$this->authorize(\'update\'')
        ->and($fileContent)->toContain('$this->authorize(\'delete\'');
});

// ========== ERROR HANDLING TESTS ==========

test('destroy method handles expense_attached error', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('expense_attached')
        ->and($fileContent)->toContain('Expense Attached');
});

test('destroy method uses respondJson helper', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('respondJson');
});

// ========== PARENT CLASS TESTS ==========

test('ExpenseCategoriesController parent is Controller', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe('Crater\Http\Controllers\Controller');
});

// ========== METHOD COUNT TESTS ==========

test('ExpenseCategoriesController has exactly 5 public methods', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    // Filter out inherited methods
    $ownMethods = array_filter($methods, function($method) {
        return $method->class === ExpenseCategoriesController::class;
    });
    
    expect(count($ownMethods))->toBe(5);
});

// ========== LIMIT HANDLING TESTS ==========

test('index method handles limit parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$request->has(\'limit\')')
        ->and($fileContent)->toContain('$request->limit');
});

test('index method has default limit of 5', function () {
    $reflection = new ReflectionClass(ExpenseCategoriesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain(': 5');
});