<?php

use Crater\Policies\PaymentPolicy;
use Crater\Models\User;
use Crater\Models\Payment;
use Silber\Bouncer\BouncerFacade;
use Mockery as m;

// Ensures Mockery mocks are closed and cleaned up before each test.
beforeEach(function () {
    m::close();
});

test('viewAny returns true if user can view any payment', function () {
    // Explicitly alias BouncerFacade for this test's scope
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-payment', Payment::class)
        ->andReturn(true);

    assertTrue($policy->viewAny($user));
});

test('viewAny returns false if user cannot view any payment', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-payment', Payment::class)
        ->andReturn(false);

    assertFalse($policy->viewAny($user));
});

test('view returns true if user can view specific payment and belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-payment', $payment)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertTrue($policy->view($user, $payment));
});

test('view returns false if user can view specific payment but does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-payment', $payment)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->view($user, $payment));
});

test('view returns false if user cannot view specific payment even if belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertFalse($policy->view($user, $payment));
});

test('view returns false if user cannot view specific payment and does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->view($user, $payment));
});

test('create returns true if user can create payment', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('create-payment', Payment::class)
        ->andReturn(true);

    assertTrue($policy->create($user));
});

test('create returns false if user cannot create payment', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('create-payment', Payment::class)
        ->andReturn(false);

    assertFalse($policy->create($user));
});

test('update returns true if user can edit payment and belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-payment', $payment)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertTrue($policy->update($user, $payment));
});

test('update returns false if user can edit payment but does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-payment', $payment)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->update($user, $payment));
});

test('update returns false if user cannot edit payment even if belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertFalse($policy->update($user, $payment));
});

test('update returns false if user cannot edit payment and does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('edit-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->update($user, $payment));
});

test('delete returns true if user can delete payment and belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertTrue($policy->delete($user, $payment));
});

test('delete returns false if user can delete payment but does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->delete($user, $payment));
});

test('delete returns false if user cannot delete payment even if belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertFalse($policy->delete($user, $payment));
});

test('delete returns false if user cannot delete payment and does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->delete($user, $payment));
});

test('restore returns true if user can delete payment and belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment) // White-box: checks 'delete-payment'
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertTrue($policy->restore($user, $payment));
});

test('restore returns false if user can delete payment but does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->restore($user, $payment));
});

test('restore returns false if user cannot delete payment even if belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertFalse($policy->restore($user, $payment));
});

test('restore returns false if user cannot delete payment and does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->restore($user, $payment));
});

test('forceDelete returns true if user can delete payment and belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment) // White-box: checks 'delete-payment'
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertTrue($policy->forceDelete($user, $payment));
});

test('forceDelete returns false if user can delete payment but does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->forceDelete($user, $payment));
});

test('forceDelete returns false if user cannot delete payment even if belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertFalse($policy->forceDelete($user, $payment));
});

test('forceDelete returns false if user cannot delete payment and does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->forceDelete($user, $payment));
});

test('send returns true if user can send payment and belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('send-payment', $payment)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertTrue($policy->send($user, $payment));
});

test('send returns false if user can send payment but does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('send-payment', $payment)
        ->andReturn(true);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->send($user, $payment));
});

test('send returns false if user cannot send payment even if belongs to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('send-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(true);

    assertFalse($policy->send($user, $payment));
});

test('send returns false if user cannot send payment and does not belong to company', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);
    $payment = m::mock(Payment::class);
    $payment->company_id = 1;

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('send-payment', $payment)
        ->andReturn(false);

    $user->shouldReceive('hasCompany')
        ->once()
        ->with($payment->company_id)
        ->andReturn(false);

    assertFalse($policy->send($user, $payment));
});

test('deleteMultiple returns true if user can delete multiple payments', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', Payment::class)
        ->andReturn(true);

    assertTrue($policy->deleteMultiple($user));
});

test('deleteMultiple returns false if user cannot delete multiple payments', function () {
    m::mock('alias:' . BouncerFacade::class);
    $policy = new PaymentPolicy();
    $user = m::mock(User::class);

    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('delete-payment', Payment::class)
        ->andReturn(false);

    assertFalse($policy->deleteMultiple($user));
});
