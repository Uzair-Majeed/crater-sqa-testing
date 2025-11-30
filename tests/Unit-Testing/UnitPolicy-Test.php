<?php

use Crater\Models\Item;
use Crater\Models\Unit;
use Crater\Models\User;
use Crater\Policies\UnitPolicy;
use Silber\Bouncer\BouncerFacade;

afterEach(function () {
    Mockery::close();
});

test('viewAny returns true if user can view any models', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $policy = new UnitPolicy();

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $result = $policy->viewAny($user);

    expect($result)->toBeTrue();
});

test('viewAny returns false if user cannot view any models', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $policy = new UnitPolicy();

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(false);

    $result = $policy->viewAny($user);

    expect($result)->toBeFalse();
});

test('view returns true if user can view model and has company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();
    $companyId = 1;

    $unit->company_id = $companyId;

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $result = $policy->view($user, $unit);

    expect($result)->toBeTrue();
});

test('view returns false if user can view model but does not have company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();
    $companyId = 1;

    $unit->company_id = $companyId;

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $result = $policy->view($user, $unit);

    expect($result)->toBeFalse();
});

test('view returns false if user cannot view model regardless of company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany');

    $result = $policy->view($user, $unit);

    expect($result)->toBeFalse();
});

test('create returns true if user can create models', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $policy = new UnitPolicy();

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $result = $policy->create($user);

    expect($result)->toBeTrue();
});

test('create returns false if user cannot create models', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $policy = new UnitPolicy();

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(false);

    $result = $policy->create($user);

    expect($result)->toBeFalse();
});

test('update returns true if user can update model and has company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();
    $companyId = 1;

    $unit->company_id = $companyId;

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $result = $policy->update($user, $unit);

    expect($result)->toBeTrue();
});

test('update returns false if user can update model but does not have company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();
    $companyId = 1;

    $unit->company_id = $companyId;

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $result = $policy->update($user, $unit);

    expect($result)->toBeFalse();
});

test('update returns false if user cannot update model regardless of company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany');

    $result = $policy->update($user, $unit);

    expect($result)->toBeFalse();
});

test('delete returns true if user can delete model and has company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();
    $companyId = 1;

    $unit->company_id = $companyId;

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $result = $policy->delete($user, $unit);

    expect($result)->toBeTrue();
});

test('delete returns false if user can delete model but does not have company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();
    $companyId = 1;

    $unit->company_id = $companyId;

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $result = $policy->delete($user, $unit);

    expect($result)->toBeFalse();
});

test('delete returns false if user cannot delete model regardless of company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany');

    $result = $policy->delete($user, $unit);

    expect($result)->toBeFalse();
});

test('restore returns true if user can restore model and has company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();
    $companyId = 1;

    $unit->company_id = $companyId;

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $result = $policy->restore($user, $unit);

    expect($result)->toBeTrue();
});

test('restore returns false if user can restore model but does not have company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();
    $companyId = 1;

    $unit->company_id = $companyId;

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $result = $policy->restore($user, $unit);

    expect($result)->toBeFalse();
});

test('restore returns false if user cannot restore model regardless of company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany');

    $result = $policy->restore($user, $unit);

    expect($result)->toBeFalse();
});

test('forceDelete returns true if user can permanently delete model and has company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();
    $companyId = 1;

    $unit->company_id = $companyId;

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $result = $policy->forceDelete($user, $unit);

    expect($result)->toBeTrue();
});

test('forceDelete returns false if user can permanently delete model but does not have company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();
    $companyId = 1;

    $unit->company_id = $companyId;

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $result = $policy->forceDelete($user, $unit);

    expect($result)->toBeFalse();
});

test('forceDelete returns false if user cannot permanently delete model regardless of company access', function () {
    $bouncer = Mockery::mock('alias:' . BouncerFacade::class);
    $user = Mockery::mock(User::class);
    $unit = Mockery::mock(Unit::class);
    $policy = new UnitPolicy();

    $bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany');

    $result = $policy->forceDelete($user, $unit);

    expect($result)->toBeFalse();
});
