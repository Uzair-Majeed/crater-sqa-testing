<?php

use Crater\Models\Company;
use Crater\Models\Expense;
use Crater\Models\Payment;
use Crater\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
uses(\Mockery::class);

beforeEach(function () {
    Mockery::close();
});

afterAll(function () {
    Mockery::close();
});

test('it sets settings attribute by json encoding the value', function () {
    $model = new PaymentMethod();
    $settings = ['key' => 'value', 'array' => [1, 2]];

    $model->setSettingsAttribute($settings);

    expect($model->getAttributes()['settings'])->toBeJson();
    expect(json_decode($model->getAttributes()['settings'], true))->toEqual($settings);

    $model->setSettingsAttribute(null);
    expect($model->getAttributes()['settings'])->toBeJson();
    expect(json_decode($model->getAttributes()['settings'], true))->toBeNull();

    $model->setSettingsAttribute([]);
    expect($model->getAttributes()['settings'])->toBeJson();
    expect(json_decode($model->getAttributes()['settings'], true))->toEqual([]);
});

test('payments relationship returns hasMany relation', function () {
    $paymentMethod = new PaymentMethod();
    $relation = $paymentMethod->payments();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Payment::class);
    expect($relation->getForeignKeyName())->toBe('payment_method_id');
});

test('expenses relationship returns hasMany relation', function () {
    $paymentMethod = new PaymentMethod();
    $relation = $paymentMethod->expenses();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Expense::class);
    expect($relation->getForeignKeyName())->toBe('payment_method_id');
});

test('company relationship returns belongsTo relation', function () {
    $paymentMethod = new PaymentMethod();
    $relation = $paymentMethod->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
    expect($relation->getForeignKeyName())->toBe('company_id');
});

test('scopeWhereCompanyId applies where clause for company id', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $companyId = 123;

    $mockQuery->shouldReceive('where')
        ->once()
        ->with('company_id', $companyId)
        ->andReturnSelf();

    $paymentMethod = new PaymentMethod();
    $paymentMethod->scopeWhereCompanyId($mockQuery, $companyId);
});

test('scopeWhereCompany applies where clause based on request header company', function () {
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn(456);

    app()->instance('request', $mockRequest);

    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('where')
        ->once()
        ->with('company_id', 456)
        ->andReturnSelf();

    $paymentMethod = new PaymentMethod();
    $paymentMethod->scopeWhereCompany($mockQuery);

    app()->offsetUnset('request');
});

test('scopeWherePaymentMethod applies orWhere clause for payment method id', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $paymentId = 789;

    $mockQuery->shouldReceive('orWhere')
        ->once()
        ->with('id', $paymentId)
        ->andReturnSelf();

    $paymentMethod = new PaymentMethod();
    $paymentMethod->scopeWherePaymentMethod($mockQuery, $paymentId);
});

test('scopeWhereSearch applies where like clause for name', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $search = 'test name';

    $mockQuery->shouldReceive('where')
        ->once()
        ->with('name', 'LIKE', '%' . $search . '%')
        ->andReturnSelf();

    $paymentMethod = new PaymentMethod();
    $paymentMethod->scopeWhereSearch($mockQuery, $search);
});

test('scopeApplyFilters applies no filters when filters array is empty', function () {
    $mockQuery = Mockery::mock(Builder::class);

    $mockQuery->shouldNotReceive('wherePaymentMethod');
    $mockQuery->shouldNotReceive('whereCompany');
    $mockQuery->shouldNotReceive('whereSearch');

    $paymentMethod = new PaymentMethod();
    $paymentMethod->scopeApplyFilters($mockQuery, []);
});

test('scopeApplyFilters applies method_id filter', function () {
    $methodId = 1;
    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('wherePaymentMethod')
        ->once()
        ->with($methodId)
        ->andReturnSelf();
    $mockQuery->shouldNotReceive('whereCompany');
    $mockQuery->shouldNotReceive('whereSearch');

    $paymentMethod = new PaymentMethod();
    $paymentMethod->scopeApplyFilters($mockQuery, ['method_id' => $methodId]);
});

test('scopeApplyFilters applies company_id filter', function () {
    $companyId = 2; // Value passed to scope, though scope implementation ignores it.
    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('whereCompany')
        ->once()
        ->with($companyId)
        ->andReturnSelf();
    $mockQuery->shouldNotReceive('wherePaymentMethod');
    $mockQuery->shouldNotReceive('whereSearch');

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn(456);
    app()->instance('request', $mockRequest);

    $paymentMethod = new PaymentMethod();
    $paymentMethod->scopeApplyFilters($mockQuery, ['company_id' => $companyId]);

    app()->offsetUnset('request');
});

test('scopeApplyFilters applies search filter', function () {
    $search = 'query';
    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('whereSearch')
        ->once()
        ->with($search)
        ->andReturnSelf();
    $mockQuery->shouldNotReceive('wherePaymentMethod');
    $mockQuery->shouldNotReceive('whereCompany');

    $paymentMethod = new PaymentMethod();
    $paymentMethod->scopeApplyFilters($mockQuery, ['search' => $search]);
});

test('scopeApplyFilters applies all filters', function () {
    $methodId = 1;
    $companyId = 2; // Value passed to scope, though scope implementation ignores it.
    $search = 'query';

    $mockQuery = Mockery::mock(Builder::class);
    $mockQuery->shouldReceive('wherePaymentMethod')->once()->with($methodId)->andReturnSelf();
    $mockQuery->shouldReceive('whereCompany')->once()->with($companyId)->andReturnSelf();
    $mockQuery->shouldReceive('whereSearch')->once()->with($search)->andReturnSelf();

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('header')
        ->once()
        ->with('company')
        ->andReturn(456);
    app()->instance('request', $mockRequest);

    $paymentMethod = new PaymentMethod();
    $paymentMethod->scopeApplyFilters($mockQuery, [
        'method_id' => $methodId,
        'company_id' => $companyId,
        'search' => $search,
    ]);

    app()->offsetUnset('request');
});

test('scopePaginateData returns all records when limit is all', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $expectedCollection = collect(['item1', 'item2']);

    $mockQuery->shouldReceive('get')
        ->once()
        ->andReturn($expectedCollection);
    $mockQuery->shouldNotReceive('paginate');

    $paymentMethod = new PaymentMethod();
    $result = $paymentMethod->scopePaginateData($mockQuery, 'all');

    expect($result)->toEqual($expectedCollection);
});

test('scopePaginateData paginates records when limit is not all', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $limit = 10;
    $expectedPaginator = Mockery::mock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

    $mockQuery->shouldReceive('paginate')
        ->once()
        ->with($limit)
        ->andReturn($expectedPaginator);
    $mockQuery->shouldNotReceive('get');

    $paymentMethod = new PaymentMethod();
    $result = $paymentMethod->scopePaginateData($mockQuery, $limit);

    expect($result)->toEqual($expectedPaginator);
});

test('createPaymentMethod creates a new payment method', function () {
    $payload = ['name' => 'New Method', 'type' => PaymentMethod::TYPE_GENERAL];

    $mockRequest = Mockery::mock();
    $mockRequest->shouldReceive('getPaymentMethodPayload')
        ->once()
        ->andReturn($payload);

    $mockPaymentMethod = Mockery::mock('overload:' . PaymentMethod::class);
    $mockPaymentMethod->shouldReceive('create')
        ->once()
        ->with($payload)
        ->andReturn((new PaymentMethod())->fill($payload));

    $createdPaymentMethod = PaymentMethod::createPaymentMethod($mockRequest);

    expect($createdPaymentMethod)->toBeInstanceOf(PaymentMethod::class);
    expect($createdPaymentMethod->name)->toEqual($payload['name']);
    expect($createdPaymentMethod->type)->toEqual($payload['type']);
});

test('getSettings retrieves settings for a given id', function () {
    $id = 1;
    $settings = ['api_key' => '123', 'mode' => 'test'];

    $mockModelInstance = Mockery::mock(PaymentMethod::class);
    $mockModelInstance->settings = $settings;

    $mockPaymentMethod = Mockery::mock('overload:' . PaymentMethod::class);
    $mockPaymentMethod->shouldReceive('find')
        ->once()
        ->with($id)
        ->andReturn($mockModelInstance);

    $retrievedSettings = PaymentMethod::getSettings($id);

    expect($retrievedSettings)->toEqual($settings);
});

test('getSettings throws error if payment method not found', function () {
    $id = 999;

    $mockPaymentMethod = Mockery::mock('overload:' . PaymentMethod::class);
    $mockPaymentMethod->shouldReceive('find')
        ->once()
        ->with($id)
        ->andReturn(null);

    expect(function () use ($id) {
        PaymentMethod::getSettings($id);
    })->toThrow(\Error::class);
});
