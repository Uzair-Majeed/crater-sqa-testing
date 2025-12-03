<?php

use Crater\Policies\ModulesPolicy;
use Crater\Models\User;

test('manageModules returns true for an owner user', function () {
    // Create a mock User instance
    $user = Mockery::mock(User::class);
    // Configure the isOwner() method to return true
    $user->shouldReceive('isOwner')->andReturn(true);

    // Instantiate the policy
    $policy = new ModulesPolicy();

    // Call the method under test
    $result = $policy->manageModules($user);

    // Assert the expected result
    expect($result)->toBeTrue();

    // Verify interactions with the mock
    $user->shouldHaveReceived('isOwner')->once();
});

test('manageModules returns false for a non-owner user', function () {
    // Create a mock User instance
    $user = Mockery::mock(User::class);
    // Configure the isOwner() method to return false
    $user->shouldReceive('isOwner')->andReturn(false);

    // Instantiate the policy
    $policy = new ModulesPolicy();

    // Call the method under test
    $result = $policy->manageModules($user);

    // Assert the expected result
    expect($result)->toBeFalse();

    // Verify interactions with the mock
    $user->shouldHaveReceived('isOwner')->once();
});




afterEach(function () {
    Mockery::close();
});
