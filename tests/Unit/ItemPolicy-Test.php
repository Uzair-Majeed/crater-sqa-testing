<?php

use Crater\Policies\ItemPolicy;
use Crater\Models\Item;
use Crater\Models\User;
use Mockery as m;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Bouncer; // Import the actual Bouncer service class

beforeEach(function () {
    // Mock the underlying Bouncer service and bind it to the Laravel container.
    // This ensures that any static calls to BouncerFacade::can() (or just Bouncer::can() if Bouncer is resolved from the container)
    // will be intercepted and directed to our mock instance.
    $bouncerMock = m::mock(Bouncer::class);
    app()->instance('bouncer', $bouncerMock);
    $this->bouncer = $bouncerMock; // Store the mock instance for easy access in tests
    $this->policy = new ItemPolicy();
});

afterEach(function () {
    // Forget the Bouncer instance from the container to ensure test isolation.
    app()->forgetInstance('bouncer');
    m::close();
});

test('viewAny allows access if the user can view any item', function () {
    // Use the stored bouncer mock instance for setting expectations
    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(true);

    $user = m::mock(User::class);

    expect($this->policy->viewAny($user))->toBeTrue();
});

test('viewAny denies access if the user cannot view any item', function () {
    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', Item::class)
        ->andReturn(false);

    $user = m::mock(User::class);

    expect($this->policy->viewAny($user))->toBeFalse();
});

// Tests for view method
test('view allows access if the user can view the specific item and belongs to its company', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    // Policies typically read model attributes via magic __get, which calls getAttribute.
    // We mock getAttribute to provide the company_id without triggering a Mockery exception for __set.
    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->atLeast()->once();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', $item)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    expect($this->policy->view($user, $item))->toBeTrue();
});

test('view denies access if the user can view the item but does not belong to its company', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->atLeast()->once();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', $item)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    expect($this->policy->view($user, $item))->toBeFalse();
});

test('view denies access if the user cannot view the item (short-circuit)', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    // If 'can' returns false, hasCompany (and thus accessing $item->company_id) should not be called.
    // We set zeroOrMoreTimes in case the policy somehow accesses company_id before the 'can' check,
    // but the main point is that hasCompany is not called.
    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->zeroOrMoreTimes();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', $item)
        ->andReturn(false);

    // hasCompany should not be called due to short-circuiting
    $user->shouldNotReceive('hasCompany');

    expect($this->policy->view($user, $item))->toBeFalse();
});

test('view denies access if the user cannot view the item and does not belong to its company (short-circuit)', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->zeroOrMoreTimes();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('view-item', $item)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany'); // Still short-circuits

    expect($this->policy->view($user, $item))->toBeFalse();
});

// Tests for create method
test('create allows access if the user can create an item', function () {
    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('create-item', Item::class)
        ->andReturn(true);

    $user = m::mock(User::class);

    expect($this->policy->create($user))->toBeTrue();
});

test('create denies access if the user cannot create an item', function () {
    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('create-item', Item::class)
        ->andReturn(false);

    $user = m::mock(User::class);

    expect($this->policy->create($user))->toBeFalse();
});

// Tests for update method
test('update allows access if the user can edit the specific item and belongs to its company', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->atLeast()->once();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('edit-item', $item)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    expect($this->policy->update($user, $item))->toBeTrue();
});

test('update denies access if the user can edit the item but does not belong to its company', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->atLeast()->once();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('edit-item', $item)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    expect($this->policy->update($user, $item))->toBeFalse();
});

test('update denies access if the user cannot edit the item (short-circuit)', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->zeroOrMoreTimes();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('edit-item', $item)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany');

    expect($this->policy->update($user, $item))->toBeFalse();
});

// Tests for delete method
test('delete allows access if the user can delete the specific item and belongs to its company', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->atLeast()->once();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('delete-item', $item)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    expect($this->policy->delete($user, $item))->toBeTrue();
});

test('delete denies access if the user can delete the item but does not belong to its company', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->atLeast()->once();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('delete-item', $item)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    expect($this->policy->delete($user, $item))->toBeFalse();
});

test('delete denies access if the user cannot delete the item (short-circuit)', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->zeroOrMoreTimes();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('delete-item', $item)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany');

    expect($this->policy->delete($user, $item))->toBeFalse();
});

// Tests for restore method
test('restore allows access if the user can delete the specific item and belongs to its company', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->atLeast()->once();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('delete-item', $item) // Policy uses 'delete-item' for restore
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    expect($this->policy->restore($user, $item))->toBeTrue();
});

test('restore denies access if the user can delete the item but does not belong to its company', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->atLeast()->once();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('delete-item', $item)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    expect($this->policy->restore($user, $item))->toBeFalse();
});

test('restore denies access if the user cannot delete the item (short-circuit)', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->zeroOrMoreTimes();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('delete-item', $item)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany');

    expect($this->policy->restore($user, $item))->toBeFalse();
});

// Tests for forceDelete method
test('forceDelete allows access if the user can delete the specific item and belongs to its company', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->atLeast()->once();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('delete-item', $item) // Policy uses 'delete-item' for forceDelete
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    expect($this->policy->forceDelete($user, $item))->toBeTrue();
});

test('forceDelete denies access if the user can delete the item but does not belong to its company', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->atLeast()->once();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('delete-item', $item)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    expect($this->policy->forceDelete($user, $item))->toBeFalse();
});

test('forceDelete denies access if the user cannot delete the item (short-circuit)', function () {
    $companyId = 1;
    $user = m::mock(User::class);
    $item = m::mock(Item::class);

    $item->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId)->zeroOrMoreTimes();

    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('delete-item', $item)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany');

    expect($this->policy->forceDelete($user, $item))->toBeFalse();
});

// Tests for deleteMultiple method
test('deleteMultiple allows access if the user can delete any item', function () {
    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('delete-item', Item::class)
        ->andReturn(true);

    $user = m::mock(User::class);

    expect($this->policy->deleteMultiple($user))->toBeTrue();
});

test('deleteMultiple denies access if the user cannot delete any item', function () {
    $this->bouncer->shouldReceive('can')
        ->once()
        ->with('delete-item', Item::class)
        ->andReturn(false);

    $user = m::mock(User::class);

    expect($this->policy->deleteMultiple($user))->toBeFalse();
});