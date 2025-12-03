<?php

use function Pest\Laravel\{mock};
use Crater\Models\Estimate;
use Crater\Models\User;
use Crater\Policies\EstimatePolicy;
use Silber\Bouncer\BouncerFacade;

beforeEach(function () {
    Mockery::close(); // Clean up Mockery mocks after each test
});

test('viewAny returns true when user has permission to view any estimates', function () {
    // Arrange
    $user = Mockery::mock(User::class);
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('view-estimate', Estimate::class)
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->viewAny($user);

    // Assert
    expect($result)->toBeTrue();
});

test('viewAny returns false when user does not have permission to view any estimates', function () {
    // Arrange
    $user = Mockery::mock(User::class);
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('view-estimate', Estimate::class)
        ->andReturn(false);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->viewAny($user);

    // Assert
    expect($result)->toBeFalse();
});

test('view returns true when user has permission and belongs to the estimate\'s company', function () {
    // Arrange
    $companyId = 123;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('view-estimate', $estimate)
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->view($user, $estimate);

    // Assert
    expect($result)->toBeTrue();
});

test('view returns false when user does not have permission to view the estimate', function () {
    // Arrange
    $companyId = 123;
    
    $user = Mockery::mock(User::class);
    $user->shouldNotReceive('hasCompany'); // Should not be called due to short-circuiting

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('view-estimate', $estimate)
        ->andReturn(false);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->view($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('view returns false when user does not belong to the estimate\'s company', function () {
    // Arrange
    $companyId = 123;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false); // User does not belong to the company

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('view-estimate', $estimate)
        ->andReturn(true); // User has permission

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->view($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('create returns true when user has permission to create estimates', function () {
    // Arrange
    $user = Mockery::mock(User::class);
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('create-estimate', Estimate::class)
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->create($user);

    // Assert
    expect($result)->toBeTrue();
});

test('create returns false when user does not have permission to create estimates', function () {
    // Arrange
    $user = Mockery::mock(User::class);
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('create-estimate', Estimate::class)
        ->andReturn(false);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->create($user);

    // Assert
    expect($result)->toBeFalse();
});

test('update returns true when user has permission and belongs to the estimate\'s company', function () {
    // Arrange
    $companyId = 456;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('edit-estimate', $estimate)
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->update($user, $estimate);

    // Assert
    expect($result)->toBeTrue();
});

test('update returns false when user does not have permission to update the estimate', function () {
    // Arrange
    $companyId = 456;
    
    $user = Mockery::mock(User::class);
    $user->shouldNotReceive('hasCompany'); // Should not be called due to short-circuiting

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('edit-estimate', $estimate)
        ->andReturn(false);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->update($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('update returns false when user does not belong to the estimate\'s company', function () {
    // Arrange
    $companyId = 456;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false); // User does not belong to the company

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('edit-estimate', $estimate)
        ->andReturn(true); // User has permission

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->update($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('delete returns true when user has permission and belongs to the estimate\'s company', function () {
    // Arrange
    $companyId = 789;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-estimate', $estimate)
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->delete($user, $estimate);

    // Assert
    expect($result)->toBeTrue();
});

test('delete returns false when user does not have permission to delete the estimate', function () {
    // Arrange
    $companyId = 789;
    
    $user = Mockery::mock(User::class);
    $user->shouldNotReceive('hasCompany'); // Should not be called due to short-circuiting

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-estimate', $estimate)
        ->andReturn(false);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->delete($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('delete returns false when user does not belong to the estimate\'s company', function () {
    // Arrange
    $companyId = 789;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false); // User does not belong to the company

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-estimate', $estimate)
        ->andReturn(true); // User has permission

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->delete($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('restore returns true when user has permission and belongs to the estimate\'s company', function () {
    // Arrange
    $companyId = 101;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-estimate', $estimate) // Policy uses 'delete-estimate' for restore
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->restore($user, $estimate);

    // Assert
    expect($result)->toBeTrue();
});

test('restore returns false when user does not have permission to restore the estimate', function () {
    // Arrange
    $companyId = 101;
    
    $user = Mockery::mock(User::class);
    $user->shouldNotReceive('hasCompany');

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-estimate', $estimate)
        ->andReturn(false);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->restore($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('restore returns false when user does not belong to the estimate\'s company', function () {
    // Arrange
    $companyId = 101;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-estimate', $estimate)
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->restore($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('forceDelete returns true when user has permission and belongs to the estimate\'s company', function () {
    // Arrange
    $companyId = 202;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-estimate', $estimate) // Policy uses 'delete-estimate' for forceDelete
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->forceDelete($user, $estimate);

    // Assert
    expect($result)->toBeTrue();
});

test('forceDelete returns false when user does not have permission to force delete the estimate', function () {
    // Arrange
    $companyId = 202;
    
    $user = Mockery::mock(User::class);
    $user->shouldNotReceive('hasCompany');

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-estimate', $estimate)
        ->andReturn(false);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->forceDelete($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('forceDelete returns false when user does not belong to the estimate\'s company', function () {
    // Arrange
    $companyId = 202;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-estimate', $estimate)
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->forceDelete($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('send returns true when user has permission and belongs to the estimate\'s company', function () {
    // Arrange
    $companyId = 303;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(true);

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('send-estimate', $estimate)
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->send($user, $estimate);

    // Assert
    expect($result)->toBeTrue();
});

test('send returns false when user does not have permission to send the estimate', function () {
    // Arrange
    $companyId = 303;
    
    $user = Mockery::mock(User::class);
    $user->shouldNotReceive('hasCompany');

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('send-estimate', $estimate)
        ->andReturn(false);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->send($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('send returns false when user does not belong to the estimate\'s company', function () {
    // Arrange
    $companyId = 303;
    
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasCompany')
        ->once()
        ->with($companyId)
        ->andReturn(false);

    $estimate = Mockery::mock(Estimate::class);
    $estimate->company_id = $companyId;
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('send-estimate', $estimate)
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->send($user, $estimate);

    // Assert
    expect($result)->toBeFalse();
});

test('deleteMultiple returns true when user has permission to delete multiple estimates', function () {
    // Arrange
    $user = Mockery::mock(User::class);
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-estimate', Estimate::class)
        ->andReturn(true);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->deleteMultiple($user);

    // Assert
    expect($result)->toBeTrue();
});

test('deleteMultiple returns false when user does not have permission to delete multiple estimates', function () {
    // Arrange
    $user = Mockery::mock(User::class);
    
    mock(BouncerFacade::class)
        ->shouldReceive('can')
        ->once()
        ->with('delete-estimate', Estimate::class)
        ->andReturn(false);

    $policy = new EstimatePolicy();

    // Act
    $result = $policy->deleteMultiple($user);

    // Assert
    expect($result)->toBeFalse();
});




afterEach(function () {
    Mockery::close();
});
