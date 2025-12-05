<?php

use Crater\Http\Requests\CustomerEstimateStatusRequest;
use Illuminate\Support\Facades\Validator;

// Test that authorize method returns true
test('authorize method returns true', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $this->assertTrue($request->authorize());
});

// Test that rules method returns correct validation rules structure
test('rules method returns correct validation rules for status', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $rules = $request->rules();
    
    // Assert that 'status' key exists
    $this->assertArrayHasKey('status', $rules);
    
    $statusRules = $rules['status'];
    
    // Assert that 'status' rules is an array
    $this->assertIsArray($statusRules);
    
    // Assert 'required' rule exists
    $this->assertContains('required', $statusRules);
    
    // Check for the string representation of the 'in' rule
    $stringInRuleFound = false;
    foreach ($statusRules as $rule) {
        if ($rule === 'in:ACCEPTED,REJECTED') {
            $stringInRuleFound = true;
            break;
        }
    }
    $this->assertTrue($stringInRuleFound, "Rules should contain string 'in:ACCEPTED,REJECTED'");
});

// Test that rules method returns only the expected keys
test('rules method returns only the expected keys', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $rules = $request->rules();
    
    $this->assertCount(1, $rules); // Only 'status' key should be present
    $this->assertArrayHasKey('status', $rules);
});

// Test validation passes with valid 'ACCEPTED' status
test('validation passes with ACCEPTED status', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = ['status' => 'ACCEPTED'];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertFalse($validator->fails());
    $this->assertTrue($validator->passes());
});

// Test validation passes with valid 'REJECTED' status
test('validation passes with REJECTED status', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = ['status' => 'REJECTED'];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertFalse($validator->fails());
    $this->assertTrue($validator->passes());
});

// Test validation fails when status is missing
test('validation fails when status is missing', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = [];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('status', $validator->errors()->toArray());
});

// Test validation fails with invalid status value
test('validation fails with invalid status value', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = ['status' => 'PENDING'];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('status', $validator->errors()->toArray());
});

// Test validation fails with empty string status
test('validation fails with empty string status', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = ['status' => ''];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('status', $validator->errors()->toArray());
});

// Test validation fails with null status
test('validation fails with null status', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = ['status' => null];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('status', $validator->errors()->toArray());
});

// Test validation fails with numeric status
test('validation fails with numeric status', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = ['status' => 123];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('status', $validator->errors()->toArray());
});

// Test validation fails with lowercase accepted
test('validation fails with lowercase accepted', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = ['status' => 'accepted'];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('status', $validator->errors()->toArray());
});

// Test validation fails with mixed case rejected
test('validation fails with mixed case rejected', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = ['status' => 'Rejected'];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('status', $validator->errors()->toArray());
});

// Test that extra fields don't affect validation
test('validation passes with extra fields when status is valid', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = [
        'status' => 'ACCEPTED',
        'extra_field' => 'some_value',
        'another_field' => 123
    ];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertFalse($validator->fails());
    $this->assertTrue($validator->passes());
});

// Test validation error message for missing status
test('validation error message for missing status is correct', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = [];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertTrue($validator->fails());
    $errors = $validator->errors();
    $this->assertTrue($errors->has('status'));
    
    // Check that error message contains 'required'
    $statusErrors = $errors->get('status');
    $this->assertNotEmpty($statusErrors);
});

// Test validation error message for invalid status value
test('validation error message for invalid status value is correct', function () {
    $request = new CustomerEstimateStatusRequest();
    
    $data = ['status' => 'INVALID'];
    
    $validator = Validator::make($data, $request->rules());
    
    $this->assertTrue($validator->fails());
    $errors = $validator->errors();
    $this->assertTrue($errors->has('status'));
    
    $statusErrors = $errors->get('status');
    $this->assertNotEmpty($statusErrors);
});