<?php
use Carbon\Carbon;
use Crater\Models\Address;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\Estimate;
use Crater\Models\Expense;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Models\RecurringInvoice;
use Crater\Notifications\CustomerMailResetPasswordNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
uses(\Mockery::class);
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Mockery::close();
});

test('customer has expected fillable attributes', function () {
    $customer = new Customer();
    $guarded = (new \ReflectionClass($customer))->getProperty('guarded')->getValue($customer);
    expect($guarded)->toBe(['id']);
});

test('customer has expected hidden attributes', function () {
    $customer = new Customer();
    $hidden = (new \ReflectionClass($customer))->getProperty('hidden')->getValue($customer);
    expect($hidden)->toBe(['password', 'remember_token']);
});

test('customer has expected with relationships', function () {
    $customer = new Customer();
    $with = (new \ReflectionClass($customer))->getProperty('with')->getValue($customer);
    expect($with)->toBe(['currency']);
});

test('customer has expected appended attributes', function () {
    $customer = new Customer();
    $appends = (new \ReflectionClass($customer))->getProperty('appends')->getValue($customer);
    expect($appends)->toBe(['formattedCreatedAt', 'avatar']);
});

test('customer has expected cast attributes', function () {
    $customer = new Customer();
    $casts = (new \ReflectionClass($customer))->getProperty('casts')->getValue($customer);
    expect($casts)->toBe(['enable_portal' => 'boolean']);
});

test('getFormattedCreatedAtAttribute returns correctly formatted date', function () {
    $mockCarbon = Mockery::mock('overload:' . Carbon::class);
    $mockCarbon->shouldReceive('parse')->once()->andReturnSelf();
    $mockCarbon->shouldReceive('format')->with('Y-m-d H:i:s')->once()->andReturn('2023-01-01 10:00:00');

    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', 1)
        ->once()
        ->andReturn('Y-m-d H:i:s');

    $customer = new Customer(['company_id' => 1, 'created_at' => '2023-01-01 10:00:00']);
    $customer->exists = true;

    expect($customer->formattedCreatedAt)->toBe('2023-01-01 10:00:00');
});

test('setPasswordAttribute sets password when value is not null', function () {
    $customer = new Customer();
    $password = 'secretpassword';

    $customer->setPasswordAttribute($password);

    expect($customer->attributes['password'])->not->toBe($password);
    expect($customer->attributes['password'])->toMatch('/^\$2y\$/');
});

test('setPasswordAttribute does not set password when value is null', function () {
    $customer = new Customer();
    $customer->attributes['password'] = 'existing_hash';

    $customer->setPasswordAttribute(null);

    expect($customer->attributes['password'])->toBe('existing_hash');
});

test('estimates relationship returns hasMany', function () {
    $customer = new Customer();
    $relation = $customer->estimates();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Estimate::class);
    expect($relation->getForeignKeyName())->toBe('customer_id');
});

test('expenses relationship returns hasMany', function () {
    $customer = new Customer();
    $relation = $customer->expenses();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Expense::class);
    expect($relation->getForeignKeyName())->toBe('customer_id');
});

test('invoices relationship returns hasMany', function () {
    $customer = new Customer();
    $relation = $customer->invoices();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Invoice::class);
    expect($relation->getForeignKeyName())->toBe('customer_id');
});

test('payments relationship returns hasMany', function () {
    $customer = new Customer();
    $relation = $customer->payments();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Payment::class);
    expect($relation->getForeignKeyName())->toBe('customer_id');
});

test('addresses relationship returns hasMany', function () {
    $customer = new Customer();
    $relation = $customer->addresses();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Address::class);
    expect($relation->getForeignKeyName())->toBe('customer_id');
});

test('recurringInvoices relationship returns hasMany', function () {
    $customer = new Customer();
    $relation = $customer->recurringInvoices();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(RecurringInvoice::class);
    expect($relation->getForeignKeyName())->toBe('customer_id');
});

test('currency relationship returns belongsTo', function () {
    $customer = new Customer();
    $relation = $customer->currency();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Currency::class);
    expect($relation->getForeignKeyName())->toBe('currency_id');
});

test('creator relationship returns belongsTo', function () {
    $customer = new Customer();
    $relation = $customer->creator();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Customer::class);
    expect($relation->getForeignKeyName())->toBe('creator_id');
});

test('company relationship returns belongsTo', function () {
    $customer = new Customer();
    $relation = $customer->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
    expect($relation->getForeignKeyName())->toBe('company_id');
});

test('billingAddress relationship returns hasOne with correct type', function () {
    $customer = new Customer();
    $relation = $customer->billingAddress();

    expect($relation)->toBeInstanceOf(HasOne::class);
    expect($relation->getRelated())->toBeInstanceOf(Address::class);
    expect($relation->getQuery()->getQuery()->wheres)->toContain(
        ['column' => 'type', 'operator' => '=', 'value' => Address::BILLING_TYPE]
    );
});

test('shippingAddress relationship returns hasOne with correct type', function () {
    $customer = new Customer();
    $relation = $customer->shippingAddress();

    expect($relation)->toBeInstanceOf(HasOne::class);
    expect($relation->getRelated())->toBeInstanceOf(Address::class);
    expect($relation->getQuery()->getQuery()->wheres)->toContain(
        ['column' => 'type', 'operator' => '=', 'value' => Address::SHIPPING_TYPE]
    );
});

test('sendPasswordResetNotification sends customer mail reset password notification', function () {
    Notification::fake();

    $customer = Customer::factory()->make();
    $token = 'some-reset-token';

    $customer->sendPasswordResetNotification($token);

    Notification::assertSentTo($customer, CustomerMailResetPasswordNotification::class, function ($notification) use ($token) {
        return $notification->token === $token;
    });
});

test('getAvatarAttribute returns asset url if avatar exists', function () {
    $customer = Mockery::mock(Customer::class)->makePartial();
    $customer->id = 1;

    $mediaCollection = new Collection();
    $mockMedia = Mockery::mock(Media::class);
    $mockMedia->shouldReceive('getUrl')->once()->andReturn('media/customer_avatar_1.jpg');
    $mediaCollection->add($mockMedia);

    $customer->shouldReceive('getMedia')->with('customer_avatar')->once()->andReturn($mediaCollection);

    URL::shouldReceive('asset')->with('media/customer_avatar_1.jpg')->once()->andReturn('http://localhost/media/customer_avatar_1.jpg');

    expect($customer->getAvatarAttribute())->toBe('http://localhost/media/customer_avatar_1.jpg');
});

test('getAvatarAttribute returns 0 if no avatar exists', function () {
    $customer = Mockery::mock(Customer::class)->makePartial();
    $customer->id = 1;

    $customer->shouldReceive('getMedia')->with('customer_avatar')->once()->andReturn(new Collection());

    expect($customer->getAvatarAttribute())->toBe(0);
});

test('deleteCustomers deletes customer and all related records', function () {
    $customerIds = [1, 2];

    $customer1 = Mockery::mock(Customer::class)->makePartial();
    $customer1->id = 1;
    $customer2 = Mockery::mock(Customer::class)->makePartial();
    $customer2->id = 2;

    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with(1)
        ->andReturn($customer1)
        ->once();
    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with(2)
        ->andReturn($customer2)
        ->once();

    $mockEstimateQuery1 = Mockery::mock(Builder::class);
    $mockEstimateQuery1->shouldReceive('exists')->andReturn(true)->once();
    $mockEstimateQuery1->shouldReceive('delete')->andReturn(true)->once();
    $customer1->shouldReceive('estimates')->andReturn($mockEstimateQuery1)->once();

    $mockInvoiceQuery1 = Mockery::mock(Builder::class);
    $mockInvoiceQuery1->shouldReceive('exists')->andReturn(true)->once();
    $mockInvoiceQuery1->shouldReceive('delete')->andReturn(true)->once();
    $customer1->shouldReceive('invoices')->andReturn($mockInvoiceQuery1)->once();
    $mockInvoice1 = Mockery::mock(Invoice::class);
    $mockInvoice1->shouldReceive('delete')->once();
    $mockTransactionQuery1 = Mockery::mock(Builder::class);
    $mockTransactionQuery1->shouldReceive('exists')->andReturn(true)->once();
    $mockTransactionQuery1->shouldReceive('delete')->andReturn(true)->once();
    $mockInvoice1->shouldReceive('transactions')->andReturn($mockTransactionQuery1)->once();
    $customer1->invoices = collect([$mockInvoice1]);

    $mockPaymentQuery1 = Mockery::mock(Builder::class);
    $mockPaymentQuery1->shouldReceive('exists')->andReturn(true)->once();
    $mockPaymentQuery1->shouldReceive('delete')->andReturn(true)->once();
    $customer1->shouldReceive('payments')->andReturn($mockPaymentQuery1)->once();

    $mockAddressQuery1 = Mockery::mock(Builder::class);
    $mockAddressQuery1->shouldReceive('exists')->andReturn(true)->once();
    $mockAddressQuery1->shouldReceive('delete')->andReturn(true)->once();
    $customer1->shouldReceive('addresses')->andReturn($mockAddressQuery1)->once();

    $mockExpenseQuery1 = Mockery::mock(Builder::class);
    $mockExpenseQuery1->shouldReceive('exists')->andReturn(true)->once();
    $mockExpenseQuery1->shouldReceive('delete')->andReturn(true)->once();
    $customer1->shouldReceive('expenses')->andReturn($mockExpenseQuery1)->once();

    $mockRecurringInvoiceQuery1 = Mockery::mock(Builder::class);
    $mockRecurringInvoiceQuery1->shouldReceive('exists')->andReturn(true)->once();
    $customer1->shouldReceive('recurringInvoices')->andReturn($mockRecurringInvoiceQuery1)->once();
    $mockRecurringInvoice1 = Mockery::mock(RecurringInvoice::class);
    $mockRecurringInvoice1->shouldReceive('delete')->once();
    $mockItemQuery1 = Mockery::mock(Builder::class);
    $mockItemQuery1->shouldReceive('exists')->andReturn(true)->once();
    $mockItemQuery1->shouldReceive('delete')->andReturn(true)->once();
    $mockRecurringInvoice1->shouldReceive('items')->andReturn($mockItemQuery1)->once();
    $customer1->recurringInvoices = collect([$mockRecurringInvoice1]);

    $customer1->shouldReceive('delete')->andReturn(true)->once();

    $mockEstimateQuery2 = Mockery::mock(Builder::class);
    $mockEstimateQuery2->shouldReceive('exists')->andReturn(false)->once();
    $customer2->shouldReceive('estimates')->andReturn($mockEstimateQuery2)->once();

    $mockInvoiceQuery2 = Mockery::mock(Builder::class);
    $mockInvoiceQuery2->shouldReceive('exists')->andReturn(false)->once();
    $customer2->shouldReceive('invoices')->andReturn($mockInvoiceQuery2)->once();
    $customer2->invoices = collect([]);

    $mockPaymentQuery2 = Mockery::mock(Builder::class);
    $mockPaymentQuery2->shouldReceive('exists')->andReturn(false)->once();
    $customer2->shouldReceive('payments')->andReturn($mockPaymentQuery2)->once();

    $mockAddressQuery2 = Mockery::mock(Builder::class);
    $mockAddressQuery2->shouldReceive('exists')->andReturn(false)->once();
    $customer2->shouldReceive('addresses')->andReturn($mockAddressQuery2)->once();

    $mockExpenseQuery2 = Mockery::mock(Builder::class);
    $mockExpenseQuery2->shouldReceive('exists')->andReturn(false)->once();
    $customer2->shouldReceive('expenses')->andReturn($mockExpenseQuery2)->once();

    $mockRecurringInvoiceQuery2 = Mockery::mock(Builder::class);
    $mockRecurringInvoiceQuery2->shouldReceive('exists')->andReturn(false)->once();
    $customer2->shouldReceive('recurringInvoices')->andReturn($mockRecurringInvoiceQuery2)->once();
    $customer2->recurringInvoices = collect([]);

    $customer2->shouldReceive('delete')->andReturn(true)->once();

    $result = Customer::deleteCustomers($customerIds);

    expect($result)->toBeTrue();
});

test('createCustomer creates customer with no addresses or custom fields', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('getCustomerPayload')->once()->andReturn(['name' => 'Test Customer']);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(null)->once();
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(null)->once();
    $mockRequest->shouldReceive('offsetGet')->with('customFields')->andReturn(null)->once();

    $createdCustomer = Mockery::mock(Customer::class)->makePartial();
    $createdCustomer->id = 1;
    $createdCustomer->name = 'Test Customer';
    $createdCustomer->shouldReceive('addresses')->never();
    $createdCustomer->shouldReceive('addCustomFields')->never();

    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('create')
        ->with(['name' => 'Test Customer'])
        ->andReturn($createdCustomer)
        ->once();

    $mockedQuery = Mockery::mock(Builder::class);
    $mockedQuery->shouldReceive('find')->with(1)->andReturn($createdCustomer)->once();
    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('with')
        ->with('billingAddress', 'shippingAddress', 'fields')
        ->andReturn($mockedQuery)
        ->once();

    $customer = Customer::createCustomer($mockRequest);

    expect($customer)->toBe($createdCustomer);
});

test('createCustomer creates customer with shipping address', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('getCustomerPayload')->once()->andReturn(['name' => 'Test Customer']);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(['street' => '123 Shipping'])->once();
    $mockRequest->shouldReceive('hasAddress')->with(['street' => '123 Shipping'])->andReturn(true)->once();
    $mockRequest->shouldReceive('getShippingAddress')->andReturn(['street' => '123 Shipping', 'type' => Address::SHIPPING_TYPE])->once();
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(null)->once();
    $mockRequest->shouldReceive('offsetGet')->with('customFields')->andReturn(null)->once();

    $createdCustomer = Mockery::mock(Customer::class)->makePartial();
    $createdCustomer->id = 1;
    $createdCustomer->name = 'Test Customer';

    $mockAddressesRelation = Mockery::mock(HasMany::class);
    $mockAddressesRelation->shouldReceive('create')->with(['street' => '123 Shipping', 'type' => Address::SHIPPING_TYPE])->once()->andReturn(Mockery::mock(Address::class));
    $createdCustomer->shouldReceive('addresses')->andReturn($mockAddressesRelation)->once();
    $createdCustomer->shouldReceive('addCustomFields')->never();

    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('create')
        ->with(['name' => 'Test Customer'])
        ->andReturn($createdCustomer)
        ->once();

    $mockedQuery = Mockery::mock(Builder::class);
    $mockedQuery->shouldReceive('find')->with(1)->andReturn($createdCustomer)->once();
    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('with')
        ->with('billingAddress', 'shippingAddress', 'fields')
        ->andReturn($mockedQuery)
        ->once();

    $customer = Customer::createCustomer($mockRequest);

    expect($customer)->toBe($createdCustomer);
});

test('createCustomer creates customer with billing address', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('getCustomerPayload')->once()->andReturn(['name' => 'Test Customer']);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(null)->once();
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(['street' => '456 Billing'])->once();
    $mockRequest->shouldReceive('hasAddress')->with(['street' => '456 Billing'])->andReturn(true)->once();
    $mockRequest->shouldReceive('getBillingAddress')->andReturn(['street' => '456 Billing', 'type' => Address::BILLING_TYPE])->once();
    $mockRequest->shouldReceive('offsetGet')->with('customFields')->andReturn(null)->once();

    $createdCustomer = Mockery::mock(Customer::class)->makePartial();
    $createdCustomer->id = 1;
    $createdCustomer->name = 'Test Customer';

    $mockAddressesRelation = Mockery::mock(HasMany::class);
    $mockAddressesRelation->shouldReceive('create')->with(['street' => '456 Billing', 'type' => Address::BILLING_TYPE])->once()->andReturn(Mockery::mock(Address::class));
    $createdCustomer->shouldReceive('addresses')->andReturn($mockAddressesRelation)->once();
    $createdCustomer->shouldReceive('addCustomFields')->never();

    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('create')
        ->with(['name' => 'Test Customer'])
        ->andReturn($createdCustomer)
        ->once();

    $mockedQuery = Mockery::mock(Builder::class);
    $mockedQuery->shouldReceive('find')->with(1)->andReturn($createdCustomer)->once();
    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('with')
        ->with('billingAddress', 'shippingAddress', 'fields')
        ->andReturn($mockedQuery)
        ->once();

    $customer = Customer::createCustomer($mockRequest);

    expect($customer)->toBe($createdCustomer);
});

test('createCustomer creates customer with custom fields', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('getCustomerPayload')->once()->andReturn(['name' => 'Test Customer']);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(null)->once();
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(null)->once();
    $customFields = ['field1' => 'value1'];
    $mockRequest->shouldReceive('offsetGet')->with('customFields')->andReturn($customFields)->once();

    $createdCustomer = Mockery::mock(Customer::class)->makePartial();
    $createdCustomer->id = 1;
    $createdCustomer->name = 'Test Customer';
    $createdCustomer->shouldReceive('addresses')->never();
    $createdCustomer->shouldReceive('addCustomFields')->with($customFields)->once();

    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('create')
        ->with(['name' => 'Test Customer'])
        ->andReturn($createdCustomer)
        ->once();

    $mockedQuery = Mockery::mock(Builder::class);
    $mockedQuery->shouldReceive('find')->with(1)->andReturn($createdCustomer)->once();
    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('with')
        ->with('billingAddress', 'shippingAddress', 'fields')
        ->andReturn($mockedQuery)
        ->once();

    $customer = Customer::createCustomer($mockRequest);

    expect($customer)->toBe($createdCustomer);
});

test('updateCustomer returns currency error if currency changed and related records exist', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('offsetGet')->with('currency_id')->andReturn(2)->once();

    $customer = Mockery::mock(Customer::class)->makePartial();
    $customer->currency_id = 1;
    $customer->id = 1;

    $mockEstimateQuery = Mockery::mock(Builder::class);
    $mockEstimateQuery->shouldReceive('exists')->andReturn(true)->once();
    $customer->shouldReceive('estimates')->andReturn($mockEstimateQuery)->once();

    $result = Customer::updateCustomer($mockRequest, $customer);

    expect($result)->toBe('you_cannot_edit_currency');
});

test('updateCustomer updates customer with no address or custom fields changes', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('offsetGet')->with('currency_id')->andReturn(1)->once();
    $mockRequest->shouldReceive('getCustomerPayload')->andReturn(['name' => 'Updated Name'])->once();
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(null)->once();
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(null)->once();
    $mockRequest->shouldReceive('offsetGet')->with('customFields')->andReturn(null)->once();

    $customer = Mockery::mock(Customer::class)->makePartial();
    $customer->currency_id = 1;
    $customer->id = 1;
    $customer->name = 'Old Name';

    $customer->shouldReceive('estimates->exists')->andReturn(false)->once();
    $customer->shouldReceive('invoices->exists')->andReturn(false)->once();
    $customer->shouldReceive('payments->exists')->andReturn(false)->once();
    $customer->shouldReceive('recurringInvoices->exists')->andReturn(false)->once();

    $customer->shouldReceive('update')->with(['name' => 'Updated Name'])->once();

    $mockAddressesRelation = Mockery::mock(HasMany::class);
    $mockAddressesRelation->shouldReceive('delete')->once();
    $customer->shouldReceive('addresses')->andReturn($mockAddressesRelation)->once();

    $customer->shouldReceive('updateCustomFields')->never();

    $mockedQuery = Mockery::mock(Builder::class);
    $mockedQuery->shouldReceive('find')->with(1)->andReturn($customer)->once();
    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('with')
        ->with('billingAddress', 'shippingAddress', 'fields')
        ->andReturn($mockedQuery)
        ->once();

    $updatedCustomer = Customer::updateCustomer($mockRequest, $customer);

    expect($updatedCustomer)->toBe($customer);
    expect($customer->name)->toBe('Updated Name');
});

test('updateCustomer updates customer with new shipping and billing addresses', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('offsetGet')->with('currency_id')->andReturn(1)->once();
    $mockRequest->shouldReceive('getCustomerPayload')->andReturn(['name' => 'Updated Name'])->once();

    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(['street' => 'New Shipping'])->once();
    $mockRequest->shouldReceive('hasAddress')->with(['street' => 'New Shipping'])->andReturn(true)->once();
    $mockRequest->shouldReceive('getShippingAddress')->andReturn(['street' => 'New Shipping', 'type' => Address::SHIPPING_TYPE])->once();

    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(['street' => 'New Billing'])->once();
    $mockRequest->shouldReceive('hasAddress')->with(['street' => 'New Billing'])->andReturn(true)->once();
    $mockRequest->shouldReceive('getBillingAddress')->andReturn(['street' => 'New Billing', 'type' => Address::BILLING_TYPE])->once();

    $mockRequest->shouldReceive('offsetGet')->with('customFields')->andReturn(null)->once();

    $customer = Mockery::mock(Customer::class)->makePartial();
    $customer->currency_id = 1;
    $customer->id = 1;
    $customer->name = 'Old Name';

    $customer->shouldReceive('estimates->exists')->andReturn(false)->once();
    $customer->shouldReceive('invoices->exists')->andReturn(false)->once();
    $customer->shouldReceive('payments->exists')->andReturn(false)->once();
    $customer->shouldReceive('recurringInvoices->exists')->andReturn(false)->once();

    $customer->shouldReceive('update')->with(['name' => 'Updated Name'])->once();

    $mockAddressesRelation = Mockery::mock(HasMany::class);
    $mockAddressesRelation->shouldReceive('delete')->once();
    $mockAddressesRelation->shouldReceive('create')->with(['street' => 'New Shipping', 'type' => Address::SHIPPING_TYPE])->once()->andReturn(Mockery::mock(Address::class));
    $mockAddressesRelation->shouldReceive('create')->with(['street' => 'New Billing', 'type' => Address::BILLING_TYPE])->once()->andReturn(Mockery::mock(Address::class));
    $customer->shouldReceive('addresses')->andReturn($mockAddressesRelation)->twice();

    $customer->shouldReceive('updateCustomFields')->never();

    $mockedQuery = Mockery::mock(Builder::class);
    $mockedQuery->shouldReceive('find')->with(1)->andReturn($customer)->once();
    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('with')
        ->with('billingAddress', 'shippingAddress', 'fields')
        ->andReturn($mockedQuery)
        ->once();

    $updatedCustomer = Customer::updateCustomer($mockRequest, $customer);

    expect($updatedCustomer)->toBe($customer);
});

test('updateCustomer updates custom fields', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('offsetGet')->with('currency_id')->andReturn(1)->once();
    $mockRequest->shouldReceive('getCustomerPayload')->andReturn(['name' => 'Updated Name'])->once();
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(null)->once();
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(null)->once();
    $customFields = ['field1' => 'updated_value'];
    $mockRequest->shouldReceive('offsetGet')->with('customFields')->andReturn($customFields)->once();

    $customer = Mockery::mock(Customer::class)->makePartial();
    $customer->currency_id = 1;
    $customer->id = 1;

    $customer->shouldReceive('estimates->exists')->andReturn(false)->once();
    $customer->shouldReceive('invoices->exists')->andReturn(false)->once();
    $customer->shouldReceive('payments->exists')->andReturn(false)->once();
    $customer->shouldReceive('recurringInvoices->exists')->andReturn(false)->once();

    $customer->shouldReceive('update')->with(['name' => 'Updated Name'])->once();

    $mockAddressesRelation = Mockery::mock(HasMany::class);
    $mockAddressesRelation->shouldReceive('delete')->once();
    $customer->shouldReceive('addresses')->andReturn($mockAddressesRelation)->once();

    $customer->shouldReceive('updateCustomFields')->with($customFields)->once();

    $mockedQuery = Mockery::mock(Builder::class);
    $mockedQuery->shouldReceive('find')->with(1)->andReturn($customer)->once();
    Mockery::mock('alias:' . Customer::class)
        ->shouldReceive('with')
        ->with('billingAddress', 'shippingAddress', 'fields')
        ->andReturn($mockedQuery)
        ->once();

    $updatedCustomer = Customer::updateCustomer($mockRequest, $customer);

    expect($updatedCustomer)->toBe($customer);
});

test('scopePaginateData returns all records when limit is "all"', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('get')->once()->andReturn(new Collection(['customer1', 'customer2']));

    $customer = new Customer();
    $result = $customer->scopePaginateData($mockBuilder, 'all');

    expect($result)->toEqual(new Collection(['customer1', 'customer2']));
});

test('scopePaginateData returns paginated records when limit is a number', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('paginate')->with(10)->once()->andReturn('paginated_results');

    $customer = new Customer();
    $result = $customer->scopePaginateData($mockBuilder, 10);

    expect($result)->toBe('paginated_results');
});

test('scopeWhereCompany applies company filter', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    request()->headers->set('company', 123);
    $mockBuilder->shouldReceive('where')->with('customers.company_id', 123)->once()->andReturnSelf();

    $customer = new Customer();
    $result = $customer->scopeWhereCompany($mockBuilder);

    expect($result)->toBe($mockBuilder);
});

test('scopeWhereContactName applies contact name filter', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('where')->with('contact_name', 'LIKE', '%John Doe%')->once()->andReturnSelf();

    $customer = new Customer();
    $result = $customer->scopeWhereContactName($mockBuilder, 'John Doe');

    expect($result)->toBe($mockBuilder);
});

test('scopeWhereDisplayName applies display name filter', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('where')->with('name', 'LIKE', '%Acme Corp%')->once()->andReturnSelf();

    $customer = new Customer();
    $result = $customer->scopeWhereDisplayName($mockBuilder, 'Acme Corp');

    expect($result)->toBe($mockBuilder);
});

test('scopeWhereOrder applies order by', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('orderBy')->with('name', 'asc')->once()->andReturnSelf();

    $customer = new Customer();
    $result = $customer->scopeWhereOrder($mockBuilder, 'name', 'asc');

    expect($result)->toBe($mockBuilder);
});

test('scopeWhereSearch applies search filter across multiple fields', function () {
    $mockBuilder = Mockery::mock(Builder::class);

    $mockBuilder->shouldReceive('where')->withArgs(function ($callback) {
        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('where')->with('name', 'LIKE', '%term1%')->once()->andReturnSelf();
        $mockQuery->shouldReceive('orWhere')->with('email', 'LIKE', '%term1%')->once()->andReturnSelf();
        $mockQuery->shouldReceive('orWhere')->with('phone', 'LIKE', '%term1%')->once()->andReturnSelf();
        $callback($mockQuery);
        return true;
    })->once()->andReturnSelf();

    $mockBuilder->shouldReceive('where')->withArgs(function ($callback) {
        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('where')->with('name', 'LIKE', '%term2%')->once()->andReturnSelf();
        $mockQuery->shouldReceive('orWhere')->with('email', 'LIKE', '%term2%')->once()->andReturnSelf();
        $mockQuery->shouldReceive('orWhere')->with('phone', 'LIKE', '%term2%')->once()->andReturnSelf();
        $callback($mockQuery);
        return true;
    })->once()->andReturnSelf();

    $customer = new Customer();
    $result = $customer->scopeWhereSearch($mockBuilder, 'term1 term2');

    expect($result)->toBe($mockBuilder);
});

test('scopeWherePhone applies phone filter', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('where')->with('phone', 'LIKE', '%123-456-7890%')->once()->andReturnSelf();

    $customer = new Customer();
    $result = $customer->scopeWherePhone($mockBuilder, '123-456-7890');

    expect($result)->toBe($mockBuilder);
});

test('scopeWhereCustomer applies customer ID filter', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('orWhere')->with('customers.id', 5)->once()->andReturnSelf();

    $customer = new Customer();
    $result = $customer->scopeWhereCustomer($mockBuilder, 5);

    expect($result)->toBe($mockBuilder);
});

test('scopeApplyInvoiceFilters applies date range filter if both from_date and to_date are present', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $filters = ['from_date' => '2023-01-01', 'to_date' => '2023-01-31'];

    $mockCarbon1 = Mockery::mock(Carbon::class);
    $mockCarbon2 = Mockery::mock(Carbon::class);
    Mockery::mock('overload:' . Carbon::class)
        ->shouldReceive('createFromFormat')
        ->with('Y-m-d', '2023-01-01')
        ->andReturn($mockCarbon1)
        ->once();
    Mockery::mock('overload:' . Carbon::class)
        ->shouldReceive('createFromFormat')
        ->with('Y-m-d', '2023-01-31')
        ->andReturn($mockCarbon2)
        ->once();

    $customer = Mockery::mock(Customer::class)->makePartial();
    $customer->shouldReceive('scopeInvoicesBetween')->with($mockBuilder, $mockCarbon1, $mockCarbon2)->once()->andReturn($mockBuilder);

    $result = $customer->scopeApplyInvoiceFilters($mockBuilder, $filters);

    expect($result)->toBe($mockBuilder);
});

test('scopeApplyInvoiceFilters does not apply date range filter if only from_date is present', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $filters = ['from_date' => '2023-01-01'];

    Mockery::mock('overload:' . Carbon::class)
        ->shouldNotReceive('createFromFormat');

    $customer = Mockery::mock(Customer::class)->makePartial();
    $customer->shouldNotReceive('scopeInvoicesBetween');

    $result = $customer->scopeApplyInvoiceFilters($mockBuilder, $filters);

    expect($result)->toBe($mockBuilder);
});

test('scopeApplyInvoiceFilters does not apply date range filter if only to_date is present', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $filters = ['to_date' => '2023-01-31'];

    Mockery::mock('overload:' . Carbon::class)
        ->shouldNotReceive('createFromFormat');

    $customer = Mockery::mock(Customer::class)->makePartial();
    $customer->shouldNotReceive('scopeInvoicesBetween');

    $result = $customer->scopeApplyInvoiceFilters($mockBuilder, $filters);

    expect($result)->toBe($mockBuilder);
});

test('scopeInvoicesBetween applies whereHas for invoices with date range', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $start = Carbon::parse('2023-01-01');
    $end = Carbon::parse('2023-01-31');

    $mockBuilder->shouldReceive('whereHas')->with('invoices', Mockery::on(function ($callback) use ($start, $end) {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereBetween')
            ->with('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->once();
        $callback($query);
        return true;
    }))->once()->andReturnSelf();

    $customer = new Customer();
    $result = $customer->scopeInvoicesBetween($mockBuilder, $start, $end);

    expect($result)->toBe($mockBuilder);
});

test('scopeApplyFilters applies all available filters', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $filters = [
        'search' => 'test search',
        'contact_name' => 'John',
        'display_name' => 'Acme',
        'customer_id' => 10,
        'phone' => '123',
        'orderByField' => 'name',
        'orderBy' => 'desc',
    ];

    $customer = Mockery::mock(Customer::class)->makePartial();
    $customer->shouldReceive('scopeWhereSearch')->with($mockBuilder, 'test search')->once()->andReturn($mockBuilder);
    $customer->shouldReceive('scopeWhereContactName')->with($mockBuilder, 'John')->once()->andReturn($mockBuilder);
    $customer->shouldReceive('scopeWhereDisplayName')->with($mockBuilder, 'Acme')->once()->andReturn($mockBuilder);
    $customer->shouldReceive('scopeWhereCustomer')->with($mockBuilder, 10)->once()->andReturn($mockBuilder);
    $customer->shouldReceive('scopeWherePhone')->with($mockBuilder, '123')->once()->andReturn($mockBuilder);
    $customer->shouldReceive('scopeWhereOrder')->with($mockBuilder, 'name', 'desc')->once()->andReturn($mockBuilder);

    $result = $customer->scopeApplyFilters($mockBuilder, $filters);

    expect($result)->toBe($mockBuilder);
});

test('scopeApplyFilters applies default order if orderByField or orderBy missing', function () {
    $mockBuilder = Mockery::mock(Builder::class);
    $filters1 = ['orderByField' => 'email'];
    $filters2 = ['orderBy' => 'desc'];
    $filters3 = [];

    $customer1 = Mockery::mock(Customer::class)->makePartial();
    $customer1->shouldReceive('scopeWhereOrder')->with($mockBuilder, 'email', 'asc')->once()->andReturn($mockBuilder);
    $customer1->scopeApplyFilters($mockBuilder, $filters1);

    $customer2 = Mockery::mock(Customer::class)->makePartial();
    $customer2->shouldReceive('scopeWhereOrder')->with($mockBuilder, 'name', 'desc')->once()->andReturn($mockBuilder);
    $customer2->scopeApplyFilters($mockBuilder, $filters2);

    $customer3 = Mockery::mock(Customer::class)->makePartial();
    $customer3->shouldReceive('scopeWhereOrder')->with($mockBuilder, 'name', 'asc')->once()->andReturn($mockBuilder);
    $customer3->scopeApplyFilters($mockBuilder, $filters3);
});
