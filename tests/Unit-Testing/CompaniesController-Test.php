<?php

use Crater\Http\Controllers\V1\Admin\Company\CompaniesController;
use Crater\Http\Requests\CompaniesRequest;
use Crater\Http\Resources\CompanyResource;
use Crater\Models\Company;
use Crater\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Silber\Bouncer\BouncerFacade;
use Vinkla\Hashids\Facades\Hashids;
use Mockery\MockInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Auth\Access\AuthorizationException;

// Helper function `respondJson` is used in the controller.
// Since it's a global helper and not part of the standard Laravel test environment
// for isolated unit tests, we define a minimal version if it doesn't exist.
if (!function_exists('respondJson')) {
    function respondJson(string $key, string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'key' => $key,
        ], $status);
    }
}

beforeEach(function () {
    // We will mock Gate for authorize calls
    Gate::fake();

    // Reset mocks for facades to ensure clean state between tests
    Hashids::forgetDrivers();
    BouncerFacade::forgetChecks();
    // Close Mockery to verify expectations and clear mocks for the next test
    Mockery::close();
});

test('store creates a company with default data and without address', function () {
    Gate::shouldReceive('authorize')->once()->with('create company')->andReturn(true);

    $companyPayload = ['name' => 'Test Company', 'email' => 'test@example.com'];
    $companyId = 1;
    $uniqueHash = 'hashed_company_id';

    // Mock User instance and its relations/methods
    $mockUser = Mockery::mock(User::class);
    $mockUser->shouldReceive('companies')->andReturn(
        Mockery::mock(BelongsToMany::class)
            ->shouldReceive('attach')
            ->with($companyId)
            ->once()
            ->getMock()
    )->once();
    $mockUser->shouldReceive('assign')->with('super admin')->once()->andReturn($mockUser);

    // Mock Company instance and its methods
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = null; // Property will be set by the controller
    $mockCompany->shouldReceive('save')->once()->andReturn(true);
    $mockCompany->shouldReceive('setupDefaultData')->once();
    $mockCompany->shouldNotReceive('address'); // The 'address' relationship should not be called

    // Mock static methods of Company model
    Mockery::mock('alias:' . Company::class)
        ->shouldReceive('create')
        ->once()
        ->with($companyPayload)
        ->andReturn($mockCompany);

    // Mock Hashids facade for unique hash generation
    Hashids::shouldReceive('connection')
        ->with(Company::class)
        ->once()
        ->andReturn(
            Mockery::mock()
                ->shouldReceive('encode')
                ->with($companyId)
                ->once()
                ->andReturn($uniqueHash)
                ->getMock()
        );

    // Mock CompaniesRequest instance to control inputs
    $mockRequest = Mockery::mock(CompaniesRequest::class);
    $mockRequest->shouldReceive('user')->andReturn($mockUser)->once();
    $mockRequest->shouldReceive('getCompanyPayload')->andReturn($companyPayload)->once();
    $mockRequest->address = null; // Simulate no address data in the request

    // Create controller instance
    $controller = new CompaniesController();

    // Act
    $response = $controller->store($mockRequest);

    // Assert the response and internal state
    expect($response)->toBeInstanceOf(CompanyResource::class);
    expect($response->resource)->toBe($mockCompany);
    expect($mockCompany->unique_hash)->toBe($uniqueHash);
});

test('store creates a company with address data', function () {
    Gate::shouldReceive('authorize')->once()->with('create company')->andReturn(true);

    $companyPayload = ['name' => 'Test Company', 'email' => 'test@example.com'];
    $addressPayload = ['line_1' => '123 Main St', 'city' => 'Anytown'];
    $companyId = 1;
    $uniqueHash = 'hashed_company_id';

    // Mock User instance and its relations/methods
    $mockUser = Mockery::mock(User::class);
    $mockUser->shouldReceive('companies')->andReturn(
        Mockery::mock(BelongsToMany::class)
            ->shouldReceive('attach')
            ->with($companyId)
            ->once()
            ->getMock()
    )->once();
    $mockUser->shouldReceive('assign')->with('super admin')->once()->andReturn($mockUser);

    // Mock Company instance and its methods
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = null; // Property will be set by the controller
    $mockCompany->shouldReceive('save')->once()->andReturn(true);
    $mockCompany->shouldReceive('setupDefaultData')->once();
    // The 'address' relationship should be called and create an address
    $mockCompany->shouldReceive('address')->andReturn(
        Mockery::mock(HasOne::class)
            ->shouldReceive('create')
            ->with($addressPayload)
            ->once()
            ->getMock()
    )->once();

    // Mock static methods of Company model
    Mockery::mock('alias:' . Company::class)
        ->shouldReceive('create')
        ->once()
        ->with($companyPayload)
        ->andReturn($mockCompany);

    // Mock Hashids facade for unique hash generation
    Hashids::shouldReceive('connection')
        ->with(Company::class)
        ->once()
        ->andReturn(
            Mockery::mock()
                ->shouldReceive('encode')
                ->with($companyId)
                ->once()
                ->andReturn($uniqueHash)
                ->getMock()
        );

    // Mock CompaniesRequest instance to control inputs
    $mockRequest = Mockery::mock(CompaniesRequest::class);
    $mockRequest->shouldReceive('user')->andReturn($mockUser)->once();
    $mockRequest->shouldReceive('getCompanyPayload')->andReturn($companyPayload)->once();
    $mockRequest->address = $addressPayload; // Simulate address data in the request

    // Create controller instance
    $controller = new CompaniesController();

    // Act
    $response = $controller->store($mockRequest);

    // Assert the response and internal state
    expect($response)->toBeInstanceOf(CompanyResource::class);
    expect($response->resource)->toBe($mockCompany);
    expect($mockCompany->unique_hash)->toBe($uniqueHash);
});

test('destroy deletes a company successfully', function () {
    $companyId = 1;
    $companyName = 'Company A';
    $userId = 10;

    // Mock Company instance and its methods
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->name = $companyName;
    $mockCompany->shouldReceive('deleteCompany')->once()->with(Mockery::type(User::class));

    // Mock static methods of Company model
    Mockery::mock('alias:' . Company::class)
        ->shouldReceive('find')
        ->once()
        ->with($companyId)
        ->andReturn($mockCompany);

    Gate::shouldReceive('authorize')->once()->with('delete company', $mockCompany)->andReturn(true);

    // Mock User instance and its methods/properties
    $mockUser = Mockery::mock(User::class);
    $mockUser->id = $userId;
    $mockUser->companies_count = 2; // User has more than one company
    $mockUser->shouldReceive('loadCount')->with('companies')->once()->andReturn($mockUser);

    // Mock Request instance to control inputs
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $mockRequest->shouldReceive('user')->andReturn($mockUser)->once();
    $mockRequest->name = $companyName; // Company name matches

    // Create controller instance
    $controller = new CompaniesController();

    // Act
    $response = $controller->destroy($mockRequest);

    // Assert the successful JSON response
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);
});

test('destroy returns error if company name does not match', function () {
    $companyId = 1;
    $companyName = 'Company A';
    $userId = 10;
    $mismatchedName = 'Wrong Company';

    // Mock Company instance and its methods
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->name = $companyName;
    $mockCompany->shouldNotReceive('deleteCompany'); // deleteCompany should not be called

    // Mock static methods of Company model
    Mockery::mock('alias:' . Company::class)
        ->shouldReceive('find')
        ->once()
        ->with($companyId)
        ->andReturn($mockCompany);

    Gate::shouldReceive('authorize')->once()->with('delete company', $mockCompany)->andReturn(true);

    // Mock User instance
    $mockUser = Mockery::mock(User::class);
    $mockUser->id = $userId;
    // loadCount will not be called as the name mismatch check happens earlier

    // Mock Request instance to control inputs
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $mockRequest->shouldReceive('user')->andReturn($mockUser)->once();
    $mockRequest->name = $mismatchedName; // Mismatched name

    // Create controller instance
    $controller = new CompaniesController();

    // Act
    $response = $controller->destroy($mockRequest);

    // Assert the error JSON response
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => false,
        'message' => 'Company name must match with given name',
        'key' => 'company_name_must_match_with_given_name',
    ]);
});

test('destroy returns error if user tries to delete all companies', function () {
    $companyId = 1;
    $companyName = 'Company A';
    $userId = 10;

    // Mock Company instance and its methods
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->name = $companyName;
    $mockCompany->shouldNotReceive('deleteCompany'); // deleteCompany should not be called

    // Mock static methods of Company model
    Mockery::mock('alias:' . Company::class)
        ->shouldReceive('find')
        ->once()
        ->with($companyId)
        ->andReturn($mockCompany);

    Gate::shouldReceive('authorize')->once()->with('delete company', $mockCompany)->andReturn(true);

    // Mock User instance and its methods/properties
    $mockUser = Mockery::mock(User::class);
    $mockUser->id = $userId;
    $mockUser->companies_count = 1; // User has only one company
    $mockUser->shouldReceive('loadCount')->with('companies')->once()->andReturn($mockUser);

    // Mock Request instance to control inputs
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $mockRequest->shouldReceive('user')->andReturn($mockUser)->once();
    $mockRequest->name = $companyName; // Company name matches

    // Create controller instance
    $controller = new CompaniesController();

    // Act
    $response = $controller->destroy($mockRequest);

    // Assert the error JSON response
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => false,
        'message' => 'You cannot delete all companies',
        'key' => 'You_cannot_delete_all_companies',
    ]);
});

test('destroy handles company not found gracefully via authorization exception', function () {
    $companyId = 99; // Non-existent company ID

    // Mock static methods of Company model to return null
    Mockery::mock('alias:' . Company::class)
        ->shouldReceive('find')
        ->once()
        ->with($companyId)
        ->andReturn(null); // Company not found

    // Expect authorization to fail when called with a null company
    Gate::shouldReceive('authorize')
        ->once()
        ->with('delete company', null)
        ->andThrow(AuthorizationException::class);

    // Mock Request instance
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    // `user()` and `name` properties of the request will not be accessed as authorization fails first

    // Create controller instance
    $controller = new CompaniesController();

    // Act & Assert that an AuthorizationException is thrown
    expect(function () use ($controller, $mockRequest) {
        $controller->destroy($mockRequest);
    })->toThrow(AuthorizationException::class);
});

test('transferOwnership transfers ownership successfully', function () {
    $companyId = 1;
    $newOwnerId = 2;

    // Mock Company instance and its methods
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->shouldReceive('update')->once()->with(['owner_id' => $newOwnerId]);

    // Mock static methods of Company model
    Mockery::mock('alias:' . Company::class)
        ->shouldReceive('find')
        ->once()
        ->with($companyId)
        ->andReturn($mockCompany);

    Gate::shouldReceive('authorize')->once()->with('transfer company ownership', $mockCompany)->andReturn(true);

    // Mock the User instance that will be the new owner
    $mockNewOwner = Mockery::mock(User::class);
    $mockNewOwner->id = $newOwnerId;
    $mockNewOwner->shouldReceive('hasCompany')->with($companyId)->once()->andReturn(false); // User does not belong to this company yet

    // Mock BouncerFacade for role synchronization
    BouncerFacade::shouldReceive('sync')
        ->once()
        ->with($mockNewOwner)
        ->andReturn(
            Mockery::mock()
                ->shouldReceive('roles')
                ->with(['super admin'])
                ->once()
                ->getMock()
        );

    // Mock Request instance to control header input
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    // Create controller instance
    $controller = new CompaniesController();

    // Act
    $response = $controller->transferOwnership($mockRequest, $mockNewOwner);

    // Assert the successful JSON response
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);
});

test('transferOwnership returns error if user already belongs to the company', function () {
    $companyId = 1;
    $newOwnerId = 2;

    // Mock Company instance and ensure update is not called
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->shouldNotReceive('update'); // Company update should not be called

    // Mock static methods of Company model
    Mockery::mock('alias:' . Company::class)
        ->shouldReceive('find')
        ->once()
        ->with($companyId)
        ->andReturn($mockCompany);

    Gate::shouldReceive('authorize')->once()->with('transfer company ownership', $mockCompany)->andReturn(true);

    // Mock the User instance. This user already belongs to the company.
    $mockNewOwner = Mockery::mock(User::class);
    $mockNewOwner->id = $newOwnerId;
    $mockNewOwner->shouldReceive('hasCompany')->with($companyId)->once()->andReturn(true); // User already belongs to company

    // BouncerFacade should not be called
    BouncerFacade::shouldNotReceive('sync');

    // Mock Request instance to control header input
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    // Create controller instance
    $controller = new CompaniesController();

    // Act
    $response = $controller->transferOwnership($mockRequest, $mockNewOwner);

    // Assert the error JSON response
    expect($response)->toBeInstanceOf(JsonResponse::class);
    // Note: The original controller code has a slightly counter-intuitive error message.
    // We test for the exact message as implemented.
    expect($response->getData(true))->toEqual([
        'success' => false,
        'message' => 'User does not belongs to this company.'
    ]);
});

test('transferOwnership handles company not found gracefully via authorization exception', function () {
    $companyId = 99; // Non-existent company ID
    $newOwnerId = 2;

    // Mock static methods of Company model to return null
    Mockery::mock('alias:' . Company::class)
        ->shouldReceive('find')
        ->once()
        ->with($companyId)
        ->andReturn(null); // Company not found

    // Expect authorization to fail when called with a null company
    Gate::shouldReceive('authorize')
        ->once()
        ->with('transfer company ownership', null)
        ->andThrow(AuthorizationException::class);

    // Mock the User instance (won't be fully utilized due to early authorization failure)
    $mockNewOwner = Mockery::mock(User::class);
    $mockNewOwner->id = $newOwnerId;

    // Mock Request instance to control header input
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')->with('company')->andReturn($companyId)->once();

    // Create controller instance
    $controller = new CompaniesController();

    // Act & Assert that an AuthorizationException is thrown
    expect(function () use ($controller, $mockRequest, $mockNewOwner) {
        $controller->transferOwnership($mockRequest, $mockNewOwner);
    })->toThrow(AuthorizationException::class);
});


test('getUserCompanies returns a collection of companies', function () {
    // Mock a collection of companies
    $mockCompanies = collect([
        (object)['id' => 1, 'name' => 'Company A'],
        (object)['id' => 2, 'name' => 'Company B'],
    ]);

    // Mock User instance and its `companies` property
    $mockUser = Mockery::mock(User::class);
    $mockUser->companies = $mockCompanies;

    // Mock Request instance to return the mock user
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('user')->andReturn($mockUser)->once();

    // Create controller instance
    $controller = new CompaniesController();

    // Act
    $response = $controller->getUserCompanies($mockRequest);

    // Assert the response is an AnonymousResourceCollection containing the mock companies
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->collection)->toEqual($mockCompanies);
});

test('getUserCompanies returns an empty collection if user has no companies', function () {
    // Mock an empty collection of companies
    $mockCompanies = collect([]);

    // Mock User instance and its `companies` property
    $mockUser = Mockery::mock(User::class);
    $mockUser->companies = $mockCompanies;

    // Mock Request instance to return the mock user
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('user')->andReturn($mockUser)->once();

    // Create controller instance
    $controller = new CompaniesController();

    // Act
    $response = $controller->getUserCompanies($mockRequest);

    // Assert the response is an AnonymousResourceCollection containing an empty collection
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->collection)->toEqual($mockCompanies);
    expect($response->collection)->toBeEmpty();
});
