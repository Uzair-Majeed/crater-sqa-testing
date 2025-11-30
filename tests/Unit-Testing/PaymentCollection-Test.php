<?php

use Mockery as m;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Crater\Http\Resources\PaymentCollection;
use Illuminate\Http\Resources\Json\JsonResource;

beforeEach(function () {
    m::close(); // Ensure Mockery mocks are closed before each test
});

test('toArray returns an empty data array when the underlying collection is empty', function () {
    $request = m::mock(Request::class);
    $collection = new Collection([]);

    $paymentCollection = new PaymentCollection($collection);

    $result = $paymentCollection->toArray($request);

    // According to ResourceCollection's default behavior, an empty collection results in ['data' => []]
    expect($result)->toBe([
        'data' => [],
    ]);
});

test('toArray correctly transforms a collection of simple associative array items', function () {
    $request = m::mock(Request::class);
    $item1 = ['id' => 1, 'amount' => 100, 'currency' => 'USD'];
    $item2 = ['id' => 2, 'amount' => 250, 'currency' => 'EUR'];
    $collection = new Collection([$item1, $item2]);

    $paymentCollection = new PaymentCollection($collection);

    $result = $paymentCollection->toArray($request);

    // ResourceCollection::toArray wraps simple arrays in JsonResource and calls their toArray method.
    // JsonResource's default toArray for an array simply returns the array itself.
    expect($result)->toBe([
        'data' => [
            $item1,
            $item2,
        ],
    ]);
});

test('toArray correctly transforms a collection of stdClass objects', function () {
    $request = m::mock(Request::class);
    $item1 = (object) ['id' => 1, 'note' => 'Test A'];
    $item2 = (object) ['id' => 2, 'note' => 'Test B'];
    $collection = new Collection([$item1, $item2]);

    $paymentCollection = new PaymentCollection($collection);

    $result = $paymentCollection->toArray($request);

    // JsonResource::toArray for an object casts it to an array.
    expect($result)->toBe([
        'data' => [
            (array) $item1,
            (array) $item2,
        ],
    ]);
});

test('toArray handles items that are already JsonResource instances by calling their toArray method', function () {
    $request = m::mock(Request::class);
    $resource1Data = ['id' => 10, 'status' => 'paid'];
    $resource2Data = ['id' => 20, 'status' => 'pending'];

    // Mock JsonResource instances to control their toArray output
    $resource1 = m::mock(JsonResource::class);
    $resource1->shouldReceive('toArray')->once()->with($request)->andReturn($resource1Data);

    $resource2 = m::mock(JsonResource::class);
    $resource2->shouldReceive('toArray')->once()->with($request)->andReturn($resource2Data);

    $collection = new Collection([$resource1, $resource2]);

    $paymentCollection = new PaymentCollection($collection);

    $result = $paymentCollection->toArray($request);

    expect($result)->toBe([
        'data' => [
            $resource1Data,
            $resource2Data,
        ],
    ]);
});

test('toArray handles null items gracefully, returning null in the data array', function () {
    $request = m::mock(Request::class);
    $collection = new Collection([null, ['id' => 1, 'value' => 'not null']]);

    $paymentCollection = new PaymentCollection($collection);
    $result = $paymentCollection->toArray($request);

    // JsonResource::toArray for null returns null.
    expect($result)->toBe([
        'data' => [
            null,
            ['id' => 1, 'value' => 'not null'],
        ],
    ]);
});

test('toArray handles primitive items (integers, strings, booleans) gracefully, returning them directly', function () {
    $request = m::mock(Request::class);
    $collection = new Collection([123, 'string_data', true, ['id' => 1]]);

    $paymentCollection = new PaymentCollection($collection);
    $result = $paymentCollection->toArray($request);

    // JsonResource::toArray for primitive values returns the value itself.
    expect($result)->toBe([
        'data' => [
            123,
            'string_data',
            true,
            ['id' => 1],
        ],
    ]);
});

test('toArray respects global JsonResource wrapping settings by returning unwrapped data when disabled', function () {
    $request = m::mock(Request::class);
    $item1 = ['id' => 1, 'amount' => 100];
    $item2 = ['id' => 2, 'amount' => 200];
    $collection = new Collection([$item1, $item2]);

    JsonResource::withoutWrapping(); // Disable wrapping globally

    $paymentCollection = new PaymentCollection($collection);
    $result = $paymentCollection->toArray($request);

    // If wrapping is disabled, ResourceCollection::toArray returns the raw array of transformed resources directly.
    expect($result)->toBe([$item1, $item2]);

    // IMPORTANT: Re-enable wrapping to avoid impacting other tests
    JsonResource::withWrapping();
});

test('toArray passes the request object to the underlying resources', function () {
    $mockRequest = m::mock(Request::class);
    $mockRequest->shouldReceive('get')->with('param', 'default')->andReturn('test_value');

    // Create a mock resource that expects the request and uses it
    $mockItemData = ['original_id' => 5];
    $resource = new class($mockItemData) extends JsonResource {
        public function toArray($request)
        {
            // Simulate using the request in a real resource
            return [
                'id' => $this->original_id,
                'param_from_request' => $request->get('param', 'default'),
            ];
        }
    };

    $collection = new Collection([$resource]);
    $paymentCollection = new PaymentCollection($collection);

    $result = $paymentCollection->toArray($mockRequest);

    // Assert that the transformed item reflects the interaction with the mock request
    expect($result)->toBe([
        'data' => [
            [
                'id' => 5,
                'param_from_request' => 'test_value',
            ],
        ],
    ]);
});

// No private or protected methods defined directly in PaymentCollection to test.
// The class itself contains no complex logic or branches beyond delegating to its parent.
// All tests focus on verifying this delegation behaves as expected under various input conditions.
