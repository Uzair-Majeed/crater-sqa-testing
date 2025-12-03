<?php

use Crater\Http\Controllers\V1\Customer\General\DashboardController;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery as m;

beforeEach(function () {
    // Unload facades before mocking
    if (class_exists('Illuminate\Support\Facades\Auth', false)) {
        // Remove facade instance for Auth to allow proper mocking via 'overload'
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('auth');
        // Remove cached facade accessor
        if (method_exists(\Illuminate\Support\Facades\Auth::class, 'setFacadeApplication')) {
            \Illuminate\Support\Facades\Auth::setFacadeApplication(null);
        }
    }
});

test('it returns dashboard data for a customer with existing records', function () {
    $userId = 1;
    $request = Request::create('/');

    // Mock Auth Facade
    $authMock = m::mock('overload:\Illuminate\Support\Facades\Auth');
    $authGuardMock = m::mock();
    $authMock->shouldReceive('guard')->with('customer')->andReturn($authGuardMock);
    $authGuardMock->shouldReceive('user')->andReturn((object)['id' => $userId]);

    // Mock Invoice Model for sum('due_amount') chain
    $invoiceSumQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    m::mock('overload:' . Invoice::class)
        ->shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturn($invoiceSumQueryBuilder)
        ->times(3); // To allow all 3 Invoice::whereCustomer calls
    $invoiceSumQueryBuilder->shouldReceive('where')
        ->with('status', '<>', 'DRAFT')
        ->andReturnSelf()
        ->shouldReceive('sum')
        ->with('due_amount')
        ->andReturn(1500.50);

    // Mock Invoice Model for count() chain
    $invoiceCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
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

    // Make Invoice::whereCustomer return correct query builder instance each call
    $invoiceOverloadMock = m::self();
    Invoice::shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturnUsing(function () use (&$invoiceSumQueryBuilder, &$invoiceCountQueryBuilder, &$recentInvoicesQueryBuilder) {
            static $call = 0;
            $instances = [
                $invoiceSumQueryBuilder,
                $invoiceCountQueryBuilder,
                $recentInvoicesQueryBuilder,
            ];
            return $instances[$call++];
        });

    // Mock Estimate Model for count() chain
    $estimateCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $estimateCountQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('count')->andReturn(5);

    // Mock Estimate Model for recentEstimates chain
    $recentEstimatesCollection = collect([
        (object) ['id' => 10, 'estimate_number' => 'EST-001'],
        (object) ['id' => 11, 'estimate_number' => 'EST-002'],
    ]);
    $recentEstimatesQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $recentEstimatesQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('take')->with(5)->andReturnSelf()->shouldReceive('latest')->andReturnSelf()->shouldReceive('get')->andReturn($recentEstimatesCollection);

    // Make Estimate::whereCustomer return correct query builder instance each call
    $estimateOverloadMock = m::self();
    Estimate::shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturnUsing(function () use (&$estimateCountQueryBuilder, &$recentEstimatesQueryBuilder) {
            static $call = 0;
            $instances = [
                $estimateCountQueryBuilder,
                $recentEstimatesQueryBuilder,
            ];
            return $instances[$call++];
        });

    // Mock Payment Model for count() chain
    $paymentCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $paymentCountQueryBuilder->shouldReceive('count')->andReturn(3);

    // Make Payment::whereCustomer return correct query builder instance
    Payment::shouldReceive('whereCustomer')->with($userId)->andReturn($paymentCountQueryBuilder);

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
    $authMock = m::mock('overload:\Illuminate\Support\Facades\Auth');
    $authGuardMock = m::mock();
    $authMock->shouldReceive('guard')->with('customer')->andReturn($authGuardMock);
    $authGuardMock->shouldReceive('user')->andReturn((object)['id' => $userId]);

    // Mock Invoice Model for zero data
    $invoiceSumQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $invoiceSumQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('sum')->with('due_amount')->andReturn(0.00);

    $invoiceCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $invoiceCountQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('count')->andReturn(0);

    $recentInvoicesQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $recentInvoicesQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('take')->with(5)->andReturnSelf()->shouldReceive('latest')->andReturnSelf()->shouldReceive('get')->andReturn(collect([]));

    // Make Invoice::whereCustomer return correct query builder instance each call
    Invoice::shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturnUsing(function () use (&$invoiceSumQueryBuilder, &$invoiceCountQueryBuilder, &$recentInvoicesQueryBuilder) {
            static $call = 0;
            $instances = [
                $invoiceSumQueryBuilder,
                $invoiceCountQueryBuilder,
                $recentInvoicesQueryBuilder,
            ];
            return $instances[$call++];
        });

    // Mock Estimate Model for zero data
    $estimateCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $estimateCountQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('count')->andReturn(0);

    $recentEstimatesQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $recentEstimatesQueryBuilder->shouldReceive('where')->with('status', '<>', 'DRAFT')->andReturnSelf()->shouldReceive('take')->with(5)->andReturnSelf()->shouldReceive('latest')->andReturnSelf()->shouldReceive('get')->andReturn(collect([]));

    // Make Estimate::whereCustomer return correct query builder instance each call
    Estimate::shouldReceive('whereCustomer')
        ->with($userId)
        ->andReturnUsing(function () use (&$estimateCountQueryBuilder, &$recentEstimatesQueryBuilder) {
            static $call = 0;
            $instances = [
                $estimateCountQueryBuilder,
                $recentEstimatesQueryBuilder,
            ];
            return $instances[$call++];
        });

    // Mock Payment Model for zero data
    $paymentCountQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $paymentCountQueryBuilder->shouldReceive('count')->andReturn(0);

    // Make Payment::whereCustomer return correct query builder instance
    Payment::shouldReceive('whereCustomer')->with($userId)->andReturn($paymentCountQueryBuilder);

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
    $authMock = m::mock('overload:\Illuminate\Support\Facades\Auth');
    $authGuardMock = m::mock();
    $authMock->shouldReceive('guard')->with('customer')->andReturn($authGuardMock);
    $authGuardMock->shouldReceive('user')->andReturn(null);

    // Act & Assert
    $controller = new DashboardController();

    expect(fn () => $controller($request))
        ->toThrow(\Error::class, 'Attempt to read property "id" on null');
});

afterEach(function () {
    Mockery::close();
});