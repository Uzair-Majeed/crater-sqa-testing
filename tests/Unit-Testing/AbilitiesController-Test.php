<?php

namespace Tests\Unit;

use Crater\Http\Controllers\V1\Admin\Role\AbilitiesController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request; // <-- FIX: Imports the correct Request class
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse; // <-- FIX: Imports the TestResponse helper
use Mockery;

test('it returns a list of abilities from the config file', function () {
    // Arrange
    $mockAbilities = [
        'users.view' => 'View Users',
        'users.create' => 'Create Users',
        'roles.manage' => 'Manage Roles',
    ];

    Config::set('abilities.abilities', $mockAbilities);
    $controller = new AbilitiesController();
    $request = Request::create('/test-abilities', 'GET'); // No longer throws error

    // Act
    $jsonResponse = $controller($request);
    $response = new TestResponse($jsonResponse); // No longer throws error

    // Assert
    $response->assertOk();
    $response->assertJson(['abilities' => $mockAbilities]);
    expect($jsonResponse)->toBeInstanceOf(JsonResponse::class);
});

test('it returns an empty array when no abilities are configured', function () {
    // Arrange
    Config::set('abilities.abilities', []);
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


test('the request object does not influence the returned abilities', function () {
    // Arrange
    $mockAbilities = ['test.ability' => 'Test Ability'];
    Config::set('abilities.abilities', $mockAbilities);

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

afterEach(function () {
    // Cleaning up Mockery is a good habit even if not used in the test.
    Mockery::close();
});