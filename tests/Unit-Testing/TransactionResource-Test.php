<?php

use Illuminate\Http\Request;
use Mockery\MockInterface;
use Crater\Http\Resources\TransactionResource;
use Crater\Http\Resources\InvoiceResource;
use Crater\Http\Resources\CompanyResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

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

afterEach(function () {
    Mockery::close();
});

test('toArray correctly transforms transaction without relationships', function () {
    /** @var MockInterface|Transaction $transactionMock */
    $transactionMock = Mockery::mock(Transaction::class);
    $transactionMock->id = 1;
    $transactionMock->transaction_id = 'TRX-001';
    $transactionMock->type = 'income';
    $transactionMock->status = 'paid';
    $transactionMock->transaction_date = '2023-01-01';
    $transactionMock->invoice_id = null;

    // Mock relationship methods to indicate non-existence
    $invoiceRelationMock = Mockery::mock(Relation::class);
    $invoiceRelationMock->shouldReceive('exists')->once()->andReturn(false);
    $transactionMock->shouldReceive('invoice')->once()->andReturn($invoiceRelationMock);

    $companyRelationMock = Mockery::mock(Relation::class);
    $companyRelationMock->shouldReceive('exists')->once()->andReturn(false);
    $transactionMock->shouldReceive('company')->once()->andReturn($companyRelationMock);

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
    $transactionMock->id = 2;
    $transactionMock->transaction_id = 'TRX-002';
    $transactionMock->type = 'expense';
    $transactionMock->status = 'pending';
    $transactionMock->transaction_date = '2023-02-01';
    $transactionMock->invoice_id = 101;

    // Mock related Invoice and Company models that the resource will wrap
    /** @var MockInterface|Invoice $invoiceModelMock */
    $invoiceModelMock = Mockery::mock(Invoice::class);
    $transactionMock->invoice = $invoiceModelMock; // Direct property access for the actual model instance

    /** @var MockInterface|Company $companyModelMock */
    $companyModelMock = Mockery::mock(Company::class);
    $transactionMock->company = $companyModelMock; // Direct property access for the actual model instance

    // Mock relationship methods to indicate existence
    $invoiceRelationMock = Mockery::mock(Relation::class);
    $invoiceRelationMock->shouldReceive('exists')->once()->andReturn(true);
    $transactionMock->shouldReceive('invoice')->once()->andReturn($invoiceRelationMock);

    $companyRelationMock = Mockery::mock(Relation::class);
    $companyRelationMock->shouldReceive('exists')->once()->andReturn(true);
    $transactionMock->shouldReceive('company')->once()->andReturn($companyRelationMock);

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
    $transactionMock->id = 3;
    $transactionMock->transaction_id = 'TRX-003';
    $transactionMock->type = 'transfer';
    $transactionMock->status = 'reconciled';
    $transactionMock->transaction_date = '2023-03-01';
    $transactionMock->invoice_id = 102;

    /** @var MockInterface|Invoice $invoiceModelMock */
    $invoiceModelMock = Mockery::mock(Invoice::class);
    $transactionMock->invoice = $invoiceModelMock;

    // Invoice relationship exists
    $invoiceRelationMock = Mockery::mock(Relation::class);
    $invoiceRelationMock->shouldReceive('exists')->once()->andReturn(true);
    $transactionMock->shouldReceive('invoice')->once()->andReturn($invoiceRelationMock);

    // Company relationship does not exist
    $companyRelationMock = Mockery::mock(Relation::class);
    $companyRelationMock->shouldReceive('exists')->once()->andReturn(false);
    $transactionMock->shouldReceive('company')->once()->andReturn($companyRelationMock);

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
    $transactionMock->id = 4;
    $transactionMock->transaction_id = 'TRX-004';
    $transactionMock->type = 'deposit';
    $transactionMock->status = 'cleared';
    $transactionMock->transaction_date = '2023-04-01';
    $transactionMock->invoice_id = null;

    /** @var MockInterface|Company $companyModelMock */
    $companyModelMock = Mockery::mock(Company::class);
    $transactionMock->company = $companyModelMock;

    // Invoice relationship does not exist
    $invoiceRelationMock = Mockery::mock(Relation::class);
    $invoiceRelationMock->shouldReceive('exists')->once()->andReturn(false);
    $transactionMock->shouldReceive('invoice')->once()->andReturn($invoiceRelationMock);

    // Company relationship exists
    $companyRelationMock = Mockery::mock(Relation::class);
    $companyRelationMock->shouldReceive('exists')->once()->andReturn(true);
    $transactionMock->shouldReceive('company')->once()->andReturn($companyRelationMock);

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
    $transactionMock->id = null;
    $transactionMock->transaction_id = null;
    $transactionMock->type = null;
    $transactionMock->status = null;
    $transactionMock->transaction_date = null;
    $transactionMock->invoice_id = null;

    // Mock relationship methods to indicate non-existence
    $invoiceRelationMock = Mockery::mock(Relation::class);
    $invoiceRelationMock->shouldReceive('exists')->once()->andReturn(false);
    $transactionMock->shouldReceive('invoice')->once()->andReturn($invoiceRelationMock);

    $companyRelationMock = Mockery::mock(Relation::class);
    $companyRelationMock->shouldReceive('exists')->once()->andReturn(false);
    $transactionMock->shouldReceive('company')->once()->andReturn($companyRelationMock);

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
