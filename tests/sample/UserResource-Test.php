```php
<?php

use Crater\Http\Resources\UserResource;
use Crater\Http\Resources\CurrencyResource;
use Crater\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Mockery; // Ensure Mockery is imported

beforeEach(function () {
    // These ensure a clean slate for Mockery before each test
    Mockery::close();
});

/**
 * Helper function to create a mocked user model with properties and methods for UserResource testing.
 *
 * This function addresses the "Undefined property" error by creating a Mockery mock of stdClass
 * that explicitly has all expected properties defined. It also sets up common method mocks
 * like `isOwner` and relationship methods (`currency`, `companies`) to return mock relation objects,
 * and crucially, directly sets the relationship properties on the mock model (e.g., $userModel->currency)
 * to mimic loaded Eloquent relationships that JsonResource often accesses directly.
 *
 * @param array $properties The properties to set on the mock model.
 * @param bool $isOwner The return value for the 'isOwner' method.
 * @param object|null $currency The currency object if the relationship is loaded.
 * @param Collection|null $companies The companies collection if the relationship is loaded.
 * @return Mockery\MockInterface
 */
function createMockUser(array $properties, bool $isOwner = false, ?object $currency = null, ?Collection $companies = null): Mockery\MockInterface
{
    // Define all properties that UserResource might try to access.
    // This ensures no 'Undefined property' errors, even if a test doesn't explicitly set them.
    // Properties are initialized with null or empty defaults.
    $defaultProperties = [
        'id' => null,
        'name' => null,
        'email' => null,
        'phone' => null,
        'role' => null,
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
        'formattedCreatedAt' => null, // Assumed accessor or dynamic property
        'roles' => new Collection([]), // Assumed accessor or dynamic property, initialized as Collection
    ];

    // Merge provided properties over defaults to ensure everything is present.
    $finalProperties = array_merge($defaultProperties, $properties);

    // Create a mock of stdClass directly with the merged properties.
    // This is the key fix for "Undefined property" errors: it ensures that when JsonResource
    // delegates property access (e.g., $this->resource->id), the mock object directly
    // has these public properties, as stdClass properties are public by default.
    $userModel = Mockery::mock(stdClass::class, $finalProperties);

    // Mock the 'isOwner' method which is called by the resource.
    // Using zeroOrMoreTimes as its call count might vary or might not be strictly 'once'
    // in every test's execution path.
    $userModel->shouldReceive('isOwner')
        ->zeroOrMoreTimes()
        ->andReturn($isOwner);

    // Mock the 'currency' relationship method (`$user->currency()`).
    $mockCurrencyRelation = Mockery::mock(BelongsTo::class);
    // The 'exists' method on the relationship object indicates if the relation is loaded.
    $mockCurrencyRelation->shouldReceive('exists')->zeroOrMoreTimes()->andReturn($currency !== null);
    $userModel->shouldReceive('currency')->zeroOrMoreTimes()->andReturn($mockCurrencyRelation);

    // Mock the 'companies' relationship method (`$user->companies()`).
    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    // The 'exists' method on the relationship object indicates if the relation is loaded.
    $mockCompaniesRelation->shouldReceive('exists')->zeroOrMoreTimes()->andReturn($companies !== null && $companies->isNotEmpty());
    $userModel->shouldReceive('companies')->zeroOrMoreTimes()->andReturn($mockCompaniesRelation);
    
    // Crucially, if the UserResource accesses relationships directly as properties (e.g., `$this->currency`),
    // we need to set these properties on the mock model. This mimics how Eloquent models hold loaded relations.
    // This ensures that `isset($this->currency)` within the resource works as expected.
    if ($currency) {
        $userModel->currency = $currency;
    }
    if ($companies) {
        $userModel->companies = $companies;
    }

    return $userModel;
}

test('user resource transforms a basic user model into an array with expected fields', function () {
    $properties = [
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

    $userModel = createMockUser($properties, true); // isOwner = true

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
        ->and($result)->not->toHaveKeys(['currency', 'companies']) // Should not be present if relationships are not loaded/conditionally added
        ->and($result['id'])->toBe(1)
        ->and($result['name'])->toBe('John Doe')
        ->and($result['email'])->toBe('john@example.com')
        ->and($result['is_owner'])->toBeTrue()
        ->and($result['formatted_created_at'])->toBe('January 1, 2023')
        ->and($result['roles'])->toEqual(['admin', 'editor']); // JsonResource converts Collections to arrays
});

test('user resource handles null and empty properties gracefully', function () {
    $properties = [
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

    $userModel = createMockUser($properties, false); // isOwner = false

    $request = new Request();
    $resource = new UserResource($userModel);
    $result = $resource->toArray($request);

    expect($result)->toBeArray()
        ->and($result['id'])->toBe(2)
        ->and($result['name'])->toBeNull()
        ->and($result['email'])->toBe('null@example.com')
        ->and($result['is_owner'])->toBeFalse()
        ->and($result['formatted_created_at'])->toBeNull()
        ->and($result['roles'])->toEqual([]); // JsonResource converts Collections to arrays
});

test('user resource includes currency when relationship exists', function () {
    $mockCurrency = (object)['id' => 10, 'code' => 'USD', 'name' => 'US Dollar']; // Simple object for currency
    $properties = [
        'id' => 3,
        'name' => 'User With Currency',
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection([]),
    ];

    // Use the helper to create the user model, passing the mock currency.
    // The helper will ensure `userModel->currency` property is set and `currency()` method is mocked.
    $userModel = createMockUser($properties, false, $mockCurrency); // isOwner = false, currency exists

    // We explicitly mock the `currency()` method call to ensure the `exists()` call on the returned relation object
    // is also properly tracked with `once()` for this specific test's assertion if the resource calls it.
    // Note: The resource might prefer direct property access `$this->currency` if already loaded.
    $userModel->shouldReceive('currency')
        ->once() // Expect the method to be called exactly once if resource logic checks it
        ->andReturn(Mockery::mock(BelongsTo::class)->shouldReceive('exists')->once()->andReturn(true)->getMock());

    // Overload CurrencyResource constructor to capture its instantiation and return a mock representation
    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with(Mockery::on(function ($arg) use ($mockCurrency) {
            // Ensure the resource is constructed with the correct currency model (our mock currency object)
            return $arg instanceof stdClass && $arg->id === $mockCurrency->id && $arg->code === $mockCurrency->code;
        }))
        ->andReturnUsing(function ($model) {
            // Return a simple object that represents the resource being instantiated
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
    $properties = [
        'id' => 4,
        'name' => 'User Without Currency',
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection([]),
    ];

    // No currency object passed, so `userModel->currency` property won't be set initially.
    $userModel = createMockUser($properties, false, null); // isOwner = false, no currency

    // Mock the currency relationship method and its exists() call to return false
    $userModel->shouldReceive('currency')
        ->once() // Expect the method to be called once
        ->andReturn(Mockery::mock(BelongsTo::class)->shouldReceive('exists')->once()->andReturn(false)->getMock());

    // Ensure CurrencyResource constructor is NOT called
    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldNotReceive('__construct');

    $request = new Request();
    $resource = new UserResource($userModel);
    $result = $resource->toArray($request);

    expect($result)->not->toHaveKey('currency');
});

test('user resource includes companies when relationship exists', function () {
    $mockCompany1 = (object)['id' => 101, 'name' => 'Company A'];
    $mockCompany2 = (object)['id' => 102, 'name' => 'Company B'];
    $mockCompanies = new Collection([$mockCompany1, $mockCompany2]);

    $properties = [
        'id' => 5,
        'name' => 'User With Companies',
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection([]),
    ];

    // Use helper to create user model, passing mock companies.
    // Helper ensures `userModel->companies` property is set and `companies()` method is mocked.
    $userModel = createMockUser($properties, false, null, $mockCompanies); // isOwner = false

    // Mock the companies relationship method and its exists() call
    $userModel->shouldReceive('companies')
        ->once() // Expect the method to be called once
        ->andReturn(Mockery::mock(BelongsToMany::class)->shouldReceive('exists')->once()->andReturn(true)->getMock());

    // Overload CompanyResource::collection static method
    Mockery::mock('overload:' . CompanyResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with(Mockery::on(function ($arg) use ($mockCompanies) {
            // Ensure the collection passed to resource is correct
            return $arg instanceof Collection && $arg->count() === $mockCompanies->count() && $arg->contains($mockCompany1);
        }))
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
    $properties = [
        'id' => 6,
        'name' => 'User Without Companies',
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection([]),
    ];

    // Empty collection passed, so `userModel->companies` property will be set to an empty collection.
    $userModel = createMockUser($properties, false, null, new Collection([])); // isOwner = false, no companies (empty collection)

    // Mock the companies relationship method and its exists() call to return false
    $userModel->shouldReceive('companies')
        ->once() // Expect the method to be called once
        ->andReturn(Mockery::mock(BelongsToMany::class)->shouldReceive('exists')->once()->andReturn(false)->getMock());

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

    $properties = [
        'id' => 7,
        'name' => 'User With Both',
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection(['test']),
    ];

    // Pass both currency and companies to the helper
    $userModel = createMockUser($properties, true, $mockCurrency, $mockCompanies); // isOwner = true

    // Explicitly mock relationship methods for specific assertions in this test
    $userModel->shouldReceive('currency')
        ->once()
        ->andReturn(Mockery::mock(BelongsTo::class)->shouldReceive('exists')->once()->andReturn(true)->getMock());
    $userModel->shouldReceive('companies')
        ->once()
        ->andReturn(Mockery::mock(BelongsToMany::class)->shouldReceive('exists')->once()->andReturn(true)->getMock());

    // Overload CurrencyResource
    Mockery::mock('overload:' . CurrencyResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg instanceof stdClass && $arg->id === $mockCurrency->id))
        ->andReturnUsing(fn ($model) => (object)['id' => $model->id, 'code' => $model->code, 'type' => 'mock_currency']);

    // Overload CompanyResource::collection
    Mockery::mock('overload:' . CompanyResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg instanceof Collection && $arg->count() === 1))
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
    $properties = [
        'id' => 8,
        'name' => 'Regular User',
        'email' => 'regular@example.com',
        'formattedCreatedAt' => 'Jan 1, 2023',
        'roles' => new Collection([]),
    ];

    $userModel = createMockUser($properties, false); // isOwner = false

    $request = new Request();
    $resource = new UserResource($userModel);
    $result = $resource->toArray($request);

    expect($result['is_owner'])->toBeFalse();
});

afterEach(function () {
    Mockery::close();
});
```