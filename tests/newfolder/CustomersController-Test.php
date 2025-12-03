<?php

use Crater\Http\Controllers\V1\Admin\Customer\CustomersController;
use Crater\Http\Requests\DeleteCustomersRequest;
use Crater\Http\Requests\CustomerRequest;
use Crater\Http\Resources\CustomerResource;
use Crater\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Mockery as m;

beforeEach(function () {
    $this->controller = m::mock(CustomersController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->andReturn(true)->byDefault();
});

test('index method displays a listing of customers with default limit', function () {
    $request = m::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('all')->andReturn([]);

    // Mock static Customer::with() by swapping
    $customerQuery = m::mock(Builder::class);
    $customerMock = m::mock('alias:' . Customer::class);
    $customerMock->shouldReceive('with')->with('creator')->andReturn($customerQuery);

    $customerQuery->shouldReceive('whereCompany')->andReturn($customerQuery);
    $customerQuery->shouldReceive('applyFilters')->with([])->andReturn($customerQuery);

    DB::shouldReceive('raw')->with('sum(invoices.base_due_amount) as base_due_amount')->andReturn(m::mock(\Illuminate\Database\Query\Expression::class));
    DB::shouldReceive('raw')->with('sum(invoices.due_amount) as due_amount')->andReturn(m::mock(\Illuminate\Database\Query\Expression::class));

    $customerQuery->shouldReceive('select')->andReturn($customerQuery);
    $customerQuery->shouldReceive('groupBy')->with('customers.id')->andReturn($customerQuery);
    $customerQuery->shouldReceive('leftJoin')->with('invoices', 'customers.id', '=', 'invoices.customer_id')->andReturn($customerQuery);

    $paginatedCustomers = m::mock(LengthAwarePaginator::class);
    $customerQuery->shouldReceive('paginateData')->with(10)->andReturn($paginatedCustomers);

    $resourceCollectionMock = m::mock(AnonymousResourceCollection::class);
    $customerResourceMock = m::mock('alias:' . CustomerResource::class);
    $customerResourceMock->shouldReceive('collection')->with($paginatedCustomers)->andReturn($resourceCollectionMock);

    $resourceCollectionMock->shouldReceive('additional')->once()->andReturnUsing(function ($data) use ($resourceCollectionMock) {
        expect($data)->toEqual(['meta' => ['customer_total_count' => 5]]);
        return $resourceCollectionMock;
    });

    $totalCustomerQuery = m::mock(Builder::class);
    $customerMock->shouldReceive('whereCompany')->once()->andReturn($totalCustomerQuery);
    $totalCustomerQuery->shouldReceive('count')->once()->andReturn(5);

    $response = $this->controller->index($request);

    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
});

test('index method displays a listing of customers with a custom limit', function () {
    $request = m::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    $request->limit = 20;
    $request->shouldReceive('all')->andReturn(['limit' => 20]);

    $customerQuery = m::mock(Builder::class);
    $customerMock = m::mock('alias:' . Customer::class);
    $customerMock->shouldReceive('with')->with('creator')->andReturn($customerQuery);

    $customerQuery->shouldReceive('whereCompany')->andReturn($customerQuery);
    $customerQuery->shouldReceive('applyFilters')->with(['limit' => 20])->andReturn($customerQuery);

    DB::shouldReceive('raw')->andReturn(m::mock(\Illuminate\Database\Query\Expression::class));
    $customerQuery->shouldReceive('select')->andReturn($customerQuery);
    $customerQuery->shouldReceive('groupBy')->with('customers.id')->andReturn($customerQuery);
    $customerQuery->shouldReceive('leftJoin')->with('invoices', 'customers.id', '=', 'invoices.customer_id')->andReturn($customerQuery);

    $paginatedCustomers = m::mock(LengthAwarePaginator::class);
    $customerQuery->shouldReceive('paginateData')->with(20)->andReturn($paginatedCustomers);

    $resourceCollectionMock = m::mock(AnonymousResourceCollection::class);
    $customerResourceMock = m::mock('alias:' . CustomerResource::class);
    $customerResourceMock->shouldReceive('collection')->with($paginatedCustomers)->andReturn($resourceCollectionMock);
    $resourceCollectionMock->shouldReceive('additional')->once()->andReturnSelf();

    $totalCustomerQuery = m::mock(Builder::class);
    $customerMock->shouldReceive('whereCompany')->once()->andReturn($totalCustomerQuery);
    $totalCustomerQuery->shouldReceive('count')->once()->andReturn(10);

    $response = $this->controller->index($request);

    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
});

test('store method creates a new customer', function () {
    $request = m::mock(CustomerRequest::class);
    $customer = m::mock(Customer::class);

    $customerMock = m::mock('alias:' . Customer::class);
    $customerMock->shouldReceive('createCustomer')->with($request)->andReturn($customer);

    $resourceMock = m::mock(CustomerResource::class);
    $resourceMock->shouldReceive('toArray')->andReturn([]);

    m::mock('overload:' . CustomerResource::class)
        ->shouldReceive('__construct')
        ->with($customer)
        ->andReturnUsing(function ($customer) use ($resourceMock) {
            // do nothing; instance methods are on $resourceMock
            return null;
        });

    m::mock('overload:' . CustomerResource::class)
        ->shouldReceive('toArray')
        ->andReturn([]);

    $response = $this->controller->store($request);

    expect($response)->toBeInstanceOf(CustomerResource::class);
});

test('show method displays a specific customer', function () {
    $customer = m::mock(Customer::class);

    $resourceMock = m::mock(CustomerResource::class);
    $resourceMock->shouldReceive('toArray')->andReturn([]);

    m::mock('overload:' . CustomerResource::class)
        ->shouldReceive('__construct')
        ->with($customer)
        ->andReturnUsing(function ($customer) use ($resourceMock) {
            return null;
        });

    m::mock('overload:' . CustomerResource::class)
        ->shouldReceive('toArray')
        ->andReturn([]);

    $response = $this->controller->show($customer);

    expect($response)->toBeInstanceOf(CustomerResource::class);
});

test('update method updates an existing customer successfully', function () {
    $request = m::mock(CustomerRequest::class);
    $customer = m::mock(Customer::class);
    $updatedCustomer = m::mock(Customer::class);

    $customerMock = m::mock('alias:' . Customer::class);
    $customerMock->shouldReceive('updateCustomer')->with($request, $customer)->andReturn($updatedCustomer);

    $resourceMock = m::mock(CustomerResource::class);
    $resourceMock->shouldReceive('toArray')->andReturn([]);

    m::mock('overload:' . CustomerResource::class)
        ->shouldReceive('__construct')
        ->with($updatedCustomer)
        ->andReturnUsing(function ($customer) use ($resourceMock) {
            return null;
        });

    m::mock('overload:' . CustomerResource::class)
        ->shouldReceive('toArray')
        ->andReturn([]);

    $response = $this->controller->update($request, $customer);

    expect($response)->toBeInstanceOf(CustomerResource::class);
});

test('update method returns error if currency cannot be edited', function () {
    $request = m::mock(CustomerRequest::class);
    $customer = m::mock(Customer::class);

    $customerMock = m::mock('alias:' . Customer::class);
    $customerMock->shouldReceive('updateCustomer')->with($request, $customer)->andReturn('you_cannot_edit_currency');

    $jsonResponseBuilder = m::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
    app()->instance('Illuminate\Contracts\Routing\ResponseFactory', $jsonResponseBuilder);
    $jsonResponseBuilder->shouldReceive('json')
        ->with('you_cannot_edit_currency', 'Cannot change currency once transactions created', 409)
        ->once()
        ->andReturn(new JsonResponse([
            'error' => 'you_cannot_edit_currency',
            'message' => 'Cannot change currency once transactions created',
        ], 409));

    $response = $this->controller->update($request, $customer);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(409);
    expect($response->getData(true))->toEqual([
        'error' => 'you_cannot_edit_currency',
        'message' => 'Cannot change currency once transactions created',
    ]);
});

test('delete method removes customers successfully', function () {
    $request = m::mock(DeleteCustomersRequest::class);
    $request->ids = [1, 2, 3];

    $this->controller->shouldReceive('authorize')->with('delete multiple customers')->andReturn(true);

    $customerMock = m::mock('alias:' . Customer::class);
    $customerMock->shouldReceive('deleteCustomers')->with([1, 2, 3])->once()->andReturn(null);

    $jsonResponseBuilder = m::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
    app()->instance('Illuminate\Contracts\Routing\ResponseFactory', $jsonResponseBuilder);
    $jsonResponseBuilder->shouldReceive('json')->with(['success' => true])->andReturn(
        new JsonResponse(['success' => true])
    );

    $response = $this->controller->delete($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);
});

afterEach(function () {
    Mockery::close();
});