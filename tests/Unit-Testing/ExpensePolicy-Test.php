<?php

use Crater\Policies\ExpensePolicy;
use Crater\Models\Expense;
use Crater\Models\User;
use Silber\Bouncer\BouncerFacade;

beforeEach(function () {
    $this->policy = new ExpensePolicy();
    Mockery::close(); // Ensures mocks are cleaned up for each test
});

test('viewAny returns true if user can view any expense', function () {
    $user = Mockery::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-expense', Expense::class)
        ->andReturn(true);

    $result = $this->policy->viewAny($user);

    expect($result)->toBeTrue();
});

test('viewAny returns false if user cannot view any expense', function () {
    $user = Mockery::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-expense', Expense::class)
        ->andReturn(false);

    $result = $this->policy->viewAny($user);

    expect($result)->toBeFalse();
});

test('view returns true if user can view specific expense and owns company', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 123;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-expense', $expense)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $result = $this->policy->view($user, $expense);

    expect($result)->toBeTrue();
});

test('view returns false if user cannot view specific expense', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 123;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-expense', $expense)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany'); // Short-circuiting

    $result = $this->policy->view($user, $expense);

    expect($result)->toBeFalse();
});

test('view returns false if user can view specific expense but does not own company', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 123;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-expense', $expense)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $result = $this->policy->view($user, $expense);

    expect($result)->toBeFalse();
});

test('view returns false if user cannot view specific expense and does not own company', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 123;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-expense', $expense)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany'); // Short-circuiting

    $result = $this->policy->view($user, $expense);

    expect($result)->toBeFalse();
});

test('create returns true if user can create expense', function () {
    $user = Mockery::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('create-expense', Expense::class)
        ->andReturn(true);

    $result = $this->policy->create($user);

    expect($result)->toBeTrue();
});

test('create returns false if user cannot create expense', function () {
    $user = Mockery::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('create-expense', Expense::class)
        ->andReturn(false);

    $result = $this->policy->create($user);

    expect($result)->toBeFalse();
});

test('update returns true if user can edit specific expense and owns company', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 456;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-expense', $expense)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $result = $this->policy->update($user, $expense);

    expect($result)->toBeTrue();
});

test('update returns false if user cannot edit specific expense', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 456;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-expense', $expense)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany'); // Short-circuiting

    $result = $this->policy->update($user, $expense);

    expect($result)->toBeFalse();
});

test('update returns false if user can edit specific expense but does not own company', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 456;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-expense', $expense)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $result = $this->policy->update($user, $expense);

    expect($result)->toBeFalse();
});

test('delete returns true if user can delete specific expense and owns company', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 789;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-expense', $expense)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $result = $this->policy->delete($user, $expense);

    expect($result)->toBeTrue();
});

test('delete returns false if user cannot delete specific expense', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 789;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-expense', $expense)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany'); // Short-circuiting

    $result = $this->policy->delete($user, $expense);

    expect($result)->toBeFalse();
});

test('delete returns false if user can delete specific expense but does not own company', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 789;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-expense', $expense)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $result = $this->policy->delete($user, $expense);

    expect($result)->toBeFalse();
});

test('restore returns true if user can restore specific expense and owns company', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 101;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-expense', $expense) // Uses delete-expense permission
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $result = $this->policy->restore($user, $expense);

    expect($result)->toBeTrue();
});

test('restore returns false if user cannot restore specific expense', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 101;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-expense', $expense)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany'); // Short-circuiting

    $result = $this->policy->restore($user, $expense);

    expect($result)->toBeFalse();
});

test('restore returns false if user can restore specific expense but does not own company', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 101;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-expense', $expense)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $result = $this->policy->restore($user, $expense);

    expect($result)->toBeFalse();
});

test('forceDelete returns true if user can force delete specific expense and owns company', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 202;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-expense', $expense) // Uses delete-expense permission
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $result = $this->policy->forceDelete($user, $expense);

    expect($result)->toBeTrue();
});

test('forceDelete returns false if user cannot force delete specific expense', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 202;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-expense', $expense)
        ->andReturn(false);

    $user->shouldNotReceive('hasCompany'); // Short-circuiting

    $result = $this->policy->forceDelete($user, $expense);

    expect($result)->toBeFalse();
});

test('forceDelete returns false if user can force delete specific expense but does not own company', function () {
    $user = Mockery::mock(User::class);
    $expense = Mockery::mock(Expense::class);
    $companyId = 202;

    $expense->company_id = $companyId;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-expense', $expense)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $result = $this->policy->forceDelete($user, $expense);

    expect($result)->toBeFalse();
});

test('deleteMultiple returns true if user can delete multiple expenses', function () {
    $user = Mockery::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-expense', Expense::class)
        ->andReturn(true);

    $result = $this->policy->deleteMultiple($user);

    expect($result)->toBeTrue();
});

test('deleteMultiple returns false if user cannot delete multiple expenses', function () {
    $user = Mockery::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-expense', Expense::class)
        ->andReturn(false);

    $result = $this->policy->deleteMultiple($user);

    expect($result)->toBeFalse();
});




afterEach(function () {
    Mockery::close();
});
