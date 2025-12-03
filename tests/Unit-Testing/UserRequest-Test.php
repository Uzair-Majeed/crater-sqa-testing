<?php

use Crater\Http\Requests\UserRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Foundation\Auth\User as Authenticatable; // For type hinting if needed, but for mocks, a simple object is fine

// Helper function to extract a specific rule type from an array of rules
function extractRuleOfType(array $rules, string $type)
{
    return collect($rules)->first(fn ($rule) => $rule instanceof $type);
}

test('authorize method always returns true', function () {
    $request = new UserRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct validation rules for POST request (user creation)', function () {
    // Create an anonymous class extending UserRequest to mock the getMethod() behavior
    $request = new class extends UserRequest {
        public function getMethod()
        {
            return 'POST'; // Simulate a creation request
        }
    };

    $rules = $request->rules();

    // Assert all expected top-level keys are present
    expect($rules)->toHaveKeys(['name', 'email', 'phone', 'password', 'companies', 'companies.*.id', 'companies.*.role']);

    // Test 'name' rule
    expect($rules['name'])->toEqual(['required']);

    // Test 'email' rule for creation
    expect($rules['email'])->toContain('required', 'email');
    $uniqueRule = extractRuleOfType($rules['email'], Unique::class);
    expect($uniqueRule)
        ->toBeInstanceOf(Unique::class)
        ->and($uniqueRule->table)->toEqual('users')
        ->and($uniqueRule->column)->toEqual('email') // Default column for Unique rule
        ->and($uniqueRule->ignoreId)->toBeNull() // No ignore ID for creation
        ->and($uniqueRule->ignoreColumn)->toBeNull();

    // Test 'phone' rule
    expect($rules['phone'])->toEqual(['nullable']);

    // Test 'password' rule for creation
    expect($rules['password'])->toEqual(['required', 'min:8']);

    // Test 'companies' rule
    expect($rules['companies'])->toEqual(['required']);
    expect($rules['companies.*.id'])->toEqual(['required']);
    expect($rules['companies.*.role'])->toEqual(['required']);
});

test('rules method returns correct validation rules for PUT request (user update)', function () {
    $userIdToIgnore = 99; // Represents the ID of the user being updated ($this->user->id)

    // Create an anonymous class extending UserRequest to mock getMethod() and $this->user property
    $request = new class($userIdToIgnore) extends UserRequest {
        // Public property to simulate $this->user being set, e.g., by route model binding
        public $user;

        public function __construct($userId) {
            $this->user = (object)['id' => $userId];
            // Parent constructor for FormRequest doesn't need to be explicitly called
            // as we are controlling the necessary internal state directly.
        }

        public function getMethod()
        {
            return 'PUT'; // Simulate an update request
        }
    };

    $rules = $request->rules();

    // Assert all expected top-level keys are present
    expect($rules)->toHaveKeys(['name', 'email', 'phone', 'password', 'companies', 'companies.*.id', 'companies.*.role']);

    // Test 'name' rule - should be unchanged from creation
    expect($rules['name'])->toEqual(['required']);

    // Test 'email' rule for update - should include the ignore rule
    expect($rules['email'])->toContain('required', 'email');
    $uniqueRule = extractRuleOfType($rules['email'], Unique::class);
    expect($uniqueRule)
        ->toBeInstanceOf(Unique::class)
        ->and($uniqueRule->table)->toEqual('users')
        ->and($uniqueRule->column)->toEqual('email')
        ->and($uniqueRule->ignoreId)->toEqual($userIdToIgnore) // Check the ignore ID
        ->and($uniqueRule->ignoreColumn)->toEqual('id'); // Default ignore column is 'id' when ID is passed

    // Test 'phone' rule - should be unchanged
    expect($rules['phone'])->toEqual(['nullable']);

    // Test 'password' rule for update - should be nullable
    expect($rules['password'])->toEqual(['nullable', 'min:8']);

    // Companies rules should remain unchanged
    expect($rules['companies'])->toEqual(['required']);
    expect($rules['companies.*.id'])->toEqual(['required']);
    expect($rules['companies.*.role'])->toEqual(['required']);
});

test('getUserPayload method returns validated data merged with creator id', function () {
    $creatorId = 1;
    $validatedData = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '0987654321',
        'password' => 'newpassword',
        'companies' => [
            ['id' => 201, 'role' => 'editor'],
        ],
    ];

    // Create an anonymous class extending UserRequest to mock validated() and user() methods
    $request = new class($validatedData, (object)['id' => $creatorId]) extends UserRequest {
        private $mockValidatedData;
        private $mockUser;

        public function __construct(array $validatedData, $user)
        {
            $this->mockValidatedData = $validatedData;
            $this->mockUser = $user;
            // No need to call parent::__construct() as FormRequest's constructor
            // does not initialize state relevant to these mocked methods.
        }

        public function validated()
        {
            return $this->mockValidatedData;
        }

        public function user($guard = null)
        {
            return $this->mockUser;
        }
    };

    $payload = $request->getUserPayload();

    // Define the expected payload by merging validated data with creator_id
    $expectedPayload = array_merge($validatedData, ['creator_id' => $creatorId]);

    // Assert that the returned payload exactly matches the expected payload
    expect($payload)->toEqual($expectedPayload);
    // Assert that the return type is an array
    expect($payload)->toBeArray();
});




afterEach(function () {
    Mockery::close();
});
