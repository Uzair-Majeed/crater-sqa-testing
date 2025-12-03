<?php

use Carbon\Carbon;
use Crater\Http\Controllers\V1\Admin\Estimate\ConvertEstimateController;
use Crater\Http\Resources\InvoiceResource;
use Crater\Models\CompanySetting;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;

beforeEach(function () {
    // Reset mocks for each test
    Mockery::close();

    // Set a fixed time for Carbon::now() for predictable test results
    Carbon::setTestNow(Carbon::create(2023, 1, 15, 12, 0, 0));

    // Mock Auth::id() globally for the tests
    Auth::shouldReceive('id')->andReturn(1)->byDefault();
});

afterEach(function () {
    Carbon::setTestNow(null); // Clear test now after each test
});

test('it successfully converts an estimate to an invoice with automatic due date and items with taxes', function () {
    // Arrange
    $companyId = 'test-company-id';
    $customerId = 'test-customer-id';
    $userId = 1;
    $estimateId = 5;
    $newInvoiceId = 10;
    $exchangeRate = 1.2;

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->atLeast()->once();

    // Mock Estimate model
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockEstimate->id = $estimateId;
    $mockEstimate->company_id = $companyId;
    $mockEstimate->customer_id = $customerId;
    $mockEstimate->exchange_rate = $exchangeRate;
    $mockEstimate->sub_total = 100.00;
    $mockEstimate->discount = 10.00;
    $mockEstimate->discount_type = 'percentage';
    $mockEstimate->discount_val = 10.00;
    $mockEstimate->total = 90.00;
    $mockEstimate->tax_per_item = true;
    $mockEstimate->discount_per_item = false;
    $mockEstimate->tax = 5.00;
    $mockEstimate->notes = 'Test notes';
    $mockEstimate->currency_id = 1;
    $mockEstimate->sales_tax_type = 'exclusive';
    $mockEstimate->sales_tax_address_type = 'billing';

    // Expect load relations
    $mockEstimate->shouldReceive('load')->with(['items', 'items.taxes', 'customer', 'taxes'])->once();

    // Mock getInvoiceTemplateName
    $mockEstimate->shouldReceive('getInvoiceTemplateName')->andReturn('modern')->once();

    // Mock items and their taxes
    $estimateItem1 = [
        'id' => 1,
        'name' => 'Item 1',
        'price' => 50.00,
        'quantity' => 1,
        'total' => 50.00,
        'tax' => 5.00,
        'discount_val' => 0,
        'taxes' => [
            ['id' => 101, 'name' => 'GST', 'amount' => 5.00, 'percent' => 5, 'tax_type' => 'inclusive'],
        ],
    ];
    $estimateItem2 = [
        'id' => 2,
        'name' => 'Item 2',
        'price' => 50.00,
        'quantity' => 1,
        'total' => 50.00,
        'tax' => 0, // No tax on this item
        'discount_val' => 0,
        'taxes' => [],
    ];
    $mockEstimate->items = new EloquentCollection([
        (object)$estimateItem1,
        (object)$estimateItem2,
    ]);

    // Mock global taxes
    $estimateGlobalTax1 = [
        'id' => 201,
        'name' => 'Service Tax',
        'amount' => 5.00,
        'percent' => 5,
        'tax_type' => 'exclusive',
        'estimate_id' => $estimateId,
    ];
    $mockEstimate->taxes = new EloquentCollection([
        (object)$estimateGlobalTax1,
    ]);

    // Mock checkForEstimateConvertAction
    $mockEstimate->shouldReceive('checkForEstimateConvertAction')->once();

    // Mock CompanySetting for due date
    CompanySetting::shouldReceive('getSetting')
        ->with('invoice_set_due_date_automatically', $companyId)
        ->andReturn('YES')
        ->once();
    CompanySetting::shouldReceive('getSetting')
        ->with('invoice_due_date_days', $companyId)
        ->andReturn(7)
        ->once();

    // Mock SerialNumberFormatter
    $mockSerialNumberFormatter = Mockery::mock(SerialNumberFormatter::class);
    $mockSerialNumberFormatter->shouldReceive('setModel')->andReturnSelf()->once();
    $mockSerialNumberFormatter->shouldReceive('setCompany')->with($companyId)->andReturnSelf()->once();
    $mockSerialNumberFormatter->shouldReceive('setCustomer')->with($customerId)->andReturnSelf()->once();
    $mockSerialNumberFormatter->shouldReceive('setNextNumbers')->once();
    $mockSerialNumberFormatter->shouldReceive('getNextNumber')->andReturn('INV-001', 'REF-001')->times(2); // For invoice_number and reference_number
    $mockSerialNumberFormatter->nextSequenceNumber = 1;
    $mockSerialNumberFormatter->nextCustomerSequenceNumber = 1;

    // Bind mock instance to the container for `new SerialNumberFormatter()`
    app()->singleton(SerialNumberFormatter::class, fn () => $mockSerialNumberFormatter);

    // Mock Invoice model (the one passed as parameter, not the one being created)
    $mockInvoiceParam = Mockery::mock(Invoice::class);

    // Mock new Invoice object returned by Invoice::create
    $mockCreatedInvoice = Mockery::mock(Invoice::class);
    $mockCreatedInvoice->id = $newInvoiceId;
    $mockCreatedInvoice->shouldReceive('save')->once();

    // Mock relationships for the created invoice to create items/taxes
    $mockInvoiceItemsRelation = Mockery::mock(HasMany::class);
    $mockInvoiceItemsRelation->shouldReceive('create')->times(2)->andReturnUsing(function ($itemData) use ($exchangeRate) {
        // Assertions for item creation
        expect($itemData['company_id'])->toBe('test-company-id'); // Ensure it uses the request header company_id
        expect($itemData['exchange_rate'])->toBe($exchangeRate);
        expect($itemData['base_price'])->toBe($itemData['price'] * $exchangeRate);
        expect($itemData['base_discount_val'])->toBe($itemData['discount_val'] * $exchangeRate);
        expect($itemData['base_tax'])->toBe($itemData['tax'] * $exchangeRate);
        expect($itemData['base_total'])->toBe($itemData['total'] * $exchangeRate);

        $mockItem = Mockery::mock();
        $mockItem->shouldReceive('taxes')->andReturnSelf(); // Chainable
        $mockItem->shouldReceive('taxes->create')->zeroOrMoreTimes(); // Allow calling for items with taxes
        if ($itemData['name'] === 'Item 1') {
            $mockItem->shouldReceive('taxes->create')->once()->with(Mockery::on(function ($taxData) {
                expect($taxData['name'])->toBe('GST');
                expect($taxData['amount'])->toBe(5.00);
                expect($taxData['company_id'])->toBe('test-company-id');
                return true;
            }));
        } else {
            $mockItem->shouldNotReceive('taxes->create');
        }
        return $mockItem;
    });
    $mockCreatedInvoice->shouldReceive('items')->andReturn($mockInvoiceItemsRelation)->once();

    $mockInvoiceTaxesRelation = Mockery::mock(HasMany::class);
    $mockInvoiceTaxesRelation->shouldReceive('create')->once()->andReturnUsing(function ($taxData) use ($exchangeRate, $companyId) {
        // Assertions for global tax creation
        expect($taxData['company_id'])->toBe($companyId);
        expect($taxData['exchange_rate'])->toBe($exchangeRate);
        expect($taxData['base_amount'])->toBe($taxData['amount'] * $exchangeRate);
        expect($taxData)->not->toHaveKey('estimate_id'); // Ensure estimate_id is unset
        expect($taxData['currency_id'])->toBe(1); // From estimate->currency_id
        return true;
    });
    $mockCreatedInvoice->shouldReceive('taxes')->andReturn($mockInvoiceTaxesRelation)->once();

    // Mock Invoice::create()
    Invoice::shouldReceive('create')
        ->once()
        ->andReturn($mockCreatedInvoice)
        ->with(Mockery::on(function ($attributes) use ($companyId, $customerId, $exchangeRate, $userId, $mockEstimate) {
            // Assertions for Invoice creation attributes
            expect($attributes['creator_id'])->toBe($userId);
            expect($attributes['invoice_date'])->toBe('2023-01-15');
            expect($attributes['due_date'])->toBe('2023-01-22'); // Carbon::now()->addDays(7)
            expect($attributes['invoice_number'])->toBe('INV-001');
            expect($attributes['sequence_number'])->toBe(1);
            expect($attributes['customer_sequence_number'])->toBe(1);
            expect($attributes['reference_number'])->toBe('REF-001');
            expect($attributes['customer_id'])->toBe($customerId);
            expect($attributes['company_id'])->toBe($companyId);
            expect($attributes['template_name'])->toBe('modern');
            expect($attributes['status'])->toBe(Invoice::STATUS_DRAFT);
            expect($attributes['paid_status'])->toBe(Invoice::STATUS_UNPAID);
            expect($attributes['sub_total'])->toBe($mockEstimate->sub_total);
            expect($attributes['discount'])->toBe($mockEstimate->discount);
            expect($attributes['discount_type'])->toBe($mockEstimate->discount_type);
            expect($attributes['discount_val'])->toBe($mockEstimate->discount_val);
            expect($attributes['total'])->toBe($mockEstimate->total);
            expect($attributes['due_amount'])->toBe($mockEstimate->total);
            expect($attributes['tax_per_item'])->toBe($mockEstimate->tax_per_item);
            expect($attributes['discount_per_item'])->toBe($mockEstimate->discount_per_item);
            expect($attributes['tax'])->toBe($mockEstimate->tax);
            expect($attributes['notes'])->toBe($mockEstimate->notes);
            expect($attributes['exchange_rate'])->toBe($exchangeRate);
            expect($attributes['base_discount_val'])->toBe($mockEstimate->discount_val * $exchangeRate);
            expect($attributes['base_sub_total'])->toBe($mockEstimate->sub_total * $exchangeRate);
            expect($attributes['base_total'])->toBe($mockEstimate->total * $exchangeRate);
            expect($attributes['base_tax'])->toBe($mockEstimate->tax * $exchangeRate);
            expect($attributes['currency_id'])->toBe($mockEstimate->currency_id);
            expect($attributes['sales_tax_type'])->toBe($mockEstimate->sales_tax_type);
            expect($attributes['sales_tax_address_type'])->toBe($mockEstimate->sales_tax_address_type);
            return true;
        }));

    // Mock Hashids
    Hashids::shouldReceive('connection')->with(Invoice::class)->andReturnSelf()->once();
    Hashids::shouldReceive('encode')->with($newInvoiceId)->andReturn('encoded_hash')->once();
    $mockCreatedInvoice->shouldReceive('setAttribute')->with('unique_hash', 'encoded_hash')->once()->andReturnSelf();

    // Mock Invoice::find() for the final retrieval
    Invoice::shouldReceive('find')->with($newInvoiceId)->andReturn($mockCreatedInvoice)->once();

    // Mock the authorize method on the controller instance
    $controller = new ConvertEstimateController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldReceive('authorize')->with('create', Invoice::class)->once();

    // Act
    $response = $controller->__invoke($request, $mockEstimate, $mockInvoiceParam);

    // Assert
    expect($response)->toBeInstanceOf(InvoiceResource::class);
    expect($response->resource)->toBe($mockCreatedInvoice);
});

test('it successfully converts an estimate to an invoice without automatic due date and no items/taxes', function () {
    // Arrange
    $companyId = 'test-company-id-no-due-date';
    $customerId = 'test-customer-id-no-due-date';
    $userId = 1;
    $estimateId = 6;
    $newInvoiceId = 11;
    $exchangeRate = 1.0;

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->atLeast()->once();

    // Mock Estimate model
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockEstimate->id = $estimateId;
    $mockEstimate->company_id = $companyId;
    $mockEstimate->customer_id = $customerId;
    $mockEstimate->exchange_rate = $exchangeRate;
    $mockEstimate->sub_total = 0.00;
    $mockEstimate->discount = 0.00;
    $mockEstimate->discount_type = 'percentage';
    $mockEstimate->discount_val = 0.00;
    $mockEstimate->total = 0.00;
    $mockEstimate->tax_per_item = false;
    $mockEstimate->discount_per_item = true;
    $mockEstimate->tax = 0.00;
    $mockEstimate->notes = null;
    $mockEstimate->currency_id = 1;
    $mockEstimate->sales_tax_type = 'inclusive';
    $mockEstimate->sales_tax_address_type = 'shipping';

    // Expect load relations
    $mockEstimate->shouldReceive('load')->with(['items', 'items.taxes', 'customer', 'taxes'])->once();

    // Mock getInvoiceTemplateName
    $mockEstimate->shouldReceive('getInvoiceTemplateName')->andReturn('simple')->once();

    // Empty items and taxes
    $mockEstimate->items = new EloquentCollection([]);
    $mockEstimate->taxes = new EloquentCollection([]);

    // Mock checkForEstimateConvertAction
    $mockEstimate->shouldReceive('checkForEstimateConvertAction')->once();

    // Mock CompanySetting for due date (return 'NO')
    CompanySetting::shouldReceive('getSetting')
        ->with('invoice_set_due_date_automatically', $companyId)
        ->andReturn('NO')
        ->once();
    // No call for 'invoice_due_date_days' is expected if 'NO'

    // Mock SerialNumberFormatter
    $mockSerialNumberFormatter = Mockery::mock(SerialNumberFormatter::class);
    $mockSerialNumberFormatter->shouldReceive('setModel')->andReturnSelf()->once();
    $mockSerialNumberFormatter->shouldReceive('setCompany')->with($companyId)->andReturnSelf()->once();
    $mockSerialNumberFormatter->shouldReceive('setCustomer')->with($customerId)->andReturnSelf()->once();
    $mockSerialNumberFormatter->shouldReceive('setNextNumbers')->once();
    $mockSerialNumberFormatter->shouldReceive('getNextNumber')->andReturn('INV-002', 'REF-002')->times(2);
    $mockSerialNumberFormatter->nextSequenceNumber = 2;
    $mockSerialNumberFormatter->nextCustomerSequenceNumber = 2;
    app()->singleton(SerialNumberFormatter::class, fn () => $mockSerialNumberFormatter);

    // Mock Invoice model (the one passed as parameter)
    $mockInvoiceParam = Mockery::mock(Invoice::class);

    // Mock new Invoice object returned by Invoice::create
    $mockCreatedInvoice = Mockery::mock(Invoice::class);
    $mockCreatedInvoice->id = $newInvoiceId;
    $mockCreatedInvoice->shouldReceive('save')->once();

    // No calls to items()->create() or taxes()->create() expected because collections are empty
    $mockCreatedInvoice->shouldNotReceive('items');
    $mockCreatedInvoice->shouldNotReceive('taxes');

    // Mock Invoice::create()
    Invoice::shouldReceive('create')
        ->once()
        ->andReturn($mockCreatedInvoice)
        ->with(Mockery::on(function ($attributes) use ($companyId, $customerId, $exchangeRate, $userId, $mockEstimate) {
            // Assertions for Invoice creation attributes
            expect($attributes['creator_id'])->toBe($userId);
            expect($attributes['invoice_date'])->toBe('2023-01-15');
            expect($attributes['due_date'])->toBeNull(); // This is the key difference
            expect($attributes['invoice_number'])->toBe('INV-002');
            expect($attributes['sequence_number'])->toBe(2);
            expect($attributes['customer_sequence_number'])->toBe(2);
            expect($attributes['reference_number'])->toBe('REF-002');
            expect($attributes['customer_id'])->toBe($customerId);
            expect($attributes['company_id'])->toBe($companyId);
            expect($attributes['template_name'])->toBe('simple');
            expect($attributes['status'])->toBe(Invoice::STATUS_DRAFT);
            expect($attributes['paid_status'])->toBe(Invoice::STATUS_UNPAID);
            expect($attributes['sub_total'])->toBe($mockEstimate->sub_total);
            expect($attributes['discount'])->toBe($mockEstimate->discount);
            expect($attributes['discount_type'])->toBe($mockEstimate->discount_type);
            expect($attributes['discount_val'])->toBe($mockEstimate->discount_val);
            expect($attributes['total'])->toBe($mockEstimate->total);
            expect($attributes['due_amount'])->toBe($mockEstimate->total);
            expect($attributes['tax_per_item'])->toBe($mockEstimate->tax_per_item);
            expect($attributes['discount_per_item'])->toBe($mockEstimate->discount_per_item);
            expect($attributes['tax'])->toBe($mockEstimate->tax);
            expect($attributes['notes'])->toBe($mockEstimate->notes);
            expect($attributes['exchange_rate'])->toBe($exchangeRate);
            expect($attributes['base_discount_val'])->toBe($mockEstimate->discount_val * $exchangeRate);
            expect($attributes['base_sub_total'])->toBe($mockEstimate->sub_total * $exchangeRate);
            expect($attributes['base_total'])->toBe($mockEstimate->total * $exchangeRate);
            expect($attributes['base_tax'])->toBe($mockEstimate->tax * $exchangeRate);
            expect($attributes['currency_id'])->toBe($mockEstimate->currency_id);
            expect($attributes['sales_tax_type'])->toBe($mockEstimate->sales_tax_type);
            expect($attributes['sales_tax_address_type'])->toBe($mockEstimate->sales_tax_address_type);
            return true;
        }));

    // Mock Hashids
    Hashids::shouldReceive('connection')->with(Invoice::class)->andReturnSelf()->once();
    Hashids::shouldReceive('encode')->with($newInvoiceId)->andReturn('encoded_hash_2')->once();
    $mockCreatedInvoice->shouldReceive('setAttribute')->with('unique_hash', 'encoded_hash_2')->once()->andReturnSelf();

    // Mock Invoice::find() for the final retrieval
    Invoice::shouldReceive('find')->with($newInvoiceId)->andReturn($mockCreatedInvoice)->once();

    // Mock the authorize method on the controller instance
    $controller = new ConvertEstimateController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldReceive('authorize')->with('create', Invoice::class)->once();

    // Act
    $response = $controller->__invoke($request, $mockEstimate, $mockInvoiceParam);

    // Assert
    expect($response)->toBeInstanceOf(InvoiceResource::class);
    expect($response->resource)->toBe($mockCreatedInvoice);
});

test('it handles items with zero amount taxes and unsets estimate_id for global taxes', function () {
    // Arrange
    $companyId = 'company-with-zero-tax';
    $customerId = 'customer-with-zero-tax';
    $userId = 1;
    $estimateId = 7;
    $newInvoiceId = 12;
    $exchangeRate = 1.0;

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->atLeast()->once();

    // Mock Estimate model
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockEstimate->id = $estimateId;
    $mockEstimate->company_id = $companyId;
    $mockEstimate->customer_id = $customerId;
    $mockEstimate->exchange_rate = $exchangeRate;
    $mockEstimate->sub_total = 100.00;
    $mockEstimate->discount = 0.00;
    $mockEstimate->discount_type = 'fixed';
    $mockEstimate->discount_val = 0.00;
    $mockEstimate->total = 100.00;
    $mockEstimate->tax_per_item = true;
    $mockEstimate->discount_per_item = false;
    $mockEstimate->tax = 0.00;
    $mockEstimate->notes = 'Zero tax notes';
    $mockEstimate->currency_id = 2;
    $mockEstimate->sales_tax_type = 'none';
    $mockEstimate->sales_tax_address_type = 'none';

    // Expect load relations
    $mockEstimate->shouldReceive('load')->with(['items', 'items.taxes', 'customer', 'taxes'])->once();

    // Mock getInvoiceTemplateName
    $mockEstimate->shouldReceive('getInvoiceTemplateName')->andReturn('custom')->once();

    // Mock items with a zero-amount tax
    $estimateItem1 = [
        'id' => 1,
        'name' => 'Item with zero tax',
        'price' => 100.00,
        'quantity' => 1,
        'total' => 100.00,
        'tax' => 0.00,
        'discount_val' => 0,
        'taxes' => [
            ['id' => 301, 'name' => 'VAT Zero', 'amount' => 0.00, 'percent' => 0, 'tax_type' => 'inclusive'],
            ['id' => 302, 'name' => 'VAT Five', 'amount' => 5.00, 'percent' => 5, 'tax_type' => 'inclusive'],
        ],
    ];
    $mockEstimate->items = new EloquentCollection([
        (object)$estimateItem1,
    ]);

    // Mock global taxes with an estimate_id that should be unset
    $estimateGlobalTax1 = [
        'id' => 401,
        'name' => 'Special Tax',
        'amount' => 2.50,
        'percent' => 2.5,
        'tax_type' => 'exclusive',
        'estimate_id' => $estimateId, // This should be unset
    ];
    $mockEstimate->taxes = new EloquentCollection([
        (object)$estimateGlobalTax1,
    ]);

    // Mock checkForEstimateConvertAction
    $mockEstimate->shouldReceive('checkForEstimateConvertAction')->once();

    // Mock CompanySetting for due date
    CompanySetting::shouldReceive('getSetting')
        ->with('invoice_set_due_date_automatically', $companyId)
        ->andReturn('YES')
        ->once();
    CompanySetting::shouldReceive('getSetting')
        ->with('invoice_due_date_days', $companyId)
        ->andReturn(0) // Due date is today
        ->once();

    // Mock SerialNumberFormatter
    $mockSerialNumberFormatter = Mockery::mock(SerialNumberFormatter::class);
    $mockSerialNumberFormatter->shouldReceive('setModel')->andReturnSelf()->once();
    $mockSerialNumberFormatter->shouldReceive('setCompany')->with($companyId)->andReturnSelf()->once();
    $mockSerialNumberFormatter->shouldReceive('setCustomer')->with($customerId)->andReturnSelf()->once();
    $mockSerialNumberFormatter->shouldReceive('setNextNumbers')->once();
    $mockSerialNumberFormatter->shouldReceive('getNextNumber')->andReturn('INV-003', 'REF-003')->times(2);
    $mockSerialNumberFormatter->nextSequenceNumber = 3;
    $mockSerialNumberFormatter->nextCustomerSequenceNumber = 3;
    app()->singleton(SerialNumberFormatter::class, fn () => $mockSerialNumberFormatter);

    // Mock Invoice model (param)
    $mockInvoiceParam = Mockery::mock(Invoice::class);

    // Mock new Invoice object returned by Invoice::create
    $mockCreatedInvoice = Mockery::mock(Invoice::class);
    $mockCreatedInvoice->id = $newInvoiceId;
    $mockCreatedInvoice->shouldReceive('save')->once();

    // Mock relationships for the created invoice to create items/taxes
    $mockInvoiceItemsRelation = Mockery::mock(HasMany::class);
    $mockInvoiceItemsRelation->shouldReceive('create')->times(1)->andReturnUsing(function ($itemData) use ($exchangeRate, $companyId) {
        expect($itemData['company_id'])->toBe($companyId);
        $mockItem = Mockery::mock();
        $mockItem->shouldReceive('taxes')->andReturnSelf();
        // Expect only one tax (VAT Five) to be created, not VAT Zero because amount is 0.00
        $mockItem->shouldReceive('taxes->create')->once()->with(Mockery::on(function ($taxData) use ($companyId) {
            expect($taxData['name'])->toBe('VAT Five');
            expect($taxData['amount'])->toBe(5.00);
            expect($taxData['company_id'])->toBe($companyId);
            return true;
        }));
        return $mockItem;
    });
    $mockCreatedInvoice->shouldReceive('items')->andReturn($mockInvoiceItemsRelation)->once();

    $mockInvoiceTaxesRelation = Mockery::mock(HasMany::class);
    $mockInvoiceTaxesRelation->shouldReceive('create')->once()->andReturnUsing(function ($taxData) use ($exchangeRate, $companyId) {
        expect($taxData['company_id'])->toBe($companyId);
        expect($taxData['exchange_rate'])->toBe($exchangeRate);
        expect($taxData['base_amount'])->toBe($taxData['amount'] * $exchangeRate);
        expect($taxData)->not->toHaveKey('estimate_id'); // Crucial assertion
        expect($taxData['name'])->toBe('Special Tax');
        expect($taxData['currency_id'])->toBe(2); // From estimate->currency_id
        return true;
    });
    $mockCreatedInvoice->shouldReceive('taxes')->andReturn($mockInvoiceTaxesRelation)->once();

    // Mock Invoice::create()
    Invoice::shouldReceive('create')
        ->once()
        ->andReturn($mockCreatedInvoice)
        ->with(Mockery::on(function ($attributes) use ($companyId, $customerId, $exchangeRate, $userId, $mockEstimate) {
            expect($attributes['creator_id'])->toBe($userId);
            expect($attributes['invoice_date'])->toBe('2023-01-15');
            expect($attributes['due_date'])->toBe('2023-01-15'); // addDays(0)
            expect($attributes['invoice_number'])->toBe('INV-003');
            expect($attributes['reference_number'])->toBe('REF-003');
            return true;
        }));

    // Mock Hashids
    Hashids::shouldReceive('connection')->with(Invoice::class)->andReturnSelf()->once();
    Hashids::shouldReceive('encode')->with($newInvoiceId)->andReturn('encoded_hash_3')->once();
    $mockCreatedInvoice->shouldReceive('setAttribute')->with('unique_hash', 'encoded_hash_3')->once()->andReturnSelf();

    // Mock Invoice::find() for the final retrieval
    Invoice::shouldReceive('find')->with($newInvoiceId)->andReturn($mockCreatedInvoice)->once();

    // Mock the authorize method on the controller instance
    $controller = new ConvertEstimateController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldReceive('authorize')->with('create', Invoice::class)->once();

    // Act
    $response = $controller->__invoke($request, $mockEstimate, $mockInvoiceParam);

    // Assert
    expect($response)->toBeInstanceOf(InvoiceResource::class);
    expect($response->resource)->toBe($mockCreatedInvoice);
});

test('it handles authorization failure', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockInvoiceParam = Mockery::mock(Invoice::class);

    // Mock the authorize method on the controller instance to throw an exception
    $controller = new ConvertEstimateController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldReceive('authorize')->with('create', Invoice::class)->once()->andThrow(new AuthorizationException());

    // Act & Assert
    expect(fn () => $controller->__invoke($request, $mockEstimate, $mockInvoiceParam))
        ->toThrow(AuthorizationException::class);
});

test('it handles missing company header and estimate company_id gracefully', function () {
    // Arrange
    $companyId = null; // Simulate missing company header
    $customerId = 'test-customer-id-no-company';
    $userId = 1;
    $estimateId = 8;
    $newInvoiceId = 13;
    $exchangeRate = 1.0;

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->atLeast()->once();

    // Mock Estimate model
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockEstimate->id = $estimateId;
    $mockEstimate->company_id = null; // Also simulate estimate having no company_id
    $mockEstimate->customer_id = $customerId;
    $mockEstimate->exchange_rate = $exchangeRate;
    $mockEstimate->sub_total = 100.00;
    $mockEstimate->discount = 0.00;
    $mockEstimate->discount_type = 'percentage';
    $mockEstimate->discount_val = 0.00;
    $mockEstimate->total = 100.00;
    $mockEstimate->tax_per_item = true;
    $mockEstimate->discount_per_item = false;
    $mockEstimate->tax = 0.00;
    $mockEstimate->notes = null;
    $mockEstimate->currency_id = 1;
    $mockEstimate->sales_tax_type = 'exclusive';
    $mockEstimate->sales_tax_address_type = 'billing';

    $mockEstimate->shouldReceive('load')->with(['items', 'items.taxes', 'customer', 'taxes'])->once();
    $mockEstimate->shouldReceive('getInvoiceTemplateName')->andReturn('modern')->once();
    $mockEstimate->items = new EloquentCollection([]); // Empty items
    $mockEstimate->taxes = new EloquentCollection([]); // Empty taxes
    $mockEstimate->shouldReceive('checkForEstimateConvertAction')->once();

    // CompanySetting::getSetting would be called with null, which it should handle.
    CompanySetting::shouldReceive('getSetting')
        ->with('invoice_set_due_date_automatically', null) // Expect null for companyId
        ->andReturn('NO')
        ->once();

    // SerialNumberFormatter should be called with null for company_id from estimate
    $mockSerialNumberFormatter = Mockery::mock(SerialNumberFormatter::class);
    $mockSerialNumberFormatter->shouldReceive('setModel')->andReturnSelf()->once();
    $mockSerialNumberFormatter->shouldReceive('setCompany')->with(null)->andReturnSelf()->once(); // Expect null here
    $mockSerialNumberFormatter->shouldReceive('setCustomer')->with($customerId)->andReturnSelf()->once();
    $mockSerialNumberFormatter->shouldReceive('setNextNumbers')->once();
    $mockSerialNumberFormatter->shouldReceive('getNextNumber')->andReturn('INV-004', 'REF-004')->times(2);
    $mockSerialNumberFormatter->nextSequenceNumber = 4;
    $mockSerialNumberFormatter->nextCustomerSequenceNumber = 4;
    app()->singleton(SerialNumberFormatter::class, fn () => $mockSerialNumberFormatter);

    $mockInvoiceParam = Mockery::mock(Invoice::class);

    $mockCreatedInvoice = Mockery::mock(Invoice::class);
    $mockCreatedInvoice->id = $newInvoiceId;
    $mockCreatedInvoice->shouldReceive('save')->once();
    $mockCreatedInvoice->shouldNotReceive('items'); // No items to process
    $mockCreatedInvoice->shouldNotReceive('taxes'); // No global taxes to process

    Invoice::shouldReceive('create')
        ->once()
        ->andReturn($mockCreatedInvoice)
        ->with(Mockery::on(function ($attributes) use ($companyId, $customerId, $userId) {
            expect($attributes['company_id'])->toBe($companyId); // Expect null company_id for invoice
            expect($attributes['customer_id'])->toBe($customerId);
            expect($attributes['creator_id'])->toBe($userId);
            expect($attributes['due_date'])->toBeNull(); // No due date set automatically
            expect($attributes['invoice_number'])->toBe('INV-004');
            expect($attributes['reference_number'])->toBe('REF-004');
            return true;
        }));

    Hashids::shouldReceive('connection')->with(Invoice::class)->andReturnSelf()->once();
    Hashids::shouldReceive('encode')->with($newInvoiceId)->andReturn('encoded_hash_4')->once();
    $mockCreatedInvoice->shouldReceive('setAttribute')->with('unique_hash', 'encoded_hash_4')->once()->andReturnSelf();

    Invoice::shouldReceive('find')->with($newInvoiceId)->andReturn($mockCreatedInvoice)->once();

    $controller = new ConvertEstimateController();
    $controller = Mockery::mock($controller)->makePartial();
    $controller->shouldReceive('authorize')->with('create', Invoice::class)->once();

    // Act
    $response = $controller->__invoke($request, $mockEstimate, $mockInvoiceParam);

    // Assert
    expect($response)->toBeInstanceOf(InvoiceResource::class);
    expect($response->resource)->toBe($mockCreatedInvoice);
});

 
