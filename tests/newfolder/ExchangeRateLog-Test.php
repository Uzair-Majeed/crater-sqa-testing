```php
<?php

use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\ExchangeRateLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Mockery;
use Illuminate\Support\Facades\Facade;

beforeEach(function () {
    Facade::clearResolvedInstances();
    Mockery::close();
});

test('it belongs to a currency using the currency_id foreign key', function () {
    $log = new ExchangeRateLog();
    $relation = $log->currency();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())
        ->toBeInstanceOf(Currency::class)
        ->and($relation->getForeignKeyName())
        ->toBe('currency_id') // Default foreign key for a method named 'currency'
        ->and($relation->getOwnerKeyName())
        ->toBe('id');
});

test('it belongs to a company using the company_id foreign key', function () {
    $log = new ExchangeRateLog();
    $relation = $log->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())
        ->toBeInstanceOf(Company::class)
        ->and($relation->getForeignKeyName())
        ->toBe('company_id')
        ->and($relation->getOwnerKeyName())
        ->toBe('id');
});

test('addExchangeRateLog creates a new log entry with correct data', function () {
    $companySettingMock = Mockery::mock('overload:' . CompanySetting::class);
    $companySettingMock->shouldReceive('getSetting')
        ->with('currency', 1)
        ->andReturn(2)
        ->once();

    // Swap out ExchangeRateLog::create with a partial mock using Laravel's shouldReceive
    $exchangeRateLogMock = Mockery::mock('overload:' . ExchangeRateLog::class);

    $modelInput = (object) [
        'exchange_rate' => 1.23,
        'company_id' => 1,
        'currency_id' => 10,
    ];

    $expectedDataForCreate = [
        'exchange_rate' => 1.23,
        'company_id' => 1,
        'base_currency_id' => 10,
        'currency_id' => 2,
    ];

    $createdLogInstance = new ExchangeRateLog(['id' => 5] + $expectedDataForCreate);

    ExchangeRateLog::shouldReceive('create')
        ->withArgs(function ($args) use ($expectedDataForCreate) {
            return $args === $expectedDataForCreate;
        })
        ->andReturn($createdLogInstance)
        ->once();

    $result = ExchangeRateLog::addExchangeRateLog($modelInput);

    expect($result)->toBeInstanceOf(ExchangeRateLog::class)
        ->and($result->id)->toBe(5)
        ->and($result->exchange_rate)->toBe(1.23)
        ->and($result->company_id)->toBe(1)
        ->and($result->base_currency_id)->toBe(10)
        ->and($result->currency_id)->toBe(2);
});

test('addExchangeRateLog handles different input values and company settings', function () {
    $companySettingMock = Mockery::mock('overload:' . CompanySetting::class);
    $companySettingMock->shouldReceive('getSetting')
        ->with('currency', 5)
        ->andReturn(99)
        ->once();

    $exchangeRateLogMock = Mockery::mock('overload:' . ExchangeRateLog::class);

    $modelInput = (object) [
        'exchange_rate' => 0.5,
        'company_id' => 5,
        'currency_id' => 101,
    ];

    $expectedDataForCreate = [
        'exchange_rate' => 0.5,
        'company_id' => 5,
        'base_currency_id' => 101,
        'currency_id' => 99,
    ];

    $createdLogInstance = new ExchangeRateLog(['id' => 10] + $expectedDataForCreate);

    ExchangeRateLog::shouldReceive('create')
        ->with($expectedDataForCreate)
        ->andReturn($createdLogInstance)
        ->once();

    $result = ExchangeRateLog::addExchangeRateLog($modelInput);

    expect($result)->toBeInstanceOf(ExchangeRateLog::class)
        ->and($result->id)->toBe(10)
        ->and($result->exchange_rate)->toBe(0.5)
        ->and($result->company_id)->toBe(5)
        ->and($result->base_currency_id)->toBe(101)
        ->and($result->currency_id)->toBe(99);
});

test('addExchangeRateLog passes null for currency_id if CompanySetting returns null', function () {
    $companySettingMock = Mockery::mock('overload:' . CompanySetting::class);
    $companySettingMock->shouldReceive('getSetting')
        ->with('currency', 3)
        ->andReturn(null)
        ->once();

    $exchangeRateLogMock = Mockery::mock('overload:' . ExchangeRateLog::class);

    $modelInput = (object) [
        'exchange_rate' => 2.0,
        'company_id' => 3,
        'currency_id' => 20,
    ];

    $expectedDataForCreate = [
        'exchange_rate' => 2.0,
        'company_id' => 3,
        'base_currency_id' => 20,
        'currency_id' => null,
    ];

    $createdLogInstance = new ExchangeRateLog(['id' => 15] + $expectedDataForCreate);

    ExchangeRateLog::shouldReceive('create')
        ->with($expectedDataForCreate)
        ->andReturn($createdLogInstance)
        ->once();

    $result = ExchangeRateLog::addExchangeRateLog($modelInput);

    expect($result)->toBeInstanceOf(ExchangeRateLog::class)
        ->and($result->id)->toBe(15)
        ->and($result->exchange_rate)->toBe(2.0)
        ->and($result->company_id)->toBe(3)
        ->and($result->base_currency_id)->toBe(20)
        ->and($result->currency_id)->toBeNull();
});

test('exchange_rate attribute is cast to float', function () {
    $log = new ExchangeRateLog(['exchange_rate' => '1.23']);
    expect($log->exchange_rate)->toBeFloat()->toBe(1.23);

    $log = new ExchangeRateLog(['exchange_rate' => '1']);
    expect($log->exchange_rate)->toBeFloat()->toBe(1.0);

    $log = new ExchangeRateLog(['exchange_rate' => null]);
    expect($log->exchange_rate)->toBeNull(); // Laravel's float cast often keeps null as null.

    $log = new ExchangeRateLog(['exchange_rate' => 'invalid']);
    // Eloquent's float casting can convert non-numeric strings to 0 or throw errors depending on strict mode.
    // Default behavior is usually 0.0 or exception.
    // For white-box testing, we assume valid input or test how it handles invalid data.
    // Here, we'll test for what Eloquent typically does with invalid string to float cast.
    expect($log->exchange_rate)->toBe(0.0);
});

test('id is a guarded property and cannot be mass-assigned', function () {
    $log = new ExchangeRateLog(); // Start with a model without ID
    $log->fill(['id' => 999, 'exchange_rate' => 2.5]);

    // The 'id' should not be mass-assignable by fill()
    expect($log->id)->toBeNull(); // It should remain null as it was not set initially
    expect($log->exchange_rate)->toBe(2.5); // Other fillable attributes should be updated
});

afterEach(function () {
    Facade::clearResolvedInstances();
    Mockery::close();
});

```