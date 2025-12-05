<?php

use Crater\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->provider = new AuthServiceProvider(app());
});

test('auth service provider works without errors', function () {
    // This is an integration test - we just verify the provider
    // can be instantiated and its methods run without throwing exceptions
    
    $this->provider->register();
    $this->provider->boot();
    
    expect(true)->toBeTrue(); // If we reach here, no exceptions were thrown
});

test('provider is correctly instantiated', function () {
    expect($this->provider)->toBeInstanceOf(AuthServiceProvider::class);
    expect($this->provider)->toBeInstanceOf(\Illuminate\Support\ServiceProvider::class);
});

test('boot method defines abilities', function () {
    // Call boot method
    $this->provider->boot();
    
    // Verify some abilities are defined (partial verification)
    expect(Gate::has('view dashboard'))->toBeTrue();
    expect(Gate::has('manage settings'))->toBeTrue();
    expect(Gate::has('create company'))->toBeTrue();
});

test('provider can be instantiated', function () {
    expect($this->provider)->toBeInstanceOf(AuthServiceProvider::class);
});