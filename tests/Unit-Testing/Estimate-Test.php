<?php

use Carbon\Carbon;
use Crater\Mail\SendEstimateMail;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Crater\Models\CustomField;
use Crater\Models\EmailLog;
use Crater\Models\Estimate;
use Crater\Models\EstimateItem;
use Crater\Models\ExchangeRateLog;
use Crater\Models\Invoice;
use Crater\Models\Tax;
use Crater\Models\User;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Vinkla\Hashids\Facades\Hashids;
use Barryvdh\DomPDF\Facade as PDF;

beforeEach(function () {
    // Clear mocks before each test
    Mockery::close();

    // Mock facades that don't need specific behavior per test or need to be swapped
    Mail::swap(Mockery::mock(Mail::class));
    Hashids::swap(Mockery::mock(Hashids::class));
    App::swap(Mockery::mock(App::class));
    URL::swap(Mockery::mock(URL::class)); // For url() helper
    Storage::swap(Mockery::mock(Storage::class));
    PDF::swap(Mockery::mock(PDF::class));
    View::swap(Mockery::mock(View::class)); // For view()->share()
    Str::swap(Mockery::mock(Str::class)); // For Str::before and Str::replace
});

// Define a dummy vite_asset function if it's not available in the test environment
if (!function_exists('vite_asset')) {
    function vite_asset($path) {
        return '/mock-asset-path/' . $path;
    }
}

test('it has correct fillable attributes', function () {
    $estimate = new Estimate();
    // 'guarded' implies everything is fillable except 'id'.
    $this->assertEquals(['id'], $estimate->getGuarded());
});

test('it uses the HasFactory trait', function () {
    $this->assertContains('Illuminate\Database\Eloquent\Factories\HasFactory', class_uses(Estimate::class));
});

test('it uses the InteractsWithMedia trait', function () {
    $this->assertContains('Spatie\MediaLibrary\InteractsWithMedia', class_uses(Estimate::class));
});

test('it uses the GeneratesPdfTrait', function () {
    $this->assertContains('Crater\Traits\GeneratesPdfTrait', class_uses(Estimate::class));
});

test('it uses the HasCustomFieldsTrait', function () {
    $this->assertContains('Crater\Traits\HasCustomFieldsTrait', class_uses(Estimate::class));
});

test('it has correct date attributes', function () {
    $estimate = new Estimate();
    $expectedDates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'estimate_date',
        'expiry_date'
    ];
    $this->assertEqualsCanonicalizing($expectedDates, $estimate->getDates());
});

test('it has correct appended attributes', function () {
    $estimate = new Estimate();
    $expectedAppends = [
        'formattedExpiryDate',
        'formattedEstimateDate',
        'estimatePdfUrl',
    ];
    $this->assertEqualsCanonicalizing($expectedAppends, $estimate->getAppends());
});

test('it casts attributes correctly', function () {
    $estimate = new Estimate();
    $expectedCasts = [
        'total' => 'integer',
        'tax' => 'integer',
        'sub_total' => 'integer',
        'discount' => 'float',
        'discount_val' => 'integer',
        'exchange_rate' => 'float'
    ];
    $this->assertEquals($expectedCasts, $estimate->getCasts());
});

test('getEstimatePdfUrlAttribute returns correct url', function () {
    URL::shouldReceive('to')
        ->once()
        ->with('/estimates/pdf/uniquehash123')
        ->andReturn('http://localhost/estimates/pdf/uniquehash123');

    $estimate = new Estimate(['unique_hash' => 'uniquehash123']);

    expect($estimate->estimatePdfUrl)->toBe('http://localhost/estimates/pdf/uniquehash123');
});

test('emailLogs relationship returns a morphMany relation', function () {
    $estimate = new Estimate();
    $relation = $estimate->emailLogs();

    expect($relation)->toBeInstanceOf(MorphMany::class);
    expect($relation->getRelated())->toBeInstanceOf(EmailLog::class);
    expect($relation->getMorphType())->toBe('mailable');
});

test('items relationship returns a hasMany relation', function () {
    $estimate = new Estimate();
    $relation = $estimate->items();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(EstimateItem::class);
});

test('customer relationship returns a belongsTo relation', function () {
    $estimate = new Estimate();
    $relation = $estimate->customer();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Customer::class);
    expect($relation->getForeignKeyName())->toBe('customer_id');
});

test('creator relationship returns a belongsTo relation', function () {
    $estimate = new Estimate();
    $relation = $estimate->creator();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getForeignKeyName())->toBe('creator_id');
});

test('company relationship returns a belongsTo relation', function () {
    $estimate = new Estimate();
    $relation = $estimate->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
});

test('currency relationship returns a belongsTo relation', function () {
    $estimate = new Estimate();
    $relation = $estimate->currency();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Currency::class);
});

test('taxes relationship returns a hasMany relation', function () {
    $estimate = new Estimate();
    $relation = $estimate->taxes();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Tax::class);
});

test('getFormattedExpiryDateAttribute returns formatted date', function () {
    Carbon::setTestNow(Carbon::parse('2023-01-01'));
    $estimate = new Estimate(['expiry_date' => '2023-12-25', 'company_id' => 1]);

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', 1)
        ->andReturn('Y-m-d');

    expect($estimate->formattedExpiryDate)->toBe('2023-12-25');

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', 1)
        ->andReturn('d/m/Y');

    expect($estimate->formattedExpiryDate)->toBe('25/12/2023');
});

test('getFormattedEstimateDateAttribute returns formatted date', function () {
    Carbon::setTestNow(Carbon::parse('2023-01-01'));
    $estimate = new Estimate(['estimate_date' => '2023-11-15', 'company_id' => 1]);

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', 1)
        ->andReturn('Y-m-d');

    expect($estimate->formattedEstimateDate)->toBe('2023-11-15');

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', 1)
        ->andReturn('m-d-Y');

    expect($estimate->formattedEstimateDate)->toBe('11-15-2023');
});

test('scopeEstimatesBetween applies date range filter', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('whereBetween')
        ->once()
        ->with('estimates.estimate_date', ['2023-01-01', '2023-01-31'])
        ->andReturn($query);

    $start = Carbon::parse('2023-01-01');
    $end = Carbon::parse('2023-01-31');

    Estimate::scopeEstimatesBetween($query, $start, $end);
});

test('scopeWhereStatus applies status filter', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('estimates.status', Estimate::STATUS_SENT)
        ->andReturn($query);

    Estimate::scopeWhereStatus($query, Estimate::STATUS_SENT);
});

test('scopeWhereEstimateNumber applies estimate number filter', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('estimates.estimate_number', 'LIKE', '%EST-001%')
        ->andReturn($query);

    Estimate::scopeWhereEstimateNumber($query, 'EST-001');
});

test('scopeWhereEstimate applies estimate ID filter', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('orWhere')
        ->once()
        ->with('id', 123)
        ->andReturn($query);

    Estimate::scopeWhereEstimate($query, 123);
});

test('scopeWhereSearch applies search filter for customer fields', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);

    $searchTerms = ['John', 'Doe'];

    // For each term, `whereHas` is called, and inside its callback, the relation query is built.
    $query->shouldReceive('whereHas')
        ->times(count($searchTerms))
        ->andReturnUsing(function ($relation, $callback) use ($query, $searchTerms) {
            expect($relation)->toBe('customer');

            $relationQuery = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);

            // Expect the inner query to be built for a single term
            $relationQuery->shouldReceive('where')
                ->once()
                ->with('name', 'LIKE', Mockery::any())
                ->andReturn($relationQuery);
            $relationQuery->shouldReceive('orWhere')
                ->once()
                ->with('contact_name', 'LIKE', Mockery::any())
                ->andReturn($relationQuery);
            $relationQuery->shouldReceive('orWhere')
                ->once()
                ->with('company_name', 'LIKE', Mockery::any())
                ->andReturn($relationQuery);

            $callback($relationQuery);
            return $query; // The outer query should be returned for chaining
        });

    Estimate::scopeWhereSearch($query, 'John Doe');
});

test('scopeApplyFilters applies all filters correctly', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);

    // Mock specific scopes that `applyFilters` calls
    $query->shouldReceive('whereSearch')
        ->with('test search')
        ->andReturn($query);
    $query->shouldReceive('whereEstimateNumber')
        ->with('EST-001')
        ->andReturn($query);
    $query->shouldReceive('whereStatus')
        ->with('SENT')
        ->andReturn($query);
    $query->shouldReceive('whereEstimate')
        ->with(1)
        ->andReturn($query);
    $query->shouldReceive('estimatesBetween')
        ->andReturnUsing(function ($start, $end) use ($query) {
            expect($start)->toBeInstanceOf(Carbon::class);
            expect($end)->toBeInstanceOf(Carbon::class);
            return $query;
        });
    $query->shouldReceive('whereCustomer')
        ->with(10)
        ->andReturn($query);
    $query->shouldReceive('whereOrder')
        ->with('estimate_date', 'asc')
        ->andReturn($query);

    $filters = [
        'search' => 'test search',
        'estimate_number' => 'EST-001',
        'status' => 'SENT',
        'estimate_id' => 1,
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'customer_id' => 10,
        'orderByField' => 'estimate_date',
        'orderBy' => 'asc',
    ];

    Estimate::scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters applies default order by values if not provided', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);

    $query->shouldReceive('whereOrder')
        ->with('sequence_number', 'desc') // Default values
        ->andReturn($query);

    $filters = [
        'orderByField' => null, // Not provided, should use default 'sequence_number'
        'orderBy' => null,     // Not provided, should use default 'desc'
    ];

    Estimate::scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters handles partial order by values', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);

    $query->shouldReceive('whereOrder')
        ->with('estimate_date', 'desc') // 'orderBy' is default
        ->andReturn($query);

    $filters = [
        'orderByField' => 'estimate_date',
        'orderBy' => null,
    ];

    Estimate::scopeApplyFilters($query, $filters);

    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('whereOrder')
        ->with('sequence_number', 'asc') // 'orderByField' is default
        ->andReturn($query);

    $filters = [
        'orderByField' => null,
        'orderBy' => 'asc',
    ];

    Estimate::scopeApplyFilters($query, $filters);
});

test('scopeApplyFilters does not apply filters if values are empty or null', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('whereEstimateNumber');
    $query->shouldNotReceive('whereStatus');
    $query->shouldNotReceive('whereEstimate');
    $query->shouldNotReceive('estimatesBetween');
    $query->shouldNotReceive('whereCustomer');
    $query->shouldNotReceive('whereOrder');

    $filters = [
        'search' => '',
        'estimate_number' => null,
        'status' => '',
        'estimate_id' => 0, // Assuming 0 is falsy for ID
        'from_date' => null,
        'to_date' => null,
        'customer_id' => null,
        'orderByField' => null,
        'orderBy' => null,
    ];

    Estimate::scopeApplyFilters($query, $filters);
});

test('scopeWhereOrder applies order by clause', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('orderBy')
        ->once()
        ->with('created_at', 'desc')
        ->andReturn($query);

    Estimate::scopeWhereOrder($query, 'created_at', 'desc');
});

test('scopeWhereCompany applies company filter from request header', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn(123);

    $query->shouldReceive('where')
        ->once()
        ->with('estimates.company_id', 123)
        ->andReturn($query);

    // Use a test helper to replace the global request() function
    test()->instance('request', $request);

    Estimate::scopeWhereCompany($query);
});

test('scopeWhereCustomer applies customer filter', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('estimates.customer_id', 456)
        ->andReturn($query);

    Estimate::scopeWhereCustomer($query, 456);
});

test('scopePaginateData returns all records when limit is "all"', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $expectedCollection = new Collection(['item1', 'item2']);
    $query->shouldReceive('get')
        ->once()
        ->andReturn($expectedCollection);

    $result = Estimate::scopePaginateData($query, 'all');

    expect($result)->toBe($expectedCollection);
});

test('scopePaginateData returns paginated data when limit is a number', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $paginatedResult = Mockery::mock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
    $query->shouldReceive('paginate')
        ->once()
        ->with(10)
        ->andReturn($paginatedResult);

    $result = Estimate::scopePaginateData($query, 10);

    expect($result)->toBe($paginatedResult);
});

test('createEstimate creates estimate and related data', function () {
    $request = Mockery::mock(Request::class);
    $estimate = Mockery::mock(Estimate::class)->makePartial(); // Make partial to allow calling real methods and mocking others
    $estimate->id = 1;
    $estimate->company_id = 1;
    $estimate->customer_id = 1;
    $estimate->exchange_rate = 1.0;
    $estimate->currency_id = 1;

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->nextSequenceNumber = 'SEQ001';
    $serialFormatter->nextCustomerSequenceNumber = 'CUSTSEQ001';

    // Mock dependencies
    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('__construct')
        ->andReturn($serialFormatter); // Ensure constructor returns our mock
    $serialFormatter->shouldReceive('setModel')->once()->with(Mockery::type(Estimate::class))->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCompany')->once()->with(1)->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCustomer')->once()->with(1)->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setNextNumbers')->once()->andReturn($serialFormatter);


    Hashids::shouldReceive('connection')
        ->once()
        ->with(Estimate::class)
        ->andReturnSelf();
    Hashids::shouldReceive('encode')
        ->once()
        ->with(1)
        ->andReturn('hashedid1');

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', 1)
        ->andReturn('1'); // Same currency, no ExchangeRateLog call

    ExchangeRateLog::shouldReceive('addExchangeRateLog')->never();

    // Mock self::create and self::createItems / self::createTaxes / addCustomFields
    $request->shouldReceive('getEstimatePayload')
        ->once()
        ->andReturn(['currency_id' => 1, 'customer_id' => 1]);
    $request->shouldReceive('has')
        ->with('estimateSend')
        ->andReturn(false); // Do not change status to SENT
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn(1);
    $request->shouldReceive('has')
        ->with('taxes')
        ->andReturn(false);
    $request->taxes = null;
    $request->customFields = null;

    // Mock static create method
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('create')
        ->once()
        ->with(['currency_id' => 1, 'customer_id' => 1])
        ->andReturn($estimate);

    // Mock internal saves of the $estimate object
    $estimate->shouldReceive('save')->once(); // one for sequence numbers

    // Mock createItems and createTaxes calls statically
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createItems')
        ->once()
        ->with($estimate, $request, $estimate->exchange_rate);
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createTaxes')
        ->never(); // because $request->has('taxes') is false

    $estimate->shouldReceive('addCustomFields')->never();

    $result = Estimate::createEstimate($request);

    expect($result)->toBe($estimate);
    expect($result->unique_hash)->toBe('hashedid1');
    expect($result->sequence_number)->toBe('SEQ001');
    expect($result->customer_sequence_number)->toBe('CUSTSEQ001');
    expect($result->status)->toBeNull(); // Not set to SENT
});

test('createEstimate sets status to SENT if estimateSend is true', function () {
    $request = Mockery::mock(Request::class);
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->id = 1;
    $estimate->company_id = 1;
    $estimate->customer_id = 1;
    $estimate->exchange_rate = 1.0;
    $estimate->currency_id = 1;
    $estimate->status = null; // Initial status

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->nextSequenceNumber = 'SEQ001';
    $serialFormatter->nextCustomerSequenceNumber = 'CUSTSEQ001';

    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('__construct')
        ->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setModel')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCompany')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCustomer')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setNextNumbers')->andReturn($serialFormatter);

    Hashids::shouldReceive('connection')->andReturnSelf();
    Hashids::shouldReceive('encode')->andReturn('hashedid1');
    CompanySetting::shouldReceive('getSetting')->andReturn('1');
    ExchangeRateLog::shouldReceive('addExchangeRateLog')->never();

    $request->shouldReceive('getEstimatePayload')
        ->andReturn(['currency_id' => 1, 'customer_id' => 1]);
    $request->shouldReceive('has')
        ->with('estimateSend')
        ->andReturn(true); // This is the change
    $request->shouldReceive('header')
        ->andReturn(1);
    $request->shouldReceive('has')
        ->with('taxes')
        ->andReturn(false);
    $request->taxes = null;
    $request->customFields = null;

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('create')
        ->with(['currency_id' => 1, 'customer_id' => 1, 'status' => Estimate::STATUS_SENT]) // Status should be sent
        ->andReturn($estimate);

    $estimate->shouldReceive('save')->once(); // for sequence numbers, status is set in create payload

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createItems');
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createTaxes')
        ->never();

    $result = Estimate::createEstimate($request);
    expect($result->status)->toBe(Estimate::STATUS_SENT); // Assert status change on the mock
});

test('createEstimate calls ExchangeRateLog if currency differs', function () {
    $request = Mockery::mock(Request::class);
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->id = 1;
    $estimate->company_id = 1;
    $estimate->customer_id = 1;
    $estimate->exchange_rate = 1.0;
    $estimate->currency_id = 2; // Different currency

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->nextSequenceNumber = 'SEQ001';
    $serialFormatter->nextCustomerSequenceNumber = 'CUSTSEQ001';

    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('__construct')
        ->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setModel')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCompany')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCustomer')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setNextNumbers')->andReturn($serialFormatter);

    Hashids::shouldReceive('connection')->andReturnSelf();
    Hashids::shouldReceive('encode')->andReturn('hashedid1');
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', 1)
        ->andReturn('1'); // Company currency is 1

    ExchangeRateLog::shouldReceive('addExchangeRateLog')
        ->once()
        ->with($estimate); // Should be called

    $request->shouldReceive('getEstimatePayload')
        ->andReturn(['currency_id' => 2, 'customer_id' => 1]); // Estimate currency is 2
    $request->shouldReceive('has')
        ->with('estimateSend')
        ->andReturn(false);
    $request->shouldReceive('header')
        ->andReturn(1);
    $request->shouldReceive('has')
        ->with('taxes')
        ->andReturn(false);
    $request->taxes = null;
    $request->customFields = null;

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('create')
        ->andReturn($estimate);

    $estimate->shouldReceive('save')->once(); // for sequence numbers

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createItems');
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createTaxes')
        ->never();

    Estimate::createEstimate($request);
});

test('createEstimate calls createTaxes if taxes are present', function () {
    $request = Mockery::mock(Request::class);
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->id = 1;
    $estimate->company_id = 1;
    $estimate->customer_id = 1;
    $estimate->exchange_rate = 1.0;
    $estimate->currency_id = 1;

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->nextSequenceNumber = 'SEQ001';
    $serialFormatter->nextCustomerSequenceNumber = 'CUSTSEQ001';

    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('__construct')
        ->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setModel')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCompany')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCustomer')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setNextNumbers')->andReturn($serialFormatter);

    Hashids::shouldReceive('connection')->andReturnSelf();
    Hashids::shouldReceive('encode')->andReturn('hashedid1');
    CompanySetting::shouldReceive('getSetting')->andReturn('1');
    ExchangeRateLog::shouldReceive('addExchangeRateLog')->never();

    $request->shouldReceive('getEstimatePayload')
        ->andReturn(['currency_id' => 1, 'customer_id' => 1]);
    $request->shouldReceive('has')
        ->with('estimateSend')
        ->andReturn(false);
    $request->shouldReceive('header')
        ->andReturn(1);
    $request->shouldReceive('has')
        ->with('taxes')
        ->andReturn(true);
    $request->taxes = [['amount' => 10]]; // Dummy tax data
    $request->customFields = null;

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('create')
        ->andReturn($estimate);

    $estimate->shouldReceive('save')->once();

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createItems');
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createTaxes')
        ->once()
        ->with($estimate, $request, $estimate->exchange_rate); // Should be called

    Estimate::createEstimate($request);
});

test('createEstimate calls addCustomFields if customFields are present', function () {
    $request = Mockery::mock(Request::class);
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->id = 1;
    $estimate->company_id = 1;
    $estimate->customer_id = 1;
    $estimate->exchange_rate = 1.0;
    $estimate->currency_id = 1;

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->nextSequenceNumber = 'SEQ001';
    $serialFormatter->nextCustomerSequenceNumber = 'CUSTSEQ001';

    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('__construct')
        ->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setModel')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCompany')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCustomer')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setNextNumbers')->andReturn($serialFormatter);

    Hashids::shouldReceive('connection')->andReturnSelf();
    Hashids::shouldReceive('encode')->andReturn('hashedid1');
    CompanySetting::shouldReceive('getSetting')->andReturn('1');
    ExchangeRateLog::shouldReceive('addExchangeRateLog')->never();

    $request->shouldReceive('getEstimatePayload')
        ->andReturn(['currency_id' => 1, 'customer_id' => 1]);
    $request->shouldReceive('has')
        ->with('estimateSend')
        ->andReturn(false);
    $request->shouldReceive('header')
        ->andReturn(1);
    $request->shouldReceive('has')
        ->with('taxes')
        ->andReturn(false);
    $request->taxes = null;
    $request->customFields = [['field_id' => 1, 'value' => 'test']]; // Dummy custom field data

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('create')
        ->andReturn($estimate);

    $estimate->shouldReceive('save')->once();
    $estimate->shouldReceive('addCustomFields')
        ->once()
        ->with($request->customFields); // Should be called

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createItems');
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createTaxes')
        ->never();

    Estimate::createEstimate($request);
});


test('updateEstimate updates estimate and related data', function () {
    $request = Mockery::mock(Request::class);
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->id = 1;
    $estimate->company_id = 1;
    $estimate->customer_id = 1;
    $estimate->exchange_rate = 1.0;
    $estimate->currency_id = 1;

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->nextCustomerSequenceNumber = 'UPDATEDCUSTSEQ';

    // Mock dependencies
    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('__construct')
        ->andReturn($serialFormatter); // Ensure constructor returns our mock
    $serialFormatter->shouldReceive('setModel')->once()->with($estimate)->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCompany')->once()->with(1)->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCustomer')->once()->with(2)->andReturn($serialFormatter); // Request customer_id
    $serialFormatter->shouldReceive('setModelObject')->once()->with(1)->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setNextNumbers')->once()->andReturn($serialFormatter);

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', 1)
        ->andReturn('1'); // Same currency, no ExchangeRateLog call

    ExchangeRateLog::shouldReceive('addExchangeRateLog')->never();

    // Mock self::create and self::createItems / self::createTaxes / addCustomFields
    $request->shouldReceive('getEstimatePayload')
        ->once()
        ->andReturn(['currency_id' => 1, 'customer_id' => 2]); // Updated customer_id
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn(1);
    $request->shouldReceive('has')
        ->with('taxes')
        ->andReturn(false);
    $request->taxes = null;
    $request->customFields = null;

    // Mock internal collection operations for items and their fields
    $field1 = Mockery::mock();
    $field1->shouldReceive('delete')->once();
    $field2 = Mockery::mock();
    $field2->shouldReceive('delete')->once();

    $item1 = Mockery::mock(EstimateItem::class);
    $item1->shouldReceive('fields->get')->andReturn(collect([$field1]));
    $item2 = Mockery::mock(EstimateItem::class);
    $item2->shouldReceive('fields->get')->andReturn(collect([$field2]));

    $estimate->items = collect([$item1, $item2]); // Set the items collection directly on mock

    // Mock methods on the $estimate instance
    $estimate->shouldReceive('update')
        ->once()
        ->with(Mockery::subset([
            'currency_id' => 1,
            'customer_id' => 2,
            'customer_sequence_number' => 'UPDATEDCUSTSEQ'
        ]))
        ->andReturn(true); // Indicate successful update

    $itemsRelation = Mockery::mock(HasMany::class);
    $estimate->shouldReceive('items')->andReturn($itemsRelation);
    $itemsRelation->shouldReceive('delete')->once();

    $taxesRelation = Mockery::mock(HasMany::class);
    $estimate->shouldReceive('taxes')->andReturn($taxesRelation);
    $taxesRelation->shouldReceive('delete')->once();


    // Mock static createItems and createTaxes methods
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createItems')
        ->once()
        ->with($estimate, $request, $estimate->exchange_rate);
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createTaxes')
        ->never();

    $estimate->shouldReceive('updateCustomFields')->never();

    // Mock the final find call
    $finalEstimate = Mockery::mock(Estimate::class);
    $finalEstimate->id = 1;
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('with')
        ->once()
        ->andReturnSelf();
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn($finalEstimate);

    $result = $estimate->updateEstimate($request);

    expect($result)->toBe($finalEstimate);
    expect($estimate->customer_sequence_number)->toBe('UPDATEDCUSTSEQ'); // Should be updated on mock instance
});

test('updateEstimate calls ExchangeRateLog if currency differs', function () {
    $request = Mockery::mock(Request::class);
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->id = 1;
    $estimate->company_id = 1;
    $estimate->customer_id = 1;
    $estimate->exchange_rate = 1.0;
    $estimate->currency_id = 2; // Different currency

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->nextCustomerSequenceNumber = 'UPDATEDCUSTSEQ';

    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('__construct')
        ->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setModel')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCompany')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCustomer')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setModelObject')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setNextNumbers')->andReturn($serialFormatter);

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', 1)
        ->andReturn('1'); // Company currency is 1

    ExchangeRateLog::shouldReceive('addExchangeRateLog')
        ->once()
        ->with($estimate); // Should be called

    $request->shouldReceive('getEstimatePayload')
        ->andReturn(['currency_id' => 2, 'customer_id' => 1]); // Estimate currency is 2
    $request->shouldReceive('header')
        ->andReturn(1);
    $request->shouldReceive('has')
        ->with('taxes')
        ->andReturn(false);
    $request->taxes = null;
    $request->customFields = null;

    $estimate->items = collect([]);
    $estimate->shouldReceive('update')->andReturn(true);

    $itemsRelation = Mockery::mock(HasMany::class);
    $estimate->shouldReceive('items')->andReturn($itemsRelation);
    $itemsRelation->shouldReceive('delete')->once();

    $taxesRelation = Mockery::mock(HasMany::class);
    $estimate->shouldReceive('taxes')->andReturn($taxesRelation);
    $taxesRelation->shouldReceive('delete')->once();

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createItems');
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createTaxes')
        ->never();

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('with')->andReturnSelf();
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('find')->andReturn($estimate);

    $estimate->updateEstimate($request);
});

test('updateEstimate calls createTaxes if taxes are present', function () {
    $request = Mockery::mock(Request::class);
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->id = 1;
    $estimate->company_id = 1;
    $estimate->customer_id = 1;
    $estimate->exchange_rate = 1.0;
    $estimate->currency_id = 1;

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->nextCustomerSequenceNumber = 'UPDATEDCUSTSEQ';

    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('__construct')
        ->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setModel')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCompany')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCustomer')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setModelObject')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setNextNumbers')->andReturn($serialFormatter);

    CompanySetting::shouldReceive('getSetting')->andReturn('1');
    ExchangeRateLog::shouldReceive('addExchangeRateLog')->never();

    $request->shouldReceive('getEstimatePayload')
        ->andReturn(['currency_id' => 1, 'customer_id' => 1]);
    $request->shouldReceive('header')
        ->andReturn(1);
    $request->shouldReceive('has')
        ->with('taxes')
        ->andReturn(true);
    $request->taxes = [['amount' => 20]]; // Dummy tax data
    $request->customFields = null;

    $estimate->items = collect([]);
    $estimate->shouldReceive('update')->andReturn(true);

    $itemsRelation = Mockery::mock(HasMany::class);
    $estimate->shouldReceive('items')->andReturn($itemsRelation);
    $itemsRelation->shouldReceive('delete')->once();

    $taxesRelation = Mockery::mock(HasMany::class);
    $estimate->shouldReceive('taxes')->andReturn($taxesRelation);
    $taxesRelation->shouldReceive('delete')->once();

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createItems');
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createTaxes')
        ->once()
        ->with($estimate, $request, $estimate->exchange_rate); // Should be called

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('with')->andReturnSelf();
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('find')->andReturn($estimate);

    $estimate->updateEstimate($request);
});

test('updateEstimate calls updateCustomFields if customFields are present', function () {
    $request = Mockery::mock(Request::class);
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->id = 1;
    $estimate->company_id = 1;
    $estimate->customer_id = 1;
    $estimate->exchange_rate = 1.0;
    $estimate->currency_id = 1;

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->nextCustomerSequenceNumber = 'UPDATEDCUSTSEQ';

    Mockery::mock('alias:' . SerialNumberFormatter::class)
        ->shouldReceive('__construct')
        ->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setModel')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCompany')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setCustomer')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setModelObject')->andReturn($serialFormatter);
    $serialFormatter->shouldReceive('setNextNumbers')->andReturn($serialFormatter);

    CompanySetting::shouldReceive('getSetting')->andReturn('1');
    ExchangeRateLog::shouldReceive('addExchangeRateLog')->never();

    $request->shouldReceive('getEstimatePayload')
        ->andReturn(['currency_id' => 1, 'customer_id' => 1]);
    $request->shouldReceive('header')
        ->andReturn(1);
    $request->shouldReceive('has')
        ->with('taxes')
        ->andReturn(false);
    $request->taxes = null;
    $request->customFields = [['field_id' => 1, 'value' => 'updated']]; // Dummy custom field data

    $estimate->items = collect([]);
    $estimate->shouldReceive('update')->andReturn(true);

    $itemsRelation = Mockery::mock(HasMany::class);
    $estimate->shouldReceive('items')->andReturn($itemsRelation);
    $itemsRelation->shouldReceive('delete')->once();

    $taxesRelation = Mockery::mock(HasMany::class);
    $estimate->shouldReceive('taxes')->andReturn($taxesRelation);
    $taxesRelation->shouldReceive('delete')->once();

    $estimate->shouldReceive('updateCustomFields')
        ->once()
        ->with($request->customFields); // Should be called

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createItems');
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('createTaxes')
        ->never();

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('with')->andReturnSelf();
    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('find')->andReturn($estimate);

    $estimate->updateEstimate($request);
});


test('createItems creates estimate items and nested taxes/custom fields', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->exchange_rate = 0.5;
    $request = Mockery::mock(Request::class);
    $request->items = [
        [
            'price' => 100,
            'discount_val' => 10,
            'total' => 90,
            'taxes' => [
                ['name' => 'VAT', 'amount' => 5, 'tax_type_id' => 1],
                ['name' => 'Sales', 'amount' => 2, 'tax_type_id' => 2],
            ],
            'custom_fields' => [
                ['field_id' => 1, 'value' => 'item field 1'],
            ],
        ],
        [
            'price' => 200,
            'discount_val' => 0,
            'total' => 200,
            'taxes' => [], // No taxes
        ],
        [
            'price' => 300,
            'discount_val' => 0,
            'total' => 300,
            'custom_fields' => [], // No custom fields
        ],
        [
            'price' => 400,
            'discount_val' => 0,
            'total' => 400,
            'taxes' => [
                ['name' => 'VAT', 'amount' => null, 'tax_type_id' => 1], // Null tax amount
            ],
        ],
    ];
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn(1);

    $estimate->tax = 10; // For base_tax calculation

    // Mock item creation
    $estimateItemsRelation = Mockery::mock(HasMany::class);
    $estimate->shouldReceive('items')->andReturn($estimateItemsRelation);

    // First item
    $item1 = Mockery::mock(EstimateItem::class);
    $item1->shouldReceive('taxes->create')
        ->once()
        ->with(Mockery::subset([
            'name' => 'VAT',
            'amount' => 5,
            'tax_type_id' => 1,
            'company_id' => 1
        ]));
    $item1->shouldReceive('taxes->create')
        ->once()
        ->with(Mockery::subset([
            'name' => 'Sales',
            'amount' => 2,
            'tax_type_id' => 2,
            'company_id' => 1
        ]));
    $item1->shouldReceive('addCustomFields')
        ->once()
        ->with($request->items[0]['custom_fields']);

    $estimateItemsRelation->shouldReceive('create')
        ->once()
        ->with(Mockery::subset([
            'company_id' => 1,
            'exchange_rate' => 0.5,
            'base_price' => 50.0, // 100 * 0.5
            'base_discount_val' => 5.0, // 10 * 0.5
            'base_tax' => 5.0, // 10 * 0.5
            'base_total' => 45.0, // 90 * 0.5
        ]))
        ->andReturn($item1);

    // Second item (no taxes, no custom fields)
    $item2 = Mockery::mock(EstimateItem::class);
    $item2->shouldNotReceive('taxes->create');
    $item2->shouldNotReceive('addCustomFields');
    $estimateItemsRelation->shouldReceive('create')
        ->once()
        ->with(Mockery::subset([
            'company_id' => 1,
            'exchange_rate' => 0.5,
            'base_price' => 100.0,
            'base_discount_val' => 0.0,
            'base_tax' => 5.0,
            'base_total' => 100.0,
        ]))
        ->andReturn($item2);

    // Third item (no custom fields)
    $item3 = Mockery::mock(EstimateItem::class);
    $item3->shouldNotReceive('addCustomFields');
    $estimateItemsRelation->shouldReceive('create')
        ->once()
        ->with(Mockery::subset([
            'company_id' => 1,
            'exchange_rate' => 0.5,
            'base_price' => 150.0,
            'base_discount_val' => 0.0,
            'base_tax' => 5.0,
            'base_total' => 150.0,
        ]))
        ->andReturn($item3);

    // Fourth item (tax with null amount)
    $item4 = Mockery::mock(EstimateItem::class);
    $item4->shouldNotReceive('taxes->create'); // Should not create tax with null amount
    $item4->shouldNotReceive('addCustomFields');
    $estimateItemsRelation->shouldReceive('create')
        ->once()
        ->with(Mockery::subset([
            'company_id' => 1,
            'exchange_rate' => 0.5,
            'base_price' => 200.0,
            'base_discount_val' => 0.0,
            'base_tax' => 5.0,
            'base_total' => 200.0,
        ]))
        ->andReturn($item4);

    Estimate::createItems($estimate, $request, 0.5);
});

test('createTaxes creates estimate taxes', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->currency_id = 10;
    $request = Mockery::mock(Request::class);
    $request->taxes = [
        ['name' => 'Global Tax 1', 'amount' => 20],
        ['name' => 'Global Tax 2', 'amount' => null], // Should be skipped
    ];
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn(1);

    $estimateTaxesRelation = Mockery::mock(HasMany::class);
    $estimate->shouldReceive('taxes')->andReturn($estimateTaxesRelation);

    $estimateTaxesRelation->shouldReceive('create')
        ->once()
        ->with(Mockery::subset([
            'name' => 'Global Tax 1',
            'amount' => 20,
            'company_id' => 1,
            'exchange_rate' => 0.5,
            'base_amount' => 10.0, // 20 * 0.5
            'currency_id' => 10,
        ]));

    Estimate::createTaxes($estimate, $request, 0.5);
});

test('sendEstimateData returns formatted data', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->shouldAllowMockingProtectedMethods()->makePartial(); // Allow mocking trait methods

    $customer = Mockery::mock(Customer::class);
    $customer->shouldReceive('toArray')->andReturn(['customer_data']);
    $estimate->customer = $customer;

    $company = Mockery::mock(Company::class);
    $company->shouldReceive('toArray')->andReturn(['company_data']);
    $estimate->company = $company;

    $estimate->shouldReceive('toArray')->andReturn(['estimate_data']);
    $estimate->shouldReceive('getEmailBody')->once()->with('email body')->andReturn('formatted body');
    $estimate->shouldReceive('getEmailAttachmentSetting')->once()->andReturn(true);
    $estimate->shouldReceive('getPDFData')->once()->andReturn('pdf data');

    $initialData = ['body' => 'email body', 'subject' => 'test'];
    $result = $estimate->sendEstimateData($initialData);

    expect($result)->toEqual([
        'body' => 'formatted body',
        'subject' => 'test',
        'estimate' => ['estimate_data'],
        'user' => ['customer_data'],
        'company' => ['company_data'],
        'attach' => ['data' => 'pdf data'],
    ]);
});

test('sendEstimateData returns null attachment if setting is false', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->shouldAllowMockingProtectedMethods()->makePartial(); // Allow mocking trait methods

    $customer = Mockery::mock(Customer::class);
    $customer->shouldReceive('toArray')->andReturn(['customer_data']);
    $estimate->customer = $customer;

    $company = Mockery::mock(Company::class);
    $company->shouldReceive('toArray')->andReturn(['company_data']);
    $estimate->company = $company;

    $estimate->shouldReceive('toArray')->andReturn(['estimate_data']);
    $estimate->shouldReceive('getEmailBody')->andReturn('formatted body');
    $estimate->shouldReceive('getEmailAttachmentSetting')->once()->andReturn(false); // No attachment
    $estimate->shouldNotReceive('getPDFData');

    $initialData = ['body' => 'email body'];
    $result = $estimate->sendEstimateData($initialData);

    expect($result['attach']['data'])->toBeNull();
});

test('send sends email and updates status if draft', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->status = Estimate::STATUS_DRAFT; // Initial status
    $estimate->shouldReceive('sendEstimateData')
        ->once()
        ->with(['to' => 'test@example.com'])
        ->andReturn(['to' => 'test@example.com', 'estimate' => [], 'user' => [], 'company' => [], 'body' => 'formatted body', 'attach' => ['data' => null]]);

    Mail::shouldReceive('to')
        ->once()
        ->with('test@example.com')
        ->andReturnSelf();
    Mail::shouldReceive('send')
        ->once()
        ->with(Mockery::type(SendEstimateMail::class));

    $estimate->shouldReceive('save')
        ->once(); // Should be called to update status

    $result = $estimate->send(['to' => 'test@example.com']);

    expect($estimate->status)->toBe(Estimate::STATUS_SENT); // Status updated
    expect($result)->toEqual([
        'success' => true,
        'type' => 'send',
    ]);
});

test('send sends email but does not update status if not draft', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->status = Estimate::STATUS_VIEWED; // Not DRAFT
    $estimate->shouldReceive('sendEstimateData')
        ->once()
        ->andReturn(['to' => 'test@example.com', 'estimate' => [], 'user' => [], 'company' => [], 'body' => 'formatted body', 'attach' => ['data' => null]]);

    Mail::shouldReceive('to')
        ->once()
        ->andReturnSelf();
    Mail::shouldReceive('send')
        ->once();

    $estimate->shouldNotReceive('save'); // Should not be called

    $estimate->send(['to' => 'test@example.com']);

    expect($estimate->status)->toBe(Estimate::STATUS_VIEWED); // Status unchanged
});

test('getPDFData returns PDF load view for non-preview requests', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->id = 1;
    $estimate->tax_per_item = 'NO'; // No item tax aggregation
    $estimate->template_name = 'template1';
    $estimate->company_id = 1;

    $company = Mockery::mock(Company::class);
    $company->id = 1;
    $company->logo_path = 'logo.png';

    Mockery::mock('alias:' . CustomField::class)
        ->shouldReceive('where')
        ->once()
        ->with('model_type', 'Item')
        ->andReturnSelf();
    Mockery::mock('alias:' . CustomField::class)
        ->shouldReceive('get')
        ->once()
        ->andReturn(collect(['itemCustomField']));

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('language', 1)
        ->andReturn('en');

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn($estimate);
    Mockery::mock('alias:' . Company::class)
        ->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn($company);

    App::shouldReceive('setLocale')
        ->once()
        ->with('en');

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::subset([
            'estimate' => $estimate,
            'customFields' => collect(['itemCustomField']),
            'logo' => 'logo.png',
            'company_address' => 'company address',
            'shipping_address' => 'shipping address',
            'billing_address' => 'billing address',
            'notes' => 'notes content',
            'taxes' => Mockery::type(Collection::class), // Empty collection in this case
        ]));

    // Mock PDF Facade
    $pdfInstance = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    PDF::shouldReceive('loadView')
        ->once()
        ->with('app.pdf.estimate.template1')
        ->andReturn($pdfInstance);

    // Mock request() helper for preview
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')
        ->with('preview')
        ->andReturn(false);
    test()->instance('request', $request);

    // Mock trait methods
    $estimate->shouldReceive('getCompanyAddress')->andReturn('company address');
    $estimate->shouldReceive('getCustomerShippingAddress')->andReturn('shipping address');
    $estimate->shouldReceive('getCustomerBillingAddress')->andReturn('billing address');
    $estimate->shouldReceive('getNotes')->andReturn('notes content');

    $result = $estimate->getPDFData();

    expect($result)->toBe($pdfInstance);
});

test('getPDFData returns view directly for preview requests', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->id = 1;
    $estimate->tax_per_item = 'NO';
    $estimate->template_name = 'template1';
    $estimate->company_id = 1;

    $company = Mockery::mock(Company::class);
    $company->id = 1;
    $company->logo_path = null;

    Mockery::mock('alias:' . CustomField::class)
        ->shouldReceive('where')
        ->andReturnSelf();
    Mockery::mock('alias:' . CustomField::class)
        ->shouldReceive('get')
        ->andReturn(collect([]));

    CompanySetting::shouldReceive('getSetting')->andReturn('en');

    Mockery::mock('alias:' . Estimate::class)
        ->shouldReceive('find')
        ->andReturn($estimate);
    Mockery::mock('alias:' . Company::class)
        ->shouldReceive('find')
        ->andReturn($company);

    App::shouldReceive('setLocale');

    View::shouldReceive('share');
    View::shouldReceive('make') // This is what `view('...')` implicitly calls
        ->once()
        ->with('app.pdf.estimate.template1')
        ->andReturn('rendered view');

    PDF::shouldNotReceive('loadView'); // Should not be called for preview

    // Mock request() helper for preview
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')
        ->with('preview')
        ->andReturn(true);
    test()->instance('request', $request);

    // Mock trait methods
    $estimate->shouldReceive('getCompanyAddress')->andReturn(false);
    $estimate->shouldReceive('getCustomerShippingAddress')->andReturn(false);
    $estimate->shouldReceive('getCustomerBillingAddress')->andReturn(false);
    $estimate->shouldReceive('getNotes')->andReturn('');

    $result = $estimate->getPDFData();

    expect($result)->toBe('rendered view');
});

test('getPDFData aggregates taxes when tax_per_item is YES', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->id = 1;
    $estimate->tax_per_item = 'YES';
    $estimate->template_name = 'template1';
    $estimate->company_id = 1;

    $tax1 = (object)['tax_type_id' => 1, 'amount' => 10];
    $tax2 = (object)['tax_type_id' => 2, 'amount' => 5];
    $tax3 = (object)['tax_type_id' => 1, 'amount' => 15]; // Same tax_type_id as tax1

    $item1 = Mockery::mock(EstimateItem::class);
    $item1->taxes = collect([$tax1, $tax2]);
    $item2 = Mockery::mock(EstimateItem::class);
    $item2->taxes = collect([$tax3]);

    $estimate->items = collect([$item1, $item2]);

    $company = Mockery::mock(Company::class);
    $company->id = 1;
    Mockery::mock('alias:' . CustomField::class)->shouldReceive('where')->andReturnSelf()->shouldReceive('get')->andReturn(collect());
    CompanySetting::shouldReceive('getSetting')->andReturn('en');
    Mockery::mock('alias:' . Estimate::class)->shouldReceive('find')->andReturn($estimate);
    Mockery::mock('alias:' . Company::class)->shouldReceive('find')->andReturn($company);
    App::shouldReceive('setLocale');

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($args) {
            $taxes = $args['taxes'];
            expect($taxes)->toBeInstanceOf(Collection::class);
            expect($taxes)->toHaveCount(2);

            $aggregatedTax1 = $taxes->first(fn($t) => $t->tax_type_id == 1);
            expect($aggregatedTax1->amount)->toBe(25); // 10 + 15

            $aggregatedTax2 = $taxes->first(fn($t) => $t->tax_type_id == 2);
            expect($aggregatedTax2->amount)->toBe(5);

            return true;
        }));

    PDF::shouldReceive('loadView')->andReturn('pdf instance');

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('has')->andReturn(false);
    test()->instance('request', $request);

    $estimate->shouldReceive('getCompanyAddress')->andReturn('');
    $estimate->shouldReceive('getCustomerShippingAddress')->andReturn('');
    $estimate->shouldReceive('getCustomerBillingAddress')->andReturn('');
    $estimate->shouldReceive('getNotes')->andReturn('');

    $result = $estimate->getPDFData();
    expect($result)->toBe('pdf instance');
});

test('getCompanyAddress returns false if company address does not exist', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->company_id = 1;

    $company = Mockery::mock(Company::class);
    $company->shouldReceive('address->exists')->andReturn(false);
    $estimate->company = $company;

    expect($estimate->getCompanyAddress())->toBeFalse();
});

test('getCompanyAddress returns formatted string if company address exists', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->company_id = 1;

    $company = Mockery::mock(Company::class);
    $company->shouldReceive('address->exists')->andReturn(true);
    $estimate->company = $company;

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('estimate_company_address_format', 1)
        ->andReturn('Company Address Format');

    // Mock the trait method
    $estimate->shouldReceive('getFormattedString')
        ->once()
        ->with('Company Address Format')
        ->andReturn('Formatted Company Address');

    expect($estimate->getCompanyAddress())->toBe('Formatted Company Address');
});

test('getCustomerShippingAddress returns false if customer shipping address does not exist', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->company_id = 1;

    $customer = Mockery::mock(Customer::class);
    $customer->shouldReceive('shippingAddress->exists')->andReturn(false);
    $estimate->customer = $customer;

    expect($estimate->getCustomerShippingAddress())->toBeFalse();
});

test('getCustomerShippingAddress returns formatted string if customer shipping address exists', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->company_id = 1;

    $customer = Mockery::mock(Customer::class);
    $customer->shouldReceive('shippingAddress->exists')->andReturn(true);
    $estimate->customer = $customer;

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('estimate_shipping_address_format', 1)
        ->andReturn('Shipping Address Format');

    // Mock the trait method
    $estimate->shouldReceive('getFormattedString')
        ->once()
        ->with('Shipping Address Format')
        ->andReturn('Formatted Shipping Address');

    expect($estimate->getCustomerShippingAddress())->toBe('Formatted Shipping Address');
});

test('getCustomerBillingAddress returns false if customer billing address does not exist', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->company_id = 1;

    $customer = Mockery::mock(Customer::class);
    $customer->shouldReceive('billingAddress->exists')->andReturn(false);
    $estimate->customer = $customer;

    expect($estimate->getCustomerBillingAddress())->toBeFalse();
});

test('getCustomerBillingAddress returns formatted string if customer billing address exists', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->company_id = 1;

    $customer = Mockery::mock(Customer::class);
    $customer->shouldReceive('billingAddress->exists')->andReturn(true);
    $estimate->customer = $customer;

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('estimate_billing_address_format', 1)
        ->andReturn('Billing Address Format');

    // Mock the trait method
    $estimate->shouldReceive('getFormattedString')
        ->once()
        ->with('Billing Address Format')
        ->andReturn('Formatted Billing Address');

    expect($estimate->getCustomerBillingAddress())->toBe('Formatted Billing Address');
});

test('getNotes returns formatted notes string', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->notes = 'Some notes content {field}';

    // Mock the trait method
    $estimate->shouldReceive('getFormattedString')
        ->once()
        ->with('Some notes content {field}')
        ->andReturn('Formatted Notes Content');

    expect($estimate->getNotes())->toBe('Formatted Notes Content');
});

test('getEmailAttachmentSetting returns true when setting is not NO', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->company_id = 1;

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('estimate_email_attachment', 1)
        ->andReturn('YES');

    expect($estimate->getEmailAttachmentSetting())->toBeTrue();

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('estimate_email_attachment', 1)
        ->andReturn('ANYTHING_ELSE');

    expect($estimate->getEmailAttachmentSetting())->toBeTrue();
});

test('getEmailAttachmentSetting returns false when setting is NO', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->company_id = 1;

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('estimate_email_attachment', 1)
        ->andReturn('NO');

    expect($estimate->getEmailAttachmentSetting())->toBeFalse();
});

test('getEmailBody replaces placeholders and removes unmatched ones', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->shouldAllowMockingProtectedMethods()->makePartial();

    $estimate->shouldReceive('getFieldsArray')
        ->once()
        ->andReturn(['{CUSTOMER_NAME}' => 'John Doe']);
    $estimate->shouldReceive('getExtraFields')
        ->once()
        ->andReturn(['{ESTIMATE_NUMBER}' => 'EST-001']);

    $body = "Hello {CUSTOMER_NAME}, your estimate number is {ESTIMATE_NUMBER}. Unmatched {FIELD}.";
    $expectedBody = "Hello John Doe, your estimate number is EST-001. Unmatched .";

    expect($estimate->getEmailBody($body))->toBe($expectedBody);
});

test('getExtraFields returns correct array of placeholders and values', function () {
    Carbon::setTestNow(Carbon::parse('2023-01-01'));
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->formattedEstimateDate = '2023-01-01'; // Mock accessor
    $estimate->formattedExpiryDate = '2023-01-31'; // Mock accessor
    $estimate->estimate_number = 'EST-TEST';
    $estimate->reference_number = 'REF-TEST';

    $expectedFields = [
        '{ESTIMATE_DATE}' => '2023-01-01',
        '{ESTIMATE_EXPIRY_DATE}' => '2023-01-31',
        '{ESTIMATE_NUMBER}' => 'EST-TEST',
        '{ESTIMATE_REF_NUMBER}' => 'REF-TEST',
    ];

    expect($estimate->getExtraFields())->toEqual($expectedFields);
});

test('estimateTemplates returns correctly formatted array of templates', function () {
    Storage::disk('views')->shouldReceive('files')
        ->once()
        ->with('/app/pdf/estimate')
        ->andReturn([
            'path/to/app/pdf/estimate/template1.blade.php',
            'path/to/app/pdf/estimate/template2.blade.php',
        ]);

    Str::shouldReceive('before')
        ->with('template1.blade.php', '.blade.php')
        ->andReturn('template1');
    Str::shouldReceive('before')
        ->with('template2.blade.php', '.blade.php')
        ->andReturn('template2');

    $expected = [
        ['name' => 'template1', 'path' => '/mock-asset-path/img/PDF/template1.png'],
        ['name' => 'template2', 'path' => '/mock-asset-path/img/PDF/template2.png'],
    ];

    $result = Estimate::estimateTemplates();

    expect($result)->toEqual($expected);
});

test('getInvoiceTemplateName returns converted template name if available', function () {
    $estimate = new Estimate(['template_name' => 'estimate1']);

    Mockery::mock('alias:' . Invoice::class)
        ->shouldReceive('invoiceTemplates')
        ->once()
        ->andReturn([
            ['name' => 'invoice1'],
            ['name' => 'invoice2'],
        ]);

    Str::shouldReceive('replace')
        ->once()
        ->with('estimate', 'invoice', 'estimate1')
        ->andReturn('invoice1');

    expect($estimate->getInvoiceTemplateName())->toBe('invoice1');
});

test('getInvoiceTemplateName returns default invoice1 if converted template not available', function () {
    $estimate = new Estimate(['template_name' => 'estimate_non_existent']);

    Mockery::mock('alias:' . Invoice::class)
        ->shouldReceive('invoiceTemplates')
        ->once()
        ->andReturn([
            ['name' => 'invoice1'],
            ['name' => 'invoice2'],
        ]);

    Str::shouldReceive('replace')
        ->once()
        ->with('estimate', 'invoice', 'estimate_non_existent')
        ->andReturn('invoice_non_existent');

    expect($estimate->getInvoiceTemplateName())->toBe('invoice1');
});

test('checkForEstimateConvertAction deletes estimate if setting is delete_estimate', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->company_id = 1;

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('estimate_convert_action', 1)
        ->andReturn('delete_estimate');

    $estimate->shouldReceive('delete')
        ->once()
        ->andReturn(true);

    expect($estimate->checkForEstimateConvertAction())->toBeTrue();
});

test('checkForEstimateConvertAction marks estimate as accepted if setting is mark_estimate_as_accepted', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->company_id = 1;
    $estimate->status = Estimate::STATUS_SENT; // Initial status

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('estimate_convert_action', 1)
        ->andReturn('mark_estimate_as_accepted');

    $estimate->shouldReceive('save')
        ->once();

    expect($estimate->checkForEstimateConvertAction())->toBeTrue();
    expect($estimate->status)->toBe(Estimate::STATUS_ACCEPTED);
});

test('checkForEstimateConvertAction does nothing if setting is other value', function () {
    $estimate = Mockery::mock(Estimate::class)->makePartial();
    $estimate->company_id = 1;
    $estimate->status = Estimate::STATUS_SENT; // Initial status

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('estimate_convert_action', 1)
        ->andReturn('do_nothing'); // Neither 'delete_estimate' nor 'mark_estimate_as_accepted'

    $estimate->shouldNotReceive('delete');
    $estimate->shouldNotReceive('save');

    expect($estimate->checkForEstimateConvertAction())->toBeTrue();
    expect($estimate->status)->toBe(Estimate::STATUS_SENT); // Status unchanged
});




afterEach(function () {
    Mockery::close();
});
