<?php

use Crater\Http\Requests\DatabaseEnvironmentRequest;

test('authorize method always returns true', function () {
    $request = new DatabaseEnvironmentRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns sqlite specific rules when database connection is sqlite', function () {
    $mockRequest = Mockery::mock(DatabaseEnvironmentRequest::class)->makePartial();
    $mockRequest->shouldReceive('get')
                ->with('database_connection')
                ->andReturn('sqlite')
                ->once();

    $expectedRules = [
        'app_url' => ['required', 'url'],
        'database_connection' => ['required', 'string'],
        'database_name' => ['required', 'string'],
    ];

    expect($mockRequest->rules())->toEqual($expectedRules);
});

test('rules method returns default rules when database connection is mysql', function () {
    $mockRequest = Mockery::mock(DatabaseEnvironmentRequest::class)->makePartial();
    $mockRequest->shouldReceive('get')
                ->with('database_connection')
                ->andReturn('mysql')
                ->once();

    $expectedRules = [
        'app_url' => ['required', 'url'],
        'database_connection' => ['required', 'string'],
        'database_hostname' => ['required', 'string'],
        'database_port' => ['required', 'numeric'],
        'database_name' => ['required', 'string'],
        'database_username' => ['required', 'string'],
    ];

    expect($mockRequest->rules())->toEqual($expectedRules);
});

test('rules method returns default rules when database connection is pgsql', function () {
    $mockRequest = Mockery::mock(DatabaseEnvironmentRequest::class)->makePartial();
    $mockRequest->shouldReceive('get')
                ->with('database_connection')
                ->andReturn('pgsql')
                ->once();

    $expectedRules = [
        'app_url' => ['required', 'url'],
        'database_connection' => ['required', 'string'],
        'database_hostname' => ['required', 'string'],
        'database_port' => ['required', 'numeric'],
        'database_name' => ['required', 'string'],
        'database_username' => ['required', 'string'],
    ];

    expect($mockRequest->rules())->toEqual($expectedRules);
});

test('rules method returns default rules when database connection is an unknown string', function () {
    $mockRequest = Mockery::mock(DatabaseEnvironmentRequest::class)->makePartial();
    $mockRequest->shouldReceive('get')
                ->with('database_connection')
                ->andReturn('unknown_db_type')
                ->once();

    $expectedRules = [
        'app_url' => ['required', 'url'],
        'database_connection' => ['required', 'string'],
        'database_hostname' => ['required', 'string'],
        'database_port' => ['required', 'numeric'],
        'database_name' => ['required', 'string'],
        'database_username' => ['required', 'string'],
    ];

    expect($mockRequest->rules())->toEqual($expectedRules);
});

test('rules method returns default rules when database connection is null', function () {
    $mockRequest = Mockery::mock(DatabaseEnvironmentRequest::class)->makePartial();
    $mockRequest->shouldReceive('get')
                ->with('database_connection')
                ->andReturn(null)
                ->once();

    $expectedRules = [
        'app_url' => ['required', 'url'],
        'database_connection' => ['required', 'string'],
        'database_hostname' => ['required', 'string'],
        'database_port' => ['required', 'numeric'],
        'database_name' => ['required', 'string'],
        'database_username' => ['required', 'string'],
    ];

    expect($mockRequest->rules())->toEqual($expectedRules);
});

test('rules method returns default rules when database connection is an empty string', function () {
    $mockRequest = Mockery::mock(DatabaseEnvironmentRequest::class)->makePartial();
    $mockRequest->shouldReceive('get')
                ->with('database_connection')
                ->andReturn('')
                ->once();

    $expectedRules = [
        'app_url' => ['required', 'url'],
        'database_connection' => ['required', 'string'],
        'database_hostname' => ['required', 'string'],
        'database_port' => ['required', 'numeric'],
        'database_name' => ['required', 'string'],
        'database_username' => ['required', 'string'],
    ];

    expect($mockRequest->rules())->toEqual($expectedRules);
});

afterEach(function () {
    Mockery::close();
});