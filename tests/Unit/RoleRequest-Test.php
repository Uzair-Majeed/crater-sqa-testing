<?php

use Crater\Http\Requests\RoleRequest;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rules\Unique;
use Mockery\MockInterface;

// Ensure Mockery is closed after each test to prevent test pollution

test('authorize method always returns true', function () {
    $request = new RoleRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct validation rules for POST request', function () {
    $companyId = 'company-abc-123';

    /** @var RoleRequest|MockInterface $request */
    $request = Mockery::mock(RoleRequest::class)->makePartial();
    $request->shouldReceive('getMethod')->andReturn('POST')->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->shouldNotReceive('route'); // 'route' should not be called for POST method in rules

    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys(['name', 'abilities', 'abilities.*']);

    $nameRules = $rules['name'];
    expect($nameRules)->toContain('required', 'string');

    // Find the unique rule and assert its properties using reflection
    $uniqueRule = collect($nameRules)->first(fn ($rule) => $rule instanceof Unique);
    expect($uniqueRule)->not->toBeNull('Unique rule not found for name field.');

    $reflection = new ReflectionClass($uniqueRule);

    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    expect($tableProperty->getValue($uniqueRule))->toBe('roles');

    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);
    // FIX: Unique rule's 'wheres' array includes 'operator' and 'boolean' keys.
    // For POST, only the 'scope' where clause should be present.
    expect($wheresProperty->getValue($uniqueRule))->toEqual([
        ['column' => 'scope', 'operator' => '=', 'value' => $companyId, 'boolean' => 'AND']
    ]);

    // Removed the following lines as $ignoreIdProperty and $ignoreColumnProperty
    // do not exist on Illuminate\Validation\Rules\Unique rule in recent Laravel versions.
    // The 'ignore' logic is implemented via 'whereNot' (or 'boolean' => 'AND NOT').
    // $ignoreIdProperty = $reflection->getProperty('ignoreId');
    // $ignoreIdProperty->setAccessible(true);
    // expect($ignoreIdProperty->getValue($uniqueRule))->toBeNull();
    // $ignoreColumnProperty = $reflection->getProperty('ignoreColumn');
    // $ignoreColumnProperty->setAccessible(true);
    // expect($ignoreColumnProperty->getValue($uniqueRule))->toBeNull();

    // Assert 'abilities' rules
    expect($rules['abilities'])->toContain('required');
    expect($rules['abilities.*'])->toContain('required');
});

test('rules method returns correct validation rules for PUT request', function () {
    $companyId = 'company-xyz-456';
    $roleId = 123;

    /** @var Route|MockInterface $mockRoute */
    $mockRoute = Mockery::mock(Route::class);
    // FIX: Mocking dynamic property access for $route->id, as the Route object likely accesses a parameter.
    $mockRoute->shouldReceive('__get')->with('id')->andReturn($roleId)->once();

    /** @var RoleRequest|MockInterface $request */
    $request = Mockery::mock(RoleRequest::class)->makePartial();
    $request->shouldReceive('getMethod')->andReturn('PUT')->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->shouldReceive('route')->with('role')->andReturn($mockRoute)->once();

    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys(['name', 'abilities', 'abilities.*']);

    $nameRules = $rules['name'];
    expect($nameRules)->toContain('required', 'string');

    // Find the unique rule and assert its properties using reflection
    $uniqueRule = collect($nameRules)->first(fn ($rule) => $rule instanceof Unique);
    expect($uniqueRule)->not->toBeNull('Unique rule not found for name field.');

    $reflection = new ReflectionClass($uniqueRule);

    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    expect($tableProperty->getValue($uniqueRule))->toBe('roles');

    // Removed the following lines as $ignoreIdProperty and $ignoreColumnProperty
    // do not exist on Illuminate\Validation\Rules\Unique rule.
    // $ignoreIdProperty = $reflection->getProperty('ignoreId');
    // $ignoreIdProperty->setAccessible(true);
    // expect($ignoreIdProperty->getValue($uniqueRule))->toBe($roleId);
    // $ignoreColumnProperty = $reflection->getProperty('ignoreColumn');
    // $ignoreColumnProperty->setAccessible(true);
    // expect($ignoreColumnProperty->getValue($uniqueRule))->toBe('id');

    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);
    // FIX: Unique rule's 'wheres' array includes 'operator' and 'boolean' keys.
    // For PUT, it should include both the 'scope' where clause and the 'id' ignore clause.
    // Assuming 'where' is called before 'ignore' in the RoleRequest, hence the order.
    expect($wheresProperty->getValue($uniqueRule))->toEqual([
        ['column' => 'scope', 'operator' => '=', 'value' => $companyId, 'boolean' => 'AND'],
        ['column' => 'id', 'operator' => '=', 'value' => $roleId, 'boolean' => 'AND NOT']
    ]);

    // Assert 'abilities' rules
    expect($rules['abilities'])->toContain('required');
    expect($rules['abilities.*'])->toContain('required');
});

test('rules method handles missing company header for POST request', function () {
    /** @var RoleRequest|MockInterface $request */
    $request = Mockery::mock(RoleRequest::class)->makePartial();
    $request->shouldReceive('getMethod')->andReturn('POST')->once();
    $request->shouldReceive('header')->with('company')->andReturn(null)->once();

    $rules = $request->rules();

    $nameRules = $rules['name'];
    $uniqueRule = collect($nameRules)->first(fn ($rule) => $rule instanceof Unique);
    expect($uniqueRule)->not->toBeNull('Unique rule not found for name field.');

    $reflection = new ReflectionClass($uniqueRule);
    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);
    // FIX: When a null value is passed to a 'where' clause, Laravel's Unique rule often
    // converts it to the string 'NULL' for database query compatibility.
    // Also, include 'operator' and 'boolean' keys.
    expect($wheresProperty->getValue($uniqueRule))->toEqual([
        ['column' => 'scope', 'operator' => '=', 'value' => 'NULL', 'boolean' => 'AND']
    ]);
});

test('rules method handles missing company header for PUT request', function () {
    $roleId = 456;
    /** @var Route|MockInterface $mockRoute */
    $mockRoute = Mockery::mock(Route::class);
    $mockRoute->shouldReceive('__get')->with('id')->andReturn($roleId)->once();

    /** @var RoleRequest|MockInterface $request */
    $request = Mockery::mock(RoleRequest::class)->makePartial();
    $request->shouldReceive('getMethod')->andReturn('PUT')->once();
    $request->shouldReceive('header')->with('company')->andReturn(null)->once();
    $request->shouldReceive('route')->with('role')->andReturn($mockRoute)->once();

    $rules = $request->rules();

    $nameRules = $rules['name'];
    $uniqueRule = collect($nameRules)->first(fn ($rule) => $rule instanceof Unique);
    expect($uniqueRule)->not->toBeNull('Unique rule not found for name field.');

    $reflection = new ReflectionClass($uniqueRule);
    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);
    // FIX: Expect 'value' to be 'NULL' string for scope, and include 'operator' and 'boolean' keys for both clauses.
    // Assuming 'where' is called before 'ignore' in the RoleRequest.
    expect($wheresProperty->getValue($uniqueRule))->toEqual([
        ['column' => 'scope', 'operator' => '=', 'value' => 'NULL', 'boolean' => 'AND'],
        ['column' => 'id', 'operator' => '=', 'value' => $roleId, 'boolean' => 'AND NOT']
    ]);
});

test('getRolePayload method returns correct payload by excluding abilities and merging scope', function () {
    $companyId = 'company-test-456';
    $requestData = [
        'name' => 'Admin Role',
        'display_name' => 'Administrator',
        'abilities' => ['view', 'create'],
        'description' => 'A role for administrators',
    ];
    $expectedPayload = [
        'name' => 'Admin Role',
        'display_name' => 'Administrator',
        'description' => 'A role for administrators',
        'scope' => $companyId,
    ];

    /** @var RoleRequest|MockInterface $request */
    $request = Mockery::mock(RoleRequest::class)->makePartial();
    // The original test setup correctly mocks 'except' directly to control its return value.
    $request->shouldReceive('except')->with('abilities')->andReturn(collect($requestData)->except('abilities')->toArray())->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    $payload = $request->getRolePayload();

    expect($payload)->toBeArray()
        ->and($payload)->toEqual($expectedPayload)
        ->and($payload)->not->toHaveKey('abilities'); // Ensure abilities are explicitly excluded
});

test('getRolePayload method handles missing company header gracefully', function () {
    // Test case for when 'company' header is null/missing for payload generation
    $requestData = [
        'name' => 'Test Role',
        'abilities' => ['view'],
    ];
    $expectedPayload = [
        'name' => 'Test Role',
        'scope' => null, // Expected null scope when header is missing
    ];

    /** @var RoleRequest|MockInterface $request */
    $request = Mockery::mock(RoleRequest::class)->makePartial();
    $request->shouldReceive('except')->with('abilities')->andReturn(collect($requestData)->except('abilities')->toArray())->once();
    $request->shouldReceive('header')->with('company')->andReturn(null)->once();

    $payload = $request->getRolePayload();

    expect($payload)->toBeArray()
        ->and($payload)->toEqual($expectedPayload);
});

test('getRolePayload method handles empty request data', function () {
    $companyId = 'company-empty-123';
    $requestData = ['abilities' => ['view']]; // Only abilities, so except('abilities') will result in an empty array for data
    $expectedPayload = ['scope' => $companyId];

    /** @var RoleRequest|MockInterface $request */
    $request = Mockery::mock(RoleRequest::class)->makePartial();
    $request->shouldReceive('except')->with('abilities')->andReturn(collect($requestData)->except('abilities')->toArray())->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    $payload = $request->getRolePayload();

    expect($payload)->toBeArray()
        ->and($payload)->toEqual($expectedPayload);
});


afterEach(function () {
    Mockery::close();
});