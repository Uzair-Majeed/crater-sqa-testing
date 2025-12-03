<?php
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

    // Assert 'in' rule for specific values exists, checking by type rather than object instance
    $inRuleFound = false;
    foreach ($statusRules as $rule) {
        // If Rule::in is used, check for instance of Rule
        if (($rule instanceof \Illuminate\Validation\Rules\In)) {
            $inRuleFound = $rule->values === ['ACCEPTED', 'REJECTED'];
            if ($inRuleFound) {
                break;
            }
        }
    }
    $this->assertTrue($inRuleFound, "Rules should contain Illuminate\Validation\Rules\In with specified values");

    // Alternatively, checking the string representation if Rule::in isn't used
    $stringInRuleFound = false;
    foreach ($statusRules as $rule) {
        if ($rule === 'in:ACCEPTED,REJECTED') {
            $stringInRuleFound = true;
            break;
        }
    }
    $this->assertTrue($stringInRuleFound, "Rules should contain string 'in:ACCEPTED,REJECTED'");
});

test('rules method returns only the expected keys', function () {
    $request = new CustomerEstimateStatusRequest();

    $rules = $request->rules();

    $this->assertCount(1, $rules); // Only 'status' key should be present
    $this->assertArrayHasKey('status', $rules);
});

afterEach(function () {
    Mockery::close();
});