<?php

use Crater\Http\Requests\EstimatesRequest;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Crater\Models\Estimate;
use Illuminate\Validation\Rule;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;

uses(MockeryPHPUnitIntegration::class);

test('authorize method always returns true', function () {
    $request = new EstimatesRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules returns default validation rules for non-PUT requests with matching currencies', function () {
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', '1')
        ->andReturn('USD');

    m::mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with('customer-id-123')
        ->andReturn((object)['currency_id' => 'USD']);

    /** @var EstimatesRequest|m\MockInterface $request */
    $request = m::mock(EstimatesRequest::class)->makePartial();
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn('1');
    $request->shouldReceive('isMethod')
        ->with('PUT')
        ->andReturn(false);
    $request->customer_id = 'customer-id-123';

    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules['estimate_date'])->toEqual(['required'])
        ->and($rules['expiry_date'])->toEqual(['nullable'])
        ->and($rules['customer_id'])->toEqual(['required'])
        ->and($rules['estimate_number'][0])->toEqual('required')
        ->and($rules['estimate_number'][1])->toBeInstanceOf(Rule::class)
        ->and($rules['exchange_rate'])->toEqual(['nullable'])
        ->and($rules['discount'])->toEqual(['required'])
        ->and($rules['discount_val'])->toEqual(['required'])
        ->and($rules['sub_total'])->toEqual(['required'])
        ->and($rules['total'])->toEqual(['required'])
        ->and($rules['tax'])->toEqual(['required'])
        ->and($rules['template_name'])->toEqual(['required'])
        ->and($rules['items'])->toEqual(['required', 'array'])
        ->and($rules['items.*.description'])->toEqual(['nullable'])
        ->and($rules['items.*'])->toEqual(['required', 'max:255'])
        ->and($rules['items.*.name'])->toEqual(['required'])
        ->and($rules['items.*.quantity'])->toEqual(['required'])
        ->and($rules['items.*.price'])->toEqual(['required']);
});

test('rules sets exchange_rate to required when company and customer currencies differ for non-PUT requests', function () {
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', '1')
        ->andReturn('USD');

    m::mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with('customer-id-123')
        ->andReturn((object)['currency_id' => 'EUR']);

    /** @var EstimatesRequest|m\MockInterface $request */
    $request = m::mock(EstimatesRequest::class)->makePartial();
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn('1');
    $request->shouldReceive('isMethod')
        ->with('PUT')
        ->andReturn(false);
    $request->customer_id = 'customer-id-123';

    $rules = $request->rules();

    expect($rules['exchange_rate'])->toEqual(['required']);
});

test('rules maintains nullable exchange_rate when company currency setting is missing', function () {
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', '1')
        ->andReturn(null);

    m::mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with('customer-id-123')
        ->andReturn((object)['currency_id' => 'EUR']);

    /** @var EstimatesRequest|m\MockInterface $request */
    $request = m::mock(EstimatesRequest::class)->makePartial();
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn('1');
    $request->shouldReceive('isMethod')
        ->with('PUT')
        ->andReturn(false);
    $request->customer_id = 'customer-id-123';

    $rules = $request->rules();

    expect($rules['exchange_rate'])->toEqual(['nullable']);
});

test('rules maintains nullable exchange_rate when customer is not found', function () {
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', '1')
        ->andReturn('USD');

    m::mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with('customer-id-123')
        ->andReturn(null);

    /** @var EstimatesRequest|m\MockInterface $request */
    $request = m::mock(EstimatesRequest::class)->makePartial();
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn('1');
    $request->shouldReceive('isMethod')
        ->with('PUT')
        ->andReturn(false);
    $request->customer_id = 'customer-id-123';

    $rules = $request->rules();

    expect($rules['exchange_rate'])->toEqual(['nullable']);
});

test('rules sets estimate_number rule for PUT requests to ignore current estimate', function () {
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', '1')
        ->andReturn('USD');
    m::mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with('customer-id-123')
        ->andReturn((object)['currency_id' => 'USD']);

    /** @var EstimatesRequest|m\MockInterface $request */
    $request = m::mock(EstimatesRequest::class)->makePartial();
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn('1');
    $request->shouldReceive('isMethod')
        ->with('PUT')
        ->andReturn(true);
    $request->shouldReceive('route')
        ->with('estimate')
        ->andReturn((object)['id' => 'estimate-id-456']);
    $request->customer_id = 'customer-id-123';

    $rules = $request->rules();

    expect($rules['estimate_number'])->toBeArray()
        ->and($rules['estimate_number'][0])->toEqual('required')
        ->and($rules['estimate_number'][1])->toBeInstanceOf(Rule::class);
});

test('getEstimatePayload constructs payload correctly when user is present, estimate is draft, and currencies match', function () {
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', '1')
        ->andReturn('USD');
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('tax_per_item', '1')
        ->andReturn('YES');
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('discount_per_item', '1')
        ->andReturn('YES');

    $customer = (object)['currency_id' => 'USD'];
    m::mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with('customer-id-123')
        ->andReturn($customer);

    $user = (object)['id' => 99];

    /** @var EstimatesRequest|m\MockInterface $request */
    $request = m::mock(EstimatesRequest::class)->makePartial();
    $request->shouldReceive('except')
        ->with('items', 'taxes')
        ->andReturn(['extra_field' => 'extra_value']);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn('1');
    $request->shouldReceive('user')
        ->andReturn($user);
    $request->shouldReceive('has')
        ->with('estimateSend')
        ->andReturn(false);

    $request->customer_id = 'customer-id-123';
    $request->currency_id = 'USD';
    $request->exchange_rate = 1.0;
    $request->discount_val = 10.0;
    $request->sub_total = 100.0;
    $request->total = 90.0;
    $request->tax = 5.0;

    $payload = $request->getEstimatePayload();

    expect($payload)->toBeArray()
        ->and($payload['extra_field'])->toEqual('extra_value')
        ->and($payload['creator_id'])->toEqual(99)
        ->and($payload['status'])->toEqual(Estimate::STATUS_DRAFT)
        ->and($payload['company_id'])->toEqual('1')
        ->and($payload['tax_per_item'])->toEqual('YES')
        ->and($payload['discount_per_item'])->toEqual('YES')
        ->and($payload['exchange_rate'])->toEqual(1.0)
        ->and($payload['base_discount_val'])->toEqual(10.0 * 1.0)
        ->and($payload['base_sub_total'])->toEqual(100.0 * 1.0)
        ->and($payload['base_total'])->toEqual(90.0 * 1.0)
        ->and($payload['base_tax'])->toEqual(5.0 * 1.0)
        ->and($payload['currency_id'])->toEqual('USD');
});

test('getEstimatePayload constructs payload correctly when user is absent, estimate is sent, and currencies differ', function () {
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', '1')
        ->andReturn('EUR');
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('tax_per_item', '1')
        ->andReturn(null);
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('discount_per_item', '1')
        ->andReturn(null);

    $customer = (object)['currency_id' => 'USD'];
    m::mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with('customer-id-123')
        ->andReturn($customer);

    /** @var EstimatesRequest|m\MockInterface $request */
    $request = m::mock(EstimatesRequest::class)->makePartial();
    $request->shouldReceive('except')
        ->with('items', 'taxes')
        ->andReturn(['some_field' => 'some_value']);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn('1');
    $request->shouldReceive('user')
        ->andReturn(null);
    $request->shouldReceive('has')
        ->with('estimateSend')
        ->andReturn(true);

    $request->customer_id = 'customer-id-123';
    $request->currency_id = 'USD';
    $request->exchange_rate = 1.2;
    $request->discount_val = 20.0;
    $request->sub_total = 200.0;
    $request->total = 180.0;
    $request->tax = 10.0;

    $payload = $request->getEstimatePayload();

    expect($payload)->toBeArray()
        ->and($payload['some_field'])->toEqual('some_value')
        ->and($payload['creator_id'])->toBeNull()
        ->and($payload['status'])->toEqual(Estimate::STATUS_SENT)
        ->and($payload['company_id'])->toEqual('1')
        ->and($payload['tax_per_item'])->toEqual('NO ')
        ->and($payload['discount_per_item'])->toEqual('NO')
        ->and($payload['exchange_rate'])->toEqual(1.2)
        ->and($payload['base_discount_val'])->toEqual(20.0 * 1.2)
        ->and($payload['base_sub_total'])->toEqual(200.0 * 1.2)
        ->and($payload['base_total'])->toEqual(180.0 * 1.2)
        ->and($payload['base_tax'])->toEqual(10.0 * 1.2)
        ->and($payload['currency_id'])->toEqual('USD');
});

test('getEstimatePayload throws TypeError when customer is not found', function () {
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('currency', '1')
        ->andReturn('USD');
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('tax_per_item', '1')
        ->andReturn('YES');
    m::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('discount_per_item', '1')
        ->andReturn('YES');

    m::mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with('non-existent-customer')
        ->andReturn(null);

    $user = (object)['id' => 99];

    /** @var EstimatesRequest|m\MockInterface $request */
    $request = m::mock(EstimatesRequest::class)->makePartial();
    $request->shouldReceive('except')
        ->with('items', 'taxes')
        ->andReturn([]);
    $request->shouldReceive('header')
        ->with('company')
        ->andReturn('1');
    $request->shouldReceive('user')
        ->andReturn($user);
    $request->shouldReceive('has')
        ->with('estimateSend')
        ->andReturn(false);

    $request->customer_id = 'non-existent-customer';
    $request->currency_id = 'USD';
    $request->exchange_rate = 1.0;
    $request->discount_val = 0.0;
    $request->sub_total = 0.0;
    $request->total = 0.0;
    $request->tax = 0.0;

    expect(fn() => $request->getEstimatePayload())->toThrow(TypeError::class);
});




afterEach(function () {
    Mockery::close();
});
