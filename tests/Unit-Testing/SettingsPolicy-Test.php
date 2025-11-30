<?php

use Crater\Policies\SettingsPolicy;
use Crater\Models\Company;
use Crater\Models\User;
use Mockery as m;

beforeEach(function () {
    $this->policy = new SettingsPolicy();
});

afterEach(function () {
    m::close();
});

test('manageCompany returns true if user is the company owner', function () {
    $userId = 1;
    $ownerId = 1;

    $user = m::mock(User::class);
    $user->id = $userId;

    $company = m::mock(Company::class);
    $company->owner_id = $ownerId;

    $result = $this->policy->manageCompany($user, $company);

    expect($result)->toBeTrue();
});

test('manageCompany returns false if user is not the company owner', function () {
    $userId = 1;
    $ownerId = 2; // Different owner ID

    $user = m::mock(User::class);
    $user->id = $userId;

    $company = m::mock(Company::class);
    $company->owner_id = $ownerId;

    $result = $this->policy->manageCompany($user, $company);

    expect($result)->toBeFalse();
});

test('manageBackups returns true if user is an owner', function () {
    $user = m::mock(User::class);
    $user->shouldReceive('isOwner')->andReturn(true)->once();

    $result = $this->policy->manageBackups($user);

    expect($result)->toBeTrue();
});

test('manageBackups returns false if user is not an owner', function () {
    $user = m::mock(User::class);
    $user->shouldReceive('isOwner')->andReturn(false)->once();

    $result = $this->policy->manageBackups($user);

    expect($result)->toBeFalse();
});

test('manageFileDisk returns true if user is an owner', function () {
    $user = m::mock(User::class);
    $user->shouldReceive('isOwner')->andReturn(true)->once();

    $result = $this->policy->manageFileDisk($user);

    expect($result)->toBeTrue();
});

test('manageFileDisk returns false if user is not an owner', function () {
    $user = m::mock(User::class);
    $user->shouldReceive('isOwner')->andReturn(false)->once();

    $result = $this->policy->manageFileDisk($user);

    expect($result)->toBeFalse();
});

test('manageEmailConfig returns true if user is an owner', function () {
    $user = m::mock(User::class);
    $user->shouldReceive('isOwner')->andReturn(true)->once();

    $result = $this->policy->manageEmailConfig($user);

    expect($result)->toBeTrue();
});

test('manageEmailConfig returns false if user is not an owner', function () {
    $user = m::mock(User::class);
    $user->shouldReceive('isOwner')->andReturn(false)->once();

    $result = $this->policy->manageEmailConfig($user);

    expect($result)->toBeFalse();
});

test('manageSettings returns true if user is an owner', function () {
    $user = m::mock(User::class);
    $user->shouldReceive('isOwner')->andReturn(true)->once();

    $result = $this->policy->manageSettings($user);

    expect($result)->toBeTrue();
});

test('manageSettings returns false if user is not an owner', function () {
    $user = m::mock(User::class);
    $user->shouldReceive('isOwner')->andReturn(false)->once();

    $result = $this->policy->manageSettings($user);

    expect($result)->toBeFalse();
});
