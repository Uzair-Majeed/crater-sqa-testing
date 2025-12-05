<?php

use Crater\Models\ExpenseCategory;
use Crater\Models\Expense;
use Crater\Models\Company;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ========== CLASS STRUCTURE TESTS ==========

test('ExpenseCategory can be instantiated', function () {
    $category = new ExpenseCategory();
    expect($category)->toBeInstanceOf(ExpenseCategory::class);
});

test('ExpenseCategory extends Model', function () {
    $category = new ExpenseCategory();
    expect($category)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
});

test('ExpenseCategory is in correct namespace', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Models');
});

test('ExpenseCategory is not abstract', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('ExpenseCategory is instantiable', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== FILLABLE PROPERTIES TESTS ==========

test('ExpenseCategory has fillable properties', function () {
    $category = new ExpenseCategory();
    $fillable = $category->getFillable();
    
    expect($fillable)->toContain('name')
        ->and($fillable)->toContain('company_id')
        ->and($fillable)->toContain('description');
});

test('ExpenseCategory allows mass assignment of name', function () {
    $category = new ExpenseCategory();
    $category->fill(['name' => 'Test Category']);
    
    expect($category->name)->toBe('Test Category');
});

test('ExpenseCategory allows mass assignment of company_id', function () {
    $category = new ExpenseCategory();
    $category->fill(['company_id' => 1]);
    
    expect($category->company_id)->toBe(1);
});

test('ExpenseCategory allows mass assignment of description', function () {
    $category = new ExpenseCategory();
    $category->fill(['description' => 'Test Description']);
    
    expect($category->description)->toBe('Test Description');
});

// ========== APPENDS TESTS ==========

test('ExpenseCategory has appends property', function () {
    $category = new ExpenseCategory();
    $reflection = new ReflectionClass($category);
    $property = $reflection->getProperty('appends');
    $property->setAccessible(true);
    $appends = $property->getValue($category);
    
    expect($appends)->toContain('amount')
        ->and($appends)->toContain('formattedCreatedAt');
});

// ========== RELATIONSHIP TESTS ==========

test('expenses relationship exists', function () {
    $category = new ExpenseCategory();
    expect(method_exists($category, 'expenses'))->toBeTrue();
});

test('expenses relationship returns HasMany', function () {
    $category = new ExpenseCategory();
    $relation = $category->expenses();
    
    expect($relation)->toBeInstanceOf(HasMany::class);
});

test('expenses relationship is to Expense model', function () {
    $category = new ExpenseCategory();
    $relation = $category->expenses();
    
    expect($relation->getRelated())->toBeInstanceOf(Expense::class);
});

test('expenses relationship uses expense_category_id foreign key', function () {
    $category = new ExpenseCategory();
    $relation = $category->expenses();
    
    expect($relation->getForeignKeyName())->toBe('expense_category_id');
});

test('expenses relationship uses id local key', function () {
    $category = new ExpenseCategory();
    $relation = $category->expenses();
    
    expect($relation->getLocalKeyName())->toBe('id');
});

test('company relationship exists', function () {
    $category = new ExpenseCategory();
    expect(method_exists($category, 'company'))->toBeTrue();
});

test('company relationship returns BelongsTo', function () {
    $category = new ExpenseCategory();
    $relation = $category->company();
    
    expect($relation)->toBeInstanceOf(BelongsTo::class);
});

test('company relationship is to Company model', function () {
    $category = new ExpenseCategory();
    $relation = $category->company();
    
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
});

test('company relationship uses company_id foreign key', function () {
    $category = new ExpenseCategory();
    $relation = $category->company();
    
    expect($relation->getForeignKeyName())->toBe('company_id');
});

test('company relationship uses id owner key', function () {
    $category = new ExpenseCategory();
    $relation = $category->company();
    
    expect($relation->getOwnerKeyName())->toBe('id');
});

// ========== METHOD EXISTENCE TESTS ==========

test('ExpenseCategory has getFormattedCreatedAtAttribute method', function () {
    $category = new ExpenseCategory();
    expect(method_exists($category, 'getFormattedCreatedAtAttribute'))->toBeTrue();
});

test('ExpenseCategory has getAmountAttribute method', function () {
    $category = new ExpenseCategory();
    expect(method_exists($category, 'getAmountAttribute'))->toBeTrue();
});

test('ExpenseCategory has scopeWhereCompany method', function () {
    $category = new ExpenseCategory();
    expect(method_exists($category, 'scopeWhereCompany'))->toBeTrue();
});

test('ExpenseCategory has scopeWhereCategory method', function () {
    $category = new ExpenseCategory();
    expect(method_exists($category, 'scopeWhereCategory'))->toBeTrue();
});

test('ExpenseCategory has scopeWhereSearch method', function () {
    $category = new ExpenseCategory();
    expect(method_exists($category, 'scopeWhereSearch'))->toBeTrue();
});

test('ExpenseCategory has scopeApplyFilters method', function () {
    $category = new ExpenseCategory();
    expect(method_exists($category, 'scopeApplyFilters'))->toBeTrue();
});

test('ExpenseCategory has scopePaginateData method', function () {
    $category = new ExpenseCategory();
    expect(method_exists($category, 'scopePaginateData'))->toBeTrue();
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('all scope methods are public', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    
    expect($reflection->getMethod('scopeWhereCompany')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('scopeWhereCategory')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('scopeWhereSearch')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('scopeApplyFilters')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('scopePaginateData')->isPublic())->toBeTrue();
});

test('all accessor methods are public', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    
    expect($reflection->getMethod('getFormattedCreatedAtAttribute')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('getAmountAttribute')->isPublic())->toBeTrue();
});

test('scopeWhereCategory accepts category_id parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $method = $reflection->getMethod('scopeWhereCategory');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('query')
        ->and($parameters[1]->getName())->toBe('category_id');
});

test('scopeWhereSearch accepts search parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $method = $reflection->getMethod('scopeWhereSearch');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('query')
        ->and($parameters[1]->getName())->toBe('search');
});

test('scopeApplyFilters accepts filters array parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $method = $reflection->getMethod('scopeApplyFilters');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('query')
        ->and($parameters[1]->getName())->toBe('filters');
});

test('scopePaginateData accepts limit parameter', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $method = $reflection->getMethod('scopePaginateData');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('query')
        ->and($parameters[1]->getName())->toBe('limit');
});

// ========== TRAITS TESTS ==========

test('ExpenseCategory uses HasFactory trait', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
});

// ========== INSTANCE TESTS ==========

test('multiple ExpenseCategory instances can be created', function () {
    $category1 = new ExpenseCategory();
    $category2 = new ExpenseCategory();
    
    expect($category1)->toBeInstanceOf(ExpenseCategory::class)
        ->and($category2)->toBeInstanceOf(ExpenseCategory::class)
        ->and($category1)->not->toBe($category2);
});

test('ExpenseCategory can be cloned', function () {
    $category = new ExpenseCategory(['name' => 'Test']);
    $clone = clone $category;
    
    expect($clone)->toBeInstanceOf(ExpenseCategory::class)
        ->and($clone)->not->toBe($category);
});

test('ExpenseCategory can be used in type hints', function () {
    $testFunction = function (ExpenseCategory $category) {
        return $category;
    };
    
    $category = new ExpenseCategory();
    $result = $testFunction($category);
    
    expect($result)->toBe($category);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('ExpenseCategory is not final', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('ExpenseCategory is not an interface', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('ExpenseCategory is not a trait', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('ExpenseCategory class is loaded', function () {
    expect(class_exists(ExpenseCategory::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('ExpenseCategory uses required classes', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Carbon\Carbon')
        ->and($fileContent)->toContain('use Illuminate\Database\Eloquent\Factories\HasFactory')
        ->and($fileContent)->toContain('use Illuminate\Database\Eloquent\Model');
});

// ========== FILE STRUCTURE TESTS ==========

test('ExpenseCategory file has expected structure', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class ExpenseCategory extends Model')
        ->and($fileContent)->toContain('protected $fillable')
        ->and($fileContent)->toContain('protected $appends');
});

test('ExpenseCategory has reasonable line count', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeGreaterThan(50)
        ->and($lineCount)->toBeLessThan(150);
});

// ========== IMPLEMENTATION TESTS ==========

test('scopeWhereCompany uses request header', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('request()->header(\'company\')');
});

test('scopeWhereCategory uses orWhere', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$query->orWhere(\'id\', $category_id)');
});

test('scopeWhereSearch uses LIKE operator', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('LIKE')
        ->and($fileContent)->toContain('%\'.$search.\'%');
});

test('scopeApplyFilters uses collect helper', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('collect($filters)');
});

test('scopeApplyFilters checks category_id filter', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$filters->get(\'category_id\')')
        ->and($fileContent)->toContain('->whereCategory');
});

test('scopeApplyFilters checks company_id filter', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$filters->get(\'company_id\')')
        ->and($fileContent)->toContain('->whereCompany');
});

test('scopeApplyFilters checks search filter', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$filters->get(\'search\')')
        ->and($fileContent)->toContain('->whereSearch');
});

test('scopePaginateData handles all limit', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($limit == \'all\')')
        ->and($fileContent)->toContain('return $query->get()');
});

test('scopePaginateData uses paginate for numeric limit', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('return $query->paginate($limit)');
});

test('getAmountAttribute uses expenses sum', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->expenses()->sum(\'amount\')');
});

test('getFormattedCreatedAtAttribute uses CompanySetting', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('CompanySetting::getSetting');
});

test('getFormattedCreatedAtAttribute uses Carbon parse', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Carbon::parse($this->created_at)');
});

// ========== ATTRIBUTE TESTS ==========

test('can set and get name attribute', function () {
    $category = new ExpenseCategory();
    $category->name = 'Test Category';
    
    expect($category->name)->toBe('Test Category');
});

test('can set and get company_id attribute', function () {
    $category = new ExpenseCategory();
    $category->company_id = 5;
    
    expect($category->company_id)->toBe(5);
});

test('can set and get description attribute', function () {
    $category = new ExpenseCategory();
    $category->description = 'Test Description';
    
    expect($category->description)->toBe('Test Description');
});

// ========== DATA INTEGRITY TESTS ==========

test('different instances have independent data', function () {
    $category1 = new ExpenseCategory(['name' => 'Category 1']);
    $category2 = new ExpenseCategory(['name' => 'Category 2']);
    
    expect($category1->name)->not->toBe($category2->name)
        ->and($category1->name)->toBe('Category 1')
        ->and($category2->name)->toBe('Category 2');
});

// ========== PARENT CLASS TESTS ==========

test('ExpenseCategory parent is Model', function () {
    $reflection = new ReflectionClass(ExpenseCategory::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe('Illuminate\Database\Eloquent\Model');
});

// ========== MODEL FEATURES TESTS ==========

test('ExpenseCategory inherits Model methods', function () {
    $category = new ExpenseCategory();
    
    expect(method_exists($category, 'save'))->toBeTrue()
        ->and(method_exists($category, 'fill'))->toBeTrue()
        ->and(method_exists($category, 'toArray'))->toBeTrue();
});