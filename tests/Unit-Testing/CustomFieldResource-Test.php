<?php
test('toArray transforms resource with all fields and existing company', function () {
    // Arrange
    $mockCompanyQueryBuilder = Mockery::mock(stdClass::class);
    $mockCompanyQueryBuilder->shouldReceive('exists')->andReturn(true);

    $mockCompanyModelInstance = Mockery::mock(stdClass::class);
    $mockCompanyModelInstance->id = 10;
    $mockCompanyModelInstance->name = 'Example Company';
    // Add other properties that CompanyResource might expect if necessary,
    // though not directly asserted here, only the instance type.

    $mockModel = Mockery::mock(stdClass::class);
    $mockModel->id = 1;
    $mockModel->name = 'Test Custom Field';
    $mockModel->slug = 'test_custom_field';
    $mockModel->label = 'Test Label';
    $mockModel->model_type = 'Invoice';
    $mockModel->type = 'text';
    $mockModel->placeholder = 'Enter value';
    $mockModel->options = ['Option A', 'Option B'];
    $mockModel->boolean_answer = true;
    $mockModel->date_answer = '2023-01-01';
    $mockModel->time_answer = '10:00:00';
    $mockModel->string_answer = 'Some string';
    $mockModel->number_answer = 123;
    $mockModel->date_time_answer = '2023-01-01 10:00:00';
    $mockModel->is_required = true;
    $mockModel->in_use = false;
    $mockModel->order = 1;
    $mockModel->company_id = 5;
    $mockModel->default_answer = 'Default Answer';
    $mockModel->company = $mockCompanyModelInstance; // The actual company model instance
    $mockModel->shouldReceive('company')->andReturn($mockCompanyQueryBuilder); // Mock the relationship method call

    $resource = new \Crater\Http\Resources\CustomFieldResource($mockModel);
    $request = Mockery::mock(\Illuminate\Http\Request::class);

    // Act
    $result = $resource->toArray($request);

    // Assert
    expect($result)->toBeArray();
    expect($result)->toEqual([
        'id' => 1,
        'name' => 'Test Custom Field',
        'slug' => 'test_custom_field',
        'label' => 'Test Label',
        'model_type' => 'Invoice',
        'type' => 'text',
        'placeholder' => 'Enter value',
        'options' => ['Option A', 'Option B'],
        'boolean_answer' => true,
        'date_answer' => '2023-01-01',
        'time_answer' => '10:00:00',
        'string_answer' => 'Some string',
        'number_answer' => 123,
        'date_time_answer' => '2023-01-01 10:00:00',
        'is_required' => true,
        'in_use' => false,
        'order' => 1,
        'company_id' => 5,
        'default_answer' => 'Default Answer',
        'company' => Mockery::type(\Crater\Http\Resources\CompanyResource::class),
    ]);
});

test('toArray transforms resource with all fields and non-existing company', function () {
    // Arrange
    $mockCompanyQueryBuilder = Mockery::mock(stdClass::class);
    $mockCompanyQueryBuilder->shouldReceive('exists')->andReturn(false);

    $mockModel = Mockery::mock(stdClass::class);
    $mockModel->id = 2;
    $mockModel->name = 'Another Field';
    $mockModel->slug = 'another_field';
    $mockModel->label = 'Another Label';
    $mockModel->model_type = 'Expense';
    $mockModel->type = 'number';
    $mockModel->placeholder = null;
    $mockModel->options = null;
    $mockModel->boolean_answer = false;
    $mockModel->date_answer = null;
    $mockModel->time_answer = null;
    $mockModel->string_answer = null;
    $mockModel->number_answer = 456;
    $mockModel->date_time_answer = null;
    $mockModel->is_required = false;
    $mockModel->in_use = true;
    $mockModel->order = 2;
    $mockModel->company_id = null;
    $mockModel->default_answer = null;
    $mockModel->company = null; // No company instance
    $mockModel->shouldReceive('company')->andReturn($mockCompanyQueryBuilder);

    $resource = new \Crater\Http\Resources\CustomFieldResource($mockModel);
    $request = Mockery::mock(\Illuminate\Http\Request::class);

    // Act
    $result = $resource->toArray($request);

    // Assert
    expect($result)->toBeArray();
    expect($result)->toEqual([
        'id' => 2,
        'name' => 'Another Field',
        'slug' => 'another_field',
        'label' => 'Another Label',
        'model_type' => 'Expense',
        'type' => 'number',
        'placeholder' => null,
        'options' => null,
        'boolean_answer' => false,
        'date_answer' => null,
        'time_answer' => null,
        'string_answer' => null,
        'number_answer' => 456,
        'date_time_answer' => null,
        'is_required' => false,
        'in_use' => true,
        'order' => 2,
        'company_id' => null,
        'default_answer' => null,
    ]);
    expect($result)->not->toHaveKey('company');
});

test('toArray handles null values for all fields gracefully, with existing company', function () {
    // Arrange
    $mockCompanyQueryBuilder = Mockery::mock(stdClass::class);
    $mockCompanyQueryBuilder->shouldReceive('exists')->andReturn(true);

    $mockCompanyModelInstance = Mockery::mock(stdClass::class);
    // No specific properties for the company instance needed for this test as it's just type checked

    $mockModel = Mockery::mock(stdClass::class);
    $mockModel->id = 3; // ID cannot be null typically, but for test completeness setting it.
    $mockModel->name = null;
    $mockModel->slug = null;
    $mockModel->label = null;
    $mockModel->model_type = null;
    $mockModel->type = null;
    $mockModel->placeholder = null;
    $mockModel->options = null;
    $mockModel->boolean_answer = null;
    $mockModel->date_answer = null;
    $mockModel->time_answer = null;
    $mockModel->string_answer = null;
    $mockModel->number_answer = null;
    $mockModel->date_time_answer = null;
    $mockModel->is_required = null;
    $mockModel->in_use = null;
    $mockModel->order = null;
    $mockModel->company_id = null;
    $mockModel->default_answer = null;
    $mockModel->company = $mockCompanyModelInstance;
    $mockModel->shouldReceive('company')->andReturn($mockCompanyQueryBuilder);

    $resource = new \Crater\Http\Resources\CustomFieldResource($mockModel);
    $request = Mockery::mock(\Illuminate\Http\Request::class);

    // Act
    $result = $resource->toArray($request);

    // Assert
    expect($result)->toBeArray();
    expect($result)->toEqual([
        'id' => 3,
        'name' => null,
        'slug' => null,
        'label' => null,
        'model_type' => null,
        'type' => null,
        'placeholder' => null,
        'options' => null,
        'boolean_answer' => null,
        'date_answer' => null,
        'time_answer' => null,
        'string_answer' => null,
        'number_answer' => null,
        'date_time_answer' => null,
        'is_required' => null,
        'in_use' => null,
        'order' => null,
        'company_id' => null,
        'default_answer' => null,
        'company' => Mockery::type(\Crater\Http\Resources\CompanyResource::class),
    ]);
});

 

afterEach(function () {
    Mockery::close();
});
