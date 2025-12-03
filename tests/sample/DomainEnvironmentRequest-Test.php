<?php
use Crater\Http\Requests\DomainEnvironmentRequest;
use Illuminate\Foundation\Http\FormRequest;

test('domain environment request authorizes all users', function () {
    $request = new DomainEnvironmentRequest();
    expect($request->authorize())->toBeTrue();
});

test('domain environment request returns correct validation rules', function () {
    $request = new DomainEnvironmentRequest();
    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('app_domain')
        ->and($rules['app_domain'])->toBeArray()
        ->and($rules['app_domain'])->toContain('required')
        ->and($rules['app_domain'])->toHaveCount(1); // Ensure no other rules are present for 'app_domain'
});

afterEach(function () {
    Mockery::close();
});