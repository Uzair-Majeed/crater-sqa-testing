<?php

use Mockery as m;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Crater\Http\Controllers\V1\Admin\General\GetAllUsedCurrenciesController;
use Crater\Models\Currency;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Models\Tax;

// Using a group for better organization and ensuring Mockery setup/teardown
uses()->group('GetAllUsedCurrenciesController');

// Ensure Mockery is properly torn down after each test to prevent static mock leaks
afterEach(function () {
    m::close();
});

test('it returns an empty array of currencies when no models have null exchange rates', function () {
    // Arrange: Mock all model queries to return empty collections of currency IDs.
    $mockInvoiceQueryBuilder = m::mock();
    Invoice::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockInvoiceQueryBuilder);
    $mockInvoiceQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection());

    $mockTaxQueryBuilder = m::mock();
    Tax::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockTaxQueryBuilder);
    $mockTaxQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection());

    $mockEstimateQueryBuilder = m::mock();
    Estimate::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockEstimateQueryBuilder);
    $mockEstimateQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection());

    $mockPaymentQueryBuilder = m::mock();
    Payment::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockPaymentQueryBuilder);
    $mockPaymentQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection());

    // Expect Currency::whereIn to be called with an empty array, and return an empty collection.
    $mockCurrencyQueryBuilder = m::mock();
    Currency::shouldReceive('whereIn')->once()->with('id', [])->andReturn($mockCurrencyQueryBuilder);
    $mockCurrencyQueryBuilder->shouldReceive('get')->once()->andReturn(new Collection());

    $controller = new GetAllUsedCurrenciesController();
    $request = new Request(); // The request object itself is not used in the logic

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true))->toEqual(['currencies' => []]);
});

test('it returns a single currency when only one model has a currency with null exchange rate', function () {
    // Arrange
    $invoiceCurrencyId = 1;
    $expectedCurrency = ['id' => $invoiceCurrencyId, 'code' => 'USD', 'name' => 'US Dollar'];
    $mockCurrencyObject = (object)$expectedCurrency; // Eloquent returns objects from get()
    $mockCurrencyCollection = new Collection([$mockCurrencyObject]);

    $invoiceIds = [$invoiceCurrencyId];
    $taxIds = [];
    $estimateIds = [];
    $paymentIds = [];
    $mergedIds = array_merge($invoiceIds, $taxIds, $estimateIds, $paymentIds); // Should be [1]

    // Mock Invoice to return currency ID 1, others return empty
    $mockInvoiceQueryBuilder = m::mock();
    Invoice::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockInvoiceQueryBuilder);
    $mockInvoiceQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($invoiceIds));

    $mockTaxQueryBuilder = m::mock();
    Tax::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockTaxQueryBuilder);
    $mockTaxQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($taxIds));

    $mockEstimateQueryBuilder = m::mock();
    Estimate::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockEstimateQueryBuilder);
    $mockEstimateQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($estimateIds));

    $mockPaymentQueryBuilder = m::mock();
    Payment::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockPaymentQueryBuilder);
    $mockPaymentQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($paymentIds));

    // Expect Currency::whereIn to be called with the merged ID and return the mock currency.
    $mockCurrencyQueryBuilder = m::mock();
    Currency::shouldReceive('whereIn')->once()->with('id', $mergedIds)->andReturn($mockCurrencyQueryBuilder);
    $mockCurrencyQueryBuilder->shouldReceive('get')->once()->andReturn($mockCurrencyCollection);

    $controller = new GetAllUsedCurrenciesController();
    $request = new Request();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true))->toEqual(['currencies' => [$expectedCurrency]]);
});

test('it returns multiple unique currencies from different models', function () {
    // Arrange
    $invoiceCurrencyId = 1;
    $taxCurrencyId = 2;
    $estimateCurrencyId = 3;
    $paymentCurrencyId = 4;

    $expectedCurrencies = [
        ['id' => $invoiceCurrencyId, 'code' => 'USD', 'name' => 'US Dollar'],
        ['id' => $taxCurrencyId, 'code' => 'EUR', 'name' => 'Euro'],
        ['id' => $estimateCurrencyId, 'code' => 'GBP', 'name' => 'British Pound'],
        ['id' => $paymentCurrencyId, 'code' => 'JPY', 'name' => 'Japanese Yen'],
    ];
    $mockCurrencyCollection = new Collection(array_map(fn($c) => (object)$c, $expectedCurrencies));

    $invoiceIds = [$invoiceCurrencyId];
    $taxIds = [$taxCurrencyId];
    $estimateIds = [$estimateCurrencyId];
    $paymentIds = [$paymentCurrencyId];
    $mergedIds = array_merge($invoiceIds, $taxIds, $estimateIds, $paymentIds); // Should be [1, 2, 3, 4]

    // Mock each model to return a unique currency ID
    $mockInvoiceQueryBuilder = m::mock();
    Invoice::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockInvoiceQueryBuilder);
    $mockInvoiceQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($invoiceIds));

    $mockTaxQueryBuilder = m::mock();
    Tax::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockTaxQueryBuilder);
    $mockTaxQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($taxIds));

    $mockEstimateQueryBuilder = m::mock();
    Estimate::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockEstimateQueryBuilder);
    $mockEstimateQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($estimateIds));

    $mockPaymentQueryBuilder = m::mock();
    Payment::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockPaymentQueryBuilder);
    $mockPaymentQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($paymentIds));

    // Expect Currency::whereIn with all unique IDs, returning the collection of mock currencies.
    $mockCurrencyQueryBuilder = m::mock();
    Currency::shouldReceive('whereIn')->once()->with('id', $mergedIds)->andReturn($mockCurrencyQueryBuilder);
    $mockCurrencyQueryBuilder->shouldReceive('get')->once()->andReturn($mockCurrencyCollection);

    $controller = new GetAllUsedCurrenciesController();
    $request = new Request();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true)['currencies'])->toHaveCount(4);
    expect(json_decode($response->getContent(), true)['currencies'])->toEqual($expectedCurrencies);
});

test('it handles duplicate currency IDs gracefully across different models and within the same model', function () {
    // Arrange
    $currencyId1 = 1;
    $currencyId2 = 2;

    $expectedCurrencies = [
        ['id' => $currencyId1, 'code' => 'USD', 'name' => 'US Dollar'],
        ['id' => $currencyId2, 'code' => 'EUR', 'name' => 'Euro'],
    ];
    // The Currency::whereIn will effectively fetch only the unique ones from the database.
    $mockCurrencyCollection = new Collection(array_map(fn($c) => (object)$c, $expectedCurrencies));

    $invoiceIds = [$currencyId1, $currencyId1]; // Duplicate within invoice
    $taxIds = [$currencyId2];
    $estimateIds = [$currencyId1]; // Duplicate across models
    $paymentIds = [$currencyId2, $currencyId2]; // Duplicate within payment and across models
    $mergedIds = array_merge($invoiceIds, $taxIds, $estimateIds, $paymentIds); // Result: [1, 1, 2, 1, 2, 2]

    // Mock models to return lists containing duplicates
    $mockInvoiceQueryBuilder = m::mock();
    Invoice::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockInvoiceQueryBuilder);
    $mockInvoiceQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($invoiceIds));

    $mockTaxQueryBuilder = m::mock();
    Tax::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockTaxQueryBuilder);
    $mockTaxQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($taxIds));

    $mockEstimateQueryBuilder = m::mock();
    Estimate::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockEstimateQueryBuilder);
    $mockEstimateQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($estimateIds));

    $mockPaymentQueryBuilder = m::mock();
    Payment::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockPaymentQueryBuilder);
    $mockPaymentQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($paymentIds));

    // Expect Currency::whereIn with the raw merged array (including duplicates).
    // Eloquent's `whereIn` internally handles deduplication.
    $mockCurrencyQueryBuilder = m::mock();
    Currency::shouldReceive('whereIn')->once()->with('id', $mergedIds)->andReturn($mockCurrencyQueryBuilder);
    $mockCurrencyQueryBuilder->shouldReceive('get')->once()->andReturn($mockCurrencyCollection);

    $controller = new GetAllUsedCurrenciesController();
    $request = new Request();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    // The final result should only contain unique currencies from the database lookup
    expect(json_decode($response->getContent(), true)['currencies'])->toHaveCount(2);
    expect(json_decode($response->getContent(), true)['currencies'])->toEqual($expectedCurrencies);
});

test('it returns currencies when some models return empty and others return IDs', function () {
    // Arrange
    $invoiceCurrencyId = 1;
    $estimateCurrencyId = 3;

    $expectedCurrencies = [
        ['id' => $invoiceCurrencyId, 'code' => 'USD', 'name' => 'US Dollar'],
        ['id' => $estimateCurrencyId, 'code' => 'GBP', 'name' => 'British Pound'],
    ];
    $mockCurrencyCollection = new Collection(array_map(fn($c) => (object)$c, $expectedCurrencies));

    $invoiceIds = [$invoiceCurrencyId];
    $taxIds = [];
    $estimateIds = [$estimateCurrencyId];
    $paymentIds = [];
    $mergedIds = array_merge($invoiceIds, $taxIds, $estimateIds, $paymentIds); // Should be [1, 3]

    // Mock Invoice and Estimate to return IDs, Tax and Payment to return empty
    $mockInvoiceQueryBuilder = m::mock();
    Invoice::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockInvoiceQueryBuilder);
    $mockInvoiceQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($invoiceIds));

    $mockTaxQueryBuilder = m::mock();
    Tax::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockTaxQueryBuilder);
    $mockTaxQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($taxIds));

    $mockEstimateQueryBuilder = m::mock();
    Estimate::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockEstimateQueryBuilder);
    $mockEstimateQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($estimateIds));

    $mockPaymentQueryBuilder = m::mock();
    Payment::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockPaymentQueryBuilder);
    $mockPaymentQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($paymentIds));

    // Expect Currency::whereIn with the combined IDs from non-empty sources.
    $mockCurrencyQueryBuilder = m::mock();
    Currency::shouldReceive('whereIn')->once()->with('id', $mergedIds)->andReturn($mockCurrencyQueryBuilder);
    $mockCurrencyQueryBuilder->shouldReceive('get')->once()->andReturn($mockCurrencyCollection);

    $controller = new GetAllUsedCurrenciesController();
    $request = new Request();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true)['currencies'])->toHaveCount(2);
    expect(json_decode($response->getContent(), true)['currencies'])->toEqual($expectedCurrencies);
});

test('it handles cases where all models return the same currency ID', function () {
    // Arrange
    $currencyId = 10;
    $expectedCurrency = ['id' => $currencyId, 'code' => 'AUD', 'name' => 'Australian Dollar'];
    $mockCurrencyObject = (object)$expectedCurrency;
    $mockCurrencyCollection = new Collection([$mockCurrencyObject]);

    $ids = [$currencyId];
    $mergedIds = array_merge($ids, $ids, $ids, $ids); // Result: [10, 10, 10, 10]

    // Mock all models to return the same single currency ID
    $mockInvoiceQueryBuilder = m::mock();
    Invoice::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockInvoiceQueryBuilder);
    $mockInvoiceQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($ids));

    $mockTaxQueryBuilder = m::mock();
    Tax::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockTaxQueryBuilder);
    $mockTaxQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($ids));

    $mockEstimateQueryBuilder = m::mock();
    Estimate::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockEstimateQueryBuilder);
    $mockEstimateQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($ids));

    $mockPaymentQueryBuilder = m::mock();
    Payment::shouldReceive('where')->once()->with('exchange_rate', null)->andReturn($mockPaymentQueryBuilder);
    $mockPaymentQueryBuilder->shouldReceive('pluck')->once()->with('currency_id')->andReturn(new Collection($ids));

    // Expect Currency::whereIn with the repeated ID, but `get()` will only return the unique one.
    $mockCurrencyQueryBuilder = m::mock();
    Currency::shouldReceive('whereIn')->once()->with('id', $mergedIds)->andReturn($mockCurrencyQueryBuilder);
    $mockCurrencyQueryBuilder->shouldReceive('get')->once()->andReturn($mockCurrencyCollection);

    $controller = new GetAllUsedCurrenciesController();
    $request = new Request();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true)['currencies'])->toHaveCount(1);
    expect(json_decode($response->getContent(), true)['currencies'])->toEqual([$expectedCurrency]);
});



