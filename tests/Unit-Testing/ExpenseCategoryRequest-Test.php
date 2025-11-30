<?php

test('authorize method always returns true', function () {
    $request = new \Crater\Http\Requests\ExpenseCategoryRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct validation rules', function () {
    $request = new \Crater\Http\Requests\ExpenseCategoryRequest();
    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKeys(['name', 'description'])
        ->and($rules['name'])->toEqual(['required'])
        ->and($rules['description'])->toEqual(['nullable']);
});

test('getExpenseCategoryPayload returns correct payload with all fields', function () {
    $mockRequest = \Mockery::mock(\Crater\Http\Requests\ExpenseCategoryRequest::class)
        ->makePartial();

    $mockRequest->shouldReceive('validated')
        ->once()
        ->andReturn([
            'name' => 'Test Category',
            'description' => 'A test description',
        ]);

    $mockRequest->shouldReceive('header')
        ->with('company')
        ->once()
        ->andReturn(123); // Example company ID

    $payload = $mockRequest->getExpenseCategoryPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['name', 'description', 'company_id'])
        ->and($payload['name'])->toBe('Test Category')
        ->and($payload['description'])->toBe('A test description')
        ->and($payload['company_id'])->toBe(123);
});

test('getExpenseCategoryPayload returns correct payload when description is null', function () {
    $mockRequest = \Mockery::mock(\Crater\Http\Requests\ExpenseCategoryRequest::class)
        ->makePartial();

    $mockRequest->shouldReceive('validated')
        ->once()
        ->andReturn([
            'name' => 'Another Category',
            'description' => null, // Description is nullable
        ]);

    $mockRequest->shouldReceive('header')
        ->with('company')
        ->once()
        ->andReturn(456);

    $payload = $mockRequest->getExpenseCategoryPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['name', 'description', 'company_id'])
        ->and($payload['name'])->toBe('Another Category')
        ->and($payload['description'])->toBeNull()
        ->and($payload['company_id'])->toBe(456);
});

test('getExpenseCategoryPayload returns correct payload when description is missing from validated data', function () {
    $mockRequest = \Mockery::mock(\Crater\Http\Requests\ExpenseCategoryRequest::class)
        ->makePartial();

    $mockRequest->shouldReceive('validated')
        ->once()
        ->andReturn([
            'name' => 'Category Without Desc',
        ]);

    $mockRequest->shouldReceive('header')
        ->with('company')
        ->once()
        ->andReturn(789);

    $payload = $mockRequest->getExpenseCategoryPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['name', 'company_id'])
        ->and($payload)->not->toHaveKey('description')
        ->and($payload['name'])->toBe('Category Without Desc')
        ->and($payload['company_id'])->toBe(789);
});

test('getExpenseCategoryPayload handles empty validated data gracefully', function () {
    $mockRequest = \Mockery::mock(\Crater\Http\Requests\ExpenseCategoryRequest::class)
        ->makePartial();

    $mockRequest->shouldReceive('validated')
        ->once()
        ->andReturn([]); // Empty validated data

    $mockRequest->shouldReceive('header')
        ->with('company')
        ->once()
        ->andReturn(101);

    $payload = $mockRequest->getExpenseCategoryPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['company_id'])
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('description')
        ->and($payload['company_id'])->toBe(101);
});

test('getExpenseCategoryPayload handles null company header', function () {
    $mockRequest = \Mockery::mock(\Crater\Http\Requests\ExpenseCategoryRequest::class)
        ->makePartial();

    $mockRequest->shouldReceive('validated')
        ->once()
        ->andReturn([
            'name' => 'Null Company Category',
        ]);

    $mockRequest->shouldReceive('header')
        ->with('company')
        ->once()
        ->andReturn(null); // No company header

    $payload = $mockRequest->getExpenseCategoryPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['name', 'company_id'])
        ->and($payload['name'])->toBe('Null Company Category')
        ->and($payload['company_id'])->toBeNull();
});

test('getExpenseCategoryPayload handles zero company header', function () {
    $mockRequest = \Mockery::mock(\Crater\Http\Requests\ExpenseCategoryRequest::class)
        ->makePartial();

    $mockRequest->shouldReceive('validated')
        ->once()
        ->andReturn([
            'name' => 'Zero Company Category',
        ]);

    $mockRequest->shouldReceive('header')
        ->with('company')
        ->once()
        ->andReturn(0); // Company ID can be 0 for some systems

    $payload = $mockRequest->getExpenseCategoryPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['name', 'company_id'])
        ->and($payload['name'])->toBe('Zero Company Category')
        ->and($payload['company_id'])->toBe(0);
});

test('getExpenseCategoryPayload handles empty string company header', function () {
    $mockRequest = \Mockery::mock(\Crater\Http\Requests\ExpenseCategoryRequest::class)
        ->makePartial();

    $mockRequest->shouldReceive('validated')
        ->once()
        ->andReturn([
            'name' => 'Empty String Company Category',
        ]);

    $mockRequest->shouldReceive('header')
        ->with('company')
        ->once()
        ->andReturn(''); // Empty string company header

    $payload = $mockRequest->getExpenseCategoryPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['name', 'company_id'])
        ->and($payload['name'])->toBe('Empty String Company Category')
        ->and($payload['company_id'])->toBe('');
});
