<?php

use function Pest\Laravel\mock;
uses(\Mockery::class);
use Crater\Http\Controllers\V1\Admin\Payment\PaymentMethodsController;
use Crater\Http\Requests\PaymentMethodRequest;
use Crater\Http\Resources\PaymentMethodResource;
use Crater\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

beforeEach(function () {
    // We'll mock the global respondJson function if it exists.
    // In a real application, you might use a package like 'php-mock/php-mock-mockery'
    // or refactor global helpers for easier testing.
    // For this context, we'll create a simple mockable wrapper.
    if (!function_exists('respondJson')) {
        function respondJson(string $key, string $message, int $status = 422): JsonResponse
        {
            return response()->json([
                'error' => $key,
                'message' => $message,
            ], $status);
        }
    }
});

test('index method returns a collection of payment methods with default limit', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('all')->andReturn([]);

    $paymentMethods = Mockery::mock(LengthAwarePaginator::class);
    $paymentMethods->shouldReceive('toArray')->andReturn(['data' => []]); // Simplified for resource collection

    $queryBuilder = Mockery::mock(Builder::class);
    $queryBuilder->shouldReceive('where')->with('type', PaymentMethod::TYPE_GENERAL)->andReturnSelf();
    $queryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $queryBuilder->shouldReceive('latest')->andReturnSelf();
    $queryBuilder->shouldReceive('paginateData')->with(5)->andReturn($paymentMethods);

    Mockery::mock('alias:' . PaymentMethod::class)
        ->shouldReceive('applyFilters')
        ->with([])
        ->andReturn($queryBuilder);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('viewAny', PaymentMethod::class);

    $resourceCollection = Mockery::mock(PaymentMethodResource::class);
    Mockery::mock('alias:' . PaymentMethodResource::class)
        ->shouldReceive('collection')
        ->with($paymentMethods)
        ->andReturn($resourceCollection);

    $result = $controller->index($request);

    expect($result)->toBe($resourceCollection);
});

test('index method returns a collection of payment methods with custom limit', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->shouldReceive('get')->with('limit')->andReturn(10); // Using get instead of direct property access for mock
    $request->shouldReceive('limit')->andReturn(10); // Fallback for direct property access if used
    $request->shouldReceive('all')->andReturn(['limit' => 10]);

    $paymentMethods = Mockery::mock(LengthAwarePaginator::class);
    $paymentMethods->shouldReceive('toArray')->andReturn(['data' => []]);

    $queryBuilder = Mockery::mock(Builder::class);
    $queryBuilder->shouldReceive('where')->with('type', PaymentMethod::TYPE_GENERAL)->andReturnSelf();
    $queryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $queryBuilder->shouldReceive('latest')->andReturnSelf();
    $queryBuilder->shouldReceive('paginateData')->with(10)->andReturn($paymentMethods);

    Mockery::mock('alias:' . PaymentMethod::class)
        ->shouldReceive('applyFilters')
        ->with(['limit' => 10])
        ->andReturn($queryBuilder);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('viewAny', PaymentMethod::class);

    $resourceCollection = Mockery::mock(PaymentMethodResource::class);
    Mockery::mock('alias:' . PaymentMethodResource::class)
        ->shouldReceive('collection')
        ->with($paymentMethods)
        ->andReturn($resourceCollection);

    $result = $controller->index($request);

    expect($result)->toBe($resourceCollection);
});

test('store method creates and returns a new payment method resource', function () {
    $request = Mockery::mock(PaymentMethodRequest::class);
    $paymentMethod = Mockery::mock(PaymentMethod::class);

    Mockery::mock('alias:' . PaymentMethod::class)
        ->shouldReceive('createPaymentMethod')
        ->once()
        ->with($request)
        ->andReturn($paymentMethod);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('create', PaymentMethod::class);

    $resource = Mockery::mock(PaymentMethodResource::class);
    Mockery::mock('overload:' . PaymentMethodResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($paymentMethod)
        ->andReturnNull();

    $result = $controller->store($request);

    expect($result)->toBeInstanceOf(PaymentMethodResource::class);
});

test('show method returns a payment method resource', function () {
    $paymentMethod = Mockery::mock(PaymentMethod::class);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('view', $paymentMethod);

    $resource = Mockery::mock(PaymentMethodResource::class);
    Mockery::mock('overload:' . PaymentMethodResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($paymentMethod)
        ->andReturnNull();

    $result = $controller->show($paymentMethod);

    expect($result)->toBeInstanceOf(PaymentMethodResource::class);
});

test('update method updates and returns the payment method resource', function () {
    $request = Mockery::mock(PaymentMethodRequest::class);
    $paymentMethod = Mockery::mock(PaymentMethod::class);
    $payload = ['name' => 'Updated Name'];

    $request->shouldReceive('getPaymentMethodPayload')->once()->andReturn($payload);
    $paymentMethod->shouldReceive('update')->once()->with($payload)->andReturn(true);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('update', $paymentMethod);

    $resource = Mockery::mock(PaymentMethodResource::class);
    Mockery::mock('overload:' . PaymentMethodResource::class)
        ->shouldReceive('__construct')
        ->once()
        ->with($paymentMethod)
        ->andReturnNull();

    $result = $controller->update($request, $paymentMethod);

    expect($result)->toBeInstanceOf(PaymentMethodResource::class);
});

test('destroy method deletes payment method if no payments or expenses are attached', function () {
    $paymentMethod = Mockery::mock(PaymentMethod::class);
    $queryBuilder = Mockery::mock(Builder::class);

    $paymentMethod->shouldReceive('payments')->once()->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('exists')->once()->andReturn(false);

    $paymentMethod->shouldReceive('expenses')->once()->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('exists')->once()->andReturn(false);

    $paymentMethod->shouldReceive('delete')->once()->andReturn(true);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('delete', $paymentMethod);

    $result = $controller->destroy($paymentMethod);

    expect($result)->toBeInstanceOf(JsonResponse::class)
        ->and($result->getStatusCode())->toBe(200)
        ->and($result->getData(true))->toEqual(['success' => 'Payment method deleted successfully']);
});

test('destroy method returns error if payments are attached', function () {
    $paymentMethod = Mockery::mock(PaymentMethod::class);
    $queryBuilder = Mockery::mock(Builder::class);

    $paymentMethod->shouldReceive('payments')->once()->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('exists')->once()->andReturn(true);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('delete', $paymentMethod);

    $result = $controller->destroy($paymentMethod);

    expect($result)->toBeInstanceOf(JsonResponse::class)
        ->and($result->getStatusCode())->toBe(422)
        ->and($result->getData(true))->toEqual(['error' => 'payments_attached', 'message' => 'Payments Attached.']);
});

test('destroy method returns error if expenses are attached but no payments', function () {
    $paymentMethod = Mockery::mock(PaymentMethod::class);
    $queryBuilder = Mockery::mock(Builder::class);

    $paymentMethod->shouldReceive('payments')->once()->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('exists')->once()->andReturn(false);

    $paymentMethod->shouldReceive('expenses')->once()->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('exists')->once()->andReturn(true);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('delete', $paymentMethod);

    $result = $controller->destroy($paymentMethod);

    expect($result)->toBeInstanceOf(JsonResponse::class)
        ->and($result->getStatusCode())->toBe(422)
        ->and($result->getData(true))->toEqual(['error' => 'expenses_attached', 'message' => 'Expenses Attached.']);
});

// Helper for respondJson if it's not globally available or needs explicit mocking
// This is typically handled by setting up a test environment that includes helpers.
// If respondJson were a class method or trait, it would be mocked differently.
// For a global function, this setup in beforeEach is a common workaround for unit testing.
// Alternatively, one could use `expect($result)->toHaveStatus(422)->toHaveJson(['error' => 'payments_attached']);`
// if the test runs in a Laravel context and `response()->json()` works as expected.
// Since the instruction is white-box unit testing and focusing on the class itself,
// mocking `respondJson` directly is the most isolated approach.
