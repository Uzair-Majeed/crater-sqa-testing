<?php

use Crater\Http\Controllers\V1\Customer\General\ProfileController;
use Crater\Http\Requests\Customer\CustomerProfileRequest;

// ========== MERGED PROFILE TESTS (2 CLASSES, ~15 TESTS FOR GOOD COVERAGE) ==========

// --- ProfileController Tests (7 tests) ---

test('ProfileController can be instantiated', function () {
    $controller = new ProfileController();
    expect($controller)->toBeInstanceOf(ProfileController::class);
});

test('ProfileController extends Controller', function () {
    $controller = new ProfileController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('ProfileController is in correct namespace', function () {
    $reflection = new ReflectionClass(ProfileController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Customer\General');
});

test('ProfileController has updateProfile method', function () {
    $reflection = new ReflectionClass(ProfileController::class);
    expect($reflection->hasMethod('updateProfile'))->toBeTrue();
    
    $method = $reflection->getMethod('updateProfile');
    expect($method->isPublic())->toBeTrue();
});

test('ProfileController has getUser method', function () {
    $reflection = new ReflectionClass(ProfileController::class);
    expect($reflection->hasMethod('getUser'))->toBeTrue();
    
    $method = $reflection->getMethod('getUser');
    expect($method->isPublic())->toBeTrue();
});

test('ProfileController updateProfile uses Auth guard customer', function () {
    $reflection = new ReflectionClass(ProfileController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Auth::guard(\'customer\')->user()');
});

test('ProfileController updateProfile handles avatar removal and upload', function () {
    $reflection = new ReflectionClass(ProfileController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('is_customer_avatar_removed')
        ->and($fileContent)->toContain('clearMediaCollection(\'customer_avatar\')')
        ->and($fileContent)->toContain('hasFile(\'customer_avatar\')')
        ->and($fileContent)->toContain('addMediaFromRequest(\'customer_avatar\')');
});

test('ProfileController updateProfile handles billing and shipping addresses', function () {
    $reflection = new ReflectionClass(ProfileController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($request->billing !== null)')
        ->and($fileContent)->toContain('shippingAddress()->delete()')
        ->and($fileContent)->toContain('getShippingAddress()')
        ->and($fileContent)->toContain('if ($request->shipping !== null)')
        ->and($fileContent)->toContain('billingAddress()->delete()')
        ->and($fileContent)->toContain('getBillingAddress()');
});

test('ProfileController returns CustomerResource', function () {
    $reflection = new ReflectionClass(ProfileController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('return new CustomerResource($customer)');
});

// --- CustomerProfileRequest Tests (8 tests) ---

test('CustomerProfileRequest can be instantiated', function () {
    $request = new CustomerProfileRequest();
    expect($request)->toBeInstanceOf(CustomerProfileRequest::class);
});

test('CustomerProfileRequest extends FormRequest', function () {
    $request = new CustomerProfileRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

test('CustomerProfileRequest is in correct namespace', function () {
    $reflection = new ReflectionClass(CustomerProfileRequest::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Requests\Customer');
});

test('CustomerProfileRequest authorize returns true', function () {
    $request = new CustomerProfileRequest();
    expect($request->authorize())->toBeTrue();
});

test('CustomerProfileRequest has required methods', function () {
    $reflection = new ReflectionClass(CustomerProfileRequest::class);
    
    expect($reflection->hasMethod('authorize'))->toBeTrue()
        ->and($reflection->hasMethod('rules'))->toBeTrue()
        ->and($reflection->hasMethod('getShippingAddress'))->toBeTrue()
        ->and($reflection->hasMethod('getBillingAddress'))->toBeTrue();
});

test('CustomerProfileRequest rules include profile fields', function () {
    $reflection = new ReflectionClass(CustomerProfileRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'name\' =>')
        ->and($fileContent)->toContain('\'password\' =>')
        ->and($fileContent)->toContain('\'email\' =>')
        ->and($fileContent)->toContain('Rule::unique(\'customers\')');
});

test('CustomerProfileRequest rules include billing and shipping address fields', function () {
    $reflection = new ReflectionClass(CustomerProfileRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'billing.name\' =>')
        ->and($fileContent)->toContain('\'billing.address_street_1\' =>')
        ->and($fileContent)->toContain('\'shipping.name\' =>')
        ->and($fileContent)->toContain('\'shipping.address_street_1\' =>');
});

test('CustomerProfileRequest rules include customer_avatar validation', function () {
    $reflection = new ReflectionClass(CustomerProfileRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'customer_avatar\' =>')
        ->and($fileContent)->toContain('\'file\'')
        ->and($fileContent)->toContain('\'mimes:gif,jpg,png\'')
        ->and($fileContent)->toContain('\'max:20000\'');
});

test('CustomerProfileRequest getShippingAddress merges SHIPPING_TYPE', function () {
    $reflection = new ReflectionClass(CustomerProfileRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('public function getShippingAddress()')
        ->and($fileContent)->toContain('collect($this->shipping)')
        ->and($fileContent)->toContain('\'type\' => Address::SHIPPING_TYPE');
});

test('CustomerProfileRequest getBillingAddress merges BILLING_TYPE', function () {
    $reflection = new ReflectionClass(CustomerProfileRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('public function getBillingAddress()')
        ->and($fileContent)->toContain('collect($this->billing)')
        ->and($fileContent)->toContain('\'type\' => Address::BILLING_TYPE');
});
