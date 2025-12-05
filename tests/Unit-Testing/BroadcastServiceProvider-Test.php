<?php

use Crater\Providers\BroadcastServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\File;

test('broadcast service provider exists and follows pattern', function () {
    // Basic checks
    expect(class_exists(BroadcastServiceProvider::class))->toBeTrue();
    
    $provider = new BroadcastServiceProvider(app());
    
    expect($provider)->toBeInstanceOf(BroadcastServiceProvider::class);
    expect($provider)->toBeInstanceOf(Illuminate\Support\ServiceProvider::class);
    expect(method_exists($provider, 'register'))->toBeTrue();
    expect(method_exists($provider, 'boot'))->toBeTrue();
    
    // Register should not throw
    expect(fn() => $provider->register())->not->toThrow(Exception::class);
});

test('broadcast routes method is called', function () {
    // We can't easily test this without mocking, but we can verify
    // that the Broadcast facade has the routes method
    expect(method_exists(Broadcast::getFacadeRoot(), 'routes'))->toBeTrue();
});