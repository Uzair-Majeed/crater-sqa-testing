<?php
use Crater\Models\Company;
use Crater\Models\User;
use Crater\Policies\CompanyPolicy;
use Mockery as m;

test('create method allows creation if the user is an owner', function () {
        // Arrange
        $user = m::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(true);

        $policy = new CompanyPolicy();

        // Act
        $result = $policy->create($user);

        // Assert
        expect($result)->toBeTrue();
    });

    test('create method denies creation if the user is not an owner', function () {
        // Arrange
        $user = m::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(false);

        $policy = new CompanyPolicy();

        // Act
        $result = $policy->create($user);

        // Assert
        expect($result)->toBeFalse();
    });

    // Test cases for the delete method
    test('delete method allows deletion if the user is the company owner', function () {
        // Arrange
        $user = m::mock(User::class);
        $user->id = 1;

        $company = m::mock(Company::class);
        $company->owner_id = 1;

        $policy = new CompanyPolicy();

        // Act
        $result = $policy->delete($user, $company);

        // Assert
        expect($result)->toBeTrue();
    });

    test('delete method denies deletion if the user is not the company owner', function () {
        // Arrange
        $user = m::mock(User::class);
        $user->id = 1;

        $company = m::mock(Company::class);
        $company->owner_id = 2; // Mismatched owner ID

        $policy = new CompanyPolicy();

        // Act
        $result = $policy->delete($user, $company);

        // Assert
        expect($result)->toBeFalse();
    });

    // Test cases for the transferOwnership method
    test('transferOwnership method allows transfer if the user is the company owner', function () {
        // Arrange
        $user = m::mock(User::class);
        $user->id = 1;

        $company = m::mock(Company::class);
        $company->owner_id = 1;

        $policy = new CompanyPolicy();

        // Act
        $result = $policy->transferOwnership($user, $company);

        // Assert
        expect($result)->toBeTrue();
    });

    test('transferOwnership method denies transfer if the user is not the company owner', function () {
        // Arrange
        $user = m::mock(User::class);
        $user->id = 1;

        $company = m::mock(Company::class);
        $company->owner_id = 2; // Mismatched owner ID

        $policy = new CompanyPolicy();

        // Act
        $result = $policy->transferOwnership($user, $company);

        // Assert
        expect($result)->toBeFalse();
    });

afterEach(function () {
    m::close(); // Clean up Mockery expectations after each test
});
 

