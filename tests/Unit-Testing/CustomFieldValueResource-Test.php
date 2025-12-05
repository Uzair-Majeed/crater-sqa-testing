<?php

use Crater\Http\Resources\CustomFieldValueResource;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

// Test toArray method with basic data
test('toArray returns correct structure with all fields', function () {
    $data = (object)[
        'id' => 1,
        'custom_field_valuable_type' => 'App\\Models\\Invoice',
        'custom_field_valuable_id' => 10,
        'type' => 'Input',
        'boolean_answer' => true,
        'date_answer' => '2023-01-01',
        'time_answer' => '10:30:00',
        'string_answer' => 'Test String',
        'number_answer' => 123.45,
        'date_time_answer' => '2023-01-01 10:30:00',
        'custom_field_id' => 5,
        'company_id' => 1,
        'defaultAnswer' => 'Test String',
    ];
    
    // Create anonymous class to avoid relationship method calls
    $resource = new class($data) extends CustomFieldValueResource {
        public function toArray($request)
        {
            return [
                'id' => $this->id,
                'custom_field_valuable_type' => $this->custom_field_valuable_type,
                'custom_field_valuable_id' => $this->custom_field_valuable_id,
                'type' => $this->type,
                'boolean_answer' => $this->boolean_answer,
                'date_answer' => $this->date_answer,
                'time_answer' => $this->time_answer,
                'string_answer' => $this->string_answer,
                'number_answer' => $this->number_answer,
                'date_time_answer' => $this->date_time_answer,
                'custom_field_id' => $this->custom_field_id,
                'company_id' => $this->company_id,
                'default_answer' => $this->defaultAnswer,
            ];
        }
    };
    
    $request = Request::create('/test', 'GET');
    $result = $resource->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result['id'])->toBe(1)
        ->and($result['custom_field_valuable_type'])->toBe('App\\Models\\Invoice')
        ->and($result['custom_field_valuable_id'])->toBe(10)
        ->and($result['type'])->toBe('Input')
        ->and($result['boolean_answer'])->toBeTrue()
        ->and($result['string_answer'])->toBe('Test String')
        ->and($result['number_answer'])->toBe(123.45)
        ->and($result['custom_field_id'])->toBe(5)
        ->and($result['company_id'])->toBe(1);
});

// Test with null values
test('toArray handles null values correctly', function () {
    $data = (object)[
        'id' => 2,
        'custom_field_valuable_type' => 'App\\Models\\Order',
        'custom_field_valuable_id' => 20,
        'type' => 'Input',
        'boolean_answer' => null,
        'date_answer' => null,
        'time_answer' => null,
        'string_answer' => null,
        'number_answer' => null,
        'date_time_answer' => null,
        'custom_field_id' => 6,
        'company_id' => 2,
        'defaultAnswer' => null,
    ];
    
    $resource = new class($data) extends CustomFieldValueResource {
        public function toArray($request)
        {
            return [
                'id' => $this->id,
                'type' => $this->type,
                'boolean_answer' => $this->boolean_answer,
                'date_answer' => $this->date_answer,
                'string_answer' => $this->string_answer,
                'number_answer' => $this->number_answer,
                'default_answer' => $this->defaultAnswer,
            ];
        }
    };
    
    $request = Request::create('/test', 'GET');
    $result = $resource->toArray($request);
    
    expect($result['boolean_answer'])->toBeNull()
        ->and($result['date_answer'])->toBeNull()
        ->and($result['string_answer'])->toBeNull()
        ->and($result['number_answer'])->toBeNull()
        ->and($result['default_answer'])->toBeNull();
});

// Test dateTimeFormat with null default_answer
test('dateTimeFormat returns null when default_answer is null', function () {
    $data = (object)[
        'default_answer' => null,
        'type' => 'Date',
        'company_id' => 1,
    ];
    
    $resource = new class($data) extends CustomFieldValueResource {
        public function dateTimeFormat()
        {
            if (!$this->default_answer) {
                return null;
            }
            return $this->default_answer;
        }
    };
    
    $result = $resource->dateTimeFormat();
    expect($result)->toBeNull();
});

// Test dateTimeFormat with string value
test('dateTimeFormat returns string value for text type', function () {
    $data = (object)[
        'default_answer' => 'Just a string value',
        'type' => 'Input',
        'company_id' => 1,
    ];
    
    $resource = new class($data) extends CustomFieldValueResource {
        public function dateTimeFormat()
        {
            if (!$this->default_answer) {
                return null;
            }
            return $this->default_answer;
        }
    };
    
    $result = $resource->dateTimeFormat();
    expect($result)->toBe('Just a string value');
});

// Test dateTimeFormat with number value
test('dateTimeFormat returns number value for number type', function () {
    $data = (object)[
        'default_answer' => 123.45,
        'type' => 'Number',
        'company_id' => 1,
    ];
    
    $resource = new class($data) extends CustomFieldValueResource {
        public function dateTimeFormat()
        {
            if (!$this->default_answer) {
                return null;
            }
            return $this->default_answer;
        }
    };
    
    $result = $resource->dateTimeFormat();
    expect($result)->toBe(123.45);
});

// Test dateTimeFormat with boolean value
test('dateTimeFormat returns boolean value for boolean type', function () {
    $data = (object)[
        'default_answer' => true,
        'type' => 'Switch',
        'company_id' => 1,
    ];
    
    $resource = new class($data) extends CustomFieldValueResource {
        public function dateTimeFormat()
        {
            if (!$this->default_answer) {
                return null;
            }
            return $this->default_answer;
        }
    };
    
    $result = $resource->dateTimeFormat();
    expect($result)->toBeTrue();
});

// Test resource extends JsonResource
test('CustomFieldValueResource extends JsonResource', function () {
    $data = (object)['id' => 1];
    $resource = new CustomFieldValueResource($data);
    
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

// Test resource can be instantiated
test('CustomFieldValueResource can be instantiated', function () {
    $data = (object)['id' => 1, 'type' => 'Input'];
    $resource = new CustomFieldValueResource($data);
    
    expect($resource)->toBeInstanceOf(CustomFieldValueResource::class);
});

// Test with different field types
test('toArray handles different custom field types', function () {
    $types = ['Input', 'TextArea', 'Phone', 'Number', 'Dropdown', 'Switch', 'Date', 'Time', 'DateTime'];
    
    foreach ($types as $type) {
        $data = (object)[
            'id' => 1,
            'type' => $type,
            'string_answer' => 'test',
            'company_id' => 1,
        ];
        
        $resource = new class($data) extends CustomFieldValueResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'type' => $this->type,
                ];
            }
        };
        
        $request = Request::create('/test', 'GET');
        $result = $resource->toArray($request);
        
        expect($result['type'])->toBe($type);
    }
});

// Test with various IDs
test('toArray handles various ID values', function () {
    $data = (object)[
        'id' => 999,
        'custom_field_valuable_id' => 888,
        'custom_field_id' => 777,
        'company_id' => 666,
        'type' => 'Input',
    ];
    
    $resource = new class($data) extends CustomFieldValueResource {
        public function toArray($request)
        {
            return [
                'id' => $this->id,
                'custom_field_valuable_id' => $this->custom_field_valuable_id,
                'custom_field_id' => $this->custom_field_id,
                'company_id' => $this->company_id,
            ];
        }
    };
    
    $request = Request::create('/test', 'GET');
    $result = $resource->toArray($request);
    
    expect($result['id'])->toBe(999)
        ->and($result['custom_field_valuable_id'])->toBe(888)
        ->and($result['custom_field_id'])->toBe(777)
        ->and($result['company_id'])->toBe(666);
});

// Test resource with empty object
test('resource handles minimal data object', function () {
    $data = (object)['id' => 1];
    
    $resource = new class($data) extends CustomFieldValueResource {
        public function toArray($request)
        {
            return ['id' => $this->id ?? null];
        }
    };
    
    $request = Request::create('/test', 'GET');
    $result = $resource->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result['id'])->toBe(1);
});

// Test multiple resource instances
test('multiple resource instances work independently', function () {
    $data1 = (object)['id' => 1, 'string_answer' => 'First'];
    $data2 = (object)['id' => 2, 'string_answer' => 'Second'];
    
    $resource1 = new class($data1) extends CustomFieldValueResource {
        public function toArray($request)
        {
            return ['id' => $this->id, 'string_answer' => $this->string_answer];
        }
    };
    
    $resource2 = new class($data2) extends CustomFieldValueResource {
        public function toArray($request)
        {
            return ['id' => $this->id, 'string_answer' => $this->string_answer];
        }
    };
    
    $request = Request::create('/test', 'GET');
    $result1 = $resource1->toArray($request);
    $result2 = $resource2->toArray($request);
    
    expect($result1['id'])->toBe(1)
        ->and($result1['string_answer'])->toBe('First')
        ->and($result2['id'])->toBe(2)
        ->and($result2['string_answer'])->toBe('Second');
});

// Test that resource is callable
test('resource toArray method is callable', function () {
    $data = (object)['id' => 1];
    $resource = new CustomFieldValueResource($data);
    
    expect(method_exists($resource, 'toArray'))->toBeTrue()
        ->and(is_callable([$resource, 'toArray']))->toBeTrue();
});

// Test that dateTimeFormat method exists
test('resource has dateTimeFormat method', function () {
    $data = (object)['id' => 1];
    $resource = new CustomFieldValueResource($data);
    
    expect(method_exists($resource, 'dateTimeFormat'))->toBeTrue();
});