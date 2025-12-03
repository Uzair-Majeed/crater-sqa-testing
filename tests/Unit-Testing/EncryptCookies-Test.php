<?php

use Crater\Http\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\EncryptCookies as BaseEncryptCookies;


test('it correctly extends the base EncryptCookies middleware', function () {
    $middleware = new EncryptCookies();
    expect($middleware)->toBeInstanceOf(BaseEncryptCookies::class);
});

test('it sets the static serialize property to false', function () {
    $reflectionClass = new ReflectionClass(EncryptCookies::class);
    $serializeProperty = $reflectionClass->getStaticPropertyValue('serialize');

    expect($serializeProperty)->toBeFalse();
});

test('it sets the protected except property to an empty array', function () {
    $reflectionClass = new ReflectionClass(EncryptCookies::class);
    $exceptProperty = $reflectionClass->getProperty('except');
    $exceptProperty->setAccessible(true);

    $middleware = new EncryptCookies();
    $exceptValue = $exceptProperty->getValue($middleware);

    expect($exceptValue)->toBeArray()->toBeEmpty();
});




afterEach(function () {
    Mockery::close();
});
