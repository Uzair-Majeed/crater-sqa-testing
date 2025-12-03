<?php

use Crater\Policies\InvoicePolicy;
use Crater\Models\User;
use Crater\Models\Invoice;

beforeEach(function () {
        // Clear Mockery mocks before each test
        Mockery::close();
    });

    test('viewAny returns true if user can view any invoice', function () {
        // Mock BouncerFacade alias for static method
        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('view-invoice', Invoice::class)
            ->andReturn(true)
            ->once();

        // User object is required by method signature but not used in logic
        $user = Mockery::mock(User::class);

        $policy = new InvoicePolicy();
        $result = $policy->viewAny($user);

        expect($result)->toBeTrue();
    });

    test('viewAny returns false if user cannot view any invoice', function () {
        // Mock BouncerFacade alias for static method
        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('view-invoice', Invoice::class)
            ->andReturn(false)
            ->once();

        // User object is required by method signature but not used in logic
        $user = Mockery::mock(User::class);

        $policy = new InvoicePolicy();
        $result = $policy->viewAny($user);

        expect($result)->toBeFalse();
    });

    test('view returns true if user can view specific invoice and has company access', function () {
        $invoiceCompanyId = 1;

        // Mock BouncerFacade alias for static method
        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('view-invoice', Mockery::type(Invoice::class))
            ->andReturn(true)
            ->once();

        // Mock Invoice to have a company_id property
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        // Mock User and its hasCompany method
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(true)
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->view($user, $invoice);

        expect($result)->toBeTrue();
    });

    test('view returns false if user cannot view specific invoice (bouncer denies)', function () {
        $invoiceCompanyId = 1;

        // Mock BouncerFacade alias to deny
        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('view-invoice', Mockery::type(Invoice::class))
            ->andReturn(false) // Bouncer denies
            ->once();

        // Mock Invoice
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        // Mock User - hasCompany should not be called due to short-circuiting
        $user = Mockery::mock(User::class);
        $user->shouldNotReceive('hasCompany');

        $policy = new InvoicePolicy();
        $result = $policy->view($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('view returns false if user does not have company access for specific invoice', function () {
        $invoiceCompanyId = 1;

        // Mock BouncerFacade alias to allow
        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('view-invoice', Mockery::type(Invoice::class))
            ->andReturn(true) // Bouncer allows
            ->once();

        // Mock Invoice
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        // Mock User to deny company access
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(false) // User denies company access
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->view($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('create returns true if user can create invoice', function () {
        // Mock BouncerFacade alias
        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('create-invoice', Invoice::class)
            ->andReturn(true)
            ->once();

        // User object is required by method signature but not used in logic
        $user = Mockery::mock(User::class);

        $policy = new InvoicePolicy();
        $result = $policy->create($user);

        expect($result)->toBeTrue();
    });

    test('create returns false if user cannot create invoice', function () {
        // Mock BouncerFacade alias
        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('create-invoice', Invoice::class)
            ->andReturn(false)
            ->once();

        // User object is required by method signature but not used in logic
        $user = Mockery::mock(User::class);

        $policy = new InvoicePolicy();
        $result = $policy->create($user);

        expect($result)->toBeFalse();
    });

    test('update returns true if user can edit invoice, has company access, and invoice is editable', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;
        $invoice->allow_edit = true; // Invoice allows editing

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('edit-invoice', Mockery::type(Invoice::class))
            ->andReturn(true)
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(true)
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->update($user, $invoice);

        expect($result)->toBeTrue();
    });

    test('update returns false if user can edit invoice, has company access, but invoice is not editable', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;
        $invoice->allow_edit = false; // Invoice does NOT allow editing

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('edit-invoice', Mockery::type(Invoice::class))
            ->andReturn(true)
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(true)
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->update($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('update returns false if user cannot edit invoice (bouncer denies)', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;
        $invoice->allow_edit = true; // Could be editable, but bouncer denies

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('edit-invoice', Mockery::type(Invoice::class))
            ->andReturn(false) // Bouncer denies
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldNotReceive('hasCompany'); // Short-circuit

        $policy = new InvoicePolicy();
        $result = $policy->update($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('update returns false if user does not have company access for invoice', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;
        $invoice->allow_edit = true;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('edit-invoice', Mockery::type(Invoice::class))
            ->andReturn(true) // Bouncer allows
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(false) // User denies company access
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->update($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('delete returns true if user can delete specific invoice and has company access', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('delete-invoice', Mockery::type(Invoice::class))
            ->andReturn(true)
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(true)
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->delete($user, $invoice);

        expect($result)->toBeTrue();
    });

    test('delete returns false if user cannot delete specific invoice (bouncer denies)', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('delete-invoice', Mockery::type(Invoice::class))
            ->andReturn(false) // Bouncer denies
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldNotReceive('hasCompany'); // Short-circuit

        $policy = new InvoicePolicy();
        $result = $policy->delete($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('delete returns false if user does not have company access for specific invoice', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('delete-invoice', Mockery::type(Invoice::class))
            ->andReturn(true) // Bouncer allows
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(false) // User denies company access
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->delete($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('restore returns true if user can delete/restore specific invoice and has company access', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('delete-invoice', Mockery::type(Invoice::class)) // Note: 'delete-invoice' ability is used
            ->andReturn(true)
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(true)
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->restore($user, $invoice);

        expect($result)->toBeTrue();
    });

    test('restore returns false if user cannot delete/restore specific invoice (bouncer denies)', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('delete-invoice', Mockery::type(Invoice::class))
            ->andReturn(false) // Bouncer denies
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldNotReceive('hasCompany'); // Short-circuit

        $policy = new InvoicePolicy();
        $result = $policy->restore($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('restore returns false if user does not have company access for specific invoice', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('delete-invoice', Mockery::type(Invoice::class))
            ->andReturn(true) // Bouncer allows
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(false) // User denies company access
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->restore($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('forceDelete returns true if user can delete/forceDelete specific invoice and has company access', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('delete-invoice', Mockery::type(Invoice::class)) // Note: 'delete-invoice' ability is used
            ->andReturn(true)
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(true)
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->forceDelete($user, $invoice);

        expect($result)->toBeTrue();
    });

    test('forceDelete returns false if user cannot delete/forceDelete specific invoice (bouncer denies)', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('delete-invoice', Mockery::type(Invoice::class))
            ->andReturn(false) // Bouncer denies
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldNotReceive('hasCompany'); // Short-circuit

        $policy = new InvoicePolicy();
        $result = $policy->forceDelete($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('forceDelete returns false if user does not have company access for specific invoice', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('delete-invoice', Mockery::type(Invoice::class))
            ->andReturn(true) // Bouncer allows
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(false) // User denies company access
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->forceDelete($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('send returns true if user can send specific invoice and has company access', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('send-invoice', Mockery::type(Invoice::class))
            ->andReturn(true)
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(true)
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->send($user, $invoice);

        expect($result)->toBeTrue();
    });

    test('send returns false if user cannot send specific invoice (bouncer denies)', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('send-invoice', Mockery::type(Invoice::class))
            ->andReturn(false) // Bouncer denies
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldNotReceive('hasCompany'); // Short-circuit

        $policy = new InvoicePolicy();
        $result = $policy->send($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('send returns false if user does not have company access for specific invoice', function () {
        $invoiceCompanyId = 1;
        $invoice = Mockery::mock(Invoice::class);
        $invoice->company_id = $invoiceCompanyId;

        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('send-invoice', Mockery::type(Invoice::class))
            ->andReturn(true) // Bouncer allows
            ->once();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasCompany')
            ->with($invoiceCompanyId)
            ->andReturn(false) // User denies company access
            ->once();

        $policy = new InvoicePolicy();
        $result = $policy->send($user, $invoice);

        expect($result)->toBeFalse();
    });

    test('deleteMultiple returns true if user can delete multiple invoices', function () {
        // Mock BouncerFacade alias
        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('delete-invoice', Invoice::class)
            ->andReturn(true)
            ->once();

        // User object is required by method signature but not used in logic
        $user = Mockery::mock(User::class);

        $policy = new InvoicePolicy();
        $result = $policy->deleteMultiple($user);

        expect($result)->toBeTrue();
    });

    test('deleteMultiple returns false if user cannot delete multiple invoices', function () {
        // Mock BouncerFacade alias
        Mockery::mock('alias:Silber\Bouncer\BouncerFacade')
            ->shouldReceive('can')
            ->with('delete-invoice', Invoice::class)
            ->andReturn(false)
            ->once();

        // User object is required by method signature but not used in logic
        $user = Mockery::mock(User::class);

        $policy = new InvoicePolicy();
        $result = $policy->deleteMultiple($user);

        expect($result)->toBeFalse();
    });




afterEach(function () {
    Mockery::close();
});
