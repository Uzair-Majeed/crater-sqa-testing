<?php

use Crater\Services\SerialNumberFormatter;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

if (!function_exists('callPrivateMethod')) {
    function callPrivateMethod($object, $methodName, array $parameters = []) {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}

if (!function_exists('setPrivateProperty')) {
    function setPrivateProperty($object, $propertyName, $value) {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}

if (!function_exists('getPrivateProperty')) {
    function getPrivateProperty($object, $propertyName) {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}

// Ensure Mockery is closed after each test to prevent memory leaks and conflicts
afterEach(fn () => Mockery::close());

test('setModel sets the model property and returns self', function () {
    $formatter = new SerialNumberFormatter();
    $modelClass = 'App\Models\Invoice'; // A dummy class name

    $result = $formatter->setModel($modelClass);

    expect($result)->toBeInstanceOf(SerialNumberFormatter::class);
    expect(getPrivateProperty($formatter, 'model'))->toBe($modelClass);
});

test('setModelObject sets nextSequenceNumber and nextCustomerSequenceNumber when object exists and matches customer', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->setModel('App\Models\Invoice');

    // Mock the dynamic model class for its static `find` method
    $modelMockInstance = (object)['id' => 1, 'sequence_number' => 100, 'customer_sequence_number' => 50, 'customer_id' => 1];
    mock('alias:App\Models\Invoice')
        ->shouldReceive('find')
        ->with(1)
        ->andReturn($modelMockInstance)
        ->once();

    // Set a mock customer which will be used for comparison
    $customerMock = (object)['id' => 1, 'prefix' => 'CST'];
    setPrivateProperty($formatter, 'customer', $customerMock);

    $result = $formatter->setModelObject(1);

    expect($result)->toBeInstanceOf(SerialNumberFormatter::class);
    expect(getPrivateProperty($formatter, 'ob'))->toBe($modelMockInstance);
    expect($formatter->nextSequenceNumber)->toBe(100);
    expect($formatter->nextCustomerSequenceNumber)->toBe(50);
});

test('setModelObject does not set sequence numbers if model object is not found', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->setModel('App\Models\Invoice');

    mock('alias:App\Models\Invoice')
        ->shouldReceive('find')
        ->with(99)
        ->andReturn(null)
        ->once();

    $formatter->setModelObject(99);

    expect(getPrivateProperty($formatter, 'ob'))->toBeNull();
    expect($formatter->nextSequenceNumber)->toBeNull();
    expect($formatter->nextCustomerSequenceNumber)->toBeNull();
});

test('setModelObject sets nextSequenceNumber but not nextCustomerSequenceNumber if customer not set or mismatched', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->setModel('App\Models\Invoice');

    $modelMockInstance = (object)['id' => 1, 'sequence_number' => 100, 'customer_sequence_number' => 50, 'customer_id' => 2]; // Customer ID mismatch
    mock('alias:App\Models\Invoice')
        ->shouldReceive('find')
        ->with(1)
        ->andReturn($modelMockInstance)
        ->once();

    // Set a mock customer with ID 1, which will not match the model's customer_id 2
    $customerMock = (object)['id' => 1, 'prefix' => 'CST'];
    setPrivateProperty($formatter, 'customer', $customerMock);

    $formatter->setModelObject(1);

    expect($formatter->nextSequenceNumber)->toBe(100);
    expect($formatter->nextCustomerSequenceNumber)->toBeNull(); // Should not be set due to customer ID mismatch
});

test('setModelObject sets nextSequenceNumber but not nextCustomerSequenceNumber if customer_sequence_number property is missing on model object', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->setModel('App\Models\Invoice');

    $modelMockInstance = (object)['id' => 1, 'sequence_number' => 100, 'customer_id' => 1]; // customer_sequence_number missing
    mock('alias:App\Models\Invoice')
        ->shouldReceive('find')
        ->with(1)
        ->andReturn($modelMockInstance)
        ->once();

    $customerMock = (object)['id' => 1, 'prefix' => 'CST'];
    setPrivateProperty($formatter, 'customer', $customerMock);

    $formatter->setModelObject(1);

    expect($formatter->nextSequenceNumber)->toBe(100);
    expect($formatter->nextCustomerSequenceNumber)->toBeNull();
});

test('setCompany sets the company property and returns self', function () {
    $formatter = new SerialNumberFormatter();
    $companyId = 123;

    $result = $formatter->setCompany($companyId);

    expect($result)->toBeInstanceOf(SerialNumberFormatter::class);
    expect(getPrivateProperty($formatter, 'company'))->toBe($companyId);
});

test('setCustomer finds and sets the customer property', function () {
    $formatter = new SerialNumberFormatter();
    $customerId = 1;
    $customerMock = Mockery::mock(Customer::class);
    $customerMock->id = $customerId;

    mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with($customerId)
        ->andReturn($customerMock)
        ->once();

    $result = $formatter->setCustomer($customerId);

    expect($result)->toBeInstanceOf(SerialNumberFormatter::class);
    expect(getPrivateProperty($formatter, 'customer'))->toBe($customerMock);
});

test('setCustomer sets customer to null if no customer ID is provided', function () {
    $formatter = new SerialNumberFormatter();

    mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with(null)
        ->andReturn(null) // Customer::find(null) usually returns null
        ->once();

    $result = $formatter->setCustomer(); // No ID provided

    expect($result)->toBeInstanceOf(SerialNumberFormatter::class);
    expect(getPrivateProperty($formatter, 'customer'))->toBeNull();
});

test('setCustomer sets customer to null if customer ID is provided but not found', function () {
    $formatter = new SerialNumberFormatter();
    $customerId = 999; // Non-existent ID

    mock('alias:' . Customer::class)
        ->shouldReceive('find')
        ->with($customerId)
        ->andReturn(null)
        ->once();

    $result = $formatter->setCustomer($customerId);

    expect($result)->toBeInstanceOf(SerialNumberFormatter::class);
    expect(getPrivateProperty($formatter, 'customer'))->toBeNull();
});

test('getNextNumber uses request format if available', function () {
    // Use a partial mock to allow mocking private methods of the SUT
    $formatter = Mockery::mock(SerialNumberFormatter::class)->makePartial();
    $formatter->shouldAllowMockingProtectedMethods(); // Necessary for mocking private methods

    $formatter->setModel('App\Models\Invoice');
    $formatter->setCompany(1);

    // Mock request() helper using Laravel's Request facade
    $requestMock = mock(Request::class);
    $requestMock->shouldReceive('has')->with('format')->andReturn(true)->once();
    $requestMock->shouldReceive('get')->with('format')->andReturn('REQ-{{SEQUENCE}}')->once();
    \Illuminate\Support\Facades\Request::swap($requestMock);

    // Ensure CompanySetting::getSetting is NOT called as request format takes precedence
    mock('alias:' . CompanySetting::class)
        ->shouldNotReceive('getSetting');

    // Mock internal calls to `setNextNumbers` and `generateSerialNumber`
    $formatter->shouldReceive('setNextNumbers')->once();
    $formatter->shouldReceive('generateSerialNumber')
        ->with('REQ-{{SEQUENCE}}') // Assert the correct format is passed
        ->andReturn('MOCKED_SERIAL_NUMBER_FROM_REQ')
        ->once();

    $result = $formatter->getNextNumber();
    expect($result)->toBe('MOCKED_SERIAL_NUMBER_FROM_REQ');

    // Clean up request mock for other tests
    \Illuminate\Support\Facades\Request::swap(new Request());
});

test('getNextNumber uses CompanySetting format if request format is not available', function () {
    $formatter = Mockery::mock(SerialNumberFormatter::class)->makePartial();
    $formatter->shouldAllowMockingProtectedMethods();

    $formatter->setModel('App\Models\Invoice');
    $formatter->setCompany(1);

    // Mock request() helper
    $requestMock = mock(Request::class);
    $requestMock->shouldReceive('has')->with('format')->andReturn(false)->once();
    \Illuminate\Support\Facades\Request::swap($requestMock);

    // Mock CompanySetting::getSetting()
    mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('invoice_number_format', 1) // `invoice` derived from class_basename('App\Models\Invoice')
        ->andReturn('COMP-{{SEQUENCE}}')
        ->once();

    // Mock internal calls
    $formatter->shouldReceive('setNextNumbers')->once();
    $formatter->shouldReceive('generateSerialNumber')
        ->with('COMP-{{SEQUENCE}}') // Assert the correct format is passed
        ->andReturn('MOCKED_SERIAL_NUMBER_FROM_COMP')
        ->once();

    $result = $formatter->getNextNumber();
    expect($result)->toBe('MOCKED_SERIAL_NUMBER_FROM_COMP');

    // Clean up request mock
    \Illuminate\Support\Facades\Request::swap(new Request());
});

test('setNextNumbers calls setNextSequenceNumber and setNextCustomerSequenceNumber if their properties are not already set', function () {
    $formatter = Mockery::mock(SerialNumberFormatter::class)->makePartial();
    $formatter->shouldAllowMockingProtectedMethods();

    $formatter->nextSequenceNumber = null; // Ensure null
    $formatter->nextCustomerSequenceNumber = null; // Ensure null

    // Expect both private methods to be called
    $formatter->shouldReceive('setNextSequenceNumber')->once()->andReturnSelf();
    $formatter->shouldReceive('setNextCustomerSequenceNumber')->once()->andReturnSelf();

    $result = $formatter->setNextNumbers();

    expect($result)->toBeInstanceOf(SerialNumberFormatter::class);
});

test('setNextNumbers does not call setNextSequenceNumber if its property is already set', function () {
    $formatter = Mockery::mock(SerialNumberFormatter::class)->makePartial();
    $formatter->shouldAllowMockingProtectedMethods();

    $formatter->nextSequenceNumber = 50; // Already set
    $formatter->nextCustomerSequenceNumber = null;

    // Expect setNextSequenceNumber NOT to be called
    $formatter->shouldNotReceive('setNextSequenceNumber');
    // Expect setNextCustomerSequenceNumber to be called
    $formatter->shouldReceive('setNextCustomerSequenceNumber')->once()->andReturnSelf();

    $result = $formatter->setNextNumbers();

    expect($result)->toBeInstanceOf(SerialNumberFormatter::class);
});

test('setNextNumbers does not call setNextCustomerSequenceNumber if its property is already set', function () {
    $formatter = Mockery::mock(SerialNumberFormatter::class)->makePartial();
    $formatter->shouldAllowMockingProtectedMethods();

    $formatter->nextSequenceNumber = null;
    $formatter->nextCustomerSequenceNumber = 20; // Already set

    // Expect setNextSequenceNumber to be called
    $formatter->shouldReceive('setNextSequenceNumber')->once()->andReturnSelf();
    // Expect setNextCustomerSequenceNumber NOT to be called
    $formatter->shouldNotReceive('setNextCustomerSequenceNumber');

    $result = $formatter->setNextNumbers();

    expect($result)->toBeInstanceOf(SerialNumberFormatter::class);
});

test('setNextSequenceNumber sets nextSequenceNumber to 1 if no last record found', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->setModel('App\Models\Invoice');
    $formatter->setCompany(1);

    // Mock the Eloquent query builder chain to return null (no last record)
    $queryBuilderMock = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $queryBuilderMock->shouldReceive('where')->andReturnSelf(); // Chainable `where` calls
    $queryBuilderMock->shouldReceive('take')->with(1)->andReturnSelf();
    $queryBuilderMock->shouldReceive('first')->andReturn(null)->once(); // Crucial: no record found

    mock('alias:App\Models\Invoice')
        ->shouldReceive('orderBy')->with('sequence_number', 'desc')->andReturn($queryBuilderMock)->once();

    callPrivateMethod($formatter, 'setNextSequenceNumber');

    expect($formatter->nextSequenceNumber)->toBe(1);
});

test('setNextSequenceNumber sets nextSequenceNumber to last sequence + 1 if record found', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->setModel('App\Models\Invoice');
    $formatter->setCompany(1);

    $lastModel = (object)['sequence_number' => 123];

    $queryBuilderMock = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $queryBuilderMock->shouldReceive('where')->andReturnSelf();
    $queryBuilderMock->shouldReceive('take')->with(1)->andReturnSelf();
    $queryBuilderMock->shouldReceive('first')->andReturn($lastModel)->once(); // Crucial: record found

    mock('alias:App\Models\Invoice')
        ->shouldReceive('orderBy')->with('sequence_number', 'desc')->andReturn($queryBuilderMock)->once();

    callPrivateMethod($formatter, 'setNextSequenceNumber');

    expect($formatter->nextSequenceNumber)->toBe(124);
});

test('setNextCustomerSequenceNumber sets nextCustomerSequenceNumber to 1 if no last record found (customer not set)', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->setModel('App\Models\Invoice');
    $formatter->setCompany(1);
    // Customer not set, so `customer_id` for query should default to 1 as per implementation
    setPrivateProperty($formatter, 'customer', null);

    $queryBuilderMock = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $queryBuilderMock->shouldReceive('where')->andReturnSelf();
    $queryBuilderMock->shouldReceive('take')->with(1)->andReturnSelf();
    $queryBuilderMock->shouldReceive('first')->andReturn(null)->once();

    mock('alias:App\Models\Invoice')
        ->shouldReceive('orderBy')->with('customer_sequence_number', 'desc')->andReturn($queryBuilderMock)->once();

    callPrivateMethod($formatter, 'setNextCustomerSequenceNumber');

    expect($formatter->nextCustomerSequenceNumber)->toBe(1);
});

test('setNextCustomerSequenceNumber sets nextCustomerSequenceNumber to last customer sequence + 1 if record found', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->setModel('App\Models\Invoice');
    $formatter->setCompany(1);

    $customerMock = (object)['id' => 5];
    setPrivateProperty($formatter, 'customer', $customerMock);

    $lastModel = (object)['customer_sequence_number' => 77];

    $queryBuilderMock = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $queryBuilderMock->shouldReceive('where')->andReturnSelf();
    $queryBuilderMock->shouldReceive('take')->with(1)->andReturnSelf();
    $queryBuilderMock->shouldReceive('first')->andReturn($lastModel)->once();

    mock('alias:App\Models\Invoice')
        ->shouldReceive('orderBy')->with('customer_sequence_number', 'desc')->andReturn($queryBuilderMock)->once();

    callPrivateMethod($formatter, 'setNextCustomerSequenceNumber');

    expect($formatter->nextCustomerSequenceNumber)->toBe(78);
});

test('getPlaceholders returns an empty collection for an empty format string', function () {
    $placeholders = SerialNumberFormatter::getPlaceholders('');
    expect($placeholders)->toBeInstanceOf(Collection::class)
        ->and($placeholders)->toBeEmpty();
});

test('getPlaceholders returns an empty collection for a format string with no placeholders', function () {
    $placeholders = SerialNumberFormatter::getPlaceholders('PLAIN_TEXT_NUMBER');
    expect($placeholders)->toBeInstanceOf(Collection::class)
        ->and($placeholders)->toBeEmpty();
});

test('getPlaceholders extracts valid placeholders with and without values', function () {
    $format = 'INV-{{SEQUENCE:4}}-{{DATE_FORMAT}}-{{RANDOM_SEQUENCE:8}}-{{CUSTOMER_SERIES}}-{{DELIMITER:-}}';
    $expected = collect([
        ['name' => 'SEQUENCE', 'value' => '4'],
        ['name' => 'DATE_FORMAT', 'value' => ''], // No value provided
        ['name' => 'RANDOM_SEQUENCE', 'value' => '8'],
        ['name' => 'CUSTOMER_SERIES', 'value' => ''], // No value provided
        ['name' => 'DELIMITER', 'value' => '-'],
    ]);

    $placeholders = SerialNumberFormatter::getPlaceholders($format);
    expect($placeholders->toArray())->toEqual($expected->toArray());
});

test('getPlaceholders ignores invalid placeholders', function () {
    $format = '{{SEQUENCE:4}}-{{INVALID_PLACEHOLDER:abc}}-{{DATE_FORMAT}}';
    $expected = collect([
        ['name' => 'SEQUENCE', 'value' => '4'],
        ['name' => 'DATE_FORMAT', 'value' => ''],
    ]);

    $placeholders = SerialNumberFormatter::getPlaceholders($format);
    expect($placeholders->toArray())->toEqual($expected->toArray());
});

test('getPlaceholders handles multiple placeholders of the same type', function () {
    $format = '{{SEQUENCE:2}}-{{SEQUENCE:3}}';
    $expected = collect([
        ['name' => 'SEQUENCE', 'value' => '2'],
        ['name' => 'SEQUENCE', 'value' => '3'],
    ]);

    $placeholders = SerialNumberFormatter::getPlaceholders($format);
    expect($placeholders->toArray())->toEqual($expected->toArray());
});

test('generateSerialNumber processes SEQUENCE placeholder with default length', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 123;
    setPrivateProperty($formatter, 'nextCustomerSequenceNumber', 1); // Set to avoid warnings if customer sequence logic runs

    // Mock the static `getPlaceholders` method that `generateSerialNumber` calls
    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{SEQUENCE}}')
        ->andReturn(collect([['name' => 'SEQUENCE', 'value' => '']])) // Empty value for default length
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{SEQUENCE}}']);
    expect($serialNumber)->toBe('000123'); // Default length 6
});

test('generateSerialNumber processes SEQUENCE placeholder with custom length', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 123;
    setPrivateProperty($formatter, 'nextCustomerSequenceNumber', 1);

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{SEQUENCE:4}}')
        ->andReturn(collect([['name' => 'SEQUENCE', 'value' => '4']]))
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{SEQUENCE:4}}']);
    expect($serialNumber)->toBe('0123');
});

test('generateSerialNumber processes DATE_FORMAT placeholder with default format', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 1;
    setPrivateProperty($formatter, 'nextCustomerSequenceNumber', 1);

    // Mock the current date using Carbon for predictable `date()` output
    Carbon::setTestNow(Carbon::create(2023, 10, 26));

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{DATE_FORMAT}}')
        ->andReturn(collect([['name' => 'DATE_FORMAT', 'value' => '']])) // Empty value for default format
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{DATE_FORMAT}}']);
    expect($serialNumber)->toBe('2023'); // Default format 'Y'
    Carbon::setTestNow(); // Reset Carbon after the test
});

test('generateSerialNumber processes DATE_FORMAT placeholder with custom format', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 1;
    setPrivateProperty($formatter, 'nextCustomerSequenceNumber', 1);

    Carbon::setTestNow(Carbon::create(2023, 10, 26));

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{DATE_FORMAT:Ymd}}')
        ->andReturn(collect([['name' => 'DATE_FORMAT', 'value' => 'Ymd']]))
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{DATE_FORMAT:Ymd}}']);
    expect($serialNumber)->toBe('20231026');
    Carbon::setTestNow();
});

test('generateSerialNumber processes RANDOM_SEQUENCE placeholder with default length', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 1;
    setPrivateProperty($formatter, 'nextCustomerSequenceNumber', 1);

    // Mock global functions `random_bytes` and `bin2hex` for predictable output
    function random_bytes(int $length): string { return str_repeat('z', $length); }
    function bin2hex(string $string): string { return str_repeat('e', strlen($string) * 2); } // 'z' -> '7a', so '7a7a...'

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{RANDOM_SEQUENCE}}')
        ->andReturn(collect([['name' => 'RANDOM_SEQUENCE', 'value' => '']])) // Empty value for default length
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{RANDOM_SEQUENCE}}']);
    // random_bytes(6) -> "zzzzzz"
    // bin2hex("zzzzzz") -> "7a7a7a7a7a7a" (length 12)
    // substr("7a7a7a7a7a7a", 0, 6) -> "7a7a7a"
    expect($serialNumber)->toBe('7a7a7a');
});

test('generateSerialNumber processes RANDOM_SEQUENCE placeholder with custom length', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 1;
    setPrivateProperty($formatter, 'nextCustomerSequenceNumber', 1);

    function random_bytes(int $length): string { return str_repeat('y', $length); }
    function bin2hex(string $string): string { return str_repeat('6a', strlen($string)); } // 'y' -> '79', so '7979...'

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{RANDOM_SEQUENCE:4}}')
        ->andReturn(collect([['name' => 'RANDOM_SEQUENCE', 'value' => '4']]))
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{RANDOM_SEQUENCE:4}}']);
    // random_bytes(4) -> "yyyy"
    // bin2hex("yyyy") -> "79797979" (length 8)
    // substr("79797979", 0, 4) -> "7979"
    expect($serialNumber)->toBe('7979');
});

test('generateSerialNumber processes CUSTOMER_SERIES placeholder with customer prefix', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 1;
    setPrivateProperty($formatter, 'nextCustomerSequenceNumber', 1);

    $customerMock = (object)['prefix' => 'ABC'];
    setPrivateProperty($formatter, 'customer', $customerMock);

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{CUSTOMER_SERIES}}')
        ->andReturn(collect([['name' => 'CUSTOMER_SERIES', 'value' => '']]))
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{CUSTOMER_SERIES}}']);
    expect($serialNumber)->toBe('ABC');
});

test('generateSerialNumber processes CUSTOMER_SERIES placeholder with default prefix if customer has no prefix', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 1;
    setPrivateProperty($formatter, 'nextCustomerSequenceNumber', 1);

    $customerMock = (object)['id' => 1, 'prefix' => null]; // `prefix` is explicitly null
    setPrivateProperty($formatter, 'customer', $customerMock);

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{CUSTOMER_SERIES}}')
        ->andReturn(collect([['name' => 'CUSTOMER_SERIES', 'value' => '']]))
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{CUSTOMER_SERIES}}']);
    expect($serialNumber)->toBe('CST'); // Default 'CST'
});

test('generateSerialNumber processes CUSTOMER_SERIES placeholder with default prefix if no customer is set', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 1;
    setPrivateProperty($formatter, 'nextCustomerSequenceNumber', 1);

    setPrivateProperty($formatter, 'customer', null); // No customer object set

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{CUSTOMER_SERIES}}')
        ->andReturn(collect([['name' => 'CUSTOMER_SERIES', 'value' => '']]))
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{CUSTOMER_SERIES}}']);
    expect($serialNumber)->toBe('CST'); // Default 'CST'
});

test('generateSerialNumber processes CUSTOMER_SEQUENCE placeholder with default padding (empty value for length)', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 1;
    $formatter->nextCustomerSequenceNumber = 123;

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{CUSTOMER_SEQUENCE}}')
        ->andReturn(collect([['name' => 'CUSTOMER_SEQUENCE', 'value' => '']])) // Empty value for length
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{CUSTOMER_SEQUENCE}}']);
    expect($serialNumber)->toBe('123'); // No padding as length is effectively 0
});

test('generateSerialNumber processes CUSTOMER_SEQUENCE placeholder with custom padding', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 1;
    $formatter->nextCustomerSequenceNumber = 123;

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{CUSTOMER_SEQUENCE:5}}')
        ->andReturn(collect([['name' => 'CUSTOMER_SEQUENCE', 'value' => '5']]))
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{CUSTOMER_SEQUENCE:5}}']);
    expect($serialNumber)->toBe('00123');
});

test('generateSerialNumber processes DELIMITER and SERIES placeholders (default case in switch)', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 1;
    setPrivateProperty($formatter, 'nextCustomerSequenceNumber', 1);

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with('{{SERIES:INV}}-{{DELIMITER:-}}')
        ->andReturn(collect([
            ['name' => 'SERIES', 'value' => 'INV'],
            ['name' => 'DELIMITER', 'value' => '-'],
        ]))
        ->once();

    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', ['{{SERIES:INV}}-{{DELIMITER:-}}']);
    expect($serialNumber)->toBe('INV-'); // Values appended directly for unknown placeholders
});

test('generateSerialNumber handles a combination of multiple placeholders', function () {
    $formatter = new SerialNumberFormatter();
    $formatter->nextSequenceNumber = 20;
    $formatter->nextCustomerSequenceNumber = 5;

    $customerMock = (object)['prefix' => 'CUST'];
    setPrivateProperty($formatter, 'customer', $customerMock);

    Carbon::setTestNow(Carbon::create(2023, 11, 15));

    // Mock global functions for predictable output for RANDOM_SEQUENCE
    function random_bytes(int $length): string { return str_repeat('x', $length); }
    function bin2hex(string $string): string { return str_repeat('78', strlen($string)); } // 'x' is 78 in hex

    $format = '{{CUSTOMER_SERIES}}-{{DATE_FORMAT:ymd}}-{{SEQUENCE:3}}-{{RANDOM_SEQUENCE:4}}-{{CUSTOMER_SEQUENCE:2}}';
    $expectedPlaceholders = collect([
        ['name' => 'CUSTOMER_SERIES', 'value' => ''],
        ['name' => 'DATE_FORMAT', 'value' => 'ymd'],
        ['name' => 'SEQUENCE', 'value' => '3'],
        ['name' => 'RANDOM_SEQUENCE', 'value' => '4'],
        ['name' => 'CUSTOMER_SEQUENCE', 'value' => '2'],
    ]);

    mock('overload:' . SerialNumberFormatter::class)
        ->shouldReceive('getPlaceholders')
        ->with($format)
        ->andReturn($expectedPlaceholders)
        ->once();

    // Expected components:
    // CUSTOMER_SERIES: 'CUST' (from customer mock)
    // DATE_FORMAT: '231115' (from Carbon::setTestNow and 'ymd' format)
    // SEQUENCE: '020' (str_pad(20, 3, 0, STR_PAD_LEFT))
    // RANDOM_SEQUENCE: '7878' (random_bytes(4) -> 'xxxx', bin2hex('xxxx') -> '78787878', substr(0,4) -> '7878')
    // CUSTOMER_SEQUENCE: '05' (str_pad(5, 2, 0, STR_PAD_LEFT))

    $expectedSerialNumber = 'CUST-231115-020-7878-05';
    $serialNumber = callPrivateMethod($formatter, 'generateSerialNumber', [$format]);
    expect($serialNumber)->toBe($expectedSerialNumber);

    Carbon::setTestNow(); // Reset Carbon
});



