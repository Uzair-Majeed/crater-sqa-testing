<?php

use function Pest\Laravel\mock;
use Crater\Http\Controllers\V1\Admin\Payment\PaymentMethodsController;
use Crater\Http\Requests\PaymentMethodRequest;
use Crater\Http\Resources\PaymentMethodResource;
use Crater\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\ResourceCollection; // Added for PaymentMethodResource::collection type hint

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

    $paymentMethodsPaginator = Mockery::mock(LengthAwarePaginator::class);
    $paymentMethodsPaginator->shouldReceive('toArray')->andReturn(['data' => []]); // Simplified for resource collection

    $queryBuilder = Mockery::mock(Builder::class);
    // FIX: Replaced PaymentMethod::TYPE_GENERAL with its string value 'general'.
    // Accessing PaymentMethod::TYPE_GENERAL would load the PaymentMethod class prematurely,
    // leading to "class already exists" errors when trying to create an alias mock for it later.
    $queryBuilder->shouldReceive('where')->with('type', 'general')->andReturnSelf();
    $queryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $queryBuilder->shouldReceive('latest')->andReturnSelf();
    $queryBuilder->shouldReceive('paginateData')->with(5)->andReturn($paymentMethodsPaginator);

    // FIX: Replaced 'alias:' mock with Pest's 'mock' helper for the PaymentMethod model.
    // This binds a mock instance to the service container. For static methods like applyFilters (often a scope),
    // Laravel's __callStatic can delegate to the container-bound instance.
    mock(PaymentMethod::class)
        ->shouldReceive('applyFilters')
        ->with([])
        ->andReturn($queryBuilder);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('viewAny', PaymentMethod::class);

    // FIX: Mocking the expected return type of PaymentMethodResource::collection().
    // 'alias:' is kept here for the static `collection` method as it's the direct way to mock static calls.
    // Given the PaymentMethod::TYPE_GENERAL issue is resolved, this alias should now be loadable.
    $resourceCollection = Mockery::mock(ResourceCollection::class); // Mock what JsonResource::collection() returns
    Mockery::mock('alias:' . PaymentMethodResource::class)
        ->shouldReceive('collection')
        ->with($paymentMethodsPaginator)
        ->andReturn($resourceCollection);

    $result = $controller->index($request);

    expect($result)->toBe($resourceCollection);
});

test('index method returns a collection of payment methods with custom limit', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->shouldReceive('get')->with('limit')->andReturn(10);
    $request->shouldReceive('all')->andReturn(['limit' => 10]);

    $paymentMethodsPaginator = Mockery::mock(LengthAwarePaginator::class);
    $paymentMethodsPaginator->shouldReceive('toArray')->andReturn(['data' => []]);

    $queryBuilder = Mockery::mock(Builder::class);
    // FIX: Replaced PaymentMethod::TYPE_GENERAL with its string value 'general'.
    $queryBuilder->shouldReceive('where')->with('type', 'general')->andReturnSelf();
    $queryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $queryBuilder->shouldReceive('latest')->andReturnSelf();
    $queryBuilder->shouldReceive('paginateData')->with(10)->andReturn($paymentMethodsPaginator);

    // FIX: Replaced 'alias:' mock with Pest's 'mock' helper for the PaymentMethod model.
    mock(PaymentMethod::class)
        ->shouldReceive('applyFilters')
        ->with(['limit' => 10])
        ->andReturn($queryBuilder);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('viewAny', PaymentMethod::class);

    // FIX: Keeping alias mock for static JsonResource::collection.
    $resourceCollection = Mockery::mock(ResourceCollection::class); // Mock what JsonResource::collection() returns
    Mockery::mock('alias:' . PaymentMethodResource::class)
        ->shouldReceive('collection')
        ->with($paymentMethodsPaginator)
        ->andReturn($resourceCollection);

    $result = $controller->index($request);

    expect($result)->toBe($resourceCollection);
});

test('store method creates and returns a new payment method resource', function () {
    $request = Mockery::mock(PaymentMethodRequest::class);
    $paymentMethod = Mockery::mock(PaymentMethod::class);

    // FIX: Replaced 'alias:' mock with Pest's 'mock' helper for the PaymentMethod model.
    mock(PaymentMethod::class)
        ->shouldReceive('createPaymentMethod')
        ->once()
        ->with($request)
        ->andReturn($paymentMethod);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('create', PaymentMethod::class);

    // FIX: Removed 'overload' mock for PaymentMethodResource.
    // The controller typically instantiates a real `PaymentMethodResource` with `new PaymentMethodResource($paymentMethod)`.
    // The `toBeInstanceOf` assertion sufficiently verifies this interaction for unit testing the controller.
    // The `$resource = Mockery::mock(PaymentMethodResource::class);` line was creating a mock that was never returned by the controller.
    // We expect the controller to return a real instance.

    $result = $controller->store($request);

    expect($result)->toBeInstanceOf(PaymentMethodResource::class);
});

test('show method returns a payment method resource', function () {
    $paymentMethod = Mockery::mock(PaymentMethod::class);

    $controller = Mockery::mock(PaymentMethodsController::class)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->once()->with('view', $paymentMethod);

    // FIX: Removed 'overload' mock for PaymentMethodResource.
    // As in the 'store' test, the controller is expected to return a real `PaymentMethodResource` instance.
    // The `toBeInstanceOf` assertion is sufficient.

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

    // FIX: Removed 'overload' mock for PaymentMethodResource.
    // As in previous tests, the controller is expected to return a real `PaymentMethodResource` instance.
    // The `toBeInstanceOf` assertion is sufficient.

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

afterEach(function () {
    Mockery::close();
});