<?php

use Crater\Http\Controllers\V1\Admin\General\CurrenciesController;
use Crater\Http\Resources\CurrencyResource;
use Crater\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

beforeEach(function () {
    Mockery::close();
});

test('it returns an empty collection of currency resources when no currencies exist', function () {
    $mockQueryBuilder = Mockery::mock();
    $mockQueryBuilder->shouldReceive('get')->once()->andReturn(new Collection());

    Mockery::mock('overload:' . Currency::class)
        ->shouldReceive('latest')
        ->once()
        ->andReturn($mockQueryBuilder);

    $mockAnonymousResourceCollection = Mockery::mock(AnonymousResourceCollection::class);
    $mockAnonymousResourceCollection->shouldReceive('jsonSerialize')->andReturn([]);

    Mockery::mock('alias:' . CurrencyResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($collection) {
            return $collection instanceof Collection && $collection->isEmpty();
        })
        ->andReturn($mockAnonymousResourceCollection);

    $controller = new CurrenciesController();
    $request = new Request();

    $response = $controller($request);

    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->jsonSerialize())->toBe([]);
});

test('it returns a collection of currency resources when currencies exist', function () {
    $currency1 = (object)['id' => 1, 'name' => 'USD', 'code' => 'USD', 'symbol' => '$', 'precision' => 2, 'created_at' => now(), 'updated_at' => now()];
    $currency2 = (object)['id' => 2, 'name' => 'EUR', 'code' => 'EUR', 'symbol' => '€', 'precision' => 2, 'created_at' => now(), 'updated_at' => now()];
    $mockCurrencies = new Collection([$currency1, $currency2]);

    $expectedResourceData = [
        ['id' => 1, 'name' => 'USD', 'code' => 'USD'],
        ['id' => 2, 'name' => 'EUR', 'code' => 'EUR'],
    ];

    $mockQueryBuilder = Mockery::mock();
    $mockQueryBuilder->shouldReceive('get')->once()->andReturn($mockCurrencies);

    Mockery::mock('overload:' . Currency::class)
        ->shouldReceive('latest')
        ->once()
        ->andReturn($mockQueryBuilder);

    $mockAnonymousResourceCollection = Mockery::mock(AnonymousResourceCollection::class);
    $mockAnonymousResourceCollection->shouldReceive('jsonSerialize')->andReturn($expectedResourceData);

    Mockery::mock('alias:' . CurrencyResource::class)
        ->shouldReceive('collection')
        ->once()
        ->withArgs(function ($collection) use ($mockCurrencies) {
            return $collection instanceof Collection && $collection->toArray() === $mockCurrencies->toArray();
        })
        ->andReturn($mockAnonymousResourceCollection);

    $controller = new CurrenciesController();
    $request = new Request();

    $response = $controller($request);

    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->jsonSerialize())->toBe($expectedResourceData);
});

afterEach(function () {
    Mockery::close();
});