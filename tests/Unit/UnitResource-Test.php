<?php

use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\UnitResource;
use Illuminate\Http\Request;

// Ensure Mockery expectations are cleared before each test
beforeEach(function () {
    Mockery::close();
});

test('toArray transforms unit with an existing company correctly', function () {
    // Expected output from a mocked CompanyResource
    $expectedCompanyArray = [
        'id' => 10,
        'name' => 'Acme Inc. (transformed)',
        'some_other_company_field' => 'value',
    ];

    // 1. Mock the underlying Company model that the Unit will be associated with
    $mockCompanyModel = Mockery::mock();
    $mockCompanyModel->id = $expectedCompanyArray['id'];
    $mockCompanyModel->name = 'Acme Inc.'; // Original name
    // Add any other properties the real CompanyResource might read
    $mockCompanyModel->currency = 'USD';

    // 2. Mock the relationship query builder for the 'company()' method on the Unit model.
    // This mock needs to respond to 'exists()' and return true.
    $mockCompanyQueryBuilder = Mockery::mock();
    $mockCompanyQueryBuilder->shouldReceive('exists')
                            ->once()
                            ->andReturn(true);

    // 3. Mock the underlying Unit model that UnitResource will wrap.
    $unitId = 1;
    $unitName = 'Kilogram';
    $unitCompanyId = $expectedCompanyArray['id'];

    $mockUnitModel = Mockery::mock();
    $mockUnitModel->id = $unitId;
    $mockUnitModel->name = $unitName;
    $mockUnitModel->company_id = $unitCompanyId;
    $mockUnitModel->company = $mockCompanyModel; // Set the actual related model for $this->company access

    // Mock the `company()` method on the Unit model to return the query builder.
    $mockUnitModel->shouldReceive('company')
                  ->once()
                  ->andReturn($mockCompanyQueryBuilder);

    // 4. Mock the CompanyResource class itself using an alias mock.
    // This allows us to intercept `new CompanyResource(...)` calls made by UnitResource.
    $companyResourceAlias = Mockery::mock('alias:' . CompanyResource::class);

    // Configure the alias mock to behave like a constructor, expecting the mockCompanyModel.
    // `andReturnSelf()` is crucial here to ensure the same mock instance is returned for subsequent method calls.
    $companyResourceAlias->shouldReceive('__construct')
                         ->once()
                         ->with($mockCompanyModel)
                         ->andReturnSelf(); // Constructor doesn't return, but Mockery needs to maintain the mock chain.

    // Configure the mock instance (which is the alias itself) to respond to `toArray()`.
    // This will be called when the parent UnitResource is resolved, processing its nested resources.
    $companyResourceAlias->shouldReceive('toArray')
                         ->once()
                         ->with(Mockery::type(Request::class))
                         ->andReturn($expectedCompanyArray);

    // 5. Create an instance of the UnitResource with the mocked Unit model.
    $unitResource = new UnitResource($mockUnitModel);

    // 6. Mock the Request object.
    $mockRequest = Mockery::mock(Request::class);

    // Act: Call the resolve method.
    // Calling `resolve()` ensures that Laravel's JsonResource processing logic,
    // including resolving nested resources and conditional attributes (like `when()`),
    // is correctly applied, mimicking how it would behave when returned from a controller.
    $result = $unitResource->resolve($mockRequest); // FIX: Changed to resolve()

    // Assert: Check the structure and values of the transformed array.
    expect($result)->toMatchArray([
        'id' => $unitId,
        'name' => $unitName,
        'company_id' => $unitCompanyId,
        'company' => $expectedCompanyArray, // This now comes directly from our mocked CompanyResource.
    ]);
});

test('toArray transforms unit without an existing company correctly', function () {
    // 1. Mock the relationship query builder for the 'company()' method on the Unit model.
    // This mock needs to respond to 'exists()' and return false.
    $mockCompanyQueryBuilder = Mockery::mock();
    $mockCompanyQueryBuilder->shouldReceive('exists')
                            ->once()
                            ->andReturn(false);

    // 2. Mock the underlying Unit model.
    $unitId = 2;
    $unitName = 'Liter';
    $unitCompanyId = null; // No company associated.

    $mockUnitModel = Mockery::mock();
    $mockUnitModel->id = $unitId;
    $mockUnitModel->name = $unitName;
    $mockUnitModel->company_id = $unitCompanyId;
    $mockUnitModel->company = null; // No related company model.

    // Mock the `company()` method on the Unit model to return the query builder.
    $mockUnitModel->shouldReceive('company')
                  ->once()
                  ->andReturn($mockCompanyQueryBuilder);

    // 3. Create an instance of the UnitResource.
    $unitResource = new UnitResource($mockUnitModel);

    // 4. Mock the Request object.
    $mockRequest = Mockery::mock(Request::class);

    // Act: Call the resolve method to ensure proper filtering via the `when()` helper.
    $result = $unitResource->resolve($mockRequest); // FIX: Changed to resolve()

    // Assert: Check the structure and values of the transformed array.
    // The 'company' key should be missing because when($condition, $callback) only executes
    // the callback if $condition is true, otherwise it removes the key.
    expect($result)->toMatchArray([
        'id' => $unitId,
        'name' => $unitName,
        'company_id' => $unitCompanyId,
    ])
    ->not->toHaveKey('company'); // Explicitly assert 'company' key is absent.
});

test('toArray handles null properties gracefully for core fields', function () {
    // 1. Mock the relationship query builder, ensuring company does not exist.
    $mockCompanyQueryBuilder = Mockery::mock();
    $mockCompanyQueryBuilder->shouldReceive('exists')
                            ->once()
                            ->andReturn(false);

    // 2. Mock the underlying Unit model with nulls for 'name' and 'company_id'.
    $unitId = 3;
    $mockUnitModel = Mockery::mock();
    $mockUnitModel->id = $unitId;
    $mockUnitModel->name = null; // Test null name.
    $mockUnitModel->company_id = null; // Test null company_id.
    $mockUnitModel->company = null; // No company object.

    // Mock the `company()` method on the Unit model to return the query builder.
    $mockUnitModel->shouldReceive('company')
                  ->once()
                  ->andReturn($mockCompanyQueryBuilder);

    // 3. Create resource and request.
    $unitResource = new UnitResource($mockUnitModel);
    $mockRequest = Mockery::mock(Request::class);

    // Act: Call the resolve method.
    $result = $unitResource->resolve($mockRequest); // FIX: Changed to resolve()

    // Assert: Check the structure and values, expecting nulls to be preserved.
    expect($result)->toMatchArray([
        'id' => $unitId,
        'name' => null,
        'company_id' => null,
    ])->not->toHaveKey('company'); // 'company' key should still be absent.
});


afterEach(function () {
    Mockery::close();
});