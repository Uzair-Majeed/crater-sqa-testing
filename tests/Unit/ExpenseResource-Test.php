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
use Crater\Models\Expense; // Assuming Crater\Models\Expense is the actual Eloquent model
use Illuminate\Support\Collection; // For custom fields relationship

beforeEach(function () {
    // Ensure all mocks are cleaned up
    m::close();
});

// Helper function to create a mock relation for the `exists()` check
function mockRelation($exists = true)
{
    // A mock relation object that responds to 'exists()'
    $relation = m::mock(\Illuminate\Database\Eloquent\Relations\Relation::class);
    $relation->shouldReceive('exists')->andReturn($exists);
    return $relation;
}

/**
 * Helper function to create a base Expense model mock with properties and relationship mocks.
 * This ensures that direct property access and relationship method calls on the mock work correctly,
 * resolving the "Undefined property" errors when the JsonResource tries to access properties.
 *
 * @param array $attributes Overrides for default model attributes.
 * @param array $relationships Key-value pairs where key is the relationship name and value is the related mock object/collection.
 * @return Expense|\Mockery\MockInterface
 */
function createBaseExpenseModelMock(array $attributes = [], array $relationships = [])
{
    $defaultAttributes = [
        'id' => 1,
        'expense_date' => '2023-01-15',
        'amount' => 100.50,
        'notes' => 'Some notes',
        'customer_id' => null,
        'receipt_url' => null,
        'receipt' => null,
        'receipt_meta' => null,
        'company_id' => null,
        'expense_category_id' => null,
        'creator_id' => null,
        'formattedExpenseDate' => 'Jan 15, 2023',
        'formattedCreatedAt' => 'Jan 01, 2023 10:00 AM',
        'exchange_rate' => 1.0,
        'currency_id' => null,
        'base_amount' => 100.50,
        'payment_method_id' => null,
    ];

    // Create a partial mock of the Expense model.
    // makePartial() ensures that existing methods/properties of the base class (Eloquent Model) are preserved,
    // and we can override or add behavior with shouldReceive().
    $model = m::mock(Expense::class)->makePartial();

    // Assign attributes to the mock model.
    // Eloquent models handle direct property assignments as attributes.
    foreach (array_merge($defaultAttributes, $attributes) as $key => $value) {
        $model->{$key} = $value;
    }

    // Default mock for all relationship methods to return a non-existent relation,
    // and set the corresponding property to null (or empty collection for fields)
    // to mimic an unloaded relationship for whenLoaded checks.
    $model->shouldReceive('customer')->andReturn(mockRelation(false));
    $model->customer = null;
    $model->shouldReceive('category')->andReturn(mockRelation(false));
    $model->category = null;
    $model->shouldReceive('creator')->andReturn(mockRelation(false));
    $model->creator = null;
    $model->shouldReceive('fields')->andReturn(mockRelation(false));
    $model->fields = new Collection(); // Default to empty collection for 'fields' when not loaded
    $model->shouldReceive('company')->andReturn(mockRelation(false));
    $model->company = null;
    $model->shouldReceive('currency')->andReturn(mockRelation(false));
    $model->currency = null;
    $model->shouldReceive('paymentMethod')->andReturn(mockRelation(false));
    $model->paymentMethod = null;

    // Apply specific relationships from the $relationships array.
    // For relationships that "exist", we mock the method to return true for exists(),
    // and crucially, set the public property on the model mock to the actual related mock object.
    // This allows JsonResource's $this->relation to access the loaded data.
    foreach ($relationships as $relationName => $relatedModel) {
        $model->shouldReceive($relationName)->andReturn(mockRelation(true));
        $model->{$relationName} = $relatedModel;
    }

    return $model;
}

test('it transforms expense with basic properties when no relationships exist', function () {
    $expenseModel = createBaseExpenseModelMock([
        'id' => 1,
        'receipt_url' => 'http://example.com/receipt1.jpg',
        'receipt' => 'receipt1.jpg',
        'receipt_meta' => ['size' => 1024, 'mime' => 'image/jpeg'],
        'company_id' => 1, // These are direct attributes, not necessarily loaded relationships in this test
        'currency_id' => 1,
    ]);

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

    $expenseModel = createBaseExpenseModelMock(
        ['customer_id' => $mockCustomer->id],
        ['customer' => $mockCustomer]
    );

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('customer');
    expect($result['customer'])->toBeInstanceOf(CustomerResource::class);
    expect($result['customer']->resource)->toBe($mockCustomer);
});

test('it includes expense category resource when category relationship exists', function () {
    $mockCategory = (object) ['id' => 10, 'name' => 'Travel'];

    $expenseModel = createBaseExpenseModelMock(
        ['expense_category_id' => $mockCategory->id],
        ['category' => $mockCategory]
    );

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('expense_category');
    expect($result['expense_category'])->toBeInstanceOf(ExpenseCategoryResource::class);
    expect($result['expense_category']->resource)->toBe($mockCategory);
});

test('it includes creator resource when creator relationship exists', function () {
    $mockUser = (object) ['id' => 20, 'name' => 'John Doe'];

    $expenseModel = createBaseExpenseModelMock(
        ['creator_id' => $mockUser->id],
        ['creator' => $mockUser]
    );

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

    $expenseModel = createBaseExpenseModelMock(
        [], // No direct ID attribute needed for custom fields in this context
        ['fields' => $mockFields]
    );

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

    $expenseModel = createBaseExpenseModelMock(
        ['company_id' => $mockCompany->id],
        ['company' => $mockCompany]
    );

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('company');
    expect($result['company'])->toBeInstanceOf(CompanyResource::class);
    expect($result['company']->resource)->toBe($mockCompany);
});

test('it includes currency resource when currency relationship exists', function () {
    $mockCurrency = (object) ['id' => 1, 'name' => 'USD', 'code' => 'USD'];

    $expenseModel = createBaseExpenseModelMock(
        ['currency_id' => $mockCurrency->id],
        ['currency' => $mockCurrency]
    );

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    expect($result)->toHaveKey('currency');
    expect($result['currency'])->toBeInstanceOf(CurrencyResource::class);
    expect($result['currency']->resource)->toBe($mockCurrency);
});

test('it includes payment method resource when payment method relationship exists', function () {
    $mockPaymentMethod = (object) ['id' => 1, 'name' => 'Cash'];

    $expenseModel = createBaseExpenseModelMock(
        ['payment_method_id' => $mockPaymentMethod->id],
        ['paymentMethod' => $mockPaymentMethod]
    );

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

    $expenseModel = createBaseExpenseModelMock(
        [
            'customer_id' => $mockCustomer->id,
            'receipt_url' => 'http://example.com/receipt1.jpg',
            'receipt' => 'receipt1.jpg',
            'receipt_meta' => ['size' => 1024, 'mime' => 'image/jpeg'],
            'company_id' => $mockCompany->id,
            'expense_category_id' => $mockCategory->id,
            'creator_id' => $mockUser->id,
            'currency_id' => $mockCurrency->id,
            'payment_method_id' => $mockPaymentMethod->id,
        ],
        [
            'customer' => $mockCustomer,
            'category' => $mockCategory,
            'creator' => $mockUser,
            'fields' => $mockFields,
            'company' => $mockCompany,
            'currency' => $mockCurrency,
            'paymentMethod' => $mockPaymentMethod,
        ]
    );

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
    $expenseModel = createBaseExpenseModelMock([
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
    ]);

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
    // This test specifically verifies what happens if the relationship *method* returns null
    // (e.g., `expense->customer()` returns null) instead of a Relation object.
    // This scenario could cause a "Call to a member function exists() on null" if not handled.
    // The `createBaseExpenseModelMock` sets up relationships to return `mockRelation(false)` and properties to `null` by default.
    // For this test, we override the method mocks to return `null` explicitly for relationships.

    $expenseModel = m::mock(Expense::class)->makePartial();
    // Minimal attributes for the resource to process
    $expenseModel->id = 1;
    $expenseModel->expense_date = '2023-01-15';
    $expenseModel->amount = 100.50;
    $expenseModel->notes = 'Some notes';
    $expenseModel->company_id = 1;
    $expenseModel->currency_id = 1;
    $expenseModel->base_amount = 100.50;
    $expenseModel->formattedExpenseDate = 'Jan 15, 2023';
    $expenseModel->formattedCreatedAt = 'Jan 01, 2023 10:00 AM';
    $expenseModel->exchange_rate = 1.0;

    // Mock all relationship methods to return null, and ensure corresponding properties are null
    $expenseModel->shouldReceive('customer')->andReturn(null);
    $expenseModel->customer = null;
    $expenseModel->shouldReceive('category')->andReturn(null);
    $expenseModel->category = null;
    $expenseModel->shouldReceive('creator')->andReturn(null);
    $expenseModel->creator = null;
    $expenseModel->shouldReceive('fields')->andReturn(null);
    $expenseModel->fields = new Collection(); // If a relationship method for a collection returns null, the property might be an empty collection or null. Empty collection is safer.
    $expenseModel->shouldReceive('company')->andReturn(null);
    $expenseModel->company = null;
    $expenseModel->shouldReceive('currency')->andReturn(null);
    $expenseModel->currency = null;
    $expenseModel->shouldReceive('paymentMethod')->andReturn(null);
    $expenseModel->paymentMethod = null;

    $request = m::mock(Request::class);
    $resource = new ExpenseResource($expenseModel);
    $result = $resource->toArray($request);

    // Expect no relationship keys to be present, as `whenLoaded` (or similar logic)
    // should correctly interpret a null relation method return as an unloaded relationship.
    expect($result)->not->toHaveKey('customer');
    expect($result)->not->toHaveKey('expense_category');
    expect($result)->not->toHaveKey('creator');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('company');
    expect($result)->not->toHaveKey('currency');
    expect($result)->not->toHaveKey('payment_method');
});

afterEach(function () {
    Mockery::close();
});