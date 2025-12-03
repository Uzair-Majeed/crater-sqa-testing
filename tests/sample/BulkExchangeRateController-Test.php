<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use Crater\Http\Controllers\V1\Admin\General\BulkExchangeRateController;
use Crater\Http\Requests\BulkExchangeRateRequest;
use Crater\Models\CompanySetting;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Models\Tax;

beforeEach(function () {
    // Ensure mocks are properly cleaned up before each test
    Mockery::close();
});

/**
 * Helper for mocking static `where()->get()` calls on Eloquent models.
 * This sets up an alias mock for the model class to intercept static `where` calls.
 *
 * @param string $modelClass The fully qualified class name of the model (e.g., `Crater\Models\Invoice`).
 * @param array<int, array<MockInterface>> $expectedCurrencyToItemsMap A map where keys are currency IDs
 *                                                                    and values are arrays of mocked model instances
 *                                                                    that should be returned by `get()`.
 * @return void
 */
function mockModelStaticWhereGet(string $modelClass, array $expectedCurrencyToItemsMap): void
{
    // Only create an alias mock if it doesn't exist yet
    if (!Mockery::getContainer()->hasNamedMock("alias:$modelClass")) {
        $mockAlias = Mockery::mock("alias:$modelClass");
    } else {
        $mockAlias = Mockery::namedMock("alias:$modelClass");
    }
    foreach ($expectedCurrencyToItemsMap as $currencyId => $items) {
        $queryBuilderMock = Mockery::mock();
        $queryBuilderMock->shouldReceive('get')->andReturn(collect($items))->once();
        $mockAlias->shouldReceive('where')
            ->with('currency_id', $currencyId)
            ->andReturn($queryBuilderMock)
            ->once();
    }
}

// ========================================================================
// Test cases for __invoke method
// ========================================================================

test('invoke returns error when bulk exchange rate is already configured', function () {
    // Arrange
    $companyId = 1;
    $request = Mockery::mock(BulkExchangeRateRequest::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->shouldNotReceive('currencies'); // No need to access currencies if config is YES

    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('bulk_exchange_rate_configured', $companyId)
        ->andReturn('YES')
        ->once();

    // Directly use a real JsonResponse to compare objects, Pest doesn't handle class aliasing well
    $mockJsonResponse = new \Illuminate\Http\JsonResponse(['error' => false]);
    // Alias mock, but return our real JsonResponse instance
    Mockery::mock('alias:response')
        ->shouldReceive('json')
        ->with(['error' => false])
        ->andReturn($mockJsonResponse)
        ->once();

    $controller = new BulkExchangeRateController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBe($mockJsonResponse);
});

test('invoke processes currencies and updates models when bulk exchange rate is not configured', function () {
    // Arrange
    $companyId = 1;
    $currency1Id = 101;
    $currency2Id = 102;
    $exchangeRate1 = 1.2;
    $exchangeRate2 = 0.8;

    $request = Mockery::mock(BulkExchangeRateRequest::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->atLeast()->once();
    $request->currencies = [
        ['id' => $currency1Id, 'exchange_rate' => $exchangeRate1],
        ['id' => $currency2Id, 'exchange_rate' => $exchangeRate2],
    ];

    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('bulk_exchange_rate_configured', $companyId)
        ->andReturn('NO')
        ->once();
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('setSettings')
        ->with(['bulk_exchange_rate_configured' => 'YES'], $companyId)
        ->once();

    // Use real JsonResponse so object compares work in Pest
    $mockJsonResponse = new \Illuminate\Http\JsonResponse(['success' => true]);
    Mockery::mock('alias:response')
        ->shouldReceive('json')
        ->with(['success' => true])
        ->andReturn($mockJsonResponse)
        ->once();

    // Invoice mocks
    $invoice1_1 = Mockery::mock(Invoice::class);
    $invoice1_1->sub_total = 100; $invoice1_1->total = 120; $invoice1_1->tax = 20; $invoice1_1->due_amount = 50;
    $invoice1_1->shouldReceive('setAttribute')->with('exchange_rate', $exchangeRate1)->once();
    $invoice1_1->shouldReceive('setAttribute')->with('base_discount_val', 100 * $exchangeRate1)->once();
    $invoice1_1->shouldReceive('setAttribute')->with('base_sub_total', 100 * $exchangeRate1)->once();
    $invoice1_1->shouldReceive('setAttribute')->with('base_total', 120 * $exchangeRate1)->once();
    $invoice1_1->shouldReceive('setAttribute')->with('base_tax', 20 * $exchangeRate1)->once();
    $invoice1_1->shouldReceive('setAttribute')->with('base_due_amount', 50 * $exchangeRate1)->once();
    $invoice1_1->shouldReceive('update')->with([
        'exchange_rate' => $exchangeRate1,
        'base_discount_val' => 100 * $exchangeRate1, 'base_sub_total' => 100 * $exchangeRate1,
        'base_total' => 120 * $exchangeRate1, 'base_tax' => 20 * $exchangeRate1,
        'base_due_amount' => 50 * $exchangeRate1,
    ])->once();

    $invoice1_2 = Mockery::mock(Invoice::class);
    $invoice1_2->sub_total = 200; $invoice1_2->total = 240; $invoice1_2->tax = 40; $invoice1_2->due_amount = 100;
    $invoice1_2->shouldReceive('setAttribute')->with('exchange_rate', $exchangeRate1)->once();
    $invoice1_2->shouldReceive('setAttribute')->with('base_discount_val', 200 * $exchangeRate1)->once();
    $invoice1_2->shouldReceive('setAttribute')->with('base_sub_total', 200 * $exchangeRate1)->once();
    $invoice1_2->shouldReceive('setAttribute')->with('base_total', 240 * $exchangeRate1)->once();
    $invoice1_2->shouldReceive('setAttribute')->with('base_tax', 40 * $exchangeRate1)->once();
    $invoice1_2->shouldReceive('setAttribute')->with('base_due_amount', 100 * $exchangeRate1)->once();
    $invoice1_2->shouldReceive('update')->with([
        'exchange_rate' => $exchangeRate1,
        'base_discount_val' => 200 * $exchangeRate1, 'base_sub_total' => 200 * $exchangeRate1,
        'base_total' => 240 * $exchangeRate1, 'base_tax' => 40 * $exchangeRate1,
        'base_due_amount' => 100 * $exchangeRate1,
    ])->once();

    $invoice2_1 = Mockery::mock(Invoice::class);
    $invoice2_1->sub_total = 50; $invoice2_1->total = 60; $invoice2_1->tax = 10; $invoice2_1->due_amount = 25;
    $invoice2_1->shouldReceive('setAttribute')->with('exchange_rate', $exchangeRate2)->once();
    $invoice2_1->shouldReceive('setAttribute')->with('base_discount_val', 50 * $exchangeRate2)->once();
    $invoice2_1->shouldReceive('setAttribute')->with('base_sub_total', 50 * $exchangeRate2)->once();
    $invoice2_1->shouldReceive('setAttribute')->with('base_total', 60 * $exchangeRate2)->once();
    $invoice2_1->shouldReceive('setAttribute')->with('base_tax', 10 * $exchangeRate2)->once();
    $invoice2_1->shouldReceive('setAttribute')->with('base_due_amount', 25 * $exchangeRate2)->once();
    $invoice2_1->shouldReceive('update')->with([
        'exchange_rate' => $exchangeRate2,
        'base_discount_val' => 50 * $exchangeRate2, 'base_sub_total' => 50 * $exchangeRate2,
        'base_total' => 60 * $exchangeRate2, 'base_tax' => 10 * $exchangeRate2,
        'base_due_amount' => 25 * $exchangeRate2,
    ])->once();
    mockModelStaticWhereGet(Invoice::class, [
        $currency1Id => [$invoice1_1, $invoice1_2],
        $currency2Id => [$invoice2_1],
    ]);

    // Estimate mocks
    $estimate1_1 = Mockery::mock(Estimate::class);
    $estimate1_1->sub_total = 150; $estimate1_1->total = 180; $estimate1_1->tax = 30;
    $estimate1_1->shouldReceive('setAttribute')->with('exchange_rate', $exchangeRate1)->once();
    $estimate1_1->shouldReceive('setAttribute')->with('base_discount_val', 150 * $exchangeRate1)->once();
    $estimate1_1->shouldReceive('setAttribute')->with('base_sub_total', 150 * $exchangeRate1)->once();
    $estimate1_1->shouldReceive('setAttribute')->with('base_total', 180 * $exchangeRate1)->once();
    $estimate1_1->shouldReceive('setAttribute')->with('base_tax', 30 * $exchangeRate1)->once();
    $estimate1_1->shouldReceive('update')->with([
        'exchange_rate' => $exchangeRate1,
        'base_discount_val' => 150 * $exchangeRate1, 'base_sub_total' => 150 * $exchangeRate1,
        'base_total' => 180 * $exchangeRate1, 'base_tax' => 30 * $exchangeRate1,
    ])->once();
    mockModelStaticWhereGet(Estimate::class, [
        $currency1Id => [$estimate1_1],
        $currency2Id => [], // No estimates for currency 2
    ]);

    // Tax mocks
    $tax1_1 = Mockery::mock(Tax::class); $tax1_1->base_amount = 50; // Initial amount
    $tax1_1->shouldReceive('setAttribute')->with('base_amount', 50 * $exchangeRate1)->once();
    $tax1_1->shouldReceive('setAttribute')->with('exchange_rate', $exchangeRate1)->once();
    $tax1_1->shouldReceive('save')->once()->andReturnTrue();
    $tax2_1 = Mockery::mock(Tax::class); $tax2_1->base_amount = 75; // Initial amount
    $tax2_1->shouldReceive('setAttribute')->with('base_amount', 75 * $exchangeRate2)->once();
    $tax2_1->shouldReceive('setAttribute')->with('exchange_rate', $exchangeRate2)->once();
    $tax2_1->shouldReceive('save')->once()->andReturnTrue();
    mockModelStaticWhereGet(Tax::class, [
        $currency1Id => [$tax1_1],
        $currency2Id => [$tax2_1],
    ]);

    // Payment mocks
    $payment1_1 = Mockery::mock(Payment::class); $payment1_1->amount = 200;
    $payment1_1->shouldReceive('setAttribute')->with('exchange_rate', $exchangeRate1)->once();
    $payment1_1->shouldReceive('setAttribute')->with('base_amount', 200 * $exchangeRate1)->once();
    $payment1_1->shouldReceive('save')->once()->andReturnTrue();
    mockModelStaticWhereGet(Payment::class, [
        $currency1Id => [$payment1_1],
        $currency2Id => [], // No payments for currency 2
    ]);

    // Partial mock the controller to isolate `items` calls (taxes on models are internal to items)
    $controller = test()->partialMock(BulkExchangeRateController::class, function (MockInterface $mock) use ($invoice1_1, $invoice1_2, $invoice2_1, $estimate1_1) {
        $mock->shouldReceive('items')->with($invoice1_1)->once();
        $mock->shouldReceive('items')->with($invoice1_2)->once();
        $mock->shouldReceive('items')->with($invoice2_1)->once();
        $mock->shouldReceive('items')->with($estimate1_1)->once();
    });

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBe($mockJsonResponse);
});

test('invoke handles currencies with missing exchange rate defaulting to 1', function () {
    // Arrange
    $companyId = 1;
    $currencyId = 101;
    $defaultExchangeRate = 1; // Expected default

    $request = Mockery::mock(BulkExchangeRateRequest::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->atLeast()->once();
    $request->currencies = [
        ['id' => $currencyId], // No exchange_rate provided
    ];

    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('bulk_exchange_rate_configured', $companyId)
        ->andReturn('NO')
        ->once();
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('setSettings')
        ->with(['bulk_exchange_rate_configured' => 'YES'], $companyId)
        ->once();

    $mockJsonResponse = new \Illuminate\Http\JsonResponse(['success' => true]);
    Mockery::mock('alias:response')
        ->shouldReceive('json')
        ->with(['success' => true])
        ->andReturn($mockJsonResponse)
        ->once();

    $invoice = Mockery::mock(Invoice::class);
    $invoice->sub_total = 100; $invoice->total = 120; $invoice->tax = 20; $invoice->due_amount = 50;
    $invoice->shouldReceive('setAttribute')->with('exchange_rate', $defaultExchangeRate)->once();
    $invoice->shouldReceive('setAttribute')->with('base_discount_val', 100 * $defaultExchangeRate)->once();
    $invoice->shouldReceive('setAttribute')->with('base_sub_total', 100 * $defaultExchangeRate)->once();
    $invoice->shouldReceive('setAttribute')->with('base_total', 120 * $defaultExchangeRate)->once();
    $invoice->shouldReceive('setAttribute')->with('base_tax', 20 * $defaultExchangeRate)->once();
    $invoice->shouldReceive('setAttribute')->with('base_due_amount', 50 * $defaultExchangeRate)->once();
    $invoice->shouldReceive('update')->with([
        'exchange_rate' => $defaultExchangeRate,
        'base_discount_val' => 100 * $defaultExchangeRate, 'base_sub_total' => 100 * $defaultExchangeRate,
        'base_total' => 120 * $defaultExchangeRate, 'base_tax' => 20 * $defaultExchangeRate,
        'base_due_amount' => 50 * $defaultExchangeRate,
    ])->once();
    mockModelStaticWhereGet(Invoice::class, [$currencyId => [$invoice]]);

    // Mock other models as empty to keep focus on exchange_rate default
    mockModelStaticWhereGet(Estimate::class, [$currencyId => []]);
    mockModelStaticWhereGet(Tax::class, [$currencyId => []]);
    mockModelStaticWhereGet(Payment::class, [$currencyId => []]);

    $controller = test()->partialMock(BulkExchangeRateController::class, function (MockInterface $mock) use ($invoice) {
        $mock->shouldReceive('items')->with($invoice)->once();
    });

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBe($mockJsonResponse);
});

test('invoke handles no currencies in request', function () {
    // Arrange
    $companyId = 1;

    $request = Mockery::mock(BulkExchangeRateRequest::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->currencies = null; // No currencies

    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('bulk_exchange_rate_configured', $companyId)
        ->andReturn('NO')
        ->once();
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('setSettings')
        ->with(['bulk_exchange_rate_configured' => 'YES'], $companyId)
        ->once();

    $mockJsonResponse = new \Illuminate\Http\JsonResponse(['success' => true]);
    Mockery::mock('alias:response')
        ->shouldReceive('json')
        ->with(['success' => true])
        ->andReturn($mockJsonResponse)
        ->once();

    // Ensure no model interactions happen if no currencies are provided
    // Only alias-mock if not already created, otherwise retrieve
    if (!Mockery::getContainer()->hasNamedMock('alias:' . Invoice::class)) {
        $invoiceAlias = Mockery::mock('alias:' . Invoice::class);
    } else {
        $invoiceAlias = Mockery::namedMock('alias:' . Invoice::class);
    }
    $invoiceAlias->shouldNotReceive('where');

    if (!Mockery::getContainer()->hasNamedMock('alias:' . Estimate::class)) {
        $estimateAlias = Mockery::mock('alias:' . Estimate::class);
    } else {
        $estimateAlias = Mockery::namedMock('alias:' . Estimate::class);
    }
    $estimateAlias->shouldNotReceive('where');

    if (!Mockery::getContainer()->hasNamedMock('alias:' . Tax::class)) {
        $taxAlias = Mockery::mock('alias:' . Tax::class);
    } else {
        $taxAlias = Mockery::namedMock('alias:' . Tax::class);
    }
    $taxAlias->shouldNotReceive('where');

    if (!Mockery::getContainer()->hasNamedMock('alias:' . Payment::class)) {
        $paymentAlias = Mockery::mock('alias:' . Payment::class);
    } else {
        $paymentAlias = Mockery::namedMock('alias:' . Payment::class);
    }
    $paymentAlias->shouldNotReceive('where');

    // Do not partial mock items/taxes as they should not be called
    $controller = new BulkExchangeRateController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBe($mockJsonResponse);
});

// ========================================================================
// Test cases for items method
// ========================================================================

test('items updates all child items and calls taxes on them and the model', function () {
    // Arrange
    // We partial mock the controller to isolate the 'taxes' method calls.
    $controller = test()->partialMock(BulkExchangeRateController::class, function (MockInterface $mock) {
        // One call for each item, plus one for the main model.
        $mock->shouldReceive('taxes')->times(3);
    });

    $modelExchangeRate = 1.5;

    $item1 = Mockery::mock(\stdClass::class); // Represents an InvoiceItem or EstimateItem
    $item1->discount_val = 10; $item1->price = 50; $item1->tax = 5; $item1->total = 55;
    $item1->shouldReceive('setAttribute')->with('exchange_rate', $modelExchangeRate)->once();
    $item1->shouldReceive('setAttribute')->with('base_discount_val', 10 * $modelExchangeRate)->once();
    $item1->shouldReceive('setAttribute')->with('base_price', 50 * $modelExchangeRate)->once();
    $item1->shouldReceive('setAttribute')->with('base_tax', 5 * $modelExchangeRate)->once();
    $item1->shouldReceive('setAttribute')->with('base_total', 55 * $modelExchangeRate)->once();
    $item1->shouldReceive('update')->with([
        'exchange_rate' => $modelExchangeRate,
        'base_discount_val' => 10 * $modelExchangeRate,
        'base_price' => 50 * $modelExchangeRate,
        'base_tax' => 5 * $modelExchangeRate,
        'base_total' => 55 * $modelExchangeRate,
    ])->once();

    $item2 = Mockery::mock(\stdClass::class);
    $item2->discount_val = 20; $item2->price = 100; $item2->tax = 10; $item2->total = 110;
    $item2->shouldReceive('setAttribute')->with('exchange_rate', $modelExchangeRate)->once();
    $item2->shouldReceive('setAttribute')->with('base_discount_val', 20 * $modelExchangeRate)->once();
    $item2->shouldReceive('setAttribute')->with('base_price', 100 * $modelExchangeRate)->once();
    $item2->shouldReceive('setAttribute')->with('base_tax', 10 * $modelExchangeRate)->once();
    $item2->shouldReceive('setAttribute')->with('base_total', 110 * $modelExchangeRate)->once();
    $item2->shouldReceive('update')->with([
        'exchange_rate' => $modelExchangeRate,
        'base_discount_val' => 20 * $modelExchangeRate,
        'base_price' => 100 * $modelExchangeRate,
        'base_tax' => 10 * $modelExchangeRate,
        'base_total' => 110 * $modelExchangeRate,
    ])->once();

    $model = Mockery::mock(Invoice::class); // Could also be Estimate, or any model with 'items' relationship
    $model->exchange_rate = $modelExchangeRate;
    $model->shouldReceive('setAttribute')->with('exchange_rate', $modelExchangeRate)->zeroOrMoreTimes();
    $model->items = collect([$item1, $item2]); // The collection of child items

    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('items');
    $method->setAccessible(true); // Ensure it's callable even if it were protected/private

    // Act
    $method->invoke($controller, $model);

    // Assertions are handled by Mockery expectations on $item1, $item2, and the controller mock
});

test('items handles model with no child items gracefully', function () {
    // Arrange
    $controller = test()->partialMock(BulkExchangeRateController::class, function (MockInterface $mock) {
        $mock->shouldReceive('taxes')->with(Mockery::any())->once(); // Only taxes for the main model
    });

    $model = Mockery::mock(Invoice::class);
    $model->exchange_rate = 1.5;
    $model->shouldReceive('setAttribute')->with('exchange_rate', 1.5)->zeroOrMoreTimes();
    $model->items = collect([]); // Empty items collection
    $model->shouldNotReceive('update'); // Ensure no update calls on phantom items

    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('items');
    $method->setAccessible(true);

    // Act
    $method->invoke($controller, $model);

    // Assertions handled by Mockery expectations (only one taxes call for the model itself)
});

// ========================================================================
// Test cases for taxes method
// ========================================================================

test('taxes updates all related taxes when they exist', function () {
    // Arrange
    $controller = new BulkExchangeRateController(); // No need for partial mock here

    $modelExchangeRate = 1.8;

    $tax1 = Mockery::mock(Tax::class);
    $tax1->amount = 25;
    $tax1->shouldReceive('setAttribute')->with('exchange_rate', $modelExchangeRate)->once();
    $tax1->shouldReceive('setAttribute')->with('base_amount', 25 * $modelExchangeRate)->once();
    $tax1->shouldReceive('update')->with([
        'exchange_rate' => $modelExchangeRate,
        'base_amount' => 25 * $modelExchangeRate,
    ])->once();

    $tax2 = Mockery::mock(Tax::class);
    $tax2->amount = 30;
    $tax2->shouldReceive('setAttribute')->with('exchange_rate', $modelExchangeRate)->once();
    $tax2->shouldReceive('setAttribute')->with('base_amount', 30 * $modelExchangeRate)->once();
    $tax2->shouldReceive('update')->with([
        'exchange_rate' => $modelExchangeRate,
        'base_amount' => 30 * $modelExchangeRate,
    ])->once();

    $modelTaxRelation = Mockery::mock(); // Represents the HasMany relation builder
    $modelTaxRelation->shouldReceive('exists')->andReturnTrue()->once();

    $model = Mockery::mock(Invoice::class); // Could be Invoice, Estimate, Item (any model with 'taxes' relation)
    $model->exchange_rate = $modelExchangeRate;
    $model->shouldReceive('setAttribute')->with('exchange_rate', $modelExchangeRate)->zeroOrMoreTimes();
    $model->shouldReceive('taxes')->andReturn($modelTaxRelation); // taxes() method returns the relation
    $model->taxes = collect([$tax1, $tax2]); // taxes property holds the actual collection for mapping

    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('taxes');
    $method->setAccessible(true);

    // Act
    $method->invoke($controller, $model);

    // Assertions handled by Mockery expectations on $tax1 and $tax2
});

test('taxes does nothing when no related taxes exist', function () {
    // Arrange
    $controller = new BulkExchangeRateController();

    $modelTaxRelation = Mockery::mock();
    $modelTaxRelation->shouldReceive('exists')->andReturnFalse()->once();

    $model = Mockery::mock(Invoice::class);
    $model->exchange_rate = 1.8;
    $model->shouldReceive('setAttribute')->with('exchange_rate', 1.8)->zeroOrMoreTimes();
    $model->shouldReceive('taxes')->andReturn($modelTaxRelation);
    $model->taxes = collect([]); // Ensure empty collection when exists is false to match typical Eloquent behavior

    // Ensure no update calls or map operations on taxes
    // No need to mock Tax::class alias, since the taxes collection is empty

    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('taxes');
    $method->setAccessible(true);

    // Act
    $method->invoke($controller, $model);

    // Assertions handled by Mockery expectations (nothing should be called on Tax models)
});

test('taxes handles an empty collection even if exists is true (edge case)', function () {
    // Arrange
    $controller = new BulkExchangeRateController();

    $modelTaxRelation = Mockery::mock();
    $modelTaxRelation->shouldReceive('exists')->andReturnTrue()->once(); // exists returns true

    $model = Mockery::mock(Invoice::class);
    $model->exchange_rate = 1.8;
    $model->shouldReceive('setAttribute')->with('exchange_rate', 1.8)->zeroOrMoreTimes();
    $model->shouldReceive('taxes')->andReturn($modelTaxRelation);
    $model->taxes = collect([]); // Empty collection, even if `exists()` was true (unlikely but possible edge case)

    // We don't expect 'update' to be called on any tax model as the collection is empty.

    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('taxes');
    $method->setAccessible(true);

    // Act
    $method->invoke($controller, $model);

    // Assertions handled by Mockery expectations (no Tax model updates)
});

afterEach(function () {
    Mockery::close();
});