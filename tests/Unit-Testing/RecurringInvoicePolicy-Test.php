<?php

use Crater\Models\RecurringInvoice;
use Crater\Models\User;
use Crater\Policies\RecurringInvoicePolicy;
use Silber\Bouncer\BouncerFacade;

beforeEach(function () {
    // Mock the BouncerFacade alias to control its 'can' method for unit isolation
    $this->bouncerMock = Mockery::mock('alias:' . BouncerFacade::class);
    $this->policy = new RecurringInvoicePolicy();
});


// --- Test Cases for viewAny method ---

test('viewAny allows access when Bouncer grants permission', function () {
    $user = Mockery::mock(User::class);

    $this->bouncerMock->shouldReceive('can')
        ->with('view-recurring-invoice', RecurringInvoice::class)
        ->andReturn(true)
        ->once();

    $result = $this->policy->viewAny($user);

    expect($result)->toBeTrue();
});

test('viewAny denies access when Bouncer denies permission', function () {
    $user = Mockery::mock(User::class);

    $this->bouncerMock->shouldReceive('can')
        ->with('view-recurring-invoice', RecurringInvoice::class)
        ->andReturn(false)
        ->once();

    $result = $this->policy->viewAny($user);

    expect($result)->toBeFalse();
});

// --- Test Cases for view method ---

test('view allows access when user has permission AND belongs to the recurring invoice company', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId; // Simulate dynamic property access

    $this->bouncerMock->shouldReceive('can')
        ->with('view-recurring-invoice', $recurringInvoice)
        ->andReturn(true)
        ->once();

    $user->shouldReceive('hasCompany')
        ->with($companyId)
        ->andReturn(true)
        ->once();

    $result = $this->policy->view($user, $recurringInvoice);

    expect($result)->toBeTrue();
});

test('view denies access when user has permission but does NOT belong to the recurring invoice company', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('view-recurring-invoice', $recurringInvoice)
        ->andReturn(true)
        ->once();

    $user->shouldReceive('hasCompany')
        ->with($companyId)
        ->andReturn(false)
        ->once();

    $result = $this->policy->view($user, $recurringInvoice);

    expect($result)->toBeFalse();
});

test('view denies access when user does NOT have permission to view recurring invoice (short-circuit)', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('view-recurring-invoice', $recurringInvoice)
        ->andReturn(false)
        ->once();

    // hasCompany should not be called due to short-circuiting of the '&&' operator
    $user->shouldNotReceive('hasCompany');

    $result = $this->policy->view($user, $recurringInvoice);

    expect($result)->toBeFalse();
});

test('view denies access when user has no permission AND does not belong to the company (short-circuit)', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('view-recurring-invoice', $recurringInvoice)
        ->andReturn(false)
        ->once();

    $user->shouldNotReceive('hasCompany'); // Still short-circuits on first false

    $result = $this->policy->view($user, $recurringInvoice);

    expect($result)->toBeFalse();
});

// --- Test Cases for create method ---

test('create allows access when Bouncer grants permission', function () {
    $user = Mockery::mock(User::class);

    $this->bouncerMock->shouldReceive('can')
        ->with('create-recurring-invoice', RecurringInvoice::class)
        ->andReturn(true)
        ->once();

    $result = $this->policy->create($user);

    expect($result)->toBeTrue();
});

test('create denies access when Bouncer denies permission', function () {
    $user = Mockery::mock(User::class);

    $this->bouncerMock->shouldReceive('can')
        ->with('create-recurring-invoice', RecurringInvoice::class)
        ->andReturn(false)
        ->once();

    $result = $this->policy->create($user);

    expect($result)->toBeFalse();
});

// --- Test Cases for update method ---

test('update allows access when user has permission AND belongs to the recurring invoice company', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('edit-recurring-invoice', $recurringInvoice)
        ->andReturn(true)
        ->once();

    $user->shouldReceive('hasCompany')
        ->with($companyId)
        ->andReturn(true)
        ->once();

    $result = $this->policy->update($user, $recurringInvoice);

    expect($result)->toBeTrue();
});

test('update denies access when user has permission but does NOT belong to the recurring invoice company', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('edit-recurring-invoice', $recurringInvoice)
        ->andReturn(true)
        ->once();

    $user->shouldReceive('hasCompany')
        ->with($companyId)
        ->andReturn(false)
        ->once();

    $result = $this->policy->update($user, $recurringInvoice);

    expect($result)->toBeFalse();
});

test('update denies access when user does NOT have permission to edit recurring invoice (short-circuit)', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('edit-recurring-invoice', $recurringInvoice)
        ->andReturn(false)
        ->once();

    $user->shouldNotReceive('hasCompany');

    $result = $this->policy->update($user, $recurringInvoice);

    expect($result)->toBeFalse();
});

// --- Test Cases for delete method ---

test('delete allows access when user has permission AND belongs to the recurring invoice company', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('delete-recurring-invoice', $recurringInvoice)
        ->andReturn(true)
        ->once();

    $user->shouldReceive('hasCompany')
        ->with($companyId)
        ->andReturn(true)
        ->once();

    $result = $this->policy->delete($user, $recurringInvoice);

    expect($result)->toBeTrue();
});

test('delete denies access when user has permission but does NOT belong to the recurring invoice company', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('delete-recurring-invoice', $recurringInvoice)
        ->andReturn(true)
        ->once();

    $user->shouldReceive('hasCompany')
        ->with($companyId)
        ->andReturn(false)
        ->once();

    $result = $this->policy->delete($user, $recurringInvoice);

    expect($result)->toBeFalse();
});

test('delete denies access when user does NOT have permission to delete recurring invoice (short-circuit)', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('delete-recurring-invoice', $recurringInvoice)
        ->andReturn(false)
        ->once();

    $user->shouldNotReceive('hasCompany');

    $result = $this->policy->delete($user, $recurringInvoice);

    expect($result)->toBeFalse();
});

// --- Test Cases for restore method ---

test('restore allows access when user has permission AND belongs to the recurring invoice company', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('delete-recurring-invoice', $recurringInvoice) // Policy uses 'delete-recurring-invoice' for restore
        ->andReturn(true)
        ->once();

    $user->shouldReceive('hasCompany')
        ->with($companyId)
        ->andReturn(true)
        ->once();

    $result = $this->policy->restore($user, $recurringInvoice);

    expect($result)->toBeTrue();
});

test('restore denies access when user has permission but does NOT belong to the recurring invoice company', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('delete-recurring-invoice', $recurringInvoice)
        ->andReturn(true)
        ->once();

    $user->shouldReceive('hasCompany')
        ->with($companyId)
        ->andReturn(false)
        ->once();

    $result = $this->policy->restore($user, $recurringInvoice);

    expect($result)->toBeFalse();
});

test('restore denies access when user does NOT have permission to delete recurring invoice (short-circuit)', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('delete-recurring-invoice', $recurringInvoice)
        ->andReturn(false)
        ->once();

    $user->shouldNotReceive('hasCompany');

    $result = $this->policy->restore($user, $recurringInvoice);

    expect($result)->toBeFalse();
});

// --- Test Cases for forceDelete method ---

test('forceDelete allows access when user has permission AND belongs to the recurring invoice company', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('delete-recurring-invoice', $recurringInvoice) // Policy uses 'delete-recurring-invoice' for forceDelete
        ->andReturn(true)
        ->once();

    $user->shouldReceive('hasCompany')
        ->with($companyId)
        ->andReturn(true)
        ->once();

    $result = $this->policy->forceDelete($user, $recurringInvoice);

    expect($result)->toBeTrue();
});

test('forceDelete denies access when user has permission but does NOT belong to the recurring invoice company', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('delete-recurring-invoice', $recurringInvoice)
        ->andReturn(true)
        ->once();

    $user->shouldReceive('hasCompany')
        ->with($companyId)
        ->andReturn(false)
        ->once();

    $result = $this->policy->forceDelete($user, $recurringInvoice);

    expect($result)->toBeFalse();
});

test('forceDelete denies access when user does NOT have permission to delete recurring invoice (short-circuit)', function () {
    $user = Mockery::mock(User::class);
    $recurringInvoice = Mockery::mock(RecurringInvoice::class);
    $companyId = 1;

    $recurringInvoice->company_id = $companyId;

    $this->bouncerMock->shouldReceive('can')
        ->with('delete-recurring-invoice', $recurringInvoice)
        ->andReturn(false)
        ->once();

    $user->shouldNotReceive('hasCompany');

    $result = $this->policy->forceDelete($user, $recurringInvoice);

    expect($result)->toBeFalse();
});

// --- Test Cases for deleteMultiple method ---

test('deleteMultiple allows access when Bouncer grants permission', function () {
    $user = Mockery::mock(User::class);

    $this->bouncerMock->shouldReceive('can')
        ->with('delete-recurring-invoice', RecurringInvoice::class)
        ->andReturn(true)
        ->once();

    $result = $this->policy->deleteMultiple($user);

    expect($result)->toBeTrue();
});

test('deleteMultiple denies access when Bouncer denies permission', function () {
    $user = Mockery::mock(User::class);

    $this->bouncerMock->shouldReceive('can')
        ->with('delete-recurring-invoice', RecurringInvoice::class)
        ->andReturn(false)
        ->once();

    $result = $this->policy->deleteMultiple($user);

    expect($result)->toBeFalse();
});




afterEach(function () {
    Mockery::close();
});
