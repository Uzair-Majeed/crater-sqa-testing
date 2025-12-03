<?php

use Crater\Http\Resources\UserResource;
use Crater\Http\Resources\CurrencyResource;
use Crater\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

beforeEach(function () {
    // These ensure a clean slate for Mockery before each test
    Mockery::close();
});

test('user resource transforms a basic user model into an array with expected fields', function () {
    $userModel = (object) [
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '123-456-7890',
        'role' => 'admin',
        'contact_name' => 'John Doe Contact',
        'company_name' => 'Acme Inc.',
        'website' => 'http://www.acme.com',
        'enable_portal' => true,
        'currency_id' => 1,
        'facebook_id' => 'fb123',
        'google_id' => 'ggl456',
        'github_id' => 'gh789',
        'created_at' => '2023-01-01 10:00:00',
        'updated_at' => '2023-01-01 11:00:00',
        'avatar' => 'avatar.jpg',
        'formattedCreatedAt' => 'January 1, 2023', // Accessed as property
        'roles' => new Collection(['admin', 'editor']), // Accessed as property
    ];

    // Mock methods on the userModel that are called by the resource
    $userModel = Mockery::mock($userModel)
        ->shouldReceive('isOwner')
        ->once()
        ->andReturn(true)
        ->getMock();

    // Mock relationship existence checks, assuming they don't exist for this basic test
    $mockCurrencyRelation = Mockery::mock(BelongsTo::class);
    $mockCurrencyRelation->shouldReceive('exists')->andReturn(false);
    $userModel->shouldReceive('currency')->andReturn($mockCurrencyRelation);

    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('exists')->andReturn(false);
    $userModel->shouldReceive('companies')->andReturn($mockCompaniesRelation);

    $request = new Request();
    $resource = new UserResource($userModel);
    $result = $resource->toArray($request);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys([
            'id', 'name', 'email', 'phone', 'role', 'contact_name', 'company_name',
            'website', 'enable_portal', 'currency_id', 'facebook_id', 'google_id',
            'github_id', 'created_at', 'updated_at', 'avatar', 'is_owner', 'roles',
            'formatted_created_at'
        ])
        ->and($result)->not->toHaveKeys(['currency', 'companies']) // Should not be present if relationships don't exist
        ->and($result['id'])->toBe(1)
        ->and($result['name'])->toBe('John Doe')
        ->and($result['email'])->toBe('john@example.com')
        ->and($result['is_owner'])->toBeTrue()
        ->and($result['formatted_created_at'])->toBe('January 1, 2023')
        ->and($result['roles'])->toEqual(new Collection(['admin', 'editor']));
});

test('user resource handles null and empty properties gracefully', function () {
    $userModel = (object) [
        'id' => 2,
        'name' => null,
        'email' => 'null@example.com',
        'phone' => null,
        'role' => 'user',
        'contact_name' => null,
        'company_name' => null,
        'website' => null,
        'enable_portal' => false,
        'currency_id' => null,
        'facebook_id' => null,
        'google_id' => null,
        'github_id' => null,
        'created_at' => null,
        'updated_at' => null,
        'avatar' => null,
        'formattedCreatedAt' => null,
        'roles' => new Collection([]), // Empty collection
    ];

    $userModel = Mockery::mock($userModel)
        ->shouldReceive('isOwner')
        ->once()
        ->andReturn(false)
        ->getMock();

    $mockCurrencyRelation = Mockery::mock(BelongsTo::class);
    $mockCurrencyRelation->shouldReceive('exists')->andReturn(false);
    $userModel->shouldReceive('currency')->andReturn($mockCurrencyRelation);

    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('exists')->andReturn(false);
    $userModel->shouldReceive('companies')->andReturn($mockCompaniesRelation);

    $request = new Request();
    $resource = new UserResource($userModel);
    $result = $resource->toArray($request);

    expect($result)->toBeArray()
        ->and($result['id'])->toBe(2)
        ->and($result['name'])->toBeNull()
        ->and($result['email'])->toBe('null@example.com')
        ->and($result['is_owner'])->toBeFalse()
        ->and($result['formatted_created_at'])->toBeNull()
        ->and($result['roles'])->toEqual(new Collection([]));
});

test('user resource includes currency when relationship exists', function () {
    $mockCurrency = (object)['id' => 10, 'code' => 'USD', 'name' => 'US Dollar']; // Simple object for currency
    $userModel = (object) [
        'id' => 3,
        'name' => 'User With Currency',
        'currency' => $mockCurrency, // The actual currency model
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection([]),
    ];

    $userModel = Mockery::mock($userModel)
        ->shouldReceive('isOwner')
        ->once()
        ->andReturn(false)
        ->getMock();

    // Mock the currency relationship method and its exists() call
    $mockCurrencyRelation = Mockery::mock(BelongsTo::class);
    $mockCurrencyRelation->shouldReceive('exists')->once()->andReturn(true);
    $userModel->shouldReceive('currency')->once()->andReturn($mockCurrencyRelation);

    // Mock companies relationship not existing
    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('exists')->andReturn(false);
    $userModel->shouldReceive('companies')->andReturn($mockCompaniesRelation);

    // Overload CurrencyResource constructor to capture its instantiation and return a mock representation
    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($mockCurrency)
        ->andReturnUsing(function ($model) {
            // Return a simple object that represents the resource being instantiated
            // This allows us to assert on the data passed to the CurrencyResource
            return (object)['id' => $model->id, 'code' => $model->code, 'type' => 'currency_resource_mock'];
        });

    $request = new Request();
    $resource = new UserResource($userModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('currency')
        ->and($result['currency'])->toBeInstanceOf(stdClass::class) // It will be our mock object
        ->and($result['currency']->id)->toBe($mockCurrency->id)
        ->and($result['currency']->code)->toBe($mockCurrency->code)
        ->and($result['currency']->type)->toBe('currency_resource_mock');
});

test('user resource omits currency when relationship does not exist', function () {
    $userModel = (object) [
        'id' => 4,
        'name' => 'User Without Currency',
        'currency' => null, // No currency model available
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection([]),
    ];

    $userModel = Mockery::mock($userModel)
        ->shouldReceive('isOwner')
        ->once()
        ->andReturn(false)
        ->getMock();

    // Mock the currency relationship method and its exists() call to return false
    $mockCurrencyRelation = Mockery::mock(BelongsTo::class);
    $mockCurrencyRelation->shouldReceive('exists')->once()->andReturn(false); // Does not exist
    $userModel->shouldReceive('currency')->once()->andReturn($mockCurrencyRelation);

    // Ensure CurrencyResource constructor is NOT called
    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldNotReceive('__construct');

    // Mock companies relationship not existing
    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('exists')->andReturn(false);
    $userModel->shouldReceive('companies')->andReturn($mockCompaniesRelation);

    $request = new Request();
    $resource = new UserResource($userModel);
    $result = $resource->toArray($request);

    expect($result)->not->toHaveKey('currency');
});

test('user resource includes companies when relationship exists', function () {
    $mockCompany1 = (object)['id' => 101, 'name' => 'Company A'];
    $mockCompany2 = (object)['id' => 102, 'name' => 'Company B'];
    $mockCompanies = new Collection([$mockCompany1, $mockCompany2]);

    $userModel = (object) [
        'id' => 5,
        'name' => 'User With Companies',
        'companies' => $mockCompanies, // The actual companies collection
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection([]),
    ];

    $userModel = Mockery::mock($userModel)
        ->shouldReceive('isOwner')
        ->once()
        ->andReturn(false)
        ->getMock();

    // Mock currency relationship not existing
    $mockCurrencyRelation = Mockery::mock(BelongsTo::class);
    $mockCurrencyRelation->shouldReceive('exists')->andReturn(false);
    $userModel->shouldReceive('currency')->andReturn($mockCurrencyRelation);

    // Mock the companies relationship method and its exists() call
    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('exists')->once()->andReturn(true);
    $userModel->shouldReceive('companies')->once()->andReturn($mockCompaniesRelation);

    // Overload CompanyResource::collection static method
    Mockery::mock('overload:' . CompanyResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with($mockCompanies)
        ->andReturnUsing(function ($collection) {
            // Simulate the collection transformation by returning a mapped collection of mock objects
            return $collection->map(fn ($item) => (object)['id' => $item->id, 'name' => $item->name, 'type' => 'company_resource_mock'])->toArray();
        });

    $request = new Request();
    $resource = new UserResource($userModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('companies')
        ->and($result['companies'])->toBeArray()
        ->and($result['companies'])->toHaveCount(2)
        ->and($result['companies'][0])->toBeInstanceOf(stdClass::class)
        ->and($result['companies'][0]->id)->toBe($mockCompany1->id)
        ->and($result['companies'][0]->type)->toBe('company_resource_mock')
        ->and($result['companies'][1]->id)->toBe($mockCompany2->id)
        ->and($result['companies'][1]->type)->toBe('company_resource_mock');
});

test('user resource omits companies when relationship does not exist', function () {
    $userModel = (object) [
        'id' => 6,
        'name' => 'User Without Companies',
        'companies' => new Collection([]), // Empty collection available
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection([]),
    ];

    $userModel = Mockery::mock($userModel)
        ->shouldReceive('isOwner')
        ->once()
        ->andReturn(false)
        ->getMock();

    // Mock currency relationship not existing
    $mockCurrencyRelation = Mockery::mock(BelongsTo::class);
    $mockCurrencyRelation->shouldReceive('exists')->andReturn(false);
    $userModel->shouldReceive('currency')->andReturn($mockCurrencyRelation);

    // Mock the companies relationship method and its exists() call to return false
    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('exists')->once()->andReturn(false); // Does not exist
    $userModel->shouldReceive('companies')->once()->andReturn($mockCompaniesRelation);

    // Ensure CompanyResource::collection is NOT called
    Mockery::mock('overload:' . CompanyResource::class)
        ->shouldNotReceive('collection');

    $request = new Request();
    $resource = new UserResource($userModel);
    $result = $resource->toArray($request);

    expect($result)->not->toHaveKey('companies');
});

test('user resource includes both currency and companies when both relationships exist', function () {
    $mockCurrency = (object)['id' => 20, 'code' => 'EUR'];
    $mockCompany = (object)['id' => 201, 'title' => 'MegaCorp'];
    $mockCompanies = new Collection([$mockCompany]);

    $userModel = (object) [
        'id' => 7,
        'name' => 'User With Both',
        'currency' => $mockCurrency,
        'companies' => $mockCompanies,
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection(['test']),
    ];

    $userModel = Mockery::mock($userModel)
        ->shouldReceive('isOwner')
        ->once()
        ->andReturn(true)
        ->getMock();

    // Mock currency relationship exists
    $mockCurrencyRelation = Mockery::mock(BelongsTo::class);
    $mockCurrencyRelation->shouldReceive('exists')->once()->andReturn(true);
    $userModel->shouldReceive('currency')->once()->andReturn($mockCurrencyRelation);

    // Mock companies relationship exists
    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('exists')->once()->andReturn(true);
    $userModel->shouldReceive('companies')->once()->andReturn($mockCompaniesRelation);

    // Overload CurrencyResource
    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($mockCurrency)
        ->andReturnUsing(fn ($model) => (object)['id' => $model->id, 'code' => $model->code, 'type' => 'mock_currency']);

    // Overload CompanyResource::collection
    Mockery::mock('overload:' . CompanyResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with($mockCompanies)
        ->andReturnUsing(fn ($collection) => $collection->map(fn ($item) => (object)['id' => $item->id, 'title' => $item->title, 'type' => 'mock_company'])->toArray());

    $request = new Request();
    $resource = new UserResource($userModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('currency')
        ->and($result['currency']->id)->toBe($mockCurrency->id)
        ->and($result['currency']->type)->toBe('mock_currency')
        ->and($result)->toHaveKey('companies')
        ->and($result['companies'])->toHaveCount(1)
        ->and($result['companies'][0]->id)->toBe($mockCompany->id)
        ->and($result['companies'][0]->type)->toBe('mock_company')
        ->and($result['is_owner'])->toBeTrue();
});

test('user resource reflects the return value of isOwner method', function () {
    $userModel = (object) [
        'id' => 8,
        'name' => 'Regular User',
        'email' => 'regular@example.com',
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection([]),
    ];

    $userModel = Mockery::mock($userModel)
        ->shouldReceive('isOwner')
        ->once()
        ->andReturn(false) // isOwner returns false
        ->getMock();

    $mockCurrencyRelation = Mockery::mock(BelongsTo::class);
    $mockCurrencyRelation->shouldReceive('exists')->andReturn(false);
    $userModel->shouldReceive('currency')->andReturn($mockCurrencyRelation);

    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('exists')->andReturn(false);
    $userModel->shouldReceive('companies')->andReturn($mockCompaniesRelation);

    $request = new Request();
    $resource = new UserResource($userModel);
    $result = $resource->toArray($request);

    expect($result['is_owner'])->toBeFalse();
});




afterEach(function () {
    Mockery::close();
});
