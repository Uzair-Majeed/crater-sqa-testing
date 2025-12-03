<?php

use Crater\Http\Resources\AddressResource;
use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Mockery;

beforeEach(function () {
    Mockery::close();
});

/**
 * Helper to create underlying object (not a Mockery mock of stdClass)
 * with wanted attributes.
 */
function makeCompanyObject(array $attributes): object
{
    $obj = new class {
        public function address()
        {
            // dummy, will be replaced by test
            return null;
        }
    };
    foreach ($attributes as $key => $value) {
        $obj->$key = $value;
    }
    return $obj;
}

test('toArray transforms company with all direct properties and no relationships', function () {
    $company = makeCompanyObject([
        'id' => 1,
        'name' => 'Test Company',
        'logo' => 'company-logo.png',
        'logo_path' => '/storage/logos/company-logo.png',
        'unique_hash' => 'abcd123',
        'owner_id' => 10,
        'slug' => 'test-company',
        'address' => null, // Ensure direct access doesn't return anything
        'roles' => new Collection(),
    ]);

    // The CompanyResource relies on $company->id etc. - so the base must be a real object.
    // The address() relationship should indicate it does not exist.
    $companyMock = Mockery::mock($company);
    $companyMock->shouldAllowMockingProtectedMethods()
        ->shouldReceive('address')->zeroOrMoreTimes()
        ->andReturn((object)['exists' => function() { return false; }]);
    // Above: Since CompanyResource is probably calling $company->address()->exists(), we want this to return an object with ->exists() method.

    // A better and less error-prone way in Laravel is to mock `exists` as a method:
    $addressRelationMock = Mockery::mock();
    $addressRelationMock->shouldReceive('exists')->andReturn(false);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn($addressRelationMock);

    // Mock the static call to RoleResource::collection to return an empty collection
    $roleResourceMock = Mockery::mock('alias:' . RoleResource::class);
    $roleResourceMock->shouldReceive('collection')->once()
        ->with($company->roles)
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)
            ->shouldReceive('toArray')->andReturn([])->getMock());

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
    ->not->toHaveKey('address')
    ->and($transformed['roles'])->toBeArray()->toBeEmpty();
});

test('toArray transforms company with an existing address relationship', function () {
    $addressModel = new class {
        public $id = 50;
        public $street = '123 Main St';
    };

    $company = makeCompanyObject([
        'id' => 2,
        'name' => 'Company With Address',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'efgh456',
        'owner_id' => 20,
        'slug' => 'company-address',
        'address' => $addressModel,
        'roles' => new Collection(),
    ]);

    // address() indicates it exists
    $addressRelationMock = Mockery::mock();
    $addressRelationMock->shouldReceive('exists')->andReturn(true);
    $companyMock = Mockery::mock($company);
    $companyMock->shouldAllowMockingProtectedMethods()
        ->shouldReceive('address')->zeroOrMoreTimes()->andReturn($addressRelationMock);

    // RoleResource mock for empty collection
    $roleResourceMock = Mockery::mock('alias:' . RoleResource::class);
    $roleResourceMock->shouldReceive('collection')->once()
        ->with($company->roles)
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)
            ->shouldReceive('toArray')->andReturn([])->getMock());

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
    ->and($transformed['address']->resource)->toBe($addressModel);
});

test('toArray transforms company with an empty roles collection', function () {
    $company = makeCompanyObject([
        'id' => 3,
        'name' => 'No Roles Company',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'ijkl789',
        'owner_id' => 30,
        'slug' => 'no-roles',
        'address' => null,
        'roles' => new Collection(),
    ]);

    $addressRelationMock = Mockery::mock();
    $addressRelationMock->shouldReceive('exists')->andReturn(false);
    $companyMock = Mockery::mock($company);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn($addressRelationMock);

    // RoleResource mock for empty collection
    $roleResourceMock = Mockery::mock('alias:' . RoleResource::class);
    $roleResourceMock->shouldReceive('collection')->once()
        ->withArgs(function ($collection) use ($company) {
            return $collection == $company->roles;
        })
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)
            ->shouldReceive('toArray')->andReturn([])->getMock());

    $resource = new CompanyResource($companyMock);
    $request = Mockery::mock(Request::class);

    $transformed = $resource->toArray($request);

    expect($transformed['roles'])->toBeArray()->toBeEmpty();
});

test('toArray transforms company with a populated roles collection', function () {
    $role1 = new class {
        public $id = 1;
        public $name = 'Admin';
    };
    $role2 = new class {
        public $id = 2;
        public $name = 'Editor';
    };
    $rolesCollection = new Collection([$role1, $role2]);

    $company = makeCompanyObject([
        'id' => 4,
        'name' => 'Roles Company',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'mnop012',
        'owner_id' => 40,
        'slug' => 'roles-company',
        'address' => null,
        'roles' => $rolesCollection,
    ]);

    $addressRelationMock = Mockery::mock();
    $addressRelationMock->shouldReceive('exists')->andReturn(false);
    $companyMock = Mockery::mock($company);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn($addressRelationMock);

    $mockedRolesArray = [['id' => 1, 'name' => 'Admin'], ['id' => 2, 'name' => 'Editor']];
    $roleResourceMock = Mockery::mock('alias:' . RoleResource::class);
    $roleResourceMock->shouldReceive('collection')->once()
        ->withArgs(function ($collection) use ($company) {
            return $collection == $company->roles;
        })
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)
            ->shouldReceive('toArray')->andReturn($mockedRolesArray)->getMock());

    $resource = new CompanyResource($companyMock);
    $request = Mockery::mock(Request::class);

    $transformed = $resource->toArray($request);

    expect($transformed['roles'])->toBeArray()->toEqual($mockedRolesArray);
});

test('toArray handles a fully populated company model', function () {
    $addressModel = new class {
        public $id = 100;
        public $street = '456 Oak Ave';
    };
    $role1 = new class {
        public $id = 1;
        public $name = 'Super Admin';
    };
    $role2 = new class {
        public $id = 2;
        public $name = 'Viewer';
    };
    $rolesCollection = new Collection([$role1, $role2]);

    $company = makeCompanyObject([
        'id' => 5,
        'name' => 'Full Featured Company',
        'logo' => 'full-logo.png',
        'logo_path' => '/storage/full-logo.png',
        'unique_hash' => 'qrst345',
        'owner_id' => 50,
        'slug' => 'full-company',
        'address' => $addressModel,
        'roles' => $rolesCollection,
    ]);

    $addressRelationMock = Mockery::mock();
    $addressRelationMock->shouldReceive('exists')->andReturn(true);
    $companyMock = Mockery::mock($company);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn($addressRelationMock);

    $mockedRolesArray = [['id' => 1, 'name' => 'Super Admin'], ['id' => 2, 'name' => 'Viewer']];
    $roleResourceMock = Mockery::mock('alias:' . RoleResource::class);
    $roleResourceMock->shouldReceive('collection')->once()
        ->withArgs(function ($collection) use ($company) {
            return $collection == $company->roles;
        })
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)
            ->shouldReceive('toArray')->andReturn($mockedRolesArray)->getMock());

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
    $company = makeCompanyObject([
        'id' => 6,
        'name' => 'Company No Optional Fields',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'uvwx678',
        'owner_id' => null,
        'slug' => 'no-optional',
        'address' => null,
        'roles' => new Collection(),
    ]);

    $addressRelationMock = Mockery::mock();
    $addressRelationMock->shouldReceive('exists')->andReturn(false);
    $companyMock = Mockery::mock($company);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn($addressRelationMock);

    $roleResourceMock = Mockery::mock('alias:' . RoleResource::class);
    $roleResourceMock->shouldReceive('collection')->once()
        ->withArgs(function ($collection) use ($company) {
            return $collection == $company->roles;
        })
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)
            ->shouldReceive('toArray')->andReturn([])->getMock());

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
    $addressModel = new class {
        public $id = 99;
        public $street = 'Should Not Be Used';
    };
    $company = makeCompanyObject([
        'id' => 7,
        'name' => 'Company No Address Exists',
        'logo' => null,
        'logo_path' => null,
        'unique_hash' => 'yyyyy',
        'owner_id' => 70,
        'slug' => 'no-address-exists',
        'address' => $addressModel,
        'roles' => new Collection(),
    ]);

    $addressRelationMock = Mockery::mock();
    $addressRelationMock->shouldReceive('exists')->andReturn(false);
    $companyMock = Mockery::mock($company);
    $companyMock->shouldReceive('address')->zeroOrMoreTimes()->andReturn($addressRelationMock);

    $roleResourceMock = Mockery::mock('alias:' . RoleResource::class);
    $roleResourceMock->shouldReceive('collection')->once()
        ->andReturn(Mockery::mock(AnonymousResourceCollection::class)
            ->shouldReceive('toArray')->andReturn([])->getMock());

    $resource = new CompanyResource($companyMock);
    $request = Mockery::mock(Request::class);

    $transformed = $resource->toArray($request);

    expect($transformed)->not->toHaveKey('address');
});

afterEach(function () {
    Mockery::close();
});