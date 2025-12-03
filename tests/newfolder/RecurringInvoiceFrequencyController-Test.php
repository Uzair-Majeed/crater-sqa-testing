<?php

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

// Ensure Mockery is closed after each test to prevent static mock pollution
beforeEach(function () {
    Mockery::close();
});

test('it calculates the next invoice date for daily frequency successfully', function () {
    // Arrange
    $frequency = 'daily';
    $startsAt = '2023-01-01';
    $expectedNextInvoiceAt = '2023-01-02';

    // Create a request instance with the necessary parameters
    $request = Request::create('/api/v1/admin/recurring-invoice/frequency', 'GET', [
        'frequency' => $frequency,
        'starts_at' => $startsAt,
    ]);

    // Mock the static method on the RecurringInvoice model
    // Using Mockery::mock('alias:...') to mock static methods.
    // The leading backslash ensures a global namespace alias for Crater\Models\RecurringInvoice.
    $recurringInvoiceMock = Mockery::mock('alias:\Crater\Models\RecurringInvoice');
    $recurringInvoiceMock
        ->shouldReceive('getNextInvoiceDate')
        ->once()
        ->with($frequency, $startsAt)
        ->andReturn($expectedNextInvoiceAt);

    $controller = new \Crater\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceFrequencyController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => true,
        'next_invoice_at' => $expectedNextInvoiceAt,
    ]);
});

test('it calculates the next invoice date for weekly frequency successfully', function () {
    // Arrange
    $frequency = 'weekly';
    $startsAt = '2023-01-01'; // Sunday
    $expectedNextInvoiceAt = '2023-01-08'; // Next Sunday

    $request = Request::create('/api/v1/admin/recurring-invoice/frequency', 'GET', [
        'frequency' => $frequency,
        'starts_at' => $startsAt,
    ]);

    $recurringInvoiceMock = Mockery::mock('alias:\Crater\Models\RecurringInvoice');
    $recurringInvoiceMock
        ->shouldReceive('getNextInvoiceDate')
        ->once()
        ->with($frequency, $startsAt)
        ->andReturn($expectedNextInvoiceAt);

    $controller = new \Crater\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceFrequencyController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => true,
        'next_invoice_at' => $expectedNextInvoiceAt,
    ]);
});

test('it calculates the next invoice date for monthly frequency successfully', function () {
    // Arrange
    $frequency = 'monthly';
    $startsAt = '2023-01-15';
    $expectedNextInvoiceAt = '2023-02-15';

    $request = Request::create('/api/v1/admin/recurring-invoice/frequency', 'GET', [
        'frequency' => $frequency,
        'starts_at' => $startsAt,
    ]);

    $recurringInvoiceMock = Mockery::mock('alias:\Crater\Models\RecurringInvoice');
    $recurringInvoiceMock
        ->shouldReceive('getNextInvoiceDate')
        ->once()
        ->with($frequency, $startsAt)
        ->andReturn($expectedNextInvoiceAt);

    $controller = new \Crater\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceFrequencyController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => true,
        'next_invoice_at' => $expectedNextInvoiceAt,
    ]);
});

test('it calculates the next invoice date for yearly frequency successfully', function () {
    // Arrange
    $frequency = 'yearly';
    $startsAt = '2023-03-01';
    $expectedNextInvoiceAt = '2024-03-01';

    $request = Request::create('/api/v1/admin/recurring-invoice/frequency', 'GET', [
        'frequency' => $frequency,
        'starts_at' => $startsAt,
    ]);

    $recurringInvoiceMock = Mockery::mock('alias:\Crater\Models\RecurringInvoice');
    $recurringInvoiceMock
        ->shouldReceive('getNextInvoiceDate')
        ->once()
        ->with($frequency, $startsAt)
        ->andReturn($expectedNextInvoiceAt);

    $controller = new \Crater\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceFrequencyController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => true,
        'next_invoice_at' => $expectedNextInvoiceAt,
    ]);
});

test('it returns null for next invoice date if starts_at parameter is missing in the request', function () {
    // Arrange
    $frequency = 'daily';
    $startsAt = null; // $request->starts_at will be null if not present in the request
    $expectedNextInvoiceAt = null; // Assuming getNextInvoiceDate handles null gracefully

    $request = Request::create('/api/v1/admin/recurring-invoice/frequency', 'GET', [
        'frequency' => $frequency,
        // 'starts_at' is intentionally omitted
    ]);

    $recurringInvoiceMock = Mockery::mock('alias:\Crater\Models\RecurringInvoice');
    $recurringInvoiceMock
        ->shouldReceive('getNextInvoiceDate')
        ->once()
        ->with($frequency, $startsAt)
        ->andReturn($expectedNextInvoiceAt);

    $controller = new \Crater\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceFrequencyController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => true,
        'next_invoice_at' => $expectedNextInvoiceAt,
    ]);
});

test('it returns null for next invoice date if frequency parameter is missing in the request', function () {
    // Arrange
    $frequency = null; // $request->frequency will be null if not present in the request
    $startsAt = '2023-01-01';
    $expectedNextInvoiceAt = null; // Assuming getNextInvoiceDate handles null gracefully

    $request = Request::create('/api/v1/admin/recurring-invoice/frequency', 'GET', [
        'starts_at' => $startsAt,
        // 'frequency' is intentionally omitted
    ]);

    $recurringInvoiceMock = Mockery::mock('alias:\Crater\Models\RecurringInvoice');
    $recurringInvoiceMock
        ->shouldReceive('getNextInvoiceDate')
        ->once()
        ->with($frequency, $startsAt)
        ->andReturn($expectedNextInvoiceAt);

    $controller = new \Crater\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceFrequencyController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => true,
        'next_invoice_at' => $expectedNextInvoiceAt,
    ]);
});

test('it returns null for next invoice date if both frequency and starts_at parameters are missing', function () {
    // Arrange
    $frequency = null;
    $startsAt = null;
    $expectedNextInvoiceAt = null; // Assuming getNextInvoiceDate handles both null gracefully

    $request = Request::create('/api/v1/admin/recurring-invoice/frequency', 'GET', [
        // Both parameters are intentionally omitted
    ]);

    $recurringInvoiceMock = Mockery::mock('alias:\Crater\Models\RecurringInvoice');
    $recurringInvoiceMock
        ->shouldReceive('getNextInvoiceDate')
        ->once()
        ->with($frequency, $startsAt)
        ->andReturn($expectedNextInvoiceAt);

    $controller = new \Crater\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceFrequencyController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => true,
        'next_invoice_at' => $expectedNextInvoiceAt,
    ]);
});

test('it correctly handles different types of return values from getNextInvoiceDate, such as Carbon objects', function () {
    // Arrange
    $frequency = 'daily';
    $startsAt = '2023-01-01';
    // Simulate getNextInvoiceDate returning a Carbon instance
    $carbonInstance = Carbon::parse('2023-01-02 10:30:00');
    // The JsonResponse will serialize Carbon dates to ISO 8601 format by default
    $expectedNextInvoiceAtString = $carbonInstance->toISOString();

    $request = Request::create('/api/v1/admin/recurring-invoice/frequency', 'GET', [
        'frequency' => $frequency,
        'starts_at' => $startsAt,
    ]);

    $recurringInvoiceMock = Mockery::mock('alias:\Crater\Models\RecurringInvoice');
    $recurringInvoiceMock
        ->shouldReceive('getNextInvoiceDate')
        ->once()
        ->with($frequency, $startsAt)
        ->andReturn($carbonInstance); // Mock returns a Carbon object

    $controller = new \Crater\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceFrequencyController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => true,
        'next_invoice_at' => $expectedNextInvoiceAtString, // The response should convert it to string
    ]);
});


afterEach(function () {
    Mockery::close();
});