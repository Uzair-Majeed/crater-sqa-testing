<?php

namespace Tests\Unit;

use Crater\Http\Requests\CompanyLogoRequest;
use Crater\Rules\Base64Mime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

test('authorize method returns true', function () {
    $request = new CompanyLogoRequest();
    
    // Properly initialize the form request
    $request->setContainer(app());
    
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct validation rules', function () {
    $request = new CompanyLogoRequest();
    $rules = $request->rules();
    
    // Check basic structure
    expect($rules)->toBeArray()
        ->toHaveKey('company_logo');
    
    // The company_logo should be an array of rules
    $companyLogoRules = $rules['company_logo'];
    
    // Should contain 'nullable' rule
    expect($companyLogoRules)->toBeArray()
        ->toContain('nullable');
    
    // Should contain a Base64Mime instance
    $hasBase64Mime = collect($companyLogoRules)
        ->contains(fn ($rule) => $rule instanceof Base64Mime);
    
    expect($hasBase64Mime)->toBeTrue();
});

test('validation passes with valid base64 image', function () {
    $request = new CompanyLogoRequest();
    $rules = $request->rules();
    
    // Valid PNG image as JSON string (1x1 pixel transparent)
    $validPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    
    // Create JSON string with data property
    $jsonData = json_encode(['data' => $validPng]);
    
    $validator = Validator::make(
        ['company_logo' => $jsonData],
        $rules
    );
    
    expect($validator->passes())->toBeTrue();
});

test('validation passes with null value', function () {
    $request = new CompanyLogoRequest();
    $rules = $request->rules();
    
    $validator = Validator::make(
        ['company_logo' => null],
        $rules
    );
    
    expect($validator->passes())->toBeTrue();
});

test('validation passes with empty string', function () {
    $request = new CompanyLogoRequest();
    $rules = $request->rules();
    
    $validator = Validator::make(
        ['company_logo' => ''],
        $rules
    );
    
    expect($validator->passes())->toBeTrue();
});

test('validation fails with invalid JSON', function () {
    $request = new CompanyLogoRequest();
    $rules = $request->rules();
    
    // Invalid JSON string
    $validator = Validator::make(
        ['company_logo' => '{invalid json'],
        $rules
    );
    
    expect($validator->fails())->toBeTrue();
});

test('validation fails with JSON missing data property', function () {
    $request = new CompanyLogoRequest();
    $rules = $request->rules();
    
    // Valid JSON but missing 'data' property
    $jsonData = json_encode(['something' => 'else']);
    
    $validator = Validator::make(
        ['company_logo' => $jsonData],
        $rules
    );
    
    expect($validator->fails())->toBeTrue();
});

test('validation fails with invalid file type', function () {
    $request = new CompanyLogoRequest();
    $rules = $request->rules();
    
    // PDF file (not allowed)
    $pdfData = 'data:application/pdf;base64,JVBERi0xLg==';
    $jsonData = json_encode(['data' => $pdfData]);
    
    $validator = Validator::make(
        ['company_logo' => $jsonData],
        $rules
    );
    
    expect($validator->fails())->toBeTrue();
});

test('Base64Mime rule validates allowed types directly', function () {
    // Create rule with allowed types
    $rule = new Base64Mime(['gif', 'jpg', 'png']);
    
    // Valid PNG as JSON string
    $validPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    $validJson = json_encode(['data' => $validPng]);
    
    expect($rule->passes('company_logo', $validJson))->toBeTrue();
    
    // Invalid PDF as JSON string
    $invalidPdf = 'data:application/pdf;base64,JVBERi0xLg==';
    $invalidJson = json_encode(['data' => $invalidPdf]);
    
    expect($rule->passes('company_logo', $invalidJson))->toBeFalse();
});

test('rules are functionally consistent across request methods', function () {
    $requestPost = new CompanyLogoRequest();
    $requestPut = new CompanyLogoRequest();
    
    $rulesPost = $requestPost->rules();
    $rulesPut = $requestPut->rules();
    
    // Check they have the same keys
    expect(array_keys($rulesPost))->toBe(array_keys($rulesPut));
    
    // Check company_logo rules have same structure
    expect(count($rulesPost['company_logo']))->toBe(count($rulesPut['company_logo']));
    
    // Check both contain nullable
    expect(collect($rulesPost['company_logo'])->contains('nullable'))->toBeTrue();
    expect(collect($rulesPut['company_logo'])->contains('nullable'))->toBeTrue();
    
    // Check both contain Base64Mime rule
    $postHasBase64Mime = collect($rulesPost['company_logo'])
        ->contains(fn ($rule) => $rule instanceof Base64Mime);
    $putHasBase64Mime = collect($rulesPut['company_logo'])
        ->contains(fn ($rule) => $rule instanceof Base64Mime);
    
    expect($postHasBase64Mime)->toBeTrue();
    expect($putHasBase64Mime)->toBeTrue();
});

test('rule accepts allowed extensions parameter', function () {
    // Test that Base64Mime rule can be instantiated with extensions
    $rule = new Base64Mime(['gif', 'jpg', 'png']);
    
    expect($rule)->toBeInstanceOf(Base64Mime::class);
    
    // Test it works with a valid type
    $validPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    $validJson = json_encode(['data' => $validPng]);
    
    expect($rule->passes('test', $validJson))->toBeTrue();
});