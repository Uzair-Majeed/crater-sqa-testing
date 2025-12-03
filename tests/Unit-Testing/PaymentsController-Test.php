<?php

use Crater\Http\Controllers\V1\Customer\Payment\PaymentsController;
use Crater\Http\Resources\Customer\PaymentResource;
use Crater\Models\Company;
use Crater\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

// Mock Auth facade for all tests in this file
beforeEach(function () {
    Auth::shouldReceive('guard')->with('customer')->andReturnSelf();
    Auth::shouldReceive('id')->andReturn(1); // Mock customer ID
});

// Helper to mock a query builder chain for Payment model methods common in 'index'
function mockPaymentQueryBuilderChainForIndex($modelMock)
{
    $builderMock = Mockery::mock();
    $modelMock->shouldReceive('with')->andReturn($builderMock);
    $builderMock->shouldReceive('whereCustomer')->andReturn($builderMock);
    $builderMock->shouldReceive('leftJoin')->andReturn($builderMock);
    $builderMock->shouldReceive('select')->andReturn($builderMock);
    $builderMock->shouldReceive('latest')->andReturn($builderMock);
    return $builderMock;
}

test('index method returns a collection of payments with default limit and no filters', function () {
    // Arrange
    $controller = new PaymentsController();
    $request = Request::create('/payments', 'GET'); // No limit or filters specified
    $customerId = 1;
    $defaultLimit = 10;
    $totalPaymentsCount = 5;

    // Mock payments for the paginated result
    $mockPayment1 = Mockery::mock(Payment::class);
    $mockPayment1->id = 1;
    $mockPayment2 = Mockery::mock(Payment::class);
    $mockPayment2->id = 2;
    $mockPayments = collect([$mockPayment1, $mockPayment2]);

    // Mock LengthAwarePaginator
    $paginator = new LengthAwarePaginator($mockPayments, $totalPaymentsCount, $defaultLimit, 1);

    // Mock Payment model query builder chain for `index`
    $mockPaymentModel = Mockery::mock('overload:' . Payment::class);
    $queryBuilderMock = mockPaymentQueryBuilderChainForIndex($mockPaymentModel);
    $queryBuilderMock->shouldReceive('applyFilters')
        ->once()
        ->with([
            'payment_number' => null,
            'payment_method_id' => null,
            'orderByField' => null,
            'orderBy' => null,
        ])
        ->andReturn($queryBuilderMock);
    $queryBuilderMock->shouldReceive('paginateData')->once()->with($defaultLimit)->andReturn($paginator);

    // Mock Payment::whereCustomer($customerId)->count() for meta data
    $countBuilderMock = Mockery::mock();
    $mockPaymentModel->shouldReceive('whereCustomer')->with($customerId)->andReturn($countBuilderMock);
    $countBuilderMock->shouldReceive('count')->once()->andReturn($totalPaymentsCount);

    // Mock PaymentResource::collection to ensure it's called with the correct paginator and returns the expected additional data
    Mockery::mock('overload:' . PaymentResource::class);
    PaymentResource::shouldReceive('collection')
        ->once()
        ->with($paginator)
        ->andReturn(Mockery::mock()->shouldReceive('additional')->once()->with([
            'meta' => [
                'paymentTotalCount' => $totalPaymentsCount,
            ],
        ])->andReturn(Mockery::mock(PaymentResource::class))->getMock());

    // Act
    $response = (new PaymentsController())->index($request);

    // Assert
    expect($response)->toBeInstanceOf(PaymentResource::class);
});

test('index method returns a collection of payments with custom limit and filters', function () {
    // Arrange
    $controller = new PaymentsController();
    $request = Request::create('/payments?limit=5&payment_number=PAY001&payment_method_id=1&orderByField=payment_date&orderBy=asc', 'GET', [
        'limit' => 5,
        'payment_number' => 'PAY001',
        'payment_method_id' => 1,
        'orderByField' => 'payment_date',
        'orderBy' => 'asc',
    ]);
    $customerId = 1;
    $customLimit = 5;
    $totalPaymentsCount = 3;

    $mockPayment1 = Mockery::mock(Payment::class);
    $mockPayment1->id = 1;
    $mockPayments = collect([$mockPayment1]);

    $paginator = new LengthAwarePaginator($mockPayments, $totalPaymentsCount, $customLimit, 1);

    $mockPaymentModel = Mockery::mock('overload:' . Payment::class);
    $queryBuilderMock = mockPaymentQueryBuilderChainForIndex($mockPaymentModel);
    $queryBuilderMock->shouldReceive('applyFilters')
        ->once()
        ->with([
            'payment_number' => 'PAY001',
            'payment_method_id' => 1,
            'orderByField' => 'payment_date',
            'orderBy' => 'asc',
        ])
        ->andReturn($queryBuilderMock);
    $queryBuilderMock->shouldReceive('paginateData')->once()->with($customLimit)->andReturn($paginator);

    $countBuilderMock = Mockery::mock();
    $mockPaymentModel->shouldReceive('whereCustomer')->with($customerId)->andReturn($countBuilderMock);
    $countBuilderMock->shouldReceive('count')->once()->andReturn($totalPaymentsCount);

    Mockery::mock('overload:' . PaymentResource::class);
    PaymentResource::shouldReceive('collection')
        ->once()
        ->with($paginator)
        ->andReturn(Mockery::mock()->shouldReceive('additional')->once()->with([
            'meta' => [
                'paymentTotalCount' => $totalPaymentsCount,
            ],
        ])->andReturn(Mockery::mock(PaymentResource::class))->getMock());

    // Act
    $response = (new PaymentsController())->index($request);

    // Assert
    expect($response)->toBeInstanceOf(PaymentResource::class);
});

test('show method returns a single payment resource when payment is found', function () {
    // Arrange
    $controller = new PaymentsController();
    $paymentId = 10;
    $customerId = 1;

    $mockCompany = Mockery::mock(Company::class);
    $mockPayment = Mockery::mock(Payment::class);
    $mockPayment->id = $paymentId; // Ensure the mock has relevant properties if resource uses them

    // Mock company->payments() chain
    $companyPaymentsQueryBuilderMock = Mockery::mock();
    $mockCompany->shouldReceive('payments')->once()->andReturn($companyPaymentsQueryBuilderMock);
    $companyPaymentsQueryBuilderMock->shouldReceive('whereCustomer')->with($customerId)->once()->andReturn($companyPaymentsQueryBuilderMock);
    $companyPaymentsQueryBuilderMock->shouldReceive('where')->with('id', $paymentId)->once()->andReturn($companyPaymentsQueryBuilderMock);
    $companyPaymentsQueryBuilderMock->shouldReceive('first')->once()->andReturn($mockPayment); // Payment found

    // Mock PaymentResource instantiation
    Mockery::mock('overload:' . PaymentResource::class);
    PaymentResource::shouldReceive('__construct')
        ->once()
        ->with(Mockery::on(function ($arg) use ($mockPayment) {
            return $arg === $mockPayment; // Ensure constructor receives the correct payment mock
        }))
        ->andReturn(Mockery::mock(PaymentResource::class)); // Return a mock instance of the resource

    // Act
    $response = $controller->show($mockCompany, $paymentId);

    // Assert
    expect($response)->toBeInstanceOf(PaymentResource::class);
});

test('show method returns 404 error response when payment is not found', function () {
    // Arrange
    $controller = new PaymentsController();
    $paymentId = 99; // ID that won't be found
    $customerId = 1;

    $mockCompany = Mockery::mock(Company::class);

    // Mock company->payments() chain
    $companyPaymentsQueryBuilderMock = Mockery::mock();
    $mockCompany->shouldReceive('payments')->once()->andReturn($companyPaymentsQueryBuilderMock);
    $companyPaymentsQueryBuilderMock->shouldReceive('whereCustomer')->with($customerId)->once()->andReturn($companyPaymentsQueryBuilderMock);
    $companyPaymentsQueryBuilderMock->shouldReceive('where')->with('id', $paymentId)->once()->andReturn($companyPaymentsQueryBuilderMock);
    $companyPaymentsQueryBuilderMock->shouldReceive('first')->once()->andReturn(null); // Payment not found

    // Act
    $response = $controller->show($mockCompany, $paymentId);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(404)
        ->and($response->getData(true))->toBe(['error' => 'payment_not_found']);
});




afterEach(function () {
    Mockery::close();
});
