<?php

use Crater\Models\User;
use Crater\Models\ExchangeRateProvider;
use Silber\Bouncer\BouncerFacade;
use Crater\Policies\ExchangeRateProviderPolicy;

beforeEach(function () {
    // Ensure BouncerFacade is mocked clean for each test
    Mockery::close();
});

test('viewAny returns true when user has permission', function () {
    $user = Mockery::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-exchange-rate-provider', ExchangeRateProvider::class)
        ->andReturn(true);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->viewAny($user);

    expect($result)->toBeTrue();
});

test('viewAny returns false when user does not have permission', function () {
    $user = Mockery::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-exchange-rate-provider', ExchangeRateProvider::class)
        ->andReturn(false);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->viewAny($user);

    expect($result)->toBeFalse();
});

test('view returns true when user has permission and belongs to company', function () {
    $user = Mockery::mock(User::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $exchangeRateProvider->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-exchange-rate-provider', $exchangeRateProvider)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($exchangeRateProvider->company_id)
        ->andReturn(true);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->view($user, $exchangeRateProvider);

    expect($result)->toBeTrue();
});

test('view returns false when user has permission but does not belong to company', function () {
    $user = Mockery::mock(User::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $exchangeRateProvider->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-exchange-rate-provider', $exchangeRateProvider)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($exchangeRateProvider->company_id)
        ->andReturn(false);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->view($user, $exchangeRateProvider);

    expect($result)->toBeFalse();
});

test('view returns false when user does not have permission, regardless of company', function () {
    $user = Mockery::mock(User::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $exchangeRateProvider->company_id = 1;

    // BouncerFacade::can will return false, the second condition (hasCompany) should not be called
    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-exchange-rate-provider', $exchangeRateProvider)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany'); // Ensure hasCompany is NOT called due to short-circuiting

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->view($user, $exchangeRateProvider);

    expect($result)->toBeFalse();
});

test('create returns true when user has permission', function () {
    $user = Mockery::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('create-exchange-rate-provider', ExchangeRateProvider::class)
        ->andReturn(true);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->create($user);

    expect($result)->toBeTrue();
});

test('create returns false when user does not have permission', function () {
    $user = Mockery::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('create-exchange-rate-provider', ExchangeRateProvider::class)
        ->andReturn(false);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->create($user);

    expect($result)->toBeFalse();
});

test('update returns true when user has permission and belongs to company', function () {
    $user = Mockery::mock(User::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $exchangeRateProvider->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-exchange-rate-provider', $exchangeRateProvider)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($exchangeRateProvider->company_id)
        ->andReturn(true);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->update($user, $exchangeRateProvider);

    expect($result)->toBeTrue();
});

test('update returns false when user has permission but does not belong to company', function () {
    $user = Mockery::mock(User::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $exchangeRateProvider->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-exchange-rate-provider', $exchangeRateProvider)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($exchangeRateProvider->company_id)
        ->andReturn(false);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->update($user, $exchangeRateProvider);

    expect($result)->toBeFalse();
});

test('update returns false when user does not have permission, regardless of company', function () {
    $user = Mockery::mock(User::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $exchangeRateProvider->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-exchange-rate-provider', $exchangeRateProvider)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany');

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->update($user, $exchangeRateProvider);

    expect($result)->toBeFalse();
});

test('delete returns true when user has permission and belongs to company', function () {
    $user = Mockery::mock(User::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $exchangeRateProvider->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-exchange-rate-provider', $exchangeRateProvider)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($exchangeRateProvider->company_id)
        ->andReturn(true);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->delete($user, $exchangeRateProvider);

    expect($result)->toBeTrue();
});

test('delete returns false when user has permission but does not belong to company', function () {
    $user = Mockery::mock(User::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $exchangeRateProvider->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-exchange-rate-provider', $exchangeRateProvider)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($exchangeRateProvider->company_id)
        ->andReturn(false);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->delete($user, $exchangeRateProvider);

    expect($result)->toBeFalse();
});

test('delete returns false when user does not have permission, regardless of company', function () {
    $user = Mockery::mock(User::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);
    $exchangeRateProvider->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-exchange-rate-provider', $exchangeRateProvider)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany');

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->delete($user, $exchangeRateProvider);

    expect($result)->toBeFalse();
});

test('restore method returns null as it is not implemented', function () {
    $user = Mockery::mock(User::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->restore($user, $exchangeRateProvider);

    expect($result)->toBeNull();
});

test('forceDelete method returns null as it is not implemented', function () {
    $user = Mockery::mock(User::class);
    $exchangeRateProvider = Mockery::mock(ExchangeRateProvider::class);

    $policy = new ExchangeRateProviderPolicy();
    $result = $policy->forceDelete($user, $exchangeRateProvider);

    expect($result)->toBeNull();
});




afterEach(function () {
    Mockery::close();
});
