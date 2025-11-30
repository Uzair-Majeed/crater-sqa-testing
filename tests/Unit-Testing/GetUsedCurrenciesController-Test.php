<?php

uses(\Mockery::class);
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Crater\Models\ExchangeRateProvider;
use Crater\Http\Controllers\V1\Admin\ExchangeRate\GetUsedCurrenciesController;

// Clean up Mockery after each test
beforeEach(function () {
    Mockery::close();
});

test('it returns all and active used currencies when provider ID is null and data exists', function () {
    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->provider_id = null; // Property access for the controller
    $request->shouldReceive('get')->with('provider_id')->andReturn(null); // Method access for robustness

    // Mock Controller's authorize method
    $controller = Mockery::mock(GetUsedCurrenciesController::class . '[authorize]');
    $controller->shouldReceive('authorize')->with('viewAny', ExchangeRateProvider::class)->once();

    // ----------------------------------------------------
    // Mocking for the first query chain: activeExchangeRateProviders
    // `ExchangeRateProvider::where('active', true)->whereCompany()->when($providerId, ...)->pluck('currencies');`
    $activeQueryBuilderMock = Mockery::mock(Builder::class);
    $activeQueryBuilderMock->shouldReceive('whereCompany')->andReturnSelf()->once(); // The scope call
    // Since $providerId is null, the 'when' condition is false, so the callback is not executed.
    // The `when` method just returns the builder itself.
    $activeQueryBuilderMock->shouldReceive('when')
        ->with(null, Mockery::type('callable')) // Condition is null, so it evaluates to false
        ->andReturnSelf()
        ->once();
    $activeQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([
            ['USD', 'EUR'],
            ['GBP'],
        ]))
        ->once();
    $activeQueryBuilderMock->shouldNotReceive('where')->with('id', '<>', Mockery::any()); // Should not apply where condition

    // ----------------------------------------------------
    // Mocking for the second query chain: allExchangeRateProviders
    // `ExchangeRateProvider::whereCompany()->pluck('currencies');`
    $allQueryBuilderMock = Mockery::mock(Builder::class);
    $allQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([
            ['USD', 'EUR'],
            ['GBP'],
            ['JPY'],
        ]))
        ->once();

    // Mock the static calls to ExchangeRateProvider
    // This uses Pest's `mock()` helper, which sets up a static mock for the class
    mock(ExchangeRateProvider::class, function ($mock) use ($activeQueryBuilderMock, $allQueryBuilderMock) {
        $mock->shouldReceive('where')
            ->with('active', true)
            ->andReturn($activeQueryBuilderMock) // Returns the configured Builder mock for the active chain
            ->once();
        $mock->shouldReceive('whereCompany')
            ->andReturn($allQueryBuilderMock) // Returns the configured Builder mock for the all chain
            ->once();
    });

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response->getData(true))->toEqual([
        'allUsedCurrencies' => ['USD', 'EUR', 'GBP', 'JPY'],
        'activeUsedCurrencies' => ['USD', 'EUR', 'GBP'],
    ]);
});

test('it filters active used currencies when provider ID is present', function () {
    $providerId = 123;
    $request = Mockery::mock(Request::class);
    $request->provider_id = $providerId;
    $request->shouldReceive('get')->with('provider_id')->andReturn($providerId);

    $controller = Mockery::mock(GetUsedCurrenciesController::class . '[authorize]');
    $controller->shouldReceive('authorize')->with('viewAny', ExchangeRateProvider::class)->once();

    // Mock Builder for active chain
    $activeQueryBuilderMock = Mockery::mock(Builder::class);
    $activeQueryBuilderMock->shouldReceive('whereCompany')->andReturnSelf()->once();
    // 'when' condition is true, callback executed. It should call `where('id', '<>', $providerId)` on itself.
    $activeQueryBuilderMock->shouldReceive('when')
        ->with($providerId, Mockery::type('callable')) // Condition is $providerId (truthy)
        ->andReturnUsing(function ($condition, $callback) use ($activeQueryBuilderMock) {
            // Simulate the callback modifying the query builder
            $callback($activeQueryBuilderMock);
            return $activeQueryBuilderMock;
        })
        ->once();
    // Expect the `where` call from inside the 'when' callback
    $activeQueryBuilderMock->shouldReceive('where')->with('id', '<>', $providerId)->andReturnSelf()->once();
    $activeQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([
            ['USD', 'EUR'],
            ['GBP'],
        ]))
        ->once();

    // Mock Builder for all chain
    $allQueryBuilderMock = Mockery::mock(Builder::class);
    $allQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([
            ['USD', 'EUR'],
            ['GBP'],
            ['JPY'],
        ]))
        ->once();

    mock(ExchangeRateProvider::class, function ($mock) use ($activeQueryBuilderMock, $allQueryBuilderMock) {
        $mock->shouldReceive('where')
            ->with('active', true)
            ->andReturn($activeQueryBuilderMock)
            ->once();
        $mock->shouldReceive('whereCompany')
            ->andReturn($allQueryBuilderMock)
            ->once();
    });

    $response = $controller->__invoke($request);

    expect($response->getData(true))->toEqual([
        'allUsedCurrencies' => ['USD', 'EUR', 'GBP', 'JPY'],
        'activeUsedCurrencies' => ['USD', 'EUR', 'GBP'],
    ]);
});

test('it returns empty active currencies if no active providers found', function () {
    $request = Mockery::mock(Request::class);
    $request->provider_id = null;
    $request->shouldReceive('get')->with('provider_id')->andReturn(null);

    $controller = Mockery::mock(GetUsedCurrenciesController::class . '[authorize]');
    $controller->shouldReceive('authorize')->with('viewAny', ExchangeRateProvider::class)->once();

    $activeQueryBuilderMock = Mockery::mock(Builder::class);
    $activeQueryBuilderMock->shouldReceive('whereCompany')->andReturnSelf()->once();
    $activeQueryBuilderMock->shouldReceive('when')->with(null, Mockery::type('callable'))->andReturnSelf()->once();
    $activeQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([])) // Simulate no active providers
        ->once();

    $allQueryBuilderMock = Mockery::mock(Builder::class);
    $allQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([
            ['USD', 'EUR'],
            ['GBP'],
        ]))
        ->once();

    mock(ExchangeRateProvider::class, function ($mock) use ($activeQueryBuilderMock, $allQueryBuilderMock) {
        $mock->shouldReceive('where')
            ->with('active', true)
            ->andReturn($activeQueryBuilderMock)
            ->once();
        $mock->shouldReceive('whereCompany')
            ->andReturn($allQueryBuilderMock)
            ->once();
    });

    $response = $controller->__invoke($request);

    expect($response->getData(true))->toEqual([
        'allUsedCurrencies' => ['USD', 'EUR', 'GBP'],
        'activeUsedCurrencies' => [],
    ]);
});

test('it returns empty all currencies if no providers found', function () {
    $request = Mockery::mock(Request::class);
    $request->provider_id = null;
    $request->shouldReceive('get')->with('provider_id')->andReturn(null);

    $controller = Mockery::mock(GetUsedCurrenciesController::class . '[authorize]');
    $controller->shouldReceive('authorize')->with('viewAny', ExchangeRateProvider::class)->once();

    $activeQueryBuilderMock = Mockery::mock(Builder::class);
    $activeQueryBuilderMock->shouldReceive('whereCompany')->andReturnSelf()->once();
    $activeQueryBuilderMock->shouldReceive('when')->with(null, Mockery::type('callable'))->andReturnSelf()->once();
    $activeQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([
            ['USD', 'EUR'],
        ]))
        ->once();

    $allQueryBuilderMock = Mockery::mock(Builder::class);
    $allQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([])) // Simulate no providers at all
        ->once();

    mock(ExchangeRateProvider::class, function ($mock) use ($activeQueryBuilderMock, $allQueryBuilderMock) {
        $mock->shouldReceive('where')
            ->with('active', true)
            ->andReturn($activeQueryBuilderMock)
            ->once();
        $mock->shouldReceive('whereCompany')
            ->andReturn($allQueryBuilderMock)
            ->once();
    });

    $response = $controller->__invoke($request);

    expect($response->getData(true))->toEqual([
        'allUsedCurrencies' => [],
        'activeUsedCurrencies' => ['USD', 'EUR'],
    ]);
});

test('it handles non-array currency data gracefully', function () {
    $request = Mockery::mock(Request::class);
    $request->provider_id = null;
    $request->shouldReceive('get')->with('provider_id')->andReturn(null);

    $controller = Mockery::mock(GetUsedCurrenciesController::class . '[authorize]');
    $controller->shouldReceive('authorize')->with('viewAny', ExchangeRateProvider::class)->once();

    $activeQueryBuilderMock = Mockery::mock(Builder::class);
    $activeQueryBuilderMock->shouldReceive('whereCompany')->andReturnSelf()->once();
    $activeQueryBuilderMock->shouldReceive('when')->with(null, Mockery::type('callable'))->andReturnSelf()->once();
    $activeQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([
            ['USD', 'EUR'],
            'invalid_string', // Non-array data
            null,             // Non-array data
            ['GBP'],
        ]))
        ->once();

    $allQueryBuilderMock = Mockery::mock(Builder::class);
    $allQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([
            'not_an_array',
            ['JPY'],
        ]))
        ->once();

    mock(ExchangeRateProvider::class, function ($mock) use ($activeQueryBuilderMock, $allQueryBuilderMock) {
        $mock->shouldReceive('where')
            ->with('active', true)
            ->andReturn($activeQueryBuilderMock)
            ->once();
        $mock->shouldReceive('whereCompany')
            ->andReturn($allQueryBuilderMock)
            ->once();
    });

    $response = $controller->__invoke($request);

    expect($response->getData(true))->toEqual([
        'allUsedCurrencies' => ['JPY'], // Only valid arrays processed
        'activeUsedCurrencies' => ['USD', 'EUR', 'GBP'], // Only valid arrays processed
    ]);
});

test('it handles empty currency arrays correctly', function () {
    $request = Mockery::mock(Request::class);
    $request->provider_id = null;
    $request->shouldReceive('get')->with('provider_id')->andReturn(null);

    $controller = Mockery::mock(GetUsedCurrenciesController::class . '[authorize]');
    $controller->shouldReceive('authorize')->with('viewAny', ExchangeRateProvider::class)->once();

    $activeQueryBuilderMock = Mockery::mock(Builder::class);
    $activeQueryBuilderMock->shouldReceive('whereCompany')->andReturnSelf()->once();
    $activeQueryBuilderMock->shouldReceive('when')->with(null, Mockery::type('callable'))->andReturnSelf()->once();
    $activeQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([
            ['USD'],
            [], // Empty array
            ['EUR'],
        ]))
        ->once();

    $allQueryBuilderMock = Mockery::mock(Builder::class);
    $allQueryBuilderMock->shouldReceive('pluck')
        ->with('currencies')
        ->andReturn(collect([
            ['JPY'],
            [], // Empty array
            ['GBP'],
        ]))
        ->once();

    mock(ExchangeRateProvider::class, function ($mock) use ($activeQueryBuilderMock, $allQueryBuilderMock) {
        $mock->shouldReceive('where')
            ->with('active', true)
            ->andReturn($activeQueryBuilderMock)
            ->once();
        $mock->shouldReceive('whereCompany')
            ->andReturn($allQueryBuilderMock)
            ->once();
    });

    $response = $controller->__invoke($request);

    expect($response->getData(true))->toEqual([
        'allUsedCurrencies' => ['JPY', 'GBP'],
        'activeUsedCurrencies' => ['USD', 'EUR'],
    ]);
});
