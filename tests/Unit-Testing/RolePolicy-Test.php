<?php

use Crater\Policies\RolePolicy;
use Crater\Models\User;
use Silber\Bouncer\Database\Role;


// Test cases for viewAny method
test('viewAny returns true if user is an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(true);

    $policy = new RolePolicy();
    expect($policy->viewAny($user))->toBeTrue();
});

test('viewAny returns false if user is not an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);

    $policy = new RolePolicy();
    expect($policy->viewAny($user))->toBeFalse();
});

// Test cases for view method
test('view returns true if user is an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(true);
    $role = Mockery::mock(Role::class);

    $policy = new RolePolicy();
    expect($policy->view($user, $role))->toBeTrue();
});

test('view returns false if user is not an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);
    $role = Mockery::mock(Role::class);

    $policy = new RolePolicy();
    expect($policy->view($user, $role))->toBeFalse();
});

// Test cases for create method
test('create returns true if user is an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(true);

    $policy = new RolePolicy();
    expect($policy->create($user))->toBeTrue();
});

test('create returns false if user is not an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);

    $policy = new RolePolicy();
    expect($policy->create($user))->toBeFalse();
});

// Test cases for update method
test('update returns true if user is an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(true);
    $role = Mockery::mock(Role::class);

    $policy = new RolePolicy();
    expect($policy->update($user, $role))->toBeTrue();
});

test('update returns false if user is not an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);
    $role = Mockery::mock(Role::class);

    $policy = new RolePolicy();
    expect($policy->update($user, $role))->toBeFalse();
});

// Test cases for delete method
test('delete returns true if user is an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(true);
    $role = Mockery::mock(Role::class);

    $policy = new RolePolicy();
    expect($policy->delete($user, $role))->toBeTrue();
});

test('delete returns false if user is not an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);
    $role = Mockery::mock(Role::class);

    $policy = new RolePolicy();
    expect($policy->delete($user, $role))->toBeFalse();
});

// Test cases for restore method
test('restore returns true if user is an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(true);
    $role = Mockery::mock(Role::class);

    $policy = new RolePolicy();
    expect($policy->restore($user, $role))->toBeTrue();
});

test('restore returns false if user is not an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);
    $role = Mockery::mock(Role::class);

    $policy = new RolePolicy();
    expect($policy->restore($user, $role))->toBeFalse();
});

// Test cases for forceDelete method
test('forceDelete returns true if user is an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(true);
    $role = Mockery::mock(Role::class);

    $policy = new RolePolicy();
    expect($policy->forceDelete($user, $role))->toBeTrue();
});

test('forceDelete returns false if user is not an owner', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isOwner')->once()->andReturn(false);
    $role = Mockery::mock(Role::class);

    $policy = new RolePolicy();
    expect($policy->forceDelete($user, $role))->toBeFalse();
});




afterEach(function () {
    Mockery::close();
});
