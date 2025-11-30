<?php

use Crater\Policies\OwnerPolicy;
use Crater\Models\User;
uses(\Mockery::class);

test('managedByOwner returns true if the user is an owner', function () {
    // Arrange
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(true);

    $policy = new OwnerPolicy();

    // Act
    $result = $policy->managedByOwner($user);

    // Assert
    expect($result)->toBeTrue();

    // Verify mocks
    Mockery::close();
});

test('managedByOwner returns false if the user is not an owner', function () {
    // Arrange
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);

    $policy = new OwnerPolicy();

    // Act
    $result = $policy->managedByOwner($user);

    // Assert
    expect($result)->toBeFalse();

    // Verify mocks
    Mockery::close();
});
