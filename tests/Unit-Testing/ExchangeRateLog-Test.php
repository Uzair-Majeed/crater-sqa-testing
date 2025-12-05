<?php

use Crater\Models\ExchangeRateLog;
use Crater\Models\Currency;
use Crater\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ========== CLASS STRUCTURE TESTS ==========

test('ExchangeRateLog can be instantiated', function () {
    $log = new ExchangeRateLog();
    expect($log)->toBeInstanceOf(ExchangeRateLog::class);
});

test('ExchangeRateLog extends Model', function () {
    $log = new ExchangeRateLog();
    expect($log)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
});

test('ExchangeRateLog is in correct namespace', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Models');
});

test('ExchangeRateLog is not abstract', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('ExchangeRateLog is instantiable', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== GUARDED PROPERTIES TESTS ==========

test('ExchangeRateLog has guarded properties', function () {
    $log = new ExchangeRateLog();
    expect($log->getGuarded())->toBe(['id']);
});

test('guarded property prevents id from mass assignment', function () {
    $log = new ExchangeRateLog();
    $guarded = $log->getGuarded();
    
    expect($guarded)->toContain('id');
});

test('id cannot be mass assigned via fill', function () {
    $log = new ExchangeRateLog();
    $log->fill(['id' => 999, 'exchange_rate' => 1.5]);
    
    expect($log->id)->toBeNull()
        ->and($log->exchange_rate)->toBe(1.5);
});

// ========== CASTS TESTS ==========

test('exchange_rate is cast to float', function () {
    $log = new ExchangeRateLog();
    $log->exchange_rate = 1.23;
    
    expect($log->exchange_rate)->toBeFloat()
        ->and($log->exchange_rate)->toBe(1.23);
});

test('exchange_rate casts string to float', function () {
    $log = new ExchangeRateLog();
    $log->exchange_rate = "1.5";
    
    expect($log->exchange_rate)->toBeFloat()
        ->and($log->exchange_rate)->toBe(1.5);
});

test('exchange_rate casts integer to float', function () {
    $log = new ExchangeRateLog();
    $log->exchange_rate = 2;
    
    expect($log->exchange_rate)->toBeFloat()
        ->and($log->exchange_rate)->toBe(2.0);
});

test('exchange_rate handles null values', function () {
    $log = new ExchangeRateLog();
    $log->exchange_rate = null;
    
    expect($log->exchange_rate)->toBeNull();
});

test('exchange_rate casts invalid string to zero', function () {
    $log = new ExchangeRateLog();
    $log->exchange_rate = "invalid";
    
    expect($log->exchange_rate)->toBeFloat()
        ->and($log->exchange_rate)->toBe(0.0);
});

// ========== RELATIONSHIP TESTS ==========

test('currency method exists', function () {
    $log = new ExchangeRateLog();
    expect(method_exists($log, 'currency'))->toBeTrue();
});

test('currency relationship returns BelongsTo', function () {
    $log = new ExchangeRateLog();
    $relation = $log->currency();
    
    expect($relation)->toBeInstanceOf(BelongsTo::class);
});

test('currency relationship is to Currency model', function () {
    $log = new ExchangeRateLog();
    $relation = $log->currency();
    
    expect($relation->getRelated())->toBeInstanceOf(Currency::class);
});

test('currency relationship uses currency_id foreign key', function () {
    $log = new ExchangeRateLog();
    $relation = $log->currency();
    
    expect($relation->getForeignKeyName())->toBe('currency_id');
});

test('currency relationship uses id owner key', function () {
    $log = new ExchangeRateLog();
    $relation = $log->currency();
    
    expect($relation->getOwnerKeyName())->toBe('id');
});

test('company method exists', function () {
    $log = new ExchangeRateLog();
    expect(method_exists($log, 'company'))->toBeTrue();
});

test('company relationship returns BelongsTo', function () {
    $log = new ExchangeRateLog();
    $relation = $log->company();
    
    expect($relation)->toBeInstanceOf(BelongsTo::class);
});

test('company relationship is to Company model', function () {
    $log = new ExchangeRateLog();
    $relation = $log->company();
    
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
});

test('company relationship uses company_id foreign key', function () {
    $log = new ExchangeRateLog();
    $relation = $log->company();
    
    expect($relation->getForeignKeyName())->toBe('company_id');
});

test('company relationship uses id owner key', function () {
    $log = new ExchangeRateLog();
    $relation = $log->company();
    
    expect($relation->getOwnerKeyName())->toBe('id');
});

// ========== STATIC METHOD TESTS ==========

test('addExchangeRateLog method exists', function () {
    expect(method_exists(ExchangeRateLog::class, 'addExchangeRateLog'))->toBeTrue();
});

test('addExchangeRateLog is a static method', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $method = $reflection->getMethod('addExchangeRateLog');
    
    expect($method->isStatic())->toBeTrue();
});

test('addExchangeRateLog is public', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $method = $reflection->getMethod('addExchangeRateLog');
    
    expect($method->isPublic())->toBeTrue();
});

test('addExchangeRateLog accepts one parameter', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $method = $reflection->getMethod('addExchangeRateLog');
    
    expect($method->getNumberOfParameters())->toBe(1);
});

// ========== TRAITS TESTS ==========

test('ExchangeRateLog uses HasFactory trait', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
});

// ========== INSTANCE TESTS ==========

test('multiple ExchangeRateLog instances can be created', function () {
    $log1 = new ExchangeRateLog();
    $log2 = new ExchangeRateLog();
    
    expect($log1)->toBeInstanceOf(ExchangeRateLog::class)
        ->and($log2)->toBeInstanceOf(ExchangeRateLog::class)
        ->and($log1)->not->toBe($log2);
});

test('ExchangeRateLog can be cloned', function () {
    $log = new ExchangeRateLog(['exchange_rate' => 1.5]);
    $clone = clone $log;
    
    expect($clone)->toBeInstanceOf(ExchangeRateLog::class)
        ->and($clone)->not->toBe($log)
        ->and($clone->exchange_rate)->toBe($log->exchange_rate);
});

test('ExchangeRateLog can be used in type hints', function () {
    $testFunction = function (ExchangeRateLog $log) {
        return $log;
    };
    
    $log = new ExchangeRateLog();
    $result = $testFunction($log);
    
    expect($result)->toBe($log);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('ExchangeRateLog is not final', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('ExchangeRateLog is not an interface', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('ExchangeRateLog is not a trait', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('ExchangeRateLog class is loaded', function () {
    expect(class_exists(ExchangeRateLog::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('ExchangeRateLog uses required classes', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Illuminate\Database\Eloquent\Factories\HasFactory')
        ->and($fileContent)->toContain('use Illuminate\Database\Eloquent\Model');
});

// ========== FILE STRUCTURE TESTS ==========

test('ExchangeRateLog file has expected structure', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class ExchangeRateLog extends Model')
        ->and($fileContent)->toContain('protected $guarded')
        ->and($fileContent)->toContain('protected $casts');
});

test('ExchangeRateLog has compact implementation', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be concise (< 1500 bytes)
    expect(strlen($fileContent))->toBeLessThan(1500);
});

test('ExchangeRateLog has minimal line count', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(60);
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('all relationship methods are public', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    
    expect($reflection->getMethod('currency')->isPublic())->toBeTrue()
        ->and($reflection->getMethod('company')->isPublic())->toBeTrue();
});

test('all relationship methods are not static', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    
    expect($reflection->getMethod('currency')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('company')->isStatic())->toBeFalse();
});

test('all relationship methods have no parameters', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    
    expect($reflection->getMethod('currency')->getNumberOfParameters())->toBe(0)
        ->and($reflection->getMethod('company')->getNumberOfParameters())->toBe(0);
});

// ========== ATTRIBUTE TESTS ==========

test('can set and get exchange_rate attribute', function () {
    $log = new ExchangeRateLog();
    $log->exchange_rate = 1.75;
    
    expect($log->exchange_rate)->toBe(1.75);
});

test('can set and get company_id attribute', function () {
    $log = new ExchangeRateLog();
    $log->company_id = 5;
    
    expect($log->company_id)->toBe(5);
});

test('can set and get currency_id attribute', function () {
    $log = new ExchangeRateLog();
    $log->currency_id = 10;
    
    expect($log->currency_id)->toBe(10);
});

test('can set and get base_currency_id attribute', function () {
    $log = new ExchangeRateLog();
    $log->base_currency_id = 15;
    
    expect($log->base_currency_id)->toBe(15);
});

// ========== CASTS CONFIGURATION TESTS ==========

test('casts are properly configured', function () {
    $log = new ExchangeRateLog();
    $casts = $log->getCasts();
    
    expect($casts)->toHaveKey('exchange_rate')
        ->and($casts['exchange_rate'])->toBe('float');
});

// ========== COMPLEX CASTING TESTS ==========

test('handles decimal string for float cast', function () {
    $log = new ExchangeRateLog();
    $log->exchange_rate = "1.234567";
    
    expect($log->exchange_rate)->toBeFloat()
        ->and($log->exchange_rate)->toBe(1.234567);
});

test('handles negative values for float cast', function () {
    $log = new ExchangeRateLog();
    $log->exchange_rate = -0.5;
    
    expect($log->exchange_rate)->toBeFloat()
        ->and($log->exchange_rate)->toBe(-0.5);
});

test('handles zero values correctly', function () {
    $log = new ExchangeRateLog();
    $log->exchange_rate = 0;
    
    expect($log->exchange_rate)->toBe(0.0);
});

test('handles very small float values', function () {
    $log = new ExchangeRateLog();
    $log->exchange_rate = 0.0001;
    
    expect($log->exchange_rate)->toBeFloat()
        ->and($log->exchange_rate)->toBe(0.0001);
});

test('handles very large float values', function () {
    $log = new ExchangeRateLog();
    $log->exchange_rate = 999999.99;
    
    expect($log->exchange_rate)->toBeFloat()
        ->and($log->exchange_rate)->toBe(999999.99);
});

// ========== ADDEXCHANGERATELOG IMPLEMENTATION TESTS ==========

test('addExchangeRateLog method uses CompanySetting', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('CompanySetting::getSetting');
});

test('addExchangeRateLog method calls create', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('self::create');
});

test('addExchangeRateLog method maps model properties', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('exchange_rate')
        ->and($fileContent)->toContain('company_id')
        ->and($fileContent)->toContain('base_currency_id')
        ->and($fileContent)->toContain('currency_id');
});

test('addExchangeRateLog uses model parameter properties', function () {
    $reflection = new ReflectionClass(ExchangeRateLog::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$model->exchange_rate')
        ->and($fileContent)->toContain('$model->company_id')
        ->and($fileContent)->toContain('$model->currency_id');
});

// ========== DATA INTEGRITY TESTS ==========

test('data is preserved through constructor', function () {
    $data = [
        'exchange_rate' => 1.5,
        'company_id' => 1,
        'currency_id' => 2,
        'base_currency_id' => 3
    ];
    
    $log = new ExchangeRateLog($data);
    
    expect($log->exchange_rate)->toBe(1.5)
        ->and($log->company_id)->toBe(1)
        ->and($log->currency_id)->toBe(2)
        ->and($log->base_currency_id)->toBe(3);
});

test('different instances have independent data', function () {
    $log1 = new ExchangeRateLog(['exchange_rate' => 1.0]);
    $log2 = new ExchangeRateLog(['exchange_rate' => 2.0]);
    
    expect($log1->exchange_rate)->not->toBe($log2->exchange_rate)
        ->and($log1->exchange_rate)->toBe(1.0)
        ->and($log2->exchange_rate)->toBe(2.0);
});

// ========== MODEL FEATURES TESTS ==========

test('ExchangeRateLog inherits Model methods', function () {
    $log = new ExchangeRateLog();
    
    expect(method_exists($log, 'save'))->toBeTrue()
        ->and(method_exists($log, 'fill'))->toBeTrue()
        ->and(method_exists($log, 'toArray'))->toBeTrue();
});

test('ExchangeRateLog can use Model features', function () {
    $log = new ExchangeRateLog();
    
    expect(is_callable([$log, 'fill']))->toBeTrue()
        ->and(is_callable([$log, 'toArray']))->toBeTrue();
});
