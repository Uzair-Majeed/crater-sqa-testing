<?php

use Illuminate\Validation\Rules\Unique;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration; // Required for Mockery and PHPUnit integration (Pest often includes this implicitly)

// No global beforeEach for Mockery tear-down defined here.
// The explicit `afterEach` hook at the bottom handles Mockery cleanup.
// `uses()->in(__DIR__)` is typically for global setups like `TestCase.php`
// and not strictly needed for basic test files unless it sets up a test suite.

// Test for authorize() method
test('authorize method always returns true', function () {
    $request = new \Crater\Http\Requests\UnitRequest();
    expect($request->authorize())->toBeTrue();
});

// Test for rules() method - POST request (create scenario)
test('rules method returns correct validation rules for POST request', function () {
    $companyId = 1;

    // Create a partial mock of UnitRequest to control FormRequest methods
    $request = Mockery::mock(\Crater\Http\Requests\UnitRequest::class)->makePartial();
    $request->shouldReceive('getMethod')->andReturn('POST');
    $request->shouldReceive('header')->with('company')->andReturn($companyId);

    $rules = $request->rules();

    // Assert that 'name' rule is present and is an array
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('name');

    $nameRules = $rules['name'];
    expect($nameRules)->toBeArray()
        ->and($nameRules)->toContain('required'); // Should contain 'required'

    // Find and assert properties of the Unique rule
    $uniqueRule = collect($nameRules)->first(fn($rule) => $rule instanceof Unique);
    expect($uniqueRule)->not->toBeNull('A Unique rule should be present for the name field.');

    // Use reflection to access protected 'table' property of the Unique rule
    $reflectionPropertyTable = new \ReflectionProperty($uniqueRule, 'table');
    $reflectionPropertyTable->setAccessible(true);
    expect($reflectionPropertyTable->getValue($uniqueRule))->toBe('units');

    // Use reflection to access protected 'column' property of the Unique rule
    $reflectionPropertyColumn = new \ReflectionProperty($uniqueRule, 'column');
    $reflectionPropertyColumn->setAccessible(true);
    expect($reflectionPropertyColumn->getValue($uniqueRule))->toBe('name');

    // Use reflection to access protected 'extra' property (where clauses are stored here)
    $reflectionPropertyExtra = new \ReflectionProperty($uniqueRule, 'extra'); // Changed from 'where' to 'extra'
    $reflectionPropertyExtra->setAccessible(true);
    $extraClauses = $reflectionPropertyExtra->getValue($uniqueRule);

    expect($extraClauses)->toBeArray()
        ->and($extraClauses)->toHaveCount(1); // Expect one 'company_id' clause

    // Assert the structure of the where clause for company_id
    $foundCompanyIdClause = collect($extraClauses)->first(function ($clause) use ($companyId) {
        return is_array($clause) && count($clause) === 3
               && $clause[0] === 'company_id'
               && $clause[1] === '='
               && $clause[2] === $companyId;
    });
    expect($foundCompanyIdClause)->not->toBeNull('Expected a "company_id" where clause in extraClauses.');

    // Ensure 'ignore' is not set for POST requests
    $reflectionPropertyIgnore = new \ReflectionProperty($uniqueRule, 'ignore');
    $reflectionPropertyIgnore->setAccessible(true);
    $ignoreValue = $reflectionPropertyIgnore->getValue($uniqueRule);
    expect($ignoreValue)->toBeNull();
});

// Test for rules() method - PUT request (update scenario)
test('rules method returns correct validation rules for PUT request', function () {
    $companyId = 2;
    $unitId = 123; // The ID of the unit being updated

    // Create a partial mock of UnitRequest
    $request = Mockery::mock(\Crater\Http\Requests\UnitRequest::class)->makePartial();
    $request->shouldReceive('getMethod')->andReturn('PUT');
    $request->shouldReceive('header')->with('company')->andReturn($companyId);
    $request->shouldReceive('route')->with('unit')->andReturn($unitId); // Mock route parameter

    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('name');

    $nameRules = $rules['name'];
    expect($nameRules)->toBeArray()
        ->and($nameRules)->toContain('required');

    // Find and assert properties of the Unique rule
    $uniqueRule = collect($nameRules)->first(fn($rule) => $rule instanceof Unique);
    expect($uniqueRule)->not->toBeNull('A Unique rule should be present for the name field.');

    // Use reflection to access protected 'table' property of the Unique rule
    $reflectionPropertyTable = new \ReflectionProperty($uniqueRule, 'table');
    $reflectionPropertyTable->setAccessible(true);
    expect($reflectionPropertyTable->getValue($uniqueRule))->toBe('units');

    // Use reflection to access protected 'column' property of the Unique rule
    $reflectionPropertyColumn = new \ReflectionProperty($uniqueRule, 'column');
    $reflectionPropertyColumn->setAccessible(true);
    expect($reflectionPropertyColumn->getValue($uniqueRule))->toBe('name');

    // Check 'extra' clauses
    $reflectionPropertyExtra = new \ReflectionProperty($uniqueRule, 'extra'); // Changed from 'where' to 'extra'
    $reflectionPropertyExtra->setAccessible(true);
    $extraClauses = $reflectionPropertyExtra->getValue($uniqueRule);

    expect($extraClauses)->toBeArray()
        ->and($extraClauses)->toHaveCount(1); // Expect one 'company_id' clause

    // Assert the structure of the where clause for company_id
    $foundCompanyIdClause = collect($extraClauses)->first(function ($clause) use ($companyId) {
        return is_array($clause) && count($clause) === 3
               && $clause[0] === 'company_id'
               && $clause[1] === '='
               && $clause[2] === $companyId;
    });
    expect($foundCompanyIdClause)->not->toBeNull('Expected a "company_id" where clause in extraClauses.');

    // Check 'ignore' for PUT requests
    $reflectionPropertyIgnore = new \ReflectionProperty($uniqueRule, 'ignore');
    $reflectionPropertyIgnore->setAccessible(true);
    $ignoreValue = $reflectionPropertyIgnore->getValue($uniqueRule);
    expect($ignoreValue)->toBe($unitId);

    // Check 'idColumn' for PUT requests
    $reflectionPropertyIdColumn = new \ReflectionProperty($uniqueRule, 'idColumn');
    $reflectionPropertyIdColumn->setAccessible(true);
    $idColumnValue = $reflectionPropertyIdColumn->getValue($uniqueRule);
    expect($idColumnValue)->toBe('id');
});

// Edge case for rules: company header is null (should still apply to where clause)
test('rules method handles null company header for POST request gracefully', function () {
    $companyId = null;

    $request = Mockery::mock(\Crater\Http\Requests\UnitRequest::class)->makePartial();
    $request->shouldReceive('getMethod')->andReturn('POST');
    $request->shouldReceive('header')->with('company')->andReturn($companyId);

    $rules = $request->rules();
    $nameRules = $rules['name'];
    $uniqueRule = collect($nameRules)->first(fn($rule) => $rule instanceof Unique);

    expect($uniqueRule)->not->toBeNull('A Unique rule should be present even with null company header.');

    // Check 'extra' clauses
    $reflectionPropertyExtra = new \ReflectionProperty($uniqueRule, 'extra'); // Changed from 'where' to 'extra'
    $reflectionPropertyExtra->setAccessible(true);
    $extraClauses = $reflectionPropertyExtra->getValue($uniqueRule);

    expect($extraClauses)->toBeArray()
        ->and($extraClauses)->toHaveCount(1); // Expect one 'company_id' clause

    // Assert the structure of the where clause for company_id (should be null)
    $foundCompanyIdClause = collect($extraClauses)->first(function ($clause) use ($companyId) {
        return is_array($clause) && count($clause) === 3
               && $clause[0] === 'company_id'
               && $clause[1] === '='
               && $clause[2] === $companyId; // Assert it's passed as null
    });
    expect($foundCompanyIdClause)->not->toBeNull('Expected a "company_id" where clause, even if companyId is null.');
});

// Edge case for rules: unit route parameter is null for PUT
test('rules method handles null unit ID for PUT request gracefully', function () {
    $companyId = 5;
    $unitId = null; // Unit ID is null

    $request = Mockery::mock(\Crater\Http\Requests\UnitRequest::class)->makePartial();
    $request->shouldReceive('getMethod')->andReturn('PUT');
    $request->shouldReceive('header')->with('company')->andReturn($companyId);
    $request->shouldReceive('route')->with('unit')->andReturn($unitId);

    $rules = $request->rules();
    $nameRules = $rules['name'];
    $uniqueRule = collect($nameRules)->first(fn($rule) => $rule instanceof Unique);

    expect($uniqueRule)->not->toBeNull('A Unique rule should be present even with null unit ID.');

    $reflectionPropertyIgnore = new \ReflectionProperty($uniqueRule, 'ignore');
    $reflectionPropertyIgnore->setAccessible(true);
    $ignoreValue = $reflectionPropertyIgnore->getValue($uniqueRule);
    expect($ignoreValue)->toBe($unitId); // Should be null
});

// Test for getUnitPayload() method - happy path
test('getUnitPayload returns correct data merged with company ID', function () {
    $validatedData = ['name' => 'Test Unit Name', 'status' => 'active'];
    $companyId = 10;

    // Create a partial mock of UnitRequest
    $request = Mockery::mock(\Crater\Http\Requests\UnitRequest::class)->makePartial();
    $request->shouldReceive('validated')->andReturn($validatedData);
    $request->shouldReceive('header')->with('company')->andReturn($companyId);

    $payload = $request->getUnitPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('name', 'Test Unit Name')
        ->and($payload)->toHaveKey('status', 'active')
        ->and($payload)->toHaveKey('company_id', $companyId);
});

// Edge case for getUnitPayload: validated() returns empty array
test('getUnitPayload handles empty validated data correctly', function () {
    $validatedData = [];
    $companyId = 11;

    $request = Mockery::mock(\Crater\Http\Requests\UnitRequest::class)->makePartial();
    $request->shouldReceive('validated')->andReturn($validatedData);
    $request->shouldReceive('header')->with('company')->andReturn($companyId);

    $payload = $request->getUnitPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveCount(1) // Only 'company_id' should be present
        ->and($payload)->toHaveKey('company_id', $companyId);
});

// Edge case for getUnitPayload: company header is null
test('getUnitPayload handles null company header gracefully', function () {
    $validatedData = ['name' => 'Unit Without Company'];
    $companyId = null;

    $request = Mockery::mock(\Crater\Http\Requests\UnitRequest::class)->makePartial();
    $request->shouldReceive('validated')->andReturn($validatedData);
    $request->shouldReceive('header')->with('company')->andReturn($companyId);

    $payload = $request->getUnitPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('name', 'Unit Without Company')
        ->and($payload)->toHaveKey('company_id', $companyId); // Should be null
});

// Test for getUnitPayload: validated data contains company_id already (merge behavior)
test('getUnitPayload overwrites company_id if present in validated data', function () {
    $validatedData = ['name' => 'Unit with old company', 'company_id' => 99];
    $companyId = 12; // This should be the final company_id

    $request = Mockery::mock(\Crater\Http\Requests\UnitRequest::class)->makePartial();
    $request->shouldReceive('validated')->andReturn($validatedData);
    $request->shouldReceive('header')->with('company')->andReturn($companyId);

    $payload = $request->getUnitPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('name', 'Unit with old company')
        ->and($payload)->toHaveKey('company_id', $companyId); // Should be the one from header
});


afterEach(function () {
    Mockery::close();
});