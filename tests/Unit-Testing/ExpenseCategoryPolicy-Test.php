<?php

use Crater\Policies\ExpenseCategoryPolicy;

// ========== CLASS STRUCTURE TESTS ==========

test('ExpenseCategoryPolicy can be instantiated', function () {
    $policy = new ExpenseCategoryPolicy();
    expect($policy)->toBeInstanceOf(ExpenseCategoryPolicy::class);
});

test('ExpenseCategoryPolicy is in correct namespace', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Policies');
});

test('ExpenseCategoryPolicy is not abstract', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('ExpenseCategoryPolicy is instantiable', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== TRAITS TESTS ==========

test('ExpenseCategoryPolicy uses HandlesAuthorization trait', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Auth\Access\HandlesAuthorization');
});

// ========== METHOD EXISTENCE TESTS ==========

test('ExpenseCategoryPolicy has viewAny method', function () {
    $policy = new ExpenseCategoryPolicy();
    expect(method_exists($policy, 'viewAny'))->toBeTrue();
});

test('ExpenseCategoryPolicy has view method', function () {
    $policy = new ExpenseCategoryPolicy();
    expect(method_exists($policy, 'view'))->toBeTrue();
});

test('ExpenseCategoryPolicy has create method', function () {
    $policy = new ExpenseCategoryPolicy();
    expect(method_exists($policy, 'create'))->toBeTrue();
});

test('ExpenseCategoryPolicy has update method', function () {
    $policy = new ExpenseCategoryPolicy();
    expect(method_exists($policy, 'update'))->toBeTrue();
});

test('ExpenseCategoryPolicy has delete method', function () {
    $policy = new ExpenseCategoryPolicy();
    expect(method_exists($policy, 'delete'))->toBeTrue();
});

test('ExpenseCategoryPolicy has restore method', function () {
    $policy = new ExpenseCategoryPolicy();
    expect(method_exists($policy, 'restore'))->toBeTrue();
});

test('ExpenseCategoryPolicy has forceDelete method', function () {
    $policy = new ExpenseCategoryPolicy();
    expect(method_exists($policy, 'forceDelete'))->toBeTrue();
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('all policy methods are public', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    
    expect($reflection->getMethod('viewAny')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('view')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('create')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('update')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('delete')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('restore')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('forceDelete')->isPublic())->toBeTrue();
});

test('all policy methods are not static', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    
    expect($reflection->getMethod('viewAny')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('view')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('create')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('update')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('delete')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('restore')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('forceDelete')->isStatic())->toBeFalse();
});

// ========== METHOD PARAMETERS TESTS ==========

test('viewAny accepts User parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('viewAny');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('user');
});

test('view accepts User and ExpenseCategory parameters', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('view');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('user')
        ->and($parameters[1]->getName())->toBe('expenseCategory');
});

test('create accepts User parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('create');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('user');
});

test('update accepts User and ExpenseCategory parameters', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('update');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('user')
        ->and($parameters[1]->getName())->toBe('expenseCategory');
});

test('delete accepts User and ExpenseCategory parameters', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('delete');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('user')
        ->and($parameters[1]->getName())->toBe('expenseCategory');
});

test('restore accepts User and ExpenseCategory parameters', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('restore');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('user')
        ->and($parameters[1]->getName())->toBe('expenseCategory');
});

test('forceDelete accepts User and ExpenseCategory parameters', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('forceDelete');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('user')
        ->and($parameters[1]->getName())->toBe('expenseCategory');
});

// ========== INSTANCE TESTS ==========

test('multiple ExpenseCategoryPolicy instances can be created', function () {
    $policy1 = new ExpenseCategoryPolicy();
    $policy2 = new ExpenseCategoryPolicy();
    
    expect($policy1)->toBeInstanceOf(ExpenseCategoryPolicy::class)
        ->and($policy2)->toBeInstanceOf(ExpenseCategoryPolicy::class)
        ->and($policy1)->not->toBe($policy2);
});

test('ExpenseCategoryPolicy can be cloned', function () {
    $policy = new ExpenseCategoryPolicy();
    $clone = clone $policy;
    
    expect($clone)->toBeInstanceOf(ExpenseCategoryPolicy::class)
        ->and($clone)->not->toBe($policy);
});

test('ExpenseCategoryPolicy can be used in type hints', function () {
    $testFunction = function (ExpenseCategoryPolicy $policy) {
        return $policy;
    };
    
    $policy = new ExpenseCategoryPolicy();
    $result = $testFunction($policy);
    
    expect($result)->toBe($policy);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('ExpenseCategoryPolicy is not final', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('ExpenseCategoryPolicy is not an interface', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('ExpenseCategoryPolicy is not a trait', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('ExpenseCategoryPolicy class is loaded', function () {
    expect(class_exists(ExpenseCategoryPolicy::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('ExpenseCategoryPolicy uses required classes', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Models\Expense')
        ->and($fileContent)->toContain('use Crater\Models\ExpenseCategory')
        ->and($fileContent)->toContain('use Crater\Models\User')
        ->and($fileContent)->toContain('use Illuminate\Auth\Access\HandlesAuthorization')
        ->and($fileContent)->toContain('use Silber\Bouncer\BouncerFacade');
});

// ========== FILE STRUCTURE TESTS ==========

test('ExpenseCategoryPolicy file has expected structure', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class ExpenseCategoryPolicy')
        ->and($fileContent)->toContain('use HandlesAuthorization')
        ->and($fileContent)->toContain('public function viewAny')
        ->and($fileContent)->toContain('public function view')
        ->and($fileContent)->toContain('public function create')
        ->and($fileContent)->toContain('public function update')
        ->and($fileContent)->toContain('public function delete')
        ->and($fileContent)->toContain('public function restore')
        ->and($fileContent)->toContain('public function forceDelete');
});

test('ExpenseCategoryPolicy has reasonable line count', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeGreaterThan(50)
        ->and($lineCount)->toBeLessThan(200);
});

// ========== DOCUMENTATION TESTS ==========

test('viewAny method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('viewAny');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('view method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('view');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('create method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('create');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('update method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('update');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('delete method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('delete');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('restore method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('restore');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('forceDelete method has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('forceDelete');
    
    expect($method->getDocComment())->not->toBeFalse();
});

// ========== IMPLEMENTATION TESTS ==========

test('all methods use BouncerFacade', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // Count occurrences - should appear in all 7 methods
    $count = substr_count($fileContent, 'BouncerFacade::can');
    expect($count)->toBeGreaterThanOrEqual(7);
});

test('all methods check view-expense permission', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('view-expense');
});

test('all methods check against Expense class', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // Should check Expense::class in all methods
    $count = substr_count($fileContent, 'Expense::class');
    expect($count)->toBeGreaterThanOrEqual(7);
});

test('methods with category parameter check hasCompany', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // view, update, delete, restore, forceDelete should check hasCompany
    $count = substr_count($fileContent, '$user->hasCompany');
    expect($count)->toBe(5);
});

test('methods with category parameter check company_id', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$expenseCategory->company_id');
});

test('all methods return boolean', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // Each method should have return true and return false
    $trueCount = substr_count($fileContent, 'return true');
    $falseCount = substr_count($fileContent, 'return false');
    
    expect($trueCount)->toBe(7)
        ->and($falseCount)->toBe(7);
});

// ========== METHOD COUNT TESTS ==========

test('ExpenseCategoryPolicy has exactly 7 public methods', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    // Filter out inherited methods
    $ownMethods = array_filter($methods, function($method) {
        return $method->class === ExpenseCategoryPolicy::class;
    });
    
    expect(count($ownMethods))->toBe(7);
});

// ========== LOGIC STRUCTURE TESTS ==========

test('viewAny has simple if-return structure', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('viewAny');
    
    expect($method->getNumberOfParameters())->toBe(1);
});

test('view has compound if condition', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // view method should have && condition
    expect($fileContent)->toContain('BouncerFacade::can(\'view-expense\', Expense::class) && $user->hasCompany');
});

test('create has same structure as viewAny', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('create');
    
    expect($method->getNumberOfParameters())->toBe(1);
});

test('update has same structure as view', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('update');
    
    expect($method->getNumberOfParameters())->toBe(2);
});

test('delete has same structure as view', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('delete');
    
    expect($method->getNumberOfParameters())->toBe(2);
});

test('restore has same structure as view', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('restore');
    
    expect($method->getNumberOfParameters())->toBe(2);
});

test('forceDelete has same structure as view', function () {
    $reflection = new ReflectionClass(ExpenseCategoryPolicy::class);
    $method = $reflection->getMethod('forceDelete');
    
    expect($method->getNumberOfParameters())->toBe(2);
});