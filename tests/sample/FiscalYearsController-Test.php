```php
<?php

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Crater\Http\Controllers\V1\Admin\Config\FiscalYearsController;
use Mockery; // Explicitly use Mockery for clarity in beforeEach/afterEach hooks.

beforeEach(function () {
    // This mock is crucial for unit tests that involve instantiating Laravel components
    // (like controllers) while the Config facade is being mocked.
    // Laravel's internal service providers, such as the ViewServiceProvider,
    // often access configuration values during application setup using array-like access
    // (e.g., `$app['config']['view.paths']`).
    // When `Config::shouldReceive()` is used, Mockery replaces the real Config instance
    // with a strict mock. Any method call or array access that isn't explicitly mocked
    // will throw a `Mockery\Exception\BadMethodCallException`.
    // The debug output indicates the error originates from `Config::offsetGet('view.paths')`.
    // We mock this specific call to prevent the exception.
    // `zeroOrMoreTimes()` is used to allow this call any number of times, and
    // `andReturn([])` provides a harmless default value as the exact content of `view.paths`
    // is not relevant to these controller tests.
    Config::shouldReceive('offsetGet')
        ->with('view.paths')
        ->zeroOrMoreTimes()
        ->andReturn([]);
});

test('it returns fiscal years from config when present', function () {
    Config::shouldReceive('get')
        ->with('crater.fiscal_years')
        ->andReturn([2020, 2021, 2022]);

    $controller = new FiscalYearsController();
    $request = Request::create('/');

    $response = $controller->__invoke($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual([
            'fiscal_years' => [2020, 2021, 2022],
        ]);
});

test('it returns an empty array when fiscal years config is empty', function () {
    Config::shouldReceive('get')
        ->with('crater.fiscal_years')
        ->andReturn([]);

    $controller = new FiscalYearsController();
    $request = Request::create('/');

    $response = $controller->__invoke($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual([
            'fiscal_years' => [],
        ]);
});

test('it returns null for fiscal years when config is not set', function () {
    Config::shouldReceive('get')
        ->with('crater.fiscal_years')
        ->andReturn(null);

    $controller = new FiscalYearsController();
    $request = Request::create('/');

    $response = $controller->__invoke($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual([
            'fiscal_years' => null,
        ]);
});

test('it handles non-array fiscal years config value gracefully', function () {
    Config::shouldReceive('get')
        ->with('crater.fiscal_years')
        ->andReturn('just_a_string');

    $controller = new FiscalYearsController();
    $request = Request::create('/');

    $response = $controller->__invoke($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual([
            'fiscal_years' => 'just_a_string',
        ]);
});

test('it handles object fiscal years config value gracefully', function () {
    $objectConfig = (object)['start' => 2020, 'end' => 2021];
    Config::shouldReceive('get')
        ->with('crater.fiscal_years')
        ->andReturn($objectConfig);

    $controller = new FiscalYearsController();
    $request = Request::create('/');

    $response = $controller->__invoke($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual([
            'fiscal_years' => $objectConfig,
        ]);
});

afterEach(function () {
    // Cleans up Mockery expectations and mocks after each test to ensure isolation.
    Mockery::close();
});
```