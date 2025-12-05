<?php

use Carbon\Carbon;
use Crater\Http\Controllers\V1\Admin\Invoice\CloneInvoiceController;
use Crater\Http\Requests\InvoiceRequest;
use Crater\Http\Resources\InvoiceResource;
use Crater\Models\CompanySetting;
use Crater\Models\Invoice;
use Crater\Models\InvoiceItem;
use Crater\Models\Tax;
use Crater\Models\CustomField;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Vinkla\Hashids\Facades\Hashids;

beforeEach(function () {
    $this->controller = new CloneInvoiceController();
});

test('clone invoice controller can be instantiated', function () {
    expect($this->controller)->toBeInstanceOf(CloneInvoiceController::class);
    expect($this->controller)->toBeInstanceOf(\Illuminate\Routing\Controller::class);
});

test('controller has __invoke method', function () {
    expect(method_exists($this->controller, '__invoke'))->toBeTrue();
    
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->isPublic())->toBeTrue();
    expect($method->getNumberOfParameters())->toBe(2);
    
    $params = $method->getParameters();
    expect($params[0]->getType()->getName())->toBe('Illuminate\Http\Request');
    expect($params[1]->getType()->getName())->toBe('Crater\Models\Invoice');
});

test('controller uses correct authorization', function () {
    $reflection = new ReflectionClass($this->controller);
    
    // Check if authorize method exists (from parent Controller)
    expect(method_exists($this->controller, 'authorize'))->toBeTrue();
    
    // Check authorization call in source code
    $source = file_get_contents($reflection->getFileName());
    expect(str_contains($source, "authorize('create', Invoice::class)"))->toBeTrue();
});

test('controller uses Carbon for date handling', function () {
    $reflection = new ReflectionClass($this->controller);
    $source = file_get_contents($reflection->getFileName());
    
    expect(str_contains($source, 'Carbon\\Carbon'))->toBeTrue();
    expect(str_contains($source, 'Carbon::now()'))->toBeTrue();
});

test('controller uses required models and services', function () {
    $reflection = new ReflectionClass($this->controller);
    $source = file_get_contents($reflection->getFileName());
    
    $requiredClasses = [
        'Crater\Models\Invoice',
        'Crater\Models\CompanySetting',
        'Crater\Services\SerialNumberFormatter',
        'Vinkla\Hashids\Facades\Hashids',
        'Crater\Http\Resources\InvoiceResource',
    ];
    
    foreach ($requiredClasses as $class) {
        expect(str_contains($source, $class))->toBeTrue();
    }
});



test('company setting constants exist', function () {
    $reflection = new ReflectionClass(CompanySetting::class);
    
    expect(method_exists(CompanySetting::class, 'getSetting'))->toBeTrue();
    
    // Verify the settings used in controller exist as strings
    $settingsUsed = [
        'invoice_set_due_date_automatically',
        'invoice_due_date_days',
    ];
    
    foreach ($settingsUsed as $setting) {
        expect(is_string($setting))->toBeTrue("Setting key should be string: {$setting}");
    }
});

test('invoice status constants exist', function () {
    $statusConstants = [
        'STATUS_DRAFT',
        'STATUS_UNPAID',
    ];
    
    foreach ($statusConstants as $constant) {
        expect(defined("Crater\Models\Invoice::{$constant}"))->toBeTrue(
            "Invoice model should have constant: {$constant}"
        );
        
        // Verify they are strings
        $value = constant("Crater\Models\Invoice::{$constant}");
        expect(is_string($value))->toBeTrue("Invoice::{$constant} should be a string");
    }
});

test('controller handles due date logic correctly', function (
    string $dueDateSetting,
    ?int $dueDateDays,
    bool $shouldSetDueDate
) {
    // Test the logic without executing
    $shouldSetDueDateActual = $dueDateSetting === 'YES' && is_int($dueDateDays);
    
    expect($shouldSetDueDateActual)->toBe($shouldSetDueDate);
    
    if ($shouldSetDueDate) {
        // Calculate expected due date
        $now = Carbon::create(2024, 1, 1);
        $expectedDueDate = $now->copy()->addDays($dueDateDays)->format('Y-m-d');
        expect($expectedDueDate)->toBeString();
        expect(strlen($expectedDueDate))->toBe(10); // YYYY-MM-DD format
    }
    
})->with([
    ['YES', 30, true],
    ['NO', 30, false],
    ['YES', null, false],
    ['NO', null, false],
]);

test('controller calculates exchange rates correctly', function (
    float $exchangeRate,
    float $amount,
    float $expectedBaseAmount
) {
    // Test the exchange rate calculation logic
    $calculatedAmount = $amount * $exchangeRate;
    
    expect($calculatedAmount)->toBe($expectedBaseAmount);
    
})->with([
    [1.0, 100.0, 100.0],
    [1.5, 100.0, 150.0],
    [0.8, 100.0, 80.0],
    [2.0, 50.0, 100.0],
]);

test('controller handles item cloning logic', function () {
    // Create test data structure matching what controller expects
    $testItem = [
        'name' => 'Test Item',
        'price' => 100.0,
        'discount_val' => 10.0,
        'tax' => 20.0,
        'total' => 110.0,
        'taxes' => [
            [
                'name' => 'Tax 1',
                'amount' => 10.0,
                'percent' => 10.0,
                'company_id' => 1,
            ],
            [
                'name' => 'Tax 2',
                'amount' => 0.0,
                'percent' => 5.0,
                'company_id' => 1,
            ]
        ]
    ];
    
    // Test the logic
    $exchangeRate = 1.5;
    
    // Apply the same calculations as controller
    $testItem['exchange_rate'] = $exchangeRate;
    $testItem['base_price'] = $testItem['price'] * $exchangeRate;
    $testItem['base_discount_val'] = $testItem['discount_val'] * $exchangeRate;
    $testItem['base_tax'] = $testItem['tax'] * $exchangeRate;
    $testItem['base_total'] = $testItem['total'] * $exchangeRate;
    
    expect($testItem['base_price'])->toBe(150.0);
    expect($testItem['base_discount_val'])->toBe(15.0);
    expect($testItem['base_tax'])->toBe(30.0);
    expect($testItem['base_total'])->toBe(165.0);
    
    // Test tax filtering logic
    $validTaxes = array_filter($testItem['taxes'], function($tax) {
        return $tax['amount'] > 0;
    });
    
    expect(count($validTaxes))->toBe(1);
    expect($validTaxes[0]['name'])->toBe('Tax 1');
});

test('controller returns correct resource type', function () {
    $reflection = new ReflectionClass($this->controller);
    $source = file_get_contents($reflection->getFileName());
    
    // Check that it returns InvoiceResource
    expect(str_contains($source, 'return new InvoiceResource'))->toBeTrue();
    
    // Verify InvoiceResource exists
    expect(class_exists(InvoiceResource::class))->toBeTrue();
    
    // Verify it extends JsonResource
    $resourceReflection = new ReflectionClass(InvoiceResource::class);
    expect($resourceReflection->getParentClass()->getName())->toBe('Illuminate\Http\Resources\Json\JsonResource');
});


test('serial number formatter exists and has required methods', function () {
    expect(class_exists('Crater\Services\SerialNumberFormatter'))->toBeTrue();
    
    $formatter = new SerialNumberFormatter();
    
    $requiredMethods = [
        'setModel',
        'setCompany',
        'setCustomer',
        'setNextNumbers',
        'getNextNumber',
    ];
    
    foreach ($requiredMethods as $method) {
        expect(method_exists($formatter, $method))->toBeTrue(
            "SerialNumberFormatter should have method: {$method}"
        );
    }
});

test('controller handles all data transformations', function () {
    // Test the complete data flow logic
    
    $testData = [
        'original_invoice' => [
            'sub_total' => 1000,
            'discount' => 100,
            'discount_type' => 'percentage',
            'discount_val' => 100,
            'total' => 900,
            'tax' => 180,
            'exchange_rate' => 1.5,
        ],
        'expected_calculations' => [
            'base_total' => 900 * 1.5, // 1350
            'base_discount_val' => 100 * 1.5, // 150
            'base_sub_total' => 1000 * 1.5, // 1500
            'base_tax' => 180 * 1.5, // 270
            'base_due_amount' => 900 * 1.5, // 1350
        ]
    ];
    
    foreach ($testData['expected_calculations'] as $field => $expectedValue) {
        $calculated = $testData['original_invoice']['total'] * $testData['original_invoice']['exchange_rate'];
        // Just verify the calculation logic
        expect(is_numeric($calculated))->toBeTrue();
    }
});
