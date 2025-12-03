<?php

use Crater\Http\Controllers\V1\Admin\General\NextNumberController;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

// Ensure Mockery is closed after each test to prevent conflicts, especially with 'overload' mocks.
beforeEach(function () {
    Mockery::close();
});

test('it returns the next invoice number for a valid request', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockInvoice = Mockery::mock(Invoice::class);
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockPayment = Mockery::mock(Payment::class);

    $expectedCompany = 'Acme Corp';
    $expectedUserId = 123;
    $expectedModelId = 456;
    $expectedNextNumber = 'INV-2023-001';

    // Configure Request mock to provide necessary data
    // The controller likely accesses request parameters via property access (e.g., $request->key),
    // which internally calls $request->all(). We need to mock 'all()' instead of 'offsetGet'.
    $mockRequest->shouldReceive('all')
        ->andReturn([
            'key' => 'invoice',
            'userId' => $expectedUserId,
            'model_id' => $expectedModelId,
        ]);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($expectedCompany);

    // Mock SerialNumberFormatter and its chained methods
    $mockSerialNumberFormatter = Mockery::mock('overload:' . SerialNumberFormatter::class);

    $mockSerialNumberFormatter->shouldReceive('setCompany')
        ->once()
        ->with($expectedCompany)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setCustomer')
        ->once()
        ->with($expectedUserId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModel')
        ->once()
        ->with(Mockery::type(Invoice::class))
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModelObject')
        ->once()
        ->with($expectedModelId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('getNextNumber')
        ->once()
        ->andReturn($expectedNextNumber);

    // Act
    $controller = new NextNumberController();
    $response = $controller->__invoke($mockRequest, $mockInvoice, $mockEstimate, $mockPayment);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'success' => true,
        'nextNumber' => $expectedNextNumber,
    ]);
});

test('it returns the next estimate number for a valid request', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockInvoice = Mockery::mock(Invoice::class);
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockPayment = Mockery::mock(Payment::class);

    $expectedCompany = 'Another Corp';
    $expectedUserId = 456;
    $expectedModelId = 789;
    $expectedNextNumber = 'EST-2023-002';

    // Configure Request mock
    // Mock 'all()' for property access
    $mockRequest->shouldReceive('all')
        ->andReturn([
            'key' => 'estimate',
            'userId' => $expectedUserId,
            'model_id' => $expectedModelId,
        ]);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($expectedCompany);

    // Mock SerialNumberFormatter
    $mockSerialNumberFormatter = Mockery::mock('overload:' . SerialNumberFormatter::class);

    $mockSerialNumberFormatter->shouldReceive('setCompany')
        ->once()
        ->with($expectedCompany)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setCustomer')
        ->once()
        ->with($expectedUserId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModel')
        ->once()
        ->with(Mockery::type(Estimate::class))
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModelObject')
        ->once()
        ->with($expectedModelId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('getNextNumber')
        ->once()
        ->andReturn($expectedNextNumber);

    // Act
    $controller = new NextNumberController();
    $response = $controller->__invoke($mockRequest, $mockInvoice, $mockEstimate, $mockPayment);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'success' => true,
        'nextNumber' => $expectedNextNumber,
    ]);
});

test('it returns the next payment number for a valid request', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockInvoice = Mockery::mock(Invoice::class);
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockPayment = Mockery::mock(Payment::class);

    $expectedCompany = 'Mega Corp';
    $expectedUserId = 789;
    $expectedModelId = 101;
    $expectedNextNumber = 'PAY-2023-003';

    // Configure Request mock
    // Mock 'all()' for property access
    $mockRequest->shouldReceive('all')
        ->andReturn([
            'key' => 'payment',
            'userId' => $expectedUserId,
            'model_id' => $expectedModelId,
        ]);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($expectedCompany);

    // Mock SerialNumberFormatter
    $mockSerialNumberFormatter = Mockery::mock('overload:' . SerialNumberFormatter::class);

    $mockSerialNumberFormatter->shouldReceive('setCompany')
        ->once()
        ->with($expectedCompany)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setCustomer')
        ->once()
        ->with($expectedUserId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModel')
        ->once()
        ->with(Mockery::type(Payment::class))
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModelObject')
        ->once()
        ->with($expectedModelId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('getNextNumber')
        ->once()
        ->andReturn($expectedNextNumber);

    // Act
    $controller = new NextNumberController();
    $response = $controller->__invoke($mockRequest, $mockInvoice, $mockEstimate, $mockPayment);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'success' => true,
        'nextNumber' => $expectedNextNumber,
    ]);
});

test('it returns null for an unknown request key (default case)', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockInvoice = Mockery::mock(Invoice::class);
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockPayment = Mockery::mock(Payment::class);

    // Configure Request mock with an unknown key
    // Mock 'all()' for property access. Only 'key' will be accessed for the switch.
    $mockRequest->shouldReceive('all')
        ->andReturn(['key' => 'unknown_key']);
    // Ensure no other methods of Request or SerialNumberFormatter are called for this case
    $mockRequest->shouldNotReceive('header');
    // The previous `shouldNotReceive('offsetGet')` are no longer relevant as we're mocking `all()`.

    // Act
    $controller = new NextNumberController();
    $response = $controller->__invoke($mockRequest, $mockInvoice, $mockEstimate, $mockPayment);

    // Assert: For the 'default' case, the controller explicitly returns 'null'.
    // Laravel's dispatcher would then convert this to an empty 200 OK response.
    expect($response)->toBeNull();
});

test('it returns a JSON error response when an exception occurs', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockInvoice = Mockery::mock(Invoice::class);
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockPayment = Mockery::mock(Payment::class);

    $expectedCompany = 'Error Corp';
    $expectedUserId = 999;
    $expectedModelId = 888;
    $exceptionMessage = 'Failed to generate serial number';

    // Configure Request mock
    // Mock 'all()' for property access
    $mockRequest->shouldReceive('all')
        ->andReturn([
            'key' => 'invoice',
            'userId' => $expectedUserId,
            'model_id' => $expectedModelId,
        ]);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($expectedCompany);

    // Mock SerialNumberFormatter to throw an exception
    $mockSerialNumberFormatter = Mockery::mock('overload:' . SerialNumberFormatter::class);

    $mockSerialNumberFormatter->shouldReceive('setCompany')
        ->once()
        ->with($expectedCompany)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setCustomer')
        ->once()
        ->with($expectedUserId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModel')
        ->once()
        ->with(Mockery::type(Invoice::class))
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModelObject')
        ->once()
        ->with($expectedModelId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('getNextNumber')
        ->once()
        ->andThrow(new Exception($exceptionMessage));

    // Act
    $controller = new NextNumberController();
    $response = $controller->__invoke($mockRequest, $mockInvoice, $mockEstimate, $mockPayment);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'success' => false,
        'message' => $exceptionMessage,
    ]);
    expect($response->getStatusCode())->toBe(200); // Laravel's default for response()->json is 200
});

test('it passes null for company when header is missing', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockInvoice = Mockery::mock(Invoice::class);
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockPayment = Mockery::mock(Payment::class);

    $expectedUserId = 1;
    $expectedModelId = 2;
    $expectedNextNumber = 'INV-NULL-COMPANY';

    // Mock 'all()' for property access
    $mockRequest->shouldReceive('all')
        ->andReturn([
            'key' => 'invoice',
            'userId' => $expectedUserId,
            'model_id' => $expectedModelId,
        ]);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn(null); // Simulate missing header

    $mockSerialNumberFormatter = Mockery::mock('overload:' . SerialNumberFormatter::class);

    $mockSerialNumberFormatter->shouldReceive('setCompany')
        ->once()
        ->with(null) // Expect null to be passed
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setCustomer')
        ->once()
        ->with($expectedUserId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModel')
        ->once()
        ->with(Mockery::type(Invoice::class)) // Specify the expected model type
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModelObject')
        ->once()
        ->with($expectedModelId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('getNextNumber')
        ->once()
        ->andReturn($expectedNextNumber);

    // Act
    $controller = new NextNumberController();
    $response = $controller->__invoke($mockRequest, $mockInvoice, $mockEstimate, $mockPayment);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'success' => true,
        'nextNumber' => $expectedNextNumber,
    ]);
});

test('it passes null for customer when userId is missing', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockInvoice = Mockery::mock(Invoice::class);
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockPayment = Mockery::mock(Payment::class);

    $expectedCompany = 'Test Co';
    $expectedModelId = 3;
    $expectedNextNumber = 'INV-NULL-USER';

    // Mock 'all()' for property access
    $mockRequest->shouldReceive('all')
        ->andReturn([
            'key' => 'invoice',
            'userId' => null, // Simulate missing userId
            'model_id' => $expectedModelId,
        ]);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($expectedCompany);

    $mockSerialNumberFormatter = Mockery::mock('overload:' . SerialNumberFormatter::class);

    $mockSerialNumberFormatter->shouldReceive('setCompany')
        ->once()
        ->with($expectedCompany)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setCustomer')
        ->once()
        ->with(null) // Expect null to be passed
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModel')
        ->once()
        ->with(Mockery::type(Invoice::class)) // Specify the expected model type
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModelObject')
        ->once()
        ->with($expectedModelId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('getNextNumber')
        ->once()
        ->andReturn($expectedNextNumber);

    // Act
    $controller = new NextNumberController();
    $response = $controller->__invoke($mockRequest, $mockInvoice, $mockEstimate, $mockPayment);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'success' => true,
        'nextNumber' => $expectedNextNumber,
    ]);
});

test('it passes null for model object when model_id is missing', function () {
    // Arrange
    $mockRequest = Mockery::mock(Request::class);
    $mockInvoice = Mockery::mock(Invoice::class);
    $mockEstimate = Mockery::mock(Estimate::class);
    $mockPayment = Mockery::mock(Payment::class);

    $expectedCompany = 'Test Co 2';
    $expectedUserId = 4;
    $expectedNextNumber = 'INV-NULL-MODELID';

    // Mock 'all()' for property access
    $mockRequest->shouldReceive('all')
        ->andReturn([
            'key' => 'invoice',
            'userId' => $expectedUserId,
            'model_id' => null, // Simulate missing model_id
        ]);
    $mockRequest->shouldReceive('header')
        ->with('company')
        ->andReturn($expectedCompany);

    $mockSerialNumberFormatter = Mockery::mock('overload:' . SerialNumberFormatter::class);

    $mockSerialNumberFormatter->shouldReceive('setCompany')
        ->once()
        ->with($expectedCompany)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setCustomer')
        ->once()
        ->with($expectedUserId)
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModel')
        ->once()
        ->with(Mockery::type(Invoice::class)) // Specify the expected model type
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('setModelObject')
        ->once()
        ->with(null) // Expect null to be passed
        ->andReturnSelf();

    $mockSerialNumberFormatter->shouldReceive('getNextNumber')
        ->once()
        ->andReturn($expectedNextNumber);

    // Act
    $controller = new NextNumberController();
    $response = $controller->__invoke($mockRequest, $mockInvoice, $mockEstimate, $mockPayment);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toMatchArray([
        'success' => true,
        'nextNumber' => $expectedNextNumber,
    ]);
});

afterEach(function () {
    Mockery::close();
});