<?php

namespace Tests\Unit;

use Crater\Http\Resources\CurrencyCollection;
use Crater\Http\Resources\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

// Test model that matches the actual Currency model structure
// Based on the error, it needs 'precision' property
class TestCurrencyModel
{
    public $id;
    public $name;
    public $code;
    public $symbol;
    public $precision;
    public $thousand_separator;
    public $decimal_separator;
    public $swap_currency_symbol;
    public $exchange_rate;
    public $created_at;
    public $updated_at;

    public function __construct(array $attributes = [])
    {
        $this->id = $attributes['id'] ?? null;
        $this->name = $attributes['name'] ?? null;
        $this->code = $attributes['code'] ?? null;
        $this->symbol = $attributes['symbol'] ?? null;
        $this->precision = $attributes['precision'] ?? 2;
        $this->thousand_separator = $attributes['thousand_separator'] ?? ',';
        $this->decimal_separator = $attributes['decimal_separator'] ?? '.';
        $this->swap_currency_symbol = $attributes['swap_currency_symbol'] ?? false;
        $this->exchange_rate = $attributes['exchange_rate'] ?? 1.0;
        $this->created_at = $attributes['created_at'] ?? null;
        $this->updated_at = $attributes['updated_at'] ?? null;
    }
}

// Let's first test what properties CurrencyResource expects



test('currency collection returns empty array for empty collection', function () {
    $emptyCollection = collect([]);
    $collection = new CurrencyCollection($emptyCollection);
    $result = $collection->toArray(new Request());

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('currency collection handles single currency', function () {
    $currencies = collect([
        new TestCurrencyModel([
            'id' => 3,
            'name' => 'British Pound',
            'code' => 'GBP',
            'symbol' => '£',
            'precision' => 2,
        ]),
    ]);

    $collection = new CurrencyCollection($currencies);
    $result = $collection->toArray(new Request());

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0]['id'])->toBe(3)
        ->and($result[0]['name'])->toBe('British Pound')
        ->and($result[0]['code'])->toBe('GBP');
});


test('currency collection handles empty string values', function () {
    $currencies = collect([
        new TestCurrencyModel([
            'id' => 5,
            'name' => '',
            'code' => '',
            'symbol' => '',
            'precision' => '',
        ]),
    ]);

    $collection = new CurrencyCollection($currencies);
    $result = $collection->toArray(new Request());

    expect($result[0]['id'])->toBe(5)
        ->and($result[0]['name'])->toBe('')
        ->and($result[0]['code'])->toBe('')
        ->and($result[0]['symbol'])->toBe('')
        ->and($result[0]['precision'])->toBe('');
});

test('currency collection can be serialized to JSON', function () {
    $currencies = collect([
        new TestCurrencyModel([
            'id' => 6,
            'name' => 'Japanese Yen',
            'code' => 'JPY',
            'symbol' => '¥',
            'precision' => 0,
        ]),
    ]);

    $collection = new CurrencyCollection($currencies);
    $json = json_encode($collection);

    expect($json)->toBeJson()
        ->and(json_decode($json, true))->toBeArray()
        ->and(json_decode($json, true)[0]['id'])->toBe(6)
        ->and(json_decode($json, true)[0]['name'])->toBe('Japanese Yen')
        ->and(json_decode($json, true)[0]['code'])->toBe('JPY');
});

test('currency collection preserves collection metadata', function () {
    $currencies = collect([
        new TestCurrencyModel(['id' => 7, 'name' => 'Currency 1']),
        new TestCurrencyModel(['id' => 8, 'name' => 'Currency 2']),
    ]);

    $collection = new CurrencyCollection($currencies);
    
    // Add additional metadata
    $collection->additional(['meta' => ['total' => 2, 'page' => 1]]);
    
    $response = $collection->toResponse(new Request());
    $data = $response->getData(true);
    
    expect($data)->toHaveKey('data')
        ->and($data)->toHaveKey('meta')
        ->and($data['meta'])->toBe(['total' => 2, 'page' => 1])
        ->and($data['data'])->toHaveCount(2);
});


test('currency collection inherits from ResourceCollection', function () {
    $collection = new CurrencyCollection(collect([]));
    
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('currency collection static make method', function () {
    $currencies = collect([
        new TestCurrencyModel(['id' => 11, 'name' => 'Static Make']),
    ]);

    $collection = CurrencyCollection::make($currencies);
    $result = $collection->toArray(new Request());

    expect($result)->toBeArray()
        ->and($result[0]['id'])->toBe(11)
        ->and($result[0]['name'])->toBe('Static Make');
});

test('currency collection handles large number of items', function () {
    $currencies = collect();
    
    for ($i = 1; $i <= 50; $i++) {
        $currencies->push(new TestCurrencyModel([
            'id' => $i,
            'name' => "Currency {$i}",
            'code' => "C{$i}",
            'symbol' => "$",
            'precision' => 2,
        ]));
    }

    $collection = new CurrencyCollection($currencies);
    $result = $collection->toArray(new Request());

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(50)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[0]['name'])->toBe('Currency 1')
        ->and($result[49]['id'])->toBe(50)
        ->and($result[49]['name'])->toBe('Currency 50');
});

test('currency collection with special characters', function () {
    $currencies = collect([
        new TestCurrencyModel([
            'id' => 12,
            'name' => 'Currency with spécial chàracters',
            'code' => 'SP€CIAL',
            'symbol' => '¤',
            'precision' => 2,
        ]),
    ]);

    $collection = new CurrencyCollection($currencies);
    $result = $collection->toArray(new Request());

    expect($result[0]['name'])->toBe('Currency with spécial chàracters')
        ->and($result[0]['code'])->toBe('SP€CIAL')
        ->and($result[0]['symbol'])->toBe('¤');
});

test('currency collection response structure', function () {
    $currencies = collect([
        new TestCurrencyModel(['id' => 13, 'name' => 'Response Test']),
    ]);

    $collection = new CurrencyCollection($currencies);
    $response = $collection->toResponse(new Request());

    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('application/json');
});

test('currency collection with different precision values', function () {
    $testCases = [
        ['precision' => 0, 'description' => 'zero precision'],
        ['precision' => 2, 'description' => 'standard precision'],
        ['precision' => 4, 'description' => 'high precision'],
        ['precision' => -1, 'description' => 'negative precision'],
    ];

    foreach ($testCases as $testCase) {
        $currencies = collect([
            new TestCurrencyModel([
                'id' => 14,
                'name' => 'Precision Test',
                'code' => 'PT',
                'precision' => $testCase['precision'],
            ]),
        ]);

        $collection = new CurrencyCollection($currencies);
        $result = $collection->toArray(new Request());

        expect($result[0]['precision'])->toBe($testCase['precision']);
    }
});


test('currency collection with boolean swap_currency_symbol', function () {
    $testCases = [
        ['swap_currency_symbol' => true, 'expected' => true],
        ['swap_currency_symbol' => false, 'expected' => false],
        ['swap_currency_symbol' => 1, 'expected' => 1],
        ['swap_currency_symbol' => 0, 'expected' => 0],
    ];

    foreach ($testCases as $index => $testCase) {
        $currencies = collect([
            new TestCurrencyModel([
                'id' => 16 + $index,
                'name' => 'Swap Test',
                'swap_currency_symbol' => $testCase['swap_currency_symbol'],
            ]),
        ]);

        $collection = new CurrencyCollection($currencies);
        $result = $collection->toArray(new Request());

        expect($result[0]['swap_currency_symbol'])->toBe($testCase['expected']);
    }
});

test('currency collection with exchange_rate as decimal', function () {
    $currencies = collect([
        new TestCurrencyModel([
            'id' => 20,
            'name' => 'Exchange Test',
            'exchange_rate' => 1.2345,
        ]),
    ]);

    $collection = new CurrencyCollection($currencies);
    $result = $collection->toArray(new Request());

    expect($result[0]['exchange_rate'])->toBe(1.2345);
});

test('currency collection separator fields', function () {
    $currencies = collect([
        new TestCurrencyModel([
            'id' => 21,
            'name' => 'Separator Test',
            'thousand_separator' => '.',
            'decimal_separator' => ',',
        ]),
    ]);

    $collection = new CurrencyCollection($currencies);
    $result = $collection->toArray(new Request());

    expect($result[0]['thousand_separator'])->toBe('.')
        ->and($result[0]['decimal_separator'])->toBe(',');
});

test('currency collection works with object that has all required properties', function () {
    // Create a stdClass object with all required properties
    $currency = new \stdClass();
    $currency->id = 22;
    $currency->name = 'Object Currency';
    $currency->code = 'OC';
    $currency->symbol = 'O';
    $currency->precision = 2;
    $currency->thousand_separator = ',';
    $currency->decimal_separator = '.';
    $currency->swap_currency_symbol = false;
    $currency->exchange_rate = 1.0;
    $currency->created_at = null;
    $currency->updated_at = null;

    $currencies = collect([$currency]);
    $collection = new CurrencyCollection($currencies);
    $result = $collection->toArray(new Request());

    expect($result[0]['id'])->toBe(22)
        ->and($result[0]['name'])->toBe('Object Currency')
        ->and($result[0]['code'])->toBe('OC');
});