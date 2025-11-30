<?php

use Crater\Policies\DashboardPolicy;
use Crater\Models\User;
use Crater\Models\Company;
use Mockery as m;
use Silber\Bouncer\BouncerFacade; // Import the facade directly for type hinting in mocks

// Setup for mocking BouncerFacade globally within this test file
beforeEach(function () {
    // We need to mock BouncerFacade as an alias because it's a static facade
    m::mock('alias:' . BouncerFacade::class);
});

afterEach(function () {
    m::close(); // Clean up Mockery expectations
});

test('view method allows access when user can dashboard and belongs to the company', function () {
    // Arrange
    $policy = new DashboardPolicy();

    $user = m::mock(User::class);
    $company = m::mock(Company::class);
    $company->id = 1; // Set an ID for the company

    // Expect BouncerFacade::can('dashboard') to be called and return true
    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('dashboard')
        ->andReturn(true);

    // Expect User::hasCompany($company->id) to be called and return true
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($company->id)
        ->andReturn(true);

    // Act
    $result = $policy->view($user, $company);

    // Assert
    expect($result)->toBeTrue();
});

test('view method denies access when user cannot dashboard, even if belonging to the company', function () {
    // Arrange
    $policy = new DashboardPolicy();

    $user = m::mock(User::class);
    $company = m::mock(Company::class);
    $company->id = 1;

    // Expect BouncerFacade::can('dashboard') to be called and return false
    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('dashboard')
        ->andReturn(false);

    // Due to short-circuiting (if BouncerFacade::can is false, the second part of AND is not evaluated),
    // User::hasCompany should not be called.
    $user->shouldNotReceive('hasCompany');

    // Act
    $result = $policy->view($user, $company);

    // Assert
    expect($result)->toBeFalse();
});

test('view method denies access when user does not belong to the company, even if they can dashboard', function () {
    // Arrange
    $policy = new DashboardPolicy();

    $user = m::mock(User::class);
    $company = m::mock(Company::class);
    $company->id = 1;

    // Expect BouncerFacade::can('dashboard') to be called and return true
    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('dashboard')
        ->andReturn(true);

    // Expect User::hasCompany($company->id) to be called and return false
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($company->id)
        ->andReturn(false);

    // Act
    $result = $policy->view($user, $company);

    // Assert
    expect($result)->toBeFalse();
});

test('view method denies access when user cannot dashboard and does not belong to the company', function () {
    // Arrange
    $policy = new DashboardPolicy();

    $user = m::mock(User::class);
    $company = m::mock(Company::class);
    $company->id = 1;

    // Expect BouncerFacade::can('dashboard') to be called and return false
    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('dashboard')
        ->andReturn(false);

    // Due to short-circuiting, User::hasCompany should not be called
    $user->shouldNotReceive('hasCompany');

    // Act
    $result = $policy->view($user, $company);

    // Assert
    expect($result)->toBeFalse();
});
