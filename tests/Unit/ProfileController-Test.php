<?php

use Crater\Http\Controllers\V1\Customer\General\ProfileController;
use Crater\Http\Requests\Customer\CustomerProfileRequest;
use Crater\Http\Resources\Customer\CustomerResource;
use Crater\Models\Company;
use Crater\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// The beforeEach hook causing "Could not load mock Illuminate\Support\Facades\Auth, class already exists"
// should be removed. Laravel's facade testing helpers (which Pest integrates with) automatically
// handle the mocking of facades like Auth when Auth::shouldReceive() is used directly within tests.
// Mockery::mock('alias:...') is primarily for mocking classes that are NOT facades or that need
// to be replaced *before* they are loaded, which isn't the case for a repeatedly loaded facade.
// Removing this line will fix the primary error.


test('getUser returns a customer resource for the authenticated customer', function () {
    // Arrange
    $mockCustomer = Mockery::mock(Customer::class);
    // Minimal attribute for resource transformation if needed, ensure it doesn't cause unexpected issues.
    // Sometimes resources try to access attributes that aren't directly mocked.
    // For a simple ID access, getAttribute might be called.
    $mockCustomer->shouldReceive('getAttribute')->with('id')->andReturn(1)->byDefault();
    $mockCustomer->shouldReceive('exists')->andReturn(true)->byDefault(); // Often checked by resources

    Auth::shouldReceive('guard->user')
        ->once()
        ->with('customer')
        ->andReturn($mockCustomer);

    $request = Mockery::mock(Request::class); // Request is a parameter but not used in getUser logic

    $controller = new ProfileController();

    // Act
    $resource = $controller->getUser($request);

    // Assert
    expect($resource)->toBeInstanceOf(CustomerResource::class);
    expect($resource->resource)->toBe($mockCustomer); // Ensure the correct customer model is wrapped
});

test('getUser returns a customer resource with null customer if Auth user is null', function () {
    // Arrange
    Auth::shouldReceive('guard->user')
        ->once()
        ->with('customer')
        ->andReturn(null);

    $request = Mockery::mock(Request::class);

    $controller = new ProfileController();

    // Act
    $resource = $controller->getUser($request);

    // Assert
    expect($resource)->toBeInstanceOf(CustomerResource::class);
    expect($resource->resource)->toBeNull();
});

test('updateProfile updates customer data without avatar or address changes', function () {
    // Arrange
    $validatedData = ['name' => 'New Name', 'email' => 'new@example.com'];

    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('update')
        ->once()
        ->with($validatedData)
        ->andReturn(true); // update method usually returns boolean
    $mockCustomer->shouldNotReceive('clearMediaCollection');
    $mockCustomer->shouldNotReceive('addMediaFromRequest');
    // Ensure addresses relationship calls are not made
    $mockCustomer->shouldNotReceive('shippingAddress');
    $mockCustomer->shouldNotReceive('billingAddress');
    $mockCustomer->shouldNotReceive('addresses');

    Auth::shouldReceive('guard->user')
        ->once()
        ->with('customer')
        ->andReturn($mockCustomer);

    $mockCompany = Mockery::mock(Company::class);

    $mockRequest = Mockery::mock(CustomerProfileRequest::class);
    $mockRequest->shouldReceive('validated')->andReturn($validatedData);
    $mockRequest->shouldReceive('hasFile')->with('customer_avatar')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('is_customer_avatar_removed')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('is_customer_avatar_removed')->andReturn(false); // Should be false, not null if exists is true but value is false
    $mockRequest->shouldReceive('offsetExists')->with('billing')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('shipping')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(null);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(null);

    $controller = new ProfileController();

    // Act
    $resource = $controller->updateProfile($mockCompany, $mockRequest);

    // Assert
    expect($resource)->toBeInstanceOf(CustomerResource::class);
    expect($resource->resource)->toBe($mockCustomer);
});

test('updateProfile removes customer avatar when requested', function () {
    // Arrange
    $validatedData = ['name' => 'John Doe'];

    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('update')->once()->with($validatedData)->andReturn(true);
    $mockCustomer->shouldReceive('clearMediaCollection')->once()->with('customer_avatar');
    $mockCustomer->shouldNotReceive('addMediaFromRequest');
    $mockCustomer->shouldNotReceive('shippingAddress');
    $mockCustomer->shouldNotReceive('billingAddress');
    $mockCustomer->shouldNotReceive('addresses');

    Auth::shouldReceive('guard->user')
        ->once()
        ->with('customer')
        ->andReturn($mockCustomer);

    $mockCompany = Mockery::mock(Company::class);

    $mockRequest = Mockery::mock(CustomerProfileRequest::class);
    $mockRequest->shouldReceive('validated')->andReturn($validatedData);
    $mockRequest->shouldReceive('offsetExists')->with('is_customer_avatar_removed')->andReturn(true);
    $mockRequest->shouldReceive('offsetGet')->with('is_customer_avatar_removed')->andReturn(true);
    $mockRequest->shouldReceive('hasFile')->with('customer_avatar')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('billing')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('shipping')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(null);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(null);

    $controller = new ProfileController();

    // Act
    $resource = $controller->updateProfile($mockCompany, $mockRequest);

    // Assert
    expect($resource)->toBeInstanceOf(CustomerResource::class);
    expect($resource->resource)->toBe($mockCustomer);
});

test('updateProfile uploads new customer avatar when requested', function () {
    // Arrange
    $validatedData = ['name' => 'Jane Doe'];

    $mockMediaAdder = Mockery::mock();
    $mockMediaAdder->shouldReceive('toMediaCollection')
        ->once()
        ->with('customer_avatar');

    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('update')->once()->with($validatedData)->andReturn(true);
    $mockCustomer->shouldReceive('clearMediaCollection')->once()->with('customer_avatar'); // Cleared before upload is common
    $mockCustomer->shouldReceive('addMediaFromRequest')->once()->with('customer_avatar')->andReturn($mockMediaAdder);
    $mockCustomer->shouldNotReceive('shippingAddress');
    $mockCustomer->shouldNotReceive('billingAddress');
    $mockCustomer->shouldNotReceive('addresses');

    Auth::shouldReceive('guard->user')
        ->once()
        ->with('customer')
        ->andReturn($mockCustomer);

    $mockCompany = Mockery::mock(Company::class);

    $mockRequest = Mockery::mock(CustomerProfileRequest::class);
    $mockRequest->shouldReceive('validated')->andReturn($validatedData);
    $mockRequest->shouldReceive('offsetExists')->with('is_customer_avatar_removed')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('is_customer_avatar_removed')->andReturn(false);
    $mockRequest->shouldReceive('hasFile')->with('customer_avatar')->andReturn(true);
    $mockRequest->shouldReceive('offsetExists')->with('billing')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('shipping')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(null);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(null);

    $controller = new ProfileController();

    // Act
    $resource = $controller->updateProfile($mockCompany, $mockRequest);

    // Assert
    expect($resource)->toBeInstanceOf(CustomerResource::class);
    expect($resource->resource)->toBe($mockCustomer);
});

test('updateProfile handles both avatar removal and new upload, prioritizing upload', function () {
    // Arrange
    $validatedData = ['name' => 'Alice'];

    $mockMediaAdder = Mockery::mock();
    $mockMediaAdder->shouldReceive('toMediaCollection')->once()->with('customer_avatar');

    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('update')->once()->with($validatedData)->andReturn(true);
    // clearMediaCollection will be called twice: once for explicit removal, once for upload cleanup if logic dictates
    // Assuming the controller logic clears if 'is_customer_avatar_removed' is true, AND clears before adding new media.
    // Adjust if actual production code clears only once or with different conditions.
    $mockCustomer->shouldReceive('clearMediaCollection')->times(2)->with('customer_avatar');
    $mockCustomer->shouldReceive('addMediaFromRequest')->once()->with('customer_avatar')->andReturn($mockMediaAdder);
    $mockCustomer->shouldNotReceive('shippingAddress');
    $mockCustomer->shouldNotReceive('billingAddress');
    $mockCustomer->shouldNotReceive('addresses');

    Auth::shouldReceive('guard->user')
        ->once()
        ->with('customer')
        ->andReturn($mockCustomer);

    $mockCompany = Mockery::mock(Company::class);

    $mockRequest = Mockery::mock(CustomerProfileRequest::class);
    $mockRequest->shouldReceive('validated')->andReturn($validatedData);
    $mockRequest->shouldReceive('offsetExists')->with('is_customer_avatar_removed')->andReturn(true);
    $mockRequest->shouldReceive('offsetGet')->with('is_customer_avatar_removed')->andReturn(true);
    $mockRequest->shouldReceive('hasFile')->with('customer_avatar')->andReturn(true);
    $mockRequest->shouldReceive('offsetExists')->with('billing')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('shipping')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(null);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(null);

    $controller = new ProfileController();

    // Act
    $resource = $controller->updateProfile($mockCompany, $mockRequest);

    // Assert
    expect($resource)->toBeInstanceOf(CustomerResource::class);
    expect($resource->resource)->toBe($mockCustomer);
});

test('updateProfile updates billing address when provided', function () {
    // Arrange
    $validatedData = ['name' => 'Bob'];
    $billingAddressData = ['street' => '123 Main St', 'type' => 'billing']; // Corrected to billing

    $mockAddressModel = Mockery::mock('StdClass'); // A generic mock for an existing address model
    $mockAddressModel->shouldReceive('delete')->once()->andReturn(true); // Mock deletion of old address

    $mockAddressesRelationship = Mockery::mock();
    $mockAddressesRelationship->shouldReceive('create')->once()->with($billingAddressData)->andReturn(Mockery::mock('Crater\Models\Address'));

    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('update')->once()->with($validatedData)->andReturn(true);
    $mockCustomer->shouldNotReceive('clearMediaCollection');
    $mockCustomer->shouldNotReceive('addMediaFromRequest');
    $mockCustomer->shouldNotReceive('shippingAddress'); // No shipping address changes in this test
    $mockCustomer->shouldReceive('billingAddress')->once()->andReturn($mockAddressModel); // Access existing billing address
    $mockCustomer->shouldReceive('addresses')->andReturn($mockAddressesRelationship); // Access addresses relationship to create new

    Auth::shouldReceive('guard->user')
        ->once()
        ->with('customer')
        ->andReturn($mockCustomer);

    $mockCompany = Mockery::mock(Company::class);

    $mockRequest = Mockery::mock(CustomerProfileRequest::class);
    $mockRequest->shouldReceive('validated')->andReturn($validatedData);
    $mockRequest->shouldReceive('offsetExists')->with('is_customer_avatar_removed')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('is_customer_avatar_removed')->andReturn(false);
    $mockRequest->shouldReceive('hasFile')->with('customer_avatar')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('billing')->andReturn(true);
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(['address_line_1' => 'old_billing']);
    $mockRequest->shouldReceive('getBillingAddress')->once()->andReturn($billingAddressData); // Get billing address data
    $mockRequest->shouldReceive('offsetExists')->with('shipping')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(null);

    $controller = new ProfileController();

    // Act
    $resource = $controller->updateProfile($mockCompany, $mockRequest);

    // Assert
    expect($resource)->toBeInstanceOf(CustomerResource::class);
    expect($resource->resource)->toBe($mockCustomer);
});

test('updateProfile updates shipping address when provided', function () {
    // Arrange
    $validatedData = ['name' => 'Charlie'];
    $shippingAddressData = ['street' => '456 Oak Ave', 'type' => 'shipping']; // Corrected to shipping

    $mockAddressModel = Mockery::mock('StdClass'); // A generic mock for an existing address model
    $mockAddressModel->shouldReceive('delete')->once()->andReturn(true);

    $mockAddressesRelationship = Mockery::mock();
    $mockAddressesRelationship->shouldReceive('create')->once()->with($shippingAddressData)->andReturn(Mockery::mock('Crater\Models\Address'));

    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('update')->once()->with($validatedData)->andReturn(true);
    $mockCustomer->shouldNotReceive('clearMediaCollection');
    $mockCustomer->shouldNotReceive('addMediaFromRequest');
    $mockCustomer->shouldReceive('shippingAddress')->once()->andReturn($mockAddressModel); // Access existing shipping address
    $mockCustomer->shouldNotReceive('billingAddress'); // No billing address changes in this test
    $mockCustomer->shouldReceive('addresses')->andReturn($mockAddressesRelationship); // Access addresses relationship to create new

    Auth::shouldReceive('guard->user')
        ->once()
        ->with('customer')
        ->andReturn($mockCustomer);

    $mockCompany = Mockery::mock(Company::class);

    $mockRequest = Mockery::mock(CustomerProfileRequest::class);
    $mockRequest->shouldReceive('validated')->andReturn($validatedData);
    $mockRequest->shouldReceive('offsetExists')->with('is_customer_avatar_removed')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('is_customer_avatar_removed')->andReturn(false);
    $mockRequest->shouldReceive('hasFile')->with('customer_avatar')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('billing')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(null);
    $mockRequest->shouldReceive('offsetExists')->with('shipping')->andReturn(true);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(['address_line_1' => 'old_shipping']);
    $mockRequest->shouldReceive('getShippingAddress')->once()->andReturn($shippingAddressData); // Get shipping address data

    $controller = new ProfileController();

    // Act
    $resource = $controller->updateProfile($mockCompany, $mockRequest);

    // Assert
    expect($resource)->toBeInstanceOf(CustomerResource::class);
    expect($resource->resource)->toBe($mockCustomer);
});

test('updateProfile updates both billing and shipping addresses when provided', function () {
    // Arrange
    $validatedData = ['name' => 'Diana'];
    $shippingAddressData = ['street' => '789 Pine Ln', 'type' => 'shipping'];
    $billingAddressData = ['street' => '321 Elm Rd', 'type' => 'billing'];

    $mockAddressModelForShipping = Mockery::mock('StdClass');
    $mockAddressModelForShipping->shouldReceive('delete')->once()->andReturn(true);
    $mockAddressModelForBilling = Mockery::mock('StdClass');
    $mockAddressModelForBilling->shouldReceive('delete')->once()->andReturn(true);

    $mockAddressesRelationship = Mockery::mock();
    $mockAddressesRelationship->shouldReceive('create')->once()->with($shippingAddressData)->andReturn(Mockery::mock('Crater\Models\Address'));
    $mockAddressesRelationship->shouldReceive('create')->once()->with($billingAddressData)->andReturn(Mockery::mock('Crater\Models\Address'));


    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('update')->once()->with($validatedData)->andReturn(true);
    $mockCustomer->shouldNotReceive('clearMediaCollection');
    $mockCustomer->shouldNotReceive('addMediaFromRequest');
    $mockCustomer->shouldReceive('shippingAddress')->once()->andReturn($mockAddressModelForShipping);
    $mockCustomer->shouldReceive('billingAddress')->once()->andReturn($mockAddressModelForBilling);
    $mockCustomer->shouldReceive('addresses')->times(2)->andReturn($mockAddressesRelationship); // Called twice for create

    Auth::shouldReceive('guard->user')
        ->once()
        ->with('customer')
        ->andReturn($mockCustomer);

    $mockCompany = Mockery::mock(Company::class);

    $mockRequest = Mockery::mock(CustomerProfileRequest::class);
    $mockRequest->shouldReceive('validated')->andReturn($validatedData);
    $mockRequest->shouldReceive('offsetExists')->with('is_customer_avatar_removed')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('is_customer_avatar_removed')->andReturn(false);
    $mockRequest->shouldReceive('hasFile')->with('customer_avatar')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('billing')->andReturn(true);
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(['address_line_1' => 'old_billing']);
    $mockRequest->shouldReceive('getShippingAddress')->once()->andReturn($shippingAddressData);
    $mockRequest->shouldReceive('offsetExists')->with('shipping')->andReturn(true);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(['address_line_1' => 'old_shipping']);
    $mockRequest->shouldReceive('getBillingAddress')->once()->andReturn($billingAddressData);

    $controller = new ProfileController();

    // Act
    $resource = $controller->updateProfile($mockCompany, $mockRequest);

    // Assert
    expect($resource)->toBeInstanceOf(CustomerResource::class);
    expect($resource->resource)->toBe($mockCustomer);
});

test('updateProfile does not remove avatar if is_customer_avatar_removed is false', function () {
    // Arrange
    $validatedData = ['name' => 'Eve'];

    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('update')->once()->with($validatedData)->andReturn(true);
    $mockCustomer->shouldNotReceive('clearMediaCollection');
    $mockCustomer->shouldNotReceive('addMediaFromRequest');
    $mockCustomer->shouldNotReceive('shippingAddress');
    $mockCustomer->shouldNotReceive('billingAddress');
    $mockCustomer->shouldNotReceive('addresses');

    Auth::shouldReceive('guard->user')
        ->once()
        ->with('customer')
        ->andReturn($mockCustomer);

    $mockCompany = Mockery::mock(Company::class);

    $mockRequest = Mockery::mock(CustomerProfileRequest::class);
    $mockRequest->shouldReceive('validated')->andReturn($validatedData);
    $mockRequest->shouldReceive('offsetExists')->with('is_customer_avatar_removed')->andReturn(true); // Exists but false value
    $mockRequest->shouldReceive('offsetGet')->with('is_customer_avatar_removed')->andReturn(false); // Value is false
    $mockRequest->shouldReceive('hasFile')->with('customer_avatar')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('billing')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('shipping')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('billing')->andReturn(null);
    $mockRequest->shouldReceive('offsetGet')->with('shipping')->andReturn(null);

    $controller = new ProfileController();

    // Act
    $resource = $controller->updateProfile($mockCompany, $mockRequest);

    // Assert
    expect($resource)->toBeInstanceOf(CustomerResource::class);
    expect($resource->resource)->toBe($mockCustomer);
});

test('updateProfile throws an error if Auth user is null when update is attempted', function () {
    // Arrange
    Auth::shouldReceive('guard->user')
        ->once()
        ->with('customer')
        ->andReturn(null);

    $mockCompany = Mockery::mock(Company::class);
    $mockRequest = Mockery::mock(CustomerProfileRequest::class);
    $mockRequest->shouldReceive('validated')->andReturn([]);
    // Add mocks for hasFile and offsetExists/offsetGet to prevent unexpected calls if null user is handled later
    $mockRequest->shouldReceive('hasFile')->andReturn(false)->byDefault();
    $mockRequest->shouldReceive('offsetExists')->andReturn(false)->byDefault();
    $mockRequest->shouldReceive('offsetGet')->andReturn(null)->byDefault();

    $controller = new ProfileController();

    // Act & Assert
    $this->expectException(Error::class);
    $this->expectExceptionMessage('Call to a member function update() on null');

    $controller->updateProfile($mockCompany, $mockRequest);
});

afterEach(function () {
    Mockery::close();
});
