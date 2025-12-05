<?php

use Crater\Http\Middleware\VerifyCsrfToken;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Encryption\Encrypter;

function createMiddlewareInstance(): VerifyCsrfToken
    {
        $app = Mockery::mock(Application::class);
        $encrypter = Mockery::mock(Encrypter::class);

        return new VerifyCsrfToken($app, $encrypter);
    }

    test('it correctly sets the $addHttpCookie property to true', function () {
        $middleware = createMiddlewareInstance();

        // Use reflection to access the protected property
        $reflection = new ReflectionClass($middleware);
        $addHttpCookieProperty = $reflection->getProperty('addHttpCookie');
        $addHttpCookieProperty->setAccessible(true); // Make the protected property accessible for testing

        // Assert that the value of $addHttpCookie is true as defined in the middleware
        expect($addHttpCookieProperty->getValue($middleware))->toBeTrue();
    });

    test('it correctly sets the $except property to specific URIs', function () {
        $middleware = createMiddlewareInstance();

        // Use reflection to access the protected property
        $reflection = new ReflectionClass($middleware);
        $exceptProperty = $reflection->getProperty('except');
        $exceptProperty->setAccessible(true); // Make the protected property accessible for testing

        // Assert that the value of $except is the array ['login'] as defined in the middleware
        expect($exceptProperty->getValue($middleware))->toEqual(['login']);
    });

    // Ensure Mockery is closed after each test to prevent memory leaks and unexpected behavior




afterEach(function () {
    Mockery::close();
});