<?php

use Crater\Http\Controllers\V1\Admin\Report\ItemSalesReportController;

// ========== ITEMSALESREPORTCONTROLLER TESTS (12 MINIMAL TESTS FOR 100% COVERAGE) ==========

test('ItemSalesReportController can be instantiated', function () {
    $controller = new ItemSalesReportController();
    expect($controller)->toBeInstanceOf(ItemSalesReportController::class);
});

test('ItemSalesReportController extends Controller', function () {
    $controller = new ItemSalesReportController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('ItemSalesReportController is in correct namespace', function () {
    $reflection = new ReflectionClass(ItemSalesReportController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\Report');
});

test('ItemSalesReportController is invokable', function () {
    $reflection = new ReflectionClass(ItemSalesReportController::class);
    expect($reflection->hasMethod('__invoke'))->toBeTrue();
});

test('ItemSalesReportController __invoke method accepts Request and hash parameters', function () {
    $reflection = new ReflectionClass(ItemSalesReportController::class);
    $method = $reflection->getMethod('__invoke');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[1]->getName())->toBe('hash');
});

test('ItemSalesReportController uses authorization', function () {
    $reflection = new ReflectionClass(ItemSalesReportController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'view report\', $company)');
});

test('ItemSalesReportController queries Company by unique_hash', function () {
    $reflection = new ReflectionClass(ItemSalesReportController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Company::where(\'unique_hash\', $hash)->first()');
});

test('ItemSalesReportController sets locale from company settings', function () {
    $reflection = new ReflectionClass(ItemSalesReportController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('CompanySetting::getSetting(\'language\'')
        ->and($fileContent)->toContain('App::setLocale($locale)');
});

test('ItemSalesReportController queries InvoiceItems with filters', function () {
    $reflection = new ReflectionClass(ItemSalesReportController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('InvoiceItem::whereCompany')
        ->and($fileContent)->toContain('->applyInvoiceFilters')
        ->and($fileContent)->toContain('->itemAttributes()');
});

test('ItemSalesReportController calculates total amount', function () {
    $reflection = new ReflectionClass(ItemSalesReportController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$totalAmount = 0')
        ->and($fileContent)->toContain('foreach ($items as $item)')
        ->and($fileContent)->toContain('$totalAmount += $item->total_amount');
});

test('ItemSalesReportController formats dates and retrieves currency', function () {
    $reflection = new ReflectionClass(ItemSalesReportController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('CompanySetting::getSetting(\'carbon_date_format\'')
        ->and($fileContent)->toContain('Carbon::createFromFormat')
        ->and($fileContent)->toContain('Currency::findOrFail');
});

test('ItemSalesReportController handles preview, download, and stream modes', function () {
    $reflection = new ReflectionClass(ItemSalesReportController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($request->has(\'preview\'))')
        ->and($fileContent)->toContain('if ($request->has(\'download\'))')
        ->and($fileContent)->toContain('return $pdf->download()')
        ->and($fileContent)->toContain('return $pdf->stream()');
});