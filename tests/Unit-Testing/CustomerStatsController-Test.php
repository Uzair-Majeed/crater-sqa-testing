<?php

use Carbon\Carbon;
use Crater\Http\Controllers\V1\Admin\Customer\CustomerStatsController;
use Crater\Http\Resources\CustomerResource;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Crater\Models\Expense;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
uses(\Mockery::class);

beforeEach(function () {
    // Clear mocks before each test
    Mockery::close();

    // Mock Carbon::now() to a fixed date for predictable results
    // Default to Oct 15, 2023 for general tests
    Carbon::setTestNow(Carbon::create(2023, 10, 15, 12, 0, 0));

    // Mock static methods of models using alias to intercept static calls
    Mockery::mock('alias:Crater\Models\CompanySetting');
    Mockery::mock('alias:Crater\Models\Invoice');
    Mockery::mock('alias:Crater\Models\Expense');
    Mockery::mock('alias:Crater\Models\Payment');
    Mockery::mock('alias:Crater\Models\Customer'); // For Customer::find()

    // Mock CustomerResource and its additional method using overload to intercept `new` calls
    Mockery::mock('overload:Crater\Http\Resources\CustomerResource');
});

afterEach(function () {
    Carbon::setTestNow(); // Reset Carbon's test instance after each test
});

test('it correctly calculates customer stats for default fiscal year (1-12) and current year', function () {
    $companyId = 1;
    $customerId = 123;
    $fiscalYear = '1-12'; // January to December

    // Mock Request instance
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->shouldReceive('has')->with('previous_year')->andReturnFalse()->once();

    // Mock Customer parameter
    $customerParam = Mockery::mock(Customer::class);
    $customerParam->id = $customerId;

    // Mock CompanySetting static call
    CompanySetting::shouldReceive('getSetting')
        ->with('fiscal_year', $companyId)
        ->andReturn($fiscalYear)
        ->once();

    // Mock the controller's protected authorize method
    $controller = Mockery::mock(CustomerStatsController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('view', $customerParam)->once();

    $expectedInvoiceTotals = [];
    $expectedExpenseTotals = [];
    $expectedReceiptTotals = [];
    $expectedNetProfits = [];
    $expectedMonths = [];

    // Simulate query builder chain for Invoice, Expense, Payment models
    $mockQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('whereCustomer')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('whereUser')->andReturnSelf();

    // Loop for 12 months from Jan 2023 to Dec 2023 (based on Carbon::setTestNow(2023, 10, 15) and fiscal year 1-12)
    for ($month = 1; $month <= 12; $month++) {
        $startOfMonth = Carbon::create(2023, $month, 1)->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::create(2023, $month, 1)->endOfMonth()->format('Y-m-d');

        $invoiceSum = $month * 100; // Example data
        $expenseSum = $month * 20;
        $receiptSum = $month * 80;

        Invoice::shouldReceive('whereBetween')
            ->with('invoice_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        // We configure sum for the specific call to whereBetween.
        // Mockery's `getMock()` returns the current mock instance allowing chaining
        $mockQueryBuilder->shouldReceive('sum')
            ->with('total')
            ->andReturn($invoiceSum)
            ->once();

        Expense::shouldReceive('whereBetween')
            ->with('expense_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('sum')
            ->with('amount')
            ->andReturn($expenseSum)
            ->once();

        Payment::shouldReceive('whereBetween')
            ->with('payment_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('sum')
            ->with('amount')
            ->andReturn($receiptSum)
            ->once();

        $expectedInvoiceTotals[] = $invoiceSum;
        $expectedExpenseTotals[] = $expenseSum;
        $expectedReceiptTotals[] = $receiptSum;
        $expectedNetProfits[] = $receiptSum - $expenseSum;
        $expectedMonths[] = Carbon::create(2023, $month, 1)->format('M');
    }

    // Mock for the total calculations for the entire fiscal year (Jan 2023 to Dec 2023)
    $startDateOverall = Carbon::create(2023, 1, 1)->format('Y-m-d');
    $endDateOverall = Carbon::create(2023, 12, 31)->format('Y-m-d');

    Invoice::shouldReceive('whereBetween')
        ->with('invoice_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('total')
        ->andReturn(array_sum($expectedInvoiceTotals))
        ->once();

    Payment::shouldReceive('whereBetween')
        ->with('payment_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('amount')
        ->andReturn(array_sum($expectedReceiptTotals))
        ->once();

    Expense::shouldReceive('whereBetween')
        ->with('expense_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('amount')
        ->andReturn(array_sum($expectedExpenseTotals))
        ->once();

    // Mock Customer::find() static call
    Customer::shouldReceive('find')
        ->with($customerId)
        ->andReturn($customerParam)
        ->once();

    // Configure the CustomerResource mock created by 'overload'
    $mockCustomerResource = Mockery::mock(CustomerResource::class);
    CustomerResource::shouldReceive('__construct')
        ->with($customerParam)
        ->once()
        ->andReturnUsing(function ($customer) use ($mockCustomerResource) {
            // Use reflection to set the protected 'resource' property of the JsonResource base class
            $reflection = new ReflectionClass($mockCustomerResource);
            $property = $reflection->getProperty('resource');
            $property->setAccessible(true);
            $property->setValue($mockCustomerResource, $customer);
            return $mockCustomerResource;
        });

    $expectedChartData = [
        'months' => $expectedMonths,
        'invoiceTotals' => $expectedInvoiceTotals,
        'expenseTotals' => $expectedExpenseTotals,
        'receiptTotals' => $expectedReceiptTotals,
        'netProfit' => array_sum($expectedReceiptTotals) - array_sum($expectedExpenseTotals),
        'netProfits' => $expectedNetProfits,
        'salesTotal' => array_sum($expectedInvoiceTotals),
        'totalReceipts' => array_sum($expectedReceiptTotals),
        'totalExpenses' => array_sum($expectedExpenseTotals),
    ];

    $mockCustomerResource->shouldReceive('additional')
        ->with(['meta' => ['chartData' => $expectedChartData]])
        ->andReturnSelf()
        ->once();

    // Execute the controller's __invoke method
    $response = $controller->__invoke($request, $customerParam);

    // Assertions
    expect($response)->toBeInstanceOf(CustomerResource::class);
    // Further specific assertions are handled by the mock expectations.
});

test('it correctly calculates customer stats for a fiscal year spanning across years (e.g., 7-6)', function () {
    $companyId = 1;
    $customerId = 123;
    $fiscalYear = '7-6'; // July to June

    // Set current date to Oct 15, 2023. Since fiscal year starts July (terms[0] <= $start->month is true),
    // the current fiscal year will be July 2023 - June 2024.
    Carbon::setTestNow(Carbon::create(2023, 10, 15, 12, 0, 0));

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->shouldReceive('has')->with('previous_year')->andReturnFalse()->once();

    // Mock Customer parameter
    $customerParam = Mockery::mock(Customer::class);
    $customerParam->id = $customerId;

    // Mock CompanySetting
    CompanySetting::shouldReceive('getSetting')
        ->with('fiscal_year', $companyId)
        ->andReturn($fiscalYear)
        ->once();

    // Mock the controller's authorize method
    $controller = Mockery::mock(CustomerStatsController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('view', $customerParam)->once();

    $expectedInvoiceTotals = [];
    $expectedExpenseTotals = [];
    $expectedReceiptTotals = [];
    $expectedNetProfits = [];
    $expectedMonths = [];

    $mockQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('whereCustomer')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('whereUser')->andReturnSelf();

    // Loop for 12 months, starting from July 2023 to June 2024
    $currentYear = 2023;
    for ($i = 0; $i < 12; $i++) {
        $month = (7 + $i - 1) % 12 + 1; // Generates 7, 8, ..., 12, then 1, ..., 6
        if ($month < 7) { // If month is before July, we've crossed into the next year
            $currentYear = 2024;
        } else if ($month >= 7 && $i == 0) { // Reset year for the first month of the fiscal year
            $currentYear = 2023;
        }

        $startOfMonth = Carbon::create($currentYear, $month, 1)->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::create($currentYear, $month, 1)->endOfMonth()->format('Y-m-d');

        $invoiceSum = ($i + 1) * 100;
        $expenseSum = ($i + 1) * 20;
        $receiptSum = ($i + 1) * 80;

        Invoice::shouldReceive('whereBetween')
            ->with('invoice_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('sum')
            ->with('total')
            ->andReturn($invoiceSum)
            ->once();

        Expense::shouldReceive('whereBetween')
            ->with('expense_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('sum')
            ->with('amount')
            ->andReturn($expenseSum)
            ->once();

        Payment::shouldReceive('whereBetween')
            ->with('payment_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('sum')
            ->with('amount')
            ->andReturn($receiptSum)
            ->once();

        $expectedInvoiceTotals[] = $invoiceSum;
        $expectedExpenseTotals[] = $expenseSum;
        $expectedReceiptTotals[] = $receiptSum;
        $expectedNetProfits[] = $receiptSum - $expenseSum;
        $expectedMonths[] = Carbon::create($currentYear, $month, 1)->format('M');
    }

    // Mock for the total calculations (July 2023 to June 2024)
    $startDateOverall = Carbon::create(2023, 7, 1)->format('Y-m-d');
    $endDateOverall = Carbon::create(2024, 6, 30)->format('Y-m-d');

    Invoice::shouldReceive('whereBetween')
        ->with('invoice_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('total')
        ->andReturn(array_sum($expectedInvoiceTotals))
        ->once();

    Payment::shouldReceive('whereBetween')
        ->with('payment_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('amount')
        ->andReturn(array_sum($expectedReceiptTotals))
        ->once();

    Expense::shouldReceive('whereBetween')
        ->with('expense_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('amount')
        ->andReturn(array_sum($expectedExpenseTotals))
        ->once();

    // Mock Customer::find()
    Customer::shouldReceive('find')
        ->with($customerId)
        ->andReturn($customerParam)
        ->once();

    // Configure the CustomerResource mock
    $mockCustomerResource = Mockery::mock(CustomerResource::class);
    CustomerResource::shouldReceive('__construct')
        ->with($customerParam)
        ->once()
        ->andReturnUsing(function ($customer) use ($mockCustomerResource) {
            $reflection = new ReflectionClass($mockCustomerResource);
            $property = $reflection->getProperty('resource');
            $property->setAccessible(true);
            $property->setValue($mockCustomerResource, $customer);
            return $mockCustomerResource;
        });

    $expectedChartData = [
        'months' => $expectedMonths,
        'invoiceTotals' => $expectedInvoiceTotals,
        'expenseTotals' => $expectedExpenseTotals,
        'receiptTotals' => $expectedReceiptTotals,
        'netProfit' => array_sum($expectedReceiptTotals) - array_sum($expectedExpenseTotals),
        'netProfits' => $expectedNetProfits,
        'salesTotal' => array_sum($expectedInvoiceTotals),
        'totalReceipts' => array_sum($expectedReceiptTotals),
        'totalExpenses' => array_sum($expectedExpenseTotals),
    ];

    $mockCustomerResource->shouldReceive('additional')
        ->with(['meta' => ['chartData' => $expectedChartData]])
        ->andReturnSelf()
        ->once();

    // Execute the controller
    $response = $controller->__invoke($request, $customerParam);

    // Assertions
    expect($response)->toBeInstanceOf(CustomerResource::class);
});

test('it correctly calculates customer stats for previous year (default fiscal year 1-12)', function () {
    $companyId = 1;
    $customerId = 123;
    $fiscalYear = '1-12'; // January to December

    // Carbon::setTestNow(2023, 10, 15). Requesting previous year means Jan 2022 - Dec 2022.
    Carbon::setTestNow(Carbon::create(2023, 10, 15, 12, 0, 0));

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->shouldReceive('has')->with('previous_year')->andReturnTrue()->once(); // <-- Key difference for previous year

    // Mock Customer parameter
    $customerParam = Mockery::mock(Customer::class);
    $customerParam->id = $customerId;

    // Mock CompanySetting
    CompanySetting::shouldReceive('getSetting')
        ->with('fiscal_year', $companyId)
        ->andReturn($fiscalYear)
        ->once();

    // Mock the controller's authorize method
    $controller = Mockery::mock(CustomerStatsController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('view', $customerParam)->once();

    $expectedInvoiceTotals = [];
    $expectedExpenseTotals = [];
    $expectedReceiptTotals = [];
    $expectedNetProfits = [];
    $expectedMonths = [];

    $mockQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('whereCustomer')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('whereUser')->andReturnSelf();

    // Loop for 12 months from Jan 2022 to Dec 2022
    for ($month = 1; $month <= 12; $month++) {
        $startOfMonth = Carbon::create(2022, $month, 1)->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::create(2022, $month, 1)->endOfMonth()->format('Y-m-d');

        $invoiceSum = $month * 50; // Different example data for previous year
        $expenseSum = $month * 10;
        $receiptSum = $month * 40;

        Invoice::shouldReceive('whereBetween')
            ->with('invoice_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('sum')
            ->with('total')
            ->andReturn($invoiceSum)
            ->once();

        Expense::shouldReceive('whereBetween')
            ->with('expense_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('sum')
            ->with('amount')
            ->andReturn($expenseSum)
            ->once();

        Payment::shouldReceive('whereBetween')
            ->with('payment_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('sum')
            ->with('amount')
            ->andReturn($receiptSum)
            ->once();

        $expectedInvoiceTotals[] = $invoiceSum;
        $expectedExpenseTotals[] = $expenseSum;
        $expectedReceiptTotals[] = $receiptSum;
        $expectedNetProfits[] = $receiptSum - $expenseSum;
        $expectedMonths[] = Carbon::create(2022, $month, 1)->format('M');
    }

    // Mock for the total calculations (Jan 2022 to Dec 2022)
    $startDateOverall = Carbon::create(2022, 1, 1)->format('Y-m-d');
    $endDateOverall = Carbon::create(2022, 12, 31)->format('Y-m-d');

    Invoice::shouldReceive('whereBetween')
        ->with('invoice_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('total')
        ->andReturn(array_sum($expectedInvoiceTotals))
        ->once();

    Payment::shouldReceive('whereBetween')
        ->with('payment_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('amount')
        ->andReturn(array_sum($expectedReceiptTotals))
        ->once();

    Expense::shouldReceive('whereBetween')
        ->with('expense_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('amount')
        ->andReturn(array_sum($expectedExpenseTotals))
        ->once();

    // Mock Customer::find()
    Customer::shouldReceive('find')
        ->with($customerId)
        ->andReturn($customerParam)
        ->once();

    // Configure the CustomerResource mock
    $mockCustomerResource = Mockery::mock(CustomerResource::class);
    CustomerResource::shouldReceive('__construct')
        ->with($customerParam)
        ->once()
        ->andReturnUsing(function ($customer) use ($mockCustomerResource) {
            $reflection = new ReflectionClass($mockCustomerResource);
            $property = $reflection->getProperty('resource');
            $property->setAccessible(true);
            $property->setValue($mockCustomerResource, $customer);
            return $mockCustomerResource;
        });

    $expectedChartData = [
        'months' => $expectedMonths,
        'invoiceTotals' => $expectedInvoiceTotals,
        'expenseTotals' => $expectedExpenseTotals,
        'receiptTotals' => $expectedReceiptTotals,
        'netProfit' => array_sum($expectedReceiptTotals) - array_sum($expectedExpenseTotals),
        'netProfits' => $expectedNetProfits,
        'salesTotal' => array_sum($expectedInvoiceTotals),
        'totalReceipts' => array_sum($expectedReceiptTotals),
        'totalExpenses' => array_sum($expectedExpenseTotals),
    ];

    $mockCustomerResource->shouldReceive('additional')
        ->with(['meta' => ['chartData' => $expectedChartData]])
        ->andReturnSelf()
        ->once();

    // Execute the controller
    $response = $controller->__invoke($request, $customerParam);

    // Assertions
    expect($response)->toBeInstanceOf(CustomerResource::class);
});

test('it handles null sums from queries gracefully, returning zero', function () {
    $companyId = 1;
    $customerId = 123;
    $fiscalYear = '1-12';

    Carbon::setTestNow(Carbon::create(2023, 10, 15, 12, 0, 0));

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->shouldReceive('has')->with('previous_year')->andReturnFalse()->once();

    // Mock Customer parameter
    $customerParam = Mockery::mock(Customer::class);
    $customerParam->id = $customerId;

    // Mock CompanySetting
    CompanySetting::shouldReceive('getSetting')
        ->with('fiscal_year', $companyId)
        ->andReturn($fiscalYear)
        ->once();

    // Mock the controller's authorize method
    $controller = Mockery::mock(CustomerStatsController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('view', $customerParam)->once();

    $expectedInvoiceTotals = [];
    $expectedExpenseTotals = [];
    $expectedReceiptTotals = [];
    $expectedNetProfits = [];
    $expectedMonths = [];

    $mockQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('whereCustomer')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('whereUser')->andReturnSelf();

    // Configure all sums to return null (which should be coalesced to 0 by `?? 0`)
    Invoice::shouldReceive('whereBetween')->zeroOrMoreTimes()->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')->with('total')->andReturn(null)->byDefault();

    Expense::shouldReceive('whereBetween')->zeroOrMoreTimes()->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')->with('amount')->andReturn(null)->byDefault();

    Payment::shouldReceive('whereBetween')->zeroOrMoreTimes()->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')->with('amount')->andReturn(null)->byDefault();

    for ($month = 1; $month <= 12; $month++) {
        $expectedInvoiceTotals[] = 0;
        $expectedExpenseTotals[] = 0;
        $expectedReceiptTotals[] = 0;
        $expectedNetProfits[] = 0; // 0 - 0 = 0
        $expectedMonths[] = Carbon::create(2023, $month, 1)->format('M');
    }

    // Mock Customer::find()
    Customer::shouldReceive('find')
        ->with($customerId)
        ->andReturn($customerParam)
        ->once();

    // Configure the CustomerResource mock
    $mockCustomerResource = Mockery::mock(CustomerResource::class);
    CustomerResource::shouldReceive('__construct')
        ->with($customerParam)
        ->once()
        ->andReturnUsing(function ($customer) use ($mockCustomerResource) {
            $reflection = new ReflectionClass($mockCustomerResource);
            $property = $reflection->getProperty('resource');
            $property->setAccessible(true);
            $property->setValue($mockCustomerResource, $customer);
            return $mockCustomerResource;
        });

    $expectedChartData = [
        'months' => $expectedMonths,
        'invoiceTotals' => $expectedInvoiceTotals,
        'expenseTotals' => $expectedExpenseTotals,
        'receiptTotals' => $expectedReceiptTotals,
        'netProfit' => 0, // All sums are null, so results in 0
        'netProfits' => $expectedNetProfits,
        'salesTotal' => 0,
        'totalReceipts' => 0,
        'totalExpenses' => 0,
    ];

    $mockCustomerResource->shouldReceive('additional')
        ->with(['meta' => ['chartData' => $expectedChartData]])
        ->andReturnSelf()
        ->once();

    // Execute the controller
    $response = $controller->__invoke($request, $customerParam);

    // Assertions
    expect($response)->toBeInstanceOf(CustomerResource::class);
});

test('it correctly handles authorization failure', function () {
    $companyId = 1;
    $customerId = 123;
    $fiscalYear = '1-12';

    Carbon::setTestNow(Carbon::create(2023, 10, 15, 12, 0, 0));

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->shouldReceive('has')->with('previous_year')->andReturnFalse()->once();

    $customerParam = Mockery::mock(Customer::class);
    $customerParam->id = $customerId;

    CompanySetting::shouldReceive('getSetting')
        ->with('fiscal_year', $companyId)
        ->andReturn($fiscalYear)
        ->once();

    // Mock the controller's authorize method to throw an AuthorizationException
    $controller = Mockery::mock(CustomerStatsController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->with('view', $customerParam)
        ->once()
        ->andThrow(new AuthorizationException('Unauthorized.'));

    // Expect an AuthorizationException to be thrown
    expect(function () use ($controller, $request, $customerParam) {
        $controller->__invoke($request, $customerParam);
    })->throws(AuthorizationException::class, 'Unauthorized.');

    // Ensure no further model queries were attempted after authorization failure
    // We expect these to not be called AT ALL
    Invoice::shouldNotReceive('whereBetween');
    Expense::shouldNotReceive('whereBetween');
    Payment::shouldNotReceive('whereBetween');
    Customer::shouldNotReceive('find');
    CustomerResource::shouldNotReceive('__construct');
    CustomerResource::shouldNotReceive('additional');
});

test('it correctly calculates customer stats when fiscal year starts in future month of current year', function () {
    $companyId = 1;
    $customerId = 123;
    $fiscalYear = '11-10'; // November to October

    // Set current date to Oct 15, 2023. Since fiscal year starts Nov (terms[0] <= $start->month is false),
    // the current fiscal year will be Nov 2022 - Oct 2023.
    Carbon::setTestNow(Carbon::create(2023, 10, 15, 12, 0, 0));

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->once();
    $request->shouldReceive('has')->with('previous_year')->andReturnFalse()->once();

    // Mock Customer parameter
    $customerParam = Mockery::mock(Customer::class);
    $customerParam->id = $customerId;

    // Mock CompanySetting
    CompanySetting::shouldReceive('getSetting')
        ->with('fiscal_year', $companyId)
        ->andReturn($fiscalYear)
        ->once();

    // Mock the controller's authorize method
    $controller = Mockery::mock(CustomerStatsController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('view', $customerParam)->once();

    $expectedInvoiceTotals = [];
    $expectedExpenseTotals = [];
    $expectedReceiptTotals = [];
    $expectedNetProfits = [];
    $expectedMonths = [];

    $mockQueryBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('whereCustomer')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('whereUser')->andReturnSelf();

    // Loop for 12 months, starting from Nov 2022 to Oct 2023
    $currentYear = 2022; // Initial year because fiscal year month (11) is > current month (10)
    for ($i = 0; $i < 12; $i++) {
        $month = (11 + $i - 1) % 12 + 1; // Generates 11, 12, then 1, ..., 10
        if ($month < 11) { // If month is before Nov, we've crossed into the next year
            $currentYear = 2023;
        } else if ($month >= 11 && $i == 0) { // Reset year for the first month of the fiscal year
            $currentYear = 2022;
        }

        $startOfMonth = Carbon::create($currentYear, $month, 1)->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::create($currentYear, $month, 1)->endOfMonth()->format('Y-m-d');

        $invoiceSum = ($i + 1) * 100;
        $expenseSum = ($i + 1) * 20;
        $receiptSum = ($i + 1) * 80;

        Invoice::shouldReceive('whereBetween')
            ->with('invoice_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('sum')
            ->with('total')
            ->andReturn($invoiceSum)
            ->once();

        Expense::shouldReceive('whereBetween')
            ->with('expense_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('sum')
            ->with('amount')
            ->andReturn($expenseSum)
            ->once();

        Payment::shouldReceive('whereBetween')
            ->with('payment_date', [$startOfMonth, $endOfMonth])
            ->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('sum')
            ->with('amount')
            ->andReturn($receiptSum)
            ->once();

        $expectedInvoiceTotals[] = $invoiceSum;
        $expectedExpenseTotals[] = $expenseSum;
        $expectedReceiptTotals[] = $receiptSum;
        $expectedNetProfits[] = $receiptSum - $expenseSum;
        $expectedMonths[] = Carbon::create($currentYear, $month, 1)->format('M');
    }

    // Mock for the total calculations (Nov 2022 to Oct 2023)
    $startDateOverall = Carbon::create(2022, 11, 1)->format('Y-m-d');
    $endDateOverall = Carbon::create(2023, 10, 31)->format('Y-m-d');

    Invoice::shouldReceive('whereBetween')
        ->with('invoice_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('total')
        ->andReturn(array_sum($expectedInvoiceTotals))
        ->once();

    Payment::shouldReceive('whereBetween')
        ->with('payment_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('amount')
        ->andReturn(array_sum($expectedReceiptTotals))
        ->once();

    Expense::shouldReceive('whereBetween')
        ->with('expense_date', [$startDateOverall, $endDateOverall])
        ->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('sum')
        ->with('amount')
        ->andReturn(array_sum($expectedExpenseTotals))
        ->once();

    // Mock Customer::find()
    Customer::shouldReceive('find')
        ->with($customerId)
        ->andReturn($customerParam)
        ->once();

    // Configure the CustomerResource mock
    $mockCustomerResource = Mockery::mock(CustomerResource::class);
    CustomerResource::shouldReceive('__construct')
        ->with($customerParam)
        ->once()
        ->andReturnUsing(function ($customer) use ($mockCustomerResource) {
            $reflection = new ReflectionClass($mockCustomerResource);
            $property = $reflection->getProperty('resource');
            $property->setAccessible(true);
            $property->setValue($mockCustomerResource, $customer);
            return $mockCustomerResource;
        });

    $expectedChartData = [
        'months' => $expectedMonths,
        'invoiceTotals' => $expectedInvoiceTotals,
        'expenseTotals' => $expectedExpenseTotals,
        'receiptTotals' => $expectedReceiptTotals,
        'netProfit' => array_sum($expectedReceiptTotals) - array_sum($expectedExpenseTotals),
        'netProfits' => $expectedNetProfits,
        'salesTotal' => array_sum($expectedInvoiceTotals),
        'totalReceipts' => array_sum($expectedReceiptTotals),
        'totalExpenses' => array_sum($expectedExpenseTotals),
    ];

    $mockCustomerResource->shouldReceive('additional')
        ->with(['meta' => ['chartData' => $expectedChartData]])
        ->andReturnSelf()
        ->once();

    // Execute the controller
    $response = $controller->__invoke($request, $customerParam);

    // Assertions
    expect($response)->toBeInstanceOf(CustomerResource::class);
});
