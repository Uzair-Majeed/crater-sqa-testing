<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
uses(\Mockery::class);
use Symfony\Component\HttpFoundation\ParameterBag;

beforeEach(function () {
    Mockery::close();
});

test('it calls next with the original request if user_company table does not exist', function () {
    // Arrange
    Mockery::mock('alias:Illuminate\Support\Facades\Schema')
        ->shouldReceive('hasTable')
        ->with('user_company')
        ->andReturn(false)
        ->once();

    $request = Mockery::mock(Request::class);
    $request->shouldNotReceive('user');
    $request->shouldNotReceive('header');
    
    // Mock the headers property as a ParameterBag and ensure 'set' is not called
    $request->headers = Mockery::mock(ParameterBag::class);
    $request->headers->shouldNotReceive('set');

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
    $expectedCompanyId = 123;
    $firstCompany = (object) ['id' => $expectedCompanyId];

    $companiesCollectionMock = Mockery::mock();
    $companiesCollectionMock->shouldReceive('first')->andReturn($firstCompany)->once();

    $userMock = Mockery::mock();
    $userMock->shouldReceive('hasCompany')->never(); // Should not be called if header is missing
    $userMock->shouldReceive('companies')->andReturn($companiesCollectionMock)->once();

    Mockery::mock('alias:Illuminate\Support\Facades\Schema')
        ->shouldReceive('hasTable')
        ->with('user_company')
        ->andReturn(true)
        ->once();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($userMock)->once();
    $request->shouldReceive('header')->with('company')->andReturn(null)->once(); // No company header

    $request->headers = Mockery::mock(ParameterBag::class);
    $request->headers->shouldReceive('set')->with('company', $expectedCompanyId)->once(); // Should set the header

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
    $invalidCompanyId = 999;
    $expectedCompanyId = 123;
    $firstCompany = (object) ['id' => $expectedCompanyId];

    $companiesCollectionMock = Mockery::mock();
    $companiesCollectionMock->shouldReceive('first')->andReturn($firstCompany)->once();

    $userMock = Mockery::mock();
    $userMock->shouldReceive('hasCompany')->with($invalidCompanyId)->andReturn(false)->once(); // User doesn't have this company
    $userMock->shouldReceive('companies')->andReturn($companiesCollectionMock)->once();

    Mockery::mock('alias:Illuminate\Support\Facades\Schema')
        ->shouldReceive('hasTable')
        ->with('user_company')
        ->andReturn(true)
        ->once();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($userMock)->once();
    $request->shouldReceive('header')->with('company')->andReturn($invalidCompanyId)->once();

    $request->headers = Mockery::mock(ParameterBag::class);
    $request->headers->shouldReceive('set')->with('company', $expectedCompanyId)->once(); // Should overwrite with valid ID

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
    $validCompanyId = 456;

    $userMock = Mockery::mock();
    $userMock->shouldReceive('hasCompany')->with($validCompanyId)->andReturn(true)->once(); // User has this company
    $userMock->shouldNotReceive('companies'); // Should not need to fetch companies if header is valid

    Mockery::mock('alias:Illuminate\Support\Facades\Schema')
        ->shouldReceive('hasTable')
        ->with('user_company')
        ->andReturn(true)
        ->once();

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($userMock)->once();
    $request->shouldReceive('header')->with('company')->andReturn($validCompanyId)->once();

    $request->headers = Mockery::mock(ParameterBag::class);
    $request->headers->shouldNotReceive('set'); // Should not modify the header

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
