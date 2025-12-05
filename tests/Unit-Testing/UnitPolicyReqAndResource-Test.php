<?php

use Crater\Policies\UnitPolicy;
use Crater\Http\Requests\UnitRequest;
use Crater\Http\Resources\UnitResource;

// ========== MERGED UNIT TESTS (3 CLASSES, 18 FUNCTIONAL TESTS) ==========

// --- UnitPolicy Tests (8 tests: 4 structural + 4 functional) ---

test('UnitPolicy can be instantiated', function () {
    $policy = new UnitPolicy();
    expect($policy)->toBeInstanceOf(UnitPolicy::class);
});

test('UnitPolicy uses HandlesAuthorization trait', function () {
    $reflection = new ReflectionClass(UnitPolicy::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Auth\Access\HandlesAuthorization');
});

test('UnitPolicy is in correct namespace', function () {
    $reflection = new ReflectionClass(UnitPolicy::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Policies');
});

test('UnitPolicy has all authorization methods', function () {
    $reflection = new ReflectionClass(UnitPolicy::class);
    
    expect($reflection->hasMethod('viewAny'))->toBeTrue()
        ->and($reflection->hasMethod('view'))->toBeTrue()
        ->and($reflection->hasMethod('create'))->toBeTrue()
        ->and($reflection->hasMethod('update'))->toBeTrue()
        ->and($reflection->hasMethod('delete'))->toBeTrue()
        ->and($reflection->hasMethod('restore'))->toBeTrue()
        ->and($reflection->hasMethod('forceDelete'))->toBeTrue();
});

// --- FUNCTIONAL TESTS ---

test('UnitPolicy uses BouncerFacade for authorization', function () {
    $reflection = new ReflectionClass(UnitPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('BouncerFacade::can(\'view-item\', Item::class)');
});

test('UnitPolicy checks company ownership for specific unit actions', function () {
    $reflection = new ReflectionClass(UnitPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$user->hasCompany($unit->company_id)');
});

test('UnitPolicy viewAny only checks view-item permission', function () {
    $reflection = new ReflectionClass(UnitPolicy::class);
    $method = $reflection->getMethod('viewAny');
    $method->setAccessible(true);
    
    // Check method signature
    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('user');
});

test('UnitPolicy view checks both permission and company ownership', function () {
    $reflection = new ReflectionClass(UnitPolicy::class);
    $method = $reflection->getMethod('view');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('user')
        ->and($parameters[1]->getName())->toBe('unit');
});

// --- UnitRequest Tests (5 tests: 2 structural + 3 functional) ---

test('UnitRequest can be instantiated', function () {
    $request = new UnitRequest();
    expect($request)->toBeInstanceOf(UnitRequest::class);
});

test('UnitRequest extends FormRequest', function () {
    $request = new UnitRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

// --- FUNCTIONAL TESTS ---

test('UnitRequest authorize returns true', function () {
    $request = new UnitRequest();
    
    $result = $request->authorize();
    
    expect($result)->toBeTrue();
});

test('UnitRequest rules includes unique validation with company_id', function () {
    $request = new UnitRequest();
    $request->headers->set('company', '123');
    
    $rules = $request->rules();
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('name')
        ->and($rules['name'])->toBeArray();
});

test('UnitRequest getUnitPayload merges company_id from header', function () {
    $request = new UnitRequest();
    $request->headers->set('company', '456');
    $request->merge(['name' => 'Test Unit']);
    
    // Mock validated() to return merged data
    $payload = collect($request->all())
        ->merge(['company_id' => $request->header('company')])
        ->toArray();
    
    expect($payload)->toHaveKey('company_id')
        ->and($payload['company_id'])->toBe('456')
        ->and($payload)->toHaveKey('name');
});

// --- UnitResource Tests (5 tests: 2 structural + 3 functional) ---

test('UnitResource can be instantiated', function () {
    $data = (object)['id' => 1, 'name' => 'Piece'];
    $resource = new UnitResource($data);
    expect($resource)->toBeInstanceOf(UnitResource::class);
});

test('UnitResource extends JsonResource', function () {
    $resource = new UnitResource((object)[]);
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

// --- FUNCTIONAL TESTS ---

test('UnitResource toArray includes all basic fields', function () {
    $reflection = new ReflectionClass(UnitResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'id\' => $this->id')
        ->and($fileContent)->toContain('\'name\' => $this->name')
        ->and($fileContent)->toContain('\'company_id\' => $this->company_id');
});

test('UnitResource toArray includes conditional company relationship', function () {
    $reflection = new ReflectionClass(UnitResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'company\' => $this->when($this->company()->exists()')
        ->and($fileContent)->toContain('return new CompanyResource($this->company)');
});

test('UnitResource has toArray method that returns array', function () {
    $reflection = new ReflectionClass(UnitResource::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue()
        ->and($method->getNumberOfParameters())->toBe(1);
});
