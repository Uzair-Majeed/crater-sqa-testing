<?php

use Crater\Policies\PaymentMethodPolicy;
use Crater\Models\User;
use Crater\Models\PaymentMethod;
use Crater\Models\Payment;
use Silber\Bouncer\BouncerFacade;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

// This trait is helpful for ensuring Mockery expectations are verified
// and mocks are closed even when not extending a Laravel TestCase.
uses(MockeryPHPUnitIntegration::class);

beforeEach(function () {
        $this->policy = new PaymentMethodPolicy();
    });

    // --- viewAny method tests ---
    test('viewAny allows access if user can view any payment', function () {
        // Arrange
        $user = Mockery::mock(User::class);
        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        // Act & Assert
        expect($this->policy->viewAny($user))->toBeTrue();
    });

    test('viewAny denies access if user cannot view any payment', function () {
        // Arrange
        $user = Mockery::mock(User::class);
        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(false);

        // Act & Assert
        expect($this->policy->viewAny($user))->toBeFalse();
    });

    // --- view method tests ---
    test('view allows access if user can view payment and belongs to the payment method company', function () {
        // Arrange
        $companyId = 123;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId; // Set the property on the mock

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($companyId)
            ->andReturn(true);

        // Act & Assert
        expect($this->policy->view($user, $paymentMethod))->toBeTrue();
    });

    test('view denies access if user can view payment but does not belong to the payment method company', function () {
        // Arrange
        $companyId = 123;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($companyId)
            ->andReturn(false);

        // Act & Assert
        expect($this->policy->view($user, $paymentMethod))->toBeFalse();
    });

    test('view denies access if user cannot view payment (short circuit)', function () {
        // Arrange
        $companyId = 123;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(false);

        // hasCompany should not be called due to short-circuiting '&&' operator
        $user->shouldNotReceive('hasCompany');

        // Act & Assert
        expect($this->policy->view($user, $paymentMethod))->toBeFalse();
    });

    // --- create method tests ---
    test('create allows access if user can view payment', function () {
        // Arrange
        $user = Mockery::mock(User::class);
        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        // Act & Assert
        expect($this->policy->create($user))->toBeTrue();
    });

    test('create denies access if user cannot view payment', function () {
        // Arrange
        $user = Mockery::mock(User::class);
        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(false);

        // Act & Assert
        expect($this->policy->create($user))->toBeFalse();
    });

    // --- update method tests (logic identical to view) ---
    test('update allows access if user can view payment and belongs to the payment method company', function () {
        // Arrange
        $companyId = 456;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($companyId)
            ->andReturn(true);

        // Act & Assert
        expect($this->policy->update($user, $paymentMethod))->toBeTrue();
    });

    test('update denies access if user can view payment but does not belong to the payment method company', function () {
        // Arrange
        $companyId = 456;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($companyId)
            ->andReturn(false);

        // Act & Assert
        expect($this->policy->update($user, $paymentMethod))->toBeFalse();
    });

    test('update denies access if user cannot view payment (short circuit)', function () {
        // Arrange
        $companyId = 456;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(false);

        $user->shouldNotReceive('hasCompany');

        // Act & Assert
        expect($this->policy->update($user, $paymentMethod))->toBeFalse();
    });

    // --- delete method tests (logic identical to view) ---
    test('delete allows access if user can view payment and belongs to the payment method company', function () {
        // Arrange
        $companyId = 789;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($companyId)
            ->andReturn(true);

        // Act & Assert
        expect($this->policy->delete($user, $paymentMethod))->toBeTrue();
    });

    test('delete denies access if user can view payment but does not belong to the payment method company', function () {
        // Arrange
        $companyId = 789;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($companyId)
            ->andReturn(false);

        // Act & Assert
        expect($this->policy->delete($user, $paymentMethod))->toBeFalse();
    });

    test('delete denies access if user cannot view payment (short circuit)', function () {
        // Arrange
        $companyId = 789;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(false);

        $user->shouldNotReceive('hasCompany');

        // Act & Assert
        expect($this->policy->delete($user, $paymentMethod))->toBeFalse();
    });

    // --- restore method tests (logic identical to view) ---
    test('restore allows access if user can view payment and belongs to the payment method company', function () {
        // Arrange
        $companyId = 101;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($companyId)
            ->andReturn(true);

        // Act & Assert
        expect($this->policy->restore($user, $paymentMethod))->toBeTrue();
    });

    test('restore denies access if user can view payment but does not belong to the payment method company', function () {
        // Arrange
        $companyId = 101;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($companyId)
            ->andReturn(false);

        // Act & Assert
        expect($this->policy->restore($user, $paymentMethod))->toBeFalse();
    });

    test('restore denies access if user cannot view payment (short circuit)', function () {
        // Arrange
        $companyId = 101;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(false);

        $user->shouldNotReceive('hasCompany');

        // Act & Assert
        expect($this->policy->restore($user, $paymentMethod))->toBeFalse();
    });

    // --- forceDelete method tests (logic identical to view) ---
    test('forceDelete allows access if user can view payment and belongs to the payment method company', function () {
        // Arrange
        $companyId = 202;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($companyId)
            ->andReturn(true);

        // Act & Assert
        expect($this->policy->forceDelete($user, $paymentMethod))->toBeTrue();
    });

    test('forceDelete denies access if user can view payment but does not belong to the payment method company', function () {
        // Arrange
        $companyId = 202;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($companyId)
            ->andReturn(false);

        // Act & Assert
        expect($this->policy->forceDelete($user, $paymentMethod))->toBeFalse();
    });

    test('forceDelete denies access if user cannot view payment (short circuit)', function () {
        // Arrange
        $companyId = 202;
        $user = Mockery::mock(User::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);
        $paymentMethod->company_id = $companyId;

        BouncerFacade::shouldReceive('can')
            ->once()
            ->with('view-payment', Payment::class)
            ->andReturn(false);

        $user->shouldNotReceive('hasCompany');

        // Act & Assert
        expect($this->policy->forceDelete($user, $paymentMethod))->toBeFalse();
    });
