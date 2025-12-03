```php
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
use PDF; // Facade - uncommented for consistency with usage

// Helper to create a partial mock of the controller for authorize()
function createControllerPartialMock($company = null) {
    $controller = Mockery::mock(TaxSummaryReportController::class)->makePartial();
    // Allow mocking of protected methods (like `authorize` in a base controller)
    $controller->shouldAllowMockingProtectedMethods();

    if ($company) {
        // If a specific company is provided, authorize should pass once for it.
        // `andReturnTrue()` added as `authorize` typically returns void or true on success.
        $controller->shouldReceive('authorize')->with('view report', $company)->once()->andReturnTrue();
    } else {
        // If no company is provided (e.g., in the 'company not found' test),
        // let the real `authorize` method of the controller be called.
        // This allows testing the AuthorizationException that the real method might throw
        // when a resource is null or not found.
        $controller->shouldReceive('authorize')->zeroOrMoreTimes()->passthru();
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
    // FIX: Mockery\Exception\BadMethodCallException: `__set` calls `setAttribute`.
    // Allow setting properties on the mock as if it were a real model.
    $mockCompany->shouldAllowMockingProtectedMethods()->shouldReceive('setAttribute')->byDefault()->andReturnSelf();
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
        ->with(Mockery::subset([ // Use Mockery::subset for robust array matching
            'taxTypes' => $mockTaxCollection,
            'totalTaxAmount' => $totalTaxAmount,
            'colorSettings' => $mockColorSettingsCollection,
            'company' => $mockCompany,
            'from_date' => Carbon::createFromFormat('Y-m-d', $fromDate)->format($dateFormat),
            'to_date' => Carbon::createFromFormat('Y-m-d', $toDate)->format($dateFormat),
            'currency' => $mockCurrency,
        ]));

    // Mock PDF facade
    $mockPdf = Mockery::mock(\stdClass::class);
    $mockPdf->shouldReceive('stream')->andReturn('pdf_stream_content')->once();
    PDF::shouldReceive('loadView')
        ->with('app.pdf.reports.tax-summary', Mockery::any()) // Added Mockery::any() for view data
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
    // FIX: Mockery\Exception\BadMethodCallException: Allow setting attributes on the mock
    $mockCompany->shouldAllowMockingProtectedMethods()->shouldReceive('setAttribute')->byDefault()->andReturnSelf();
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

    // Mock the global 'view()' helper function's internal call to App::make('view')
    $mockViewFactory = Mockery::mock(\Illuminate\Contracts\View\Factory::class);
    $mockViewInstance = Mockery::mock(\Illuminate\Contracts\View\View::class); // The actual View instance returned
    $mockViewFactory->shouldReceive('make')
        ->with('app.pdf.reports.tax-summary', Mockery::any())
        ->andReturn($mockViewInstance)
        ->once(); // make() should be called once by view() helper

    // Consolidate App facade mocks: `setLocale` and `make('view')` are both called on `App`.
    Mockery::mock('alias:Illuminate\Support\Facades\App')
        ->shouldReceive('setLocale')
        ->with($locale)
        ->once()
        ->shouldReceive('make') // Chain the make expectation onto the same App alias mock.
        ->with('view')
        ->andReturn($mockViewFactory)
        ->once();

    // Mock Tax data (empty for this test)
    Mockery::mock('alias:Crater\Models\Tax')
        ->shouldReceive('with')
        ->with('taxType', 'invoice', 'invoiceItem') // Added specific `with` calls.
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->with($companyId) // Added specific `with` calls.
        ->andReturnSelf()
        ->shouldReceive('whereInvoicesFilters')
        ->with(['from_date' => $fromDate, 'to_date' => $toDate]) // Added specific `with` calls.
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
    View::shouldReceive('share')
        ->once()
        ->with(Mockery::subset([ // Use Mockery::subset for robust array matching
            'taxTypes' => collect([]),
            'totalTaxAmount' => $totalTaxAmount,
            'colorSettings' => $mockColorSettingsCollection,
            'company' => $mockCompany,
            'from_date' => Carbon::createFromFormat('Y-m-d', $fromDate)->format($dateFormat),
            'to_date' => Carbon::createFromFormat('Y-m-d', $toDate)->format($dateFormat),
            'currency' => $mockCurrency,
        ]));

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
    // FIX: Mockery\Exception\BadMethodCallException: Allow setting attributes on the mock
    $mockCompany->shouldAllowMockingProtectedMethods()->shouldReceive('setAttribute')->byDefault()->andReturnSelf();
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

    // Mock Tax data (empty for this test)
    Mockery::mock('alias:Crater\Models\Tax')
        ->shouldReceive('with')
        ->with('taxType', 'invoice', 'invoiceItem') // Added specific `with` calls.
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->with($companyId) // Added specific `with` calls.
        ->andReturnSelf()
        ->shouldReceive('whereInvoicesFilters')
        ->with(['from_date' => $fromDate, 'to_date' => $toDate]) // Added specific `with` calls.
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
    View::shouldReceive('share')
        ->once()
        ->with(Mockery::subset([ // Use Mockery::subset for robust array matching
            'taxTypes' => collect([]),
            'totalTaxAmount' => $totalTaxAmount,
            'colorSettings' => $mockColorSettingsCollection,
            'company' => $mockCompany,
            'from_date' => Carbon::createFromFormat('Y-m-d', $fromDate)->format($dateFormat),
            'to_date' => Carbon::createFromFormat('Y-m-d', $toDate)->format($dateFormat),
            'currency' => $mockCurrency,
        ]));

    // Mock PDF facade for download
    $mockPdf = Mockery::mock(\stdClass::class);
    $mockPdf->shouldReceive('download')->andReturn('pdf_download_response')->once();
    PDF::shouldReceive('loadView')
        ->with('app.pdf.reports.tax-summary', Mockery::any()) // Added Mockery::any() for view data
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
    // FIX: Mockery\Exception\BadMethodCallException: Allow setting attributes on the mock
    $mockCompany->shouldAllowMockingProtectedMethods()->shouldReceive('setAttribute')->byDefault()->andReturnSelf();
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

    // Mock Tax data (return empty collection)
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
        ->with(Mockery::subset([ // Use Mockery::subset for robust array matching
            'taxTypes' => collect([]), // Expect empty collection
            'totalTaxAmount' => $totalTaxAmount, // Expect 0
            'colorSettings' => $mockColorSettingsCollection,
            'company' => $mockCompany,
            'from_date' => Carbon::createFromFormat('Y-m-d', $fromDate)->format($dateFormat),
            'to_date' => Carbon::createFromFormat('Y-m-d', $toDate)->format($dateFormat),
            'currency' => $mockCurrency,
        ]));

    // Mock PDF facade
    $mockPdf = Mockery::mock(\stdClass::class);
    $mockPdf->shouldReceive('stream')->andReturn('pdf_stream_content')->once();
    PDF::shouldReceive('loadView')
        ->with('app.pdf.reports.tax-summary', Mockery::any()) // Added Mockery::any() for view data
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
    // FIX: Mockery\Exception\BadMethodCallException: Allow setting attributes on the mock
    $mockCompany->shouldAllowMockingProtectedMethods()->shouldReceive('setAttribute')->byDefault()->andReturnSelf();
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $companyHash;

    Mockery::mock('alias:Crater\Models\Company')
        ->shouldReceive('where')
        ->with('unique_hash', $companyHash)
        ->andReturnSelf()
        ->shouldReceive('first')
        ->andReturn($mockCompany)
        ->once();

    // Mock CompanySetting to avoid errors for other settings (controller still tries to get them)
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

    // Mock CompanySetting for colors (controller still tries to get them)
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
        ->andReturn(collect([]))
        ->once();

    // Mock App facade
    Mockery::mock('alias:Illuminate\Support\Facades\App')
        ->shouldReceive('setLocale')
        ->with($locale)
        ->once();

    // Mock Tax data - specifically check whereInvoicesFilters is called with empty array
    // This part of the mock is needed because the controller will call it before Carbon's error.
    Mockery::mock('alias:Crater\Models\Tax')
        ->shouldReceive('with')
        ->with('taxType', 'invoice', 'invoiceItem')
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->with($companyId)
        ->andReturnSelf()
        ->shouldReceive('whereInvoicesFilters')
        ->with([]) // This is the crucial part: it receives an empty array when from_date/to_date are missing
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
    $controller = createControllerPartialMock($mockCompany); // Pass mockCompany, as company is found initially
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

    // When the company is null, the controller likely tries to get settings with a default or null ID.
    // The previous tests suggest `CompanySetting::getSetting` expects `$companyId`.
    // If `$company` is null, `$company->id` would be `null`, so we mock `getSetting` to receive `null`.
    $locale = 'en';
    $dateFormat = 'M d, Y';
    $currencyId = 1;

    Mockery::mock('alias:Crater\Models\CompanySetting')
        ->shouldReceive('getSetting')
        ->with('language', null) // Company is null, so companyId passed to getSetting would be null.
        ->andReturn($locale)
        ->once()
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', null) // Same here
        ->andReturn($dateFormat)
        ->once()
        ->shouldReceive('getSetting')
        ->with('currency', null) // Same here
        ->andReturn($currencyId)
        ->once();

    // App facade will still be used to set locale.
    Mockery::mock('alias:Illuminate\Support\Facades\App')
        ->shouldReceive('setLocale')
        ->with($locale)
        ->once();

    // Currency should still be mocked as it's found by a fixed ID (1).
    $mockCurrency = Mockery::mock(Currency::class);
    Mockery::mock('alias:Crater\Models\Currency')
        ->shouldReceive('findOrFail')
        ->with($currencyId)
        ->andReturn($mockCurrency)
        ->once();
    
    // View::share will still be called.
    View::shouldReceive('share')
        ->once()
        ->with(Mockery::subset([
            'taxTypes' => Mockery::type(\Illuminate\Support\Collection::class), // Will be an empty collection as tax data query likely runs with null company
            'totalTaxAmount' => 0, // No taxes if company is null or no data
            'colorSettings' => Mockery::type(\Illuminate\Support\Collection::class), // Empty collection
            'company' => null, // Company is explicitly null in this scenario
            'from_date' => Carbon::createFromFormat('Y-m-d', '2023-01-01')->format($dateFormat),
            'to_date' => Carbon::createFromFormat('Y-m-d', '2023-01-31')->format($dateFormat),
            'currency' => $mockCurrency,
        ]));

    // Also need to mock Tax related calls, as they happen before AuthorizationException.
    Mockery::mock('alias:Crater\Models\Tax')
        ->shouldReceive('with')
        ->with('taxType', 'invoice', 'invoiceItem')
        ->andReturnSelf()
        ->shouldReceive('whereCompany')
        ->with(null) // When company is null, whereCompany should receive null
        ->andReturnSelf()
        ->shouldReceive('whereInvoicesFilters')
        ->with(['from_date' => '2023-01-01', 'to_date' => '2023-01-31'])
        ->andReturnSelf()
        ->shouldReceive('taxAttributes')
        ->andReturnSelf()
        ->shouldReceive('get')
        ->andReturn(collect([])) // Return empty collection for tax data
        ->once();

    $request = Request::create('/reports/tax-summary/' . $companyHash, 'GET', [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ]);

    // 2. Act & 3. Assert
    // Pass null to createControllerPartialMock, as the company isn't found.
    // The helper is configured to passthru `authorize` calls when $company is null,
    // allowing the real `authorize` method to throw the exception.
    $controller = createControllerPartialMock(null);
    expect(function () use ($controller, $request, $companyHash) {
        $controller($request, $companyHash);
    })->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});


afterEach(function () {
    Mockery::close();
});

```