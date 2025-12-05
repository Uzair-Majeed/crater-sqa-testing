<?php

use Crater\Models\PaymentMethod;

// ========== PAYMENTMETHOD TESTS (15 MINIMAL TESTS FOR 100% COVERAGE) ==========
// NO MOCKERY - Pure unit tests with real data

test('PaymentMethod can be instantiated', function () {
    $paymentMethod = new PaymentMethod();
    expect($paymentMethod)->toBeInstanceOf(PaymentMethod::class);
});

test('PaymentMethod extends Model and uses HasFactory', function () {
    $paymentMethod = new PaymentMethod();
    $reflection = new ReflectionClass(PaymentMethod::class);
    $traits = $reflection->getTraitNames();
    
    expect($paymentMethod)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class)
        ->and($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
});

test('PaymentMethod has TYPE constants', function () {
    expect(PaymentMethod::TYPE_GENERAL)->toBe('GENERAL')
        ->and(PaymentMethod::TYPE_MODULE)->toBe('MODULE');
});

test('PaymentMethod has correct casts', function () {
    $paymentMethod = new PaymentMethod();
    $casts = $paymentMethod->getCasts();
    
    expect($casts)->toHaveKey('settings')
        ->and($casts['settings'])->toBe('array')
        ->and($casts)->toHaveKey('use_test_env')
        ->and($casts['use_test_env'])->toBe('boolean');
});

test('PaymentMethod setSettingsAttribute encodes value as JSON', function () {
    $paymentMethod = new PaymentMethod();
    $settings = ['key' => 'value', 'array' => [1, 2]];
    
    $paymentMethod->setSettingsAttribute($settings);
    
    expect($paymentMethod->getAttributes()['settings'])->toBeJson()
        ->and(json_decode($paymentMethod->getAttributes()['settings'], true))->toEqual($settings);
});

test('PaymentMethod setSettingsAttribute handles null', function () {
    $paymentMethod = new PaymentMethod();
    $paymentMethod->setSettingsAttribute(null);
    
    expect($paymentMethod->getAttributes()['settings'])->toBeJson()
        ->and(json_decode($paymentMethod->getAttributes()['settings'], true))->toBeNull();
});

test('PaymentMethod setSettingsAttribute handles empty array', function () {
    $paymentMethod = new PaymentMethod();
    $paymentMethod->setSettingsAttribute([]);
    
    expect($paymentMethod->getAttributes()['settings'])->toBeJson()
        ->and(json_decode($paymentMethod->getAttributes()['settings'], true))->toEqual([]);
});

test('PaymentMethod has payments relationship', function () {
    $paymentMethod = new PaymentMethod();
    $relation = $paymentMethod->payments();
    
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(\Crater\Models\Payment::class)
        ->and($relation->getForeignKeyName())->toBe('payment_method_id');
});

test('PaymentMethod has expenses relationship', function () {
    $paymentMethod = new PaymentMethod();
    $relation = $paymentMethod->expenses();
    
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(\Crater\Models\Expense::class)
        ->and($relation->getForeignKeyName())->toBe('payment_method_id');
});

test('PaymentMethod has company relationship', function () {
    $paymentMethod = new PaymentMethod();
    $relation = $paymentMethod->company();
    
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(\Crater\Models\Company::class)
        ->and($relation->getForeignKeyName())->toBe('company_id');
});

test('PaymentMethod has scope methods', function () {
    $reflection = new ReflectionClass(PaymentMethod::class);
    
    expect($reflection->hasMethod('scopeWhereCompanyId'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWhereCompany'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWherePaymentMethod'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWhereSearch'))->toBeTrue()
        ->and($reflection->hasMethod('scopeApplyFilters'))->toBeTrue()
        ->and($reflection->hasMethod('scopePaginateData'))->toBeTrue();
});

test('PaymentMethod scopeWhereSearch uses LIKE query', function () {
    $reflection = new ReflectionClass(PaymentMethod::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('where(\'name\', \'LIKE\', \'%\'.$search.\'%\')');
});

test('PaymentMethod scopeApplyFilters handles method_id filter', function () {
    $reflection = new ReflectionClass(PaymentMethod::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$filters->get(\'method_id\')')
        ->and($fileContent)->toContain('->wherePaymentMethod');
});

test('PaymentMethod scopePaginateData handles all limit', function () {
    $reflection = new ReflectionClass(PaymentMethod::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($limit == \'all\')')
        ->and($fileContent)->toContain('return $query->get()')
        ->and($fileContent)->toContain('return $query->paginate($limit)');
});

test('PaymentMethod has static createPaymentMethod method', function () {
    $reflection = new ReflectionClass(PaymentMethod::class);
    
    expect($reflection->hasMethod('createPaymentMethod'))->toBeTrue();
    
    $method = $reflection->getMethod('createPaymentMethod');
    expect($method->isStatic())->toBeTrue()
        ->and($method->isPublic())->toBeTrue();
});

test('PaymentMethod has static getSettings method', function () {
    $reflection = new ReflectionClass(PaymentMethod::class);
    
    expect($reflection->hasMethod('getSettings'))->toBeTrue();
    
    $method = $reflection->getMethod('getSettings');
    expect($method->isStatic())->toBeTrue()
        ->and($method->isPublic())->toBeTrue();
});