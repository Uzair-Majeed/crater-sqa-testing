<?php

uses(\Mockery::class);
use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\Currency;
use Illuminate\Http\Request;
use Crater\Models\InvoiceItem;
use Crater\Models\CompanySetting;
use Crater\Http\Controllers\V1\Admin\Report\ItemSalesReportController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

beforeEach(function () {
    Mockery::close();
});


test('it streams the item sales report PDF by default', function () {
    $controller = new ItemSalesReportController();

    $hash = 'some-unique-hash';
    $companyId = 1;
    $locale = 'en';
    $dateFormat = 'd/m/Y';
    $currencyId = 1;
    $currencyCode = 'USD';

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->shouldReceive('getAttribute')->with('id')->andReturn($companyId);
    $mockCompany->shouldReceive('getAttribute')->with('unique_hash')->andReturn($hash);
    Company::shouldReceive('where->first')
        ->once()
        ->with('unique_hash', $hash)
        ->andReturn($mockCompany);

    CompanySetting::shouldReceive('getSetting')
        ->with('language', $companyId)
        ->once()
        ->andReturn($locale);
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->once()
        ->andReturn($dateFormat);
    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->once()
        ->andReturn($currencyId);

    $mockColorSettingsCollection = collect([
        (object)['option' => 'primary_text_color', 'value' => '#000000'],
    ]);
    $mockCompanySettingQuery = Mockery::mock();
    $mockCompanySettingQuery->shouldReceive('whereIn')
        ->once()
        ->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('whereCompany')
        ->once()
        ->with($companyId)
        ->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('get')
        ->once()
        ->andReturn($mockColorSettingsCollection);
    CompanySetting::shouldReceive('whereIn')
        ->once()
        ->andReturn($mockCompanySettingQuery);

    $mockInvoiceItem1 = (object)['total_amount' => 100, 'item_name' => 'Item A'];
    $mockInvoiceItem2 = (object)['total_amount' => 50, 'item_name' => 'Item B'];
    $mockInvoiceItemsCollection = collect([$mockInvoiceItem1, $mockInvoiceItem2]);

    $mockInvoiceItemQuery = Mockery::mock();
    $mockInvoiceItemQuery->shouldReceive('whereCompany')
        ->once()
        ->with($companyId)
        ->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('applyInvoiceFilters')
        ->once()
        ->with(['from_date' => '2023-01-01', 'to_date' => '2023-01-31'])
        ->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('itemAttributes')
        ->once()
        ->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('get')
        ->once()
        ->andReturn($mockInvoiceItemsCollection);
    InvoiceItem::shouldReceive('whereCompany')
        ->once()
        ->andReturn($mockInvoiceItemQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->shouldReceive('getAttribute')->with('code')->andReturn($currencyCode);
    $mockCurrency->code = $currencyCode;
    Currency::shouldReceive('findOrFail')
        ->once()
        ->with($currencyId)
        ->andReturn($mockCurrency);

    $mockCarbonFromDate = Mockery::mock(Carbon::class);
    $mockCarbonFromDate->shouldReceive('format')
        ->once()
        ->with($dateFormat)
        ->andReturn('01/01/2023');

    $mockCarbonToDate = Mockery::mock(Carbon::class);
    $mockCarbonToDate->shouldReceive('format')
        ->once()
        ->with($dateFormat)
        ->andReturn('31/01/2023');

    Mockery::mock('overload:Carbon\Carbon')
        ->shouldReceive('createFromFormat')
        ->twice()
        ->andReturn($mockCarbonFromDate, $mockCarbonToDate);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('only')
        ->once()
        ->with(['from_date', 'to_date'])
        ->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('has')
        ->with('preview')
        ->andReturnFalse();
    $request->shouldReceive('has')
        ->with('download')
        ->andReturnFalse();

    App::shouldReceive('setLocale')
        ->once()
        ->with($locale);

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($data) use ($mockInvoiceItemsCollection, $mockCompany, $mockCurrency, $mockColorSettingsCollection) {
            expect($data)->toHaveKeys([
                'items',
                'colorSettings',
                'totalAmount',
                'company',
                'from_date',
                'to_date',
                'currency',
            ]);
            expect($data['items'])->toBe($mockInvoiceItemsCollection);
            expect($data['colorSettings'])->toBe($mockColorSettingsCollection);
            expect($data['totalAmount'])->toBe(150);
            expect($data['company'])->toBe($mockCompany);
            expect($data['from_date'])->toBe('01/01/2023');
            expect($data['to_date'])->toBe('31/01/2023');
            expect($data['currency'])->toBe($mockCurrency);
            return true;
        }));

    $mockPdfInstance = Mockery::mock();
    $mockPdfInstance->shouldReceive('stream')
        ->once()
        ->andReturn('PDF Stream Content');

    PDF::shouldReceive('loadView')
        ->once()
        ->with('app.pdf.reports.sales-items')
        ->andReturn($mockPdfInstance);

    $result = $controller($request, $hash);

    expect($result)->toBe('PDF Stream Content');
});

test('it downloads the item sales report PDF when download flag is present', function () {
    $controller = new ItemSalesReportController();

    $hash = 'some-unique-hash';
    $companyId = 1;
    $locale = 'en';
    $dateFormat = 'd/m/Y';
    $currencyId = 1;

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->shouldReceive('getAttribute')->with('id')->andReturn($companyId);
    $mockCompany->shouldReceive('getAttribute')->with('unique_hash')->andReturn($hash);
    Company::shouldReceive('where->first')
        ->once()
        ->andReturn($mockCompany);

    CompanySetting::shouldReceive('getSetting')
        ->times(3)
        ->andReturn($locale, $dateFormat, $currencyId);

    $mockCompanySettingQuery = Mockery::mock();
    $mockCompanySettingQuery->shouldReceive('whereIn')->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('whereCompany')->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('get')->andReturn(collect());
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    $mockInvoiceItemQuery = Mockery::mock();
    $mockInvoiceItemQuery->shouldReceive('whereCompany')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('applyInvoiceFilters')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('itemAttributes')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('get')->andReturn(collect());
    InvoiceItem::shouldReceive('whereCompany')->andReturn($mockInvoiceItemQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->code = 'USD';
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    Mockery::mock('overload:Carbon\Carbon')
        ->shouldReceive('createFromFormat')
        ->twice()
        ->andReturn(
            Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('01/01/2023')->getMock(),
            Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('31/01/2023')->getMock()
        );

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('has')
        ->with('preview')
        ->andReturnFalse();
    $request->shouldReceive('has')
        ->with('download')
        ->andReturnTrue();

    App::shouldReceive('setLocale')->once();
    View::shouldReceive('share')->once();

    $mockPdfInstance = Mockery::mock();
    $mockPdfInstance->shouldReceive('download')
        ->once()
        ->andReturn('PDF Download Response');

    PDF::shouldReceive('loadView')
        ->once()
        ->with('app.pdf.reports.sales-items')
        ->andReturn($mockPdfInstance);

    $result = $controller($request, $hash);

    expect($result)->toBe('PDF Download Response');
});

test('it previews the item sales report HTML when preview flag is present', function () {
    $controller = new ItemSalesReportController();

    $hash = 'some-unique-hash';
    $companyId = 1;
    $locale = 'en';
    $dateFormat = 'd/m/Y';
    $currencyId = 1;

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->shouldReceive('getAttribute')->with('id')->andReturn($companyId);
    $mockCompany->shouldReceive('getAttribute')->with('unique_hash')->andReturn($hash);
    Company::shouldReceive('where->first')
        ->once()
        ->andReturn($mockCompany);

    CompanySetting::shouldReceive('getSetting')
        ->times(3)
        ->andReturn($locale, $dateFormat, $currencyId);

    $mockCompanySettingQuery = Mockery::mock();
    $mockCompanySettingQuery->shouldReceive('whereIn')->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('whereCompany')->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('get')->andReturn(collect());
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    $mockInvoiceItemQuery = Mockery::mock();
    $mockInvoiceItemQuery->shouldReceive('whereCompany')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('applyInvoiceFilters')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('itemAttributes')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('get')->andReturn(collect());
    InvoiceItem::shouldReceive('whereCompany')->andReturn($mockInvoiceItemQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->code = 'USD';
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    Mockery::mock('overload:Carbon\Carbon')
        ->shouldReceive('createFromFormat')
        ->twice()
        ->andReturn(
            Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('01/01/2023')->getMock(),
            Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('31/01/2023')->getMock()
        );

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('has')
        ->with('preview')
        ->andReturnTrue();
    $request->shouldReceive('has')
        ->with('download')
        ->andReturnFalse();

    App::shouldReceive('setLocale')->once();
    View::shouldReceive('share')->once();
    PDF::shouldNotReceive('loadView');

    $mockLaravelView = Mockery::mock(\Illuminate\View\View::class);
    View::shouldReceive('make')
        ->once()
        ->with('app.pdf.reports.sales-items', Mockery::any(), Mockery::any())
        ->andReturn($mockLaravelView);

    $result = $controller($request, $hash);

    expect($result)->toBe($mockLaravelView);
});

test('it correctly calculates total amount when no items are found', function () {
    $controller = new ItemSalesReportController();

    $hash = 'some-unique-hash';
    $companyId = 1;
    $locale = 'en';
    $dateFormat = 'd/m/Y';
    $currencyId = 1;

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->shouldReceive('getAttribute')->with('id')->andReturn($companyId);
    $mockCompany->shouldReceive('getAttribute')->with('unique_hash')->andReturn($hash);
    Company::shouldReceive('where->first')
        ->once()
        ->andReturn($mockCompany);

    CompanySetting::shouldReceive('getSetting')
        ->times(3)
        ->andReturn($locale, $dateFormat, $currencyId);

    $mockCompanySettingQuery = Mockery::mock();
    $mockCompanySettingQuery->shouldReceive('whereIn')->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('whereCompany')->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('get')->andReturn(collect());
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    $mockInvoiceItemQuery = Mockery::mock();
    $mockInvoiceItemQuery->shouldReceive('whereCompany')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('applyInvoiceFilters')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('itemAttributes')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('get')
        ->once()
        ->andReturn(collect());
    InvoiceItem::shouldReceive('whereCompany')->andReturn($mockInvoiceItemQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->code = 'USD';
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    Mockery::mock('overload:Carbon\Carbon')
        ->shouldReceive('createFromFormat')
        ->twice()
        ->andReturn(
            Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('01/01/2023')->getMock(),
            Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('31/01/2023')->getMock()
        );

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('has')
        ->with('preview')
        ->andReturnFalse();
    $request->shouldReceive('has')
        ->with('download')
        ->andReturnFalse();

    App::shouldReceive('setLocale')->once();

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($data) {
            expect($data['totalAmount'])->toBe(0);
            expect($data['items'])->toBeEmpty();
            return true;
        }));

    $mockPdfInstance = Mockery::mock();
    $mockPdfInstance->shouldReceive('stream')->andReturn('PDF Stream Content');
    PDF::shouldReceive('loadView')->andReturn($mockPdfInstance);

    $result = $controller($request, $hash);

    expect($result)->toBe('PDF Stream Content');
});

test('it handles different date formats correctly', function () {
    $controller = new ItemSalesReportController();

    $hash = 'some-unique-hash';
    $companyId = 1;
    $locale = 'en';
    $dateFormat = 'm-d-Y';
    $currencyId = 1;

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->shouldReceive('getAttribute')->with('id')->andReturn($companyId);
    $mockCompany->shouldReceive('getAttribute')->with('unique_hash')->andReturn($hash);
    Company::shouldReceive('where->first')->andReturn($mockCompany);

    CompanySetting::shouldReceive('getSetting')
        ->with('language', $companyId)
        ->andReturn($locale);
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat);
    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($currencyId);

    $mockCompanySettingQuery = Mockery::mock();
    $mockCompanySettingQuery->shouldReceive('whereIn')->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('whereCompany')->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('get')->andReturn(collect());
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    $mockInvoiceItemQuery = Mockery::mock();
    $mockInvoiceItemQuery->shouldReceive('whereCompany')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('applyInvoiceFilters')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('itemAttributes')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('get')->andReturn(collect());
    InvoiceItem::shouldReceive('whereCompany')->andReturn($mockInvoiceItemQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->code = 'USD';
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    $mockCarbonFromDate = Mockery::mock(Carbon::class);
    $mockCarbonFromDate->shouldReceive('format')
        ->once()
        ->with($dateFormat)
        ->andReturn('01-01-2023');

    $mockCarbonToDate = Mockery::mock(Carbon::class);
    $mockCarbonToDate->shouldReceive('format')
        ->once()
        ->with($dateFormat)
        ->andReturn('01-31-2023');

    Mockery::mock('overload:Carbon\Carbon')
        ->shouldReceive('createFromFormat')
        ->twice()
        ->andReturn($mockCarbonFromDate, $mockCarbonToDate);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('has')
        ->with('preview')
        ->andReturnFalse();
    $request->shouldReceive('has')
        ->with('download')
        ->andReturnFalse();

    App::shouldReceive('setLocale')->once();

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($data) {
            expect($data['from_date'])->toBe('01-01-2023');
            expect($data['to_date'])->toBe('01-31-2023');
            return true;
        }));

    $mockPdfInstance = Mockery::mock();
    $mockPdfInstance->shouldReceive('stream')->andReturn('PDF Stream Content');
    PDF::shouldReceive('loadView')->andReturn($mockPdfInstance);

    $result = $controller($request, $hash);

    expect($result)->toBe('PDF Stream Content');
});

test('it sets the correct locale based on company settings', function () {
    $controller = new ItemSalesReportController();

    $hash = 'some-unique-hash';
    $companyId = 1;
    $locale = 'fr';
    $dateFormat = 'd/m/Y';
    $currencyId = 1;

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->shouldReceive('getAttribute')->with('id')->andReturn($companyId);
    $mockCompany->shouldReceive('getAttribute')->with('unique_hash')->andReturn($hash);
    Company::shouldReceive('where->first')->andReturn($mockCompany);

    CompanySetting::shouldReceive('getSetting')
        ->with('language', $companyId)
        ->once()
        ->andReturn($locale);
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat);
    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn($currencyId);

    $mockCompanySettingQuery = Mockery::mock();
    $mockCompanySettingQuery->shouldReceive('whereIn')->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('whereCompany')->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('get')->andReturn(collect());
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    $mockInvoiceItemQuery = Mockery::mock();
    $mockInvoiceItemQuery->shouldReceive('whereCompany')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('applyInvoiceFilters')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('itemAttributes')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('get')->andReturn(collect());
    InvoiceItem::shouldReceive('whereCompany')->andReturn($mockInvoiceItemQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->code = 'USD';
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    Mockery::mock('overload:Carbon\Carbon')
        ->shouldReceive('createFromFormat')
        ->twice()
        ->andReturn(
            Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('01/01/2023')->getMock(),
            Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('31/01/2023')->getMock()
        );

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('has')
        ->with('preview')
        ->andReturnFalse();
    $request->shouldReceive('has')
        ->with('download')
        ->andReturnFalse();

    App::shouldReceive('setLocale')
        ->once()
        ->with($locale);

    View::shouldReceive('share')->once();

    $mockPdfInstance = Mockery::mock();
    $mockPdfInstance->shouldReceive('stream')->andReturn('PDF Stream Content');
    PDF::shouldReceive('loadView')->andReturn($mockPdfInstance);

    $result = $controller($request, $hash);

    expect($result)->toBe('PDF Stream Content');
});

test('it includes color settings in shared view data', function () {
    $controller = new ItemSalesReportController();

    $hash = 'some-unique-hash';
    $companyId = 1;
    $locale = 'en';
    $dateFormat = 'd/m/Y';
    $currencyId = 1;

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->shouldReceive('getAttribute')->with('id')->andReturn($companyId);
    $mockCompany->shouldReceive('getAttribute')->with('unique_hash')->andReturn($hash);
    Company::shouldReceive('where->first')->andReturn($mockCompany);

    CompanySetting::shouldReceive('getSetting')
        ->times(3)
        ->andReturn($locale, $dateFormat, $currencyId);

    $expectedColorSettings = collect([
        (object)['option' => 'primary_text_color', 'value' => '#FF0000'],
        (object)['option' => 'footer_bg_color', 'value' => '#0000FF'],
    ]);
    $mockCompanySettingQuery = Mockery::mock();
    $mockCompanySettingQuery->shouldReceive('whereIn')
        ->once()
        ->with(Mockery::subset([
            'primary_text_color', 'heading_text_color', 'section_heading_text_color', 'border_color',
            'body_text_color', 'footer_text_color', 'footer_total_color', 'footer_bg_color', 'date_text_color',
        ]))
        ->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('whereCompany')
        ->once()
        ->with($companyId)
        ->andReturnSelf();
    $mockCompanySettingQuery->shouldReceive('get')
        ->once()
        ->andReturn($expectedColorSettings);
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    $mockInvoiceItemQuery = Mockery::mock();
    $mockInvoiceItemQuery->shouldReceive('whereCompany')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('applyInvoiceFilters')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('itemAttributes')->andReturnSelf();
    $mockInvoiceItemQuery->shouldReceive('get')->andReturn(collect());
    InvoiceItem::shouldReceive('whereCompany')->andReturn($mockInvoiceItemQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->code = 'USD';
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    Mockery::mock('overload:Carbon\Carbon')
        ->shouldReceive('createFromFormat')
        ->twice()
        ->andReturn(
            Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('01/01/2023')->getMock(),
            Mockery::mock(Carbon::class)->shouldReceive('format')->andReturn('31/01/2023')->getMock()
        );

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('has')
        ->with('preview')
        ->andReturnFalse();
    $request->shouldReceive('has')
        ->with('download')
        ->andReturnFalse();

    App::shouldReceive('setLocale')->once();

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($data) use ($expectedColorSettings) {
            expect($data['colorSettings'])->toBe($expectedColorSettings);
            return true;
        }));

    $mockPdfInstance = Mockery::mock();
    $mockPdfInstance->shouldReceive('stream')->andReturn('PDF Stream Content');
    PDF::shouldReceive('loadView')->andReturn($mockPdfInstance);

    $result = $controller($request, $hash);

    expect($result)->toBe('PDF Stream Content');
});
