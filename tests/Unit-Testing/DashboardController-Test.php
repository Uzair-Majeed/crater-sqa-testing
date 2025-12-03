<?php

use Crater\Http\Controllers\V1\Customer\General\DashboardController;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery as m;

test('it returns dashboard data for a customer with existing records', function () {
    $userId = 1;
    $request = Request::create('/');

    // Mock Auth Facade
    m::mock('overload:\Illuminate\Support\Facades\Auth')
        ->shouldReceive('guard')
        ->with('customer')
        ->andReturnSelf()
        ->shouldReceive('user')
        ->andReturn((object) ['id' => $userId])
        ->getMock();

    // Mock Invoice Model for sum('due_amount') chain
    $invoiceSumQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Invoice::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($invoiceSumQueryBuilder)
        ->once();
    $invoiceSumQueryBuilder->shouldReceive('where')
        ->with('status', '<>', 'DRAFT')
        ->andReturnSelf()
        ->shouldReceive('sum')
        ->with('due_amount')
        ->andReturn(1500.50);

    // Mock Invoice Model for count() chain
    $invoiceCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Invoice::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($invoiceCountQueryBuilder)
        ->once();
    $invoiceCountQueryBuilder->shouldReceive('where')
        ->with('status', '<>', 'DRAFT')
        ->andReturnSelf()
        ->shouldReceive('count')
        ->andReturn(10);

    // Mock Invoice Model for recentInvoices chain
    $recentInvoicesCollection = collect([
        (object) ['id' => 1, 'invoice_number' => 'INV-001', 'due_amount' => 100],
        (object) ['id' => 2, 'invoice_number' => 'INV-002', 'due_amount' => 200],
    ]);
    $recentInvoicesQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Invoice::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($recentInvoicesQueryBuilder)
        ->once();
    $recentInvoicesQueryBuilder->shouldReceive('where')
        ->with('status', '<>', 'DRAFT')
        ->andReturnSelf()
        ->shouldReceive('take')
        ->with(5)
        ->andReturnSelf()
        ->shouldReceive('latest')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn($recentInvoicesCollection);

    // Mock Estimate Model for count() chain
    $estimateCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Estimate::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($estimateCountQueryBuilder)
        ->once();
    $estimateCountQueryBuilder->shouldReceive('where')
        ->with('status', '<>', 'DRAFT')
        ->andReturnSelf()
        ->shouldReceive('count')
        ->andReturn(5);

    // Mock Estimate Model for recentEstimates chain
    $recentEstimatesCollection = collect([
        (object) ['id' => 10, 'estimate_number' => 'EST-001'],
        (object) ['id' => 11, 'estimate_number' => 'EST-002'],
    ]);
    $recentEstimatesQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Estimate::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($recentEstimatesQueryBuilder)
        ->once();
    $recentEstimatesQueryBuilder->shouldReceive('where')
        ->with('status', '<>', 'DRAFT')
        ->andReturnSelf()
        ->shouldReceive('take')
        ->with(5)
        ->andReturnSelf()
        ->shouldReceive('latest')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn($recentEstimatesCollection);

    // Mock Payment Model for count() chain
    $paymentCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Payment::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($paymentCountQueryBuilder)
        ->once();
    $paymentCountQueryBuilder->shouldReceive('count')
        ->andReturn(3);

    // Act
    $controller = new DashboardController();
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'due_amount' => 1500.50,
        'recentInvoices' => $recentInvoicesCollection->toArray(),
        'recentEstimates' => $recentEstimatesCollection->toArray(),
        'invoice_count' => 10,
        'estimate_count' => 5,
        'payment_count' => 3,
    ]);
});

test('it returns zero counts and empty collections for a customer with no records', function () {
    $userId = 2;
    $request = Request::create('/');

    // Mock Auth Facade
    m::mock('overload:\Illuminate\Support\Facades\Auth')
        ->shouldReceive('guard')
        ->with('customer')
        ->andReturnSelf()
        ->shouldReceive('user')
        ->andReturn((object) ['id' => $userId])
        ->getMock();

    // Mock Invoice Model for zero data
    $invoiceSumQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Invoice::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($invoiceSumQueryBuilder)
        ->once();
    $invoiceSumQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('sum')->with('due_amount')->andReturn(0.00);

    $invoiceCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Invoice::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($invoiceCountQueryBuilder)
        ->once();
    $invoiceCountQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('count')->andReturn(0);

    $recentInvoicesQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Invoice::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($recentInvoicesQueryBuilder)
        ->once();
    $recentInvoicesQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('take')->with(5)->andReturnSelf()->shouldReceive('latest')->andReturnSelf()->shouldReceive('get')->andReturn(collect([]));

    // Mock Estimate Model for zero data
    $estimateCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Estimate::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($estimateCountQueryBuilder)
        ->once();
    $estimateCountQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('count')->andReturn(0);

    $recentEstimatesQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Estimate::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($recentEstimatesQueryBuilder)
        ->once();
    $recentEstimatesQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('take')->with(5)->andReturnSelf()->shouldReceive('latest')->andReturnSelf()->shouldReceive('get')->andReturn(collect([]));

    // Mock Payment Model for zero data
    $paymentCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Payment::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($paymentCountQueryBuilder)
        ->once();
    $paymentCountQueryBuilder->shouldReceive('count')->andReturn(0);

    // Act
    $controller = new DashboardController();
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'due_amount' => 0.00,
        'recentInvoices' => [],
        'recentEstimates' => [],
        'invoice_count' => 0,
        'estimate_count' => 0,
        'payment_count' => 0,
    ]);
});

test('it throws an error if authenticated customer is null', function () {
    $request = Request::create('/');

    // Mock Auth Facade to return null for the user
    m::mock('overload:\Illuminate\Support\Facades\Auth')
        ->shouldReceive('guard')
        ->with('customer')
        ->andReturnSelf()
        ->shouldReceive('user')
        ->andReturn(null)
        ->getMock();

    // Act & Assert
    $controller = new DashboardController();

    // Expecting an Error because $user will be null, and it tries to access $user->id
    expect(fn () => $controller($request))
        ->toThrow(\Error::class, 'Attempt to read property "id" on null');
});




afterEach(function () {
    Mockery::close();
});
