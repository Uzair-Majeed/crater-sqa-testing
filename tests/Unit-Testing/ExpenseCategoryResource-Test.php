<?php

use Mockery as m;
use Crater\Http\Resources\ExpenseCategoryResource;
use Crater\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Helper to disable constructor of CompanyResource for mocking purposes
// This ensures that when new CompanyResource() is called, Mockery intercepts it.
function mockCompanyResourceClass()
{
    m::mock('overload:' . CompanyResource::class);
}

test('toArray includes basic properties and omits company when relationship does not exist', function () {
    // Overload CompanyResource to prevent actual instantiation
    mockCompanyResourceClass();

    // Mock the underlying ExpenseCategory model (resource's $this->resource)
    $expenseCategoryModel = m::mock('stdClass');
    $expenseCategoryModel->id = 1;
    $expenseCategoryModel->name = 'Office Supplies';
    $expenseCategoryModel->description = 'Pens, paper, etc.';
    $expenseCategoryModel->company_id = 10;
    $expenseCategoryModel->amount = 123.45;
    $expenseCategoryModel->formattedCreatedAt = '2023-01-01 10:00:00';

    // Mock the Eloquent relationship builder (e.g., BelongsTo instance)
    $relationMock = m::mock(BelongsTo::class);
    $relationMock->shouldReceive('exists')->once()->andReturn(false); // Simulate company not existing

    // The resource will call $this->company() on its underlying model
    $expenseCategoryModel->shouldReceive('company')->once()->andReturn($relationMock);

    // Create the resource instance
    $resource = new ExpenseCategoryResource($expenseCategoryModel);
    // Mock the request object, though it's not directly used in this specific toArray logic
    $request = m::mock(Request::class);

    // Call the toArray method
    $result = $resource->toArray($request);

    // Assert that basic properties are correctly transformed
    expect($result)->toMatchArray([
        'id' => 1,
        'name' => 'Office Supplies',
        'description' => 'Pens, paper, etc.',
        'company_id' => 10,
        'amount' => 123.45,
        'formatted_created_at' => '2023-01-01 10:00:00',
    ]);

    // Assert that the 'company' key is NOT present, as the relationship didn't exist
    expect($result)->not->toHaveKey('company');
});

test('toArray includes basic properties and company resource when relationship exists', function () {
    // Overload CompanyResource to prevent actual instantiation
    mockCompanyResourceClass();

    // Mock the underlying Company model that would be eager-loaded or accessed
    $companyModel = m::mock('stdClass');
    $companyModel->id = 20;
    $companyModel->name = 'Test Company';

    // Mock the underlying ExpenseCategory model
    $expenseCategoryModel = m::mock('stdClass');
    $expenseCategoryModel->id = 2;
    $expenseCategoryModel->name = 'Travel Expenses';
    $expenseCategoryModel->description = 'Flight and hotel';
    $expenseCategoryModel->company_id = 10;
    $expenseCategoryModel->amount = 500.00;
    $expenseCategoryModel->formattedCreatedAt = '2023-02-01 11:00:00';
    $expenseCategoryModel->company = $companyModel; // Ensure the 'company' property is set on the model

    // Mock the Eloquent relationship builder
    $relationMock = m::mock(BelongsTo::class);
    $relationMock->shouldReceive('exists')->once()->andReturn(true); // Simulate company existing

    // The resource will call $this->company() on its underlying model
    $expenseCategoryModel->shouldReceive('company')->once()->andReturn($relationMock);

    // Expect CompanyResource to be instantiated with the correct company model
    // and to have its toArray method called.
    CompanyResource::shouldReceive('__construct')
                    ->once()
                    ->with(m::mustBe($companyModel))
                    ->andReturnSelf(); // Allow chaining calls
    CompanyResource::shouldReceive('toArray')
                    ->once()
                    ->with(m::any()) // Expect toArray to be called with any request
                    ->andReturn(['company_transformed_data' => true, 'id' => $companyModel->id]); // Return mock transformed data

    // Create the resource instance
    $resource = new ExpenseCategoryResource($expenseCategoryModel);
    $request = m::mock(Request::class);

    // Call the toArray method
    $result = $resource->toArray($request);

    // Assert that basic properties are correct and 'company' key is present with transformed data
    expect($result)->toMatchArray([
        'id' => 2,
        'name' => 'Travel Expenses',
        'description' => 'Flight and hotel',
        'company_id' => 10,
        'amount' => 500.00,
        'formatted_created_at' => '2023-02-01 11:00:00',
        'company' => ['company_transformed_data' => true, 'id' => $companyModel->id],
    ]);
});

test('toArray handles null properties gracefully', function () {
    mockCompanyResourceClass();

    $expenseCategoryModel = m::mock('stdClass');
    $expenseCategoryModel->id = null;
    $expenseCategoryModel->name = null;
    $expenseCategoryModel->description = null;
    $expenseCategoryModel->company_id = null;
    $expenseCategoryModel->amount = null;
    $expenseCategoryModel->formattedCreatedAt = null;

    // Simulate company not existing
    $relationMock = m::mock(BelongsTo::class);
    $relationMock->shouldReceive('exists')->once()->andReturn(false);
    $expenseCategoryModel->shouldReceive('company')->once()->andReturn($relationMock);

    $resource = new ExpenseCategoryResource($expenseCategoryModel);
    $request = m::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toMatchArray([
        'id' => null,
        'name' => null,
        'description' => null,
        'company_id' => null,
        'amount' => null,
        'formatted_created_at' => null,
    ]);
    expect($result)->not->toHaveKey('company');
});

test('toArray handles zero amount property', function () {
    mockCompanyResourceClass();

    $expenseCategoryModel = m::mock('stdClass');
    $expenseCategoryModel->id = 3;
    $expenseCategoryModel->name = 'Zero Expense';
    $expenseCategoryModel->description = 'An item with no cost';
    $expenseCategoryModel->company_id = 10;
    $expenseCategoryModel->amount = 0.00;
    $expenseCategoryModel->formattedCreatedAt = '2023-03-01 12:00:00';

    // Simulate company not existing
    $relationMock = m::mock(BelongsTo::class);
    $relationMock->shouldReceive('exists')->once()->andReturn(false);
    $expenseCategoryModel->shouldReceive('company')->once()->andReturn($relationMock);

    $resource = new ExpenseCategoryResource($expenseCategoryModel);
    $request = m::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toMatchArray([
        'id' => 3,
        'name' => 'Zero Expense',
        'description' => 'An item with no cost',
        'company_id' => 10,
        'amount' => 0.00,
        'formatted_created_at' => '2023-03-01 12:00:00',
    ]);
    expect($result)->not->toHaveKey('company');
});
