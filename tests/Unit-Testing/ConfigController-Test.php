<?php

use Crater\Http\Controllers\V1\Admin\General\ConfigController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;

test('it returns a specific config value when the key exists', function () {
    // Arrange
    $key = 'app_name';
    $value = 'Crater App Test';
    Config::set("crater.$key", $value);

    $request = Request::create('/', 'GET', ['key' => $key]);
    $controller = new ConfigController();

    // Act
    /** @var \Illuminate\Http\JsonResponse $jsonResponse */
    $jsonResponse = $controller->__invoke($request);
    $response = new TestResponse($jsonResponse); // Wrap for testing assertions

    // Assert
    $response->assertOk(); // HTTP 200
    $response->assertJson([$key => $value]);

    // Clean up config specific to this test
    Config::offsetUnset("crater.$key");
});

test('it returns null when the config key does not exist', function () {
    // Arrange
    $key = 'non_existent_key';
    Config::offsetUnset("crater.$key"); // Ensure key is not set

    $request = Request::create('/', 'GET', ['key' => $key]);
    $controller = new ConfigController();

    // Act
    /** @var \Illuminate\Http\JsonResponse $jsonResponse */
    $jsonResponse = $controller->__invoke($request);
    $response = new TestResponse($jsonResponse);

    // Assert
    $response->assertOk(); // HTTP 200
    $response->assertJson([$key => null]);
});

test('it returns the entire "crater" config array when the request key is missing', function () {
    // Arrange
    $originalCraterConfig = Config::get('crater', []); // Save original config
    Config::set('crater', ['email' => 'test@example.com', 'currency' => 'USD']);

    $request = Request::create('/', 'GET'); // Missing 'key' parameter
    $controller = new ConfigController();

    // Act
    /** @var \Illuminate\Http\JsonResponse $jsonResponse */
    $jsonResponse = $controller->__invoke($request);
    $response = new TestResponse($jsonResponse);

    // Assert
    $response->assertOk(); // HTTP 200
    // When $request->key is null, the array key becomes null, which JSON encodes to an empty string.
    $response->assertJson([
        '' => [ // Empty string key for the null key from $request->key
            'email' => 'test@example.com',
            'currency' => 'USD',
        ],
    ]);

    // Clean up config
    Config::set('crater', $originalCraterConfig); // Restore original config
});

test('it returns the entire "crater" config array when the request key is an empty string', function () {
    // Arrange
    $originalCraterConfig = Config::get('crater', []); // Save original config
    Config::set('crater', ['language' => 'en', 'timezone' => 'UTC']);

    $request = Request::create('/', 'GET', ['key' => '']); // Empty string 'key' parameter
    $controller = new ConfigController();

    // Act
    /** @var \Illuminate\Http\JsonResponse $jsonResponse */
    $jsonResponse = $controller->__invoke($request);
    $response = new TestResponse($jsonResponse);

    // Assert
    $response->assertOk(); // HTTP 200
    // When $request->key is an empty string, it's used directly as the array key.
    $response->assertJson([
        '' => [ // Empty string key
            'language' => 'en',
            'timezone' => 'UTC',
        ],
    ]);

    // Clean up config
    Config::set('crater', $originalCraterConfig); // Restore original config
});

test('it handles nested config keys correctly', function () {
    // Arrange
    $key = 'settings.general.app_title';
    $value = 'My Nested App Title';
    Config::set("crater.$key", $value);

    $request = Request::create('/', 'GET', ['key' => $key]);
    $controller = new ConfigController();

    // Act
    /** @var \Illuminate\Http\JsonResponse $jsonResponse */
    $jsonResponse = $controller->__invoke($request);
    $response = new TestResponse($jsonResponse);

    // Assert
    $response->assertOk();
    $response->assertJson([$key => $value]);

    Config::offsetUnset("crater.$key");
});

test('it returns a complex data structure config value', function () {
    // Arrange
    $key = 'features';
    $value = ['darkMode' => true, 'notifications' => ['email' => true, 'sms' => false]];
    Config::set("crater.$key", $value);

    $request = Request::create('/', 'GET', ['key' => $key]);
    $controller = new ConfigController();

    // Act
    /** @var \Illuminate\Http\JsonResponse $jsonResponse */
    $jsonResponse = $controller->__invoke($request);
    $response = new TestResponse($jsonResponse);

    // Assert
    $response->assertOk();
    $response->assertJson([$key => $value]);

    Config::offsetUnset("crater.$key");
});
