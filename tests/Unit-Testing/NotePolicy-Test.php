<?php

use Crater\Policies\NotePolicy;
use Crater\Models\User;
use Crater\Models\Note;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Silber\Bouncer\BouncerFacade;

uses(MockeryPHPUnitIntegration::class);

beforeEach(function () {
    // Ensure BouncerFacade is mocked before each test
    // Set a default expectation that can be overridden by specific tests
    BouncerFacade::shouldReceive('can')->byDefault()->andReturn(false);
});

test('manageNotes returns true when user can manage all notes', function () {
    // Arrange
    $user = Mockery::mock(User::class); // Mock User model to isolate
    $policy = new NotePolicy();

    // Expect BouncerFacade::can to be called with 'manage-all-notes' and return true
    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('manage-all-notes', Note::class)
        ->andReturn(true);

    // Act
    $result = $policy->manageNotes($user);

    // Assert
    expect($result)->toBeTrue();
});

test('manageNotes returns false when user cannot manage all notes', function () {
    // Arrange
    $user = Mockery::mock(User::class); // Mock User model to isolate
    $policy = new NotePolicy();

    // Expect BouncerFacade::can to be called with 'manage-all-notes' and return false
    // This explicitly sets the expectation, overriding the beforeEach default if necessary
    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('manage-all-notes', Note::class)
        ->andReturn(false);

    // Act
    $result = $policy->manageNotes($user);

    // Assert
    expect($result)->toBeFalse();
});

test('viewNotes returns true when user can view all notes', function () {
    // Arrange
    $user = Mockery::mock(User::class); // Mock User model to isolate
    $policy = new NotePolicy();

    // Expect BouncerFacade::can to be called with 'view-all-notes' and return true
    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-all-notes', Note::class)
        ->andReturn(true);

    // Act
    $result = $policy->viewNotes($user);

    // Assert
    expect($result)->toBeTrue();
});

test('viewNotes returns false when user cannot view all notes', function () {
    // Arrange
    $user = Mockery::mock(User::class); // Mock User model to isolate
    $policy = new NotePolicy();

    // Expect BouncerFacade::can to be called with 'view-all-notes' and return false
    // This explicitly sets the expectation, overriding the beforeEach default if necessary
    BouncerFacade::shouldReceive('can')
        ->once()
        ->with('view-all-notes', Note::class)
        ->andReturn(false);

    // Act
    $result = $policy->viewNotes($user);

    // Assert
    expect($result)->toBeFalse();
});
