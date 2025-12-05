<?php

use Crater\Policies\UserPolicy;
use Crater\Models\User;

beforeEach(function () {
        $this->policy = new UserPolicy();
    });

    // Tests for viewAny method
    test('an owner can view any user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(true);

        expect($this->policy->viewAny($user))->toBeTrue();
    });

    test('a non-owner cannot view any user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(false);

        expect($this->policy->viewAny($user))->toBeFalse();
    });

    // Tests for view method
    test('an owner can view a specific user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(true);
        $model = Mockery::mock(User::class); // The model is not used in the policy's logic

        expect($this->policy->view($user, $model))->toBeTrue();
    });

    test('a non-owner cannot view a specific user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(false);
        $model = Mockery::mock(User::class);

        expect($this->policy->view($user, $model))->toBeFalse();
    });

    // Tests for create method
    test('an owner can create users', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(true);

        expect($this->policy->create($user))->toBeTrue();
    });

    test('a non-owner cannot create users', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(false);

        expect($this->policy->create($user))->toBeFalse();
    });

    // Tests for update method
    test('an owner can update a user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(true);
        $model = Mockery::mock(User::class);

        expect($this->policy->update($user, $model))->toBeTrue();
    });

    test('a non-owner cannot update a user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(false);
        $model = Mockery::mock(User::class);

        expect($this->policy->update($user, $model))->toBeFalse();
    });

    // Tests for delete method
    test('an owner can delete a user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(true);
        $model = Mockery::mock(User::class);

        expect($this->policy->delete($user, $model))->toBeTrue();
    });

    test('a non-owner cannot delete a user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(false);
        $model = Mockery::mock(User::class);

        expect($this->policy->delete($user, $model))->toBeFalse();
    });

    // Tests for restore method
    test('an owner can restore a user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(true);
        $model = Mockery::mock(User::class);

        expect($this->policy->restore($user, $model))->toBeTrue();
    });

    test('a non-owner cannot restore a user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(false);
        $model = Mockery::mock(User::class);

        expect($this->policy->restore($user, $model))->toBeFalse();
    });

    // Tests for forceDelete method
    test('an owner can force delete a user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(true);
        $model = Mockery::mock(User::class);

        expect($this->policy->forceDelete($user, $model))->toBeTrue();
    });

    test('a non-owner cannot force delete a user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(false);
        $model = Mockery::mock(User::class);

        expect($this->policy->forceDelete($user, $model))->toBeFalse();
    });

    // Tests for invite method
    test('an owner can invite a user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(true);
        $model = Mockery::mock(User::class);

        expect($this->policy->invite($user, $model))->toBeTrue();
    });

    test('a non-owner cannot invite a user', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(false);
        $model = Mockery::mock(User::class);

        expect($this->policy->invite($user, $model))->toBeFalse();
    });

    // Tests for deleteMultiple method
    test('an owner can delete multiple users', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(true);

        expect($this->policy->deleteMultiple($user))->toBeTrue();
    });

    test('a non-owner cannot delete multiple users', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isOwner')->once()->andReturn(false);

        expect($this->policy->deleteMultiple($user))->toBeFalse();
    });




afterEach(function () {
    Mockery::close();
});