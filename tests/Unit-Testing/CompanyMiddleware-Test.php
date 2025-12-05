<?php

namespace Tests\Unit;

use Crater\Http\Middleware\CompanyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

// Test classes for our test doubles
class TestCompany
{
    public $id;
    public function __construct($id) { $this->id = $id; }
}

class TestUser
{
    public $id = 1;
    protected $companies = [];

    public function __construct(array $companies = [])
    {
        $this->companies = $companies;
    }

    public function hasCompany($companyId): bool
    {
        foreach ($this->companies as $company) {
            if ($company->id == $companyId) {
                return true;
            }
        }
        return false;
    }

    public function companies()
    {
        return new class($this->companies) {
            private $companies;
            public function __construct(array $companies) { $this->companies = $companies; }
            public function first() {
                return $this->companies[0] ?? null;
            }
            public function isEmpty() {
                return empty($this->companies);
            }
        };
    }
}

// Create a testable version of the middleware that allows us to override dependencies
class TestableCompanyMiddleware extends CompanyMiddleware
{
    public $schemaHasTable = false;
    public $authUser = null;
    
    protected function schemaHasTable($table)
    {
        return $this->schemaHasTable;
    }
    
    protected function getAuthUser()
    {
        return $this->authUser;
    }
}

test('it calls next closure directly if user_company table does not exist', function () {
    $middleware = new TestableCompanyMiddleware();
    $middleware->schemaHasTable = false;
    $middleware->authUser = null;
    
    $request = new Request();
    $nextCalled = false;
    $next = function ($req) use (&$nextCalled) {
        $nextCalled = true;
        return 'response';
    };

    $response = $middleware->handle($request, $next);

    expect($nextCalled)->toBeTrue()
        ->and($response)->toBe('response')
        ->and($request->headers->get('company'))->toBeNull();
});


test('it does not change company header if company header is present and user has it', function () {
    $middleware = new TestableCompanyMiddleware();
    $middleware->schemaHasTable = true;
    
    $company1 = new TestCompany(101);
    $company2 = new TestCompany(102);
    $user = new TestUser([$company1, $company2]);
    $middleware->authUser = $user;

    $request = new Request();
    $request->headers->set('company', '102'); // User has this company
    
    $nextCalled = false;
    $next = function ($req) use (&$nextCalled) {
        $nextCalled = true;
        return 'response';
    };

    $response = $middleware->handle($request, $next);

    expect($nextCalled)->toBeTrue()
        ->and($response)->toBe('response')
        ->and($request->headers->get('company'))->toBe('102');
});


test('it handles user with no companies gracefully', function () {
    $middleware = new TestableCompanyMiddleware();
    $middleware->schemaHasTable = true;
    
    $user = new TestUser([]); // No companies
    $middleware->authUser = $user;

    $request = new Request();
    
    $nextCalled = false;
    $next = function ($req) use (&$nextCalled) {
        $nextCalled = true;
        return 'response';
    };

    $response = $middleware->handle($request, $next);

    expect($nextCalled)->toBeTrue()
        ->and($response)->toBe('response')
        ->and($request->headers->get('company'))->toBeNull();
});

test('it handles unauthenticated user gracefully', function () {
    $middleware = new TestableCompanyMiddleware();
    $middleware->schemaHasTable = true;
    $middleware->authUser = null; // No user authenticated

    $request = new Request();
    
    $nextCalled = false;
    $next = function ($req) use (&$nextCalled) {
        $nextCalled = true;
        return 'response';
    };

    $response = $middleware->handle($request, $next);

    expect($nextCalled)->toBeTrue()
        ->and($response)->toBe('response');
});

test('it passes through when table exists but no user companies found', function () {
    $middleware = new TestableCompanyMiddleware();
    $middleware->schemaHasTable = true;
    
    $user = new TestUser([]);
    $middleware->authUser = $user;

    $request = new Request();
    $request->headers->set('company', '999');
    
    $nextCalled = false;
    $next = function ($req) use (&$nextCalled) {
        $nextCalled = true;
        return 'response';
    };

    $response = $middleware->handle($request, $next);

    expect($nextCalled)->toBeTrue()
        ->and($response)->toBe('response')
        ->and($request->headers->get('company'))->toBe('999');
});

test('it handles company ID as string vs integer correctly', function () {
    $middleware = new TestableCompanyMiddleware();
    $middleware->schemaHasTable = true;
    
    $company1 = new TestCompany(101);
    $user = new TestUser([$company1]);
    $middleware->authUser = $user;

    $request = new Request();
    $request->headers->set('company', '101'); // String ID
    
    $nextCalled = false;
    $next = function ($req) use (&$nextCalled) {
        $nextCalled = true;
        return 'response';
    };

    $response = $middleware->handle($request, $next);

    expect($nextCalled)->toBeTrue()
        ->and($response)->toBe('response')
        ->and($request->headers->get('company'))->toBe('101');
});
// Test with company header as integer
test('it handles integer company header', function () {
    $middleware = new TestableCompanyMiddleware();
    $middleware->schemaHasTable = true;
    
    $company1 = new TestCompany(101);
    $user = new TestUser([$company1]);
    $middleware->authUser = $user;

    $request = new Request();
    $request->headers->set('company', 101); // Integer
    
    $nextCalled = false;
    $next = function ($req) use (&$nextCalled) {
        $nextCalled = true;
        return 'response';
    };

    $response = $middleware->handle($request, $next);

    expect($nextCalled)->toBeTrue()
        ->and($response)->toBe('response')
        ->and($request->headers->get('company'))->toBe('101'); // Should be string after middleware
});

// Test when user companies is null
test('it handles null companies collection', function () {
    $middleware = new TestableCompanyMiddleware();
    $middleware->schemaHasTable = true;
    
    $user = new class extends TestUser {
        public function companies() {
            return null; // Returns null instead of collection
        }
    };
    $middleware->authUser = $user;

    $request = new Request();
    
    $nextCalled = false;
    $next = function ($req) use (&$nextCalled) {
        $nextCalled = true;
        return 'response';
    };

    $response = $middleware->handle($request, $next);

    expect($nextCalled)->toBeTrue()
        ->and($response)->toBe('response')
        ->and($request->headers->get('company'))->toBeNull();
});