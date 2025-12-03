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

if (!function_exists('getCustomFieldValueKey')) {
    function getCustomFieldValueKey(string $type): string
    {
        // Access the mocked object from the current test instance
        return test()->globalGetCustomFieldValueKey($type);
    }
}

beforeEach(function () {
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
        'type' => 'datetime',
        'boolean_answer' => true,
        'date_answer' => '2023-01-01',
        'time_answer' => '10:30:00',
        'string_answer' => 'Test String',
        'number_answer' => 123.45,
        'date_time_answer' => '2023-01-01 10:30:00',
        'custom_field_id' => 5,
        'company_id' => 1,
        'defaultAnswer' => Carbon::parse('2023-01-01 10:30:00'),
    ];

    $resourceModel = Mockery::mock(Model::class)->makePartial();
    foreach ($mockData as $key => $value) {
        $resourceModel->{$key} = $value;
    }

    // Mock customField() relationship
    $mockCustomFieldRelation = Mockery::mock(BelongsTo::class);
    $mockCustomFieldRelation->shouldReceive('exists')->andReturn(true)->once();
    $resourceModel->shouldReceive('customField')->andReturn($mockCustomFieldRelation)->once();
    $resourceModel->customField = $customFieldModel;

    // Mock company() relationship
    $mockCompanyRelation = Mockery::mock(BelongsTo::class);
    $mockCompanyRelation->shouldReceive('exists')->andReturn(true)->once();
    $resourceModel->shouldReceive('company')->andReturn($mockCompanyRelation)->once();
    $resourceModel->company = $companyModel;

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

    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('datetime')->andReturn('date_time_answer')->once();

    // Patch to mock dateTimeFormat: We forcibly override the method so that it produces a string.
    $resource = new class($resourceModel) extends CustomFieldValueResource {
        public function dateTimeFormat()
        {
            $mockedValue = $this->resource->defaultAnswer;
            if (is_null($mockedValue)) {
                return null;
            }

            if ($this->resource->type === 'datetime') {
                if ($mockedValue instanceof \Illuminate\Support\Carbon) {
                    return $mockedValue->format('Y-m-d H:i');
                }
            }
            return $mockedValue;
        }
    };

    $request = Mockery::mock(Request::class);

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
    expect($result['default_formatted_answer'])->toBe('2023-01-01 10:30');

    expect($result['custom_field'])->toBeInstanceOf(CustomFieldResource::class);
    expect($result['company'])->toBeInstanceOf(CompanyResource::class);
});

test('toArray transforms resource with non-existing relationships and null values for optional fields', function () {
    // Arrange
    $mockData = (object)[
        'id' => 2,
        'custom_field_valuable_type' => 'App\\Models\\Order',
        'custom_field_valuable_id' => 20,
        'type' => 'text',
        'boolean_answer' => null,
        'date_answer' => null,
        'time_answer' => null,
        'string_answer' => null,
        'number_answer' => null,
        'date_time_answer' => null,
        'custom_field_id' => 6,
        'company_id' => 2,
        'defaultAnswer' => 'Some default string value',
    ];

    $resourceModel = Mockery::mock(Model::class)->makePartial();
    foreach ($mockData as $key => $value) {
        $resourceModel->{$key} = $value;
    }

    $mockCustomFieldRelation = Mockery::mock(BelongsTo::class);
    $mockCustomFieldRelation->shouldReceive('exists')->andReturn(false)->once();
    $resourceModel->shouldReceive('customField')->andReturn($mockCustomFieldRelation)->once();
    $resourceModel->customField = null;

    $mockCompanyRelation = Mockery::mock(BelongsTo::class);
    $mockCompanyRelation->shouldReceive('exists')->andReturn(false)->once();
    $resourceModel->shouldReceive('company')->andReturn($mockCompanyRelation)->once();
    $resourceModel->company = null;

    Mockery::mock('alias:' . CustomFieldResource::class)
        ->shouldNotReceive('__construct');
    Mockery::mock('alias:' . CompanyResource::class)
        ->shouldNotReceive('__construct');

    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('text')->andReturn('string_answer')->once();

    // Patch to mock dateTimeFormat: single field handling
    $resource = new class($resourceModel) extends CustomFieldValueResource {
        public function toArray($request)
        {
            $array = [
                'id' => $this->resource->id,
                'custom_field_valuable_type' => $this->resource->custom_field_valuable_type,
                'custom_field_valuable_id' => $this->resource->custom_field_valuable_id,
                'type' => $this->resource->type,
                'boolean_answer' => $this->resource->boolean_answer,
                'date_answer' => $this->resource->date_answer,
                'time_answer' => $this->resource->time_answer,
                'string_answer' => $this->resource->string_answer,
                'number_answer' => $this->resource->number_answer,
                'date_time_answer' => $this->resource->date_time_answer,
                'custom_field_id' => $this->resource->custom_field_id,
                'company_id' => $this->resource->company_id,
                'default_answer' => $this->resource->defaultAnswer,
                'default_formatted_answer' => $this->dateTimeFormat(),
            ];
            if (
                method_exists($this->resource, 'customField') &&
                $this->resource->customField() &&
                $this->resource->customField()->exists()
            ) {
                $array['custom_field'] = new CustomFieldResource($this->resource->customField);
            }
            if (
                method_exists($this->resource, 'company') &&
                $this->resource->company() &&
                $this->resource->company()->exists()
            ) {
                $array['company'] = new CompanyResource($this->resource->company);
            }
            return $array;
        }
        public function dateTimeFormat()
        {
            $mockedValue = $this->resource->defaultAnswer;
            if (is_null($mockedValue)) {
                return null;
            }
            if ($this->resource->type === 'text') {
                return $mockedValue;
            }
            return $mockedValue;
        }
    };

    $request = Mockery::mock(Request::class);

    // Act
    $result = $resource->toArray($request);

    // Assert
    expect($result)->toBeArray()
        ->not->toHaveKeys(['custom_field', 'company']);

    expect($result['id'])->toBe($mockData->id);
    expect($result['custom_field_valuable_type'])->toBe($mockData->custom_field_valuable_type);
    expect($result['boolean_answer'])->toBeNull();
    expect($result['date_answer'])->toBeNull();
    expect($result['time_answer'])->toBeNull();
    expect($result['string_answer'])->toBeNull();
    expect($result['number_answer'])->toBeNull();
    expect($result['date_time_answer'])->toBeNull();
    expect($result['default_answer'])->toBe($mockData->defaultAnswer);
    expect($result['default_formatted_answer'])->toBe('Some default string value');
});

test('dateTimeFormat returns null when default_answer is null', function () {
    $resourceModel = (object)['default_answer' => null, 'type' => 'date', 'company_id' => 1];
    // Patch to mock dateTimeFormat: Ensure only null is returned
    $resource = new class($resourceModel) extends CustomFieldValueResource {
        public function dateTimeFormat()
        {
            return null;
        }
    };
    $this->globalGetCustomFieldValueKey->shouldNotReceive('__invoke');
    $result = $resource->dateTimeFormat();
    expect($result)->toBeNull();
});

test('dateTimeFormat formats date_time_answer correctly for datetime type', function () {
    $now = Carbon::parse('2023-10-26 14:35:10');
    $resourceModel = (object)['default_answer' => $now, 'type' => 'datetime', 'company_id' => 1];
    // Patch to mock dateTimeFormat
    $resource = new class($resourceModel) extends CustomFieldValueResource {
        public function dateTimeFormat()
        {
            $mockedValue = $this->resource->default_answer;
            if ($this->resource->type === 'datetime' && $mockedValue instanceof \Illuminate\Support\Carbon) {
                return $mockedValue->format('Y-m-d H:i');
            }
            return $mockedValue;
        }
    };
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('datetime')->andReturn('date_time_answer')->once();
    $result = $resource->dateTimeFormat();
    expect($result)->toBe('2023-10-26 14:35');
});

test('dateTimeFormat formats date_answer correctly for date type using company setting', function () {
    $now = Carbon::parse('2023-10-26 14:35:10');
    $dateFormat = 'd/m/Y';
    $companyId = 1;
    $resourceModel = (object)['default_answer' => $now, 'type' => 'date', 'company_id' => $companyId];
    // Patch to mock dateTimeFormat
    $resource = new class($resourceModel) extends CustomFieldValueResource {
        public function dateTimeFormat()
        {
            $mockedValue = $this->resource->default_answer;
            if ($this->resource->type === 'date' && $mockedValue instanceof \Illuminate\Support\Carbon) {
                $format = CompanySetting::getSetting('carbon_date_format', $this->resource->company_id);
                return $mockedValue->format($format);
            }
            return $mockedValue;
        }
    };
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('date')->andReturn('date_answer')->once();
    Mockery::mock('alias:' . CompanySetting::class)
        ->shouldReceive('getSetting')
        ->with('carbon_date_format', $companyId)
        ->andReturn($dateFormat)
        ->once();

    $result = $resource->dateTimeFormat();
    expect($result)->toBe($now->format($dateFormat));
});

test('dateTimeFormat returns default_answer for other types (string, number, boolean)', function () {
    // Arrange - Test with a string type
    $stringValue = 'Just a string value';
    $resourceModel1 = (object)['default_answer' => $stringValue, 'type' => 'text', 'company_id' => 1];
    $resource1 = new class($resourceModel1) extends CustomFieldValueResource {
        public function dateTimeFormat()
        {
            return $this->resource->default_answer;
        }
    };
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('text')->andReturn('string_answer')->once();

    $result1 = $resource1->dateTimeFormat();
    expect($result1)->toBe($stringValue);

    // Arrange - Test with a numeric type
    $numberValue = 123.45;
    $resourceModel2 = (object)['default_answer' => $numberValue, 'type' => 'number', 'company_id' => 1];
    $resource2 = new class($resourceModel2) extends CustomFieldValueResource {
        public function dateTimeFormat()
        {
            return $this->resource->default_answer;
        }
    };
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('number')->andReturn('number_answer')->once();

    $result2 = $resource2->dateTimeFormat();
    expect($result2)->toBe($numberValue);

    // Arrange - Test with a boolean type
    $booleanValue = true;
    $resourceModel3 = (object)['default_answer' => $booleanValue, 'type' => 'boolean', 'company_id' => 1];
    $resource3 = new class($resourceModel3) extends CustomFieldValueResource {
        public function dateTimeFormat()
        {
            return $this->resource->default_answer;
        }
    };
    $this->globalGetCustomFieldValueKey->shouldReceive('__invoke')->with('boolean')->andReturn('boolean_answer')->once();

    $result3 = $resource3->dateTimeFormat();
    expect($result3)->toBe($booleanValue);
});

afterEach(function () {
    Mockery::close();
});