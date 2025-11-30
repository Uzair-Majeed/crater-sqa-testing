<?php

use Carbon\Carbon;
use Crater\Http\Controllers\V1\Admin\Report\ExpensesReportController;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\Expense;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Mockery\MockInterface;

// Define a common setup for most tests
beforeEach(function () {
    Mockery::close(); // Ensure mocks are fresh for each test

    // Base Company mock
    $this->mockCompany = Mockery::mock(Company::class);
    $this->mockCompany->id = 1;
    $this->mockCompany->shouldReceive('getAttribute')->with('id')->andReturn(1);
    Company::shouldReceive('where')->andReturnSelf();
    Company::shouldReceive('first')->andReturn($this->mockCompany);

    // Base Controller mock for authorization
    $this->controller = Mockery::mock(ExpensesReportController::class)->makePartial();
    $this->controller->shouldAllowMockingProtectedMethods();
    $this->controller->shouldReceive('authorize')->zeroOrMoreTimes()->andReturn(true);

    // Base CompanySetting::getSetting mocks
    CompanySetting::shouldReceive('getSetting')
        ->with('language', $this->mockCompany->id)
        ->andReturn('en');
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', $this->mockCompany->id)
        ->andReturn('d/m/Y');
    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $this->mockCompany->id)
        ->andReturn(1);

    // Base App::setLocale mock
    App::shouldReceive('setLocale')->zeroOrMoreTimes()->with('en');

    // Base Expense data mock
    $mockExpenseCategory1 = (object)['total_amount' => 100, 'category' => (object)['name' => 'Category A']];
    $mockExpenseCategory2 = (object)['total_amount' => 150, 'category' => (object)['name' => 'Category B']];
    $this->mockExpenseCategories = collect([$mockExpenseCategory1, $mockExpenseCategory2]);

    $expenseQueryBuilder = Mockery::mock();
    $expenseQueryBuilder->shouldReceive('with')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('whereCompanyId')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('applyFilters')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('expensesAttributes')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('get')->andReturn($this->mockExpenseCategories);
    Expense::shouldReceive('with')->andReturn($expenseQueryBuilder);

    // Base Carbon mock
    $this->mockCarbonInstance = Mockery::mock(Carbon::class);
    $this->mockCarbonInstance->shouldReceive('format')->zeroOrMoreTimes()->with('d/m/Y')->andReturn('01/01/2023', '31/01/2023');
    Carbon::shouldReceive('createFromFormat')->zeroOrMoreTimes()->andReturn($this->mockCarbonInstance);

    // Base Currency mock
    $this->mockCurrency = Mockery::mock(Currency::class);
    $this->mockCurrency->name = 'USD';
    $this->mockCurrency->symbol = '$';
    Currency::shouldReceive('findOrFail')->zeroOrMoreTimes()->with(1)->andReturn($this->mockCurrency);

    // Base CompanySetting color settings mock
    $this->mockColorSettings = collect([
        (object)['option' => 'primary_text_color', 'value' => '#000000'],
        (object)['option' => 'heading_text_color', 'value' => '#111111'],
    ]);
    $companySettingQueryBuilder = Mockery::mock();
    $companySettingQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $companySettingQueryBuilder->shouldReceive('get')->andReturn($this->mockColorSettings);
    CompanySetting::shouldReceive('whereIn')->andReturn($companySettingQueryBuilder);

    // Base View Facade mock for `share`
    View::shouldReceive('share')->zeroOrMoreTimes();

    // Base PDF facade mock for `loadView` (specific behaviors will be mocked in tests)
    $this->mockPdfObject = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    \PDF::shouldReceive('loadView')->zeroOrMoreTimes()->andReturn($this->mockPdfObject);
});

afterEach(function () {
    Mockery::close(); // Cleanup mocks after each test
});

test('it streams the PDF by default', function () {
    // Request setup
    $request = Mockery::mock(Request::class);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->shouldReceive('has')->with('preview')->andReturnFalse();
    $request->shouldReceive('has')->with('download')->andReturnFalse();

    // Specific PDF mock for streaming
    $this->mockPdfObject->shouldReceive('stream')->once()->andReturn('PDF Stream Content');
    $this->mockPdfObject->shouldNotReceive('download');

    // Verify View::share data
    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['expenseCategories'] === $this->mockExpenseCategories
                && $data['colorSettings'] === $this->mockColorSettings
                && $data['totalExpense'] === 250 // 100 + 150
                && $data['company'] === $this->mockCompany
                && $data['from_date'] === '01/01/2023'
                && $data['to_date'] === '31/01/2023'
                && $data['currency'] === $this->mockCurrency;
        }));

    $response = $this->controller->__invoke($request, 'some_hash');

    expect($response)->toBe('PDF Stream Content');
});

test('it downloads the PDF when download parameter is present', function () {
    // Request setup
    $request = Mockery::mock(Request::class);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->shouldReceive('has')->with('preview')->andReturnFalse();
    $request->shouldReceive('has')->with('download')->andReturnTrue();

    // Specific PDF mock for downloading
    $this->mockPdfObject->shouldReceive('download')->once()->andReturn('PDF Download Content');
    $this->mockPdfObject->shouldNotReceive('stream');

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(fn ($data) => $data['totalExpense'] === 250)); // Just check a key

    $response = $this->controller->__invoke($request, 'some_hash');

    expect($response)->toBe('PDF Download Content');
});

test('it previews the PDF view when preview parameter is present', function () {
    // Request setup
    $request = Mockery::mock(Request::class);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->shouldReceive('has')->with('preview')->andReturnTrue();
    $request->shouldReceive('has')->with('download')->andReturnFalse();

    // PDF facade should NOT be called
    \PDF::shouldReceive('loadView')->never();

    // Mock the view() helper which returns a ViewFactory instance that can render a view
    View::shouldReceive('make') // The `view()` helper ultimately calls `View::make`
        ->with('app.pdf.reports.expenses')
        ->andReturn(Mockery::mock(\Illuminate\View\View::class));

    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(fn ($data) => $data['totalExpense'] === 250)); // Just check a key

    $response = $this->controller->__invoke($request, 'some_hash');

    expect($response)->toBeInstanceOf(\Illuminate\View\View::class);
});

test('it handles missing company and throws AuthorizationException', function () {
    Company::shouldReceive('first')->andReturn(null); // No company found

    // The controller's `authorize` method (from trait) will be called with null.
    // This will lead to an AuthorizationException.
    $this->controller->shouldReceive('authorize')->once()->with('view report', null)->andThrow(AuthorizationException::class);

    $request = Mockery::mock(Request::class);

    $this->expectException(AuthorizationException::class);

    $this->controller->__invoke($request, 'non_existent_hash');
});

test('it handles authorization failure', function () {
    // Authorization should explicitly fail
    $this->controller->shouldReceive('authorize')->once()->with('view report', $this->mockCompany)->andThrow(AuthorizationException::class);

    $request = Mockery::mock(Request::class);

    $this->expectException(AuthorizationException::class);

    $this->controller->__invoke($request, 'some_hash');
});

test('it handles Carbon date parsing failure for from_date', function () {
    // Mock the request to have an invalid from_date
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('only')->andReturn(['from_date' => 'invalid-date', 'to_date' => '2023-01-31']);
    $request->from_date = 'invalid-date';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('has')->andReturnFalse();

    // Carbon should throw an exception on the first createFromFormat call
    Carbon::shouldReceive('createFromFormat')
        ->with('Y-m-d', 'invalid-date')
        ->once()
        ->andThrow(new \InvalidArgumentException('Failed to parse time string'));

    $this->expectException(\InvalidArgumentException::class);

    $this->controller->__invoke($request, 'some_hash');
});

test('it handles Carbon date parsing failure for to_date', function () {
    // Mock the request to have an invalid to_date
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => 'invalid-date']);
    $request->from_date = '2023-01-01';
    $request->to_date = 'invalid-date';
    $request->shouldReceive('has')->andReturnFalse();

    // First Carbon call succeeds, second one fails
    $this->mockCarbonInstance->shouldReceive('format')->once()->with('d/m/Y')->andReturn('01/01/2023'); // for from_date
    Carbon::shouldReceive('createFromFormat')
        ->with('Y-m-d', '2023-01-01')
        ->once()
        ->andReturn($this->mockCarbonInstance);

    Carbon::shouldReceive('createFromFormat')
        ->with('Y-m-d', 'invalid-date')
        ->once()
        ->andThrow(new \InvalidArgumentException('Failed to parse time string'));

    $this->expectException(\InvalidArgumentException::class);

    $this->controller->__invoke($request, 'some_hash');
});

test('it handles missing currency when calling findOrFail', function () {
    // CompanySetting should return a non-existent currency ID
    CompanySetting::shouldReceive('getSetting')
        ->with('currency', $this->mockCompany->id)
        ->andReturn(999);

    // Currency::findOrFail should throw ModelNotFoundException
    Currency::shouldReceive('findOrFail')
        ->with(999)
        ->once()
        ->andThrow(ModelNotFoundException::class);

    // Request setup
    $request = Mockery::mock(Request::class);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->shouldReceive('has')->andReturnFalse();

    $this->expectException(ModelNotFoundException::class);

    $this->controller->__invoke($request, 'some_hash');
});

test('it calculates total amount correctly with empty expense categories', function () {
    // Override expense categories to be empty
    $expenseQueryBuilder = Mockery::mock();
    $expenseQueryBuilder->shouldReceive('with')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('whereCompanyId')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('applyFilters')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('expensesAttributes')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('get')->andReturn(collect([])); // Empty collection
    Expense::shouldReceive('with')->andReturn($expenseQueryBuilder);

    // Request setup
    $request = Mockery::mock(Request::class);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->shouldReceive('has')->with('preview')->andReturnFalse();
    $request->shouldReceive('has')->with('download')->andReturnFalse();

    // Specific PDF mock for streaming
    $this->mockPdfObject->shouldReceive('stream')->once()->andReturn('PDF Stream Content');

    // Verify View::share data, totalExpense should be 0
    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['totalExpense'] === 0;
        }));

    $response = $this->controller->__invoke($request, 'some_hash');

    expect($response)->toBe('PDF Stream Content');
});

test('it handles no color settings gracefully', function () {
    // Override color settings to be empty
    $companySettingQueryBuilder = Mockery::mock();
    $companySettingQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $companySettingQueryBuilder->shouldReceive('get')->andReturn(collect([])); // Empty collection
    CompanySetting::shouldReceive('whereIn')->andReturn($companySettingQueryBuilder);

    // Request setup
    $request = Mockery::mock(Request::class);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->shouldReceive('has')->with('preview')->andReturnFalse();
    $request->shouldReceive('has')->with('download')->andReturnFalse();

    // Specific PDF mock for streaming
    $this->mockPdfObject->shouldReceive('stream')->once()->andReturn('PDF Stream Content');

    // Verify View::share data, colorSettings should be empty
    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['colorSettings']->isEmpty();
        }));

    $response = $this->controller->__invoke($request, 'some_hash');

    expect($response)->toBe('PDF Stream Content');
});

test('it handles zero total expenses correctly', function () {
    // Override expense categories to have total_amount zero
    $mockExpenseCategory1 = (object)['total_amount' => 0, 'category' => (object)['name' => 'Category A']];
    $mockExpenseCategory2 = (object)['total_amount' => 0, 'category' => (object)['name' => 'Category B']];
    $zeroExpenseCategories = collect([$mockExpenseCategory1, $mockExpenseCategory2]);

    $expenseQueryBuilder = Mockery::mock();
    $expenseQueryBuilder->shouldReceive('with')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('whereCompanyId')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('applyFilters')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('expensesAttributes')->andReturnSelf();
    $expenseQueryBuilder->shouldReceive('get')->andReturn($zeroExpenseCategories);
    Expense::shouldReceive('with')->andReturn($expenseQueryBuilder);

    // Request setup
    $request = Mockery::mock(Request::class);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->shouldReceive('has')->with('preview')->andReturnFalse();
    $request->shouldReceive('has')->with('download')->andReturnFalse();

    // Specific PDF mock for streaming
    $this->mockPdfObject->shouldReceive('stream')->once()->andReturn('PDF Stream Content');

    // Verify View::share data, totalExpense should be 0
    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['totalExpense'] === 0;
        }));

    $response = $this->controller->__invoke($request, 'some_hash');

    expect($response)->toBe('PDF Stream Content');
});

test('it uses correct date format from company settings', function () {
    // Override date format setting
    CompanySetting::shouldReceive('getSetting')
        ->with('carbon_date_format', $this->mockCompany->id)
        ->andReturn('m-d-Y'); // New date format

    // Request setup
    $request = Mockery::mock(Request::class);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->shouldReceive('has')->with('preview')->andReturnFalse();
    $request->shouldReceive('has')->with('download')->andReturnFalse();

    // Carbon mock must reflect the new format
    $this->mockCarbonInstance->shouldReceive('format')->zeroOrMoreTimes()->with('m-d-Y')->andReturn('01-01-2023', '01-31-2023');

    // Specific PDF mock for streaming
    $this->mockPdfObject->shouldReceive('stream')->once()->andReturn('PDF Stream Content');

    // Verify View::share data with new date formats
    View::shouldReceive('share')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['from_date'] === '01-01-2023' && $data['to_date'] === '01-31-2023';
        }));

    $response = $this->controller->__invoke($request, 'some_hash');

    expect($response)->toBe('PDF Stream Content');
});

test('it sets the correct locale from company settings', function () {
    // Override locale setting
    CompanySetting::shouldReceive('getSetting')
        ->with('language', $this->mockCompany->id)
        ->andReturn('fr'); // New locale

    // App::setLocale should be called with 'fr'
    App::shouldReceive('setLocale')->once()->with('fr');

    // Request setup
    $request = Mockery::mock(Request::class);
    $request->from_date = '2023-01-01';
    $request->to_date = '2023-01-31';
    $request->shouldReceive('only')->andReturn(['from_date' => '2023-01-01', 'to_date' => '2023-01-31']);
    $request->shouldReceive('has')->andReturnFalse();

    // Specific PDF mock for streaming
    $this->mockPdfObject->shouldReceive('stream')->once()->andReturn('PDF Stream Content');

    $response = $this->controller->__invoke($request, 'some_hash');

    expect($response)->toBe('PDF Stream Content');
    // The App::setLocale assertion is implicitly covered by `->once()` call in App mock.
});
