<?php

use Crater\Http\Controllers\V1\Admin\General\NumberPlaceholdersController;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

// Ensure Mockery is reset before each test to prevent cross-test contamination.
beforeEach(function () {
    Mockery::close();
});

// Data provider for falsy format values that should lead to an empty placeholders array.
// In PHP, null and empty string ('') are considered falsy in a boolean context.
$falsyFormats = [
    'null_format' => [null],
    'empty_string_format' => [''],
];

test('__invoke returns empty placeholders when format is falsy (null or empty string)', function (?string $formatValue) {
    // Arrange
    $request = Mockery::mock(Request::class);
    // Mimic the behavior when `format` parameter is missing or an empty string.
    // The controller accesses $request->format (property access), which internally calls $request->all().
    $request->shouldReceive('all')->andReturn(['format' => $formatValue]);
    // Also mock the 'get' method for robustness, in case the controller uses $request->get('format').
    $request->shouldReceive('get')->with('format', null)->andReturn($formatValue);

    // Crucially, ensure SerialNumberFormatter::getPlaceholders is NOT called in this case,
    // as the `if ($request->format)` condition should evaluate to false.
    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldNotReceive('getPlaceholders');

    $controller = new NumberPlaceholdersController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => true,
        'placeholders' => [],
    ]);
})->with($falsyFormats); // Use Pest's data provider feature

test('__invoke returns correct placeholders for a simple valid format string', function () {
    // Arrange
    $format = 'INV-{YY}{MM}-{NO}';
    $expectedPlaceholders = ['YY', 'MM', 'NO'];

    $request = Mockery::mock(Request::class);
    // The controller accesses $request->format (property access), which internally calls $request->all().
    $request->shouldReceive('all')->andReturn(['format' => $format]);
    // Also mock the 'get' method for robustness.
    $request->shouldReceive('get')->with('format', null)->andReturn($format);

    // Mock the static method getPlaceholders of SerialNumberFormatter.
    // It should be called exactly once with the provided format string.
    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->once()
        ->with($format)
        ->andReturn($expectedPlaceholders);

    $controller = new NumberPlaceholdersController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => true,
        'placeholders' => $expectedPlaceholders,
    ]);
});

test('__invoke returns correct placeholders for a complex valid format string', function () {
    // Arrange
    $format = 'ORDER-{YYYY}{MM}{DD}-CLIENT-{CL}-SEQUENCE-{SEQ}-CUSTOM{CUST}';
    $expectedPlaceholders = ['YYYY', 'MM', 'DD', 'CL', 'SEQ', 'CUST'];

    $request = Mockery::mock(Request::class);
    // The controller accesses $request->format (property access), which internally calls $request->all().
    $request->shouldReceive('all')->andReturn(['format' => $format]);
    // Also mock the 'get' method for robustness.
    $request->shouldReceive('get')->with('format', null)->andReturn($format);

    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->once()
        ->with($format)
        ->andReturn($expectedPlaceholders);

    $controller = new NumberPlaceholdersController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'success' => true,
        'placeholders' => $expectedPlaceholders,
    ]);
});

test('__invoke gracefully handles non-array return from SerialNumberFormatter (dependency edge case)', function () {
    // Arrange
    $format = 'FORMAT-RETURNING-NON-ARRAY';
    $unexpectedReturnValue = 'this is not an array, it is a string'; // Simulate an unexpected return type from the static method

    $request = Mockery::mock(Request::class);
    // The controller accesses $request->format (property access), which internally calls $request->all().
    $request->shouldReceive('all')->andReturn(['format' => $format]);
    // Also mock the 'get' method for robustness.
    $request->shouldReceive('get')->with('format', null)->andReturn($format);

    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->once()
        ->with($format)
        ->andReturn($unexpectedReturnValue);

    $controller = new NumberPlaceholdersController();

    // Act
    $response = $controller($request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    // The controller simply assigns the return value without explicit type checking or casting,
    // so the response should contain whatever the dependency returned.
    expect($response->getData(true))->toEqual([
        'success' => true,
        'placeholders' => $unexpectedReturnValue,
    ]);
});

afterEach(function () {
    Mockery::close();
});