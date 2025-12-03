<?php

use Crater\Http\Resources\AddressResource;
use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

beforeEach(function () {
    // Clear Mockery mocks before each test to prevent bleed-through
    Mockery::close();
});

test('toArray transforms company with all direct properties and no relationships', function () {
    $company = (object) [
        'id' => 1,
        'name' => 'Test Company',
        'logo' => 'company-logo.png',
        'logo_path' => '/storage/logos/company-logo.png',
        'unique_hash' => 'abcd123',
        'owner_id' => 10,
        'slug' => 'test-company',
        'address' => null, // Ensure direct access doesn't return anything
        'roles' => new Collection(),
    ];

    // Mock the address() relationship to indicate it does not exist
    $companyMock = Mockery::mock($company);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn(
        Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock()
    );

    // Mock the static call to RoleResource::collection to return an empty collection
    Mockery::mock('alias:' . RoleResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with($company->roles)
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)->shouldReceive('toArray')->andReturn([])->getMock());

    $resource = new CompanyResource($companyMock);
    $request = Mockery::mock(Request::class);

    $transformed = $resource->toArray($request);

    expect($transformed)->toMatchArray([
        'id' => 1,
        'name' => 'Test Company',
        'logo' => 'company-logo.png',
        'logo_path' => '/storage/logos/company-logo.png',
        'unique_hash' => 'abcd123',
        'owner_id' => 10,
        'slug' => 'test-company',
    ])
    ->not->toHaveKey('address') // Should not have address key when not present
    ->and($transformed['roles'])->toBeArray()->toBeEmpty(); // roles should be an empty array
});

test('toArray transforms company with an existing address relationship', function () {
    $addressModel = (object) ['id' => 50, 'street' => '123 Main St']; // Simple mock address model

    $company = (object) [
        'id' => 2,
        'name' => 'Company With Address',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'efgh456',
        'owner_id' => 20,
        'slug' => 'company-address',
        'address' => $addressModel, // Direct property access
        'roles' => new Collection(),
    ];

    // Mock the address() relationship to indicate it exists
    $companyMock = Mockery::mock($company);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn(
        Mockery::mock()->shouldReceive('exists')->andReturn(true)->getMock()
    );

    // Mock the static call to RoleResource::collection to return an empty collection
    Mockery::mock('alias:' . RoleResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with($company->roles)
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)->shouldReceive('toArray')->andReturn([])->getMock());

    $resource = new CompanyResource($companyMock);
    $request = Mockery::mock(Request::class);

    $transformed = $resource->toArray($request);

    expect($transformed)->toMatchArray([
        'id' => 2,
        'name' => 'Company With Address',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'efgh456',
        'owner_id' => 20,
        'slug' => 'company-address',
    ])
    ->toHaveKey('address')
    ->and($transformed['address'])->toBeInstanceOf(AddressResource::class)
    ->and($transformed['address']->resource)->toBe($addressModel); // Ensure correct model is wrapped
});

test('toArray transforms company with an empty roles collection', function () {
    $company = (object) [
        'id' => 3,
        'name' => 'No Roles Company',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'ijkl789',
        'owner_id' => 30,
        'slug' => 'no-roles',
        'address' => null,
        'roles' => new Collection(), // Empty collection
    ];

    // Mock the address() relationship to indicate it does not exist
    $companyMock = Mockery::mock($company);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn(
        Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock()
    );

    // Mock the static call to RoleResource::collection
    Mockery::mock('alias:' . RoleResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($collection) use ($company) {
            return $collection->is($company->roles);
        })
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)->shouldReceive('toArray')->andReturn([])->getMock());

    $resource = new CompanyResource($companyMock);
    $request = Mockery::mock(Request::class);

    $transformed = $resource->toArray($request);

    expect($transformed['roles'])->toBeArray()->toBeEmpty();
});

test('toArray transforms company with a populated roles collection', function () {
    $role1 = (object) ['id' => 1, 'name' => 'Admin'];
    $role2 = (object) ['id' => 2, 'name' => 'Editor'];
    $rolesCollection = new Collection([$role1, $role2]);

    $company = (object) [
        'id' => 4,
        'name' => 'Roles Company',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'mnop012',
        'owner_id' => 40,
        'slug' => 'roles-company',
        'address' => null,
        'roles' => $rolesCollection, // Populated collection
    ];

    // Mock the address() relationship to indicate it does not exist
    $companyMock = Mockery::mock($company);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn(
        Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock()
    );

    // Mock the static call to RoleResource::collection
    $mockedRolesArray = [['id' => 1, 'name' => 'Admin'], ['id' => 2, 'name' => 'Editor']];
    Mockery::mock('alias:' . RoleResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($collection) use ($company) {
            return $collection->is($company->roles);
        })
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)->shouldReceive('toArray')->andReturn($mockedRolesArray)->getMock());

    $resource = new CompanyResource($companyMock);
    $request = Mockery::mock(Request::class);

    $transformed = $resource->toArray($request);

    expect($transformed['roles'])->toBeArray()->toEqual($mockedRolesArray);
});

test('toArray handles a fully populated company model', function () {
    $addressModel = (object) ['id' => 100, 'street' => '456 Oak Ave'];
    $role1 = (object) ['id' => 1, 'name' => 'Super Admin'];
    $role2 = (object) ['id' => 2, 'name' => 'Viewer'];
    $rolesCollection = new Collection([$role1, $role2]);

    $company = (object) [
        'id' => 5,
        'name' => 'Full Featured Company',
        'logo' => 'full-logo.png',
        'logo_path' => '/storage/full-logo.png',
        'unique_hash' => 'qrst345',
        'owner_id' => 50,
        'slug' => 'full-company',
        'address' => $addressModel,
        'roles' => $rolesCollection,
    ];

    // Mock the address() relationship to indicate it exists
    $companyMock = Mockery::mock($company);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn(
        Mockery::mock()->shouldReceive('exists')->andReturn(true)->getMock()
    );

    // Mock the static call to RoleResource::collection
    $mockedRolesArray = [['id' => 1, 'name' => 'Super Admin'], ['id' => 2, 'name' => 'Viewer']];
    Mockery::mock('alias:' . RoleResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($collection) use ($company) {
            return $collection->is($company->roles);
        })
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)->shouldReceive('toArray')->andReturn($mockedRolesArray)->getMock());

    $resource = new CompanyResource($companyMock);
    $request = Mockery::mock(Request::class);

    $transformed = $resource->toArray($request);

    expect($transformed)->toMatchArray([
        'id' => 5,
        'name' => 'Full Featured Company',
        'logo' => 'full-logo.png',
        'logo_path' => '/storage/full-logo.png',
        'unique_hash' => 'qrst345',
        'owner_id' => 50,
        'slug' => 'full-company',
    ])
    ->toHaveKey('address')
    ->and($transformed['address'])->toBeInstanceOf(AddressResource::class)
    ->and($transformed['address']->resource)->toBe($addressModel)
    ->and($transformed['roles'])->toEqual($mockedRolesArray);
});

test('toArray handles company with null optional fields', function () {
    $company = (object) [
        'id' => 6,
        'name' => 'Company No Optional Fields',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'uvwx678',
        'owner_id' => null, // Test null owner_id
        'slug' => 'no-optional',
        'address' => null,
        'roles' => new Collection(),
    ];

    $companyMock = Mockery::mock($company);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn(
        Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock()
    );

    Mockery::mock('alias:' . RoleResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($collection) use ($company) {
            return $collection->is($company->roles);
        })
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)->shouldReceive('toArray')->andReturn([])->getMock());

    $resource = new CompanyResource($companyMock);
    $request = Mockery::mock(Request::class);

    $transformed = $resource->toArray($request);

    expect($transformed)->toMatchArray([
        'id' => 6,
        'name' => 'Company No Optional Fields',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'uvwx678',
        'owner_id' => null,
        'slug' => 'no-optional',
    ])
    ->not->toHaveKey('address')
    ->and($transformed['roles'])->toBeArray()->toBeEmpty();
});

test('toArray does not include address if relationship does not exist even if address property is set', function () {
    $company = (object) [
        'id' => 7,
        'name' => 'Company No Address Exists',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'yyyyy',
        'owner_id' => 70,
        'slug' => 'no-address-exists',
        'address' => (object) ['id' => 99, 'street' => 'Should Not Be Used'], // Direct property might exist, but relationship is key
        'roles' => new Collection(),
    ];

    $companyMock = Mockery::mock($company);
    // Crucially, this returns false for exists()
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn(
        Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock()
    );

    Mockery::mock('alias:' . RoleResource::class)
        ->shouldReceive('collection')
        ->once()
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)->shouldReceive('toArray')->andReturn([])->getMock());

    $resource = new CompanyResource($companyMock);
    $request = Mockery::mock(Request::class);

    $transformed = $resource->toArray($request);

    expect($transformed)->not->toHaveKey('address');
});

 


afterEach(function () {
    Mockery::close();
});
