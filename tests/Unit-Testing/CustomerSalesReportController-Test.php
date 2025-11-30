<?php

use Carbon\Carbon;
use Crater\Http\Controllers\V1\Admin\Report\CustomerSalesReportController;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\Customer;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Mockery\MockInterface;

/*
 * Note: Pest automatically handles `use` statements for classes in the current namespace
 * and some common Laravel facades/classes. For external classes or aliased facades,
 * explicit `use` statements might be needed at the top of the test file, but
 * the prompt requests no such statements unless absolutely necessary.
 * Given this constraint, `Mockery` is used for aliased mocks (e.g., `alias:PDF`)
 * and direct class names are used where `uses()` or Laravel's test setup
 * would typically handle them (e.g., `Carbon`).
 */

beforeEach(function () {
    // Clear mocks before each test
    Mockery::close();

    // Mock PDF facade (using alias for static facade methods)
    $this->pdfMock = Mockery::mock('alias:PDF');
    $this->pdfMock->shouldReceive('loadView')->andReturnSelf();
    $this->pdfMock->shouldReceive('download')->andReturn(Mockery::mock(Response::class));
    $this->pdfMock->shouldReceive('stream')->andReturn(Mockery::mock(Response::class));

    // Mock App facade (using alias for static facade methods)
    $this->appMock = Mockery::mock('alias:App');
    $this->appMock->shouldReceive('setLocale')->with(Mockery::type('string'))->andReturnNull();

    // Mock View facade (using alias for static facade methods which are called by the `view()` helper)
    $this->viewMock = Mockery::mock('alias:View');
    $this->viewMock->shouldReceive('share')->with(Mockery::type('array'))->andReturnNull();
    // For the `return view(...)` case when preview is requested
    $this->viewMock->shouldReceive('make')->with('app.pdf.reports.sales-customers')->andReturn('html_content');


    // Mock Company model
    $this->companyModelMock = Mockery::mock('alias:Crater\Models\Company');
    $this->companyMock = (object)['id' => 1, 'unique_hash' => 'test_hash', 'name' => 'Test Company'];
    $this->companyModelMock->shouldReceive('where->first')
        ->andReturn($this->companyMock)
        ->byDefault(); // Default behavior if not explicitly overridden

    // Mock CompanySetting model
    $this->companySettingModelMock = Mockery::mock('alias:Crater\Models\CompanySetting');
    $this->companySettingModelMock->shouldReceive('getSetting')
        ->with('language', $this->companyMock->id)
        ->andReturn('en')
        ->byDefault();
    $this->companySettingModelMock->shouldReceive('getSetting')
        ->with('carbon_date_format', $this->companyMock->id)
        ->andReturn('Y-m-d') // Default date format
        ->byDefault();
    $this->companySettingModelMock->shouldReceive('getSetting')
        ->with('currency', $this->companyMock->id)
        ->andReturn(1) // Default Currency ID
        ->byDefault();

    $this->defaultColorSettingsCollection = new Collection([
        (object)['option' => 'primary_text_color', 'value' => '#000000'],
        (object)['option' => 'heading_text_color', 'value' => '#333333'],
    ]);
    $this->companySettingModelMock->shouldReceive('whereIn->whereCompany->get')
        ->andReturn($this->defaultColorSettingsCollection)
        ->byDefault();

    // Mock Currency model
    $this->currencyModelMock = Mockery::mock('alias:Crater\Models\Currency');
    $this->currencyMock = (object)['id' => 1, 'name' => 'USD', 'code' => 'USD', 'symbol' => '$'];
    $this->currencyModelMock->shouldReceive('findOrFail')->andReturn($this->currencyMock)->byDefault();

    // Mock Customer model - This requires complex chaining for `with`, `where`, `applyInvoiceFilters`, `get`
    $this->customerModelMock = Mockery::mock('alias:Crater\Models\Customer');
    $this->customerQueryBuilderMock = Mockery::mock(EloquentBuilder::class); // Mock the query builder
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->byDefault();
    $this->customerQueryBuilderMock->shouldReceive('where')->andReturn($this->customerQueryBuilderMock)->byDefault();
    $this->customerQueryBuilderMock->shouldReceive('applyInvoiceFilters')->andReturn($this->customerQueryBuilderMock)->byDefault();

    // Override the `__invoke` method's `authorize` call for unit testing isolation.
    // We mock the controller to make `authorize` always pass, so we can test the logic that follows.
    $this->controller = Mockery::mock(CustomerSalesReportController::class)->makePartial();
    $this->controller->shouldReceive('authorize')->with('view report', $this->companyMock)->andReturn(true)->byDefault();
});

afterEach(function () {
    Mockery::close();
    Carbon::setTestNow(null); // Clear Carbon's test now state
});

/**
 * Helper for creating mock invoice objects.
 *
 * @param float $base_total
 * @return object
 */
function createInvoiceMock(float $base_total): object
{
    return (object)['base_total' => $base_total];
}

/**
 * Helper for creating mock customer objects with a collection of invoices.
 *
 * @param int $id
 * @param string $name
 * @param array $invoiceTotals An array of float values for base_total.
 * @return object
 */
function createCustomerMock(int $id, string $name, array $invoiceTotals): object
{
    $invoices = collect($invoiceTotals)->map(fn ($total) => createInvoiceMock($total));
    return (object)['id' => $id, 'name' => $name, 'invoices' => $invoices];
}

test('it generates and streams a customer sales report PDF by default', function () {
    // Arrange
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    // Mock Carbon dates for internal calculations (e.g., `whereBetween`)
    Carbon::setTestNow(Carbon::createFromFormat('Y-m-d', '2023-01-01'));
    $startDate = Carbon::createFromFormat('Y-m-d', '2023-01-01');
    $endDate = Carbon::createFromFormat('Y-m-d', '2023-01-31');

    // Mock customer data with invoices
    $customer1 = createCustomerMock(1, 'Customer A', [100.50, 200.00]);
    $customer2 = createCustomerMock(2, 'Customer B', [50.00, 150.00, 10.00]);
    $customers = new Collection([$customer1, $customer2]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();

    // Mock the nested query for invoices
    $invoiceQueryBuilderMock = Mockery::mock(EloquentBuilder::class);
    $invoiceQueryBuilderMock->shouldReceive('whereBetween')
        ->with('invoice_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
        ->andReturnSelf()
        ->once();

    $this->customerModelMock->shouldReceive('with')->with(Mockery::on(function ($relations) use ($invoiceQueryBuilderMock) {
        if (isset($relations['invoices']) && is_callable($relations['invoices'])) {
            $relations['invoices']($invoiceQueryBuilderMock); // Execute the closure with our mock
            return true;
        }
        return false;
    }))->andReturn($this->customerQueryBuilderMock)->once();

    // Act
    $response = $this->controller->__invoke($request, $hash);

    // Assert PDF interaction
    $this->pdfMock->shouldHaveReceived('loadView')->with('app.pdf.reports.sales-customers')->once();
    $this->pdfMock->shouldHaveReceived('stream')->once();
    expect($response)->toBeInstanceOf(Response::class);
    $this->pdfMock->shouldNotHaveReceived('download');

    // Assert view::share was called with correct data
    $this->viewMock->shouldHaveReceived('share')->once()->with(Mockery::subset([
        'customers' => Mockery::on(function ($arg) {
            expect($arg)->toHaveCount(2);
            expect($arg[0]->totalAmount)->toBe(300.50); // 100.50 + 200.00
            expect($arg[1]->totalAmount)->toBe(210.00); // 50.00 + 150.00 + 10.00
            return true;
        }),
        'totalAmount' => 510.50, // 300.50 + 210.00
        'colorSettings' => $this->defaultColorSettingsCollection,
        'company' => $this->companyMock,
        'from_date' => '2023-01-01', // Based on mocked dateFormat 'Y-m-d'
        'to_date' => '2023-01-31',   // Based on mocked dateFormat 'Y-m-d'
        'currency' => $this->currencyMock,
    ]));

    // Assert App::setLocale was called
    $this->appMock->shouldHaveReceived('setLocale')->with('en')->once();

    // Assert CompanySetting::getSetting calls
    $this->companySettingModelMock->shouldHaveReceived('getSetting')->with('language', $this->companyMock->id)->once();
    $this->companySettingModelMock->shouldHaveReceived('getSetting')->with('carbon_date_format', $this->companyMock->id)->once();
    $this->companySettingModelMock->shouldHaveReceived('getSetting')->with('currency', $this->companyMock->id)->once();

    // Assert Currency::findOrFail was called
    $this->currencyModelMock->shouldHaveReceived('findOrFail')->with(1)->once();

    // Assert Company::where('unique_hash', $hash)->first() was called
    $this->companyModelMock->shouldHaveReceived('where->first')->once();

    // Assert customer query builder chain
    $this->customerQueryBuilderMock->shouldHaveReceived('where')->with('company_id', $this->companyMock->id)->once();
    $this->customerQueryBuilderMock->shouldHaveReceived('applyInvoiceFilters')->with(['from_date' => '2023-01-01', 'to_date' => '2023-01-31'])->once();
})->group('stream');

test('it generates and downloads a customer sales report PDF when download parameter is present', function () {
    // Arrange
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'download' => '1', // Simulate download request
    ]);

    // Mock customer data
    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();

    // Mock nested invoice query (not strictly asserting `whereBetween` here, but ensuring chain is complete)
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    // Act
    $response = $this->controller->__invoke($request, $hash);

    // Assert PDF interaction
    $this->pdfMock->shouldHaveReceived('loadView')->with('app.pdf.reports.sales-customers')->once();
    $this->pdfMock->shouldHaveReceived('download')->once();
    $this->pdfMock->shouldNotHaveReceived('stream');
    expect($response)->toBeInstanceOf(Response::class);

    // Assert that `view()->share` was still called
    $this->viewMock->shouldHaveReceived('share')->once();
})->group('download');

test('it renders a customer sales report preview when preview parameter is present', function () {
    // Arrange
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'preview' => '1', // Simulate preview request
    ]);

    // Mock customer data
    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();

    // Mock nested invoice query
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    // Act
    $response = $this->controller->__invoke($request, $hash);

    // Assert PDF interaction
    $this->pdfMock->shouldNotHaveReceived('loadView');
    $this->pdfMock->shouldNotHaveReceived('download');
    $this->pdfMock->shouldNotHaveReceived('stream');

    // Assert view rendering directly
    $this->viewMock->shouldHaveReceived('make')->with('app.pdf.reports.sales-customers')->once();
    expect($response)->toBe('html_content'); // Expected return value from mocked `View::make`

    // Assert that `view()->share` was still called
    $this->viewMock->shouldHaveReceived('share')->once();
})->group('preview');

test('it correctly handles no customers returned', function () {
    // Arrange
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    // Mock customer data - empty collection
    $customers = new Collection();
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();

    // Mock nested invoice query (will not be called if no customers)
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    // Act
    $response = $this->controller->__invoke($request, $hash);

    // Assert PDF interaction
    $this->pdfMock->shouldHaveReceived('loadView')->once();
    $this->pdfMock->shouldHaveReceived('stream')->once();

    // Assert view::share was called with correct data
    $this->viewMock->shouldHaveReceived('share')->once()->with(Mockery::subset([
        'customers' => Mockery::on(fn ($arg) => $arg->isEmpty() && true),
        'totalAmount' => 0.0,
        'company' => $this->companyMock,
    ]));
})->group('no_data');

test('it correctly handles customers with no invoices', function () {
    // Arrange
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    // Mock customer data - customers with empty invoice collections
    $customer1 = createCustomerMock(1, 'Customer A', []);
    $customer2 = createCustomerMock(2, 'Customer B', []);
    $customers = new Collection([$customer1, $customer2]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();

    // Mock nested invoice query
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    // Act
    $response = $this->controller->__invoke($request, $hash);

    // Assert PDF interaction
    $this->pdfMock->shouldHaveReceived('loadView')->once();
    $this->pdfMock->shouldHaveReceived('stream')->once();

    // Assert view::share was called with correct data
    $this->viewMock->shouldHaveReceived('share')->once()->with(Mockery::subset([
        'customers' => Mockery::on(function ($arg) {
            expect($arg)->toHaveCount(2);
            expect($arg[0]->totalAmount)->toBe(0.0);
            expect($arg[1]->totalAmount)->toBe(0.0);
            return true;
        }),
        'totalAmount' => 0.0,
        'company' => $this->companyMock,
    ]));
})->group('no_invoices');

test('it uses custom date format from company settings', function () {
    // Arrange
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    // Override date format setting to a custom one
    $this->companySettingModelMock->shouldReceive('getSetting')
        ->with('carbon_date_format', $this->companyMock->id)
        ->andReturn('d/m/Y')
        ->once();

    // Mock customer data
    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    // Act
    $this->controller->__invoke($request, $hash);

    // Assert view::share was called with correctly formatted dates
    $this->viewMock->shouldHaveReceived('share')->once()->with(Mockery::subset([
        'from_date' => '01/01/2023', // Formatted using 'd/m/Y'
        'to_date' => '31/01/2023',   // Formatted using 'd/m/Y'
    ]));
})->group('date_format');

test('it uses the correct locale from company settings', function () {
    // Arrange
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    // Override locale setting
    $this->companySettingModelMock->shouldReceive('getSetting')
        ->with('language', $this->companyMock->id)
        ->andReturn('es') // Custom locale
        ->once();

    // Mock customer data
    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    // Act
    $this->controller->__invoke($request, $hash);

    // Assert App::setLocale was called with the custom locale
    $this->appMock->shouldHaveReceived('setLocale')->with('es')->once();
})->group('locale');

test('it handles invoice filtering using the closure within the with method', function () {
    // Arrange
    $hash = 'test_hash';
    $fromDate = '2023-01-01';
    $toDate = '2023-01-31';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => $fromDate,
        'to_date' => $toDate,
    ]);

    // Mock Carbon dates for comparison within the closure
    Carbon::setTestNow(Carbon::createFromFormat('Y-m-d', $fromDate));
    $start = Carbon::createFromFormat('Y-m-d', $fromDate);
    $end = Carbon::createFromFormat('Y-m-d', $toDate);

    // Mock the nested query builder for invoices to assert `whereBetween` call
    $invoiceQueryBuilderMock = Mockery::mock(EloquentBuilder::class);
    $invoiceQueryBuilderMock->shouldReceive('whereBetween')
        ->with('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
        ->once()
        ->andReturnSelf();

    // Override Customer::with to capture and execute the closure
    $this->customerModelMock->shouldReceive('with')->with(Mockery::on(function ($relations) use ($invoiceQueryBuilderMock) {
        if (isset($relations['invoices']) && is_callable($relations['invoices'])) {
            $relations['invoices']($invoiceQueryBuilderMock); // Execute the closure with our mock
            return true;
        }
        return false;
    }))->andReturn($this->customerQueryBuilderMock)->once();

    // Continue mocking the rest of the chain
    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();

    // Act
    $this->controller->__invoke($request, $hash);

    // Assert that whereBetween was called on the invoice query builder
    $invoiceQueryBuilderMock->shouldHaveReceived('whereBetween')->once();
})->group('invoice_filtering');

test('it throws an InvalidArgumentException for invalid from_date format', function () {
    // Arrange
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => 'invalid-date', // Invalid format
        'to_date' => '2023-01-31',
    ]);

    // Expect Carbon to throw an exception
    $this->expectException(\InvalidArgumentException::class);

    // Act
    $this->controller->__invoke($request, $hash);
})->group('validation');

test('it throws an InvalidArgumentException for invalid to_date format', function () {
    // Arrange
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => 'invalid-date', // Invalid format
    ]);

    // Expect Carbon to throw an exception
    $this->expectException(\InvalidArgumentException::class);

    // Act
    $this->controller->__invoke($request, $hash);
})->group('validation');

test('it correctly retrieves and passes all color settings to the view', function () {
    // Arrange
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    // Ensure mock customers are returned for the main flow
    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    // Prepare a comprehensive color settings collection with all expected options
    $mockedColorSettings = new Collection([
        (object)['option' => 'primary_text_color', 'value' => '#111111'],
        (object)['option' => 'heading_text_color', 'value' => '#222222'],
        (object)['option' => 'section_heading_text_color', 'value' => '#333333'],
        (object)['option' => 'border_color', 'value' => '#444444'],
        (object)['option' => 'body_text_color', 'value' => '#555555'],
        (object)['option' => 'footer_text_color', 'value' => '#666666'],
        (object)['option' => 'footer_total_color', 'value' => '#777777'],
        (object)['option' => 'footer_bg_color', 'value' => '#888888'],
        (object)['option' => 'date_text_color', 'value' => '#999999'],
    ]);

    $this->companySettingModelMock->shouldReceive('whereIn->whereCompany->get')
        ->andReturn($mockedColorSettings)
        ->once();

    // Act
    $this->controller->__invoke($request, $hash);

    // Assert that `view()->share` was called with the specific color settings
    $this->viewMock->shouldHaveReceived('share')->once()->with(Mockery::subset([
        'colorSettings' => $mockedColorSettings,
    ]));

    // Verify the query for color settings was correctly formed (whereIn and whereCompany)
    $colors = [
        'primary_text_color', 'heading_text_color', 'section_heading_text_color',
        'border_color', 'body_text_color', 'footer_text_color',
        'footer_total_color', 'footer_bg_color', 'date_text_color',
    ];
    $this->companySettingModelMock->shouldHaveReceived('whereIn')
        ->with('option', $colors)
        ->once();
    $this->companySettingModelMock->shouldHaveReceived('whereCompany')
        ->with($this->companyMock->id)
        ->once();
})->group('color_settings');

test('it calls applyInvoiceFilters with only from_date and to_date from the request', function () {
    // Arrange
    $hash = 'test_hash';
    $requestData = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'customer_id' => '123', // This should be ignored by `only`
        'status' => 'paid',     // This should be ignored by `only`
    ];
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', $requestData);

    // Ensure mock customers are returned for the main flow
    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    // Act
    $this->controller->__invoke($request, $hash);

    // Assert that `applyInvoiceFilters` was called with *only* the 'from_date' and 'to_date'
    $this->customerQueryBuilderMock->shouldHaveReceived('applyInvoiceFilters')
        ->with(['from_date' => '2023-01-01', 'to_date' => '2023-01-31'])
        ->once();
})->group('filters');

// The `authorize` method call is indirectly tested by ensuring it's called with correct arguments
// and that the test proceeds as if authorization passed. Explicitly testing authorization failure
// (e.g., throwing an `AuthorizationException`) is typically done in feature tests where policies
// are properly loaded and applied, as unit tests focus on the controller's internal logic.
