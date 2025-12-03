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

// Use a class-level setup for the controller and its authorize method
beforeEach(function () {
    // Create a concrete mock for CustomersController and partially mock it to control `authorize`
    $this->controller = m::mock(CustomersController::class)->makePartial();
    // Ensure the authorize method always returns true for the purpose of these unit tests,
    // as policy testing is out of scope.
    $this->controller->shouldReceive('authorize')
                     ->andReturn(true)
                     ->byDefault(); // Apply to all calls unless overridden
});

test('index method displays a listing of customers with default limit', function () {
    $request = m::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(false);
    $request->shouldReceive('all')->andReturn([]); // No filters

    // Mock Customer model's static methods and chained calls
    $customerQuery = m::mock(Builder::class);
    Customer::shouldReceive('with')->with('creator')->andReturn($customerQuery);
    $customerQuery->shouldReceive('whereCompany')->andReturn($customerQuery);
    $customerQuery->shouldReceive('applyFilters')->with([])->andReturn($customerQuery);

    // Mock DB::raw calls
    DB::shouldReceive('raw')
      ->with('sum(invoices.base_due_amount) as base_due_amount')
      ->andReturn(m::mock(\Illuminate\Database\Query\Expression::class));
    DB::shouldReceive('raw')
      ->with('sum(invoices.due_amount) as due_amount')
      ->andReturn(m::mock(\Illuminate\Database\Query\Expression::class));

    $customerQuery->shouldReceive('select')->andReturn($customerQuery);
    $customerQuery->shouldReceive('groupBy')->with('customers.id')->andReturn($customerQuery);
    $customerQuery->shouldReceive('leftJoin')->with('invoices', 'customers.id', '=', 'invoices.customer_id')->andReturn($customerQuery);

    $paginatedCustomers = m::mock(LengthAwarePaginator::class);
    $customerQuery->shouldReceive('paginateData')->with(10)->andReturn($paginatedCustomers); // Default limit is 10

    // Mock the AnonymousResourceCollection that CustomerResource::collection returns
    $anonymousResourceCollection = m::mock(AnonymousResourceCollection::class);
    CustomerResource::shouldReceive('collection')->with($paginatedCustomers)->andReturn($anonymousResourceCollection);

    // Mock the `additional` method on the anonymous resource collection
    $anonymousResourceCollection->shouldReceive('additional')->once()->andReturnUsing(function ($data) use ($anonymousResourceCollection) {
        $this->assertEquals(['meta' => ['customer_total_count' => 5]], $data); // Assert expected meta data
        return $anonymousResourceCollection; // Return self for chaining
    });

    // Mock the count for additional meta data
    $totalCustomerQuery = m::mock(Builder::class);
    Customer::shouldReceive('whereCompany')->once()->andReturn($totalCustomerQuery);
    $totalCustomerQuery->shouldReceive('count')->once()->andReturn(5); // Example count for total

    // Act
    $response = $this->controller->index($request);

    // Assert
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
});

test('index method displays a listing of customers with a custom limit', function () {
    $request = m::mock(Request::class);
    $request->shouldReceive('has')->with('limit')->andReturn(true);
    // Simulate property access for $request->limit
    $request->limit = 20;
    $request->shouldReceive('all')->andReturn(['limit' => 20]); // Filters with custom limit

    $customerQuery = m::mock(Builder::class);
    Customer::shouldReceive('with')->with('creator')->andReturn($customerQuery);
    $customerQuery->shouldReceive('whereCompany')->andReturn($customerQuery);
    $customerQuery->shouldReceive('applyFilters')->with(['limit' => 20])->andReturn($customerQuery);

    // Mock DB::raw calls (can be generic for this test as specific content is tested in default limit case)
    DB::shouldReceive('raw')->andReturn(m::mock(\Illuminate\Database\Query\Expression::class));
    $customerQuery->shouldReceive('select')->andReturn($customerQuery);
    $customerQuery->shouldReceive('groupBy')->with('customers.id')->andReturn($customerQuery);
    $customerQuery->shouldReceive('leftJoin')->with('invoices', 'customers.id', '=', 'invoices.customer_id')->andReturn($customerQuery);

    $paginatedCustomers = m::mock(LengthAwarePaginator::class);
    $customerQuery->shouldReceive('paginateData')->with(20)->andReturn($paginatedCustomers); // Custom limit here

    $anonymousResourceCollection = m::mock(AnonymousResourceCollection::class);
    CustomerResource::shouldReceive('collection')->with($paginatedCustomers)->andReturn($anonymousResourceCollection);
    $anonymousResourceCollection->shouldReceive('additional')->once()->andReturnSelf(); // Just ensure it's called

    $totalCustomerQuery = m::mock(Builder::class);
    Customer::shouldReceive('whereCompany')->once()->andReturn($totalCustomerQuery);
    $totalCustomerQuery->shouldReceive('count')->once()->andReturn(10); // Example count

    // Act
    $response = $this->controller->index($request);

    // Assert
    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
});

test('store method creates a new customer', function () {
    $request = m::mock(CustomerRequest::class);
    $customer = m::mock(Customer::class);

    Customer::shouldReceive('createCustomer')->with($request)->andReturn($customer);

    // Intercept the constructor call of CustomerResource
    m::on(CustomerResource::class)
        ->shouldReceive('__construct')
        ->with($customer)
        ->once()
        ->andReturn(null); // Return null for __construct to allow the object to be created.

    // Act
    $response = $this->controller->store($request);

    // Assert
    expect($response)->toBeInstanceOf(CustomerResource::class);
});

test('show method displays a specific customer', function () {
    $customer = m::mock(Customer::class);

    // Intercept the constructor call of CustomerResource
    m::on(CustomerResource::class)
        ->shouldReceive('__construct')
        ->with($customer)
        ->once()
        ->andReturn(null);

    // Act
    $response = $this->controller->show($customer);

    // Assert
    expect($response)->toBeInstanceOf(CustomerResource::class);
});

test('update method updates an existing customer successfully', function () {
    $request = m::mock(CustomerRequest::class);
    $customer = m::mock(Customer::class);
    $updatedCustomer = m::mock(Customer::class); // A separate mock for the returned updated customer

    Customer::shouldReceive('updateCustomer')->with($request, $customer)->andReturn($updatedCustomer);

    // Intercept the constructor call of CustomerResource
    m::on(CustomerResource::class)
        ->shouldReceive('__construct')
        ->with($updatedCustomer)
        ->once()
        ->andReturn(null);

    // Act
    $response = $this->controller->update($request, $customer);

    // Assert
    expect($response)->toBeInstanceOf(CustomerResource::class);
});

test('update method returns error if currency cannot be edited', function () {
    $request = m::mock(CustomerRequest::class);
    $customer = m::mock(Customer::class);

    // Simulate the scenario where updateCustomer returns a string error
    Customer::shouldReceive('updateCustomer')->with($request, $customer)->andReturn('you_cannot_edit_currency');

    // To unit test a helper function like `respondJson` that's not easily mockable,
    // we assume it eventually uses standard framework features (like `response()->json()`).
    // So, we mock the `ResponseFactory` to control its output.
    $jsonResponseBuilder = m::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
    app()->instance('Illuminate\Contracts\Routing\ResponseFactory', $jsonResponseBuilder);
    $jsonResponseBuilder->shouldReceive('json')
        ->with('you_cannot_edit_currency', 'Cannot change currency once transactions created', 409) // Assuming specific arguments and a 409 status
        ->once()
        ->andReturn(new JsonResponse([
            'error' => 'you_cannot_edit_currency',
            'message' => 'Cannot change currency once transactions created',
        ], 409));

    // Act
    $response = $this->controller->update($request, $customer);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(409);
    expect($response->getData(true))->toEqual([
        'error' => 'you_cannot_edit_currency',
        'message' => 'Cannot change currency once transactions created',
    ]);
});

test('delete method removes customers successfully', function () {
    $request = m::mock(DeleteCustomersRequest::class);
    $request->ids = [1, 2, 3]; // Simulate request IDs

    $this->controller->shouldReceive('authorize')->with('delete multiple customers')->andReturn(true);

    Customer::shouldReceive('deleteCustomers')->with([1, 2, 3])->once()->andReturn(null); // Assuming it returns void or success

    // Mock the response() helper and its json method
    $jsonResponseBuilder = m::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
    app()->instance('Illuminate\Contracts\Routing\ResponseFactory', $jsonResponseBuilder);
    $jsonResponseBuilder->shouldReceive('json')->with(['success' => true])->andReturn(
        new JsonResponse(['success' => true])
    );

    // Act
    $response = $this->controller->delete($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['success' => true]);
});

 

afterEach(function () {
    Mockery::close();
});
