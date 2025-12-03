<?php

use Silber\Bouncer\BouncerFacade;
use Crater\Models\User;
use Crater\Models\Customer;
use Crater\Policies\CustomerPolicy;

// Define simple mocks for the models required by the policy
// These extend their respective base classes to be compatible with Laravel's type hinting.
class TestCustomer extends \Illuminate\Database\Eloquent\Model {}

beforeEach(function () {
    // Ensure Mockery expectations are cleared before each test
    Mockery::close();
});

test('viewAny returns true when user can view any customer', function () {
    // Arrange
    $user = new User();

    // Mock BouncerFacade to return true for 'view-customer' on Customer class
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('view-customer', Customer::class)
        ->andReturn(true);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->viewAny($user);

    // Assert
    expect($result)->toBeTrue();
});

test('viewAny returns false when user cannot view any customer', function () {
    // Arrange
    $user = new User();

    // Mock BouncerFacade to return false for 'view-customer' on Customer class
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('view-customer', Customer::class)
        ->andReturn(false);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->viewAny($user);

    // Assert
    expect($result)->toBeFalse();
});

test('view returns true when user can view a specific customer', function () {
    // Arrange
    $user = new User();
    $customer = new TestCustomer(); // Specific customer instance

    // Mock BouncerFacade to return true for 'view-customer' on the specific customer instance
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('view-customer', $customer)
        ->andReturn(true);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->view($user, $customer);

    // Assert
    expect($result)->toBeTrue();
});

test('view returns false when user cannot view a specific customer', function () {
    // Arrange
    $user = new User();
    $customer = new TestCustomer(); // Specific customer instance

    // Mock BouncerFacade to return false for 'view-customer' on the specific customer instance
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('view-customer', $customer)
        ->andReturn(false);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->view($user, $customer);

    // Assert
    expect($result)->toBeFalse();
});

test('create returns true when user can create customers', function () {
    // Arrange
    $user = new User();

    // Mock BouncerFacade to return true for 'create-customer' on Customer class
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('create-customer', Customer::class)
        ->andReturn(true);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->create($user);

    // Assert
    expect($result)->toBeTrue();
});

test('create returns false when user cannot create customers', function () {
    // Arrange
    $user = new User();

    // Mock BouncerFacade to return false for 'create-customer' on Customer class
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('create-customer', Customer::class)
        ->andReturn(false);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->create($user);

    // Assert
    expect($result)->toBeFalse();
});

test('update returns true when user can update a specific customer', function () {
    // Arrange
    $user = new User();
    $customer = new TestCustomer(); // Specific customer instance

    // Mock BouncerFacade to return true for 'edit-customer' on the specific customer instance
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('edit-customer', $customer)
        ->andReturn(true);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->update($user, $customer);

    // Assert
    expect($result)->toBeTrue();
});

test('update returns false when user cannot update a specific customer', function () {
    // Arrange
    $user = new User();
    $customer = new TestCustomer(); // Specific customer instance

    // Mock BouncerFacade to return false for 'edit-customer' on the specific customer instance
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('edit-customer', $customer)
        ->andReturn(false);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->update($user, $customer);

    // Assert
    expect($result)->toBeFalse();
});

test('delete returns true when user can delete a specific customer', function () {
    // Arrange
    $user = new User();
    $customer = new TestCustomer(); // Specific customer instance

    // Mock BouncerFacade to return true for 'delete-customer' on the specific customer instance
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-customer', $customer)
        ->andReturn(true);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->delete($user, $customer);

    // Assert
    expect($result)->toBeTrue();
});

test('delete returns false when user cannot delete a specific customer', function () {
    // Arrange
    $user = new User();
    $customer = new TestCustomer(); // Specific customer instance

    // Mock BouncerFacade to return false for 'delete-customer' on the specific customer instance
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-customer', $customer)
        ->andReturn(false);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->delete($user, $customer);

    // Assert
    expect($result)->toBeFalse();
});

test('restore returns true when user can restore a specific customer', function () {
    // Arrange
    $user = new User();
    $customer = new TestCustomer(); // Specific customer instance

    // As per the original policy logic, 'delete-customer' capability is checked for restore.
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-customer', $customer)
        ->andReturn(true);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->restore($user, $customer);

    // Assert
    expect($result)->toBeTrue();
});

test('restore returns false when user cannot restore a specific customer', function () {
    // Arrange
    $user = new User();
    $customer = new TestCustomer(); // Specific customer instance

    // As per the original policy logic, 'delete-customer' capability is checked for restore.
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-customer', $customer)
        ->andReturn(false);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->restore($user, $customer);

    // Assert
    expect($result)->toBeFalse();
});

test('forceDelete returns true when user can permanently delete a specific customer', function () {
    // Arrange
    $user = new User();
    $customer = new TestCustomer(); // Specific customer instance

    // As per the original policy logic, 'delete-customer' capability is checked for forceDelete.
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-customer', $customer)
        ->andReturn(true);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->forceDelete($user, $customer);

    // Assert
    expect($result)->toBeTrue();
});

test('forceDelete returns false when user cannot permanently delete a specific customer', function () {
    // Arrange
    $user = new User();
    $customer = new TestCustomer(); // Specific customer instance

    // As per the original policy logic, 'delete-customer' capability is checked for forceDelete.
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-customer', $customer)
        ->andReturn(false);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->forceDelete($user, $customer);

    // Assert
    expect($result)->toBeFalse();
});

test('deleteMultiple returns true when user can delete multiple customers', function () {
    // Arrange
    $user = new User();

    // Mock BouncerFacade to return true for 'delete-customer' on Customer class
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-customer', Customer::class)
        ->andReturn(true);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->deleteMultiple($user);

    // Assert
    expect($result)->toBeTrue();
});

test('deleteMultiple returns false when user cannot delete multiple customers', function () {
    // Arrange
    $user = new User();

    // Mock BouncerFacade to return false for 'delete-customer' on Customer class
    Mockery::mock('alias:'.BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-customer', Customer::class)
        ->andReturn(false);

    $policy = new CustomerPolicy();

    // Act
    $result = $policy->deleteMultiple($user);

    // Assert
    expect($result)->toBeFalse();
});

afterEach(function () {
    Mockery::close();
});