<?php

use Carbon\Carbon;
use Crater\Models\Tax;
use Crater\Models\Company;
use Crater\Models\Currency;
use Illuminate\Http\Request;
use Crater\Models\CompanySetting;
use Illuminate\Support\Facades\App;
use Crater\Http\Controllers\V1\Admin\Report\TaxSummaryReportController;
use Illuminate\Support\Facades\View;
uses(\Mockery::class);
//use PDF; // Facade

// Helper to create a partial mock of the controller for authorize()
function createControllerPartialMock($company = null) {
    $controller = Mockery::mock(TaxSummaryReportController::class)->makePartial();
    // Mock authorize to allow calls, and if a company is provided, expect it once.
    // For cases where $company is null, we don't set an explicit expectation,
    // letting the default `authorize` implementation (which often checks for null)
    // potentially throw an exception, which we might test for.
    if ($company) {
        $controller->shouldReceive('authorize')->with('view report', $company)->once();
    } else {
        $controller->shouldAllowMockingProtectedMethods()->shouldReceive('authorize')->zeroOrMoreTimes();
    }
    return $controller;
}

test('it successfully generates and streams a tax summary report', function () {
    // 1. Arrange
    $companyId = 1;
    $companyHash = 'test_hash';
    $fromDate = '2023-01-01';
    $toDate = '2023-01-31';
    $locale = 'en';
    $dateFormat = 'M d, Y';
    $currencyCode = 'USD';
    $currencySymbol = '$';
    $totalTaxAmount = 150.75;

    // Mock Company
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $companyHash;

    Mockery::mock('alias:Crater\Models\Company')
        ->shouldReceive('where')
        ->with('unique_hash', $companyHash)
        ->andReturnSelf()
        ->shouldReceive('first')
        ->andReturn($mockCompany)
        ->once();

    // Mock CompanySetting
    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('getSetting')
        ->with('language', $companyId)
        ->andReturn($locale)
        ->once()
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat)
        ->once()
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($currencyId = 1)
        ->once();

    // Mock CompanySetting for colors
    $mockColorSettingsCollection = collect([
        (object)['option' => 'primary_text_color', 'value' => '#000'],
        (object)['option' => 'body_text_color', 'value' => '#333'],
    ]);
    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('whereIn')
        ->with(
            [
                'primary_text_color', 'heading_text_color', 'section_heading_text_color',
                'border_color', 'body_text_color', 'footer_text_color', 'footer_total_color',
                'footer_bg_color', 'date_text_color',
            ]
        )
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->with($companyId)
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn($mockColorSettingsCollection)
        ->once();

    // Mock App facade
    Mockery::mock('alias:Illuminate\Support\Facades\App')
        ->shouldReceive('setLocale')
        ->with($locale)
        ->once();

    // Mock Tax data
    $mockTaxType1 = (object)['total_tax_amount' => 100.25];
    $mockTaxType2 = (object)['total_tax_amount' => 50.50];
    $mockTaxCollection = collect([$mockTaxType1, $mockTaxType2]);

    Mockery::mock('alias:Crater\Models\Tax')
        ->shouldReceive('with')
        ->with('taxType', 'invoice', 'invoiceItem')
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->with($companyId)
        ->andReturnSelf()
        ->shouldReceive('whereInvoicesFilters')
        ->with(['from_date' => $fromDate, 'to_date' => $toDate])
        ->andReturnSelf()
        ->shouldReceive('taxAttributes')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn($mockTaxCollection)
        ->once();

    // Mock Currency
    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->code = $currencyCode;
    $mockCurrency->symbol = $currencySymbol;

    Mockery::mock('alias:Crater\Models\Currency')
        ->shouldReceive('findOrFail')
        ->with($currencyId)
        ->andReturn($mockCurrency)
        ->once();

    // Mock View facade for sharing data
    View::shouldReceive('share')
        ->once()
        ->with([
            'taxTypes' => $mockTaxCollection,
            'totalTaxAmount' => $totalTaxAmount,
            'colorSettings' => $mockColorSettingsCollection,
            'company' => $mockCompany,
            'from_date' => Carbon::createFromFormat('Y-m-d', $fromDate)->format($dateFormat),
            'to_date' => Carbon::createFromFormat('Y-m-d', $toDate)->format($dateFormat),
            'currency' => $mockCurrency,
        ]);

    // Mock PDF facade
    $mockPdf = Mockery::mock(\stdClass::class);
    $mockPdf->shouldReceive('stream')->andReturn('pdf_stream_content')->once();
    PDF::shouldReceive('loadView')
        ->with('app.pdf.reports.tax-summary')
        ->andReturn($mockPdf)
        ->once();

    // Create a request
    $request = Request::create('/reports/tax-summary/' . $companyHash, 'GET', [
        'from_date' => $fromDate,
        'to_date' => $toDate,
    ]);

    // 2. Act
    $controller = createControllerPartialMock($mockCompany);
    $response = $controller($request, $companyHash);

    // 3. Assert
    expect($response)->toBe('pdf_stream_content');
});

test('it returns a view for preview requests', function () {
    // 1. Arrange
    $companyId = 1;
    $companyHash = 'test_hash';
    $fromDate = '2023-01-01';
    $toDate = '2023-01-31';
    $locale = 'en';
    $dateFormat = 'M d, Y';
    $currencyCode = 'USD';
    $currencySymbol = '$';
    $totalTaxAmount = 0; // Empty tax data for simplicity here

    // Mock Company
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $companyHash;

    Mockery::mock('alias:Crater\Models\Company')
        ->shouldReceive('where')
        ->with('unique_hash', $companyHash)
        ->andReturnSelf()
        ->shouldReceive('first')
        ->andReturn($mockCompany)
        ->once();

    // Mock CompanySetting
    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('getSetting')
        ->with('language', $companyId)
        ->andReturn($locale)
        ->once()
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat)
        ->once()
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($currencyId = 1)
        ->once();

    // Mock CompanySetting for colors
    $mockColorSettingsCollection = collect([]); // Empty for simplicity
    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('whereIn')
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn($mockColorSettingsCollection)
        ->once();

    // Mock App facade
    Mockery::mock('alias:Illuminate\Support\Facades\App')
        ->shouldReceive('setLocale')
        ->with($locale)
        ->once();

    // Mock Tax data (empty for this test)
    Mockery::mock('alias:Crater\Models\Tax')
        ->shouldReceive('with')
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->andReturnSelf()
        ->shouldReceive('whereInvoicesFilters')
        ->andReturnSelf()
        ->shouldReceive('taxAttributes')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn(collect([]))
        ->once();

    // Mock Currency
    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->code = $currencyCode;
    $mockCurrency->symbol = $currencySymbol;

    Mockery::mock('alias:Crater\Models\Currency')
        ->shouldReceive('findOrFail')
        ->with($currencyId)
        ->andReturn($mockCurrency)
        ->once();

    // Mock View facade for sharing data
    View::shouldReceive('share')->once();

    // Mock the global 'view()' helper function's internal call to App::make('view')
    $mockViewFactory = Mockery::mock(\Illuminate\Contracts\View\Factory::class);
    $mockViewInstance = Mockery::mock(\Illuminate\Contracts\View\View::class); // The actual View instance returned
    $mockViewFactory->shouldReceive('make')
        ->with('app.pdf.reports.tax-summary', Mockery::any())
        ->andReturn($mockViewInstance);

    Mockery::mock('alias:Illuminate\Support\Facades\App')
        ->shouldReceive('make')
        ->with('view')
        ->andReturn($mockViewFactory);

    // No PDF interaction in preview mode
    PDF::shouldNotReceive('loadView');

    // Create a request with 'preview'
    $request = Request::create('/reports/tax-summary/' . $companyHash, 'GET', [
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'preview' => true,
    ]);

    // 2. Act
    $controller = createControllerPartialMock($mockCompany);
    $response = $controller($request, $companyHash);

    // 3. Assert
    expect($response)->toBe($mockViewInstance);
});

test('it returns a download response for download requests', function () {
    // 1. Arrange
    $companyId = 1;
    $companyHash = 'test_hash';
    $fromDate = '2023-01-01';
    $toDate = '2023-01-31';
    $locale = 'en';
    $dateFormat = 'M d, Y';
    $currencyCode = 'USD';
    $currencySymbol = '$';
    $totalTaxAmount = 0; // Empty tax data for simplicity here

    // Mock Company
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $companyHash;

    Mockery::mock('alias:Crater\Models\Company')
        ->shouldReceive('where')
        ->with('unique_hash', $companyHash)
        ->andReturnSelf()
        ->shouldReceive('first')
        ->andReturn($mockCompany)
        ->once();

    // Mock CompanySetting
    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('getSetting')
        ->with('language', $companyId)
        ->andReturn($locale)
        ->once()
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat)
        ->once()
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($currencyId = 1)
        ->once();

    // Mock CompanySetting for colors
    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('whereIn')
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn(collect([]))
        ->once();

    // Mock App facade
    Mockery::mock('alias:Illuminate\Support\Facades\App')
        ->shouldReceive('setLocale')
        ->with($locale)
        ->once();

    // Mock Tax data (empty for this test)
    Mockery::mock('alias:Crater\Models\Tax')
        ->shouldReceive('with')
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->andReturnSelf()
        ->shouldReceive('whereInvoicesFilters')
        ->andReturnSelf()
        ->shouldReceive('taxAttributes')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn(collect([]))
        ->once();

    // Mock Currency
    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->code = $currencyCode;
    $mockCurrency->symbol = $currencySymbol;

    Mockery::mock('alias:Crater\Models\Currency')
        ->shouldReceive('findOrFail')
        ->with($currencyId)
        ->andReturn($mockCurrency)
        ->once();

    // Mock View facade for sharing data
    View::shouldReceive('share')->once();

    // Mock PDF facade for download
    $mockPdf = Mockery::mock(\stdClass::class);
    $mockPdf->shouldReceive('download')->andReturn('pdf_download_response')->once();
    PDF::shouldReceive('loadView')
        ->with('app.pdf.reports.tax-summary')
        ->andReturn($mockPdf)
        ->once();

    // Create a request with 'download'
    $request = Request::create('/reports/tax-summary/' . $companyHash, 'GET', [
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'download' => true,
    ]);

    // 2. Act
    $controller = createControllerPartialMock($mockCompany);
    $response = $controller($request, $companyHash);

    // 3. Assert
    expect($response)->toBe('pdf_download_response');
});

test('it handles empty tax data gracefully', function () {
    // 1. Arrange
    $companyId = 1;
    $companyHash = 'test_hash';
    $fromDate = '2023-01-01';
    $toDate = '2023-01-31';
    $locale = 'en';
    $dateFormat = 'M d, Y';
    $currencyCode = 'USD';
    $currencySymbol = '$';
    $totalTaxAmount = 0; // Expect 0 with empty data

    // Mock Company
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $companyHash;

    Mockery::mock('alias:Crater\Models\Company')
        ->shouldReceive('where')
        ->with('unique_hash', $companyHash)
        ->andReturnSelf()
        ->shouldReceive('first')
        ->andReturn($mockCompany)
        ->once();

    // Mock CompanySetting
    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('getSetting')
        ->with('language', $companyId)
        ->andReturn($locale)
        ->once()
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat)
        ->once()
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($currencyId = 1)
        ->once();

    // Mock CompanySetting for colors
    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('whereIn')
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn(collect([]))
        ->once();

    // Mock App facade
    Mockery::mock('alias:Illuminate\Support\Facades\App')
        ->shouldReceive('setLocale')
        ->with($locale)
        ->once();

    // Mock Tax data (return empty collection)
    Mockery::mock('alias:Crater\Models\Tax')
        ->shouldReceive('with')
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->with($companyId)
        ->andReturnSelf()
        ->shouldReceive('whereInvoicesFilters')
        ->with(['from_date' => $fromDate, 'to_date' => $toDate])
        ->andReturnSelf()
        ->shouldReceive('taxAttributes')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn(collect([])) // Empty tax types
        ->once();

    // Mock Currency
    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->code = $currencyCode;
    $mockCurrency->symbol = $currencySymbol;

    Mockery::mock('alias:Crater\Models\Currency')
        ->shouldReceive('findOrFail')
        ->with($currencyId)
        ->andReturn($mockCurrency)
        ->once();

    // Mock View facade for sharing data
    View::shouldReceive('share')
        ->once()
        ->with([
            'taxTypes' => collect([]), // Expect empty collection
            'totalTaxAmount' => $totalTaxAmount, // Expect 0
            'colorSettings' => collect([]),
            'company' => $mockCompany,
            'from_date' => Carbon::createFromFormat('Y-m-d', $fromDate)->format($dateFormat),
            'to_date' => Carbon::createFromFormat('Y-m-d', $toDate)->format($dateFormat),
            'currency' => $mockCurrency,
        ]);

    // Mock PDF facade
    $mockPdf = Mockery::mock(\stdClass::class);
    $mockPdf->shouldReceive('stream')->andReturn('pdf_stream_content')->once();
    PDF::shouldReceive('loadView')
        ->with('app.pdf.reports.tax-summary')
        ->andReturn($mockPdf)
        ->once();

    // Create a request
    $request = Request::create('/reports/tax-summary/' . $companyHash, 'GET', [
        'from_date' => $fromDate,
        'to_date' => $toDate,
    ]);

    // 2. Act
    $controller = createControllerPartialMock($mockCompany);
    $response = $controller($request, $companyHash);

    // 3. Assert
    expect($response)->toBe('pdf_stream_content');
});

test('it throws a ValueError if from_date or to_date request parameters are missing', function () {
    // 1. Arrange
    $companyId = 1;
    $companyHash = 'test_hash';
    $locale = 'en';
    $dateFormat = 'M d, Y';
    $currencyId = 1;

    // Mock Company
    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $companyHash;

    Mockery::mock('alias:Crater\Models\Company')
        ->shouldReceive('where')
        ->with('unique_hash', $companyHash)
        ->andReturnSelf()
        ->shouldReceive('first')
        ->andReturn($mockCompany)
        ->once();

    // Mock CompanySetting to avoid errors for other settings
    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('getSetting')
        ->with('language', $companyId)
        ->andReturn($locale)
        ->once()
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat)
        ->once()
        ->shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($currencyId)
        ->once();

    // Mock CompanySetting for colors
    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('whereIn')
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn(collect([]))
        ->once();

    // Mock App facade
    Mockery::mock('alias:Illuminate\Support\Facades\App')
        ->shouldReceive('setLocale')
        ->with($locale)
        ->once();

    // Mock Tax data - specifically check whereInvoicesFilters is called with empty array
    Mockery::mock('alias:Crater\Models\Tax')
        ->shouldReceive('with')
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->with($companyId)
        ->andReturnSelf()
        ->shouldReceive('whereInvoicesFilters')
        ->with([]) // This is the crucial part: it receives an empty array
        ->andReturnSelf()
        ->shouldReceive('taxAttributes')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn(collect([]))
        ->once();

    // Mock Currency
    $mockCurrency = Mockery::mock(Currency::class);
    Mockery::mock('alias:Crater\Models\Currency')
        ->shouldReceive('findOrFail')
        ->with($currencyId)
        ->andReturn($mockCurrency)
        ->once();

    // We expect Carbon to throw an exception because from_date/to_date will be null from $request
    $request = Request::create('/reports/tax-summary/' . $companyHash, 'GET', []); // No from_date or to_date

    // 2. Act & 3. Assert
    $controller = createControllerPartialMock($mockCompany);
    // Carbon::createFromFormat will receive null and throw an error.
    expect(function () use ($controller, $request, $companyHash) {
        $controller($request, $companyHash);
    })->toThrow(\ValueError::class); // Carbon 2.x throws ValueError, older might throw Exception
});


test('it throws an AuthorizationException if company not found', function () {
    // 1. Arrange
    $companyHash = 'non_existent_hash';

    Mockery::mock('alias:Crater\Models\Company')
        ->shouldReceive('where')
        ->with('unique_hash', $companyHash)
        ->andReturnSelf()
        ->shouldReceive('first')
        ->andReturn(null)
        ->once();

    $request = Request::create('/reports/tax-summary/' . $companyHash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    // 2. Act & 3. Assert
    $controller = createControllerPartialMock(); // Don't pass company to authorize here
    // The authorize method will receive null for $company and should throw an exception.
    expect(function () use ($controller, $request, $companyHash) {
        $controller($request, $companyHash);
    })->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});
