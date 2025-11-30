<?php

use function Pest\Laravel\mock;

beforeEach(function () {
        // Clear Mockery expectations before each test to prevent interference
        \Mockery::close();
    });

    // Test cases for viewAny method
    test('viewAny allows access if user can view any custom fields', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('view-custom-field', \Crater\Models\CustomField::class)
            ->andReturn(true);

        $result = $policy->viewAny($user);

        expect($result)->toBeTrue();
    });

    test('viewAny denies access if user cannot view any custom fields', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('view-custom-field', \Crater\Models\CustomField::class)
            ->andReturn(false);

        $result = $policy->viewAny($user);

        expect($result)->toBeFalse();
    });

    // Test cases for view method
    test('view allows access if user can view custom field and belongs to company', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('view-custom-field', $customField)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($customField->company_id)
            ->andReturn(true);

        $result = $policy->view($user, $customField);

        expect($result)->toBeTrue();
    });

    test('view denies access if user cannot view custom field', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('view-custom-field', $customField)
            ->andReturn(false);

        $user->shouldNotReceive('hasCompany'); // hasCompany should not be called if Bouncer fails

        $result = $policy->view($user, $customField);

        expect($result)->toBeFalse();
    });

    test('view denies access if user belongs to different company than custom field', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('view-custom-field', $customField)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($customField->company_id)
            ->andReturn(false);

        $result = $policy->view($user, $customField);

        expect($result)->toBeFalse();
    });

    // Test cases for create method
    test('create allows access if user can create custom fields', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('create-custom-field', \Crater\Models\CustomField::class)
            ->andReturn(true);

        $result = $policy->create($user);

        expect($result)->toBeTrue();
    });

    test('create denies access if user cannot create custom fields', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('create-custom-field', \Crater\Models\CustomField::class)
            ->andReturn(false);

        $result = $policy->create($user);

        expect($result)->toBeFalse();
    });

    // Test cases for update method
    test('update allows access if user can edit custom field and belongs to company', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('edit-custom-field', $customField)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($customField->company_id)
            ->andReturn(true);

        $result = $policy->update($user, $customField);

        expect($result)->toBeTrue();
    });

    test('update denies access if user cannot edit custom field', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('edit-custom-field', $customField)
            ->andReturn(false);

        $user->shouldNotReceive('hasCompany');

        $result = $policy->update($user, $customField);

        expect($result)->toBeFalse();
    });

    test('update denies access if user belongs to different company than custom field', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('edit-custom-field', $customField)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($customField->company_id)
            ->andReturn(false);

        $result = $policy->update($user, $customField);

        expect($result)->toBeFalse();
    });

    // Test cases for delete method
    test('delete allows access if user can delete custom field and belongs to company', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('delete-custom-field', $customField)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($customField->company_id)
            ->andReturn(true);

        $result = $policy->delete($user, $customField);

        expect($result)->toBeTrue();
    });

    test('delete denies access if user cannot delete custom field', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('delete-custom-field', $customField)
            ->andReturn(false);

        $user->shouldNotReceive('hasCompany');

        $result = $policy->delete($user, $customField);

        expect($result)->toBeFalse();
    });

    test('delete denies access if user belongs to different company than custom field', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('delete-custom-field', $customField)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($customField->company_id)
            ->andReturn(false);

        $result = $policy->delete($user, $customField);

        expect($result)->toBeFalse();
    });

    // Test cases for restore method (uses 'delete-custom-field' permission)
    test('restore allows access if user can delete custom field and belongs to company', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('delete-custom-field', $customField) // Note: Bouncer permission is 'delete-custom-field' for restore
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($customField->company_id)
            ->andReturn(true);

        $result = $policy->restore($user, $customField);

        expect($result)->toBeTrue();
    });

    test('restore denies access if user cannot delete custom field', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('delete-custom-field', $customField)
            ->andReturn(false);

        $user->shouldNotReceive('hasCompany');

        $result = $policy->restore($user, $customField);

        expect($result)->toBeFalse();
    });

    test('restore denies access if user belongs to different company than custom field', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('delete-custom-field', $customField)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($customField->company_id)
            ->andReturn(false);

        $result = $policy->restore($user, $customField);

        expect($result)->toBeFalse();
    });

    // Test cases for forceDelete method (uses 'delete-custom-field' permission)
    test('forceDelete allows access if user can delete custom field and belongs to company', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('delete-custom-field', $customField) // Note: Bouncer permission is 'delete-custom-field' for forceDelete
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($customField->company_id)
            ->andReturn(true);

        $result = $policy->forceDelete($user, $customField);

        expect($result)->toBeTrue();
    });

    test('forceDelete denies access if user cannot delete custom field', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('delete-custom-field', $customField)
            ->andReturn(false);

        $user->shouldNotReceive('hasCompany');

        $result = $policy->forceDelete($user, $customField);

        expect($result)->toBeFalse();
    });

    test('forceDelete denies access if user belongs to different company than custom field', function () {
        $user = \Mockery::mock(\Crater\Models\User::class);
        $customField = \Mockery::mock(\Crater\Models\CustomField::class);
        $customField->company_id = 1;
        $policy = new \Crater\Policies\CustomFieldPolicy();

        mock(\Silber\Bouncer\BouncerFacade::class)
            ->shouldReceive('can')
            ->once()
            ->with('delete-custom-field', $customField)
            ->andReturn(true);

        $user->shouldReceive('hasCompany')
            ->once()
            ->with($customField->company_id)
            ->andReturn(false);

        $result = $policy->forceDelete($user, $customField);

        expect($result)->toBeFalse();
    });
