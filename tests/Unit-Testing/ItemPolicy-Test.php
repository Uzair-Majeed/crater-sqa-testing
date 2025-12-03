<?php

use Crater\Policies\ItemPolicy;
use Crater\Models\Item;
use Crater\Models\User;
use Mockery as m;
use Silber\Bouncer\BouncerFacade;

beforeEach(function () {
    // Ensure BouncerFacade is mocked as an alias for static method calls
    m::mock('alias:' . BouncerFacade::class);
    $this->policy = new ItemPolicy();
});

afterEach(function () {
    // Close Mockery after each test
    m::close();
});

test('viewAny allows access if the user can view any item', function () {
        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-item', Item::class)
            ->andReturn(true);

        $user = m::mock(User::class);

        expect($this->policy->viewAny($user))->toBeTrue();
    });

    test('viewAny denies access if the user cannot view any item', function () {
        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-item', $item)
            ->andReturn(false);

        $user->shouldNotReceive('hasCompany'); // Still short-circuits

        expect($this->policy->view($user, $item))->toBeFalse();
    });

    // Tests for create method
    test('create allows access if the user can create an item', function () {
        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('create-item', Item::class)
            ->andReturn(true);

        $user = m::mock(User::class);

        expect($this->policy->create($user))->toBeTrue();
    });

    test('create denies access if the user cannot create an item', function () {
        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
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
        $item->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('delete-item', $item)
            ->andReturn(false);

        $user->shouldNotReceive('hasCompany');

        expect($this->policy->forceDelete($user, $item))->toBeFalse();
    });

    // Tests for deleteMultiple method
    test('deleteMultiple allows access if the user can delete any item', function () {
        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('delete-item', Item::class)
            ->andReturn(true);

        $user = m::mock(User::class);

        expect($this->policy->deleteMultiple($user))->toBeTrue();
    });

    test('deleteMultiple denies access if the user cannot delete any item', function () {
        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('delete-item', Item::class)
            ->andReturn(false);

        $user = m::mock(User::class);

        expect($this->policy->deleteMultiple($user))->toBeFalse();
    });



