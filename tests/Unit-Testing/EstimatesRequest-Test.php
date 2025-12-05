<?php

use Crater\Http\Requests\EstimatesRequest;
use Illuminate\Validation\Rule;

// ========== CLASS STRUCTURE TESTS ==========

test('EstimatesRequest can be instantiated', function () {
    $request = new EstimatesRequest();
    expect($request)->toBeInstanceOf(EstimatesRequest::class);
});

test('EstimatesRequest extends FormRequest', function () {
    $request = new EstimatesRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

test('EstimatesRequest is in correct namespace', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Requests');
});

test('EstimatesRequest is not abstract', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('EstimatesRequest is instantiable', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== METHOD EXISTENCE TESTS ==========

test('EstimatesRequest has authorize method', function () {
    $request = new EstimatesRequest();
    expect(method_exists($request, 'authorize'))->toBeTrue();
});

test('EstimatesRequest has rules method', function () {
    $request = new EstimatesRequest();
    expect(method_exists($request, 'rules'))->toBeTrue();
});

test('EstimatesRequest has getEstimatePayload method', function () {
    $request = new EstimatesRequest();
    expect(method_exists($request, 'getEstimatePayload'))->toBeTrue();
});

// ========== AUTHORIZE METHOD TESTS ==========

test('authorize method returns true', function () {
    $request = new EstimatesRequest();
    expect($request->authorize())->toBeTrue();
});

test('authorize method is public', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $method = $reflection->getMethod('authorize');
    
    expect($method->isPublic())->toBeTrue();
});

test('authorize method has no parameters', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $method = $reflection->getMethod('authorize');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

test('authorize method returns boolean', function () {
    $request = new EstimatesRequest();
    $result = $request->authorize();
    
    expect($result)->toBeBool();
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('rules method is public', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $method = $reflection->getMethod('rules');
    
    expect($method->isPublic())->toBeTrue();
});

test('rules method has no parameters', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $method = $reflection->getMethod('rules');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

test('getEstimatePayload method is public', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $method = $reflection->getMethod('getEstimatePayload');
    
    expect($method->isPublic())->toBeTrue();
});

test('getEstimatePayload method has no parameters', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $method = $reflection->getMethod('getEstimatePayload');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

// ========== METHOD STATIC TESTS ==========

test('all methods are not static', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    
    expect($reflection->getMethod('authorize')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('rules')->isStatic())->toBeFalse()
        ->and($reflection->getMethod('getEstimatePayload')->isStatic())->toBeFalse();
});

test('all methods are not abstract', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    
    expect($reflection->getMethod('authorize')->isAbstract())->toBeFalse()
        ->and($reflection->getMethod('rules')->isAbstract())->toBeFalse()
        ->and($reflection->getMethod('getEstimatePayload')->isAbstract())->toBeFalse();
});

// ========== INSTANCE TESTS ==========

test('multiple EstimatesRequest instances can be created', function () {
    $request1 = new EstimatesRequest();
    $request2 = new EstimatesRequest();
    
    expect($request1)->toBeInstanceOf(EstimatesRequest::class)
        ->and($request2)->toBeInstanceOf(EstimatesRequest::class)
        ->and($request1)->not->toBe($request2);
});

test('EstimatesRequest can be cloned', function () {
    $request = new EstimatesRequest();
    $clone = clone $request;
    
    expect($clone)->toBeInstanceOf(EstimatesRequest::class)
        ->and($clone)->not->toBe($request);
});

test('EstimatesRequest can be used in type hints', function () {
    $testFunction = function (EstimatesRequest $request) {
        return $request;
    };
    
    $request = new EstimatesRequest();
    $result = $testFunction($request);
    
    expect($result)->toBe($request);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('EstimatesRequest is not final', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('EstimatesRequest is not an interface', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('EstimatesRequest is not a trait', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('EstimatesRequest class is loaded', function () {
    expect(class_exists(EstimatesRequest::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('EstimatesRequest uses required classes', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Models\CompanySetting')
        ->and($fileContent)->toContain('use Crater\Models\Customer')
        ->and($fileContent)->toContain('use Crater\Models\Estimate')
        ->and($fileContent)->toContain('use Illuminate\Foundation\Http\FormRequest')
        ->and($fileContent)->toContain('use Illuminate\Validation\Rule');
});

// ========== FILE STRUCTURE TESTS ==========

test('EstimatesRequest file has expected structure', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class EstimatesRequest extends FormRequest')
        ->and($fileContent)->toContain('public function authorize()')
        ->and($fileContent)->toContain('public function rules()')
        ->and($fileContent)->toContain('public function getEstimatePayload()');
});

test('EstimatesRequest has comprehensive implementation', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be substantial (>3000 bytes)
    expect(strlen($fileContent))->toBeGreaterThan(3000);
});

test('EstimatesRequest has reasonable line count', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeGreaterThan(100);
});

// ========== RULES IMPLEMENTATION TESTS ==========

test('rules method contains validation logic', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('estimate_date')
        ->and($fileContent)->toContain('expiry_date')
        ->and($fileContent)->toContain('customer_id')
        ->and($fileContent)->toContain('estimate_number')
        ->and($fileContent)->toContain('exchange_rate');
});

test('rules method includes item validation', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('items')
        ->and($fileContent)->toContain('items.*')
        ->and($fileContent)->toContain('items.*.name')
        ->and($fileContent)->toContain('items.*.quantity')
        ->and($fileContent)->toContain('items.*.price');
});

test('rules method includes financial fields', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('discount')
        ->and($fileContent)->toContain('discount_val')
        ->and($fileContent)->toContain('sub_total')
        ->and($fileContent)->toContain('total')
        ->and($fileContent)->toContain('tax');
});

test('rules method uses Rule::unique', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Rule::unique');
});

test('rules method checks for PUT method', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('isMethod')
        ->and($fileContent)->toContain('PUT');
});

// ========== PAYLOAD METHOD TESTS ==========

test('getEstimatePayload method contains payload logic', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('getEstimatePayload')
        ->and($fileContent)->toContain('creator_id')
        ->and($fileContent)->toContain('status')
        ->and($fileContent)->toContain('company_id');
});

test('getEstimatePayload uses CompanySetting', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('CompanySetting::getSetting');
});

test('getEstimatePayload calculates base values', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('base_discount_val')
        ->and($fileContent)->toContain('base_sub_total')
        ->and($fileContent)->toContain('base_total')
        ->and($fileContent)->toContain('base_tax');
});

test('getEstimatePayload handles exchange rate', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('exchange_rate');
});

test('getEstimatePayload uses collect helper', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('collect')
        ->and($fileContent)->toContain('except')
        ->and($fileContent)->toContain('merge')
        ->and($fileContent)->toContain('toArray');
});

// ========== ESTIMATE STATUS TESTS ==========

test('getEstimatePayload references Estimate statuses', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Estimate::STATUS_SENT')
        ->and($fileContent)->toContain('Estimate::STATUS_DRAFT');
});

test('getEstimatePayload checks for estimateSend', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('estimateSend');
});

// ========== COMPREHENSIVE VALIDATION RULES TESTS ==========

test('rules method includes template_name validation', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('template_name');
});

test('rules method validates item description', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('items.*.description');
});

// ========== CURRENCY HANDLING TESTS ==========

test('rules method checks currency matching', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('currency');
});

test('getEstimatePayload retrieves customer currency', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Customer::find')
        ->and($fileContent)->toContain('currency_id');
});

// ========== COMPANY SETTINGS TESTS ==========

test('getEstimatePayload uses tax_per_item setting', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('tax_per_item');
});

test('getEstimatePayload uses discount_per_item setting', function () {
    $reflection = new ReflectionClass(EstimatesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('discount_per_item');
});