<?php

use Carbon\Carbon;
use Crater\Http\Controllers\V1\Admin\Invoice\CloneInvoiceController;
use Crater\Http\Resources\InvoiceResource;
use Crater\Models\CompanySetting;
use Crater\Models\Invoice;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;

// Mock the Carbon facade for consistent date results across tests
beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2023, 1, 15, 12, 0, 0));
});

// Clean up mocks after each test
afterEach(function () {
    Carbon::setTestNow(null);
    Mockery::close();
});

test('it clones an invoice successfully with automatic due date and all relations', function () {
    // Arrange
    $companyId = 1;
    $customerId = 10;
    $invoiceId = 123;
    $newInvoiceId = 456;
    $exchangeRate = 1.0;
    $dueDateDays = 30;

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->atLeast()->times(5);

    // Mock Original Invoice ($invoice)
    $originalInvoice = Mockery::mock(Invoice::class)->makePartial();
    $originalInvoice->id = $invoiceId;
    $originalInvoice->company_id = $companyId;
    $originalInvoice->customer_id = $customerId;
    $originalInvoice->reference_number = 'REF-123';
    $originalInvoice->template_name = 'default';
    $originalInvoice->status = Invoice::STATUS_SENT;
    $originalInvoice->paid_status = Invoice::STATUS_PAID;
    $originalInvoice->sub_total = 100.0;
    $originalInvoice->discount = 10.0;
    $originalInvoice->discount_type = 'percentage';
    $originalInvoice->discount_val = 10.0;
    $originalInvoice->total = 90.0;
    $originalInvoice->due_amount = 90.0;
    $originalInvoice->tax_per_item = true;
    $originalInvoice->discount_per_item = false;
    $originalInvoice->tax = 5.0;
    $originalInvoice->notes = 'Original notes';
    $originalInvoice->exchange_rate = $exchangeRate;
    $originalInvoice->currency_id = 1;
    $originalInvoice->sales_tax_type = 'inclusive';
    $originalInvoice->sales_tax_address_type = 'billing';

    // Mock original invoice items with taxes
    $item1 = [
        'id' => 1, 'name' => 'Item A', 'price' => 50, 'quantity' => 1, 'discount_val' => 0, 'tax' => 2.5, 'total' => 52.5,
        'taxes' => [
            ['id' => 101, 'name' => 'Tax A', 'amount' => 2.5, 'pivot' => ['taxable_id' => 1, 'taxable_type' => 'Crater\Models\InvoiceItem', 'tax_id' => 101]],
        ],
    ];
    $item2 = [
        'id' => 2, 'name' => 'Item B', 'price' => 50, 'quantity' => 1, 'discount_val' => 0, 'tax' => 2.5, 'total' => 52.5,
        'taxes' => [
            ['id' => 102, 'name' => 'Tax B', 'amount' => 2.5, 'pivot' => ['taxable_id' => 2, 'taxable_type' => 'Crater\Models\InvoiceItem', 'tax_id' => 102]],
            ['id' => 103, 'name' => 'Tax C Zero', 'amount' => 0, 'pivot' => ['taxable_id' => 2, 'taxable_type' => 'Crater\Models\InvoiceItem', 'tax_id' => 103]], // Tax with zero amount, should not be created
        ],
    ];
    $originalInvoice->items = collect([
        (object) $item1, (object) $item2,
    ]);

    // Mock original invoice top-level taxes
    $topLevelTax1 = ['id' => 201, 'name' => 'Global Tax 1', 'amount' => 5.0];
    $originalInvoice->taxes = collect([
        (object) $topLevelTax1
    ]);

    // Mock original invoice custom fields
    $field1 = (object) ['custom_field_id' => 1, 'defaultAnswer' => 'Value 1'];
    $field2 = (object) ['custom_field_id' => 2, 'defaultAnswer' => 'Value 2'];
    $mockFieldsRelation = Mockery::mock(HasMany::class);
    $mockFieldsRelation->shouldReceive('exists')->andReturn(true);
    $originalInvoice->shouldReceive('fields')->andReturn($mockFieldsRelation);
    $originalInvoice->fields = collect([$field1, $field2]);

    $originalInvoice->shouldReceive('load')->with('items.taxes')->andReturnSelf();

    // Mock SerialNumberFormatter
    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->shouldReceive('setModel')->with($originalInvoice)->andReturnSelf();
    $serialFormatter->shouldReceive('setCompany')->with($companyId)->andReturnSelf();
    $serialFormatter->shouldReceive('setCustomer')->with($customerId)->andReturnSelf();
    $serialFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    $serialFormatter->shouldReceive('getNextNumber')->andReturn('INV-001');
    $serialFormatter->nextSequenceNumber = 1;
    $serialFormatter->nextCustomerSequenceNumber = 1;

    // Mock CompanySetting to enable automatic due date and provide days
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('invoice_set_due_date_automatically', $companyId)
        ->andReturn('YES')
        ->once()
        ->shouldReceive('getSetting')
        ->with('invoice_due_date_days', $companyId)
        ->andReturn($dueDateDays)
        ->once();

    // Mock New Invoice created by the controller
    $newInvoice = Mockery::mock(Invoice::class)->makePartial();
    $newInvoice->id = $newInvoiceId;
    $newInvoice->unique_hash = null;
    $newInvoice->shouldReceive('save')->once();
    $newInvoice->shouldReceive('addCustomFields')
        ->once()
        ->with([
            ['id' => 1, 'value' => 'Value 1'],
            ['id' => 2, 'value' => 'Value 2'],
        ]);

    // Mock relations on the new invoice
    $mockNewInvoiceItemsRelation = Mockery::mock(HasMany::class);
    $mockNewInvoiceItemsRelation->shouldReceive('create')
        ->with(
            Mockery::subset(array_merge($item1, [
                'company_id' => $companyId,
                'name' => 'Item A',
                'exchange_rate' => $exchangeRate,
                'base_price' => $item1['price'] * $exchangeRate,
                'base_discount_val' => $item1['discount_val'] * $exchangeRate,
                'base_tax' => $item1['tax'] * $exchangeRate,
                'base_total' => $item1['total'] * $exchangeRate,
                'taxes' => null, // taxes array is removed before creating item
            ]))
        )
        ->andReturn(Mockery::mock(stdClass::class)
            ->shouldReceive('taxes')
            ->andReturn(Mockery::mock(HasMany::class)
                ->shouldReceive('create')
                ->once()
                ->with(Mockery::subset(['id' => 101, 'name' => 'Tax A', 'amount' => 2.5, 'company_id' => $companyId]))
                ->getMock()
            )->getMock()
        );

    $mockNewInvoiceItemsRelation->shouldReceive('create')
        ->with(
            Mockery::subset(array_merge($item2, [
                'company_id' => $companyId,
                'name' => 'Item B',
                'exchange_rate' => $exchangeRate,
                'base_price' => $item2['price'] * $exchangeRate,
                'base_discount_val' => $item2['discount_val'] * $exchangeRate,
                'base_tax' => $item2['tax'] * $exchangeRate,
                'base_total' => $item2['total'] * $exchangeRate,
                'taxes' => null, // taxes array is removed before creating item
            ]))
        )
        ->andReturn(Mockery::mock(stdClass::class)
            ->shouldReceive('taxes')
            ->andReturn(Mockery::mock(HasMany::class)
                ->shouldReceive('create')
                ->once()
                ->with(Mockery::subset(['id' => 102, 'name' => 'Tax B', 'amount' => 2.5, 'company_id' => $companyId]))
                ->shouldNotReceive('create') // Tax with zero amount should not be created
                ->with(Mockery::subset(['id' => 103]))
                ->getMock()
            )->getMock()
        );
    $newInvoice->shouldReceive('items')->andReturn($mockNewInvoiceItemsRelation);

    $mockNewInvoiceTaxesRelation = Mockery::mock(HasMany::class);
    $mockNewInvoiceTaxesRelation->shouldReceive('create')
        ->once()
        ->with(Mockery::subset(array_merge($topLevelTax1, ['company_id' => $companyId])));
    $newInvoice->shouldReceive('taxes')->andReturn($mockNewInvoiceTaxesRelation);


    // Mock static Invoice::create method
    Mockery::mock('alias:' . Invoice::class)
        ->shouldReceive('create')
        ->with(Mockery::subset([
            'invoice_date' => '2023-01-15',
            'due_date' => Carbon::now()->addDays($dueDateDays)->format('Y-m-d'),
            'invoice_number' => 'INV-001',
            'sequence_number' => 1,
            'customer_sequence_number' => 1,
            'reference_number' => $originalInvoice->reference_number,
            'customer_id' => $originalInvoice->customer_id,
            'company_id' => $companyId,
            'template_name' => $originalInvoice->template_name,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => $originalInvoice->sub_total,
            'discount' => $originalInvoice->discount,
            'discount_type' => $originalInvoice->discount_type,
            'discount_val' => $originalInvoice->discount_val,
            'total' => $originalInvoice->total,
            'due_amount' => $originalInvoice->total,
            'tax_per_item' => $originalInvoice->tax_per_item,
            'discount_per_item' => $originalInvoice->discount_per_item,
            'tax' => $originalInvoice->tax,
            'notes' => $originalInvoice->notes,
            'exchange_rate' => $exchangeRate,
            'base_total' => $originalInvoice->total * $exchangeRate,
            'base_discount_val' => $originalInvoice->discount_val * $exchangeRate,
            'base_sub_total' => $originalInvoice->sub_total * $exchangeRate,
            'base_tax' => $originalInvoice->tax * $exchangeRate,
            'base_due_amount' => $originalInvoice->total * $exchangeRate,
            'currency_id' => $originalInvoice->currency_id,
            'sales_tax_type' => $originalInvoice->sales_tax_type,
            'sales_tax_address_type' => $originalInvoice->sales_tax_address_type,
        ]))
        ->andReturn($newInvoice)
        ->once();

    // Mock Hashids facade
    $mockHashidsConnection = Mockery::mock(stdClass::class);
    $mockHashidsConnection->shouldReceive('encode')->with($newInvoiceId)->andReturn('encoded_hash');
    Hashids::shouldReceive('connection')->with(Invoice::class)->andReturn($mockHashidsConnection);

    // Mock InvoiceResource constructor
    Mockery::mock('overload:' . InvoiceResource::class)
        ->shouldReceive('__construct')
        ->with($newInvoice)
        ->once();

    // Instantiate controller and mock `authorize` method
    $controller = Mockery::mock(CloneInvoiceController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('create', Invoice::class)->once();
    $controller->shouldAllowMockingProtectedMethods(); // Allows mocking protected methods if needed
    $controller->shouldReceive('__construct')->andReturnNull(); // Bypass parent constructor

    // Act
    $response = $controller($request, $originalInvoice);

    // Assert
    expect($response)->toBeInstanceOf(InvoiceResource::class);
});

test('it clones an invoice successfully without automatic due date, items, top-level taxes, or custom fields', function () {
    // Arrange
    $companyId = 1;
    $customerId = 10;
    $invoiceId = 123;
    $newInvoiceId = 456;
    $exchangeRate = 1.0;

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->atLeast()->times(3);

    // Mock Original Invoice ($invoice)
    $originalInvoice = Mockery::mock(Invoice::class)->makePartial();
    $originalInvoice->id = $invoiceId;
    $originalInvoice->company_id = $companyId;
    $originalInvoice->customer_id = $customerId;
    $originalInvoice->reference_number = 'REF-123';
    $originalInvoice->template_name = 'default';
    $originalInvoice->status = Invoice::STATUS_SENT;
    $originalInvoice->paid_status = Invoice::STATUS_PAID;
    $originalInvoice->sub_total = 100.0;
    $originalInvoice->discount = 10.0;
    $originalInvoice->discount_type = 'percentage';
    $originalInvoice->discount_val = 10.0;
    $originalInvoice->total = 90.0;
    $originalInvoice->due_amount = 90.0;
    $originalInvoice->tax_per_item = true;
    $originalInvoice->discount_per_item = false;
    $originalInvoice->tax = 5.0;
    $originalInvoice->notes = 'Original notes';
    $originalInvoice->exchange_rate = $exchangeRate;
    $originalInvoice->currency_id = 1;
    $originalInvoice->sales_tax_type = 'inclusive';
    $originalInvoice->sales_tax_address_type = 'billing';
    $originalInvoice->items = collect([]); // No items
    $originalInvoice->taxes = collect([]); // No top-level taxes
    $mockFieldsRelation = Mockery::mock(HasMany::class);
    $mockFieldsRelation->shouldReceive('exists')->andReturn(false); // No custom fields
    $originalInvoice->shouldReceive('fields')->andReturn($mockFieldsRelation);

    $originalInvoice->shouldReceive('load')->with('items.taxes')->andReturnSelf();

    // Mock SerialNumberFormatter
    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->shouldReceive('setModel')->with($originalInvoice)->andReturnSelf();
    $serialFormatter->shouldReceive('setCompany')->with($companyId)->andReturnSelf();
    $serialFormatter->shouldReceive('setCustomer')->with($customerId)->andReturnSelf();
    $serialFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    $serialFormatter->shouldReceive('getNextNumber')->andReturn('INV-001');
    $serialFormatter->nextSequenceNumber = 1;
    $serialFormatter->nextCustomerSequenceNumber = 1;

    // Mock CompanySetting to disable automatic due date
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('invoice_set_due_date_automatically', $companyId)
        ->andReturn('NO')
        ->once();

    // Mock New Invoice created by the controller
    $newInvoice = Mockery::mock(Invoice::class)->makePartial();
    $newInvoice->id = $newInvoiceId;
    $newInvoice->unique_hash = null;
    $newInvoice->shouldReceive('save')->once();
    $newInvoice->shouldNotReceive('addCustomFields'); // Should not be called
    $newInvoice->shouldReceive('items')->andReturn(Mockery::mock(HasMany::class)->shouldNotReceive('create')->getMock()); // No item creations
    $newInvoice->shouldReceive('taxes')->andReturn(Mockery::mock(HasMany::class)->shouldNotReceive('create')->getMock()); // No top-level tax creations

    // Mock static Invoice::create, asserting due_date is null
    Mockery::mock('alias:' . Invoice::class)
        ->shouldReceive('create')
        ->with(Mockery::subset([
            'invoice_date' => '2023-01-15',
            'due_date' => null, // Assert null due date
            'invoice_number' => 'INV-001',
            'sequence_number' => 1,
            'customer_sequence_number' => 1,
        ]))
        ->andReturn($newInvoice)
        ->once();

    // Mock Hashids facade
    $mockHashidsConnection = Mockery::mock(stdClass::class);
    $mockHashidsConnection->shouldReceive('encode')->with($newInvoiceId)->andReturn('encoded_hash');
    Hashids::shouldReceive('connection')->with(Invoice::class)->andReturn($mockHashidsConnection);

    // Mock InvoiceResource constructor
    Mockery::mock('overload:' . InvoiceResource::class)
        ->shouldReceive('__construct')
        ->with($newInvoice)
        ->once();

    // Instantiate controller and mock `authorize` method
    $controller = Mockery::mock(CloneInvoiceController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('create', Invoice::class)->once();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('__construct')->andReturnNull();

    // Act
    $response = $controller($request, $originalInvoice);

    // Assert
    expect($response)->toBeInstanceOf(InvoiceResource::class);
});

test('it clones an invoice with items having empty taxes array', function () {
    // Arrange
    $companyId = 1;
    $customerId = 10;
    $invoiceId = 123;
    $newInvoiceId = 456;
    $exchangeRate = 1.0;
    $dueDateDays = 30;

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->atLeast()->times(5);

    // Mock Original Invoice ($invoice)
    $originalInvoice = Mockery::mock(Invoice::class)->makePartial();
    $originalInvoice->id = $invoiceId;
    $originalInvoice->company_id = $companyId;
    $originalInvoice->customer_id = $customerId;
    $originalInvoice->reference_number = 'REF-123';
    $originalInvoice->template_name = 'default';
    $originalInvoice->status = Invoice::STATUS_SENT;
    $originalInvoice->paid_status = Invoice::STATUS_PAID;
    $originalInvoice->sub_total = 100.0;
    $originalInvoice->discount = 10.0;
    $originalInvoice->discount_type = 'percentage';
    $originalInvoice->discount_val = 10.0;
    $originalInvoice->total = 90.0;
    $originalInvoice->due_amount = 90.0;
    $originalInvoice->tax_per_item = true;
    $originalInvoice->discount_per_item = false;
    $originalInvoice->tax = 5.0;
    $originalInvoice->notes = 'Original notes';
    $originalInvoice->exchange_rate = $exchangeRate;
    $originalInvoice->currency_id = 1;
    $originalInvoice->sales_tax_type = 'inclusive';
    $originalInvoice->sales_tax_address_type = 'billing';

    // Mock original invoice items with empty taxes
    $item1 = [
        'id' => 1, 'name' => 'Item A', 'price' => 50, 'quantity' => 1, 'discount_val' => 0, 'tax' => 2.5, 'total' => 52.5,
        'taxes' => [], // Empty taxes array
    ];
    $originalInvoice->items = collect([
        (object) $item1
    ]);

    $originalInvoice->taxes = collect([]); // No top-level taxes
    $mockFieldsRelation = Mockery::mock(HasMany::class);
    $mockFieldsRelation->shouldReceive('exists')->andReturn(false); // No custom fields
    $originalInvoice->shouldReceive('fields')->andReturn($mockFieldsRelation);
    $originalInvoice->shouldReceive('load')->with('items.taxes')->andReturnSelf();

    // Mock SerialNumberFormatter
    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->shouldReceive('setModel')->with($originalInvoice)->andReturnSelf();
    $serialFormatter->shouldReceive('setCompany')->with($companyId)->andReturnSelf();
    $serialFormatter->shouldReceive('setCustomer')->with($customerId)->andReturnSelf();
    $serialFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    $serialFormatter->shouldReceive('getNextNumber')->andReturn('INV-001');
    $serialFormatter->nextSequenceNumber = 1;
    $serialFormatter->nextCustomerSequenceNumber = 1;

    // Mock CompanySetting
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('invoice_set_due_date_automatically', $companyId)
        ->andReturn('YES')
        ->once()
        ->shouldReceive('getSetting')
        ->with('invoice_due_date_days', $companyId)
        ->andReturn($dueDateDays)
        ->once();

    // Mock New Invoice created by the controller
    $newInvoice = Mockery::mock(Invoice::class)->makePartial();
    $newInvoice->id = $newInvoiceId;
    $newInvoice->unique_hash = null;
    $newInvoice->shouldReceive('save')->once();
    $newInvoice->shouldNotReceive('addCustomFields');
    $newInvoice->shouldReceive('taxes')->andReturn(Mockery::mock(HasMany::class)->shouldNotReceive('create')->getMock());

    // Mock relations on the new invoice
    $mockNewInvoiceItemsRelation = Mockery::mock(HasMany::class);
    $mockNewInvoiceItemsRelation->shouldReceive('create')
        ->with(
            Mockery::subset(array_merge($item1, [
                'company_id' => $companyId,
                'name' => 'Item A',
                'exchange_rate' => $exchangeRate,
                'base_price' => $item1['price'] * $exchangeRate,
                'base_discount_val' => $item1['discount_val'] * $exchangeRate,
                'base_tax' => $item1['tax'] * $exchangeRate,
                'base_total' => $item1['total'] * $exchangeRate,
                'taxes' => null,
            ]))
        )
        ->andReturn(Mockery::mock(stdClass::class)
            ->shouldReceive('taxes')
            ->andReturn(Mockery::mock(HasMany::class)
                ->shouldNotReceive('create') // Ensure no tax is created for empty array
                ->getMock()
            )->getMock()
        );
    $newInvoice->shouldReceive('items')->andReturn($mockNewInvoiceItemsRelation);

    // Mock static Invoice::create
    Mockery::mock('alias:' . Invoice::class)
        ->shouldReceive('create')
        ->with(Mockery::subset([
            'invoice_date' => '2023-01-15',
            'due_date' => Carbon::now()->addDays($dueDateDays)->format('Y-m-d'),
        ]))
        ->andReturn($newInvoice)
        ->once();

    // Mock Hashids
    $mockHashidsConnection = Mockery::mock(stdClass::class);
    $mockHashidsConnection->shouldReceive('encode')->with($newInvoiceId)->andReturn('encoded_hash');
    Hashids::shouldReceive('connection')->with(Invoice::class)->andReturn($mockHashidsConnection);

    // Mock InvoiceResource
    Mockery::mock('overload:' . InvoiceResource::class)
        ->shouldReceive('__construct')
        ->with($newInvoice)
        ->once();

    // Instantiate controller and mock `authorize`
    $controller = Mockery::mock(CloneInvoiceController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('create', Invoice::class)->once();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('__construct')->andReturnNull();

    // Act
    $response = $controller($request, $originalInvoice);

    // Assert
    expect($response)->toBeInstanceOf(InvoiceResource::class);
});

test('it clones an invoice with items having null taxes', function () {
    // Arrange
    $companyId = 1;
    $customerId = 10;
    $invoiceId = 123;
    $newInvoiceId = 456;
    $exchangeRate = 1.0;
    $dueDateDays = 30;

    // Mock Request
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('company')->andReturn($companyId)->atLeast()->times(5);

    // Mock Original Invoice ($invoice)
    $originalInvoice = Mockery::mock(Invoice::class)->makePartial();
    $originalInvoice->id = $invoiceId;
    $originalInvoice->company_id = $companyId;
    $originalInvoice->customer_id = $customerId;
    $originalInvoice->reference_number = 'REF-123';
    $originalInvoice->template_name = 'default';
    $originalInvoice->status = Invoice::STATUS_SENT;
    $originalInvoice->paid_status = Invoice::STATUS_PAID;
    $originalInvoice->sub_total = 100.0;
    $originalInvoice->discount = 10.0;
    $originalInvoice->discount_type = 'percentage';
    $originalInvoice->discount_val = 10.0;
    $originalInvoice->total = 90.0;
    $originalInvoice->due_amount = 90.0;
    $originalInvoice->tax_per_item = true;
    $originalInvoice->discount_per_item = false;
    $originalInvoice->tax = 5.0;
    $originalInvoice->notes = 'Original notes';
    $originalInvoice->exchange_rate = $exchangeRate;
    $originalInvoice->currency_id = 1;
    $originalInvoice->sales_tax_type = 'inclusive';
    $originalInvoice->sales_tax_address_type = 'billing';

    // Mock original invoice items with null taxes
    $item1 = [
        'id' => 1, 'name' => 'Item A', 'price' => 50, 'quantity' => 1, 'discount_val' => 0, 'tax' => 2.5, 'total' => 52.5,
        'taxes' => null, // Null taxes
    ];
    $originalInvoice->items = collect([
        (object) $item1
    ]);

    $originalInvoice->taxes = collect([]); // No top-level taxes
    $mockFieldsRelation = Mockery::mock(HasMany::class);
    $mockFieldsRelation->shouldReceive('exists')->andReturn(false); // No custom fields
    $originalInvoice->shouldReceive('fields')->andReturn($mockFieldsRelation);
    $originalInvoice->shouldReceive('load')->with('items.taxes')->andReturnSelf();

    // Mock SerialNumberFormatter
    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->shouldReceive('setModel')->with($originalInvoice)->andReturnSelf();
    $serialFormatter->shouldReceive('setCompany')->with($companyId)->andReturnSelf();
    $serialFormatter->shouldReceive('setCustomer')->with($customerId)->andReturnSelf();
    $serialFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    $serialFormatter->shouldReceive('getNextNumber')->andReturn('INV-001');
    $serialFormatter->nextSequenceNumber = 1;
    $serialFormatter->nextCustomerSequenceNumber = 1;

    // Mock CompanySetting
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('invoice_set_due_date_automatically', $companyId)
        ->andReturn('YES')
        ->once()
        ->shouldReceive('getSetting')
        ->with('invoice_due_date_days', $companyId)
        ->andReturn($dueDateDays)
        ->once();

    // Mock New Invoice created by the controller
    $newInvoice = Mockery::mock(Invoice::class)->makePartial();
    $newInvoice->id = $newInvoiceId;
    $newInvoice->unique_hash = null;
    $newInvoice->shouldReceive('save')->once();
    $newInvoice->shouldNotReceive('addCustomFields');
    $newInvoice->shouldReceive('taxes')->andReturn(Mockery::mock(HasMany::class)->shouldNotReceive('create')->getMock());

    // Mock relations on the new invoice
    $mockNewInvoiceItemsRelation = Mockery::mock(HasMany::class);
    $mockNewInvoiceItemsRelation->shouldReceive('create')
        ->with(
            Mockery::subset(array_merge($item1, [
                'company_id' => $companyId,
                'name' => 'Item A',
                'exchange_rate' => $exchangeRate,
                'base_price' => $item1['price'] * $exchangeRate,
                'base_discount_val' => $item1['discount_val'] * $exchangeRate,
                'base_tax' => $item1['tax'] * $exchangeRate,
                'base_total' => $item1['total'] * $exchangeRate,
                'taxes' => null,
            ]))
        )
        ->andReturn(Mockery::mock(stdClass::class)
            ->shouldReceive('taxes')
            ->andReturn(Mockery::mock(HasMany::class)
                ->shouldNotReceive('create') // Ensure no tax is created for null taxes
                ->getMock()
            )->getMock()
        );
    $newInvoice->shouldReceive('items')->andReturn($mockNewInvoiceItemsRelation);

    // Mock static Invoice::create
    Mockery::mock('alias:' . Invoice::class)
        ->shouldReceive('create')
        ->with(Mockery::subset([
            'invoice_date' => '2023-01-15',
            'due_date' => Carbon::now()->addDays($dueDateDays)->format('Y-m-d'),
        ]))
        ->andReturn($newInvoice)
        ->once();

    // Mock Hashids
    $mockHashidsConnection = Mockery::mock(stdClass::class);
    $mockHashidsConnection->shouldReceive('encode')->with($newInvoiceId)->andReturn('encoded_hash');
    Hashids::shouldReceive('connection')->with(Invoice::class)->andReturn($mockHashidsConnection);

    // Mock InvoiceResource
    Mockery::mock('overload:' . InvoiceResource::class)
        ->shouldReceive('__construct')
        ->with($newInvoice)
        ->once();

    // Instantiate controller and mock `authorize`
    $controller = Mockery::mock(CloneInvoiceController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('create', Invoice::class)->once();
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('__construct')->andReturnNull();

    // Act
    $response = $controller($request, $originalInvoice);

    // Assert
    expect($response)->toBeInstanceOf(InvoiceResource::class);
});

test('it throws authorization exception when user cannot create invoice', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $originalInvoice = Mockery::mock(Invoice::class);

    $controller = Mockery::mock(CloneInvoiceController::class)->makePartial();
    $controller->shouldReceive('authorize')->with('create', Invoice::class)->andThrow(new AuthorizationException());
    $controller->shouldAllowMockingProtectedMethods();
    $controller->shouldReceive('__construct')->andReturnNull();

    // Act & Assert
    $this->expectException(AuthorizationException::class);
    $controller($request, $originalInvoice);
});
