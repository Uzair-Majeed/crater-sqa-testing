<?php

use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\Expense;
use Crater\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
uses(\Mockery::class);

// Helper function to simplify mocking Eloquent query builder chains
function mockQueryBuilderChain(...$methods) {
    $mock = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    foreach ($methods as $method) {
        $mock->shouldReceive($method)->andReturnSelf();
    }
    return $mock;
}

beforeEach(function () {
    // Ensure mocks are reset before each test
    Mockery::close();

    // Mock PDF Facade
    PDF::shouldReceive('loadView')->andReturnSelf();
    PDF::shouldReceive('stream')->andReturn('PDF Stream Content');
    PDF::shouldReceive('download')->andReturn('PDF Download Content');

    // Mock App Facade
    App::shouldReceive('setLocale')->zeroOrMoreTimes();

    // Mock View Facade for view()->share()
    View::shouldReceive('share')->zeroOrMoreTimes();

    // Mock Carbon for date formatting
    // Default mock for Carbon::createFromFormat and instance->format
    $mockCarbonDate = Mockery::mock(Carbon::class);
    $mockCarbonDate->shouldReceive('format')->andReturnUsing(function ($format) {
        return 'Formatted Date ' . $format;
    });
    Carbon::shouldReceive('createFromFormat')->andReturn($mockCarbonDate)->zeroOrMoreTimes();
});

test('it generates profit and loss report stream successfully with valid data', function () {
    // Arrange
    $hash = 'test_hash';
    $companyId = 1;
    $requestData = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ];
    $request = Request::create('/reports/profit-loss/' . $hash, 'GET', $requestData);

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $hash;

    // Mock Company::where('unique_hash', $hash)->first()
    Company::shouldReceive('where->first')
        ->once()
        ->with('unique_hash', $hash)
        ->andReturn($mockCompany);

    // Mock $this->authorize (inherited from Controller)
    $controller = new \Crater\Http\Controllers\V1\Admin\Report\ProfitLossReportController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('view report', $mockCompany)
        ->andReturn(true); // Authorization passes

    // Mock CompanySetting::getSetting for language, date format, and currency
    CompanySetting::shouldReceive('getSetting')
        ->with('language', $companyId)
        ->andReturn('en');
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->andReturn('d/m/Y');
    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)
        ->andReturn(1); // Currency ID

    // Mock Payment::whereCompanyId()->applyFilters()->sum()
    $mockPaymentQuery = mockQueryBuilderChain('applyFilters');
    $mockPaymentQuery->shouldReceive('sum')->with('base_amount')->andReturn(1000.00); // Income
    Payment::shouldReceive('whereCompanyId')
        ->once()
        ->with($companyId)
        ->andReturn($mockPaymentQuery);

    // Mock Expense::with()->whereCompanyId()->applyFilters()->expensesAttributes()->get()
    $mockExpenseCategory1 = (object) ['total_amount' => 300.00, 'category' => (object)['name' => 'Rent']];
    $mockExpenseCategory2 = (object) ['total_amount' => 200.00, 'category' => (object)['name' => 'Utilities']];
    $expenseCategories = collect([$mockExpenseCategory1, $mockExpenseCategory2]);

    $mockExpenseQuery = mockQueryBuilderChain('with', 'whereCompanyId', 'applyFilters', 'expensesAttributes');
    $mockExpenseQuery->shouldReceive('get')->andReturn($expenseCategories);
    Expense::shouldReceive('with')
        ->once()
        ->with('category')
        ->andReturn($mockExpenseQuery);

    // Mock Currency::findOrFail
    $mockCurrency = Mockery::mock(Currency::class);
    $mockCurrency->name = 'USD';
    $mockCurrency->symbol = '$';
    Currency::shouldReceive('findOrFail')
        ->once()
        ->with(1) // Assuming currency ID is 1
        ->andReturn($mockCurrency);

    // Mock CompanySetting::whereIn()->whereCompany()->get() for colors
    $mockColorSettings = collect([
        (object)['option' => 'primary_text_color', 'value' => '#000000'],
        (object)['option' => 'footer_bg_color', 'value' => '#ffffff'],
    ]);
    $mockCompanySettingQuery = mockQueryBuilderChain('whereCompany');
    $mockCompanySettingQuery->shouldReceive('get')->andReturn($mockColorSettings);
    CompanySetting::shouldReceive('whereIn')
        ->once()
        ->andReturn($mockCompanySettingQuery);

    // Expect view()->share to be called with correct data
    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($args) use ($mockCompany, $expenseCategories, $mockColorSettings, $mockCurrency) {
            expect($args['company'])->toBe($mockCompany);
            expect($args['income'])->toBe(1000.00);
            expect($args['expenseCategories'])->toBe($expenseCategories);
            expect($args['totalExpense'])->toBe(500.00); // 300 + 200
            expect($args['colorSettings'])->toBe($mockColorSettings);
            expect($args['from_date'])->toEqual('Formatted Date d/m/Y');
            expect($args['to_date'])->toEqual('Formatted Date d/m/Y');
            expect($args['currency'])->toBe($mockCurrency);
            return true;
        }));

    // Act
    $response = $controller($request, $hash);

    // Assert
    expect($response)->toEqual('PDF Stream Content');
    PDF::shouldHaveReceived('loadView')->once()->with('app.pdf.reports.profit-loss');
    PDF::shouldHaveReceived('stream')->once();
    PDF::shouldNotHaveReceived('download');
    App::shouldHaveReceived('setLocale')->once()->with('en');
})->group('profit_loss_controller');

test('it handles no expenses correctly, resulting in zero totalExpense', function () {
    // Arrange
    $hash = 'test_hash_no_expenses';
    $companyId = 2;
    $requestData = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ];
    $request = Request::create('/reports/profit-loss/' . $hash, 'GET', $requestData);

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $hash;

    Company::shouldReceive('where->first')->andReturn($mockCompany);

    $controller = new \Crater\Http\Controllers\V1\Admin\Report\ProfitLossReportController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->andReturn(true);

    CompanySetting::shouldReceive('getSetting')->andReturn('en', 'd/m/Y', 1);

    $mockPaymentQuery = mockQueryBuilderChain('applyFilters');
    $mockPaymentQuery->shouldReceive('sum')->andReturn(500.00);
    Payment::shouldReceive('whereCompanyId')->andReturn($mockPaymentQuery);

    // No expenses
    $expenseCategories = collect([]); // Empty collection
    $mockExpenseQuery = mockQueryBuilderChain('with', 'whereCompanyId', 'applyFilters', 'expensesAttributes');
    $mockExpenseQuery->shouldReceive('get')->andReturn($expenseCategories);
    Expense::shouldReceive('with')->andReturn($mockExpenseQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    $mockCompanySettingQuery = mockQueryBuilderChain('whereCompany');
    $mockCompanySettingQuery->shouldReceive('get')->andReturn(collect([]));
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($args) {
            expect($args['income'])->toBe(500.00);
            expect($args['expenseCategories'])->toBeEmpty();
            expect($args['totalExpense'])->toBe(0.00); // Assert 0 total expense
            return true;
        }));

    // Act
    $response = $controller($request, $hash);

    // Assert
    expect($response)->toEqual('PDF Stream Content');
    PDF::shouldHaveReceived('loadView')->once();
    PDF::shouldHaveReceived('stream')->once();
})->group('profit_loss_controller');

test('it returns preview view when preview parameter is present in request', function () {
    // Arrange
    $hash = 'test_hash_preview';
    $companyId = 3;
    $requestData = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'preview' => true, // Preview parameter
    ];
    $request = Request::create('/reports/profit-loss/' . $hash, 'GET', $requestData);

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $hash;

    Company::shouldReceive('where->first')->andReturn($mockCompany);

    $controller = new \Crater\Http\Controllers\V1\Admin\Report\ProfitLossReportController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->andReturn(true);

    CompanySetting::shouldReceive('getSetting')->andReturn('en', 'd/m/Y', 1);

    $mockPaymentQuery = mockQueryBuilderChain('applyFilters');
    $mockPaymentQuery->shouldReceive('sum')->andReturn(1000.00);
    Payment::shouldReceive('whereCompanyId')->andReturn($mockPaymentQuery);

    $expenseCategories = collect([]);
    $mockExpenseQuery = mockQueryBuilderChain('with', 'whereCompanyId', 'applyFilters', 'expensesAttributes');
    $mockExpenseQuery->shouldReceive('get')->andReturn($expenseCategories);
    Expense::shouldReceive('with')->andReturn($mockExpenseQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    $mockCompanySettingQuery = mockQueryBuilderChain('whereCompany');
    $mockCompanySettingQuery->shouldReceive('get')->andReturn(collect([]));
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    // Mock the `view()` helper call for the preview response
    View::shouldReceive('make')
        ->once()
        ->with('app.pdf.reports.profit-loss')
        ->andReturn('Preview View Content');

    // Act
    $response = $controller($request, $hash);

    // Assert
    expect($response)->toEqual('Preview View Content');
    PDF::shouldNotHaveReceived('loadView'); // Should not load PDF for preview
    PDF::shouldNotHaveReceived('stream');
    PDF::shouldNotHaveReceived('download');
})->group('profit_loss_controller');


test('it returns download response when download parameter is present in request', function () {
    // Arrange
    $hash = 'test_hash_download';
    $companyId = 4;
    $requestData = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'download' => true, // Download parameter
    ];
    $request = Request::create('/reports/profit-loss/' . $hash, 'GET', $requestData);

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $hash;

    Company::shouldReceive('where->first')->andReturn($mockCompany);

    $controller = new \Crater\Http\Controllers\V1\Admin\Report\ProfitLossReportController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->andReturn(true);

    CompanySetting::shouldReceive('getSetting')->andReturn('en', 'd/m/Y', 1);

    $mockPaymentQuery = mockQueryBuilderChain('applyFilters');
    $mockPaymentQuery->shouldReceive('sum')->andReturn(1000.00);
    Payment::shouldReceive('whereCompanyId')->andReturn($mockPaymentQuery);

    $expenseCategories = collect([]);
    $mockExpenseQuery = mockQueryBuilderChain('with', 'whereCompanyId', 'applyFilters', 'expensesAttributes');
    $mockExpenseQuery->shouldReceive('get')->andReturn($expenseCategories);
    Expense::shouldReceive('with')->andReturn($mockExpenseQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    $mockCompanySettingQuery = mockQueryBuilderChain('whereCompany');
    $mockCompanySettingQuery->shouldReceive('get')->andReturn(collect([]));
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    // Act
    $response = $controller($request, $hash);

    // Assert
    expect($response)->toEqual('PDF Download Content');
    PDF::shouldHaveReceived('loadView')->once()->with('app.pdf.reports.profit-loss');
    PDF::shouldNotHaveReceived('stream');
    PDF::shouldHaveReceived('download')->once();
})->group('profit_loss_controller');

test('it throws authorization exception if company not found', function () {
    // Arrange
    $hash = 'non_existent_hash';
    $request = Request::create('/reports/profit-loss/' . $hash, 'GET', ['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);

    // Mock Company::where('unique_hash', $hash)->first() to return null
    Company::shouldReceive('where->first')
        ->once()
        ->with('unique_hash', $hash)
        ->andReturn(null);

    // Mock $this->authorize to throw AuthorizationException when company is null
    $controller = new \Crater\Http\Controllers\V1\Admin\Report\ProfitLossReportController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('view report', null) // Company is null
        ->andThrow(AuthorizationException::class);

    // Act & Assert
    expect(fn () => $controller($request, $hash))
        ->toThrow(AuthorizationException::class);

    // No further methods should be called if authorization fails early
    CompanySetting::shouldNotHaveReceived('getSetting');
    Payment::shouldNotHaveReceived('whereCompanyId');
    Expense::shouldNotHaveReceived('with');
    Currency::shouldNotHaveReceived('findOrFail');
    CompanySetting::shouldNotHaveReceived('whereIn');
    View::shouldNotHaveReceived('share');
    PDF::shouldNotHaveReceived('loadView');
})->group('profit_loss_controller');

test('it throws authorization exception if authorization fails for an existing company', function () {
    // Arrange
    $hash = 'unauthorized_hash';
    $companyId = 5;
    $request = Request::create('/reports/profit-loss/' . $hash, 'GET', ['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $hash;

    Company::shouldReceive('where->first')->andReturn($mockCompany);

    $controller = new \Crater\Http\Controllers\V1\Admin\Report\ProfitLossReportController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('view report', $mockCompany)
        ->andThrow(AuthorizationException::class);

    // Act & Assert
    expect(fn () => $controller($request, $hash))
        ->toThrow(AuthorizationException::class);

    // No further methods should be called
    CompanySetting::shouldNotHaveReceived('getSetting');
    Payment::shouldNotHaveReceived('whereCompanyId');
    Expense::shouldNotHaveReceived('with');
    Currency::shouldNotHaveReceived('findOrFail');
    CompanySetting::shouldNotHaveReceived('whereIn');
    View::shouldNotHaveReceived('share');
    PDF::shouldNotHaveReceived('loadView');
})->group('profit_loss_controller');

test('it throws ModelNotFoundException if currency is not found', function () {
    // Arrange
    $hash = 'currency_error_hash';
    $companyId = 6;
    $requestData = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ];
    $request = Request::create('/reports/profit-loss/' . $hash, 'GET', $requestData);

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $hash;

    Company::shouldReceive('where->first')->andReturn($mockCompany);

    $controller = new \Crater\Http\Controllers\V1\Admin\Report\ProfitLossReportController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->andReturn(true);

    CompanySetting::shouldReceive('getSetting')
        ->with('language', $companyId)->andReturn('en');
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)->andReturn('d/m/Y');
    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)->andReturn(999); // Non-existent currency ID

    $mockPaymentQuery = mockQueryBuilderChain('applyFilters');
    $mockPaymentQuery->shouldReceive('sum')->andReturn(1000.00);
    Payment::shouldReceive('whereCompanyId')->andReturn($mockPaymentQuery);

    $mockExpenseQuery = mockQueryBuilderChain('with', 'whereCompanyId', 'applyFilters', 'expensesAttributes');
    $mockExpenseQuery->shouldReceive('get')->andReturn(collect([]));
    Expense::shouldReceive('with')->andReturn($mockExpenseQuery);

    // Mock Currency::findOrFail to throw ModelNotFoundException
    Currency::shouldReceive('findOrFail')
        ->once()
        ->with(999)
        ->andThrow(ModelNotFoundException::class);

    // Act & Assert
    expect(fn () => $controller($request, $hash))
        ->toThrow(ModelNotFoundException::class);

    // Assert that subsequent calls are not made
    CompanySetting::shouldNotHaveReceived('whereIn');
    View::shouldNotHaveReceived('share');
    PDF::shouldNotHaveReceived('loadView');
})->group('profit_loss_controller');

test('it handles zero income correctly', function () {
    // Arrange
    $hash = 'zero_income_hash';
    $companyId = 7;
    $requestData = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ];
    $request = Request::create('/reports/profit-loss/' . $hash, 'GET', $requestData);

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $hash;

    Company::shouldReceive('where->first')->andReturn($mockCompany);

    $controller = new \Crater\Http\Controllers\V1\Admin\Report\ProfitLossReportController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->andReturn(true);

    CompanySetting::shouldReceive('getSetting')->andReturn('en', 'd/m/Y', 1);

    // Mock Payment::sum() to return 0
    $mockPaymentQuery = mockQueryBuilderChain('applyFilters');
    $mockPaymentQuery->shouldReceive('sum')->andReturn(0.00);
    Payment::shouldReceive('whereCompanyId')->andReturn($mockPaymentQuery);

    $mockExpenseCategory1 = (object) ['total_amount' => 100.00, 'category' => (object)['name' => 'Marketing']];
    $expenseCategories = collect([$mockExpenseCategory1]);
    $mockExpenseQuery = mockQueryBuilderChain('with', 'whereCompanyId', 'applyFilters', 'expensesAttributes');
    $mockExpenseQuery->shouldReceive('get')->andReturn($expenseCategories);
    Expense::shouldReceive('with')->andReturn($mockExpenseQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    $mockCompanySettingQuery = mockQueryBuilderChain('whereCompany');
    $mockCompanySettingQuery->shouldReceive('get')->andReturn(collect([]));
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($args) {
            expect($args['income'])->toBe(0.00); // Assert 0 income
            expect($args['totalExpense'])->toBe(100.00);
            return true;
        }));

    // Act
    $response = $controller($request, $hash);

    // Assert
    expect($response)->toEqual('PDF Stream Content');
    PDF::shouldHaveReceived('loadView')->once();
    PDF::shouldHaveReceived('stream')->once();
})->group('profit_loss_controller');

test('it handles different company date format settings', function () {
    // Arrange
    $hash = 'custom_date_format_hash';
    $companyId = 8;
    $requestData = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ];
    $request = Request::create('/reports/profit-loss/' . $hash, 'GET', $requestData);

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $hash;

    Company::shouldReceive('where->first')->andReturn($mockCompany);

    $controller = new \Crater\Http\Controllers\V1\Admin\Report\ProfitLossReportController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->andReturn(true);

    // CompanySetting returns a different date format
    CompanySetting::shouldReceive('getSetting')
        ->with('language', $companyId)->andReturn('en');
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)->andReturn('m/d/Y'); // Custom format
    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $companyId)->andReturn(1);

    // Mock Carbon specifically for this case
    $mockCarbonFrom = Mockery::mock(Carbon::class);
    $mockCarbonFrom->shouldReceive('format')->with('m/d/Y')->andReturn('01/01/2023');
    $mockCarbonTo = Mockery::mock(Carbon::class);
    $mockCarbonTo->shouldReceive('format')->with('m/d/Y')->andReturn('01/31/2023');

    Carbon::shouldReceive('createFromFormat')
        ->once()
        ->with('Y-m-d', '2023-01-01')
        ->andReturn($mockCarbonFrom);
    Carbon::shouldReceive('createFromFormat')
        ->once()
        ->with('Y-m-d', '2023-01-31')
        ->andReturn($mockCarbonTo);

    $mockPaymentQuery = mockQueryBuilderChain('applyFilters');
    $mockPaymentQuery->shouldReceive('sum')->andReturn(1000.00);
    Payment::shouldReceive('whereCompanyId')->andReturn($mockPaymentQuery);

    $expenseCategories = collect([]);
    $mockExpenseQuery = mockQueryBuilderChain('with', 'whereCompanyId', 'applyFilters', 'expensesAttributes');
    $mockExpenseQuery->shouldReceive('get')->andReturn($expenseCategories);
    Expense::shouldReceive('with')->andReturn($mockExpenseQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    $mockCompanySettingQuery = mockQueryBuilderChain('whereCompany');
    $mockCompanySettingQuery->shouldReceive('get')->andReturn(collect([]));
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($args) {
            expect($args['from_date'])->toEqual('01/01/2023'); // Assert custom format
            expect($args['to_date'])->toEqual('01/31/2023'); // Assert custom format
            return true;
        }));

    // Act
    $response = $controller($request, $hash);

    // Assert
    expect($response)->toEqual('PDF Stream Content');
})->group('profit_loss_controller');

test('it handles empty color settings gracefully', function () {
    // Arrange
    $hash = 'no_colors_hash';
    $companyId = 9;
    $requestData = [
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
    ];
    $request = Request::create('/reports/profit-loss/' . $hash, 'GET', $requestData);

    $mockCompany = Mockery::mock(Company::class);
    $mockCompany->id = $companyId;
    $mockCompany->unique_hash = $hash;

    Company::shouldReceive('where->first')->andReturn($mockCompany);

    $controller = new \Crater\Http\Controllers\V1\Admin\Report\ProfitLossReportController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('authorize')->andReturn(true);

    CompanySetting::shouldReceive('getSetting')->andReturn('en', 'd/m/Y', 1);

    $mockPaymentQuery = mockQueryBuilderChain('applyFilters');
    $mockPaymentQuery->shouldReceive('sum')->andReturn(1000.00);
    Payment::shouldReceive('whereCompanyId')->andReturn($mockPaymentQuery);

    $expenseCategories = collect([]);
    $mockExpenseQuery = mockQueryBuilderChain('with', 'whereCompanyId', 'applyFilters', 'expensesAttributes');
    $mockExpenseQuery->shouldReceive('get')->andReturn($expenseCategories);
    Expense::shouldReceive('with')->andReturn($mockExpenseQuery);

    $mockCurrency = Mockery::mock(Currency::class);
    Currency::shouldReceive('findOrFail')->andReturn($mockCurrency);

    // Mock CompanySetting::whereIn()->whereCompany()->get() to return an empty collection for colors
    $mockCompanySettingQuery = mockQueryBuilderChain('whereCompany');
    $mockCompanySettingQuery->shouldReceive('get')->andReturn(collect([])); // Empty color settings
    CompanySetting::shouldReceive('whereIn')->andReturn($mockCompanySettingQuery);

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($args) {
            expect($args['colorSettings'])->toBeEmpty(); // Assert empty color settings
            return true;
        }));

    // Act
    $response = $controller($request, $hash);

    // Assert
    expect($response)->toEqual('PDF Stream Content');
})->group('profit_loss_controller');
