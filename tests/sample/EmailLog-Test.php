<?php

use Carbon\Carbon;
use Crater\Models\CompanySetting;
use Crater\Models\EmailLog;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Mockery as m;

// Use `afterEach` to clean up Mockery and Carbon mocks for a clean slate between tests.
afterEach(function () {
    m::close();
    Carbon::setTestNow(null); // Clear test now
});

test('mailable returns a morphTo relationship', function () {
    // Create a partial mock of EmailLog to allow overriding the 'mailable' method
    // while still allowing other methods to function if needed.
    // In this specific case, we just want to ensure 'morphTo' is called.
    $emailLog = m::mock(EmailLog::class)->makePartial();

    // Create a mock for the MorphTo relationship object that mailable() is expected to return.
    $mockMorphTo = m::mock(MorphTo::class);

    // Expect the 'morphTo' method on the EmailLog instance to be called once and return our mock.
    $emailLog->shouldReceive('morphTo')->andReturn($mockMorphTo)->once();

    // Act: Call the mailable method on the EmailLog instance.
    $result = $emailLog->mailable();

    // Assert: Ensure the returned result is our mock MorphTo object.
    expect($result)->toBe($mockMorphTo);
});

test('isExpired returns false when automatically_expire_public_links is NO', function () {
    // Arrange: Set up test environment and mocks
    Carbon::setTestNow(Carbon::create(2023, 1, 15)); // Mock current date
    $createdAt = Carbon::create(2023, 1, 10);       // Mock email creation date
    $linkExpiryDays = 7;                            // Mock link expiry setting

    // Create a partial mock of EmailLog to control 'created_at' and mock 'mailable()'
    $emailLog = m::mock(EmailLog::class)->makePartial();
    $emailLog->created_at = $createdAt;

    $companyId = 123; // Arbitrary company ID for testing

    // Mock the Mailable relationship chain: mailable() -> get() -> toArray()
    $mockCollection = m::mock(Collection::class);
    $mockCollection->shouldReceive('toArray')->andReturn([['company_id' => $companyId]])->once();

    $mockMorphTo = m::mock(MorphTo::class);
    $mockMorphTo->shouldReceive('get')->andReturn($mockCollection)->times(2); // 'get' is called twice by isExpired

    $emailLog->shouldReceive('mailable')->andReturn($mockMorphTo)->times(2); // 'mailable' is called twice

    // Mock the static CompanySetting::getSetting method using Mockery
    $companySettingMock = m::mock('alias:' . CompanySetting::class);
    $companySettingMock->shouldReceive('getSetting')
        ->with('link_expiry_days', $companyId)
        ->andReturn($linkExpiryDays)
        ->once();
    $companySettingMock->shouldReceive('getSetting')
        ->with('automatically_expire_public_links', $companyId)
        ->andReturn('NO')
        ->once();

    // Act: Call the method under test
    $result = $emailLog->isExpired();

    // Assert: Verify the expected outcome
    expect($result)->toBeFalse();
});

test('isExpired returns false when automatically_expire_public_links is YES but not expired yet', function () {
    // Arrange
    Carbon::setTestNow(Carbon::create(2023, 1, 15)); // Current date
    $createdAt = Carbon::create(2023, 1, 10);       // Email created
    $linkExpiryDays = 7;                            // Expiry date: Jan 10 + 7 days = Jan 17
                                                    // Current date (Jan 15) is BEFORE expiry date (Jan 17)

    $emailLog = m::mock(EmailLog::class)->makePartial();
    $emailLog->created_at = $createdAt;

    $companyId = 456;
    $mockCollection = m::mock(Collection::class);
    $mockCollection->shouldReceive('toArray')->andReturn([['company_id' => $companyId]])->once();

    $mockMorphTo = m::mock(MorphTo::class);
    $mockMorphTo->shouldReceive('get')->andReturn($mockCollection)->times(2);

    $emailLog->shouldReceive('mailable')->andReturn($mockMorphTo)->times(2);

    $companySettingMock = m::mock('alias:' . CompanySetting::class);
    $companySettingMock->shouldReceive('getSetting')
        ->with('link_expiry_days', $companyId)
        ->andReturn($linkExpiryDays)
        ->once();
    $companySettingMock->shouldReceive('getSetting')
        ->with('automatically_expire_public_links', $companyId)
        ->andReturn('YES') // Condition met
        ->once();

    // Act
    $result = $emailLog->isExpired();

    // Assert
    expect($result)->toBeFalse(); // Should be false because Carbon::now() is not > expiryDate
});

test('isExpired returns true when automatically_expire_public_links is YES and is expired', function () {
    // Arrange
    Carbon::setTestNow(Carbon::create(2023, 1, 19)); // Current date
    $createdAt = Carbon::create(2023, 1, 10);       // Email created
    $linkExpiryDays = 7;                            // Expiry date: Jan 10 + 7 days = Jan 17
                                                    // Current date (Jan 19) is AFTER expiry date (Jan 17)

    $emailLog = m::mock(EmailLog::class)->makePartial();
    $emailLog->created_at = $createdAt;

    $companyId = 789;
    $mockCollection = m::mock(Collection::class);
    $mockCollection->shouldReceive('toArray')->andReturn([['company_id' => $companyId]])->once();

    $mockMorphTo = m::mock(MorphTo::class);
    $mockMorphTo->shouldReceive('get')->andReturn($mockCollection)->times(2);

    $emailLog->shouldReceive('mailable')->andReturn($mockMorphTo)->times(2);

    $companySettingMock = m::mock('alias:' . CompanySetting::class);
    $companySettingMock->shouldReceive('getSetting')
        ->with('link_expiry_days', $companyId)
        ->andReturn($linkExpiryDays)
        ->once();
    $companySettingMock->shouldReceive('getSetting')
        ->with('automatically_expire_public_links', $companyId)
        ->andReturn('YES') // Condition met
        ->once();

    // Act
    $result = $emailLog->isExpired();

    // Assert
    expect($result)->toBeTrue(); // Should be true because Carbon::now() is > expiryDate
});

test('isExpired returns false when automatically_expire_public_links is YES and current date is exactly the expiry date', function () {
    // Arrange
    Carbon::setTestNow(Carbon::create(2023, 1, 17)); // Current date
    $createdAt = Carbon::create(2023, 1, 10);       // Email created
    $linkExpiryDays = 7;                            // Expiry date: Jan 10 + 7 days = Jan 17
                                                    // Current date (Jan 17) is EQUAL to expiry date (Jan 17)

    $emailLog = m::mock(EmailLog::class)->makePartial();
    $emailLog->created_at = $createdAt;

    $companyId = 101;
    $mockCollection = m::mock(Collection::class);
    $mockCollection->shouldReceive('toArray')->andReturn([['company_id' => $companyId]])->once();

    $mockMorphTo = m::mock(MorphTo::class);
    $mockMorphTo->shouldReceive('get')->andReturn($mockCollection)->times(2);

    $emailLog->shouldReceive('mailable')->andReturn($mockMorphTo)->times(2);

    $companySettingMock = m::mock('alias:' . CompanySetting::class);
    $companySettingMock->shouldReceive('getSetting')
        ->with('link_expiry_days', $companyId)
        ->andReturn($linkExpiryDays)
        ->once();
    $companySettingMock->shouldReceive('getSetting')
        ->with('automatically_expire_public_links', $companyId)
        ->andReturn('YES') // Condition met
        ->once();

    // Act
    $result = $emailLog->isExpired();

    // Assert
    expect($result)->toBeFalse(); // `>` operator means it must be strictly after the expiry date.
});

test('isExpired handles zero link expiry days correctly', function () {
    // Arrange
    Carbon::setTestNow(Carbon::create(2023, 1, 11)); // Current date
    $createdAt = Carbon::create(2023, 1, 10);       // Email created
    $linkExpiryDays = 0;                            // Expiry date: Jan 10 + 0 days = Jan 10
                                                    // Current date (Jan 11) is AFTER expiry date (Jan 10)

    $emailLog = m::mock(EmailLog::class)->makePartial();
    $emailLog->created_at = $createdAt;

    $companyId = 202;
    $mockCollection = m::mock(Collection::class);
    $mockCollection->shouldReceive('toArray')->andReturn([['company_id' => $companyId]])->once();

    $mockMorphTo = m::mock(MorphTo::class);
    $mockMorphTo->shouldReceive('get')->andReturn($mockCollection)->times(2);

    $emailLog->shouldReceive('mailable')->andReturn($mockMorphTo)->times(2);

    $companySettingMock = m::mock('alias:' . CompanySetting::class);
    $companySettingMock->shouldReceive('getSetting')
        ->with('link_expiry_days', $companyId)
        ->andReturn($linkExpiryDays)
        ->once();
    $companySettingMock->shouldReceive('getSetting')
        ->with('automatically_expire_public_links', $companyId)
        ->andReturn('YES')
        ->once();

    // Act
    $result = $emailLog->isExpired();

    // Assert
    expect($result)->toBeTrue();
});

test('isExpired ensures correct company_id is passed to CompanySetting', function () {
    // This test specifically verifies that the company_id extracted from the mailable relationship
    // is correctly passed to the CompanySetting::getSetting calls.
    // Arrange
    $expectedCompanyId = 999;
    Carbon::setTestNow(Carbon::create(2023, 1, 12));
    $createdAt = Carbon::create(2023, 1, 10);
    $linkExpiryDays = 1; // Expired on Jan 11 (10 + 1)

    $emailLog = m::mock(EmailLog::class)->makePartial();
    $emailLog->created_at = $createdAt;

    $mockCollection = m::mock(Collection::class);
    $mockCollection->shouldReceive('toArray')->andReturn([['company_id' => $expectedCompanyId]])->once();

    $mockMorphTo = m::mock(MorphTo::class);
    $mockMorphTo->shouldReceive('get')->andReturn($mockCollection)->times(2);

    $emailLog->shouldReceive('mailable')->andReturn($mockMorphTo)->times(2);

    // Expect CompanySetting::getSetting to be called with the specific $expectedCompanyId
    $companySettingMock = m::mock('alias:' . CompanySetting::class);
    $companySettingMock->shouldReceive('getSetting')
        ->with('link_expiry_days', $expectedCompanyId)
        ->andReturn($linkExpiryDays)
        ->once();
    $companySettingMock->shouldReceive('getSetting')
        ->with('automatically_expire_public_links', $expectedCompanyId)
        ->andReturn('YES')
        ->once();

    // Act
    $result = $emailLog->isExpired();

    // Assert (primary assertion is on mock expectations, but also confirm method logic)
    expect($result)->toBeTrue();
});

test('isExpired throws error if mailable relationship returns empty array for company_id', function () {
    // This tests an edge case where the related model might not exist or the relationship returns
    // an empty collection, leading to an attempt to access an undefined array key.
    // Arrange
    Carbon::setTestNow(Carbon::create(2023, 1, 15));
    $createdAt = Carbon::create(2023, 1, 10);

    $emailLog = m::mock(EmailLog::class)->makePartial();
    $emailLog->created_at = $createdAt;

    $mockCollection = m::mock(Collection::class);
    $mockCollection->shouldReceive('toArray')->andReturn([])->once(); // Simulating empty related model

    $mockMorphTo = m::mock(MorphTo::class);
    // 'get' is called once before the error is thrown when trying to access `[0]`.
    $mockMorphTo->shouldReceive('get')->andReturn($mockCollection)->once();

    $emailLog->shouldReceive('mailable')->andReturn($mockMorphTo)->once();

    $companySettingMock = m::mock('alias:' . CompanySetting::class);
    $companySettingMock->shouldReceive('getSetting')->andReturnUsing(function () {
        // Should not be called before the error
        return null;
    });

    // Act & Assert: Expect an ErrorException due to accessing an undefined array key
    expect(fn() => $emailLog->isExpired())
        ->toThrow(ErrorException::class, 'Undefined array key 0');
});