<?php

use Illuminate\Http\Request;
use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\ExchangeRateProviderResource;


    // Test case 1: Basic properties are transformed correctly, and the company relationship does not exist.
    test('it transforms basic properties and omits company when the relationship does not exist', function () {
        // Mock the underlying model that the resource wraps
        $mockModel = Mockery::mock();
        $mockModel->id = 1;
        $mockModel->key = 'test_key';
        $mockModel->driver = 'test_driver';
        $mockModel->currencies = ['USD', 'GBP'];
        $mockModel->driver_config = ['api_key' => 'config_value'];
        $mockModel->company_id = 101;
        $mockModel->active = true;

        // Mock the 'company' relationship method to return a relation that doesn't exist
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('exists')->andReturn(false)->once();
        $mockModel->shouldReceive('company')->andReturn($mockRelation)->once();

        $resource = new ExchangeRateProviderResource($mockModel);
        $request = Request::create('/'); // A dummy request instance

        $result = $resource->toArray($request);

        expect($result)->toMatchArray([
            'id' => 1,
            'key' => 'test_key',
            'driver' => 'test_driver',
            'currencies' => ['USD', 'GBP'],
            'driver_config' => ['api_key' => 'config_value'],
            'company_id' => 101,
            'active' => true,
        ])
        ->not->toHaveKey('company'); // Ensure 'company' key is absent
    });

    // Test case 2: The company relationship exists and is included as a CompanyResource.
    test('it includes company resource when the relationship exists', function () {
        // Mock the underlying Company model
        $mockCompanyModel = Mockery::mock('stdClass'); // Using stdClass for simplicity for a data bag
        $mockCompanyModel->id = 50;
        $mockCompanyModel->name = 'Mock Company Inc.';

        // Mock the underlying ExchangeRateProvider model
        $mockModel = Mockery::mock();
        $mockModel->id = 2;
        $mockModel->key = 'another_key';
        $mockModel->driver = 'another_driver';
        $mockModel->currencies = ['EUR'];
        $mockModel->driver_config = ['timeout' => 30];
        $mockModel->company_id = 102;
        $mockModel->active = false;
        $mockModel->company = $mockCompanyModel; // Set the 'company' property on the mock model

        // Mock the 'company' relationship method to return a relation that exists
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('exists')->andReturn(true)->once();
        $mockModel->shouldReceive('company')->andReturn($mockRelation)->once();

        $resource = new ExchangeRateProviderResource($mockModel);
        $request = Request::create('/');

        $result = $resource->toArray($request);

        expect($result)->toMatchArray([
            'id' => 2,
            'key' => 'another_key',
            'driver' => 'another_driver',
            'currencies' => ['EUR'],
            'driver_config' => ['timeout' => 30],
            'company_id' => 102,
            'active' => false,
        ]);

        // Assert that 'company' key exists
        expect($result)->toHaveKey('company');
        // Assert that the 'company' value is an instance of CompanyResource
        expect($result['company'])->toBeInstanceOf(CompanyResource::class);
        // Assert that the CompanyResource wraps the correct mock Company model
        expect($result['company']->resource)->toBe($mockCompanyModel);
    });

    // Test case 3: Edge case - all properties are null/empty where applicable, and company is absent.
    test('it handles null and empty properties with no company relationship', function () {
        $mockModel = Mockery::mock();
        $mockModel->id = null;
        $mockModel->key = null;
        $mockModel->driver = null;
        $mockModel->currencies = []; // Empty array
        $mockModel->driver_config = []; // Empty array
        $mockModel->company_id = null;
        $mockModel->active = false; // False boolean

        // Mock the 'company' relationship to indicate it does not exist
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('exists')->andReturn(false)->once();
        $mockModel->shouldReceive('company')->andReturn($mockRelation)->once();

        $resource = new ExchangeRateProviderResource($mockModel);
        $request = Request::create('/');

        $result = $resource->toArray($request);

        expect($result)->toMatchArray([
            'id' => null,
            'key' => null,
            'driver' => null,
            'currencies' => [],
            'driver_config' => [],
            'company_id' => null,
            'active' => false,
        ])
        ->not->toHaveKey('company'); // Ensure 'company' key is absent
    });

    // Test case 4: Verify that the `company()` method and `exists()` are called exactly once for the conditional logic.
    test('the company relationship and its existence check are called once for conditional inclusion', function () {
        $mockModel = Mockery::mock();
        // Set minimal properties to avoid potential undefined property errors
        $mockModel->id = 1;
        $mockModel->key = 'x';
        $mockModel->driver = 'y';
        $mockModel->currencies = [];
        $mockModel->driver_config = [];
        $mockModel->company_id = 1;
        $mockModel->active = true;

        // Ensure `company()` is called once, and its `exists()` method is called once
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('exists')->andReturn(false)->once(); // Expect exists() to be called once
        $mockModel->shouldReceive('company')->andReturn($mockRelation)->once(); // Expect company() to be called once

        $resource = new ExchangeRateProviderResource($mockModel);
        $request = Request::create('/');

        $resource->toArray($request);

        // Mockery::close() in afterEach will verify all expectations set above
    });



