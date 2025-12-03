```php
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

beforeEach(function () {
    // Clear mocks before each test
    Mockery::close();

    // PDF Facade Mock
    if (!class_exists('PDF')) {
        class_alias(\Mockery::mock(), 'PDF');
    }
    $this->pdfMock = Mockery::mock('PDF');
    $this->pdfMock->shouldReceive('loadView')->andReturnSelf();
    $this->pdfMock->shouldReceive('download')->andReturn(Mockery::mock(Response::class));
    $this->pdfMock->shouldReceive('stream')->andReturn(Mockery::mock(Response::class));

    // App Facade Mock
    if (!class_exists('App')) {
        class_alias(\Mockery::mock(), 'App');
    }
    $this->appMock = Mockery::mock('App');
    $this->appMock->shouldReceive('setLocale')->with(Mockery::type('string'))->andReturnNull();

    // View Facade Mock
    if (!class_exists('View')) {
        class_alias(\Mockery::mock(), 'View');
    }
    $this->viewMock = Mockery::mock('View');
    $this->viewMock->shouldReceive('share')->with(Mockery::type('array'))->andReturnNull();
    $this->viewMock->shouldReceive('make')->with('app.pdf.reports.sales-customers')->andReturn('html_content');

    // Company Model Static Proxy (use instance, not alias!)
    $this->companyMock = (object)['id' => 1, 'unique_hash' => 'test_hash', 'name' => 'Test Company'];
    // Use partial mock for existing class instead of alias
    $this->companyModelMock = Mockery::mock('overload:Crater\Models\Company');
    $this->companyModelMock->shouldReceive('where')->with('unique_hash', 'test_hash')->andReturnSelf()->byDefault();
    $this->companyModelMock->shouldReceive('first')->andReturn($this->companyMock)->byDefault();

    // CompanySetting Model - overload so static calls are intercepted
    $this->companySettingModelMock = Mockery::mock('overload:Crater\Models\CompanySetting');
    $this->companySettingModelMock->shouldReceive('getSetting')
        ->with('language', $this->companyMock->id)
        ->andReturn('en')
        ->byDefault();
    $this->companySettingModelMock->shouldReceive('getSetting')
        ->with('carbon_date_format', $this->companyMock->id)
        ->andReturn('Y-m-d')
        ->byDefault();
    $this->companySettingModelMock->shouldReceive('getSetting')
        ->with('currency', $this->companyMock->id)
        ->andReturn(1)
        ->byDefault();

    $this->defaultColorSettingsCollection = new Collection([
        (object)['option' => 'primary_text_color', 'value' => '#000000'],
        (object)['option' => 'heading_text_color', 'value' => '#333333'],
    ]);
    $this->companySettingModelMock->shouldReceive('whereIn')->andReturnSelf()->byDefault();
    $this->companySettingModelMock->shouldReceive('whereCompany')->andReturnSelf()->byDefault();
    $this->companySettingModelMock->shouldReceive('get')
        ->andReturn($this->defaultColorSettingsCollection)
        ->byDefault();

    // Currency Model overload for static calls
    $this->currencyMock = (object)['id' => 1, 'name' => 'USD', 'code' => 'USD', 'symbol' => '$'];
    $this->currencyModelMock = Mockery::mock('overload:Crater\Models\Currency');
    $this->currencyModelMock->shouldReceive('findOrFail')->with(1)->andReturn($this->currencyMock)->byDefault();

    // Customer Model overload for static calls
    $this->customerQueryBuilderMock = Mockery::mock(EloquentBuilder::class);
    $this->customerModelMock = Mockery::mock('overload:Crater\Models\Customer');
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->byDefault();
    $this->customerQueryBuilderMock->shouldReceive('where')->andReturn($this->customerQueryBuilderMock)->byDefault();
    $this->customerQueryBuilderMock->shouldReceive('applyInvoiceFilters')->andReturn($this->customerQueryBuilderMock)->byDefault();

    // Controller partial mock
    $this->controller = Mockery::mock(CustomerSalesReportController::class)->makePartial();
    $this->controller->shouldAllowMockingProtectedMethods();
    $this->controller->shouldReceive('authorize')->with('view report', $this->companyMock)->andReturn(true)->byDefault();
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
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    Carbon::setTestNow(Carbon::createFromFormat('Y-m-d', '2023-01-01'));
    $startDate = Carbon::createFromFormat('Y-m-d', '2023-01-01');
    $endDate = Carbon::createFromFormat('Y-m-d', '2023-01-31');

    $customer1 = createCustomerMock(1, 'Customer A', [100.50, 200.00]);
    $customer2 = createCustomerMock(2, 'Customer B', [50.00, 150.00, 10.00]);
    $customers = new Collection([$customer1, $customer2]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();

    $invoiceQueryBuilderMock = Mockery::mock(EloquentBuilder::class);
    $invoiceQueryBuilderMock->shouldReceive('whereBetween')
        ->with('invoice_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
        ->andReturnSelf()
        ->once();

    $this->customerModelMock->shouldReceive('with')->with(Mockery::on(function ($relations) use ($invoiceQueryBuilderMock) {
        if (isset($relations['invoices']) && is_callable($relations['invoices'])) {
            $relations['invoices']($invoiceQueryBuilderMock);
            return true;
        }
        return false;
    }))->andReturn($this->customerQueryBuilderMock)->once();

    $response = $this->controller->__invoke($request, $hash);

    $this->pdfMock->shouldHaveReceived('loadView')->with('app.pdf.reports.sales-customers')->once();
    $this->pdfMock->shouldHaveReceived('stream')->once();
    expect($response)->toBeInstanceOf(Response::class);
    $this->pdfMock->shouldNotHaveReceived('download');

    $this->viewMock->shouldHaveReceived('share')->once()->with(Mockery::subset([
        'customers' => Mockery::on(function ($arg) {
            expect($arg)->toHaveCount(2);
            expect($arg[0]->totalAmount)->toBe(300.50);
            expect($arg[1]->totalAmount)->toBe(210.00);
            return true;
        }),
        'totalAmount' => 510.50,
        'colorSettings' => $this->defaultColorSettingsCollection,
        'company' => $this->companyMock,
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'currency' => $this->currencyMock,
    ]));

    $this->appMock->shouldHaveReceived('setLocale')->with('en')->once();
    $this->companySettingModelMock->shouldHaveReceived('getSetting')->with('language', $this->companyMock->id)->once();
    $this->companySettingModelMock->shouldHaveReceived('getSetting')->with('carbon_date_format', $this->companyMock->id)->once();
    $this->companySettingModelMock->shouldHaveReceived('getSetting')->with('currency', $this->companyMock->id)->once();
    $this->currencyModelMock->shouldHaveReceived('findOrFail')->with(1)->once();

    $this->companyModelMock->shouldHaveReceived('where')->with('unique_hash', $hash)->once();
    $this->companyModelMock->shouldHaveReceived('first')->once();

    $this->customerQueryBuilderMock->shouldHaveReceived('where')->with('company_id', $this->companyMock->id)->once();
    $this->customerQueryBuilderMock->shouldHaveReceived('applyInvoiceFilters')->with(['from_date' => '2023-01-01', 'to_date' => '2023-01-31'])->once();
})->group('stream');

test('it generates and downloads a customer sales report PDF when download parameter is present', function () {
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'download' => '1',
    ]);

    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    $response = $this->controller->__invoke($request, $hash);

    $this->pdfMock->shouldHaveReceived('loadView')->with('app.pdf.reports.sales-customers')->once();
    $this->pdfMock->shouldHaveReceived('download')->once();
    $this->pdfMock->shouldNotHaveReceived('stream');
    expect($response)->toBeInstanceOf(Response::class);

    $this->viewMock->shouldHaveReceived('share')->once();
})->group('download');

test('it renders a customer sales report preview when preview parameter is present', function () {
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'preview' => '1',
    ]);

    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    $response = $this->controller->__invoke($request, $hash);

    $this->pdfMock->shouldNotHaveReceived('loadView');
    $this->pdfMock->shouldNotHaveReceived('download');
    $this->pdfMock->shouldNotHaveReceived('stream');
    $this->viewMock->shouldHaveReceived('make')->with('app.pdf.reports.sales-customers')->once();
    expect($response)->toBe('html_content');
    $this->viewMock->shouldHaveReceived('share')->once();
})->group('preview');

test('it correctly handles no customers returned', function () {
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    $customers = new Collection();
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    $response = $this->controller->__invoke($request, $hash);

    $this->pdfMock->shouldHaveReceived('loadView')->once();
    $this->pdfMock->shouldHaveReceived('stream')->once();

    $this->viewMock->shouldHaveReceived('share')->once()->with(Mockery::subset([
        'customers' => Mockery::on(fn ($arg) => $arg->isEmpty() && true),
        'totalAmount' => 0.0,
        'company' => $this->companyMock,
    ]));
})->group('no_data');

test('it correctly handles customers with no invoices', function () {
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    $customer1 = createCustomerMock(1, 'Customer A', []);
    $customer2 = createCustomerMock(2, 'Customer B', []);
    $customers = new Collection([$customer1, $customer2]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    $response = $this->controller->__invoke($request, $hash);

    $this->pdfMock->shouldHaveReceived('loadView')->once();
    $this->pdfMock->shouldHaveReceived('stream')->once();

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
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    $this->companySettingModelMock->shouldReceive('getSetting')
        ->with('carbon_date_format', $this->companyMock->id)
        ->andReturn('d/m/Y')
        ->once();

    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    $this->controller->__invoke($request, $hash);

    $this->viewMock->shouldHaveReceived('share')->once()->with(Mockery::subset([
        'from_date' => '01/01/2023',
        'to_date' => '31/01/2023',
    ]));
})->group('date_format');

test('it uses the correct locale from company settings', function () {
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    $this->companySettingModelMock->shouldReceive('getSetting')
        ->with('language', $this->companyMock->id)
        ->andReturn('es')
        ->once();

    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    $this->controller->__invoke($request, $hash);

    $this->appMock->shouldHaveReceived('setLocale')->with('es')->once();
})->group('locale');

test('it handles invoice filtering using the closure within the with method', function () {
    $hash = 'test_hash';
    $fromDate = '2023-01-01';
    $toDate = '2023-01-31';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => $fromDate,
        'to_date' => $toDate,
    ]);

    Carbon::setTestNow(Carbon::createFromFormat('Y-m-d', $fromDate));
    $start = Carbon::createFromFormat('Y-m-d', $fromDate);
    $end = Carbon::createFromFormat('Y-m-d', $toDate);

    $invoiceQueryBuilderMock = Mockery::mock(EloquentBuilder::class);
    $invoiceQueryBuilderMock->shouldReceive('whereBetween')
        ->with('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
        ->once()
        ->andReturnSelf();

    $this->customerModelMock->shouldReceive('with')->with(Mockery::on(function ($relations) use ($invoiceQueryBuilderMock) {
        if (isset($relations['invoices']) && is_callable($relations['invoices'])) {
            $relations['invoices']($invoiceQueryBuilderMock);
            return true;
        }
        return false;
    }))->andReturn($this->customerQueryBuilderMock)->once();

    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();

    $this->controller->__invoke($request, $hash);

    $invoiceQueryBuilderMock->shouldHaveReceived('whereBetween')->once();
})->group('invoice_filtering');

test('it throws an InvalidArgumentException for invalid from_date format', function () {
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => 'invalid-date',
        'to_date' => '2023-01-31',
    ]);

    $this->expectException(\InvalidArgumentException::class);

    $this->controller->__invoke($request, $hash);
})->group('validation');

test('it throws an InvalidArgumentException for invalid to_date format', function () {
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => 'invalid-date',
    ]);

    $this->expectException(\InvalidArgumentException::class);

    $this->controller->__invoke($request, $hash);
})->group('validation');

test('it correctly retrieves and passes all color settings to the view', function () {
    $hash = 'test_hash';
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

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

    $this->companySettingModelMock->shouldReceive('whereIn')
        ->with('option', [
            'primary_text_color', 'heading_text_color', 'section_heading_text_color',
            'border_color', 'body_text_color', 'footer_text_color',
            'footer_total_color', 'footer_bg_color', 'date_text_color',
        ])
        ->andReturnSelf()
        ->once();
    $this->companySettingModelMock->shouldReceive('whereCompany')
        ->with($this->companyMock->id)
        ->andReturnSelf()
        ->once();
    $this->companySettingModelMock->shouldReceive('get')
        ->andReturn($mockedColorSettings)
        ->once();

    $this->controller->__invoke($request, $hash);

    $this->viewMock->shouldHaveReceived('share')->once()->with(Mockery::subset([
        'colorSettings' => $mockedColorSettings,
    ]));

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
    $hash = 'test_hash';
    $requestData = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'customer_id' => '123',
        'status' => 'paid',
    ];
    $request = Request::create('/reports/customer-sales/' . $hash, 'GET', $requestData);

    $customers = new Collection([createCustomerMock(1, 'Customer A', [100.00])]);
    $this->customerQueryBuilderMock->shouldReceive('get')->andReturn($customers)->once();
    $this->customerModelMock->shouldReceive('with')->andReturn($this->customerQueryBuilderMock)->once();

    $this->controller->__invoke($request, $hash);

    $this->customerQueryBuilderMock->shouldHaveReceived('applyInvoiceFilters')
        ->with(['from_date' => '2023-01-01', 'to_date' => '2023-01-31'])
        ->once();
})->group('filters');

afterEach(function () {
    Mockery::close();
});
```