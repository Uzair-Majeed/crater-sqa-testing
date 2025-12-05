<?php

use Crater\Models\ExchangeRateProvider;
use Crater\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ========== CLASS STRUCTURE TESTS ==========

test('ExchangeRateProvider can be instantiated', function () {
    $provider = new ExchangeRateProvider();
    expect($provider)->toBeInstanceOf(ExchangeRateProvider::class);
});

test('ExchangeRateProvider extends Model', function () {
    $provider = new ExchangeRateProvider();
    expect($provider)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
});

test('ExchangeRateProvider is in correct namespace', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Models');
});

test('ExchangeRateProvider is not abstract', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('ExchangeRateProvider is instantiable', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== GUARDED PROPERTIES TESTS ==========

test('ExchangeRateProvider has guarded properties', function () {
    $provider = new ExchangeRateProvider();
    expect($provider->getGuarded())->toBe(['id']);
});

test('id cannot be mass assigned', function () {
    $provider = new ExchangeRateProvider();
    $provider->fill(['id' => 999, 'driver' => 'test']);
    
    expect($provider->id)->toBeNull()
        ->and($provider->driver)->toBe('test');
});

// ========== CASTS TESTS ==========

test('currencies is cast to array', function () {
    $provider = new ExchangeRateProvider();
    $casts = $provider->getCasts();
    
    expect($casts)->toHaveKey('currencies')
        ->and($casts['currencies'])->toBe('array');
});

test('driver_config is cast to array', function () {
    $provider = new ExchangeRateProvider();
    $casts = $provider->getCasts();
    
    expect($casts)->toHaveKey('driver_config')
        ->and($casts['driver_config'])->toBe('array');
});

test('active is cast to boolean', function () {
    $provider = new ExchangeRateProvider();
    $casts = $provider->getCasts();
    
    expect($casts)->toHaveKey('active')
        ->and($casts['active'])->toBe('boolean');
});

// ========== RELATIONSHIP TESTS ==========

test('company method exists', function () {
    $provider = new ExchangeRateProvider();
    expect(method_exists($provider, 'company'))->toBeTrue();
});

test('company relationship returns BelongsTo', function () {
    $provider = new ExchangeRateProvider();
    $relation = $provider->company();
    
    expect($relation)->toBeInstanceOf(BelongsTo::class);
});

test('company relationship is to Company model', function () {
    $provider = new ExchangeRateProvider();
    $relation = $provider->company();
    
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
});

test('company relationship uses company_id foreign key', function () {
    $provider = new ExchangeRateProvider();
    $relation = $provider->company();
    
    expect($relation->getForeignKeyName())->toBe('company_id');
});

test('company relationship uses id owner key', function () {
    $provider = new ExchangeRateProvider();
    $relation = $provider->company();
    
    expect($relation->getOwnerKeyName())->toBe('id');
});

// ========== SETCURRENCIESATTRIBUTE TESTS ==========

test('setCurrenciesAttribute method exists', function () {
    $provider = new ExchangeRateProvider();
    expect(method_exists($provider, 'setCurrenciesAttribute'))->toBeTrue();
});

test('setCurrenciesAttribute json encodes array', function () {
    $provider = new ExchangeRateProvider();
    $currencies = ['USD', 'EUR', 'GBP'];
    
    $provider->setCurrenciesAttribute($currencies);
    
    $reflection = new ReflectionProperty($provider, 'attributes');
    $reflection->setAccessible(true);
    $attributes = $reflection->getValue($provider);
    
    expect($attributes['currencies'])->toBe(json_encode($currencies));
});

test('setCurrenciesAttribute handles empty array', function () {
    $provider = new ExchangeRateProvider();
    $currencies = [];
    
    $provider->setCurrenciesAttribute($currencies);
    
    $reflection = new ReflectionProperty($provider, 'attributes');
    $reflection->setAccessible(true);
    $attributes = $reflection->getValue($provider);
    
    expect($attributes['currencies'])->toBe(json_encode([]));
});

test('setCurrenciesAttribute handles single currency', function () {
    $provider = new ExchangeRateProvider();
    $currencies = ['USD'];
    
    $provider->setCurrenciesAttribute($currencies);
    
    $reflection = new ReflectionProperty($provider, 'attributes');
    $reflection->setAccessible(true);
    $attributes = $reflection->getValue($provider);
    
    expect($attributes['currencies'])->toBe(json_encode(['USD']));
});

// ========== SETDRIVERCONFIGATTRIBUTE TESTS ==========

test('setDriverConfigAttribute method exists', function () {
    $provider = new ExchangeRateProvider();
    expect(method_exists($provider, 'setDriverConfigAttribute'))->toBeTrue();
});

test('setDriverConfigAttribute json encodes array', function () {
    $provider = new ExchangeRateProvider();
    $config = ['api_key' => 'test123', 'base_url' => 'https://api.example.com'];
    
    $provider->setDriverConfigAttribute($config);
    
    $reflection = new ReflectionProperty($provider, 'attributes');
    $reflection->setAccessible(true);
    $attributes = $reflection->getValue($provider);
    
    expect($attributes['driver_config'])->toBe(json_encode($config));
});

test('setDriverConfigAttribute handles empty config', function () {
    $provider = new ExchangeRateProvider();
    $config = [];
    
    $provider->setDriverConfigAttribute($config);
    
    $reflection = new ReflectionProperty($provider, 'attributes');
    $reflection->setAccessible(true);
    $attributes = $reflection->getValue($provider);
    
    expect($attributes['driver_config'])->toBe(json_encode([]));
});

test('setDriverConfigAttribute handles complex config', function () {
    $provider = new ExchangeRateProvider();
    $config = [
        'type' => 'PREMIUM',
        'url' => 'https://api.example.com',
        'timeout' => 30,
        'retry' => true
    ];
    
    $provider->setDriverConfigAttribute($config);
    
    $reflection = new ReflectionProperty($provider, 'attributes');
    $reflection->setAccessible(true);
    $attributes = $reflection->getValue($provider);
    
    expect($attributes['driver_config'])->toBe(json_encode($config));
});

// ========== GETCURRENCYCONVERTERURL TESTS ==========

test('getCurrencyConverterUrl method exists', function () {
    expect(method_exists(ExchangeRateProvider::class, 'getCurrencyConverterUrl'))->toBeTrue();
});

test('getCurrencyConverterUrl is static', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    $method = $reflection->getMethod('getCurrencyConverterUrl');
    
    expect($method->isStatic())->toBeTrue();
});

test('getCurrencyConverterUrl returns PREMIUM url', function () {
    $data = ['type' => 'PREMIUM'];
    $url = ExchangeRateProvider::getCurrencyConverterUrl($data);
    
    expect($url)->toBe('https://api.currconv.com');
});

test('getCurrencyConverterUrl returns PREPAID url', function () {
    $data = ['type' => 'PREPAID'];
    $url = ExchangeRateProvider::getCurrencyConverterUrl($data);
    
    expect($url)->toBe('https://prepaid.currconv.com');
});

test('getCurrencyConverterUrl returns FREE url', function () {
    $data = ['type' => 'FREE'];
    $url = ExchangeRateProvider::getCurrencyConverterUrl($data);
    
    expect($url)->toBe('https://free.currconv.com');
});

test('getCurrencyConverterUrl returns DEDICATED url', function () {
    $customUrl = 'https://mycustom.currconv.com';
    $data = ['type' => 'DEDICATED', 'url' => $customUrl];
    $url = ExchangeRateProvider::getCurrencyConverterUrl($data);
    
    expect($url)->toBe($customUrl);
});

test('getCurrencyConverterUrl handles different DEDICATED urls', function () {
    $customUrl = 'https://another-custom.example.com';
    $data = ['type' => 'DEDICATED', 'url' => $customUrl];
    $url = ExchangeRateProvider::getCurrencyConverterUrl($data);
    
    expect($url)->toBe($customUrl);
});

test('getCurrencyConverterUrl returns null for unknown type', function () {
    $data = ['type' => 'UNKNOWN'];
    $url = ExchangeRateProvider::getCurrencyConverterUrl($data);
    
    expect($url)->toBeNull();
});

test('getCurrencyConverterUrl returns null for invalid type', function () {
    $data = ['type' => 'INVALID'];
    $url = ExchangeRateProvider::getCurrencyConverterUrl($data);
    
    expect($url)->toBeNull();
});

// ========== METHOD EXISTENCE TESTS ==========

test('ExchangeRateProvider has all required methods', function () {
    $provider = new ExchangeRateProvider();
    
    expect(method_exists($provider, 'company'))->toBeTrue()
        ->and(method_exists($provider, 'setCurrenciesAttribute'))->toBeTrue()
        ->and(method_exists($provider, 'setDriverConfigAttribute'))->toBeTrue()
        ->and(method_exists($provider, 'scopeWhereCompany'))->toBeTrue()
        ->and(method_exists($provider, 'updateFromRequest'))->toBeTrue();
});

test('ExchangeRateProvider has static methods', function () {
    expect(method_exists(ExchangeRateProvider::class, 'createFromRequest'))->toBeTrue()
        ->and(method_exists(ExchangeRateProvider::class, 'checkActiveCurrencies'))->toBeTrue()
        ->and(method_exists(ExchangeRateProvider::class, 'checkExchangeRateProviderStatus'))->toBeTrue()
        ->and(method_exists(ExchangeRateProvider::class, 'getCurrencyConverterUrl'))->toBeTrue();
});

// ========== TRAITS TESTS ==========

test('ExchangeRateProvider uses HasFactory trait', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
});

// ========== INSTANCE TESTS ==========

test('multiple ExchangeRateProvider instances can be created', function () {
    $provider1 = new ExchangeRateProvider();
    $provider2 = new ExchangeRateProvider();
    
    expect($provider1)->toBeInstanceOf(ExchangeRateProvider::class)
        ->and($provider2)->toBeInstanceOf(ExchangeRateProvider::class)
        ->and($provider1)->not->toBe($provider2);
});

test('ExchangeRateProvider can be cloned', function () {
    $provider = new ExchangeRateProvider(['driver' => 'test']);
    $clone = clone $provider;
    
    expect($clone)->toBeInstanceOf(ExchangeRateProvider::class)
        ->and($clone)->not->toBe($provider);
});

test('ExchangeRateProvider can be used in type hints', function () {
    $testFunction = function (ExchangeRateProvider $provider) {
        return $provider;
    };
    
    $provider = new ExchangeRateProvider();
    $result = $testFunction($provider);
    
    expect($result)->toBe($provider);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('ExchangeRateProvider is not final', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('ExchangeRateProvider is not an interface', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('ExchangeRateProvider is not a trait', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('ExchangeRateProvider class is loaded', function () {
    expect(class_exists(ExchangeRateProvider::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('ExchangeRateProvider uses required classes', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Http\Requests\ExchangeRateProviderRequest')
        ->and($fileContent)->toContain('use Illuminate\Database\Eloquent\Factories\HasFactory')
        ->and($fileContent)->toContain('use Illuminate\Database\Eloquent\Model')
        ->and($fileContent)->toContain('use Illuminate\Support\Facades\Http');
});

// ========== FILE STRUCTURE TESTS ==========

test('ExchangeRateProvider file has expected structure', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class ExchangeRateProvider extends Model')
        ->and($fileContent)->toContain('protected $guarded')
        ->and($fileContent)->toContain('protected $casts');
});

test('ExchangeRateProvider has reasonable line count', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeGreaterThan(100);
});

// ========== ATTRIBUTE TESTS ==========

test('can set and get driver attribute', function () {
    $provider = new ExchangeRateProvider();
    $provider->driver = 'currency_freak';
    
    expect($provider->driver)->toBe('currency_freak');
});

test('can set and get active attribute', function () {
    $provider = new ExchangeRateProvider();
    $provider->active = true;
    
    expect($provider->active)->toBeTrue();
});

test('active attribute casts to boolean', function () {
    $provider = new ExchangeRateProvider();
    $provider->active = 1;
    
    expect($provider->active)->toBeTrue()
        ->and($provider->active)->toBeBool();
});

test('can set and get company_id attribute', function () {
    $provider = new ExchangeRateProvider();
    $provider->company_id = 5;
    
    expect($provider->company_id)->toBe(5);
});

// ========== METHOD CHARACTERISTICS TESTS ==========

test('setCurrenciesAttribute is public', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    $method = $reflection->getMethod('setCurrenciesAttribute');
    
    expect($method->isPublic())->toBeTrue();
});

test('setDriverConfigAttribute is public', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    $method = $reflection->getMethod('setDriverConfigAttribute');
    
    expect($method->isPublic())->toBeTrue();
});

test('getCurrencyConverterUrl is public', function () {
    $reflection = new ReflectionClass(ExchangeRateProvider::class);
    $method = $reflection->getMethod('getCurrencyConverterUrl');
    
    expect($method->isPublic())->toBeTrue();
});

// ========== DATA INTEGRITY TESTS ==========

test('currencies attribute preserves data through setter', function () {
    $provider = new ExchangeRateProvider();
    $currencies = ['USD', 'EUR', 'GBP', 'JPY'];
    
    $provider->currencies = $currencies;
    
    expect($provider->currencies)->toBe($currencies);
});

test('driver_config attribute preserves data through setter', function () {
    $provider = new ExchangeRateProvider();
    $config = ['type' => 'PREMIUM', 'timeout' => 30];
    
    $provider->driver_config = $config;
    
    expect($provider->driver_config)->toBe($config);
});

test('different instances have independent data', function () {
    $provider1 = new ExchangeRateProvider(['driver' => 'currency_freak']);
    $provider2 = new ExchangeRateProvider(['driver' => 'currency_layer']);
    
    expect($provider1->driver)->not->toBe($provider2->driver)
        ->and($provider1->driver)->toBe('currency_freak')
        ->and($provider2->driver)->toBe('currency_layer');
});

// ========== GETCURRENCYCONVERTERURL COMPREHENSIVE TESTS ==========

test('getCurrencyConverterUrl handles all valid types', function () {
    $types = [
        ['type' => 'PREMIUM', 'expected' => 'https://api.currconv.com'],
        ['type' => 'PREPAID', 'expected' => 'https://prepaid.currconv.com'],
        ['type' => 'FREE', 'expected' => 'https://free.currconv.com'],
    ];
    
    foreach ($types as $test) {
        $url = ExchangeRateProvider::getCurrencyConverterUrl(['type' => $test['type']]);
        expect($url)->toBe($test['expected']);
    }
});

test('getCurrencyConverterUrl DEDICATED type requires url', function () {
    $customUrl = 'https://my-dedicated-server.com';
    $data = ['type' => 'DEDICATED', 'url' => $customUrl];
    
    $url = ExchangeRateProvider::getCurrencyConverterUrl($data);
    
    expect($url)->toBe($customUrl);
});

// ========== MODEL FEATURES TESTS ==========

test('ExchangeRateProvider inherits Model methods', function () {
    $provider = new ExchangeRateProvider();
    
    expect(method_exists($provider, 'save'))->toBeTrue()
        ->and(method_exists($provider, 'fill'))->toBeTrue()
        ->and(method_exists($provider, 'toArray'))->toBeTrue();
});

test('ExchangeRateProvider can use Model features', function () {
    $provider = new ExchangeRateProvider();
    
    expect(is_callable([$provider, 'fill']))->toBeTrue()
        ->and(is_callable([$provider, 'toArray']))->toBeTrue();
});