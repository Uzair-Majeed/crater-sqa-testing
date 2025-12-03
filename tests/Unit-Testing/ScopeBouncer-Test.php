<?php

use Crater\Http\Middleware\ScopeBouncer;
use Illuminate\Http\Request;
use Mockery as m;
use Silber\Bouncer\Bouncer;

test('constructor sets bouncer instance', function () {
    $bouncer = m::mock(Bouncer::class);
    $middleware = new ScopeBouncer($bouncer);

    // Using reflection to access the protected 'bouncer' property for white-box testing
    $reflectionProperty = new ReflectionProperty(ScopeBouncer::class, 'bouncer');
    $reflectionProperty->setAccessible(true);

    expect($reflectionProperty->getValue($middleware))->toBe($bouncer);

    m::close();
});

test('handle scopes bouncer with company header value if present', function () {
    $tenantIdFromHeader = 123;
    $expectedRequestReturn = 'next_result';

    // Mock Bouncer to expect scope()->to() call with the header value
    $bouncerScope = m::mock();
    $bouncerScope->shouldReceive('to')
                 ->once()
                 ->with($tenantIdFromHeader);
    $bouncer = m::mock(Bouncer::class);
    $bouncer->shouldReceive('scope')
            ->once()
            ->andReturn($bouncerScope);

    // Mock User (its companies() method should not be called as header is present)
    $user = m::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldNotReceive('companies');

    // Mock Request to return the user and the company header
    $request = m::mock(Request::class);
    $request->shouldReceive('user')
            ->once()
            ->andReturn($user);
    $request->shouldReceive('header')
            ->once()
            ->with('company')
            ->andReturn($tenantIdFromHeader);

    // Mock Closure to ensure it's called with the request
    $next = m::closure();
    $next->shouldReceive('__invoke')
         ->once()
         ->with($request)
         ->andReturn($expectedRequestReturn);

    $middleware = new ScopeBouncer($bouncer);
    $result = $middleware->handle($request, $next);

    expect($result)->toBe($expectedRequestReturn);
    m::close();
});

test('handle scopes bouncer with user first company id if company header is not present', function () {
    $tenantIdFromUserCompany = 456;
    $expectedRequestReturn = 'next_result';

    // Mock Bouncer to expect scope()->to() call with the user's company ID
    $bouncerScope = m::mock();
    $bouncerScope->shouldReceive('to')
                 ->once()
                 ->with($tenantIdFromUserCompany);
    $bouncer = m::mock(Bouncer::class);
    $bouncer->shouldReceive('scope')
            ->once()
            ->andReturn($bouncerScope);

    // Mock User to return a company with the specified ID
    $company = (object)['id' => $tenantIdFromUserCompany];
    $companiesCollection = m::mock(\Illuminate\Support\Collection::class);
    $companiesCollection->shouldReceive('first')
                        ->once()
                        ->andReturn($company);
    $user = m::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('companies')
         ->once()
         ->andReturn($companiesCollection);

    // Mock Request to return the user and no company header
    $request = m::mock(Request::class);
    $request->shouldReceive('user')
            ->once()
            ->andReturn($user);
    $request->shouldReceive('header')
            ->once()
            ->with('company')
            ->andReturn(null); // No header

    // Mock Closure
    $next = m::closure();
    $next->shouldReceive('__invoke')
         ->once()
         ->with($request)
         ->andReturn($expectedRequestReturn);

    $middleware = new ScopeBouncer($bouncer);
    $result = $middleware->handle($request, $next);

    expect($result)->toBe($expectedRequestReturn);
    m::close();
});

test('handle scopes bouncer with user first company id if company header is an empty string', function () {
    $tenantIdFromUserCompany = 789;
    $expectedRequestReturn = 'next_result';

    // Mock Bouncer
    $bouncerScope = m::mock();
    $bouncerScope->shouldReceive('to')
                 ->once()
                 ->with($tenantIdFromUserCompany);
    $bouncer = m::mock(Bouncer::class);
    $bouncer->shouldReceive('scope')
            ->once()
            ->andReturn($bouncerScope);

    // Mock User to return a company with the specified ID
    $company = (object)['id' => $tenantIdFromUserCompany];
    $companiesCollection = m::mock(\Illuminate\Support\Collection::class);
    $companiesCollection->shouldReceive('first')
                        ->once()
                        ->andReturn($company);
    $user = m::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('companies')
         ->once()
         ->andReturn($companiesCollection);

    // Mock Request to return the user and an empty string for company header (which is falsy)
    $request = m::mock(Request::class);
    $request->shouldReceive('user')
            ->once()
            ->andReturn($user);
    $request->shouldReceive('header')
            ->once()
            ->with('company')
            ->andReturn(''); // Empty string header (falsy)

    // Mock Closure
    $next = m::closure();
    $next->shouldReceive('__invoke')
         ->once()
         ->with($request)
         ->andReturn($expectedRequestReturn);

    $middleware = new ScopeBouncer($bouncer);
    $result = $middleware->handle($request, $next);

    expect($result)->toBe($expectedRequestReturn);
    m::close();
});

test('handle throws TypeError if user has no companies and no company header is present', function () {
    // Mock Bouncer to ensure its scope() method is NOT called if an error occurs earlier
    $bouncer = m::mock(Bouncer::class);
    $bouncer->shouldNotReceive('scope');

    // Mock User: companies() returns a collection that returns null for first()
    $companiesCollection = m::mock(\Illuminate\Support\Collection::class);
    $companiesCollection->shouldReceive('first')
                        ->once()
                        ->andReturn(null); // Simulate no companies
    $user = m::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('companies')
         ->once()
         ->andReturn($companiesCollection);

    // Mock Request to return the user and no company header
    $request = m::mock(Request::class);
    $request->shouldReceive('user')
            ->once()
            ->andReturn($user);
    $request->shouldReceive('header')
            ->once()
            ->with('company')
            ->andReturn(null); // No header

    // Mock Closure (should not be called if an error occurs)
    $next = m::closure();
    $next->shouldNotReceive('__invoke');

    $middleware = new ScopeBouncer($bouncer);

    // Expect a TypeError because the code attempts to access '->id' on null
    expect(function () use ($middleware, $request, $next) {
        $middleware->handle($request, $next);
    })->toThrow(TypeError::class);

    m::close();
});




afterEach(function () {
    Mockery::close();
});
