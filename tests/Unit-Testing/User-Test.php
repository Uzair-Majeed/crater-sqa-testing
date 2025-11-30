<?php

use Carbon\Carbon;
use Crater\Http\Requests\UserRequest;
use Crater\Models\Address;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\Estimate;
use Crater\Models\Expense;
use Crater\Models\Invoice;
use Crater\Models\Item;
use Crater\Models\Payment;
use Crater\Models\RecurringInvoice;
use Crater\Models\User;
use Crater\Models\UserSetting;
use Crater\Notifications\MailResetPasswordNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
uses(\Mockery::class);
use Silber\Bouncer\BouncerFacade;
use Spatie\MediaLibrary\MediaCollections\FileCollections\FileCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(Tests\TestCase::class, RefreshDatabase::class)->group('UserTests');

beforeEach(function () {
    // Reset mocks before each test
    Mockery::close();

    // Mock global helpers or facades that are commonly used
    $this->mockAuth = Mockery::mock('alias:Illuminate\Support\Facades\Auth');
    $this->mockSchema = Mockery::mock('alias:Illuminate\Support\Facades\Schema');
    $this->mockCompanySetting = Mockery::mock('alias:Crater\Models\CompanySetting');
    $this->mockCarbon = Mockery::mock('alias:Carbon\Carbon');
    $this->mockBouncerFacade = Mockery::mock('alias:Silber\Bouncer\BouncerFacade');
    $this->mockRequest = Mockery::mock('Illuminate\Http\Request');

    // Make sure global request() helper uses our mock
    test()->instance('request', $this->mockRequest);

    // Mock asset helper - used for avatar attribute
    if (!function_exists('asset')) {
        function asset($path, $secure = null) {
            return 'http://example.com/' . $path;
        }
    }
});

// Test findForPassport
test('findForPassport returns user if found', function () {
    $username = 'test@example.com';
    $user = User::factory()->make(['email' => $username]);

    $this->mock(User::class, function ($mock) use ($username, $user) {
        $mock->shouldReceive('where->first')
            ->once()
            ->with('email', $username)
            ->andReturn($user);
    });

    $result = (new User())->findForPassport($username);
    expect($result)->toEqual($user);
});

test('findForPassport returns null if user not found', function () {
    $username = 'notfound@example.com';

    $this->mock(User::class, function ($mock) use ($username) {
        $mock->shouldReceive('where->first')
            ->once()
            ->with('email', $username)
            ->andReturn(null);
    });

    $result = (new User())->findForPassport($username);
    expect($result)->toBeNull();
});

// Test setPasswordAttribute
test('setPasswordAttribute hashes password if value is not null', function () {
    $user = new User();
    $password = 'secret123';
    $user->setPasswordAttribute($password);

    expect($user->attributes['password'])->not->toBe($password)
        ->and($user->attributes['password'])->toStartWith('$2y$'); // Bcrypt hash starts with $2y$
});

test('setPasswordAttribute does nothing if value is null', function () {
    $user = new User();
    $user->setPasswordAttribute(null);

    expect($user->attributes)->not->toHaveKey('password');
});

// Test isSuperAdminOrAdmin
test('isSuperAdminOrAdmin returns true for super admin role', function () {
    $user = new User(['role' => 'super admin']);
    expect($user->isSuperAdminOrAdmin())->toBeTrue();
});

test('isSuperAdminOrAdmin returns true for admin role', function () {
    $user = new User(['role' => 'admin']);
    expect($user->isSuperAdminOrAdmin())->toBeTrue();
});

test('isSuperAdminOrAdmin returns false for other roles', function () {
    $user = new User(['role' => 'editor']);
    expect($user->isSuperAdminOrAdmin())->toBeFalse();

    $user->role = null;
    expect($user->isSuperAdminOrAdmin())->toBeFalse();
});

// Test login
test('login attempts authentication and returns true on success', function () {
    $request = Mockery::mock(UserRequest::class);
    $request->remember = true;
    $request->email = 'test@example.com';
    $request->password = 'password';

    $this->mockAuth->shouldReceive('attempt')
        ->once()
        ->with(['email' => 'test@example.com', 'password' => 'password'], true)
        ->andReturn(true);

    $result = User::login($request);
    expect($result)->toBeTrue();
});

test('login attempts authentication and returns false on failure', function () {
    $request = Mockery::mock(UserRequest::class);
    $request->remember = false;
    $request->email = 'test@example.com';
    $request->password = 'wrong-password';

    $this->mockAuth->shouldReceive('attempt')
        ->once()
        ->with(['email' => 'test@example.com', 'password' => 'wrong-password'], false)
        ->andReturn(false);

    $result = User::login($request);
    expect($result)->toBeFalse();
});

// Test getFormattedCreatedAtAttribute
test('getFormattedCreatedAtAttribute formats created_at correctly', function () {
    $user = new User(['created_at' => '2023-01-15 10:30:00']);
    $dateFormat = 'd/m/Y H:i';
    $expectedFormattedDate = '15/01/2023 10:30';
    $companyId = 'company-abc';

    $this->mockRequest->shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn($companyId);

    $this->mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat);

    // Mock Carbon::parse and format
    $mockCarbonInstance = Mockery::mock(Carbon::class);
    $this->mockCarbon->shouldReceive('parse')
        ->once()
        ->with($user->created_at)
        ->andReturn($mockCarbonInstance);
    $mockCarbonInstance->shouldReceive('format')
        ->once()
        ->with($dateFormat)
        ->andReturn($expectedFormattedDate);

    expect($user->formattedCreatedAt)->toBe($expectedFormattedDate);
});

test('getFormattedCreatedAtAttribute handles missing created_at', function () {
    $user = new User(); // No created_at
    $dateFormat = 'd/m/Y H:i';
    $companyId = 'company-abc';

    $this->mockRequest->shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn($companyId);

    $this->mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat);

    $mockCarbonInstance = Mockery::mock(Carbon::class);
    $this->mockCarbon->shouldReceive('parse')
        ->once()
        ->with(null) // created_at is null
        ->andReturn($mockCarbonInstance);
    $mockCarbonInstance->shouldReceive('format')
        ->once()
        ->with($dateFormat)
        ->andReturn('N/A'); // Example for null date

    expect($user->formattedCreatedAt)->toBe('N/A');
});

// Test relationship methods
test('estimates returns hasMany relationship', function () {
    $user = new User();
    $relation = $user->estimates();
    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Estimate::class)
        ->and($relation->getForeignKeyName())->toBe('creator_id');
});

test('customers returns hasMany relationship', function () {
    $user = new User();
    $relation = $user->customers();
    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Customer::class)
        ->and($relation->getForeignKeyName())->toBe('creator_id');
});

test('recurringInvoices returns hasMany relationship', function () {
    $user = new User();
    $relation = $user->recurringInvoices();
    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(RecurringInvoice::class)
        ->and($relation->getForeignKeyName())->toBe('creator_id');
});

test('currency returns belongsTo relationship', function () {
    $user = new User();
    $relation = $user->currency();
    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Currency::class)
        ->and($relation->getForeignKeyName())->toBe('currency_id');
});

test('creator returns belongsTo relationship', function () {
    $user = new User();
    $relation = $user->creator();
    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class)
        ->and($relation->getForeignKeyName())->toBe('creator_id');
});

test('companies returns belongsToMany relationship', function () {
    $user = new User();
    $relation = $user->companies();
    expect($relation)->toBeInstanceOf(BelongsToMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Company::class)
        ->and($relation->getTable())->toBe('user_company')
        ->and($relation->getForeignPivotKeyName())->toBe('user_id')
        ->and($relation->getRelatedPivotKeyName())->toBe('company_id');
});

test('expenses returns hasMany relationship', function () {
    $user = new User();
    $relation = $user->expenses();
    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Expense::class)
        ->and($relation->getForeignKeyName())->toBe('creator_id');
});

test('payments returns hasMany relationship', function () {
    $user = new User();
    $relation = $user->payments();
    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Payment::class)
        ->and($relation->getForeignKeyName())->toBe('creator_id');
});

test('invoices returns hasMany relationship', function () {
    $user = new User();
    $relation = $user->invoices();
    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Invoice::class)
        ->and($relation->getForeignKeyName())->toBe('creator_id');
});

test('items returns hasMany relationship', function () {
    $user = new User();
    $relation = $user->items();
    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Item::class)
        ->and($relation->getForeignKeyName())->toBe('creator_id');
});

test('settings returns hasMany relationship', function () {
    $user = new User();
    $relation = $user->settings();
    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(UserSetting::class)
        ->and($relation->getForeignKeyName())->toBe('user_id');
});

test('addresses returns hasMany relationship', function () {
    $user = new User();
    $relation = $user->addresses();
    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Address::class)
        ->and($relation->getForeignKeyName())->toBe('user_id'); // Default foreign key for User model
});

test('billingAddress returns hasOne relationship with type constraint', function () {
    $user = new User();
    // We need to mock the HasOne relationship and its internal query builder
    $mockHasOne = Mockery::mock(HasOne::class);
    $mockHasOne->shouldReceive('getRelated')->andReturn(new Address());
    $mockHasOne->shouldReceive('where')->once()->with('type', Address::BILLING_TYPE)->andReturnSelf();

    $user->shouldReceive('hasOne')->once()->andReturn($mockHasOne);

    $relation = $user->billingAddress();
    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(Address::class);
});

test('shippingAddress returns hasOne relationship with type constraint', function () {
    $user = new User();
    $mockHasOne = Mockery::mock(HasOne::class);
    $mockHasOne->shouldReceive('getRelated')->andReturn(new Address());
    $mockHasOne->shouldReceive('where')->once()->with('type', Address::SHIPPING_TYPE)->andReturnSelf();

    $user->shouldReceive('hasOne')->once()->andReturn($mockHasOne);

    $relation = $user->shippingAddress();
    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(Address::class);
});

// Test sendPasswordResetNotification
test('sendPasswordResetNotification dispatches MailResetPasswordNotification', function () {
    $user = Mockery::spy(User::class);
    $token = 'test_token';

    $user->sendPasswordResetNotification($token);

    $user->shouldHaveReceived('notify')
        ->once()
        ->with(Mockery::on(function ($notification) use ($token) {
            return $notification instanceof MailResetPasswordNotification &&
                   $notification->token === $token;
        }));
});

// Test scopeWhereOrder
test('scopeWhereOrder applies order by clause', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('orderBy')
        ->once()
        ->with('name', 'asc');

    $user = new User();
    $user->scopeWhereOrder($query, 'name', 'asc');
});

// Test scopeWhereSearch
test('scopeWhereSearch applies search queries for multiple terms', function () {
    $query = Mockery::spy(Builder::class);

    $user = new User();
    $user->scopeWhereSearch($query, 'john doe');

    // Expect two terms, each calling where with a closure
    $query->shouldHaveReceived('where')
        ->twice() // For 'john' and 'doe'
        ->with(Mockery::type(Closure::class));
});

// Test scopeWhereContactName
test('scopeWhereContactName applies contact name search', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('contact_name', 'LIKE', '%John Doe%')
        ->andReturnSelf(); // Chainable

    $user = new User();
    $result = $user->scopeWhereContactName($query, 'John Doe');
    expect($result)->toBe($query);
});

// Test scopeWhereDisplayName
test('scopeWhereDisplayName applies display name search', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('name', 'LIKE', '%Test User%')
        ->andReturnSelf();

    $user = new User();
    $result = $user->scopeWhereDisplayName($query, 'Test User');
    expect($result)->toBe($query);
});

// Test scopeWherePhone
test('scopeWherePhone applies phone search', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('phone', 'LIKE', '%12345%')
        ->andReturnSelf();

    $user = new User();
    $result = $user->scopeWherePhone($query, '12345');
    expect($result)->toBe($query);
});

// Test scopeWhereEmail
test('scopeWhereEmail applies email search', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('email', 'LIKE', '%test@example.com%')
        ->andReturnSelf();

    $user = new User();
    $result = $user->scopeWhereEmail($query, 'test@example.com');
    expect($result)->toBe($query);
});

// Test scopePaginateData
test('scopePaginateData returns all records if limit is "all"', function () {
    $query = Mockery::mock(Builder::class);
    $collection = new Collection(['item1', 'item2']);
    $query->shouldReceive('get')
        ->once()
        ->andReturn($collection);

    $user = new User();
    $result = $user->scopePaginateData($query, 'all');
    expect($result)->toBe($collection);
});

test('scopePaginateData returns paginated records if limit is a number', function () {
    $query = Mockery::mock(Builder::class);
    $paginator = Mockery::mock(LengthAwarePaginator::class);
    $limit = 10;

    $query->shouldReceive('paginate')
        ->once()
        ->with($limit)
        ->andReturn($paginator);

    $user = new User();
    $result = $user->scopePaginateData($query, $limit);
    expect($result)->toBe($paginator);
});

// Test scopeApplyFilters
test('scopeApplyFilters applies search filter', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereSearch')->once()->with('term');
    $query->shouldNotReceive('whereDisplayName');
    $query->shouldNotReceive('whereEmail');
    $query->shouldNotReceive('wherePhone');
    $query->shouldNotReceive('whereOrder');

    $user = new User();
    $user->scopeApplyFilters($query, ['search' => 'term']);
});

test('scopeApplyFilters applies display name filter', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereDisplayName')->once()->with('name');
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereEmail');
    $query->shouldNotReceive('wherePhone');
    $query->shouldNotReceive('whereOrder');

    $user = new User();
    $user->scopeApplyFilters($query, ['display_name' => 'name']);
});

test('scopeApplyFilters applies email filter', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereEmail')->once()->with('email@test.com');
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereDisplayName');
    $query->shouldNotReceive('wherePhone');
    $query->shouldNotReceive('whereOrder');

    $user = new User();
    $user->scopeApplyFilters($query, ['email' => 'email@test.com']);
});

test('scopeApplyFilters applies phone filter', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('wherePhone')->once()->with('12345');
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereDisplayName');
    $query->shouldNotReceive('whereEmail');
    $query->shouldNotReceive('whereOrder');

    $user = new User();
    $user->scopeApplyFilters($query, ['phone' => '12345']);
});

test('scopeApplyFilters applies order by filter with default values', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereOrder')->once()->with('name', 'asc'); // Defaults
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereDisplayName');
    $query->shouldNotReceive('whereEmail');
    $query->shouldNotReceive('wherePhone');

    $user = new User();
    $user->scopeApplyFilters($query, ['orderByField' => null, 'orderBy' => null]);
});

test('scopeApplyFilters applies order by filter with specified values', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereOrder')->once()->with('email', 'desc');
    $query->shouldNotReceive('whereSearch');

    $user = new User();
    $user->scopeApplyFilters($query, ['orderByField' => 'email', 'orderBy' => 'desc']);
});

test('scopeApplyFilters applies multiple filters', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereSearch')->once()->with('term');
    $query->shouldReceive('whereDisplayName')->once()->with('name');
    $query->shouldReceive('whereOrder')->once()->with('created_at', 'desc');

    $user = new User();
    $user->scopeApplyFilters($query, [
        'search' => 'term',
        'display_name' => 'name',
        'orderByField' => 'created_at',
        'orderBy' => 'desc',
    ]);
});

test('scopeApplyFilters applies no filters if none provided', function () {
    $query = Mockery::spy(Builder::class);
    $user = new User();
    $user->scopeApplyFilters($query, []);

    $query->shouldNotHaveReceived('whereSearch');
    $query->shouldNotHaveReceived('whereDisplayName');
    $query->shouldNotHaveReceived('whereEmail');
    $query->shouldNotHaveReceived('wherePhone');
    $query->shouldNotHaveReceived('whereOrder');
});

// Test scopeWhereSuperAdmin
test('scopeWhereSuperAdmin applies orWhere clause', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('orWhere')
        ->once()
        ->with('role', 'super admin');

    $user = new User();
    $user->scopeWhereSuperAdmin($query);
});

// Test scopeApplyInvoiceFilters
test('scopeApplyInvoiceFilters applies invoicesBetween filter if from_date and to_date are present', function () {
    $query = Mockery::mock(Builder::class);
    $startDate = Carbon::createFromFormat('Y-m-d', '2023-01-01');
    $endDate = Carbon::createFromFormat('Y-m-d', '2023-01-31');

    $this->mockCarbon->shouldReceive('createFromFormat')
        ->twice()
        ->andReturn($startDate, $endDate); // Mock returns for two calls

    $query->shouldReceive('invoicesBetween')
        ->once()
        ->with(
            Mockery::on(function ($arg) { return $arg instanceof Carbon && $arg->toDateString() === '2023-01-01'; }),
            Mockery::on(function ($arg) { return $arg instanceof Carbon && $arg->toDateString() === '2023-01-31'; })
        );

    $user = new User();
    $user->scopeApplyInvoiceFilters($query, ['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
});

test('scopeApplyInvoiceFilters does not apply invoicesBetween if dates are missing', function () {
    $query = Mockery::spy(Builder::class);
    $this->mockCarbon->shouldNotReceive('createFromFormat');
    $query->shouldNotReceive('invoicesBetween');

    $user = new User();
    $user->scopeApplyInvoiceFilters($query, ['from_date' => '2023-01-01']);
    $user->scopeApplyInvoiceFilters($query, ['to_date' => '2023-01-31']);
    $user->scopeApplyInvoiceFilters($query, []);
});

// Test scopeInvoicesBetween
test('scopeInvoicesBetween applies whereHas with date range for invoices', function () {
    $query = Mockery::mock(Builder::class);
    $start = Carbon::createFromFormat('Y-m-d', '2023-01-01');
    $end = Carbon::createFromFormat('Y-m-d', '2023-01-31');

    $mockWhereHasQuery = Mockery::mock(Builder::class);
    $mockWhereHasQuery->shouldReceive('whereBetween')
        ->once()
        ->with('invoice_date', ['2023-01-01', '2023-01-31']);

    $query->shouldReceive('whereHas')
        ->once()
        ->with('invoices', Mockery::on(function ($closure) use ($mockWhereHasQuery) {
            // Invoke the closure with our mock query builder
            $closure($mockWhereHasQuery);
            return true;
        }))
        ->andReturnSelf();

    $user = new User();
    $user->scopeInvoicesBetween($query, $start, $end);
});

// Test getAvatarAttribute
test('getAvatarAttribute returns asset URL if avatar exists', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getMedia')
        ->once()
        ->with('admin_avatar')
        ->andReturn(Mockery::mock(FileCollection::class, function ($mediaCollection) {
            $mockMedia = Mockery::mock(Media::class);
            $mockMedia->shouldReceive('getUrl')->once()->andReturn('path/to/avatar.jpg');
            $mediaCollection->shouldReceive('first')->once()->andReturn($mockMedia);
        }));

    expect($user->avatar)->toBe('http://example.com/path/to/avatar.jpg');
});

test('getAvatarAttribute returns 0 if no avatar exists', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getMedia')
        ->once()
        ->with('admin_avatar')
        ->andReturn(Mockery::mock(FileCollection::class, function ($mediaCollection) {
            $mediaCollection->shouldReceive('first')->once()->andReturn(null);
        }));

    expect($user->avatar)->toBe(0);
});

// Test setSettings
test('setSettings updates or creates user settings', function () {
    $user = Mockery::spy(User::class)->makePartial();
    $mockHasMany = Mockery::mock(HasMany::class);
    $mockHasMany->shouldReceive('updateOrCreate')
        ->twice() // Called for 'language' and 'theme'
        ->withArgs(function ($attributes, $values) {
            if ($attributes['key'] === 'language') {
                return $values['value'] === 'en';
            } elseif ($attributes['key'] === 'theme') {
                return $values['value'] === 'dark';
            }
            return false;
        });

    $user->shouldReceive('settings')->andReturn($mockHasMany);

    $settings = [
        'language' => 'en',
        'theme' => 'dark',
    ];
    $user->setSettings($settings);

    $user->shouldHaveReceived('settings')->twice(); // Once for each loop iteration
});

// Test hasCompany
test('hasCompany returns true if user is associated with company', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $companyId = 1;
    $companyIds = [1, 2, 3];

    $mockBelongsToMany = Mockery::mock(BelongsToMany::class);
    $mockBelongsToMany->shouldReceive('pluck')
        ->once()
        ->with('company_id')
        ->andReturn(collect($companyIds));

    $user->shouldReceive('companies')->andReturn($mockBelongsToMany);

    expect($user->hasCompany($companyId))->toBeTrue();
});

test('hasCompany returns false if user is not associated with company', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $companyId = 4;
    $companyIds = [1, 2, 3];

    $mockBelongsToMany = Mockery::mock(BelongsToMany::class);
    $mockBelongsToMany->shouldReceive('pluck')
        ->once()
        ->with('company_id')
        ->andReturn(collect($companyIds));

    $user->shouldReceive('companies')->andReturn($mockBelongsToMany);

    expect($user->hasCompany($companyId))->toBeFalse();
});

test('hasCompany returns false if user has no companies', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $companyId = 1;
    $companyIds = [];

    $mockBelongsToMany = Mockery::mock(BelongsToMany::class);
    $mockBelongsToMany->shouldReceive('pluck')
        ->once()
        ->with('company_id')
        ->andReturn(collect($companyIds));

    $user->shouldReceive('companies')->andReturn($mockBelongsToMany);

    expect($user->hasCompany($companyId))->toBeFalse();
});

// Test getAllSettings
test('getAllSettings returns all user settings mapped correctly', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $mockHasMany = Mockery::mock(HasMany::class);
    $settingsCollection = collect([
        ['key' => 'language', 'value' => 'en'],
        ['key' => 'theme', 'value' => 'light'],
    ]);

    $mockHasMany->shouldReceive('get')->once()->andReturn($settingsCollection);

    $user->shouldReceive('settings')->andReturn($mockHasMany);

    $expected = collect(['language' => 'en', 'theme' => 'light']);
    expect($user->getAllSettings())->toEqual($expected);
});

test('getAllSettings returns empty collection if no settings exist', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $mockHasMany = Mockery::mock(HasMany::class);
    $settingsCollection = collect([]);

    $mockHasMany->shouldReceive('get')->once()->andReturn($settingsCollection);

    $user->shouldReceive('settings')->andReturn($mockHasMany);

    expect($user->getAllSettings())->toEqual(collect([]));
});

// Test getSettings
test('getSettings returns specific user settings mapped correctly', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $keysToFetch = ['language', 'dateFormat'];
    $mockHasMany = Mockery::mock(HasMany::class);
    $settingsCollection = collect([
        ['key' => 'language', 'value' => 'fr'],
        ['key' => 'dateFormat', 'value' => 'Y-m-d'],
    ]);

    $mockHasMany->shouldReceive('whereIn')
        ->once()
        ->with('key', $keysToFetch)
        ->andReturnSelf();
    $mockHasMany->shouldReceive('get')->once()->andReturn($settingsCollection);

    $user->shouldReceive('settings')->andReturn($mockHasMany);

    $expected = collect(['language' => 'fr', 'dateFormat' => 'Y-m-d']);
    expect($user->getSettings($keysToFetch))->toEqual($expected);
});

test('getSettings returns only available settings if some keys are not found', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $keysToFetch = ['language', 'dateFormat', 'timezone'];
    $mockHasMany = Mockery::mock(HasMany::class);
    $settingsCollection = collect([
        ['key' => 'language', 'value' => 'es'],
    ]);

    $mockHasMany->shouldReceive('whereIn')
        ->once()
        ->with('key', $keysToFetch)
        ->andReturnSelf();
    $mockHasMany->shouldReceive('get')->once()->andReturn($settingsCollection);

    $user->shouldReceive('settings')->andReturn($mockHasMany);

    $expected = collect(['language' => 'es']);
    expect($user->getSettings($keysToFetch))->toEqual($expected);
});

// Test isOwner
test('isOwner returns true if user is owner of company and schema has column', function () {
    $user = User::factory()->make(['id' => 1]);
    $companyId = 123;
    $company = (object)['owner_id' => 1];

    $this->mockSchema->shouldReceive('hasColumn')
        ->once()
        ->with('companies', 'owner_id')
        ->andReturn(true);

    $this->mockRequest->shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn($companyId);

    $this->mock(Company::class, function ($mock) use ($companyId, $company) {
        $mock->shouldReceive('find')
            ->once()
            ->with($companyId)
            ->andReturn($company);
    });

    expect($user->isOwner())->toBeTrue();
});

test('isOwner returns false if user is not owner of company', function () {
    $user = User::factory()->make(['id' => 2]);
    $companyId = 123;
    $company = (object)['owner_id' => 1];

    $this->mockSchema->shouldReceive('hasColumn')
        ->once()
        ->with('companies', 'owner_id')
        ->andReturn(true);

    $this->mockRequest->shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn($companyId);

    $this->mock(Company::class, function ($mock) use ($companyId, $company) {
        $mock->shouldReceive('find')
            ->once()
            ->with($companyId)
            ->andReturn($company);
    });

    expect($user->isOwner())->toBeFalse();
});

test('isOwner returns false if company not found', function () {
    $user = User::factory()->make(['id' => 1]);
    $companyId = 123;

    $this->mockSchema->shouldReceive('hasColumn')
        ->once()
        ->with('companies', 'owner_id')
        ->andReturn(true);

    $this->mockRequest->shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn($companyId);

    $this->mock(Company::class, function ($mock) use ($companyId) {
        $mock->shouldReceive('find')
            ->once()
            ->with($companyId)
            ->andReturn(null);
    });

    expect($user->isOwner())->toBeFalse();
});

test('isOwner returns true if schema has no column and user is super admin', function () {
    $user = User::factory()->make(['id' => 1, 'role' => 'super admin']);

    $this->mockSchema->shouldReceive('hasColumn')
        ->once()
        ->with('companies', 'owner_id')
        ->andReturn(false);

    expect($user->isOwner())->toBeTrue();
});

test('isOwner returns true if schema has no column and user is admin', function () {
    $user = User::factory()->make(['id' => 1, 'role' => 'admin']);

    $this->mockSchema->shouldReceive('hasColumn')
        ->once()
        ->with('companies', 'owner_id')
        ->andReturn(false);

    expect($user->isOwner())->toBeTrue();
});

test('isOwner returns false if schema has no column and user is neither super admin nor admin', function () {
    $user = User::factory()->make(['id' => 1, 'role' => 'editor']);

    $this->mockSchema->shouldReceive('hasColumn')
        ->once()
        ->with('companies', 'owner_id')
        ->andReturn(false);

    expect($user->isOwner())->toBeFalse();
});

// Test createFromRequest
test('createFromRequest creates user, sets settings, and syncs companies/roles', function () {
    $request = Mockery::mock(UserRequest::class);
    $userPayload = ['name' => 'New User', 'email' => 'new@example.com'];
    $companyId = 'company-abc';
    $companyData = [
        ['id' => 1, 'role' => 'admin'],
        ['id' => 2, 'role' => 'employee'],
    ];

    $createdUser = Mockery::spy(User::class); // Spy to check method calls on created user
    $createdUser->id = 5; // Give it an ID for later mocking
    $createdUser->setRelation('settings', new Collection()); // Ensure relation is available for setSettings

    // Mock static `create` call
    Mockery::mock('alias:'.User::class)
        ->shouldReceive('create')
        ->once()
        ->with($userPayload)
        ->andReturn($createdUser);

    $request->shouldReceive('getUserPayload')->once()->andReturn($userPayload);
    $request->shouldReceive('header')->once()->with('company')->andReturn($companyId);
    $request->companies = collect($companyData);

    $this->mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('language', $companyId)
        ->andReturn('es');

    // Mock the user's relationships and methods
    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('sync')
        ->once()
        ->with(collect([1, 2])); // Pluck of company IDs
    $createdUser->shouldReceive('companies')->andReturn($mockCompaniesRelation);

    $mockUserSettingsRelation = Mockery::mock(HasMany::class);
    $mockUserSettingsRelation->shouldReceive('updateOrCreate')
        ->once()
        ->with(['key' => 'language'], ['key' => 'language', 'value' => 'es']);
    $createdUser->shouldReceive('settings')->andReturn($mockUserSettingsRelation);

    // Mock BouncerFacade interactions
    $mockBouncerScope = Mockery::mock();
    $mockBouncerScope->shouldReceive('to')->with(1)->once()->andReturnSelf();
    $mockBouncerScope->shouldReceive('to')->with(2)->once()->andReturnSelf();
    $this->mockBouncerFacade->shouldReceive('scope')->times(2)->andReturn($mockBouncerScope);

    $mockBouncerSync = Mockery::mock();
    $mockBouncerSync->shouldReceive('roles')->with(['admin'])->once();
    $mockBouncerSync->shouldReceive('roles')->with(['employee'])->once();
    $this->mockBouncerFacade->shouldReceive('sync')
        ->with($createdUser)
        ->twice()
        ->andReturn($mockBouncerSync);

    $result = User::createFromRequest($request);

    expect($result)->toBe($createdUser);
    $createdUser->shouldHaveReceived('settings'); // Via setSettings
    $createdUser->shouldHaveReceived('companies');
});


// Test updateFromRequest
test('updateFromRequest updates user and syncs companies/roles', function () {
    $user = Mockery::spy(User::class);
    $user->id = 1; // Needs an ID for Bouncer

    $request = Mockery::mock(UserRequest::class);
    $userPayload = ['name' => 'Updated User', 'email' => 'updated@example.com'];
    $companyData = [
        ['id' => 1, 'role' => 'editor'],
        ['id' => 3, 'role' => 'viewer'],
    ];

    $request->shouldReceive('getUserPayload')->once()->andReturn($userPayload);
    $request->companies = collect($companyData);

    $user->shouldReceive('update')
        ->once()
        ->with($userPayload);

    // Mock the user's relationships and methods
    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('sync')
        ->once()
        ->with(collect([1, 3]));
    $user->shouldReceive('companies')->andReturn($mockCompaniesRelation);

    // Mock BouncerFacade interactions
    $mockBouncerScope = Mockery::mock();
    $mockBouncerScope->shouldReceive('to')->with(1)->once()->andReturnSelf();
    $mockBouncerScope->shouldReceive('to')->with(3)->once()->andReturnSelf();
    $this->mockBouncerFacade->shouldReceive('scope')->times(2)->andReturn($mockBouncerScope);

    $mockBouncerSync = Mockery::mock();
    $mockBouncerSync->shouldReceive('roles')->with(['editor'])->once();
    $mockBouncerSync->shouldReceive('roles')->with(['viewer'])->once();
    $this->mockBouncerFacade->shouldReceive('sync')
        ->with($user)
        ->twice()
        ->andReturn($mockBouncerSync);

    $result = $user->updateFromRequest($request);

    expect($result)->toBe($user);
    $user->shouldHaveReceived('update');
    $user->shouldHaveReceived('companies');
});

// Test checkAccess
test('checkAccess returns true if user is owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(true);

    $data = (object)['data' => ['owner_only' => false, 'ability' => 'view', 'model' => null]];

    expect($user->checkAccess($data))->toBeTrue();
});

test('checkAccess returns true if not owner_only and ability is empty', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);

    $data = (object)['data' => ['owner_only' => false, 'ability' => '', 'model' => null]];

    expect($user->checkAccess($data))->toBeTrue();
});

test('checkAccess returns true if not owner_only, ability and model exist, and user can', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);
    $user->shouldReceive('can')->once()->with('view', 'App\Models\Invoice')->andReturn(true);

    $data = (object)['data' => ['owner_only' => false, 'ability' => 'view', 'model' => 'App\Models\Invoice']];

    expect($user->checkAccess($data))->toBeTrue();
});

test('checkAccess returns true if not owner_only, ability exists, and user can (without model)', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);
    $user->shouldReceive('can')->once()->with('create')->andReturn(true);

    $data = (object)['data' => ['owner_only' => false, 'ability' => 'create', 'model' => null]];

    expect($user->checkAccess($data))->toBeTrue();
});

test('checkAccess returns false if all conditions fail', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);
    $user->shouldNotReceive('can'); // Should not even be called if owner_only is true

    $data = (object)['data' => ['owner_only' => true, 'ability' => 'view', 'model' => null]];
    expect($user->checkAccess($data))->toBeFalse();

    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);
    $user->shouldReceive('can')->with('view', 'App\Models\Invoice')->andReturn(false);
    $user->shouldReceive('can')->with('view')->andReturn(false);

    $data = (object)['data' => ['owner_only' => false, 'ability' => 'view', 'model' => 'App\Models\Invoice']];
    expect($user->checkAccess($data))->toBeFalse();
});

// Test deleteUsers
test('deleteUsers deletes users and updates related records', function () {
    $userIds = [1, 2];

    // Mock User::find for each ID
    $mockUser1 = Mockery::spy(User::class);
    $mockUser1->id = 1;
    $mockUser2 = Mockery::spy(User::class);
    $mockUser2->id = 2;

    Mockery::mock('alias:'.User::class)
        ->shouldReceive('find')
        ->with(1)->andReturn($mockUser1)
        ->with(2)->andReturn($mockUser2);

    // Define expectations for user 1 (with relations)
    $mockUser1->shouldReceive('invoices->exists')->andReturn(true);
    $mockUser1->shouldReceive('invoices->update')->with(['creator_id' => null])->once();
    $mockUser1->shouldReceive('estimates->exists')->andReturn(false);
    $mockUser1->shouldReceive('customers->exists')->andReturn(true);
    $mockUser1->shouldReceive('customers->update')->with(['creator_id' => null])->once();
    $mockUser1->shouldReceive('recurringInvoices->exists')->andReturn(false);
    $mockUser1->shouldReceive('expenses->exists')->andReturn(true);
    $mockUser1->shouldReceive('expenses->update')->with(['creator_id' => null])->once();
    $mockUser1->shouldReceive('payments->exists')->andReturn(false);
    $mockUser1->shouldReceive('items->exists')->andReturn(true);
    $mockUser1->shouldReceive('items->update')->with(['creator_id' => null])->once();
    $mockUser1->shouldReceive('settings->exists')->andReturn(true);
    $mockUser1->shouldReceive('settings->delete')->once();
    $mockUser1->shouldReceive('delete')->once();

    // Define expectations for user 2 (without relations)
    $mockUser2->shouldReceive('invoices->exists')->andReturn(false);
    $mockUser2->shouldReceive('estimates->exists')->andReturn(false);
    $mockUser2->shouldReceive('customers->exists')->andReturn(false);
    $mockUser2->shouldReceive('recurringInvoices->exists')->andReturn(false);
    $mockUser2->shouldReceive('expenses->exists')->andReturn(false);
    $mockUser2->shouldReceive('payments->exists')->andReturn(false);
    $mockUser2->shouldReceive('items->exists')->andReturn(false);
    $mockUser2->shouldReceive('settings->exists')->andReturn(false);
    $mockUser2->shouldNotReceive('settings->delete');
    $mockUser2->shouldReceive('delete')->once();


    $result = User::deleteUsers($userIds);

    expect($result)->toBeTrue();
    $mockUser1->shouldHaveReceived('delete')->once();
    $mockUser2->shouldHaveReceived('delete')->once();
});

test('deleteUsers handles non-existent users gracefully', function () {
    $userIds = [1, 999]; // 999 does not exist

    $mockUser1 = Mockery::spy(User::class);
    $mockUser1->id = 1;

    Mockery::mock('alias:'.User::class)
        ->shouldReceive('find')
        ->with(1)->andReturn($mockUser1)
        ->with(999)->andReturn(null); // Non-existent user

    $mockUser1->shouldReceive('invoices->exists')->andReturn(false);
    $mockUser1->shouldReceive('estimates->exists')->andReturn(false);
    $mockUser1->shouldReceive('customers->exists')->andReturn(false);
    $mockUser1->shouldReceive('recurringInvoices->exists')->andReturn(false);
    $mockUser1->shouldReceive('expenses->exists')->andReturn(false);
    $mockUser1->shouldReceive('payments->exists')->andReturn(false);
    $mockUser1->shouldReceive('items->exists')->andReturn(false);
    $mockUser1->shouldReceive('settings->exists')->andReturn(false);
    $mockUser1->shouldReceive('delete')->once();

    $result = User::deleteUsers($userIds);

    expect($result)->toBeTrue();
    $mockUser1->shouldHaveReceived('delete')->once();
    // No calls for user 999 as find returned null
});
