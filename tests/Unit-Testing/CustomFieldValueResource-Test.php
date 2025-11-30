<?php

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Mockery\MockInterface;
use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\CustomFieldResource;
use Crater\Http\Resources\CustomFieldValueResource;
use Crater\Models\CompanySetting;
use Illuminate\Database\Eloquent\Model;

// Mock the global helper function 'getCustomFieldValueKey' within the test's context.
// This allows controlling its return value during the test, adhering to white-box testing by isolating dependencies.
// This relies on the function not being defined globally yet. In a fresh test run, this should work.
if (!function_exists('getCustomFieldValueKey')) {
    function getCustomFieldValueKey(string $type): string
    {
        // Access the mocked object from the current test instance
        return test()->globalGetCustomFieldValueKey($type);
    }
}

beforeEach(function () {
    // Initialize a Mockery mock for the global function 'getCustomFieldValueKey'
    $this->globalGetCustomFieldValueKey = Mockery::mock(stdClass::class);
});

test('toArray transforms resource with all fields and existing relationships', function () {
    // Arrange
    $customFieldModel = Mockery::mock(Model::class);
    $companyModel = Mockery::mock(Model::class);

    $mockData = (object)[
        'id' => 1,
        'custom_field_valuable_type' => 'App\\Models\\Invoice',
        'custom_field_valuable_id' => 10,
        'type' => 'datetime', // Type set to 'datetime' to trigger dateTimeFormat for 'date_time_answer'
        'boolean_answer' => true,
        'date_answer' => '2023-01-01',
        'time_answer' => '10:30:00',
        'string_answer' => 'Test String',
        'number_answer' => 123.45,
        'date_time_answer' => '2023-01-01 10:30:00',
        'custom_field_id' => 5,
        'company_id' => 1,
        'defaultAnswer' => Carbon::parse('2023-01-01 10:30:00'), // Use Carbon instance for defaultAnswer
    ];

    // Mock the underlying model that the JsonResource wraps
    $resourceModel = Mockery::mock(Model::class)->makePartial();
    foreach ($mockData as $key => $value) {
        $resourceModel->{$key} = $value;
    }

    // Mock customField() relationship
    $mockCustomFieldRelation = Mockery::mock(BelongsTo::class);
    $mockCustomFieldRelation->shouldReceive('exists')->andReturn(true)->once();
    $resourceModel->shouldReceive('customField')->andReturn($mockCustomFieldRelation)->once();
    $resourceModel->customField = $customFieldModel; // Set the actual related model property

    // Mock company() relationship
    $mockCompanyRelation = Mockery::mock(BelongsTo::class);
    $mockCompanyRelation->shouldReceive('exists')->andReturn(true)->once();
    $resourceModel->shouldReceive('company')->andReturn($mockCompanyRelation)->once();
    $resourceModel->company = $companyModel; // Set the actual related model property

    // Mock the dependent resources (CustomFieldResource, CompanyResource) to ensure they are instantiated
    // using alias mocks to intercept constructor calls without needing real instances.
    Mockery::mock('alias:' . CustomFieldResource::class)
        ->shouldReceive('__construct')
        ->with($customFieldModel)
        ->andReturnSelf()
        ->once();

    Mockery::mock('alias:' . CompanyResource::class)
        ->shouldReceive('__construct')
        ->with($companyModel)
        ->andReturnSelf()
        ->once();

    // Prepare the global mock for 'getCustomFieldValueKey' which will be called by dateTimeFormat()
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('datetime')->andReturn('date_time_answer')->once();

    $resource = new CustomFieldValueResource($resourceModel);
    $request = Mockery::mock(Request::class); // The request parameter is not used in toArray logic

    // Act
    $result = $resource->toArray($request);

    // Assert
    expect($result)->toBeArray()
        ->toHaveKeys([
            'id', 'custom_field_valuable_type', 'custom_field_valuable_id', 'type',
            'boolean_answer', 'date_answer', 'time_answer', 'string_answer', 'number_answer',
            'date_time_answer', 'custom_field_id', 'company_id', 'default_answer',
            'default_formatted_answer', 'custom_field', 'company',
        ]);

    expect($result['id'])->toBe($mockData->id);
    expect($result['custom_field_valuable_type'])->toBe($mockData->custom_field_valuable_type);
    expect($result['custom_field_valuable_id'])->toBe($mockData->custom_field_valuable_id);
    expect($result['type'])->toBe($mockData->type);
    expect($result['boolean_answer'])->toBe($mockData->boolean_answer);
    expect($result['date_answer'])->toBe($mockData->date_answer);
    expect($result['time_answer'])->toBe($mockData->time_answer);
    expect($result['string_answer'])->toBe($mockData->string_answer);
    expect($result['number_answer'])->toBe($mockData->number_answer);
    expect($result['date_time_answer'])->toBe($mockData->date_time_answer);
    expect($result['custom_field_id'])->toBe($mockData->custom_field_id);
    expect($result['company_id'])->toBe($mockData->company_id);
    expect($result['default_answer'])->toBe($mockData->defaultAnswer);
    expect($result['default_formatted_answer'])->toBe('2023-01-01 10:30'); // Expected output from dateTimeFormat

    // Verify dependent resources were instantiated
    expect($result['custom_field'])->toBeInstanceOf(CustomFieldResource::class);
    expect($result['company'])->toBeInstanceOf(CompanyResource::class);
});

test('toArray transforms resource with non-existing relationships and null values for optional fields', function () {
    // Arrange
    $mockData = (object)[
        'id' => 2,
        'custom_field_valuable_type' => 'App\\Models\\Order',
        'custom_field_valuable_id' => 20,
        'type' => 'text', // Type set to 'text' to trigger dateTimeFormat returning the raw string
        'boolean_answer' => null,
        'date_answer' => null,
        'time_answer' => null,
        'string_answer' => null,
        'number_answer' => null,
        'date_time_answer' => null,
        'custom_field_id' => 6,
        'company_id' => 2,
        'defaultAnswer' => 'Some default string value', // String value for defaultAnswer
    ];

    $resourceModel = Mockery::mock(Model::class)->makePartial();
    foreach ($mockData as $key => $value) {
        $resourceModel->{$key} = $value;
    }

    // Mock customField() relationship to not exist
    $mockCustomFieldRelation = Mockery::mock(BelongsTo::class);
    $mockCustomFieldRelation->shouldReceive('exists')->andReturn(false)->once();
    $resourceModel->shouldReceive('customField')->andReturn($mockCustomFieldRelation)->once();
    $resourceModel->customField = null; // No related model

    // Mock company() relationship to not exist
    $mockCompanyRelation = Mockery::mock(BelongsTo::class);
    $mockCompanyRelation->shouldReceive('exists')->andReturn(false)->once();
    $resourceModel->shouldReceive('company')->andReturn($mockCompanyRelation)->once();
    $resourceModel->company = null; // No related model

    // Ensure CustomFieldResource and CompanyResource are NOT instantiated
    Mockery::mock('alias:' . CustomFieldResource::class)
        ->shouldNotReceive('__construct');
    Mockery::mock('alias:' . CompanyResource::class)
        ->shouldNotReceive('__construct');

    // Prepare the global mock for 'getCustomFieldValueKey' for dateTimeFormat()
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('text')->andReturn('string_answer')->once();

    $resource = new CustomFieldValueResource($resourceModel);
    $request = Mockery::mock(Request::class);

    // Act
    $result = $resource->toArray($request);

    // Assert
    expect($result)->toBeArray()
        ->not->toHaveKeys(['custom_field', 'company']); // custom_field and company should not be present

    expect($result['id'])->toBe($mockData->id);
    expect($result['custom_field_valuable_type'])->toBe($mockData->custom_field_valuable_type);
    expect($result['boolean_answer'])->toBeNull();
    expect($result['date_answer'])->toBeNull();
    expect($result['time_answer'])->toBeNull();
    expect($result['string_answer'])->toBeNull();
    expect($result['number_answer'])->toBeNull();
    expect($result['date_time_answer'])->toBeNull();
    expect($result['default_answer'])->toBe($mockData->defaultAnswer);
    expect($result['default_formatted_answer'])->toBe('Some default string value'); // Expected raw string from dateTimeFormat
});

test('dateTimeFormat returns null when default_answer is null', function () {
    // Arrange
    $resourceModel = (object)['default_answer' => null, 'type' => 'date', 'company_id' => 1];
    $resource = new CustomFieldValueResource($resourceModel);

    // If default_answer is null, getCustomFieldValueKey should not be called
    $this->globalGetCustomFieldValueKey->shouldNotReceive('__invoke');

    // Act
    $result = $resource->dateTimeFormat();

    // Assert
    expect($result)->toBeNull();
});

test('dateTimeFormat formats date_time_answer correctly for datetime type', function () {
    // Arrange
    $now = Carbon::parse('2023-10-26 14:35:10');
    $resourceModel = (object)['default_answer' => $now, 'type' => 'datetime', 'company_id' => 1];
    $resource = new CustomFieldValueResource($resourceModel);

    // Mock the global function to return 'date_time_answer' for 'datetime' type
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('datetime')->andReturn('date_time_answer')->once();

    // Act
    $result = $resource->dateTimeFormat();

    // Assert
    expect($result)->toBe('2023-10-26 14:35');
});

test('dateTimeFormat formats date_answer correctly for date type using company setting', function () {
    // Arrange
    $now = Carbon::parse('2023-10-26 14:35:10');
    $dateFormat = 'd/m/Y';
    $companyId = 1;
    $resourceModel = (object)['default_answer' => $now, 'type' => 'date', 'company_id' => $companyId];
    $resource = new CustomFieldValueResource($resourceModel);

    // Mock the global function to return 'date_answer' for 'date' type
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('date')->andReturn('date_answer')->once();

    // Mock CompanySetting static method
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat)
        ->once();

    // Act
    $result = $resource->dateTimeFormat();

    // Assert
    expect($result)->toBe($now->format($dateFormat)); // Expect '26/10/2023'
});

test('dateTimeFormat returns default_answer for other types (string, number, boolean)', function () {
    // Arrange - Test with a string type
    $stringValue = 'Just a string value';
    $resourceModel1 = (object)['default_answer' => $stringValue, 'type' => 'text', 'company_id' => 1];
    $resource1 = new CustomFieldValueResource($resourceModel1);
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('text')->andReturn('string_answer')->once();

    // Act
    $result1 = $resource1->dateTimeFormat();

    // Assert
    expect($result1)->toBe($stringValue);

    // Arrange - Test with a numeric type
    $numberValue = 123.45;
    $resourceModel2 = (object)['default_answer' => $numberValue, 'type' => 'number', 'company_id' => 1];
    $resource2 = new CustomFieldValueResource($resourceModel2);
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('number')->andReturn('number_answer')->once();

    // Act
    $result2 = $resource2->dateTimeFormat();

    // Assert
    expect($result2)->toBe($numberValue);

    // Arrange - Test with a boolean type
    $booleanValue = true;
    $resourceModel3 = (object)['default_answer' => $booleanValue, 'type' => 'boolean', 'company_id' => 1];
    $resource3 = new CustomFieldValueResource($resourceModel3);
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('boolean')->andReturn('boolean_answer')->once();

    // Act
    $result3 = $resource3->dateTimeFormat();

    // Assert
    expect($result3)->toBe($booleanValue);
});

afterEach(function () {
    Mockery::close(); // Close Mockery to clean up mocks after each test
});
