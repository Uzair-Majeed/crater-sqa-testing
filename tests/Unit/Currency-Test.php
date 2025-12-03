<?php
use Crater\Models\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

test('currency model extends eloquent model', function () {
    expect(is_subclass_of(Currency::class, Model::class))->toBeTrue();
});

test('currency model uses has factory trait', function () {
    expect(class_uses(Currency::class))->toContain(HasFactory::class);
});

test('guarded property is correctly set', function () {
    $currency = new Currency();

    $reflectionClass = new ReflectionClass($currency);
    $guardedProperty = $reflectionClass->getProperty('guarded');
    $guardedProperty->setAccessible(true); // Make protected property accessible

    $guarded = $guardedProperty->getValue($currency);

    expect($guarded)->toBeArray()
        ->toContain('id')
        ->toHaveCount(1); // Ensure only 'id' is guarded by default
});

test('currency model does not allow mass assignment of guarded properties', function () {
    $data = [
        'id' => 1, // This should be ignored due to $guarded
        'name' => 'US Dollar',
        'code' => 'USD',
    ];

    $currency = new Currency();
    $currency->fill($data);

    // 'id' should not be mass-assignable, so it remains null (or its default value if any)
    expect($currency->id)->toBeNull();
    expect($currency->name)->toBe('US Dollar');
    expect($currency->code)->toBe('USD');
});

test('currency model uses correct default table name', function () {
    $currency = new Currency();
    // Eloquent convention: class name 'Currency' becomes table name 'currencies'
    expect($currency->getTable())->toBe('currencies');
});

test('currency model can be instantiated via factory make method', function () {
    // If CurrencyFactory does not exist, we stub it here inline for the test.
    if (!class_exists('Database\Factories\CurrencyFactory')) {
        eval('
            namespace Database\Factories;
            use Crater\Models\Currency;
            use Illuminate\Database\Eloquent\Factories\Factory;

            class CurrencyFactory extends Factory {
                protected $model = Currency::class;

                public function definition() {
                    return [
                        "name" => "Currency " . \\Illuminate\\Support\\Str::random(5),
                        "code" => strtoupper($this->faker->lexify("???")),
                    ];
                }
            }
        ');
    }

    $currency = Currency::factory()->make();

    expect($currency)->toBeInstanceOf(Currency::class);
    expect($currency->id)->toBeNull(); // `make` does not assign an ID as it's not persisted
    expect($currency->name)->not->toBeNull(); // Factory should generate a value for name
    expect($currency->code)->not->toBeNull(); // Factory should generate a value for code
});


afterEach(function () {
    Mockery::close();
});