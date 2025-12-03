<?php

uses(Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration::class);
use Crater\Models\TaxType;
use Crater\Http\Requests\TaxTypeRequest;
use Illuminate\Validation\Rules\Unique;

test('authorize method always returns true', function () {
    $request = new TaxTypeRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct rules for creation (POST)', function () {
    /** @var TaxTypeRequest|Mockery\MockInterface $request */
    $request = Mockery::mock(TaxTypeRequest::class)->makePartial();

    $request->shouldReceive('isMethod')
        ->with('PUT')
        ->andReturn(false) // Simulate POST
        ->atLeast()->once();

    $request->shouldReceive('header')
        ->with('company')
        ->andReturn(123) // Simulate company ID from header
        ->atLeast()->once();

    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys(['name', 'percent', 'description', 'compound_tax', 'collective_tax'])
        ->and($rules['name'])->toBeArray()
        ->and($rules['name'])->toContain('required')
        ->and($rules['percent'])->toBeArray()
        ->and($rules['percent'])->toContain('required')
        ->and($rules['description'])->toBeArray()
        ->and($rules['description'])->toContain('nullable')
        ->and($rules['compound_tax'])->toBeArray()
        ->and($rules['compound_tax'])->toContain('nullable')
        ->and($rules['collective_tax'])->toBeArray()
        ->and($rules['collective_tax'])->toContain('nullable');

    // Assert the unique rule specifically
    $uniqueRule = collect($rules['name'])->filter(fn ($rule) => $rule instanceof Unique)->first();
    expect($uniqueRule)->toBeInstanceOf(Unique::class);

    // Using reflection to access private properties of Unique rule for white-box testing
    $tableProperty = new ReflectionProperty(Unique::class, 'table');
    $tableProperty->setAccessible(true);
    expect($tableProperty->getValue($uniqueRule))->toBe('tax_types');

    $columnProperty = new ReflectionProperty(Unique::class, 'column');
    $columnProperty->setAccessible(true);
    // When Rule::unique('table_name') is used without a column argument, the 'column'
    // property of the Unique rule object remains null internally. The validation
    // system infers the column name (the attribute being validated) when the rule is processed.
    // The test should reflect this internal state.
    expect($columnProperty->getValue($uniqueRule))->toBeNull();

    $queryCallbacksProperty = new ReflectionProperty(Unique::class, 'queryCallbacks');
    $queryCallbacksProperty->setAccessible(true);
    $queryCallbacks = $queryCallbacksProperty->getValue($uniqueRule);

    expect($queryCallbacks)->toHaveCount(2);

    // Mock a query builder to verify the 'where' clauses
    $mockQueryBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
    $mockQueryBuilder->shouldReceive('where')
        ->with('type', TaxType::TYPE_GENERAL)
        ->once()
        ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')
        ->with('company_id', 123) // Expected company ID
        ->once()
        ->andReturnSelf();

    // Execute the callbacks with the mock builder
    foreach ($queryCallbacks as $callback) {
        $callback($mockQueryBuilder);
    }
});

test('rules method returns correct rules for update (PUT)', function () {
    /** @var TaxTypeRequest|Mockery\MockInterface $request */
    $request = Mockery::mock(TaxTypeRequest::class)->makePartial();

    $request->shouldReceive('isMethod')
        ->with('PUT')
        ->andReturn(true) // Simulate PUT
        ->atLeast()->once();

    $request->shouldReceive('header')
        ->with('company')
        ->andReturn(456) // Simulate different company ID
        ->atLeast()->once();

    // Mock the route method to return an object with an 'id' property
    $mockTaxType = (object)['id' => 789]; // Example tax type ID for ignore
    $request->shouldReceive('route')
        ->with('tax_type')
        ->andReturn($mockTaxType)
        ->atLeast()->once();

    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys(['name', 'percent', 'description', 'compound_tax', 'collective_tax'])
        ->and($rules['name'])->toBeArray()
        ->and($rules['name'])->toContain('required');

    // Assert the unique rule specifically for PUT
    $uniqueRule = collect($rules['name'])->filter(fn ($rule) => $rule instanceof Unique)->first();
    expect($uniqueRule)->toBeInstanceOf(Unique::class);

    // Using reflection to access private properties of Unique rule
    $tableProperty = new ReflectionProperty(Unique::class, 'table');
    $tableProperty->setAccessible(true);
    expect($tableProperty->getValue($uniqueRule))->toBe('tax_types');

    $columnProperty = new ReflectionProperty(Unique::class, 'column');
    $columnProperty->setAccessible(true);
    // As explained in the previous test, the 'column' property is null internally
    // when Rule::unique('table_name') is used without explicitly specifying the column.
    expect($columnProperty->getValue($uniqueRule))->toBeNull();

    $ignoreProperty = new ReflectionProperty(Unique::class, 'ignore');
    $ignoreProperty->setAccessible(true);
    expect($ignoreProperty->getValue($uniqueRule))->toEqual(789); // Should ignore the ID from the route

    $queryCallbacksProperty = new ReflectionProperty(Unique::class, 'queryCallbacks');
    $queryCallbacksProperty->setAccessible(true);
    $queryCallbacks = $queryCallbacksProperty->getValue($uniqueRule);

    expect($queryCallbacks)->toHaveCount(2); // Still two where clauses

    // Mock a query builder to verify the 'where' clauses for PUT
    $mockQueryBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
    $mockQueryBuilder->shouldReceive('where')
        ->with('type', TaxType::TYPE_GENERAL)
        ->once()
        ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')
        ->with('company_id', 456) // Expected company ID
        ->once()
        ->andReturnSelf();

    // Execute the callbacks with the mock builder
    foreach ($queryCallbacks as $callback) {
        $callback($mockQueryBuilder);
    }
});

test('getTaxTypePayload returns correct merged data with complete validated input', function () {
    /** @var TaxTypeRequest|Mockery\MockInterface $request */
    $request = Mockery::mock(TaxTypeRequest::class)->makePartial();

    $validatedData = [
        'name' => 'VAT',
        'percent' => 20,
        'description' => 'Value Added Tax',
        'compound_tax' => true,
        'collective_tax' => false,
    ];
    $companyId = 999;

    $request->shouldReceive('validated')
        ->andReturn($validatedData)
        ->atLeast()->once();

    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->atLeast()->once();

    $payload = $request->getTaxTypePayload();

    expect($payload)->toBeArray()
        ->and($payload)->toEqual(
            array_merge(
                $validatedData,
                [
                    'company_id' => $companyId,
                    'type' => TaxType::TYPE_GENERAL
                ]
            )
        );
});

test('getTaxTypePayload handles empty validated data', function () {
    /** @var TaxTypeRequest|Mockery\MockInterface $request */
    $request = Mockery::mock(TaxTypeRequest::class)->makePartial();

    $companyId = 101;

    $request->shouldReceive('validated')
        ->andReturn([]) // Empty validated data
        ->atLeast()->once();

    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->atLeast()->once();

    $payload = $request->getTaxTypePayload();

    expect($payload)->toBeArray()
        ->and($payload)->toEqual(
            [
                'company_id' => $companyId,
                'type' => TaxType::TYPE_GENERAL
            ]
        );
});

test('getTaxTypePayload handles null company header', function () {
    /** @var TaxTypeRequest|Mockery\MockInterface $request */
    $request = Mockery::mock(TaxTypeRequest::class)->makePartial();

    $validatedData = [
        'name' => 'GST',
        'percent' => 10,
    ];
    $companyId = null; // No company ID in header

    $request->shouldReceive('validated')
        ->andReturn($validatedData)
        ->atLeast()->once();

    $request->shouldReceive('header')
        ->with('company')
        ->andReturn($companyId)
        ->atLeast()->once();

    $payload = $request->getTaxTypePayload();

    expect($payload)->toBeArray()
        ->and($payload)->toEqual(
            array_merge(
                $validatedData,
                [
                    'company_id' => $companyId,
                    'type' => TaxType::TYPE_GENERAL
                ]
            )
        );
});

test('getTaxTypePayload handles both empty validated data and null company header', function () {
    /** @var TaxTypeRequest|Mockery\MockInterface $request */
    $request = Mockery::mock(TaxTypeRequest::class)->makePartial();

    $request->shouldReceive('validated')
        ->andReturn([])
        ->atLeast()->once();

    $request->shouldReceive('header')
        ->with('company')
        ->andReturn(null)
        ->atLeast()->once();

    $payload = $request->getTaxTypePayload();

    expect($payload)->toBeArray()
        ->and($payload)->toEqual(
            [
                'company_id' => null,
                'type' => TaxType::TYPE_GENERAL
            ]
        );
});


afterEach(function () {
    Mockery::close();
});