<?php

use Crater\Http\Controllers\V1\Admin\Role\AbilitiesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;

test('it returns a list of abilities from the config file', function () {
    // Arrange
    $mockAbilities = [
        'users.view' => 'View Users',
        'users.create' => 'Create Users',
        'roles.manage' => 'Manage Roles',
    ];

    Config::shouldReceive('get')
        ->once()
        ->with('abilities.abilities')
        ->andReturn($mockAbilities);

    $controller = new AbilitiesController();
    $request = Request::create('/test-abilities', 'GET');

    // Act
    $jsonResponse = $controller($request);
    $response = new TestResponse($jsonResponse); // Wrap for assertion helpers

    // Assert
    $response->assertOk();
    $response->assertJson(['abilities' => $mockAbilities]);
    expect($jsonResponse)->toBeInstanceOf(JsonResponse::class);
});

test('it returns an empty array when no abilities are configured', function () {
    // Arrange
    Config::shouldReceive('get')
        ->once()
        ->with('abilities.abilities')
        ->andReturn([]);

    $controller = new AbilitiesController();
    $request = Request::create('/test-abilities', 'GET');

    // Act
    $jsonResponse = $controller($request);
    $response = new TestResponse($jsonResponse);

    // Assert
    $response->assertOk();
    $response->assertJson(['abilities' => []]);
    expect($jsonResponse)->toBeInstanceOf(JsonResponse::class);
});

test('it handles null return from config for abilities gracefully', function () {
    // Arrange
    Config::shouldReceive('get')
        ->once()
        ->with('abilities.abilities')
        ->andReturn(null);

    $controller = new AbilitiesController();
    $request = Request::create('/test-abilities', 'GET');

    // Act
    $jsonResponse = $controller($request);
    $response = new TestResponse($jsonResponse);

    // Assert
    $response->assertOk();
    $response->assertJson(['abilities' => null]);
    expect($jsonResponse)->toBeInstanceOf(JsonResponse::class);
});

test('the request object does not influence the returned abilities', function () {
    // Arrange
    $mockAbilities = ['test.ability' => 'Test Ability'];
    Config::shouldReceive('get')
        ->twice() // Called for both requests
        ->with('abilities.abilities')
        ->andReturn($mockAbilities);

    $controller = new AbilitiesController();

    // Create different requests
    $request1 = Request::create('/api/v1/admin/abilities', 'GET', ['param' => 'value']);
    $request2 = Request::create('/another/path', 'POST', ['data' => 'different'], [], [], ['HTTP_ACCEPT' => 'application/xml']);

    // Act
    $jsonResponse1 = $controller($request1);
    $response1 = new TestResponse($jsonResponse1);

    $jsonResponse2 = $controller($request2);
    $response2 = new TestResponse($jsonResponse2);

    // Assert
    $response1->assertOk();
    $response1->assertJson(['abilities' => $mockAbilities]);

    $response2->assertOk();
    $response2->assertJson(['abilities' => $mockAbilities]);

    // Ensure the abilities are identical regardless of the request details
    expect($jsonResponse1->getData(true)['abilities'])
        ->toBe($jsonResponse2->getData(true)['abilities'])
        ->toBe($mockAbilities);
});
