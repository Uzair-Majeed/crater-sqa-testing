<?php

use Crater\Http\Requests\ExpenseRequest;
use Crater\Models\CompanySetting;
use Illuminate\Http\Request;
use Mockery\MockInterface;

beforeEach(function () {
    // Mock the static method for CompanySetting
    // We'll reset this for each test if different values are needed
    \Mockery::mock('alias:' . CompanySetting::class);
});


function createExpenseRequestWithData(array $data = [], array $headers = [])
{
    // Create a mock Request instance
    $mockRequest = Request::create('/', 'GET', $data, [], [], []);

    // Manually set headers on the mockRequest
    foreach ($headers as $key => $value) {
        $mockRequest->headers->set($key, $value);
    }

    // Instantiate ExpenseRequest and inject the mockRequest via reflection
    $expenseRequest = new ExpenseRequest();
    $reflection = new ReflectionClass($expenseRequest);
    $requestProperty = $reflection->getProperty('request');
    $requestProperty->setAccessible(true);
    $requestProperty->setValue($expenseRequest, $mockRequest);

    return $expenseRequest;
}

test('authorize method always returns true', function () {
    $request = new ExpenseRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns base rules when company currency is not set', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', '1')
        ->andReturn(null);

    $request = createExpenseRequestWithData(
        ['currency_id' => 'USD'],
        ['company' => '1']
    );

    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'expense_date', 'expense_category_id', 'exchange_rate',
        'payment_method_id', 'amount', 'customer_id', 'notes',
        'currency_id', 'attachment_receipt'
    ]);
    expect($rules['exchange_rate'])->toEqual(['nullable']);
});

test('rules method returns base rules when currency_id is not set', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', '1')
        ->andReturn('USD');

    $request = createExpenseRequestWithData(
        [], // No currency_id
        ['company' => '1']
    );

    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'expense_date', 'expense_category_id', 'exchange_rate',
        'payment_method_id', 'amount', 'customer_id', 'notes',
        'currency_id', 'attachment_receipt'
    ]);
    expect($rules['exchange_rate'])->toEqual(['nullable']);
});

test('rules method returns base rules when company currency and request currency are the same', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', '1')
        ->andReturn('USD');

    $request = createExpenseRequestWithData(
        ['currency_id' => 'USD'],
        ['company' => '1']
    );

    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'expense_date', 'expense_category_id', 'exchange_rate',
        'payment_method_id', 'amount', 'customer_id', 'notes',
        'currency_id', 'attachment_receipt'
    ]);
    expect($rules['exchange_rate'])->toEqual(['nullable']);
});

test('rules method makes exchange_rate required when company currency and request currency are different', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', '1')
        ->andReturn('EUR');

    $request = createExpenseRequestWithData(
        ['currency_id' => 'USD'],
        ['company' => '1']
    );

    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'expense_date', 'expense_category_id', 'exchange_rate',
        'payment_method_id', 'amount', 'customer_id', 'notes',
        'currency_id', 'attachment_receipt'
    ]);
    expect($rules['exchange_rate'])->toEqual(['required']);
});

test('getExpensePayload returns correct payload when company currency and request currency are the same', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', '1')
        ->andReturn('USD');

    $validatedData = [
        'expense_date' => '2023-01-01',
        'expense_category_id' => 1,
        'amount' => 100.00,
        'currency_id' => 'USD',
        'notes' => 'Some notes',
    ];

    $user = (object)['id' => 10];

    // Create a partial mock of ExpenseRequest to control internal methods
    $expenseRequest = Mockery::mock(ExpenseRequest::class)->makePartial();
    $expenseRequest->shouldReceive('validated')->andReturn($validatedData);
    $expenseRequest->shouldReceive('user')->andReturn($user);
    $expenseRequest->shouldReceive('header')->with('company')->andReturn('1');

    // Simulate magic properties like $this->currency_id, $this->amount, $this->exchange_rate
    // by making the partial mock behave like it has these properties.
    // In FormRequest, these typically come from input(). We set them directly for this test.
    $expenseRequest->currency_id = 'USD';
    $expenseRequest->amount = 100.00;
    $expenseRequest->exchange_rate = 1; // This won't be used as exchange_rate will be set to 1

    $payload = $expenseRequest->getExpensePayload();

    expect($payload)->toEqual(array_merge($validatedData, [
        'creator_id' => 10,
        'company_id' => '1',
        'exchange_rate' => 1,
        'base_amount' => 100.00, // 100 * 1
        'currency_id' => 'USD'
    ]));
    expect($payload['exchange_rate'])->toBe(1);
    expect($payload['base_amount'])->toBe(100.00);
});

test('getExpensePayload returns correct payload when company currency and request currency are different', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', '1')
        ->andReturn('EUR');

    $validatedData = [
        'expense_date' => '2023-01-01',
        'expense_category_id' => 1,
        'amount' => 100.00,
        'currency_id' => 'USD',
        'exchange_rate' => 1.2, // This is expected to come from validated data or input
        'notes' => 'Some notes',
    ];

    $user = (object)['id' => 10];

    // Create a partial mock of ExpenseRequest
    $expenseRequest = Mockery::mock(ExpenseRequest::class)->makePartial();
    $expenseRequest->shouldReceive('validated')->andReturn($validatedData);
    $expenseRequest->shouldReceive('user')->andReturn($user);
    $expenseRequest->shouldReceive('header')->with('company')->andReturn('1');

    // Set magic properties
    $expenseRequest->currency_id = 'USD';
    $expenseRequest->amount = 100.00;
    $expenseRequest->exchange_rate = 1.2;

    $payload = $expenseRequest->getExpensePayload();

    expect($payload)->toEqual(array_merge($validatedData, [
        'creator_id' => 10,
        'company_id' => '1',
        'exchange_rate' => 1.2,
        'base_amount' => 120.00, // 100 * 1.2
        'currency_id' => 'USD'
    ]));
    expect($payload['exchange_rate'])->toBe(1.2);
    expect($payload['base_amount'])->toBe(120.00);
});

test('getExpensePayload handles cases where currency_id or amount might be missing from request', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', '1')
        ->andReturn('USD');

    // Missing currency_id and amount from validated data and properties
    $validatedData = [
        'expense_date' => '2023-01-01',
        'expense_category_id' => 1,
        // 'amount' is missing, 'currency_id' is missing
    ];

    $user = (object)['id' => 10];

    $expenseRequest = Mockery::mock(ExpenseRequest::class)->makePartial();
    $expenseRequest->shouldReceive('validated')->andReturn($validatedData);
    $expenseRequest->shouldReceive('user')->andReturn($user);
    $expenseRequest->shouldReceive('header')->with('company')->andReturn('1');

    // Set properties to null to simulate them not being in request
    $expenseRequest->currency_id = null;
    $expenseRequest->amount = null;
    $expenseRequest->exchange_rate = null;

    // The rules define 'amount' and 'currency_id' as required,
    // so in a real scenario, this wouldn't pass validation.
    // However, for unit testing `getExpensePayload`'s *logic*,
    // we test how it behaves if these properties are not set.
    // PHP's multiplication of null would result in 0.

    $payload = $expenseRequest->getExpensePayload();

    // Expect base_amount to be 0 if amount is null
    expect($payload['base_amount'])->toBe(0.0);
    expect($payload['exchange_rate'])->toBe(1); // Default to 1 if currencies can't be compared
    expect($payload['currency_id'])->toBeNull(); // Should be null if not provided
    expect($payload['creator_id'])->toBe(10);
    expect($payload['company_id'])->toBe('1');
});

test('rules method includes all default validation rules', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', '1')
        ->andReturn('USD'); // Doesn't matter for this test's focus

    $request = createExpenseRequestWithData(
        ['currency_id' => 'USD'],
        ['company' => '1']
    );

    $rules = $request->rules();

    expect($rules)->toHaveKey('expense_date');
    expect($rules['expense_date'])->toContain('required');

    expect($rules)->toHaveKey('expense_category_id');
    expect($rules['expense_category_id'])->toContain('required');

    expect($rules)->toHaveKey('amount');
    expect($rules['amount'])->toContain('required');

    expect($rules)->toHaveKey('currency_id');
    expect($rules['currency_id'])->toContain('required');

    expect($rules)->toHaveKey('exchange_rate');
    expect($rules['exchange_rate'])->toContain('nullable'); // Default case

    expect($rules)->toHaveKey('payment_method_id');
    expect($rules['payment_method_id'])->toContain('nullable');

    expect($rules)->toHaveKey('customer_id');
    expect($rules['customer_id'])->toContain('nullable');

    expect($rules)->toHaveKey('notes');
    expect($rules['notes'])->toContain('nullable');

    expect($rules)->toHaveKey('attachment_receipt');
    expect($rules['attachment_receipt'])->toContain('nullable', 'file', 'mimes:jpg,png,pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:20000');
});

test('rules method defines correct validation for attachment_receipt', function () {
    CompanySetting::shouldReceive('getSetting')
        ->zeroOrMoreTimes() // Not relevant for this test
        ->andReturn('USD');

    $request = createExpenseRequestWithData(
        ['currency_id' => 'USD'],
        ['company' => '1']
    );

    $rules = $request->rules();
    expect($rules['attachment_receipt'])->toEqual([
        'nullable',
        'file',
        'mimes:jpg,png,pdf,doc,docx,xls,xlsx,ppt,pptx',
        'max:20000'
    ]);
});

test('getExpensePayload handles zero amount correctly', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', '1')
        ->andReturn('USD');

    $validatedData = [
        'expense_date' => '2023-01-01',
        'expense_category_id' => 1,
        'amount' => 0.00,
        'currency_id' => 'USD',
        'notes' => 'Some notes',
    ];

    $user = (object)['id' => 10];

    $expenseRequest = Mockery::mock(ExpenseRequest::class)->makePartial();
    $expenseRequest->shouldReceive('validated')->andReturn($validatedData);
    $expenseRequest->shouldReceive('user')->andReturn($user);
    $expenseRequest->shouldReceive('header')->with('company')->andReturn('1');

    $expenseRequest->currency_id = 'USD';
    $expenseRequest->amount = 0.00;
    $expenseRequest->exchange_rate = 1;

    $payload = $expenseRequest->getExpensePayload();

    expect($payload['base_amount'])->toBe(0.00); // 0 * 1
    expect($payload['exchange_rate'])->toBe(1);
});

test('getExpensePayload handles zero exchange rate correctly when different currencies', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', '1')
        ->andReturn('EUR');

    $validatedData = [
        'expense_date' => '2023-01-01',
        'expense_category_id' => 1,
        'amount' => 100.00,
        'currency_id' => 'USD',
        'exchange_rate' => 0.0,
        'notes' => 'Some notes',
    ];

    $user = (object)['id' => 10];

    $expenseRequest = Mockery::mock(ExpenseRequest::class)->makePartial();
    $expenseRequest->shouldReceive('validated')->andReturn($validatedData);
    $expenseRequest->shouldReceive('user')->andReturn($user);
    $expenseRequest->shouldReceive('header')->with('company')->andReturn('1');

    $expenseRequest->currency_id = 'USD';
    $expenseRequest->amount = 100.00;
    $expenseRequest->exchange_rate = 0.0;

    $payload = $expenseRequest->getExpensePayload();

    expect($payload['base_amount'])->toBe(0.0); // 100 * 0.0
    expect($payload['exchange_rate'])->toBe(0.0);
});

test('getExpensePayload includes all validated data in payload', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', '1')
        ->andReturn('USD');

    $validatedData = [
        'expense_date' => '2023-01-01',
        'expense_category_id' => 1,
        'amount' => 100.00,
        'currency_id' => 'USD',
        'payment_method_id' => 5,
        'customer_id' => 10,
        'notes' => 'Some notes',
        'attachment_receipt' => 'path/to/receipt.pdf'
    ];

    $user = (object)['id' => 10];

    $expenseRequest = Mockery::mock(ExpenseRequest::class)->makePartial();
    $expenseRequest->shouldReceive('validated')->andReturn($validatedData);
    $expenseRequest->shouldReceive('user')->andReturn($user);
    $expenseRequest->shouldReceive('header')->with('company')->andReturn('1');

    $expenseRequest->currency_id = 'USD';
    $expenseRequest->amount = 100.00;
    $expenseRequest->exchange_rate = 1;

    $payload = $expenseRequest->getExpensePayload();

    // Check if all original validated data keys are present and match
    foreach ($validatedData as $key => $value) {
        expect($payload)->toHaveKey($key);
        expect($payload[$key])->toBe($value);
    }

    // Check additional merged keys
    expect($payload)->toHaveKey('creator_id');
    expect($payload)->toHaveKey('company_id');
    expect($payload)->toHaveKey('exchange_rate');
    expect($payload)->toHaveKey('base_amount');
    expect($payload)->toHaveKey('currency_id');
});




afterEach(function () {
    Mockery::close();
});
