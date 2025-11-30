<?php
uses(Tests\TestCase::class);
use Crater\Http\Requests\CustomerEstimateStatusRequest;
use Illuminate\Validation\Rule;

test('authorize method returns true', function () {
    $request = new CustomerEstimateStatusRequest();

    $this->assertTrue($request->authorize());
});

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

    // Assert 'in' rule for specific values exists
    $this->assertContains(Rule::in(['ACCEPTED', 'REJECTED']), $statusRules);
    // Alternatively, checking the string representation if Rule::in isn't used (though it is for cleaner code)
    $this->assertContains('in:ACCEPTED,REJECTED', $statusRules);
});

test('rules method returns only the expected keys', function () {
    $request = new CustomerEstimateStatusRequest();

    $rules = $request->rules();

    $this->assertCount(1, $rules); // Only 'status' key should be present
    $this->assertArrayHasKey('status', $rules);
});
