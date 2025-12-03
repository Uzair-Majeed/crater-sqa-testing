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
use Silber\Bouncer\BouncerFacade;
use Spatie\MediaLibrary\MediaCollections\FileCollections\FileCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    // Reset mocks before each test
    Mockery::close();

    // Mock global helpers or facades that are commonly used
    // For Laravel Facades (Auth, Schema, BouncerFacade), use Pest's `$this->mock()` helper,
    // which integrates with Laravel's facade resolution and provides a mock instance
    // for the underlying service. This avoids "class already exists" errors for facades.
    $this->mockAuth = $this->mock(Auth::class);
    $this->mockSchema = $this->mock(Schema::class);
    $this->mockBouncerFacade = $this->mock(BouncerFacade::class);

    // For regular classes (CompanySetting, Carbon) where static methods are called and need mocking,
    // use Pest's `$this->mock('alias:ClassFQN')`. This ensures a class alias is set up,
    // allowing interception of static calls. This is the correct pattern for static mocking non-facade classes.
    $this->mockCompanySetting = $this->mock('alias:Crater\Models\CompanySetting');
    $this->mockCarbon = $this->mock('alias:Carbon\Carbon');
    
    // The Request mock setup is fine.
    $this->mockRequest = Mockery::mock('Illuminate\Http\Request');
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

    // Mock the User model for static `where` and `first` calls.
    // Ensure `where` returns a mock of itself to allow chaining `first`.
    $this->mock(User::class, function ($mock) use ($username, $user) {
        $mock->shouldReceive('where')
            ->once()
            ->with('email', $username)
            ->andReturn(Mockery::self()); // Allows chaining `first()` on the result of `where()`
        $mock->shouldReceive('first')
            ->once()
            ->andReturn($user);
    });

    $result = (new User())->findForPassport($username);
    expect($result)->toEqual($user);
});

test('findForPassport returns null if user not found', function () {
    $username = 'notfound@example.com';

    $this->mock(User::class, function ($mock) use ($username) {
        $mock->shouldReceive('where')
            ->once()
            ->with('email', $username)
            ->andReturn(Mockery::self());
        $mock->shouldReceive('first')
            ->once()
            ->andReturn(null);
    });

    $result = (new User())->findForPassport($username);
    expect($result)->toBeNull();
});

// Test setPasswordAttribute
test('setPasswordAttribute hashes password if value is not null', function () {
    $user = new User();
    $password = 'secret123';
    // Eloquent mutators are typically triggered by assigning to the public property
    $user->password = $password; 

    expect($user->attributes['password'])->not->toBe($password)
        ->and($user->attributes['password'])->toStartWith('$2y$'); // Bcrypt hash starts with $2y$
});

test('setPasswordAttribute does nothing if value is null', function () {
    $user = new User();
    $user->password = null;

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
    // Directly set properties on the mock request
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

    // Mock Carbon::parse and format on the Carbon alias mock
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
    $user = Mockery::spy(User::class); // Spy on the user to check hasOne calls
    $mockHasOne = Mockery::mock(HasOne::class);

    $user->shouldReceive('hasOne')
        ->once()
        ->with(Address::class) // The first argument to hasOne
        ->andReturn($mockHasOne);

    // The getRelated method is often called internally by the relationship object itself
    // or by assertion methods.
    $mockHasOne->shouldReceive('getRelated')->andReturn(new Address());
    $mockHasOne->shouldReceive('where')
        ->once()
        ->with('type', Address::BILLING_TYPE)
        ->andReturnSelf(); // Ensure 'where' returns itself for chaining

    $relation = $user->billingAddress();
    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(Address::class);
});

test('shippingAddress returns hasOne relationship with type constraint', function () {
    $user = Mockery::spy(User::class); // Spy on the user to check hasOne calls
    $mockHasOne = Mockery::mock(HasOne::class);

    $user->shouldReceive('hasOne')
        ->once()
        ->with(Address::class) // The first argument to hasOne
        ->andReturn($mockHasOne);

    $mockHasOne->shouldReceive('getRelated')->andReturn(new Address());
    $mockHasOne->shouldReceive('where')
        ->once()
        ->with('type', Address::SHIPPING_TYPE)
        ->andReturnSelf();

    $relation = $user->shippingAddress();
    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(Address::class);
});

// Test sendPasswordResetNotification
test('sendPasswordResetNotification dispatches MailResetPasswordNotification', function () {
    $user = Mockery::spy(User::class)->makePartial(); // MakePartial to allow `notify()` to be spied but other methods exist
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
        ->with('name', 'asc')
        ->andReturnSelf(); // Eloquent scopes are chainable, so they should return the builder

    $user = new User();
    $result = $user->scopeWhereOrder($query, 'name', 'asc');
    expect($result)->toBe($query); // Assert that the query builder is returned
});

// Test scopeWhereSearch
test('scopeWhereSearch applies search queries for multiple terms', function () {
    $query = Mockery::spy(Builder::class);
    $query->shouldReceive('where')->andReturnSelf(); // Ensure 'where' is chainable for the closure logic

    $user = new User();
    $user->scopeWhereSearch($query, 'john doe');

    // Expect 'where' to be called twice (for 'john' and 'doe'), each with a closure
    $query->shouldHaveReceived('where')
        ->twice()
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
    $query->shouldReceive('whereSearch')->once()->with('term')->andReturnSelf(); // Ensure chainability
    $query->shouldNotReceive('whereDisplayName');
    $query->shouldNotReceive('whereEmail');
    $query->shouldNotReceive('wherePhone');
    $query->shouldNotReceive('whereOrder');

    $user = new User();
    $user->scopeApplyFilters($query, ['search' => 'term']);
});

test('scopeApplyFilters applies display name filter', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereDisplayName')->once()->with('name')->andReturnSelf();
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereEmail');
    $query->shouldNotReceive('wherePhone');
    $query->shouldNotReceive('whereOrder');

    $user = new User();
    $user->scopeApplyFilters($query, ['display_name' => 'name']);
});

test('scopeApplyFilters applies email filter', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereEmail')->once()->with('email@test.com')->andReturnSelf();
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereDisplayName');
    $query->shouldNotReceive('wherePhone');
    $query->shouldNotReceive('whereOrder');

    $user = new User();
    $user->scopeApplyFilters($query, ['email' => 'email@test.com']);
});

test('scopeApplyFilters applies phone filter', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('wherePhone')->once()->with('12345')->andReturnSelf();
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereDisplayName');
    $query->shouldNotReceive('whereEmail');
    $query->shouldNotReceive('whereOrder');

    $user = new User();
    $user->scopeApplyFilters($query, ['phone' => '12345']);
});

test('scopeApplyFilters applies order by filter with default values', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereOrder')->once()->with('name', 'asc')->andReturnSelf(); // Defaults
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereDisplayName');
    $query->shouldNotReceive('whereEmail');
    $query->shouldNotReceive('wherePhone');

    $user = new User();
    $user->scopeApplyFilters($query, ['orderByField' => null, 'orderBy' => null]);
});

test('scopeApplyFilters applies order by filter with specified values', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereOrder')->once()->with('email', 'desc')->andReturnSelf();
    $query->shouldNotReceive('whereSearch');

    $user = new User();
    $user->scopeApplyFilters($query, ['orderByField' => 'email', 'orderBy' => 'desc']);
});

test('scopeApplyFilters applies multiple filters', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereSearch')->once()->with('term')->andReturnSelf();
    $query->shouldReceive('whereDisplayName')->once()->with('name')->andReturnSelf();
    $query->shouldReceive('whereOrder')->once()->with('created_at', 'desc')->andReturnSelf();

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
        ->with('role', 'super admin')
        ->andReturnSelf(); // Chainable

    $user = new User();
    $result = $user->scopeWhereSuperAdmin($query);
    expect($result)->toBe($query);
});

// Test scopeApplyInvoiceFilters
test('scopeApplyInvoiceFilters applies invoicesBetween filter if from_date and to_date are present', function () {
    $query = Mockery::mock(Builder::class);
    $startDateString = '2023-01-01';
    $endDateString = '2023-01-31';
    $startDate = Carbon::createFromFormat('Y-m-d', $startDateString);
    $endDate = Carbon::createFromFormat('Y-m-d', $endDateString);

    // Mock Carbon::createFromFormat using the alias mock
    // It's called twice, so we need two distinct shouldReceive calls for static methods.
    $this->mockCarbon->shouldReceive('createFromFormat')
        ->once()
        ->with('Y-m-d', $startDateString)
        ->andReturn($startDate);
    $this->mockCarbon->shouldReceive('createFromFormat')
        ->once()
        ->with('Y-m-d', $endDateString)
        ->andReturn($endDate);

    $query->shouldReceive('invoicesBetween')
        ->once()
        ->with(
            Mockery::on(function ($arg) use ($startDate) { return $arg instanceof Carbon && $arg->eq($startDate); }),
            Mockery::on(function ($arg) use ($endDate) { return $arg instanceof Carbon && $arg->eq($endDate); })
        )
        ->andReturnSelf(); // Ensure chaining for applyInvoiceFilters

    $user = new User();
    $result = $user->scopeApplyInvoiceFilters($query, ['from_date' => $startDateString, 'to_date' => $endDateString]);
    expect($result)->toBe($query);
});

test('scopeApplyInvoiceFilters does not apply invoicesBetween if dates are missing', function () {
    $query = Mockery::spy(Builder::class);
    // Explicitly state no calls expected on the Carbon mock
    $this->mockCarbon->shouldNotReceive('createFromFormat');
    $query->shouldNotReceive('invoicesBetween');

    $user = new User();
    $user->scopeApplyInvoiceFilters($query, ['from_date' => '2023-01-01']); // Missing to_date
    $user->scopeApplyInvoiceFilters($query, ['to_date' => '2023-01-31']); // Missing from_date
    $user->scopeApplyInvoiceFilters($query, []); // Missing both

    $query->shouldNotHaveReceived('invoicesBetween');
});

// Test scopeInvoicesBetween
test('scopeInvoicesBetween applies whereHas with date range for invoices', function () {
    $query = Mockery::mock(Builder::class);
    $start = Carbon::createFromFormat('Y-m-d', '2023-01-01');
    $end = Carbon::createFromFormat('Y-m-d', '2023-01-31');

    $mockWhereHasQuery = Mockery::mock(Builder::class);
    $mockWhereHasQuery->shouldReceive('whereBetween')
        ->once()
        ->with('invoice_date', [$start->toDateString(), $end->toDateString()]);

    $query->shouldReceive('whereHas')
        ->once()
        ->with('invoices', Mockery::on(function ($closure) use ($mockWhereHasQuery) {
            // Invoke the closure with our mock query builder
            $closure($mockWhereHasQuery);
            return true;
        }))
        ->andReturnSelf(); // Chainable

    $user = new User();
    $result = $user->scopeInvoicesBetween($query, $start, $end);
    expect($result)->toBe($query);
});

// Test getAvatarAttribute
test('getAvatarAttribute returns asset URL if avatar exists', function () {
    // We need to mock the `User` instance's `getMedia` method.
    $user = Mockery::mock(User::class)->makePartial();
    
    // Mock a Media instance and its getUrl method
    $mockMedia = Mockery::mock(Media::class);
    $mockMedia->shouldReceive('getUrl')->once()->andReturn('path/to/avatar.jpg');

    // Mock a FileCollection and its first method to return the mock Media
    $mockFileCollection = Mockery::mock(FileCollection::class);
    $mockFileCollection->shouldReceive('first')->once()->andReturn($mockMedia);

    // Mock the user's getMedia method to return the mock FileCollection
    $user->shouldReceive('getMedia')
        ->once()
        ->with('admin_avatar')
        ->andReturn($mockFileCollection);

    // The asset() helper is mocked globally in beforeEach, so it's ready.
    expect($user->avatar)->toBe('http://example.com/path/to/avatar.jpg');
});

test('getAvatarAttribute returns 0 if no avatar exists', function () {
    $user = Mockery::mock(User::class)->makePartial();
    
    $mockFileCollection = Mockery::mock(FileCollection::class);
    $mockFileCollection->shouldReceive('first')->once()->andReturn(null);

    $user->shouldReceive('getMedia')
        ->once()
        ->with('admin_avatar')
        ->andReturn($mockFileCollection);

    expect($user->avatar)->toBe(0);
});

// Test setSettings
test('setSettings updates or creates user settings', function () {
    $user = Mockery::spy(User::class)->makePartial();
    $mockHasMany = Mockery::mock(HasMany::class);

    // Mock the `settings()` relationship to return our mock `HasMany` instance.
    $user->shouldReceive('settings')->andReturn($mockHasMany);

    $settings = [
        'language' => 'en',
        'theme' => 'dark',
    ];

    // Expect `updateOrCreate` to be called twice (once for language, once for theme)
    // with specific arguments.
    $mockHasMany->shouldReceive('updateOrCreate')
        ->once()
        ->with(['key' => 'language'], ['key' => 'language', 'value' => 'en']);
    $mockHasMany->shouldReceive('updateOrCreate')
        ->once()
        ->with(['key' => 'theme'], ['key' => 'theme', 'value' => 'dark']);
    
    $user->setSettings($settings);

    // The `settings()` method should have been called twice in the loop of `setSettings`.
    $user->shouldHaveReceived('settings')->twice();
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
    // Simulate eloquent relation returning objects with key/value
    $settingsCollection = collect([
        (object)['key' => 'language', 'value' => 'en'],
        (object)['key' => 'theme', 'value' => 'light'],
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
    // Simulate eloquent relation returning objects
    $settingsCollection = collect([
        (object)['key' => 'language', 'value' => 'fr'],
        (object)['key' => 'dateFormat', 'value' => 'Y-m-d'],
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
        (object)['key' => 'language', 'value' => 'es'],
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

    // Mock Company model's static find method using Pest's `$this->mock` helper
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
    $companyData = collect([
        ['id' => 1, 'role' => 'admin'],
        ['id' => 2, 'role' => 'employee'],
    ]);

    $createdUser = Mockery::spy(User::class)->makePartial(); // Spy to check method calls on created user
    $createdUser->id = 5; // Give it an ID for later mocking

    // Mock the 'settings' relationship *before* `setSettings` is implicitly called by createFromRequest
    $mockUserSettingsRelation = Mockery::mock(HasMany::class);
    $mockUserSettingsRelation->shouldReceive('updateOrCreate')
        ->once()
        ->with(['key' => 'language'], ['key' => 'language', 'value' => 'es']);
    $createdUser->shouldReceive('settings')->andReturn($mockUserSettingsRelation);

    // Mock static `create` call on User model
    $this->mock(User::class, function ($mock) use ($userPayload, $createdUser) {
        $mock->shouldReceive('create')
            ->once()
            ->with($userPayload)
            ->andReturn($createdUser);
    });

    $request->shouldReceive('getUserPayload')->once()->andReturn($userPayload);
    $request->shouldReceive('header')->once()->with('company')->andReturn($companyId);
    $request->companies = $companyData; // Assign the collection directly

    $this->mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('language', $companyId)
        ->andReturn('es');

    // Mock the user's companies relationship
    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('sync')
        ->once()
        ->with(collect([1, 2])); // Pluck of company IDs
    $createdUser->shouldReceive('companies')->andReturn($mockCompaniesRelation);

    // Mock BouncerFacade interactions for chained calls: Bouncer::scope($id)->sync($user)->roles($roles);
    $this->mockBouncerFacade->shouldReceive('scope')
        ->withArgs([1]) // For company ID 1
        ->once()
        ->andReturn(Mockery::mock()->shouldReceive('sync')->with($createdUser)->once()->andReturn(
            Mockery::mock()->shouldReceive('roles')->with(['admin'])->once()->getMock()
        )->getMock());

    $this->mockBouncerFacade->shouldReceive('scope')
        ->withArgs([2]) // For company ID 2
        ->once()
        ->andReturn(Mockery::mock()->shouldReceive('sync')->with($createdUser)->once()->andReturn(
            Mockery::mock()->shouldReceive('roles')->with(['employee'])->once()->getMock()
        )->getMock());

    $result = User::createFromRequest($request);

    expect($result)->toBe($createdUser);
    $createdUser->shouldHaveReceived('settings'); // Via setSettings
    $createdUser->shouldHaveReceived('companies');
});


// Test updateFromRequest
test('updateFromRequest updates user and syncs companies/roles', function () {
    $user = Mockery::spy(User::class)->makePartial();
    $user->id = 1; // Needs an ID for Bouncer

    $request = Mockery::mock(UserRequest::class);
    $userPayload = ['name' => 'Updated User', 'email' => 'updated@example.com'];
    $companyData = collect([
        ['id' => 1, 'role' => 'editor'],
        ['id' => 3, 'role' => 'viewer'],
    ]);

    $request->shouldReceive('getUserPayload')->once()->andReturn($userPayload);
    $request->companies = $companyData;

    $user->shouldReceive('update')
        ->once()
        ->with($userPayload);

    // Mock the user's relationships and methods
    $mockCompaniesRelation = Mockery::mock(BelongsToMany::class);
    $mockCompaniesRelation->shouldReceive('sync')
        ->once()
        ->with(collect([1, 3]));
    $user->shouldReceive('companies')->andReturn($mockCompaniesRelation);

    // Mock BouncerFacade interactions for chained calls: Bouncer::scope($id)->sync($user)->roles($roles);
    $this->mockBouncerFacade->shouldReceive('scope')
        ->withArgs([1]) // For company ID 1
        ->once()
        ->andReturn(Mockery::mock()->shouldReceive('sync')->with($user)->once()->andReturn(
            Mockery::mock()->shouldReceive('roles')->with(['editor'])->once()->getMock()
        )->getMock());

    $this->mockBouncerFacade->shouldReceive('scope')
        ->withArgs([3]) // For company ID 3
        ->once()
        ->andReturn(Mockery::mock()->shouldReceive('sync')->with($user)->once()->andReturn(
            Mockery::mock()->shouldReceive('roles')->with(['viewer'])->once()->getMock()
        )->getMock());

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
    // If owner_only is true and user is not owner, 'can' should not be called.
    $data_owner_only_true = (object)['data' => ['owner_only' => true, 'ability' => 'view', 'model' => null]];
    expect($user->checkAccess($data_owner_only_true))->toBeFalse();

    // If owner_only is false, but can() returns false for all checks, it should be false.
    $user = Mockery::mock(User::class); // Re-mock for a fresh state for this sub-scenario
    $user->shouldReceive('isOwner')->once()->andReturn(false);
    // `can` is called with model first, then without if model is not null.
    $user->shouldReceive('can')->once()->with('view', 'App\Models\Invoice')->andReturn(false);
    $user->shouldReceive('can')->once()->with('view')->andReturn(false); // Fallback can check

    $data_can_false = (object)['data' => ['owner_only' => false, 'ability' => 'view', 'model' => 'App\Models\Invoice']];
    expect($user->checkAccess($data_can_false))->toBeFalse();
});

// Test deleteUsers
test('deleteUsers deletes users and updates related records', function () {
    $userIds = [1, 2];

    // Create spies for User instances that will be "found"
    $mockUser1 = Mockery::spy(User::class)->makePartial();
    $mockUser1->id = 1;
    $mockUser2 = Mockery::spy(User::class)->makePartial();
    $mockUser2->id = 2;

    // Mock the static `User::find` method using Pest's helper
    $this->mock(User::class, function ($mock) use ($mockUser1, $mockUser2) {
        $mock->shouldReceive('find')
            ->with(1)->andReturn($mockUser1)
            ->with(2)->andReturn($mockUser2);
    });

    // Define expectations for user 1 (with relations needing update/delete)
    // For relations, mock the relation method to return a mock query builder that can then be chained.
    $mockUser1->shouldReceive('invoices')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(true)->shouldReceive('update')->with(['creator_id' => null])->once()->getMock());
    $mockUser1->shouldReceive('estimates')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser1->shouldReceive('customers')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(true)->shouldReceive('update')->with(['creator_id' => null])->once()->getMock());
    $mockUser1->shouldReceive('recurringInvoices')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser1->shouldReceive('expenses')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(true)->shouldReceive('update')->with(['creator_id' => null])->once()->getMock());
    $mockUser1->shouldReceive('payments')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser1->shouldReceive('items')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(true)->shouldReceive('update')->with(['creator_id' => null])->once()->getMock());
    $mockUser1->shouldReceive('settings')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(true)->shouldReceive('delete')->once()->getMock());
    $mockUser1->shouldReceive('delete')->once();

    // Define expectations for user 2 (without relations needing update/delete)
    $mockUser2->shouldReceive('invoices')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser2->shouldReceive('estimates')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser2->shouldReceive('customers')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser2->shouldReceive('recurringInvoices')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser2->shouldReceive('expenses')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser2->shouldReceive('payments')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser2->shouldReceive('items')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser2->shouldReceive('settings')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser2->shouldReceive('delete')->once(); // Still expects delete to be called

    $result = User::deleteUsers($userIds);

    expect($result)->toBeTrue();
    $mockUser1->shouldHaveReceived('delete')->once();
    $mockUser2->shouldHaveReceived('delete')->once();
});

test('deleteUsers handles non-existent users gracefully', function () {
    $userIds = [1, 999]; // 999 does not exist

    $mockUser1 = Mockery::spy(User::class)->makePartial();
    $mockUser1->id = 1;

    $this->mock(User::class, function ($mock) use ($mockUser1) {
        $mock->shouldReceive('find')
            ->with(1)->andReturn($mockUser1)
            ->with(999)->andReturn(null); // Non-existent user
    });

    // Define expectations for user 1 (no relations needing update/delete for simplicity)
    $mockUser1->shouldReceive('invoices')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser1->shouldReceive('estimates')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser1->shouldReceive('customers')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser1->shouldReceive('recurringInvoices')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser1->shouldReceive('expenses')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser1->shouldReceive('payments')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser1->shouldReceive('items')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser1->shouldReceive('settings')->andReturn(Mockery::mock()->shouldReceive('exists')->andReturn(false)->getMock());
    $mockUser1->shouldReceive('delete')->once();

    $result = User::deleteUsers($userIds);

    expect($result)->toBeTrue();
    $mockUser1->shouldHaveReceived('delete')->once();
    // No calls for user 999 as find returned null, so no delete or relation checks for it.
});

afterEach(function () {
    Mockery::close();
});