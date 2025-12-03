<?php

use Crater\Models\User;
use Crater\Models\UserSetting;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\Factory;

test('user relationship returns a BelongsTo instance targeting the User model', function () {
    $userSetting = new UserSetting();

    $relation = $userSetting->user();

    // Assert that the relationship method returns an instance of BelongsTo
    expect($relation)->toBeInstanceOf(BelongsTo::class);

    // Assert that the relationship is correctly linked to the User model
    expect($relation->getRelated())->toBeInstanceOf(User::class);

    // Assert that default foreign key name 'user_id' is used
    expect($relation->getForeignKeyName())->toBe('user_id');

    // Assert that default owner key name 'id' is used
    expect($relation->getOwnerKeyName())->toBe('id');
});

test('user setting uses the HasFactory trait', function () {
    // Assert that the 'factory' static method exists on the UserSetting model
    expect(method_exists(UserSetting::class, 'factory'))->toBeTrue();

    // The error "Class "Database\Factories\UserSettingFactory" not found" occurs
    // because the `HasFactory` trait's `factory()` method attempts to resolve
    // and instantiate the concrete factory class (`UserSettingFactory`).
    // In a unit test, this factory might not exist or not be loaded, causing the failure.
    // To fix this, we mock the static `factory()` method of `UserSetting`
    // to return a mock of `Illuminate\Database\Eloquent\Factories\Factory`,
    // preventing the actual factory resolution logic from running.

    // Create a mock instance of the Eloquent Factory class that will be returned.
    $mockFactoryInstance = Mockery::mock(Factory::class);

    // Create a Mockery alias for the UserSetting class.
    // This allows us to intercept static method calls on `UserSetting`.
    Mockery::mock('alias:' . UserSetting::class)
        ->shouldReceive('factory')
        ->andReturn($mockFactoryInstance);

    // Now, when `UserSetting::factory()` is called, it will return our mock.
    // Assert that calling the 'factory' method returns an instance of the Eloquent Factory
    expect(UserSetting::factory())->toBeInstanceOf(Factory::class);
});


afterEach(function () {
    Mockery::close();
});