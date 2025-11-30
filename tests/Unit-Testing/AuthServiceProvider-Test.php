<?php

use Crater\Providers\AuthServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
uses(\Mockery::class);

beforeEach(function () {
    Mockery::close(); // Ensure a clean slate for mocks

    // Mock the Gate facade
    $this->gateMock = Mockery::mock('alias:' . Gate::class);

    // Create an instance of the service provider, passing a mock Application
    // The parent ServiceProvider expects an Application instance.
    $this->appMock = Mockery::mock(Application::class);
    $this->provider = new AuthServiceProvider($this->appMock);
});

afterEach(function () {
    Mockery::close(); // Clean up mocks after each test
});

test('it registers policies and defines all abilities correctly in the boot method', function () {
    // --- Expectations for Gate::policy (from registerPolicies() method call) ---
    // The registerPolicies() method (from parent ServiceProvider) iterates over the $policies array
    // and calls Gate::policy() for each entry.
    // We use reflection to access the protected $policies property of the provider.
    $reflectionClass = new ReflectionClass(AuthServiceProvider::class);
    $policiesProperty = $reflectionClass->getProperty('policies');
    $policiesProperty->setAccessible(true);
    $policies = $policiesProperty->getValue($this->provider);

    foreach ($policies as $model => $policy) {
        $this->gateMock->shouldReceive('policy')
            ->once()
            ->with($model, $policy)
            ->andReturn(null); // Gate::policy doesn't return anything significant for this test
    }

    // --- Expectations for Gate::define (explicit calls in boot method) ---

    // Company Policies
    $this->gateMock->shouldReceive('define')->once()->with('create company', [\Crater\Policies\CompanyPolicy::class, 'create']);
    $this->gateMock->shouldReceive('define')->once()->with('transfer company ownership', [\Crater\Policies\CompanyPolicy::class, 'transferOwnership']);
    $this->gateMock->shouldReceive('define')->once()->with('delete company', [\Crater\Policies\CompanyPolicy::class, 'delete']);

    // Modules Policy
    $this->gateMock->shouldReceive('define')->once()->with('manage modules', [\Crater\Policies\ModulesPolicy::class, 'manageModules']);

    // Settings Policies
    $this->gateMock->shouldReceive('define')->once()->with('manage settings', [\Crater\Policies\SettingsPolicy::class, 'manageSettings']);
    $this->gateMock->shouldReceive('define')->once()->with('manage company', [\Crater\Policies\SettingsPolicy::class, 'manageCompany']);
    $this->gateMock->shouldReceive('define')->once()->with('manage backups', [\Crater\Policies\SettingsPolicy::class, 'manageBackups']);
    $this->gateMock->shouldReceive('define')->once()->with('manage file disk', [\Crater\Policies\SettingsPolicy::class, 'manageFileDisk']);
    $this->gateMock->shouldReceive('define')->once()->with('manage email config', [\Crater\Policies\SettingsPolicy::class, 'manageEmailConfig']);

    // Note Policies
    $this->gateMock->shouldReceive('define')->once()->with('manage notes', [\Crater\Policies\NotePolicy::class, 'manageNotes']);
    $this->gateMock->shouldReceive('define')->once()->with('view notes', [\Crater\Policies\NotePolicy::class, 'viewNotes']);

    // Send Policies
    $this->gateMock->shouldReceive('define')->once()->with('send invoice', [\Crater\Policies\InvoicePolicy::class, 'send']);
    $this->gateMock->shouldReceive('define')->once()->with('send estimate', [\Crater\Policies\EstimatePolicy::class, 'send']);
    $this->gateMock->shouldReceive('define')->once()->with('send payment', [\Crater\Policies\PaymentPolicy::class, 'send']);

    // Delete Multiple Policies
    $this->gateMock->shouldReceive('define')->once()->with('delete multiple items', [\Crater\Policies\ItemPolicy::class, 'deleteMultiple']);
    $this->gateMock->shouldReceive('define')->once()->with('delete multiple customers', [\Crater\Policies\CustomerPolicy::class, 'deleteMultiple']);
    $this->gateMock->shouldReceive('define')->once()->with('delete multiple users', [\Crater\Policies\UserPolicy::class, 'deleteMultiple']);
    $this->gateMock->shouldReceive('define')->once()->with('delete multiple invoices', [\Crater\Policies\InvoicePolicy::class, 'deleteMultiple']);
    $this->gateMock->shouldReceive('define')->once()->with('delete multiple estimates', [\Crater\Policies\EstimatePolicy::class, 'deleteMultiple']);
    $this->gateMock->shouldReceive('define')->once()->with('delete multiple expenses', [\Crater\Policies\ExpensePolicy::class, 'deleteMultiple']);
    $this->gateMock->shouldReceive('define')->once()->with('delete multiple payments', [\Crater\Policies\PaymentPolicy::class, 'deleteMultiple']);
    $this->gateMock->shouldReceive('define')->once()->with('delete multiple recurring invoices', [\Crater\Policies\RecurringInvoicePolicy::class, 'deleteMultiple']);

    // Dashboard Policy
    $this->gateMock->shouldReceive('define')->once()->with('view dashboard', [\Crater\Policies\DashboardPolicy::class, 'view']);

    // Report Policy
    $this->gateMock->shouldReceive('define')->once()->with('view report', [\Crater\Policies\ReportPolicy::class, 'viewReport']);

    // Owner Policy
    $this->gateMock->shouldReceive('define')->once()->with('owner only', [\Crater\Policies\OwnerPolicy::class, 'managedByOwner']);

    // --- Execute the method under test ---
    $this->provider->boot();
});
