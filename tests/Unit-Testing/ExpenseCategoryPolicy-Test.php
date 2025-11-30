<?php

use Crater\Models\Expense;
use Crater\Models\ExpenseCategory;
use Crater\Models\User;
use Crater\Policies\ExpenseCategoryPolicy;
use Silber\Bouncer\BouncerFacade;
use function Pest\Laravel\mock;

// Clean up BouncerFacade before each test to ensure isolation when mocking facades.
// This prevents state from leaking between tests that might mock the same facade.


beforeEach(function () {
    BouncerFacade::clearResolvedInstances();
    BouncerFacade::clearResolvedFacadeInstance('Bouncer');

        $this->policy = new ExpenseCategoryPolicy();
        $this->user = mock(User::class);
        $this->expenseCategory = mock(ExpenseCategory::class);
        // Assign a default company_id to the expense category for tests requiring it
        $this->expenseCategory->company_id = 1;
    });

    // Helper function to mock BouncerFacade::can method with specific expectations
    $mockBouncerCan = function (bool $can) {
        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-expense', Expense::class)
            ->andReturn($can);
    };

    // --- viewAny method tests ---
    test('viewAny allows access if user can view expenses', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        expect($this->policy->viewAny($this->user))->toBeTrue();
    });

    test('viewAny denies access if user cannot view expenses', function () use ($mockBouncerCan) {
        $mockBouncerCan(false);
        expect($this->policy->viewAny($this->user))->toBeFalse();
    });

    // --- view method tests ---
    test('view allows access if user can view expenses and belongs to the company', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        $this->user->shouldReceive('hasCompany')
            ->once()
            ->with($this->expenseCategory->company_id)
            ->andReturn(true);

        expect($this->policy->view($this->user, $this->expenseCategory))->toBeTrue();
    });

    test('view denies access if user cannot view expenses (short-circuiting BouncerFacade::can)', function () use ($mockBouncerCan) {
        $mockBouncerCan(false);
        // Due to the short-circuiting AND, hasCompany should not be called if 'can' is false
        $this->user->shouldNotReceive('hasCompany');
        expect($this->policy->view($this->user, $this->expenseCategory))->toBeFalse();
    });

    test('view denies access if user can view expenses but does not belong to the correct company', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        $this->user->shouldReceive('hasCompany')
            ->once()
            ->with($this->expenseCategory->company_id)
            ->andReturn(false);

        expect($this->policy->view($this->user, $this->expenseCategory))->toBeFalse();
    });

    // --- create method tests ---
    test('create allows access if user can view expenses', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        expect($this->policy->create($this->user))->toBeTrue();
    });

    test('create denies access if user cannot view expenses', function () use ($mockBouncerCan) {
        $mockBouncerCan(false);
        expect($this->policy->create($this->user))->toBeFalse();
    });

    // --- update method tests ---
    test('update allows access if user can view expenses and belongs to the company', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        $this->user->shouldReceive('hasCompany')
            ->once()
            ->with($this->expenseCategory->company_id)
            ->andReturn(true);

        expect($this->policy->update($this->user, $this->expenseCategory))->toBeTrue();
    });

    test('update denies access if user cannot view expenses (short-circuiting BouncerFacade::can)', function () use ($mockBouncerCan) {
        $mockBouncerCan(false);
        $this->user->shouldNotReceive('hasCompany');
        expect($this->policy->update($this->user, $this->expenseCategory))->toBeFalse();
    });

    test('update denies access if user can view expenses but does not belong to the correct company', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        $this->user->shouldReceive('hasCompany')
            ->once()
            ->with($this->expenseCategory->company_id)
            ->andReturn(false);

        expect($this->policy->update($this->user, $this->expenseCategory))->toBeFalse();
    });

    // --- delete method tests ---
    test('delete allows access if user can view expenses and belongs to the company', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        $this->user->shouldReceive('hasCompany')
            ->once()
            ->with($this->expenseCategory->company_id)
            ->andReturn(true);

        expect($this->policy->delete($this->user, $this->expenseCategory))->toBeTrue();
    });

    test('delete denies access if user cannot view expenses (short-circuiting BouncerFacade::can)', function () use ($mockBouncerCan) {
        $mockBouncerCan(false);
        $this->user->shouldNotReceive('hasCompany');
        expect($this->policy->delete($this->user, $this->expenseCategory))->toBeFalse();
    });

    test('delete denies access if user can view expenses but does not belong to the correct company', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        $this->user->shouldReceive('hasCompany')
            ->once()
            ->with($this->expenseCategory->company_id)
            ->andReturn(false);

        expect($this->policy->delete($this->user, $this->expenseCategory))->toBeFalse();
    });

    // --- restore method tests ---
    test('restore allows access if user can view expenses and belongs to the company', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        $this->user->shouldReceive('hasCompany')
            ->once()
            ->with($this->expenseCategory->company_id)
            ->andReturn(true);

        expect($this->policy->restore($this->user, $this->expenseCategory))->toBeTrue();
    });

    test('restore denies access if user cannot view expenses (short-circuiting BouncerFacade::can)', function () use ($mockBouncerCan) {
        $mockBouncerCan(false);
        $this->user->shouldNotReceive('hasCompany');
        expect($this->policy->restore($this->user, $this->expenseCategory))->toBeFalse();
    });

    test('restore denies access if user can view expenses but does not belong to the correct company', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        $this->user->shouldReceive('hasCompany')
            ->once()
            ->with($this->expenseCategory->company_id)
            ->andReturn(false);

        expect($this->policy->restore($this->user, $this->expenseCategory))->toBeFalse();
    });

    // --- forceDelete method tests ---
    test('forceDelete allows access if user can view expenses and belongs to the company', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        $this->user->shouldReceive('hasCompany')
            ->once()
            ->with($this->expenseCategory->company_id)
            ->andReturn(true);

        expect($this->policy->forceDelete($this->user, $this->expenseCategory))->toBeTrue();
    });

    test('forceDelete denies access if user cannot view expenses (short-circuiting BouncerFacade::can)', function () use ($mockBouncerCan) {
        $mockBouncerCan(false);
        $this->user->shouldNotReceive('hasCompany');
        expect($this->policy->forceDelete($this->user, $this->expenseCategory))->toBeFalse();
    });

    test('forceDelete denies access if user can view expenses but does not belong to the correct company', function () use ($mockBouncerCan) {
        $mockBouncerCan(true);
        $this->user->shouldReceive('hasCompany')
            ->once()
            ->with($this->expenseCategory->company_id)
            ->andReturn(false);

        expect($this->policy->forceDelete($this->user, $this->expenseCategory))->toBeFalse();
    });
