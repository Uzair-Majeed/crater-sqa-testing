<?php

use Crater\Http\Requests\ExchangeRateLogRequest;

// ========== CLASS STRUCTURE TESTS ==========

test('ExchangeRateLogRequest can be instantiated', function () {
    $request = new ExchangeRateLogRequest();
    expect($request)->toBeInstanceOf(ExchangeRateLogRequest::class);
});

test('ExchangeRateLogRequest extends FormRequest', function () {
    $request = new ExchangeRateLogRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

test('ExchangeRateLogRequest is in correct namespace', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Requests');
});

test('ExchangeRateLogRequest is not abstract', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('ExchangeRateLogRequest is instantiable', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== METHOD EXISTENCE TESTS ==========

test('ExchangeRateLogRequest has authorize method', function () {
    $request = new ExchangeRateLogRequest();
    expect(method_exists($request, 'authorize'))->toBeTrue();
});

test('ExchangeRateLogRequest has rules method', function () {
    $request = new ExchangeRateLogRequest();
    expect(method_exists($request, 'rules'))->toBeTrue();
});

test('ExchangeRateLogRequest has getExchangeRateLogPayload method', function () {
    $request = new ExchangeRateLogRequest();
    expect(method_exists($request, 'getExchangeRateLogPayload'))->toBeTrue();
});

// ========== AUTHORIZE METHOD TESTS ==========

test('authorize method returns true', function () {
    $request = new ExchangeRateLogRequest();
    expect($request->authorize())->toBeTrue();
});

test('authorize method is public', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $method = $reflection->getMethod('authorize');
    
    expect($method->isPublic())->toBeTrue();
});

test('authorize method has no parameters', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $method = $reflection->getMethod('authorize');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

test('authorize method returns boolean', function () {
    $request = new ExchangeRateLogRequest();
    $result = $request->authorize();
    
    expect($result)->toBeBool();
});

// ========== RULES METHOD TESTS ==========

test('rules method is public', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $method = $reflection->getMethod('rules');
    
    expect($method->isPublic())->toBeTrue();
});

test('rules method has no parameters', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $method = $reflection->getMethod('rules');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

test('rules method returns array', function () {
    $request = new ExchangeRateLogRequest();
    $rules = $request->rules();
    
    expect($rules)->toBeArray();
});

test('rules method includes exchange_rate validation', function () {
    $request = new ExchangeRateLogRequest();
    $rules = $request->rules();
    
    expect($rules)->toHaveKey('exchange_rate')
        ->and($rules['exchange_rate'])->toBeArray()
        ->and($rules['exchange_rate'])->toContain('required');
});

test('rules method includes currency_id validation', function () {
    $request = new ExchangeRateLogRequest();
    $rules = $request->rules();
    
    expect($rules)->toHaveKey('currency_id')
        ->and($rules['currency_id'])->toBeArray()
        ->and($rules['currency_id'])->toContain('required');
});

test('rules method returns exactly two validation rules', function () {
    $request = new ExchangeRateLogRequest();
    $rules = $request->rules();
    
    expect(count($rules))->toBe(2);
});

// ========== GETEXCHANGERATELOGPAYLOAD METHOD TESTS ==========

test('getExchangeRateLogPayload method is public', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $method = $reflection->getMethod('getExchangeRateLogPayload');
    
    expect($method->isPublic())->toBeTrue();
});

test('getExchangeRateLogPayload method has no parameters', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $method = $reflection->getMethod('getExchangeRateLogPayload');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

// ========== METHOD STATIC TESTS ==========

test('all methods are not static', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    
    expect($reflection->getMethod('authorize')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('rules')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('getExchangeRateLogPayload')->isStatic())->toBeFalse();
});

test('all methods are not abstract', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    
    expect($reflection->getMethod('authorize')->isAbstract())->toBeFalse()
        ->and($reflection->getMethod('rules')->isAbstract())->toBeFalse()
        ->and($reflection->getMethod('getExchangeRateLogPayload')->isAbstract())->toBeFalse();
});

// ========== INSTANCE TESTS ==========

test('multiple ExchangeRateLogRequest instances can be created', function () {
    $request1 = new ExchangeRateLogRequest();
    $request2 = new ExchangeRateLogRequest();
    
    expect($request1)->toBeInstanceOf(ExchangeRateLogRequest::class)
        ->and($request2)->toBeInstanceOf(ExchangeRateLogRequest::class)
        ->and($request1)->not->toBe($request2);
});

test('ExchangeRateLogRequest can be cloned', function () {
    $request = new ExchangeRateLogRequest();
    $clone = clone $request;
    
    expect($clone)->toBeInstanceOf(ExchangeRateLogRequest::class)
        ->and($clone)->not->toBe($request);
});

test('ExchangeRateLogRequest can be used in type hints', function () {
    $testFunction = function (ExchangeRateLogRequest $request) {
        return $request;
    };
    
    $request = new ExchangeRateLogRequest();
    $result = $testFunction($request);
    
    expect($result)->toBe($request);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('ExchangeRateLogRequest is not final', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('ExchangeRateLogRequest is not an interface', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('ExchangeRateLogRequest is not a trait', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('ExchangeRateLogRequest class is loaded', function () {
    expect(class_exists(ExchangeRateLogRequest::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('ExchangeRateLogRequest uses required classes', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Models\CompanySetting')
        ->and($fileContent)->toContain('use Illuminate\Foundation\Http\FormRequest');
});

// ========== FILE STRUCTURE TESTS ==========

test('ExchangeRateLogRequest file has expected structure', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class ExchangeRateLogRequest extends FormRequest')
        ->and($fileContent)->toContain('public function authorize()')
        ->and($fileContent)->toContain('public function rules()')
        ->and($fileContent)->toContain('public function getExchangeRateLogPayload()');
});

test('ExchangeRateLogRequest has compact implementation', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be reasonably sized (< 2000 bytes)
    expect(strlen($fileContent))->toBeLessThan(2000);
});

test('ExchangeRateLogRequest has reasonable line count', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(70);
});

// ========== RULES IMPLEMENTATION TESTS ==========

test('rules method contains validation logic', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('exchange_rate')
        ->and($fileContent)->toContain('currency_id')
        ->and($fileContent)->toContain('required');
});

// ========== PAYLOAD METHOD TESTS ==========

test('getExchangeRateLogPayload uses CompanySetting', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('CompanySetting::getSetting');
});

test('getExchangeRateLogPayload checks currency_id', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->currency_id');
});

test('getExchangeRateLogPayload uses header method', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->header');
});

test('getExchangeRateLogPayload uses validated method', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->validated()');
});

test('getExchangeRateLogPayload uses collect helper', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('collect')
        ->and($fileContent)->toContain('merge')
        ->and($fileContent)->toContain('toArray');
});

test('getExchangeRateLogPayload includes company_id in payload', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('company_id');
});

test('getExchangeRateLogPayload includes base_currency_id in payload', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('base_currency_id');
});

test('getExchangeRateLogPayload has conditional logic', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if (');
});

test('getExchangeRateLogPayload uses strict comparison', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('!==');
});

// ========== DOCUMENTATION TESTS ==========

test('authorize method has documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $method = $reflection->getMethod('authorize');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('rules method has documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $method = $reflection->getMethod('rules');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('authorize method has return type documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $method = $reflection->getMethod('authorize');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@return');
});

test('rules method has return type documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $method = $reflection->getMethod('rules');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@return');
});

// ========== VALIDATION RULES STRUCTURE TESTS ==========

test('exchange_rate rule is an array', function () {
    $request = new ExchangeRateLogRequest();
    $rules = $request->rules();
    
    expect($rules['exchange_rate'])->toBeArray();
});

test('currency_id rule is an array', function () {
    $request = new ExchangeRateLogRequest();
    $rules = $request->rules();
    
    expect($rules['currency_id'])->toBeArray();
});

test('exchange_rate has exactly one validation rule', function () {
    $request = new ExchangeRateLogRequest();
    $rules = $request->rules();
    
    expect(count($rules['exchange_rate']))->toBe(1);
});

test('currency_id has exactly one validation rule', function () {
    $request = new ExchangeRateLogRequest();
    $rules = $request->rules();
    
    expect(count($rules['currency_id']))->toBe(1);
});

// ========== PARENT CLASS TESTS ==========

test('ExchangeRateLogRequest parent is FormRequest', function () {
    $reflection = new ReflectionClass(ExchangeRateLogRequest::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe('Illuminate\Foundation\Http\FormRequest');
});

test('ExchangeRateLogRequest inherits from Request', function () {
    $request = new ExchangeRateLogRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Http\Request::class);
});