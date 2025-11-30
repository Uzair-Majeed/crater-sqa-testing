<?php

use Crater\Http\Controllers\V1\Admin\General\DateFormatsController;
use Crater\Space\DateFormatter;
use Illuminate\Http\Request;
uses(\Mockery::class);

// Ensure Mockery is closed after each test to verify expectations and clean up.
afterEach(fn () => Mockery::close());

test('it returns a list of date formats from DateFormatter::get_list', function () {
    // Arrange
    $expectedDateFormats = [
        ['key' => 'Y-m-d', 'label' => 'YYYY-MM-DD'],
        ['key' => 'd/m/Y', 'label' => 'DD/MM/YYYY'],
        ['key' => 'm/d/Y', 'label' => 'MM/DD/YYYY'],
    ];

    // Mock the static method DateFormatter::get_list() using an alias.
    // This allows intercepting static calls to the DateFormatter class.
    Mockery::mock('alias:' . DateFormatter::class)
        ->shouldReceive('get_list')
        ->once() // Assert that get_list is called exactly once
        ->andReturn($expectedDateFormats);

    // The Request object is part of the __invoke signature but not used internally.
    // A basic instance is sufficient.
    $request = Request::create('/');
    $controller = new DateFormatsController();

    // Act
    $response = $controller($request);

    // Assert
    $response->assertOk(); // Asserts HTTP 200 OK status
    $response->assertJson([
        'date_formats' => $expectedDateFormats,
    ]);
});

test('it returns an empty list when DateFormatter::get_list returns an empty array', function () {
    // Arrange
    $emptyDateFormats = [];

    // Mock the static method DateFormatter::get_list() to return an empty array.
    Mockery::mock('alias:' . DateFormatter::class)
        ->shouldReceive('get_list')
        ->once()
        ->andReturn($emptyDateFormats);

    $request = Request::create('/');
    $controller = new DateFormatsController();

    // Act
    $response = $controller($request);

    // Assert
    $response->assertOk(); // Still a successful response, just with empty data.
    $response->assertJson([
        'date_formats' => $emptyDateFormats,
    ]);
});
