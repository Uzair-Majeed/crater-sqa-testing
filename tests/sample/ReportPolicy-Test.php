<?php

use Crater\Models\Company;
use Crater\Models\User;
use Crater\Policies\ReportPolicy;
use Mockery as m;
use Silber\Bouncer\BouncerFacade;

// The beforeEach Mockery::close() is redundant as it's called in afterEach.
// Mockery::close() should typically be called after a test to clean up.
// beforeEach(function () {
//     m::close();
// });

test('viewReport returns true when user can view financial reports and belongs to the company', function () {
    $user = m::mock(User::class);
    // Fix: Provide 'id' property directly when creating the mock
    // to avoid Mockery attempting to call `setAttribute` on the underlying Eloquent model.
    $company = m::mock(Company::class, ['id' => 1]);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-financial-reports')
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($company->id)
        ->andReturn(true);

    $policy = new ReportPolicy();

    $result = $policy->viewReport($user, $company);

    expect($result)->toBeTrue();
});

test('viewReport returns false when user cannot view financial reports (short-circuiting)', function () {
    $user = m::mock(User::class);
    // Fix: Provide 'id' property directly when creating the mock.
    $company = m::mock(Company::class, ['id' => 1]);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-financial-reports')
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany'); // hasCompany should not be called due to short-circuiting

    $policy = new ReportPolicy();

    $result = $policy->viewReport($user, $company);

    expect($result)->toBeFalse();
});

test('viewReport returns false when user can view financial reports but does not belong to the company', function () {
    $user = m::mock(User::class);
    // Fix: Provide 'id' property directly when creating the mock.
    $company = m::mock(Company::class, ['id' => 1]);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-financial-reports')
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($company->id)
        ->andReturn(false);

    $policy = new ReportPolicy();

    $result = $policy->viewReport($user, $company);

    expect($result)->toBeFalse();
});

afterEach(function () {
    // Ensures all Mockery expectations are satisfied and cleans up mocks.
    Mockery::close();
});