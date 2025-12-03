<?php

use Mockery as m;
use Crater\Http\Resources\ExpenseCategoryResource;
use Crater\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Helper to disable constructor of CompanyResource for mocking purposes
function mockCompanyResourceClass()
{
    m::mock('overload:' . CompanyResource::class);
}

test('toArray includes basic properties and omits company when relationship does not exist', function () {
    mockCompanyResourceClass();

    $expenseCategoryModel = m::mock('stdClass');
    $expenseCategoryModel->id = 1;
    $expenseCategoryModel->name = 'Office Supplies';
    $expenseCategoryModel->description = 'Pens, paper, etc.';
    $expenseCategoryModel->company_id = 10;
    $expenseCategoryModel->amount = 123.45;
    $expenseCategoryModel->formattedCreatedAt = '2023-01-01 10:00:00';

    // Simulate company relation does NOT exist
    $relationMock = m::mock(BelongsTo::class);
    $relationMock->shouldReceive('exists')->once()->andReturn(false);

    $expenseCategoryModel->shouldReceive('company')->once()->andReturn($relationMock);

    // The resource might return 'company' => null when relation does not exist,
    // so we check for that.
    $resource = new ExpenseCategoryResource($expenseCategoryModel);
    $request = m::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toMatchArray([
        'id' => 1,
        'name' => 'Office Supplies',
        'description' => 'Pens, paper, etc.',
        'company_id' => 10,
        'amount' => 123.45,
        'formatted_created_at' => '2023-01-01 10:00:00',
        'company' => null, // Ensure if present it is null
    ]);
});

test('toArray includes basic properties and company resource when relationship exists', function () {
    mockCompanyResourceClass();

    $companyModel = m::mock('stdClass');
    $companyModel->id = 20;
    $companyModel->name = 'Test Company';

    $expenseCategoryModel = m::mock('stdClass');
    $expenseCategoryModel->id = 2;
    $expenseCategoryModel->name = 'Travel Expenses';
    $expenseCategoryModel->description = 'Flight and hotel';
    $expenseCategoryModel->company_id = 10;
    $expenseCategoryModel->amount = 500.00;
    $expenseCategoryModel->formattedCreatedAt = '2023-02-01 11:00:00';
    $expenseCategoryModel->company = $companyModel;

    $relationMock = m::mock(BelongsTo::class);
    $relationMock->shouldReceive('exists')->once()->andReturn(true);

    $expenseCategoryModel->shouldReceive('company')->once()->andReturn($relationMock);

    // Overload CompanyResource and set up expectations for toArray instance method
    $companyResourceMock = m::mock('overload:' . CompanyResource::class);
    $companyResourceMock->shouldReceive('toArray')
        ->once()
        ->with(m::any())
        ->andReturn(['company_transformed_data' => true, 'id' => $companyModel->id]);

    $resource = new ExpenseCategoryResource($expenseCategoryModel);
    $request = m::mock(Request::class);

    $result = $resource->toArray($request);

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
        'company' => null, // Ensure if present it is null
    ]);
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
        'company' => null, // Ensure if present it is null
    ]);
});

afterEach(function () {
    Mockery::close();
});