<?php

use Crater\Models\Address;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\CustomField;
use Crater\Models\CustomFieldValue;
use Crater\Models\Customer;
use Crater\Models\Estimate;
use Crater\Models\ExchangeRateLog;
use Crater\Models\ExchangeRateProvider;
use Crater\Models\Expense;
use Crater\Models\ExpenseCategory;
use Crater\Models\FileDisk;
use Crater\Models\Invoice;
use Crater\Models\Item;
use Crater\Models\Payment;
use Crater\Models\PaymentMethod;
use Crater\Models\RecurringInvoice;
use Crater\Models\TaxType;
use Crater\Models\Unit;
use Crater\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Mockery as m;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    // Basic setup for the Company model instance
    $this->company = new Company(['id' => 123]);
    $this->company->id = 123; // Ensure ID is set for relationship mocking
});

afterEach(function () {
    m::close();
});

test('getRolesAttribute returns roles scoped to the company', function () {
    $mockRoleQueryBuilder = m::mock();
    $mockRoleQueryBuilder->shouldReceive('where->get')->andReturn(new Collection(['role1', 'role2']));

    m::mock('alias:' . Role::class)
        ->shouldReceive('where')
        ->once()
        ->with('scope', $this->company->id)
        ->andReturn($mockRoleQueryBuilder);

    $roles = $this->company->getRolesAttribute();
    expect($roles)->toBeInstanceOf(Collection::class);
    expect($roles)->toEqual(new Collection(['role1', 'role2']));
});

test('getLogoPathAttribute returns null when no logo exists', function () {
    $this->company = m::mock(Company::class)->makePartial();
    $this->company->shouldReceive('getMedia')->with('logo')->andReturn(new Collection());

    $logoPath = $this->company->getLogoPathAttribute();
    expect($logoPath)->toBeNull();
});

test('getLogoPathAttribute returns getPath when logo exists and FileDisk is system', function () {
    $mockMedia = m::mock(Media::class);
    $mockMedia->shouldReceive('first')->andReturn($mockMedia);
    $mockMedia->shouldReceive('getPath')->andReturn('/storage/logos/1.png');
    $mockMedia->shouldNotReceive('getFullUrl');

    $this->company = m::mock(Company::class)->makePartial();
    $this->company->shouldReceive('getMedia')->with('logo')->andReturn(new Collection([$mockMedia]));

    $mockFileDisk = m::mock(FileDisk::class);
    $mockFileDisk->shouldReceive('whereSetAsDefault->first->isSystem')->andReturn(true);
    m::mock('alias:' . FileDisk::class)->shouldReceive('whereSetAsDefault')->andReturn($mockFileDisk);


    $logoPath = $this->company->getLogoPathAttribute();
    expect($logoPath)->toBe('/storage/logos/1.png');
});

test('getLogoPathAttribute returns getFullUrl when logo exists and FileDisk is not system', function () {
    $mockMedia = m::mock(Media::class);
    $mockMedia->shouldReceive('first')->andReturn($mockMedia);
    $mockMedia->shouldReceive('getFullUrl')->andReturn('http://app.com/storage/logos/1.png');
    $mockMedia->shouldNotReceive('getPath');

    $this->company = m::mock(Company::class)->makePartial();
    $this->company->shouldReceive('getMedia')->with('logo')->andReturn(new Collection([$mockMedia]));

    $mockFileDisk = m::mock(FileDisk::class);
    $mockFileDisk->shouldReceive('whereSetAsDefault->first->isSystem')->andReturn(false);
    m::mock('alias:' . FileDisk::class)->shouldReceive('whereSetAsDefault')->andReturn($mockFileDisk);

    $logoPath = $this->company->getLogoPathAttribute();
    expect($logoPath)->toBe('http://app.com/storage/logos/1.png');
});

test('getLogoAttribute returns null when no logo exists', function () {
    $this->company = m::mock(Company::class)->makePartial();
    $this->company->shouldReceive('getMedia')->with('logo')->andReturn(new Collection());

    $logo = $this->company->getLogoAttribute();
    expect($logo)->toBeNull();
});

test('getLogoAttribute returns full URL when logo exists', function () {
    $mockMedia = m::mock(Media::class);
    $mockMedia->shouldReceive('first')->andReturn($mockMedia);
    $mockMedia->shouldReceive('getFullUrl')->andReturn('http://app.com/media/logo.png');

    $this->company = m::mock(Company::class)->makePartial();
    $this->company->shouldReceive('getMedia')->with('logo')->andReturn(new Collection([$mockMedia]));

    $logo = $this->company->getLogoAttribute();
    expect($logo)->toBe('http://app.com/media/logo.png');
});

test('customers relationship returns a HasMany relationship', function () {
    $relation = $this->company->customers();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Customer::class);
    expect($relation->getForeignKeyName())->toBe('company_id');
});

test('owner relationship returns a BelongsTo relationship', function () {
    $relation = $this->company->owner();
    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getForeignKeyName())->toBe('owner_id');
});

test('settings relationship returns a HasMany relationship', function () {
    $relation = $this->company->settings();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(CompanySetting::class);
});

test('recurringInvoices relationship returns a HasMany relationship', function () {
    $relation = $this->company->recurringInvoices();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(RecurringInvoice::class);
});

test('customFields relationship returns a HasMany relationship', function () {
    $relation = $this->company->customFields();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(CustomField::class);
});

test('customFieldValues relationship returns a HasMany relationship', function () {
    $relation = $this->company->customFieldValues();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(CustomFieldValue::class);
});

test('exchangeRateLogs relationship returns a HasMany relationship', function () {
    $relation = $this->company->exchangeRateLogs();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(ExchangeRateLog::class);
});

test('exchangeRateProviders relationship returns a HasMany relationship', function () {
    $relation = $this->company->exchangeRateProviders();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(ExchangeRateProvider::class);
});

test('invoices relationship returns a HasMany relationship', function () {
    $relation = $this->company->invoices();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Invoice::class);
});

test('expenses relationship returns a HasMany relationship', function () {
    $relation = $this->company->expenses();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Expense::class);
});

test('units relationship returns a HasMany relationship', function () {
    $relation = $this->company->units();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Unit::class);
});

test('expenseCategories relationship returns a HasMany relationship', function () {
    $relation = $this->company->expenseCategories();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(ExpenseCategory::class);
});

test('taxTypes relationship returns a HasMany relationship', function () {
    $relation = $this->company->taxTypes();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(TaxType::class);
});

test('items relationship returns a HasMany relationship', function () {
    $relation = $this->company->items();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Item::class);
});

test('payments relationship returns a HasMany relationship', function () {
    $relation = $this->company->payments();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Payment::class);
});

test('paymentMethods relationship returns a HasMany relationship', function () {
    $relation = $this->company->paymentMethods();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(PaymentMethod::class);
});

test('estimates relationship returns a HasMany relationship', function () {
    $relation = $this->company->estimates();
    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Estimate::class);
});

test('address relationship returns a HasOne relationship', function () {
    $relation = $this->company->address();
    expect($relation)->toBeInstanceOf(HasOne::class);
    expect($relation->getRelated())->toBeInstanceOf(Address::class);
});

test('users relationship returns a BelongsToMany relationship', function () {
    $relation = $this->company->users();
    expect($relation)->toBeInstanceOf(BelongsToMany::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getTable())->toBe('user_company');
    expect($relation->getForeignPivotKeyName())->toBe('company_id');
    expect($relation->getRelatedPivotKeyName())->toBe('user_id');
});

test('setupRoles creates super admin role and assigns abilities', function () {
    $this->company->id = 1; // Ensure company ID is set for BouncerFacade scope

    $mockBouncerFacadeScope = m::mock();
    $mockBouncerFacadeScope->shouldReceive('to')->with($this->company->id)->once();

    $mockBouncerFacadeRole = m::mock();
    $mockSuperAdminRole = m::mock(Role::class);
    $mockBouncerFacadeRole->shouldReceive('firstOrCreate')
        ->with([
            'name' => 'super admin',
            'title' => 'Super Admin',
            'scope' => $this->company->id
        ])
        ->andReturn($mockSuperAdminRole)
        ->once();

    $abilities = [
        ['ability' => 'view_all', 'model' => '*'],
        ['ability' => 'manage_users', 'model' => 'User'],
    ];

    $mockBouncerFacade = m::mock();
    $mockBouncerFacade->shouldReceive('scope')->andReturn($mockBouncerFacadeScope)->once();
    $mockBouncerFacade->shouldReceive('role')->andReturn($mockBouncerFacadeRole)->once();
    $mockBouncerFacade->shouldReceive('allow')->with($mockSuperAdminRole)->once()->ordered('abilities_order');
    $mockBouncerFacade->shouldReceive('allow')->with($mockSuperAdminRole)->once()->ordered('abilities_order');

    BouncerFacade::swap($mockBouncerFacade);

    config(['abilities.abilities' => $abilities]);

    $this->company->setupRoles();
});

test('setupDefaultPaymentMethods creates default payment methods', function () {
    $this->company->id = 1; // Ensure company ID is set

    m::mock('alias:' . PaymentMethod::class)
        ->shouldReceive('create')
        ->once()
        ->with(['name' => 'Cash', 'company_id' => $this->company->id])
        ->andReturn(m::mock(PaymentMethod::class));
    m::mock('alias:' . PaymentMethod::class)
        ->shouldReceive('create')
        ->once()
        ->with(['name' => 'Check', 'company_id' => $this->company->id])
        ->andReturn(m::mock(PaymentMethod::class));
    m::mock('alias:' . PaymentMethod::class)
        ->shouldReceive('create')
        ->once()
        ->with(['name' => 'Credit Card', 'company_id' => $this->company->id])
        ->andReturn(m::mock(PaymentMethod::class));
    m::mock('alias:' . PaymentMethod::class)
        ->shouldReceive('create')
        ->once()
        ->with(['name' => 'Bank Transfer', 'company_id' => $this->company->id])
        ->andReturn(m::mock(PaymentMethod::class));

    $this->company->setupDefaultPaymentMethods();
});

test('setupDefaultUnits creates default units', function () {
    $this->company->id = 1; // Ensure company ID is set

    $expectedUnits = ['box', 'cm', 'dz', 'ft', 'g', 'in', 'kg', 'km', 'lb', 'mg', 'pc'];

    $mockUnitClass = m::mock('alias:' . Unit::class);
    foreach ($expectedUnits as $unitName) {
        $mockUnitClass->shouldReceive('create')
            ->once()
            ->with(['name' => $unitName, 'company_id' => $this->company->id])
            ->andReturn(m::mock(Unit::class));
    }

    $this->company->setupDefaultUnits();
});

test('setupDefaultSettings calls CompanySetting::setSettings with default values and request currency', function () {
    $this->company->id = 1; // Ensure company ID is set

    // Mock the request facade to return a currency
    m::mock('alias:' . Request::class)
        ->shouldReceive('input')
        ->with('currency', 13)
        ->andReturn(99); // Simulate request()->currency returning 99

    $expectedSettings = [
        'invoice_auto_generate' => 'YES',
        'payment_auto_generate' => 'YES',
        'estimate_auto_generate' => 'YES',
        'save_pdf_to_disk' => 'NO',
        'invoice_mail_body' => 'You have received a new invoice from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:',
        'estimate_mail_body' => 'You have received a new estimate from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:',
        'payment_mail_body' => 'Thank you for the payment.</b></br> Please download your payment receipt using the button below:',
        'invoice_company_address_format' => '<h3><strong>{COMPANY_NAME}</strong></h3><p>{COMPANY_ADDRESS_STREET_1}</p><p>{COMPANY_ADDRESS_STREET_2}</p><p>{COMPANY_CITY} {COMPANY_STATE}</p><p>{COMPANY_COUNTRY}  {COMPANY_ZIP_CODE}</p><p>{COMPANY_PHONE}</p>',
        'invoice_shipping_address_format' => '<h3>{SHIPPING_ADDRESS_NAME}</h3><p>{SHIPPING_ADDRESS_STREET_1}</p><p>{SHIPPING_ADDRESS_STREET_2}</p><p>{SHIPPING_CITY}  {SHIPPING_STATE}</p><p>{SHIPPING_COUNTRY}  {SHIPPING_ZIP_CODE}</p><p>{SHIPPING_PHONE}</p>',
        'invoice_billing_address_format' => '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_ADDRESS_STREET_2}</p><p>{BILLING_CITY}  {BILLING_STATE}</p><p>{BILLING_COUNTRY}  {BILLING_ZIP_CODE}</p><p>{BILLING_PHONE}</p>',
        'estimate_company_address_format' => '<h3><strong>{COMPANY_NAME}</strong></h3><p>{COMPANY_ADDRESS_STREET_1}</p><p>{COMPANY_ADDRESS_STREET_2}</p><p>{COMPANY_CITY} {COMPANY_STATE}</p><p>{COMPANY_COUNTRY}  {COMPANY_ZIP_CODE}</p><p>{COMPANY_PHONE}</p>',
        'estimate_shipping_address_format' => '<h3>{SHIPPING_ADDRESS_NAME}</h3><p>{SHIPPING_ADDRESS_STREET_1}</p><p>{SHIPPING_ADDRESS_STREET_2}</p><p>{SHIPPING_CITY}  {SHIPPING_STATE}</p><p>{SHIPPING_COUNTRY}  {SHIPPING_ZIP_CODE}</p><p>{SHIPPING_PHONE}</p>',
        'estimate_billing_address_format' => '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_ADDRESS_STREET_2}</p><p>{BILLING_CITY}  {BILLING_STATE}</p><p>{BILLING_COUNTRY}  {BILLING_ZIP_CODE}</p><p>{BILLING_PHONE}</p>',
        'payment_company_address_format' => '<h3><strong>{COMPANY_NAME}</strong></h3><p>{COMPANY_ADDRESS_STREET_1}</p><p>{COMPANY_ADDRESS_STREET_2}</p><p>{COMPANY_CITY} {COMPANY_STATE}</p><p>{COMPANY_COUNTRY}  {COMPANY_ZIP_CODE}</p><p>{COMPANY_PHONE}</p>',
        'payment_from_customer_address_format' => '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_ADDRESS_STREET_2}</p><p>{BILLING_CITY} {BILLING_STATE} {BILLING_ZIP_CODE}</p><p>{BILLING_COUNTRY}</p><p>{BILLING_PHONE}</p>',
        'currency' => 99, // Should be 99 from the mocked request
        'time_zone' => 'Asia/Kolkata',
        'language' => 'en',
        'fiscal_year' => '1-12',
        'carbon_date_format' => 'Y/m/d',
        'moment_date_format' => 'YYYY/MM/DD',
        'notification_email' => 'noreply@crater.in',
        'notify_invoice_viewed' => 'NO',
        'notify_estimate_viewed' => 'NO',
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'invoice_email_attachment' => 'NO',
        'estimate_email_attachment' => 'NO',
        'payment_email_attachment' => 'NO',
        'retrospective_edits' => 'allow',
        'invoice_number_format' => '{{SERIES:INV}}{{DELIMITER:-}}{{SEQUENCE:6}}',
        'estimate_number_format' => '{{SERIES:EST}}{{DELIMITER:-}}{{SEQUENCE:6}}',
        'payment_number_format' => '{{SERIES:PAY}}{{DELIMITER:-}}{{SEQUENCE:6}}',
        'estimate_set_expiry_date_automatically' => 'YES',
        'estimate_expiry_date_days' => 7,
        'invoice_set_due_date_automatically' => 'YES',
        'invoice_due_date_days' => 7,
        'bulk_exchange_rate_configured' => 'YES',
        'estimate_convert_action' => 'no_action',
        'automatically_expire_public_links' => 'YES',
        'link_expiry_days' => 7,
    ];

    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('setSettings')
        ->once()
        ->with($expectedSettings, $this->company->id);

    // Call the method
    $this->company->setupDefaultSettings();
});

test('setupDefaultData calls all setup methods and returns true', function () {
    $company = m::mock(Company::class)->makePartial();
    $company->id = 1;

    $company->shouldReceive('setupRoles')->once()->ordered();
    $company->shouldReceive('setupDefaultPaymentMethods')->once()->ordered();
    $company->shouldReceive('setupDefaultUnits')->once()->ordered();
    $company->shouldReceive('setupDefaultSettings')->once()->ordered();

    $result = $company->setupDefaultData();
    expect($result)->toBeTrue();
});

test('checkModelData deletes item taxes and items, then model taxes', function () {
    $this->company->id = 1; // Set company ID for relationship methods

    $mockItemTaxes = m::mock();
    $mockItemTaxes->shouldReceive('exists')->andReturn(true)->once();
    $mockItemTaxes->shouldReceive('delete')->once();

    $mockItem = m::mock();
    $mockItem->shouldReceive('taxes')->andReturn($mockItemTaxes)->once();
    $mockItem->shouldReceive('delete')->once();

    $mockModelItems = new Collection([$mockItem]);

    $mockModelTaxes = m::mock();
    $mockModelTaxes->shouldReceive('exists')->andReturn(true)->once();
    $mockModelTaxes->shouldReceive('delete')->once();

    $mockModel = m::mock();
    $mockModel->items = $mockModelItems;
    $mockModel->shouldReceive('taxes')->andReturn($mockModelTaxes)->once();

    // Use reflection to call the protected method
    $method = new ReflectionMethod(Company::class, 'checkModelData');
    $method->setAccessible(true);
    $method->invoke($this->company, $mockModel);
});

test('checkModelData handles no item taxes and no model taxes gracefully', function () {
    $this->company->id = 1; // Set company ID for relationship methods

    $mockItemTaxes = m::mock();
    $mockItemTaxes->shouldReceive('exists')->andReturn(false)->once();
    $mockItemTaxes->shouldNotReceive('delete');

    $mockItem = m::mock();
    $mockItem->shouldReceive('taxes')->andReturn($mockItemTaxes)->once();
    $mockItem->shouldReceive('delete')->once();

    $mockModelItems = new Collection([$mockItem]);

    $mockModelTaxes = m::mock();
    $mockModelTaxes->shouldReceive('exists')->andReturn(false)->once();
    $mockModelTaxes->shouldNotReceive('delete');

    $mockModel = m::mock();
    $mockModel->items = $mockModelItems;
    $mockModel->shouldReceive('taxes')->andReturn($mockModelTaxes)->once();

    // Use reflection to call the protected method
    $method = new ReflectionMethod(Company::class, 'checkModelData');
    $method->setAccessible(true);
    $method->invoke($this->company, $mockModel);
});

test('deleteCompany deletes all related data when it exists', function () {
    $user = m::mock(User::class);
    $userCompaniesRelation = m::mock();

    $user->shouldReceive('companies')->andReturn($userCompaniesRelation);
    $userCompaniesRelation->shouldReceive('detach')->with($this->company->id)->once();

    // Mock company-level relationships
    $relations = [
        'exchangeRateLogs', 'exchangeRateProviders', 'expenses', 'expenseCategories', 'payments', 'paymentMethods',
        'customFieldValues', 'customFields', 'invoices', 'recurringInvoices', 'estimates', 'items', 'taxTypes',
        'customers', 'settings', 'address', 'users' // 'users' is handled differently for detach
    ];

    foreach ($relations as $relationName) {
        if (in_array($relationName, ['invoices', 'recurringInvoices', 'estimates', 'customers'])) {
            // These relationships iterate through models
            $mockCollection = new Collection([m::mock(Eloquent\Model::class)]);
            $mockRelation = m::mock();
            $this->company->shouldReceive($relationName)->andReturn($mockRelation);
            $mockRelation->shouldReceive('exists')->andReturn(true)->once();
            $this->company->{$relationName} = $mockCollection; // Set the public property for map

            if ($relationName === 'invoices') {
                $mockCollection[0]->shouldReceive('transactions->exists')->andReturn(true)->once();
                $mockCollection[0]->shouldReceive('transactions->delete')->once();
            } elseif ($relationName === 'customers') {
                $mockCollection[0]->shouldReceive('addresses->exists')->andReturn(true)->once();
                $mockCollection[0]->shouldReceive('addresses->delete')->once();
                $mockCollection[0]->shouldReceive('delete')->once();
            }

            $mockRelation->shouldReceive('delete')->once();
        } else {
            $mockRelation = m::mock();
            $this->company->shouldReceive($relationName)->andReturn($mockRelation);
            $mockRelation->shouldReceive('exists')->andReturn(true)->once();
            $mockRelation->shouldReceive('delete')->once();
        }
    }

    // Mock checkModelData for invoice, recurringInvoice, estimate iterations
    $this->company = m::mock(Company::class)->makePartial();
    $this->company->id = 123;
    $this->company->shouldAllowMockingProtectedMethods()->makePartial();
    $this->company->shouldReceive('checkModelData')->zeroOrMoreTimes(); // Will be called by map

    // Roles handling
    $mockRoleQueryBuilder = m::mock();
    $mockRole = m::mock(Role::class);
    $mockRole->shouldReceive('delete')->once();
    $mockRoleQueryBuilder->shouldReceive('when->get')->andReturn(new Collection([$mockRole]));
    m::mock('alias:' . Role::class)->shouldReceive('when')->andReturn($mockRoleQueryBuilder);


    // Finally, mock the company's own delete
    $this->company->shouldReceive('delete')->once();

    // Ensure all mocks for relationships are present
    foreach ($relations as $relationName) {
        if ($relationName === 'users') { // Special case handled above
            $this->company->shouldReceive($relationName)->andReturn($userCompaniesRelation);
        } else if (!in_array($relationName, ['invoices', 'recurringInvoices', 'estimates', 'customers'])) {
            $mockRelation = m::mock();
            $mockRelation->shouldReceive('exists')->andReturn(true);
            $mockRelation->shouldReceive('delete')->once();
            $this->company->shouldReceive($relationName)->andReturn($mockRelation);
        }
    }

    $result = $this->company->deleteCompany($user);
    expect($result)->toBeTrue();
});

test('deleteCompany handles no related data existing', function () {
    $user = m::mock(User::class);
    $userCompaniesRelation = m::mock();

    // No detach should be called if users relation doesn't exist or is empty
    $user->shouldReceive('companies')->andReturn($userCompaniesRelation);
    $userCompaniesRelation->shouldReceive('detach')->never();

    // Mock all relationships to return false for exists()
    $relations = [
        'exchangeRateLogs', 'exchangeRateProviders', 'expenses', 'expenseCategories', 'payments', 'paymentMethods',
        'customFieldValues', 'customFields', 'invoices', 'recurringInvoices', 'estimates', 'items', 'taxTypes',
        'customers', 'settings', 'address', 'users'
    ];

    foreach ($relations as $relationName) {
        $mockRelation = m::mock();
        $this->company->shouldReceive($relationName)->andReturn($mockRelation);
        $mockRelation->shouldReceive('exists')->andReturn(false)->once();
        $mockRelation->shouldNotReceive('delete'); // Ensure delete is not called

        // For iterative relationships, ensure collection is empty
        if (in_array($relationName, ['invoices', 'recurringInvoices', 'estimates', 'customers'])) {
            $this->company->{$relationName} = new Collection();
        }
    }

    // Mock checkModelData for invoice, recurringInvoice, estimate iterations
    $this->company = m::mock(Company::class)->makePartial();
    $this->company->id = 123;
    $this->company->shouldAllowMockingProtectedMethods()->makePartial();
    $this->company->shouldReceive('checkModelData')->never();

    // Roles handling - mock no roles exist
    $mockRoleQueryBuilder = m::mock();
    $mockRoleQueryBuilder->shouldReceive('when->get')->andReturn(new Collection());
    m::mock('alias:' . Role::class)->shouldReceive('when')->andReturn($mockRoleQueryBuilder);


    // Mock the company's own delete
    $this->company->shouldReceive('delete')->once();

    $result = $this->company->deleteCompany($user);
    expect($result)->toBeTrue();
});

test('hasTransactions returns true if any transactional relationship exists', function () {
    $this->company = m::mock(Company::class)->makePartial();
    $this->company->id = 1;

    $relationships = ['customers', 'items', 'invoices', 'estimates', 'expenses', 'payments', 'recurringInvoices'];

    foreach ($relationships as $relation) {
        // Reset mocks for each iteration
        m::close();
        $this->company = m::mock(Company::class)->makePartial();
        $this->company->id = 1;

        // Mock all relationships to return false for exists() initially
        foreach ($relationships as $r) {
            $mockRelation = m::mock();
            $mockRelation->shouldReceive('exists')->andReturn($r === $relation)->once();
            $this->company->shouldReceive($r)->andReturn($mockRelation);
        }

        expect($this->company->hasTransactions())->toBeTrue();
    }
});

test('hasTransactions returns false if no transactional relationship exists', function () {
    $this->company = m::mock(Company::class)->makePartial();
    $this->company->id = 1;

    $relationships = ['customers', 'items', 'invoices', 'estimates', 'expenses', 'payments', 'recurringInvoices'];

    // Mock all relationships to return false for exists()
    foreach ($relationships as $relation) {
        $mockRelation = m::mock();
        $mockRelation->shouldReceive('exists')->andReturn(false)->once();
        $this->company->shouldReceive($relation)->andReturn($mockRelation);
    }

    expect($this->company->hasTransactions())->toBeFalse();
});
 
