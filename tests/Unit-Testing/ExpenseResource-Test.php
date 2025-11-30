<?php

use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\CurrencyResource;
use Crater\Http\Resources\CustomerResource;
use Crater\Http\Resources\CustomFieldValueResource;
use Crater\Http\Resources\ExpenseCategoryResource;
use Crater\Http\Resources\ExpenseResource;
use Crater\Http\Resources\PaymentMethodResource;
use Crater\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Mockery as m;

beforeEach(function () {
    // Ensure all mocks are cleaned up
    m::close();
});

// Helper function to create a mock relation for the `exists()` check
function mockRelation($exists = true)
{
    $relation = m::mock(\Illuminate\Database\Eloquent\Relations\Relation::class);
    $relation->shouldReceive('exists')->andReturn($exists);
    return $relation;
}

test('it transforms expense with basic properties when no relationships exist', function () {
    $expenseModel = (object) [
        'id' => 1,
        'expense_date' => '2023-01-15',
        'amount' => 100.50,
        'notes' => 'Some notes',
        'customer_id' => null,
        'receipt_url' => 'http://example.com/receipt1.jpg',
        'receipt' => 'receipt1.jpg',
        'receipt_meta' => ['size' => 1024, 'mime' => 'image/jpeg'],
        'company_id' => 1,
        'expense_category_id' => null,
        'creator_id' => null,
        'formattedExpenseDate' => 'Jan 15, 2023',
        'formattedCreatedAt' => 'Jan 01, 2023 10:00 AM',
        'exchange_rate' => 1.0,
        'currency_id' => 1,
        'base_amount' => 100.50,
        'payment_method_id' => null,
    ];

    // Mock all relationship methods to return a relation where exists() is false
    $expenseModel = m::mock($expenseModel)
        ->shouldReceive('customer')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('category')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('creator')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('fields')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('company')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('currency')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('paymentMethod')->andReturn(mockRelation(false))->getMock();

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toMatchArray([
        'id' => 1,
        'expense_date' => '2023-01-15',
        'amount' => 100.50,
        'notes' => 'Some notes',
        'customer_id' => null,
        'attachment_receipt_url' => 'http://example.com/receipt1.jpg',
        'attachment_receipt' => 'receipt1.jpg',
        'attachment_receipt_meta' => ['size' => 1024, 'mime' => 'image/jpeg'],
        'company_id' => 1,
        'expense_category_id' => null,
        'creator_id' => null,
        'formatted_expense_date' => 'Jan 15, 2023',
        'formatted_created_at' => 'Jan 01, 2023 10:00 AM',
        'exchange_rate' => 1.0,
        'currency_id' => 1,
        'base_amount' => 100.50,
        'payment_method_id' => null,
    ]);

    expect($result)->not->toHaveKey('customer');
    expect($result)->not->toHaveKey('expense_category');
    expect($result)->not->toHaveKey('creator');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('company');
    expect($result)->not->toHaveKey('currency');
    expect($result)->not->toHaveKey('payment_method');
});

test('it includes customer resource when customer relationship exists', function () {
    $mockCustomer = (object) ['id' => 5, 'name' => 'Test Customer'];

    $expenseModel = (object) [ /* ... basic properties ... */ ];
    $expenseModel->id = 1; // Example to make the expense model valid
    $expenseModel->customer_id = $mockCustomer->id;
    // Fill in other required properties to avoid errors if they are accessed
    $expenseModel->expense_date = '2023-01-15';
    $expenseModel->amount = 100.50;
    $expenseModel->notes = 'Some notes';
    $expenseModel->receipt_url = null;
    $expenseModel->receipt = null;
    $expenseModel->receipt_meta = null;
    $expenseModel->company_id = 1;
    $expenseModel->expense_category_id = null;
    $expenseModel->creator_id = null;
    $expenseModel->formattedExpenseDate = 'Jan 15, 2023';
    $expenseModel->formattedCreatedAt = 'Jan 01, 2023 10:00 AM';
    $expenseModel->exchange_rate = 1.0;
    $expenseModel->currency_id = 1;
    $expenseModel->base_amount = 100.50;
    $expenseModel->payment_method_id = null;


    $expenseModel = m::mock($expenseModel);
    $expenseModel->shouldReceive('customer')->andReturn(mockRelation(true));
    $expenseModel->customer = $mockCustomer; // Set the property for the resource instantiation

    // Ensure other relationships don't exist
    $expenseModel->shouldReceive('category')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('creator')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('fields')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('company')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('currency')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('paymentMethod')->andReturn(mockRelation(false));

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('customer');
    expect($result['customer'])->toBeInstanceOf(CustomerResource::class);
    expect($result['customer']->resource)->toBe($mockCustomer);
});

test('it includes expense category resource when category relationship exists', function () {
    $mockCategory = (object) ['id' => 10, 'name' => 'Travel'];

    $expenseModel = (object) [ /* ... basic properties ... */ ];
    $expenseModel->id = 1;
    $expenseModel->expense_category_id = $mockCategory->id;
    // Fill in other required properties
    $expenseModel->expense_date = '2023-01-15';
    $expenseModel->amount = 100.50;
    $expenseModel->notes = 'Some notes';
    $expenseModel->receipt_url = null;
    $expenseModel->receipt = null;
    $expenseModel->receipt_meta = null;
    $expenseModel->company_id = 1;
    $expenseModel->customer_id = null;
    $expenseModel->creator_id = null;
    $expenseModel->formattedExpenseDate = 'Jan 15, 2023';
    $expenseModel->formattedCreatedAt = 'Jan 01, 2023 10:00 AM';
    $expenseModel->exchange_rate = 1.0;
    $expenseModel->currency_id = 1;
    $expenseModel->base_amount = 100.50;
    $expenseModel->payment_method_id = null;


    $expenseModel = m::mock($expenseModel);
    $expenseModel->shouldReceive('category')->andReturn(mockRelation(true));
    $expenseModel->category = $mockCategory;

    // Ensure other relationships don't exist
    $expenseModel->shouldReceive('customer')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('creator')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('fields')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('company')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('currency')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('paymentMethod')->andReturn(mockRelation(false));

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('expense_category');
    expect($result['expense_category'])->toBeInstanceOf(ExpenseCategoryResource::class);
    expect($result['expense_category']->resource)->toBe($mockCategory);
});

test('it includes creator resource when creator relationship exists', function () {
    $mockUser = (object) ['id' => 20, 'name' => 'John Doe'];

    $expenseModel = (object) [ /* ... basic properties ... */ ];
    $expenseModel->id = 1;
    $expenseModel->creator_id = $mockUser->id;
    // Fill in other required properties
    $expenseModel->expense_date = '2023-01-15';
    $expenseModel->amount = 100.50;
    $expenseModel->notes = 'Some notes';
    $expenseModel->receipt_url = null;
    $expenseModel->receipt = null;
    $expenseModel->receipt_meta = null;
    $expenseModel->company_id = 1;
    $expenseModel->customer_id = null;
    $expenseModel->expense_category_id = null;
    $expenseModel->formattedExpenseDate = 'Jan 15, 2023';
    $expenseModel->formattedCreatedAt = 'Jan 01, 2023 10:00 AM';
    $expenseModel->exchange_rate = 1.0;
    $expenseModel->currency_id = 1;
    $expenseModel->base_amount = 100.50;
    $expenseModel->payment_method_id = null;


    $expenseModel = m::mock($expenseModel);
    $expenseModel->shouldReceive('creator')->andReturn(mockRelation(true));
    $expenseModel->creator = $mockUser;

    // Ensure other relationships don't exist
    $expenseModel->shouldReceive('customer')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('category')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('fields')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('company')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('currency')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('paymentMethod')->andReturn(mockRelation(false));

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('creator');
    expect($result['creator'])->toBeInstanceOf(UserResource::class);
    expect($result['creator']->resource)->toBe($mockUser);
});

test('it includes custom fields resource when fields relationship exists', function () {
    $mockField1 = (object) ['id' => 1, 'value' => 'Field 1 Value'];
    $mockField2 = (object) ['id' => 2, 'value' => 'Field 2 Value'];
    $mockFields = collect([$mockField1, $mockField2]);

    $expenseModel = (object) [ /* ... basic properties ... */ ];
    $expenseModel->id = 1;
    // Fill in other required properties
    $expenseModel->expense_date = '2023-01-15';
    $expenseModel->amount = 100.50;
    $expenseModel->notes = 'Some notes';
    $expenseModel->receipt_url = null;
    $expenseModel->receipt = null;
    $expenseModel->receipt_meta = null;
    $expenseModel->company_id = 1;
    $expenseModel->customer_id = null;
    $expenseModel->expense_category_id = null;
    $expenseModel->creator_id = null;
    $expenseModel->formattedExpenseDate = 'Jan 15, 2023';
    $expenseModel->formattedCreatedAt = 'Jan 01, 2023 10:00 AM';
    $expenseModel->exchange_rate = 1.0;
    $expenseModel->currency_id = 1;
    $expenseModel->base_amount = 100.50;
    $expenseModel->payment_method_id = null;


    $expenseModel = m::mock($expenseModel);
    $expenseModel->shouldReceive('fields')->andReturn(mockRelation(true));
    $expenseModel->fields = $mockFields;

    // Ensure other relationships don't exist
    $expenseModel->shouldReceive('customer')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('category')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('creator')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('company')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('currency')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('paymentMethod')->andReturn(mockRelation(false));

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('fields');
    expect($result['fields'])->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
    expect($result['fields']->collect()->first())->toBeInstanceOf(CustomFieldValueResource::class);
    expect($result['fields']->collect()->first()->resource)->toBe($mockField1);
    expect($result['fields']->collect()->last()->resource)->toBe($mockField2);
});

test('it includes company resource when company relationship exists', function () {
    $mockCompany = (object) ['id' => 1, 'name' => 'My Company'];

    $expenseModel = (object) [ /* ... basic properties ... */ ];
    $expenseModel->id = 1;
    $expenseModel->company_id = $mockCompany->id;
    // Fill in other required properties
    $expenseModel->expense_date = '2023-01-15';
    $expenseModel->amount = 100.50;
    $expenseModel->notes = 'Some notes';
    $expenseModel->receipt_url = null;
    $expenseModel->receipt = null;
    $expenseModel->receipt_meta = null;
    $expenseModel->customer_id = null;
    $expenseModel->expense_category_id = null;
    $expenseModel->creator_id = null;
    $expenseModel->formattedExpenseDate = 'Jan 15, 2023';
    $expenseModel->formattedCreatedAt = 'Jan 01, 2023 10:00 AM';
    $expenseModel->exchange_rate = 1.0;
    $expenseModel->currency_id = 1;
    $expenseModel->base_amount = 100.50;
    $expenseModel->payment_method_id = null;


    $expenseModel = m::mock($expenseModel);
    $expenseModel->shouldReceive('company')->andReturn(mockRelation(true));
    $expenseModel->company = $mockCompany;

    // Ensure other relationships don't exist
    $expenseModel->shouldReceive('customer')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('category')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('creator')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('fields')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('currency')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('paymentMethod')->andReturn(mockRelation(false));

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('company');
    expect($result['company'])->toBeInstanceOf(CompanyResource::class);
    expect($result['company']->resource)->toBe($mockCompany);
});

test('it includes currency resource when currency relationship exists', function () {
    $mockCurrency = (object) ['id' => 1, 'name' => 'USD', 'code' => 'USD'];

    $expenseModel = (object) [ /* ... basic properties ... */ ];
    $expenseModel->id = 1;
    $expenseModel->currency_id = $mockCurrency->id;
    // Fill in other required properties
    $expenseModel->expense_date = '2023-01-15';
    $expenseModel->amount = 100.50;
    $expenseModel->notes = 'Some notes';
    $expenseModel->receipt_url = null;
    $expenseModel->receipt = null;
    $expenseModel->receipt_meta = null;
    $expenseModel->company_id = 1;
    $expenseModel->customer_id = null;
    $expenseModel->expense_category_id = null;
    $expenseModel->creator_id = null;
    $expenseModel->formattedExpenseDate = 'Jan 15, 2023';
    $expenseModel->formattedCreatedAt = 'Jan 01, 2023 10:00 AM';
    $expenseModel->exchange_rate = 1.0;
    $expenseModel->base_amount = 100.50;
    $expenseModel->payment_method_id = null;


    $expenseModel = m::mock($expenseModel);
    $expenseModel->shouldReceive('currency')->andReturn(mockRelation(true));
    $expenseModel->currency = $mockCurrency;

    // Ensure other relationships don't exist
    $expenseModel->shouldReceive('customer')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('category')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('creator')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('fields')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('company')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('paymentMethod')->andReturn(mockRelation(false));

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('currency');
    expect($result['currency'])->toBeInstanceOf(CurrencyResource::class);
    expect($result['currency']->resource)->toBe($mockCurrency);
});

test('it includes payment method resource when payment method relationship exists', function () {
    $mockPaymentMethod = (object) ['id' => 1, 'name' => 'Cash'];

    $expenseModel = (object) [ /* ... basic properties ... */ ];
    $expenseModel->id = 1;
    $expenseModel->payment_method_id = $mockPaymentMethod->id;
    // Fill in other required properties
    $expenseModel->expense_date = '2023-01-15';
    $expenseModel->amount = 100.50;
    $expenseModel->notes = 'Some notes';
    $expenseModel->receipt_url = null;
    $expenseModel->receipt = null;
    $expenseModel->receipt_meta = null;
    $expenseModel->company_id = 1;
    $expenseModel->customer_id = null;
    $expenseModel->expense_category_id = null;
    $expenseModel->creator_id = null;
    $expenseModel->formattedExpenseDate = 'Jan 15, 2023';
    $expenseModel->formattedCreatedAt = 'Jan 01, 2023 10:00 AM';
    $expenseModel->exchange_rate = 1.0;
    $expenseModel->currency_id = 1;
    $expenseModel->base_amount = 100.50;


    $expenseModel = m::mock($expenseModel);
    $expenseModel->shouldReceive('paymentMethod')->andReturn(mockRelation(true));
    $expenseModel->paymentMethod = $mockPaymentMethod;

    // Ensure other relationships don't exist
    $expenseModel->shouldReceive('customer')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('category')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('creator')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('fields')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('company')->andReturn(mockRelation(false));
    $expenseModel->shouldReceive('currency')->andReturn(mockRelation(false));

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('payment_method');
    expect($result['payment_method'])->toBeInstanceOf(PaymentMethodResource::class);
    expect($result['payment_method']->resource)->toBe($mockPaymentMethod);
});

test('it includes all relationships when they exist', function () {
    $mockCustomer = (object) ['id' => 5, 'name' => 'Test Customer'];
    $mockCategory = (object) ['id' => 10, 'name' => 'Travel'];
    $mockUser = (object) ['id' => 20, 'name' => 'John Doe'];
    $mockField1 = (object) ['id' => 1, 'value' => 'Field 1 Value'];
    $mockField2 = (object) ['id' => 2, 'value' => 'Field 2 Value'];
    $mockFields = collect([$mockField1, $mockField2]);
    $mockCompany = (object) ['id' => 1, 'name' => 'My Company'];
    $mockCurrency = (object) ['id' => 1, 'name' => 'USD', 'code' => 'USD'];
    $mockPaymentMethod = (object) ['id' => 1, 'name' => 'Cash'];

    $expenseModel = (object) [
        'id' => 1,
        'expense_date' => '2023-01-15',
        'amount' => 100.50,
        'notes' => 'Some notes',
        'customer_id' => $mockCustomer->id,
        'receipt_url' => 'http://example.com/receipt1.jpg',
        'receipt' => 'receipt1.jpg',
        'receipt_meta' => ['size' => 1024, 'mime' => 'image/jpeg'],
        'company_id' => $mockCompany->id,
        'expense_category_id' => $mockCategory->id,
        'creator_id' => $mockUser->id,
        'formattedExpenseDate' => 'Jan 15, 2023',
        'formattedCreatedAt' => 'Jan 01, 2023 10:00 AM',
        'exchange_rate' => 1.0,
        'currency_id' => $mockCurrency->id,
        'base_amount' => 100.50,
        'payment_method_id' => $mockPaymentMethod->id,
    ];

    $expenseModel = m::mock($expenseModel);
    $expenseModel->shouldReceive('customer')->andReturn(mockRelation(true));
    $expenseModel->customer = $mockCustomer;
    $expenseModel->shouldReceive('category')->andReturn(mockRelation(true));
    $expenseModel->category = $mockCategory;
    $expenseModel->shouldReceive('creator')->andReturn(mockRelation(true));
    $expenseModel->creator = $mockUser;
    $expenseModel->shouldReceive('fields')->andReturn(mockRelation(true));
    $expenseModel->fields = $mockFields;
    $expenseModel->shouldReceive('company')->andReturn(mockRelation(true));
    $expenseModel->company = $mockCompany;
    $expenseModel->shouldReceive('currency')->andReturn(mockRelation(true));
    $expenseModel->currency = $mockCurrency;
    $expenseModel->shouldReceive('paymentMethod')->andReturn(mockRelation(true));
    $expenseModel->paymentMethod = $mockPaymentMethod;

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toMatchArray([
        'id' => 1,
        'expense_date' => '2023-01-15',
        'amount' => 100.50,
        'notes' => 'Some notes',
        'customer_id' => $mockCustomer->id,
        'attachment_receipt_url' => 'http://example.com/receipt1.jpg',
        'attachment_receipt' => 'receipt1.jpg',
        'attachment_receipt_meta' => ['size' => 1024, 'mime' => 'image/jpeg'],
        'company_id' => $mockCompany->id,
        'expense_category_id' => $mockCategory->id,
        'creator_id' => $mockUser->id,
        'formatted_expense_date' => 'Jan 15, 2023',
        'formatted_created_at' => 'Jan 01, 2023 10:00 AM',
        'exchange_rate' => 1.0,
        'currency_id' => $mockCurrency->id,
        'base_amount' => 100.50,
        'payment_method_id' => $mockPaymentMethod->id,
    ]);

    expect($result)->toHaveKey('customer');
    expect($result['customer'])->toBeInstanceOf(CustomerResource::class);
    expect($result['customer']->resource)->toBe($mockCustomer);

    expect($result)->toHaveKey('expense_category');
    expect($result['expense_category'])->toBeInstanceOf(ExpenseCategoryResource::class);
    expect($result['expense_category']->resource)->toBe($mockCategory);

    expect($result)->toHaveKey('creator');
    expect($result['creator'])->toBeInstanceOf(UserResource::class);
    expect($result['creator']->resource)->toBe($mockUser);

    expect($result)->toHaveKey('fields');
    expect($result['fields'])->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
    expect($result['fields']->collect()->first())->toBeInstanceOf(CustomFieldValueResource::class);
    expect($result['fields']->collect()->first()->resource)->toBe($mockField1);

    expect($result)->toHaveKey('company');
    expect($result['company'])->toBeInstanceOf(CompanyResource::class);
    expect($result['company']->resource)->toBe($mockCompany);

    expect($result)->toHaveKey('currency');
    expect($result['currency'])->toBeInstanceOf(CurrencyResource::class);
    expect($result['currency']->resource)->toBe($mockCurrency);

    expect($result)->toHaveKey('payment_method');
    expect($result['payment_method'])->toBeInstanceOf(PaymentMethodResource::class);
    expect($result['payment_method']->resource)->toBe($mockPaymentMethod);
});

test('it handles null values for direct properties gracefully', function () {
    $expenseModel = (object) [
        'id' => null,
        'expense_date' => null,
        'amount' => null,
        'notes' => null,
        'customer_id' => null,
        'receipt_url' => null,
        'receipt' => null,
        'receipt_meta' => null,
        'company_id' => null,
        'expense_category_id' => null,
        'creator_id' => null,
        'formattedExpenseDate' => null,
        'formattedCreatedAt' => null,
        'exchange_rate' => null,
        'currency_id' => null,
        'base_amount' => null,
        'payment_method_id' => null,
    ];

    // Mock all relationship methods to return a relation where exists() is false
    $expenseModel = m::mock($expenseModel)
        ->shouldReceive('customer')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('category')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('creator')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('fields')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('company')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('currency')->andReturn(mockRelation(false))->getMock()
        ->shouldReceive('paymentMethod')->andReturn(mockRelation(false))->getMock();

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toMatchArray([
        'id' => null,
        'expense_date' => null,
        'amount' => null,
        'notes' => null,
        'customer_id' => null,
        'attachment_receipt_url' => null,
        'attachment_receipt' => null,
        'attachment_receipt_meta' => null,
        'company_id' => null,
        'expense_category_id' => null,
        'creator_id' => null,
        'formatted_expense_date' => null,
        'formatted_created_at' => null,
        'exchange_rate' => null,
        'currency_id' => null,
        'base_amount' => null,
        'payment_method_id' => null,
    ]);

    expect($result)->not->toHaveKey('customer');
    expect($result)->not->toHaveKey('expense_category');
    expect($result)->not->toHaveKey('creator');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('company');
    expect($result)->not->toHaveKey('currency');
    expect($result)->not->toHaveKey('payment_method');
});

test('it handles null relation method return gracefully', function () {
    $expenseModel = (object) [
        'id' => 1,
        'expense_date' => '2023-01-15',
        'amount' => 100.50,
        'notes' => 'Some notes',
        'customer_id' => null,
        'receipt_url' => null,
        'receipt' => null,
        'receipt_meta' => null,
        'company_id' => 1,
        'expense_category_id' => null,
        'creator_id' => null,
        'formattedExpenseDate' => 'Jan 15, 2023',
        'formattedCreatedAt' => 'Jan 01, 2023 10:00 AM',
        'exchange_rate' => 1.0,
        'currency_id' => 1,
        'base_amount' => 100.50,
        'payment_method_id' => null,
    ];

    // Mock all relationship methods to return null, mimicking a non-existent or misconfigured relation
    $expenseModel = m::mock($expenseModel)
        ->shouldReceive('customer')->andReturn(null)->getMock()
        ->shouldReceive('category')->andReturn(null)->getMock()
        ->shouldReceive('creator')->andReturn(null)->getMock()
        ->shouldReceive('fields')->andReturn(null)->getMock()
        ->shouldReceive('company')->andReturn(null)->getMock()
        ->shouldReceive('currency')->andReturn(null)->getMock()
        ->shouldReceive('paymentMethod')->andReturn(null)->getMock();

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    // Expect no relationship keys to be present
    expect($result)->not->toHaveKey('customer');
    expect($result)->not->toHaveKey('expense_category');
    expect($result)->not->toHaveKey('creator');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('company');
    expect($result)->not->toHaveKey('currency');
    expect($result)->not->toHaveKey('payment_method');
});
