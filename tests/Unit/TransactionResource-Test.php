<?php

use Illuminate\Http\Request;
use Mockery\MockInterface;
use Crater\Http\Resources\TransactionResource;
use Crater\Http\Resources\InvoiceResource;
use Crater\Http\Resources\CompanyResource;
use Illuminate\Database\Eloquent\Model;
// Removed: use Illuminate\Database\Eloquent\Relations\Relation; // No longer directly used in mocks for `whenLoaded` behavior.

// Dummy classes for the underlying models. These are necessary for Mockery
// to create mocks for specific types and for property hinting if strict.
// In a real Laravel application, these would typically be in `app/Models/`.
class Transaction extends Model {}
class Invoice extends Model {}
class Company extends Model {}

// Setup for Pest: Ensures Mockery is closed after each test and sets up global mocks
// for dependent resources. The `overload` feature intercepts `new Class()` calls.
beforeEach(function () {
    Mockery::mock('overload:' . InvoiceResource::class);
    Mockery::mock('overload:' . CompanyResource::class);
});


test('toArray correctly transforms transaction without relationships', function () {
    /** @var MockInterface|Transaction $transactionMock */
    $transactionMock = Mockery::mock(Transaction::class);

    // Mock basic attributes using `getAttribute` which Laravel Models use for magic __get
    $transactionMock->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $transactionMock->shouldReceive('getAttribute')->with('transaction_id')->andReturn('TRX-001');
    $transactionMock->shouldReceive('getAttribute')->with('type')->andReturn('income');
    $transactionMock->shouldReceive('getAttribute')->with('status')->andReturn('paid');
    $transactionMock->shouldReceive('getAttribute')->with('transaction_date')->andReturn('2023-01-01');
    $transactionMock->shouldReceive('getAttribute')->with('invoice_id')->andReturn(null);

    // Mock `relationLoaded` for `whenLoaded` checks and `getAttribute` for relationships to return null
    $transactionMock->shouldReceive('relationLoaded')->with('invoice')->andReturn(false);
    $transactionMock->shouldReceive('getAttribute')->with('invoice')->andReturn(null)->byDefault(); // Use byDefault as it might not be called if relationLoaded is false.

    $transactionMock->shouldReceive('relationLoaded')->with('company')->andReturn(false);
    $transactionMock->shouldReceive('getAttribute')->with('company')->andReturn(null)->byDefault();

    // Expect no calls to InvoiceResource or CompanyResource constructors
    InvoiceResource::shouldNotReceive('__construct');
    CompanyResource::shouldNotReceive('__construct');

    $resource = new TransactionResource($transactionMock);
    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toEqual([
        'id' => 1,
        'transaction_id' => 'TRX-001',
        'type' => 'income',
        'status' => 'paid',
        'transaction_date' => '2023-01-01',
        'invoice_id' => null,
    ]);

    expect($result)->not->toHaveKey('invoice');
    expect($result)->not->toHaveKey('company');
});

test('toArray correctly transforms transaction with all relationships', function () {
    /** @var MockInterface|Transaction $transactionMock */
    $transactionMock = Mockery::mock(Transaction::class);
    // Mock basic attributes
    $transactionMock->shouldReceive('getAttribute')->with('id')->andReturn(2);
    $transactionMock->shouldReceive('getAttribute')->with('transaction_id')->andReturn('TRX-002');
    $transactionMock->shouldReceive('getAttribute')->with('type')->andReturn('expense');
    $transactionMock->shouldReceive('getAttribute')->with('status')->andReturn('pending');
    $transactionMock->shouldReceive('getAttribute')->with('transaction_date')->andReturn('2023-02-01');
    $transactionMock->shouldReceive('getAttribute')->with('invoice_id')->andReturn(101);

    // Mock related Invoice and Company models that the resource will wrap
    /** @var MockInterface|Invoice $invoiceModelMock */
    $invoiceModelMock = Mockery::mock(Invoice::class);
    /** @var MockInterface|Company $companyModelMock */
    $companyModelMock = Mockery::mock(Company::class);

    // Mock relationships as loaded
    $transactionMock->shouldReceive('relationLoaded')->with('invoice')->andReturn(true);
    $transactionMock->shouldReceive('getAttribute')->with('invoice')->andReturn($invoiceModelMock); // Return the related model mock

    $transactionMock->shouldReceive('relationLoaded')->with('company')->andReturn(true);
    $transactionMock->shouldReceive('getAttribute')->with('company')->andReturn($companyModelMock); // Return the related model mock

    // Define the expected output for the nested resources
    $mockInvoiceResourceOutput = ['invoice_id_from_resource' => 101, 'number' => 'INV-001'];
    $mockCompanyResourceOutput = ['company_id_from_resource' => 201, 'name' => 'Acme Corp'];

    // Set expectations for the overloaded InvoiceResource:
    // 1. It should be constructed once with the correct model.
    // 2. Its `toArray` method (called by the parent JsonResource) should return our mock output.
    InvoiceResource::shouldReceive('__construct')->once()->with(Mockery::on(fn ($arg) => $arg === $invoiceModelMock));
    InvoiceResource::shouldReceive('toArray')->once()->andReturn($mockInvoiceResourceOutput);

    // Set expectations for the overloaded CompanyResource similarly.
    CompanyResource::shouldReceive('__construct')->once()->with(Mockery::on(fn ($arg) => $arg === $companyModelMock));
    CompanyResource::shouldReceive('toArray')->once()->andReturn($mockCompanyResourceOutput);

    $resource = new TransactionResource($transactionMock);
    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toEqual([
        'id' => 2,
        'transaction_id' => 'TRX-002',
        'type' => 'expense',
        'status' => 'pending',
        'transaction_date' => '2023-02-01',
        'invoice_id' => 101,
        'invoice' => $mockInvoiceResourceOutput,
        'company' => $mockCompanyResourceOutput,
    ]);
});

test('toArray correctly transforms transaction with only invoice relationship', function () {
    /** @var MockInterface|Transaction $transactionMock */
    $transactionMock = Mockery::mock(Transaction::class);
    $transactionMock->shouldReceive('getAttribute')->with('id')->andReturn(3);
    $transactionMock->shouldReceive('getAttribute')->with('transaction_id')->andReturn('TRX-003');
    $transactionMock->shouldReceive('getAttribute')->with('type')->andReturn('transfer');
    $transactionMock->shouldReceive('getAttribute')->with('status')->andReturn('reconciled');
    $transactionMock->shouldReceive('getAttribute')->with('transaction_date')->andReturn('2023-03-01');
    $transactionMock->shouldReceive('getAttribute')->with('invoice_id')->andReturn(102);

    /** @var MockInterface|Invoice $invoiceModelMock */
    $invoiceModelMock = Mockery::mock(Invoice::class);

    // Invoice relationship exists (loaded)
    $transactionMock->shouldReceive('relationLoaded')->with('invoice')->andReturn(true);
    $transactionMock->shouldReceive('getAttribute')->with('invoice')->andReturn($invoiceModelMock);

    // Company relationship does not exist (not loaded)
    $transactionMock->shouldReceive('relationLoaded')->with('company')->andReturn(false);
    $transactionMock->shouldReceive('getAttribute')->with('company')->andReturn(null)->byDefault(); // By default, as it won't be accessed if not loaded

    // Define the expected output for the nested InvoiceResource
    $mockInvoiceResourceOutput = ['invoice_id_from_resource' => 102, 'number' => 'INV-002'];

    // Set expectations for the overloaded InvoiceResource
    InvoiceResource::shouldReceive('__construct')->once()->with(Mockery::on(fn ($arg) => $arg === $invoiceModelMock));
    InvoiceResource::shouldReceive('toArray')->once()->andReturn($mockInvoiceResourceOutput);

    // Ensure CompanyResource is NOT constructed
    CompanyResource::shouldNotReceive('__construct');

    $resource = new TransactionResource($transactionMock);
    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toEqual([
        'id' => 3,
        'transaction_id' => 'TRX-003',
        'type' => 'transfer',
        'status' => 'reconciled',
        'transaction_date' => '2023-03-01',
        'invoice_id' => 102,
        'invoice' => $mockInvoiceResourceOutput,
    ]);

    expect($result)->not->toHaveKey('company');
});

test('toArray correctly transforms transaction with only company relationship', function () {
    /** @var MockInterface|Transaction $transactionMock */
    $transactionMock = Mockery::mock(Transaction::class);
    $transactionMock->shouldReceive('getAttribute')->with('id')->andReturn(4);
    $transactionMock->shouldReceive('getAttribute')->with('transaction_id')->andReturn('TRX-004');
    $transactionMock->shouldReceive('getAttribute')->with('type')->andReturn('deposit');
    $transactionMock->shouldReceive('getAttribute')->with('status')->andReturn('cleared');
    $transactionMock->shouldReceive('getAttribute')->with('transaction_date')->andReturn('2023-04-01');
    $transactionMock->shouldReceive('getAttribute')->with('invoice_id')->andReturn(null);

    /** @var MockInterface|Company $companyModelMock */
    $companyModelMock = Mockery::mock(Company::class);

    // Invoice relationship does not exist (not loaded)
    $transactionMock->shouldReceive('relationLoaded')->with('invoice')->andReturn(false);
    $transactionMock->shouldReceive('getAttribute')->with('invoice')->andReturn(null)->byDefault();

    // Company relationship exists (loaded)
    $transactionMock->shouldReceive('relationLoaded')->with('company')->andReturn(true);
    $transactionMock->shouldReceive('getAttribute')->with('company')->andReturn($companyModelMock);

    // Ensure InvoiceResource is NOT constructed
    InvoiceResource::shouldNotReceive('__construct');

    // Define the expected output for the nested CompanyResource
    $mockCompanyResourceOutput = ['company_id_from_resource' => 202, 'name' => 'Beta Corp'];

    // Set expectations for the overloaded CompanyResource
    CompanyResource::shouldReceive('__construct')->once()->with(Mockery::on(fn ($arg) => $arg === $companyModelMock));
    CompanyResource::shouldReceive('toArray')->once()->andReturn($mockCompanyResourceOutput);

    $resource = new TransactionResource($transactionMock);
    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toEqual([
        'id' => 4,
        'transaction_id' => 'TRX-004',
        'type' => 'deposit',
        'status' => 'cleared',
        'transaction_date' => '2023-04-01',
        'invoice_id' => null,
        'company' => $mockCompanyResourceOutput,
    ]);

    expect($result)->not->toHaveKey('invoice');
});

test('toArray handles null properties and no relationships gracefully', function () {
    /** @var MockInterface|Transaction $transactionMock */
    $transactionMock = Mockery::mock(Transaction::class);
    $transactionMock->shouldReceive('getAttribute')->with('id')->andReturn(null);
    $transactionMock->shouldReceive('getAttribute')->with('transaction_id')->andReturn(null);
    $transactionMock->shouldReceive('getAttribute')->with('type')->andReturn(null);
    $transactionMock->shouldReceive('getAttribute')->with('status')->andReturn(null);
    $transactionMock->shouldReceive('getAttribute')->with('transaction_date')->andReturn(null);
    $transactionMock->shouldReceive('getAttribute')->with('invoice_id')->andReturn(null);

    // Mock relationships as not loaded
    $transactionMock->shouldReceive('relationLoaded')->with('invoice')->andReturn(false);
    $transactionMock->shouldReceive('getAttribute')->with('invoice')->andReturn(null)->byDefault();

    $transactionMock->shouldReceive('relationLoaded')->with('company')->andReturn(false);
    $transactionMock->shouldReceive('getAttribute')->with('company')->andReturn(null)->byDefault();

    // Expect no calls to InvoiceResource or CompanyResource constructors
    InvoiceResource::shouldNotReceive('__construct');
    CompanyResource::shouldNotReceive('__construct');

    $resource = new TransactionResource($transactionMock);
    $request = Mockery::mock(Request::class);

    $result = $resource->toArray($request);

    expect($result)->toEqual([
        'id' => null,
        'transaction_id' => null,
        'type' => null,
        'status' => null,
        'transaction_date' => null,
        'invoice_id' => null,
    ]);

    expect($result)->not->toHaveKey('invoice');
    expect($result)->not->toHaveKey('company');
});

afterEach(function () {
    Mockery::close();
});