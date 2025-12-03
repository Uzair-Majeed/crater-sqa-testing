<?php

use Crater\Http\Middleware\PdfMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

// Helper function to mock an authentication guard's 'check' method
function mockGuard(bool $checkResult)
{
    return Mockery::mock()->shouldReceive('check')->andReturn($checkResult)->getMock();
}

beforeEach(function () {
    $this->middleware = new PdfMiddleware();
    $this->request = Mockery::mock(Request::class);
    $this->response = Mockery::mock(Response::class); // A mock response for $next to return

    // The $next closure should be callable and return an HTTP Response or RedirectResponse
    $this->next = fn ($request) => $this->response;
});


test('it calls next closure if authenticated via web guard', function () {
    // Mock Auth::guard('web')->check() to return true
    Auth::shouldReceive('guard')->with('web')->andReturn(mockGuard(true));
    // Ensure other guards are not even checked due to short-circuiting OR logic
    Auth::shouldReceive('guard')->with('sanctum')->never();
    Auth::shouldReceive('guard')->with('customer')->never();

    $result = $this->middleware->handle($this->request, $this->next);

    // Expect the result to be the response from the $next closure
    expect($result)->toBe($this->response);
});

test('it calls next closure if authenticated via sanctum guard (web not authenticated)', function () {
    // Mock Auth::guard('web')->check() to return false
    Auth::shouldReceive('guard')->with('web')->andReturn(mockGuard(false));
    // Mock Auth::guard('sanctum')->check() to return true
    Auth::shouldReceive('guard')->with('sanctum')->andReturn(mockGuard(true));
    // Ensure 'customer' guard is not checked due to short-circuiting OR logic
    Auth::shouldReceive('guard')->with('customer')->never();

    $result = $this->middleware->handle($this->request, $this->next);

    // Expect the result to be the response from the $next closure
    expect($result)->toBe($this->response);
});

test('it calls next closure if authenticated via customer guard (web and sanctum not authenticated)', function () {
    // Mock Auth::guard('web')->check() to return false
    Auth::shouldReceive('guard')->with('web')->andReturn(mockGuard(false));
    // Mock Auth::guard('sanctum')->check() to return false
    Auth::shouldReceive('guard')->with('sanctum')->andReturn(mockGuard(false));
    // Mock Auth::guard('customer')->check() to return true
    Auth::shouldReceive('guard')->with('customer')->andReturn(mockGuard(true));

    $result = $this->middleware->handle($this->request, $this->next);

    // Expect the result to be the response from the $next closure
    expect($result)->toBe($this->response);
});

test('it redirects to login if not authenticated via any guard', function () {
    // Mock all guards to return false, meaning no user is authenticated
    Auth::shouldReceive('guard')->with('web')->andReturn(mockGuard(false));
    Auth::shouldReceive('guard')->with('sanctum')->andReturn(mockGuard(false));
    Auth::shouldReceive('guard')->with('customer')->andReturn(mockGuard(false));

    $result = $this->middleware->handle($this->request, $this->next);

    // Expect the result to be an instance of RedirectResponse
    // and its target URL to be '/login'
    expect($result)->toBeInstanceOf(RedirectResponse::class)
        ->and($result->getTargetUrl())->toBe('http://crater.test/login'); // Fixed assertion: Expect full URL
});


afterEach(function () {
    Mockery::close();
});