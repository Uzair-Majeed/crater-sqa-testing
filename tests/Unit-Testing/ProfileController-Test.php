<?php

use Crater\Http\Controllers\V1\Customer\General\ProfileController;
use Crater\Http\Requests\Customer\CustomerProfileRequest;
use Crater\Http\Resources\Customer\CustomerResource;
use Crater\Models\Company;
use Crater\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
uses(\Mockery::class);

beforeEach(function () {
    Mockery::mock('alias:Illuminate\Support\Facades\Auth');
});

afterEach(function () {
    Mockery::close();
});

test('getUser returns a customer resource for the authenticated customer', function () {
    // Arrange
    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('getAttribute')->with('id')->andReturn(1); // Minimal attribute for resource transformation if needed

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
        ->with($validatedData);
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
    $mockRequest->shouldReceive('hasFile')->with('customer_avatar')->andReturn(false);
    $mockRequest->shouldReceive('offsetExists')->with('is_customer_avatar_removed')->andReturn(false);
    $mockRequest->shouldReceive('offsetGet')->with('is_customer_avatar_removed')->andReturn(null);
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
    $mockCustomer->shouldReceive('update')->once()->with($validatedData);
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
    $mockCustomer->shouldReceive('update')->once()->with($validatedData);
    $mockCustomer->shouldReceive('clearMediaCollection')->once()->with('customer_avatar'); // Cleared before upload
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
    $mockCustomer->shouldReceive('update')->once()->with($validatedData);
    // clearMediaCollection will be called twice: once for removal, once for upload cleanup
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
    $shippingAddressData = ['street' => '123 Main St', 'type' => 'shipping'];

    $mockAddressModel = Mockery::mock();
    $mockAddressModel->shouldReceive('delete')->once();

    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('update')->once()->with($validatedData);
    $mockCustomer->shouldNotReceive('clearMediaCollection');
    $mockCustomer->shouldNotReceive('addMediaFromRequest');
    $mockCustomer->shouldReceive('shippingAddress')->once()->andReturn($mockAddressModel);
    $mockCustomer->shouldReceive('addresses->create')->once()->with($shippingAddressData);
    $mockCustomer->shouldNotReceive('billingAddress');

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
    $billingAddressData = ['street' => '456 Oak Ave', 'type' => 'billing'];

    $mockAddressModel = Mockery::mock();
    $mockAddressModel->shouldReceive('delete')->once();

    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('update')->once()->with($validatedData);
    $mockCustomer->shouldNotReceive('clearMediaCollection');
    $mockCustomer->shouldNotReceive('addMediaFromRequest');
    $mockCustomer->shouldNotReceive('shippingAddress');
    $mockCustomer->shouldReceive('billingAddress')->once()->andReturn($mockAddressModel);
    $mockCustomer->shouldReceive('addresses->create')->once()->with($billingAddressData);

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
    $mockRequest->shouldReceive('getBillingAddress')->once()->andReturn($billingAddressData);

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

    $mockAddressModelForShipping = Mockery::mock();
    $mockAddressModelForShipping->shouldReceive('delete')->once();
    $mockAddressModelForBilling = Mockery::mock();
    $mockAddressModelForBilling->shouldReceive('delete')->once();

    $mockCustomer = Mockery::mock(Customer::class);
    $mockCustomer->shouldReceive('update')->once()->with($validatedData);
    $mockCustomer->shouldNotReceive('clearMediaCollection');
    $mockCustomer->shouldNotReceive('addMediaFromRequest');
    $mockCustomer->shouldReceive('shippingAddress')->once()->andReturn($mockAddressModelForShipping);
    $mockCustomer->shouldReceive('billingAddress')->once()->andReturn($mockAddressModelForBilling);
    $mockCustomer->shouldReceive('addresses->create')->once()->with($shippingAddressData);
    $mockCustomer->shouldReceive('addresses->create')->once()->with($billingAddressData);

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
    $mockCustomer->shouldReceive('update')->once()->with($validatedData);
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
    $mockRequest->shouldReceive('offsetExists')->with('is_customer_avatar_removed')->andReturn(true);
    $mockRequest->shouldReceive('offsetGet')->with('is_customer_avatar_removed')->andReturn(false);
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

    $controller = new ProfileController();

    // Act & Assert
    $this->expectException(Error::class);
    $this->expectExceptionMessage('Call to a member function update() on null');

    $controller->updateProfile($mockCompany, $mockRequest);
});
