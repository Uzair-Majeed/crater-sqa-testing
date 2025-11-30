<?php

use Crater\Http\Requests\RoleRequest;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rules\Unique;
uses(\Mockery::class);

// Ensure Mockery is closed after each test to prevent test pollution
afterEach(function () {
    Mockery::close();
});

test('authorize method always returns true', function () {
    $request = new RoleRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct validation rules for POST request', function () {
    $companyId = 'company-abc-123';

    // Mock the RoleRequest instance to control its internal methods for a POST request
    $request = Mockery::mock(RoleRequest::class)->makePartial();
    $request->shouldReceive('getMethod')->andReturn('POST')->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->shouldNotReceive('route'); // 'route' should not be called for POST

    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys(['name', 'abilities', 'abilities.*']);

    // Assert 'name' rule
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
    expect($wheresProperty->getValue($uniqueRule))->toEqual([
        ['column' => 'scope', 'value' => $companyId]
    ]);

    // Ensure ignore properties are not set for POST
    $ignoreIdProperty = $reflection->getProperty('ignoreId');
    $ignoreIdProperty->setAccessible(true);
    expect($ignoreIdProperty->getValue($uniqueRule))->toBeNull();

    $ignoreColumnProperty = $reflection->getProperty('ignoreColumn');
    $ignoreColumnProperty->setAccessible(true);
    expect($ignoreColumnProperty->getValue($uniqueRule))->toBeNull();

    // Assert 'abilities' rules
    expect($rules['abilities'])->toContain('required');
    expect($rules['abilities.*'])->toContain('required');
});

test('rules method returns correct validation rules for PUT request', function () {
    $companyId = 'company-xyz-456';
    $roleId = 123;

    // Mock a Route object for the route('role') method
    $mockRoute = Mockery::mock(Route::class);
    $mockRoute->id = $roleId; // Simulate dynamic property access on the Route object

    // Mock the RoleRequest instance to control its internal methods for a PUT request
    $request = Mockery::mock(RoleRequest::class)->makePartial();
    $request->shouldReceive('getMethod')->andReturn('PUT')->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->shouldReceive('route')->with('role')->andReturn($mockRoute)->once();

    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys(['name', 'abilities', 'abilities.*']);

    // Assert 'name' rule
    $nameRules = $rules['name'];
    expect($nameRules)->toContain('required', 'string');

    // Find the unique rule and assert its properties using reflection
    $uniqueRule = collect($nameRules)->first(fn ($rule) => $rule instanceof Unique);
    expect($uniqueRule)->not->toBeNull('Unique rule not found for name field.');

    $reflection = new ReflectionClass($uniqueRule);

    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    expect($tableProperty->getValue($uniqueRule))->toBe('roles');

    $ignoreIdProperty = $reflection->getProperty('ignoreId');
    $ignoreIdProperty->setAccessible(true);
    expect($ignoreIdProperty->getValue($uniqueRule))->toBe($roleId);

    $ignoreColumnProperty = $reflection->getProperty('ignoreColumn');
    $ignoreColumnProperty->setAccessible(true);
    expect($ignoreColumnProperty->getValue($uniqueRule))->toBe('id');

    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);
    expect($wheresProperty->getValue($uniqueRule))->toEqual([
        ['column' => 'scope', 'value' => $companyId]
    ]);

    // Assert 'abilities' rules
    expect($rules['abilities'])->toContain('required');
    expect($rules['abilities.*'])->toContain('required');
});

test('rules method handles missing company header for POST request', function () {
    // Test case for when 'company' header is null/missing for a POST request
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
    expect($wheresProperty->getValue($uniqueRule))->toEqual([
        ['column' => 'scope', 'value' => null] // Expect scope to be null
    ]);
});

test('rules method handles missing company header for PUT request', function () {
    // Test case for when 'company' header is null/missing for a PUT request
    $roleId = 456;
    $mockRoute = Mockery::mock(Route::class);
    $mockRoute->id = $roleId;

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
    expect($wheresProperty->getValue($uniqueRule))->toEqual([
        ['column' => 'scope', 'value' => null] // Expect scope to be null
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

    // Mock the RoleRequest instance
    $request = Mockery::mock(RoleRequest::class)->makePartial();
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

    $request = Mockery::mock(RoleRequest::class)->makePartial();
    $request->shouldReceive('except')->with('abilities')->andReturn(collect($requestData)->except('abilities')->toArray())->once();
    $request->shouldReceive('header')->with('company')->andReturn(null)->once();

    $payload = $request->getRolePayload();

    expect($payload)->toBeArray()
        ->and($payload)->toEqual($expectedPayload);
});

test('getRolePayload method handles empty request data', function () {
    $companyId = 'company-empty-123';
    $requestData = ['abilities' => ['view']]; // Only abilities, so except('abilities') will be empty
    $expectedPayload = ['scope' => $companyId];

    $request = Mockery::mock(RoleRequest::class)->makePartial();
    $request->shouldReceive('except')->with('abilities')->andReturn(collect($requestData)->except('abilities')->toArray())->once();
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    $payload = $request->getRolePayload();

    expect($payload)->toBeArray()
        ->and($payload)->toEqual($expectedPayload);
});
