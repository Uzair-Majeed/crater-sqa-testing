<?php

use Crater\Http\Requests\PaymentMethodRequest;
use Crater\Models\PaymentMethod;
use Illuminate\Validation\Rule;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

uses(MockeryPHPUnitIntegration::class);

// Test for authorize() method
test('authorize method always returns true', function () {
    $request = new PaymentMethodRequest();
    expect($request->authorize())->toBeTrue();
});

// Tests for rules() method
function createRulesRequestMock(string $method, ?string $companyId = null, ?int $routeId = null): PaymentMethodRequest
    {
        $mock = Mockery::mock(PaymentMethodRequest::class)->makePartial();
        $mock->shouldReceive('getMethod')->andReturn($method);
        $mock->shouldReceive('header')->with('company')->andReturn($companyId);
        // Mock route() method for retrieving 'payment_method' parameter
        $mock->shouldReceive('route')->with('payment_method')->andReturn($routeId);

        return $mock;
    }

    test('rules for POST method includes unique name without ignore clause and correct company_id', function () {
        $companyId = 'company_id_post_1';
        $request = createRulesRequestMock('POST', $companyId);

        $rules = $request->rules();

        expect($rules)->toHaveKey('name');
        expect($rules['name'])->toBeArray();
        expect($rules['name'])->toContain('required');

        $uniqueRule = collect($rules['name'])->first(fn ($rule) => $rule instanceof Rule && str_contains((string) $rule, 'unique:payment_methods'));
        expect($uniqueRule)->not->toBeNull();
        // Expected format: unique:table,column,ignoreId,idColumn,whereColumn,whereValue
        // For POST, ignoreId is empty, column is 'name' by default
        expect((string) $uniqueRule)->toBe("unique:payment_methods,name,,id,company_id,{$companyId}");
    });

    test('rules for POST method with null company header includes unique name with empty company_id', function () {
        $request = createRulesRequestMock('POST', null);

        $rules = $request->rules();

        $uniqueRule = collect($rules['name'])->first(fn ($rule) => $rule instanceof Rule && str_contains((string) $rule, 'unique:payment_methods'));
        expect($uniqueRule)->not->toBeNull();
        // Expected format: unique:table,column,ignoreId,idColumn,whereColumn,whereValue
        // For null company ID, the value part for company_id will be empty
        expect((string) $uniqueRule)->toBe("unique:payment_methods,name,,id,company_id,");
    });

    test('rules for PUT method includes unique name with ignore clause and correct company_id and payment_method id', function () {
        $companyId = 'company_id_put_1';
        $paymentMethodId = 123;
        $request = createRulesRequestMock('PUT', $companyId, $paymentMethodId);

        $rules = $request->rules();

        expect($rules)->toHaveKey('name');
        expect($rules['name'])->toBeArray();
        expect($rules['name'])->toContain('required');

        $uniqueRule = collect($rules['name'])->first(fn ($rule) => $rule instanceof Rule && str_contains((string) $rule, 'unique:payment_methods'));
        expect($uniqueRule)->not->toBeNull();
        // Expected format for PUT: unique:table,column,ignoreId,idColumn,whereColumn,whereValue
        expect((string) $uniqueRule)->toBe("unique:payment_methods,name,{$paymentMethodId},id,company_id,{$companyId}");
    });

    test('rules for PUT method with null company header includes unique name with empty company_id', function () {
        $paymentMethodId = 456;
        $request = createRulesRequestMock('PUT', null, $paymentMethodId);

        $rules = $request->rules();

        $uniqueRule = collect($rules['name'])->first(fn ($rule) => $rule instanceof Rule && str_contains((string) $rule, 'unique:payment_methods'));
        expect($uniqueRule)->not->toBeNull();
        expect((string) $uniqueRule)->toBe("unique:payment_methods,name,{$paymentMethodId},id,company_id,");
    });

    test('rules for PUT method with null payment_method route param handles ignore clause correctly (no ignore effect)', function () {
        $companyId = 'company_id_put_null_route';
        $request = createRulesRequestMock('PUT', $companyId, null); // payment_method is null

        $rules = $request->rules();

        $uniqueRule = collect($rules['name'])->first(fn ($rule) => $rule instanceof Rule && str_contains((string) $rule, 'unique:payment_methods'));
        expect($uniqueRule)->not->toBeNull();
        // When ignore ID is null, it results in an empty string in the unique rule string representation, effectively no ignore.
        expect((string) $uniqueRule)->toBe("unique:payment_methods,name,,id,company_id,{$companyId}");
    });

    test('rules for non-POST non-PUT method (e.g., GET) defaults to POST behavior', function () {
        $companyId = 'company_id_get_1';
        $request = createRulesRequestMock('GET', $companyId); // Any method other than PUT

        $rules = $request->rules();

        expect($rules)->toHaveKey('name');
        expect($rules['name'])->toBeArray();
        expect($rules['name'])->toContain('required');

        $uniqueRule = collect($rules['name'])->first(fn ($rule) => $rule instanceof Rule && str_contains((string) $rule, 'unique:payment_methods'));
        expect($uniqueRule)->not->toBeNull();
        expect((string) $uniqueRule)->toBe("unique:payment_methods,name,,id,company_id,{$companyId}");
    });
function createPayloadRequestMock(array $validatedData, ?string $companyId = null): PaymentMethodRequest
    {
        $mock = Mockery::mock(PaymentMethodRequest::class)->makePartial();
        $mock->shouldReceive('validated')->andReturn($validatedData);
        $mock->shouldReceive('header')->with('company')->andReturn($companyId);
        return $mock;
    }

    test('getPaymentMethodPayload returns merged data with company_id and payment method type', function () {
        $validatedData = ['name' => 'Credit Card', 'description' => 'Online payments via card'];
        $companyId = 'comp_001_id';
        $request = createPayloadRequestMock($validatedData, $companyId);

        $payload = $request->getPaymentMethodPayload();

        expect($payload)->toBeArray();
        expect($payload)->toEqual(array_merge($validatedData, [
            'company_id' => $companyId,
            'type' => PaymentMethod::TYPE_GENERAL,
        ]));
    });

    test('getPaymentMethodPayload handles empty validated data correctly', function () {
        $validatedData = [];
        $companyId = 'comp_002_id';
        $request = createPayloadRequestMock($validatedData, $companyId);

        $payload = $request->getPaymentMethodPayload();

        expect($payload)->toBeArray();
        expect($payload)->toEqual([
            'company_id' => $companyId,
            'type' => PaymentMethod::TYPE_GENERAL,
        ]);
    });

    test('getPaymentMethodPayload handles null company header gracefully', function () {
        $validatedData = ['name' => 'Bank Transfer'];
        $request = createPayloadRequestMock($validatedData, null); // Null company ID

        $payload = $request->getPaymentMethodPayload();

        expect($payload)->toBeArray();
        expect($payload)->toEqual(array_merge($validatedData, [
            'company_id' => null, // Expect null in payload if header is null
            'type' => PaymentMethod::TYPE_GENERAL,
        ]));
    });

    test('getPaymentMethodPayload with multiple diverse validated fields', function () {
        $validatedData = [
            'name' => 'PayPal',
            'details' => 'paypal@example.com',
            'currency' => 'USD',
            'is_active' => true,
        ];
        $companyId = 'comp_003_id';
        $request = createPayloadRequestMock($validatedData, $companyId);

        $payload = $request->getPaymentMethodPayload();

        expect($payload)->toBeArray();
        expect($payload)->toEqual(array_merge($validatedData, [
            'company_id' => $companyId,
            'type' => PaymentMethod::TYPE_GENERAL,
        ]));
    });




afterEach(function () {
    Mockery::close();
});
