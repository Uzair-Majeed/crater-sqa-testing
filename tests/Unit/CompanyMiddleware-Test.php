<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\ParameterBag;
use Pest\Laravel;

// Helper to mock the Schema facade via Facade::shouldReceive
function mockSchemaHasTable($return)
{
    Schema::shouldReceive('hasTable')
        ->with('user_company')
        ->andReturn($return)
        ->once();
}

beforeEach(function () {
    // Clean up everything
    Mockery::close();
    // Reset Facade mocks on Schema before every test
    Schema::spy();
    // Remove any previous shouldReceive
    Schema::shouldReceive()->zeroInteractions();
});

test('it calls next with the original request if user_company table does not exist', function () {
    // Arrange
    mockSchemaHasTable(false);

    $request = Mockery::mock(Request::class);
    $request->shouldNotReceive('user');
    $request->shouldNotReceive('header');
    
    // Mock the headers property as a ParameterBag and ensure 'set' is not called
    $headersMock = Mockery::mock(ParameterBag::class);
    $headersMock->shouldNotReceive('set');
    $request->headers = $headersMock;

    $next = function ($req) use ($request) {
        expect($req)->toBe($request);
        return 'response_from_next';
    };

    $middleware = new \Crater\Http\Middleware\CompanyMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('response_from_next');
});

test('it sets the company header if no company header is present and user_company table exists', function () {
    // Arrange
    mockSchemaHasTable(true);

    $expectedCompanyId = 123;
    $firstCompany = (object) ['id' => $expectedCompanyId];

    $companiesCollectionMock = Mockery::mock();
    $companiesCollectionMock->shouldReceive('first')->andReturn($firstCompany)->once();

    $userMock = Mockery::mock();
    $userMock->shouldReceive('hasCompany')->never(); // Should not be called if header is missing
    $userMock->shouldReceive('companies')->andReturn($companiesCollectionMock)->once();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($userMock)->once();
    $request->shouldReceive('header')->with('company')->andReturn(null)->once(); // No company header

    $headersMock = Mockery::mock(ParameterBag::class);
    $headersMock->shouldReceive('set')->with('company', $expectedCompanyId)->once(); // Should set the header
    $request->headers = $headersMock;

    $next = function ($req) use ($request) {
        expect($req)->toBe($request);
        return 'response_from_next';
    };

    $middleware = new \Crater\Http\Middleware\CompanyMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('response_from_next');
});

test('it sets the company header if an invalid company header is present and user_company table exists', function () {
    // Arrange
    mockSchemaHasTable(true);

    $invalidCompanyId = 999;
    $expectedCompanyId = 123;
    $firstCompany = (object) ['id' => $expectedCompanyId];

    $companiesCollectionMock = Mockery::mock();
    $companiesCollectionMock->shouldReceive('first')->andReturn($firstCompany)->once();

    $userMock = Mockery::mock();
    $userMock->shouldReceive('hasCompany')->with($invalidCompanyId)->andReturn(false)->once(); // User doesn't have this company
    $userMock->shouldReceive('companies')->andReturn($companiesCollectionMock)->once();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($userMock)->once();
    $request->shouldReceive('header')->with('company')->andReturn($invalidCompanyId)->once();

    $headersMock = Mockery::mock(ParameterBag::class);
    $headersMock->shouldReceive('set')->with('company', $expectedCompanyId)->once(); // Should overwrite with valid ID
    $request->headers = $headersMock;

    $next = function ($req) use ($request) {
        expect($req)->toBe($request);
        return 'response_from_next';
    };

    $middleware = new \Crater\Http\Middleware\CompanyMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('response_from_next');
});

test('it does not modify the company header if a valid company header is present and user_company table exists', function () {
    // Arrange
    mockSchemaHasTable(true);

    $validCompanyId = 456;

    $userMock = Mockery::mock();
    $userMock->shouldReceive('hasCompany')->with($validCompanyId)->andReturn(true)->once(); // User has this company
    $userMock->shouldNotReceive('companies'); // Should not need to fetch companies if header is valid

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($userMock)->once();
    $request->shouldReceive('header')->with('company')->andReturn($validCompanyId)->once();

    $headersMock = Mockery::mock(ParameterBag::class);
    $headersMock->shouldNotReceive('set'); // Should not modify the header
    $request->headers = $headersMock;

    $next = function ($req) use ($request) {
        expect($req)->toBe($request);
        return 'response_from_next';
    };

    $middleware = new \Crater\Http\Middleware\CompanyMiddleware();

    // Act
    $response = $middleware->handle($request, $next);

    // Assert
    expect($response)->toBe('response_from_next');
});

afterEach(function () {
    // It is safe to close mockery here, but we should also clear Facade state
    Mockery::close();
    Schema::shouldReceive()->zeroInteractions();
});