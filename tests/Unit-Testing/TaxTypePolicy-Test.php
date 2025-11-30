<?php

use Crater\Models\TaxType;
use Crater\Models\User;
use Crater\Policies\TaxTypePolicy;
use Silber\Bouncer\BouncerFacade;
use function Pest\Laravel\mock;

beforeEach(function () {
    // Mock BouncerFacade for all tests in this file
    Mockery::mock('alias:' . BouncerFacade::class);
});

test('viewAny returns true if user can view any tax types', function () {
    $user = mock(User::class);
    $policy = new TaxTypePolicy();

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-tax-type', TaxType::class)
        ->andReturn(true);

    $result = $policy->viewAny($user);

    expect($result)->toBeTrue();
});

test('viewAny returns false if user cannot view any tax types', function () {
    $user = mock(User::class);
    $policy = new TaxTypePolicy();

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-tax-type', TaxType::class)
        ->andReturn(false);

    $result = $policy->viewAny($user);

    expect($result)->toBeFalse();
});

test('view returns true if user can view specific tax type and has company access', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-tax-type', $taxType)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($taxType->company_id)
        ->andReturn(true);

    $result = $policy->view($user, $taxType);

    expect($result)->toBeTrue();
});

test('view returns false if user cannot view specific tax type', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-tax-type', $taxType)
        ->andReturn(false); // Bouncer denies

    $user->shouldNotReceive('hasCompany'); // Should not be called if bouncer denies

    $result = $policy->view($user, $taxType);

    expect($result)->toBeFalse();
});

test('view returns false if user has no company access to specific tax type', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-tax-type', $taxType)
        ->andReturn(true); // Bouncer allows

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($taxType->company_id)
        ->andReturn(false); // User lacks company access

    $result = $policy->view($user, $taxType);

    expect($result)->toBeFalse();
});

test('view returns false if user cannot view specific tax type and has no company access', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-tax-type', $taxType)
        ->andReturn(false); // Bouncer denies

    $user->shouldNotReceive('hasCompany'); // Should not be called if bouncer denies

    $result = $policy->view($user, $taxType);

    expect($result)->toBeFalse();
});

test('create returns true if user can create tax types', function () {
    $user = mock(User::class);
    $policy = new TaxTypePolicy();

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('create-tax-type', TaxType::class)
        ->andReturn(true);

    $result = $policy->create($user);

    expect($result)->toBeTrue();
});

test('create returns false if user cannot create tax types', function () {
    $user = mock(User::class);
    $policy = new TaxTypePolicy();

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('create-tax-type', TaxType::class)
        ->andReturn(false);

    $result = $policy->create($user);

    expect($result)->toBeFalse();
});

test('update returns true if user can edit specific tax type and has company access', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-tax-type', $taxType)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($taxType->company_id)
        ->andReturn(true);

    $result = $policy->update($user, $taxType);

    expect($result)->toBeTrue();
});

test('update returns false if user cannot edit specific tax type', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-tax-type', $taxType)
        ->andReturn(false); // Bouncer denies

    $user->shouldNotReceive('hasCompany');

    $result = $policy->update($user, $taxType);

    expect($result)->toBeFalse();
});

test('update returns false if user has no company access to specific tax type for update', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-tax-type', $taxType)
        ->andReturn(true); // Bouncer allows

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($taxType->company_id)
        ->andReturn(false); // User lacks company access

    $result = $policy->update($user, $taxType);

    expect($result)->toBeFalse();
});

test('delete returns true if user can delete specific tax type and has company access', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-tax-type', $taxType)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($taxType->company_id)
        ->andReturn(true);

    $result = $policy->delete($user, $taxType);

    expect($result)->toBeTrue();
});

test('delete returns false if user cannot delete specific tax type', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-tax-type', $taxType)
        ->andReturn(false); // Bouncer denies

    $user->shouldNotReceive('hasCompany');

    $result = $policy->delete($user, $taxType);

    expect($result)->toBeFalse();
});

test('delete returns false if user has no company access to specific tax type for delete', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-tax-type', $taxType)
        ->andReturn(true); // Bouncer allows

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($taxType->company_id)
        ->andReturn(false); // User lacks company access

    $result = $policy->delete($user, $taxType);

    expect($result)->toBeFalse();
});

test('restore returns true if user can restore specific tax type and has company access', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-tax-type', $taxType) // Note: restore uses 'delete-tax-type' permission
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($taxType->company_id)
        ->andReturn(true);

    $result = $policy->restore($user, $taxType);

    expect($result)->toBeTrue();
});

test('restore returns false if user cannot restore specific tax type', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-tax-type', $taxType)
        ->andReturn(false); // Bouncer denies

    $user->shouldNotReceive('hasCompany');

    $result = $policy->restore($user, $taxType);

    expect($result)->toBeFalse();
});

test('restore returns false if user has no company access to specific tax type for restore', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-tax-type', $taxType)
        ->andReturn(true); // Bouncer allows

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($taxType->company_id)
        ->andReturn(false); // User lacks company access

    $result = $policy->restore($user, $taxType);

    expect($result)->toBeFalse();
});

test('forceDelete returns true if user can force delete specific tax type and has company access', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-tax-type', $taxType) // Note: forceDelete uses 'delete-tax-type' permission
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($taxType->company_id)
        ->andReturn(true);

    $result = $policy->forceDelete($user, $taxType);

    expect($result)->toBeTrue();
});

test('forceDelete returns false if user cannot force delete specific tax type', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-tax-type', $taxType)
        ->andReturn(false); // Bouncer denies

    $user->shouldNotReceive('hasCompany');

    $result = $policy->forceDelete($user, $taxType);

    expect($result)->toBeFalse();
});

test('forceDelete returns false if user has no company access to specific tax type for force delete', function () {
    $user = mock(User::class);
    $taxType = mock(TaxType::class);
    $policy = new TaxTypePolicy();

    $taxType->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-tax-type', $taxType)
        ->andReturn(true); // Bouncer allows

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($taxType->company_id)
        ->andReturn(false); // User lacks company access

    $result = $policy->forceDelete($user, $taxType);

    expect($result)->toBeFalse();
});
