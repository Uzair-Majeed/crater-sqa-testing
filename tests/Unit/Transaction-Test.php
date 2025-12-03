<?php

use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Invoice;
use Crater\Models\Payment;
// use Crater\Models\Transaction; // Removed to allow Mockery 'overload' to work correctly
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Vinkla\Hashids\Facades\Hashids;

// Setup a test suite for Transaction model
beforeEach(function () {
    // Clean up Mockery mocks after each test
    Mockery::close();
    // Reset Carbon's test now state to ensure tests don't interfere with each other
    Carbon::setTestNow(null);
});

test('payments returns hasMany relationship', function () {
    $transaction = new \Crater\Models\Transaction(); // Use FQCN
    $relation = $transaction->payments();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Payment::class);
});

test('invoice returns belongsTo relationship', function () {
    $transaction = new \Crater\Models\Transaction(); // Use FQCN
    $relation = $transaction->invoice();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Invoice::class);
});

test('company returns belongsTo relationship', function () {
    $transaction = new \Crater\Models\Transaction(); // Use FQCN
    $relation = $transaction->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Company::class);
});

test('completeTransaction sets status to SUCCESS and saves', function () {
    $transaction = Mockery::mock(\Crater\Models\Transaction::class)->makePartial(); // Use FQCN
    $transaction->shouldReceive('save')->once()->andReturn(true);

    $transaction->completeTransaction();

    expect($transaction->status)->toBe(\Crater\Models\Transaction::SUCCESS); // Use FQCN
});

test('failedTransaction sets status to FAILED and saves', function () {
    $transaction = Mockery::mock(\Crater\Models\Transaction::class)->makePartial(); // Use FQCN
    $transaction->shouldReceive('save')->once()->andReturn(true);

    $transaction->failedTransaction();

    expect($transaction->status)->toBe(\Crater\Models\Transaction::FAILED); // Use FQCN
});

test('createTransaction creates transaction, sets unique hash, and saves', function () {
    $data = ['amount' => 100, 'description' => 'Test Transaction', 'company_id' => 1];
    $transactionId = 123;
    $encodedHash = 'encoded_hash_123';

    // Mock the static `create` method on the Transaction model
    // Using FQCN and 'overload' to ensure the class isn't loaded before Mockery can replace it.
    // This resolves the "class already exists" runtime exception.
    $mockTransactionModel = Mockery::mock('overload:' . \Crater\Models\Transaction::class);
    $mockTransaction = Mockery::mock(\Crater\Models\Transaction::class); // Actual instance that will be returned by create
    $mockTransaction->id = $transactionId;
    $mockTransaction->unique_hash = null; // Ensure it's initially null
    // Mock setAttribute because `$transaction->unique_hash = ...` uses it internally for Eloquent models
    $mockTransaction->shouldReceive('setAttribute')->with('unique_hash', $encodedHash)->once()->andReturnSelf();
    $mockTransaction->shouldReceive('save')->once()->andReturn(true);

    $mockTransactionModel->shouldReceive('create')
                         ->once()
                         ->with($data)
                         ->andReturn($mockTransaction);

    // Mock Hashids facade
    $mockHashidsConnection = Mockery::mock();
    $mockHashidsConnection->shouldReceive('encode')
                          ->once()
                          ->with($transactionId)
                          ->andReturn($encodedHash);

    Hashids::shouldReceive('connection')
           ->once()
           ->with(\Crater\Models\Transaction::class) // Use FQCN
           ->andReturn($mockHashidsConnection);

    $result = \Crater\Models\Transaction::createTransaction($data); // Use FQCN

    expect($result)->toBe($mockTransaction)
        ->and($result->unique_hash)->toBe($encodedHash); // Assert on the actual mock object's state
});

test('isExpired returns false when automatically_expire_public_links is NO', function () {
    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);
    $mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('link_expiry_days', 1)
        ->andReturn(7);
    $mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('automatically_expire_public_links', 1)
        ->andReturn('NO'); // This condition prevents expiry

    $transaction = new \Crater\Models\Transaction(); // Use FQCN
    $transaction->company_id = 1;
    $transaction->status = \Crater\Models\Transaction::SUCCESS; // Other conditions are met // Use FQCN
    $transaction->updated_at = Carbon::yesterday()->subDays(10); // Expired if link expiry was 'YES'

    expect($transaction->isExpired())->toBeFalse();
});

test('isExpired returns false when status is not SUCCESS', function () {
    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);
    // These methods are called twice, once for PENDING and once for FAILED,
    // so update ->once() to ->times(2)
    $mockCompanySetting->shouldReceive('getSetting')
        ->times(2) // Changed from ->once() to ->times(2)
        ->with('link_expiry_days', 1)
        ->andReturn(7);
    $mockCompanySetting->shouldReceive('getSetting')
        ->times(2) // Changed from ->once() to ->times(2)
        ->with('automatically_expire_public_links', 1)
        ->andReturn('YES'); // This condition is met

    $transaction = new \Crater\Models\Transaction(); // Use FQCN
    $transaction->company_id = 1;
    $transaction->updated_at = Carbon::yesterday()->subDays(10); // Expired if status was SUCCESS

    // Test with PENDING status
    $transaction->status = \Crater\Models\Transaction::PENDING; // Use FQCN
    expect($transaction->isExpired())->toBeFalse();

    // Test with FAILED status
    $transaction->status = \Crater\Models\Transaction::FAILED; // Use FQCN
    expect($transaction->isExpired())->toBeFalse();
});

test('isExpired returns false when link has not expired yet', function () {
    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);
    $mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('link_expiry_days', 1)
        ->andReturn(7); // Link expires 7 days after updated_at
    $mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('automatically_expire_public_links', 1)
        ->andReturn('YES'); // This condition is met

    Carbon::setTestNow(Carbon::create(2023, 1, 15)); // Current date: Jan 15th
    $transaction = new \Crater\Models\Transaction(); // Use FQCN
    $transaction->company_id = 1;
    $transaction->status = \Crater\Models\Transaction::SUCCESS; // This condition is met // Use FQCN
    $transaction->updated_at = Carbon::create(2023, 1, 10); // Updated: Jan 10th

    // Expiry date (Y-m-d): 2023-01-10 + 7 days = 2023-01-17
    // Carbon::now() (Jan 15th) is NOT strictly greater than expiryDate (Jan 17th)
    expect($transaction->isExpired())->toBeFalse();
});

test('isExpired returns true when all conditions for expiry are met', function () {
    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);
    $mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('link_expiry_days', 1)
        ->andReturn(7); // Link expires 7 days after updated_at
    $mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('automatically_expire_public_links', 1)
        ->andReturn('YES'); // This condition is met

    Carbon::setTestNow(Carbon::create(2023, 1, 20)); // Current date: Jan 20th
    $transaction = new \Crater\Models\Transaction(); // Use FQCN
    $transaction->company_id = 1;
    $transaction->status = \Crater\Models\Transaction::SUCCESS; // This condition is met // Use FQCN
    $transaction->updated_at = Carbon::create(2023, 1, 10); // Updated: Jan 10th

    // Expiry date (Y-m-d): 2023-01-10 + 7 days = 2023-01-17
    // Carbon::now() (Jan 20th) IS strictly greater than expiryDate (Jan 17th)
    expect($transaction->isExpired())->toBeTrue();
});

test('isExpired handles zero link expiry days correctly on the same day', function () {
    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);
    $mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('link_expiry_days', 1)
        ->andReturn(0); // Link expires on the same day as updated_at
    $mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('automatically_expire_public_links', 1)
        ->andReturn('YES'); // This condition is met

    $transaction = new \Crater\Models\Transaction(); // Use FQCN
    $transaction->company_id = 1;
    $transaction->status = \Crater\Models\Transaction::SUCCESS; // This condition is met // Use FQCN
    $transaction->updated_at = Carbon::create(2023, 1, 10, 12, 0, 0); // Updated at 12 PM

    // Scenario 1: Carbon::now() is the same day as updated_at
    Carbon::setTestNow(Carbon::create(2023, 1, 10, 15, 0, 0)); // Now is later time, but same date
    // Expiry date (Y-m-d) is 2023-01-10. Carbon::now() (Y-m-d) is 2023-01-10.
    // '2023-01-10' > '2023-01-10' is false.
    expect($transaction->isExpired())->toBeFalse();
});

test('isExpired handles zero link expiry days correctly on the next day', function () {
    $mockCompanySetting = Mockery::mock('alias:' . CompanySetting::class);
    $mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('link_expiry_days', 1)
        ->andReturn(0); // Link expires on the same day as updated_at
    $mockCompanySetting->shouldReceive('getSetting')
        ->once()
        ->with('automatically_expire_public_links', 1)
        ->andReturn('YES'); // This condition is met

    $transaction = new \Crater\Models\Transaction(); // Use FQCN
    $transaction->company_id = 1;
    $transaction->status = \Crater\Models\Transaction::SUCCESS; // This condition is met // Use FQCN
    $transaction->updated_at = Carbon::create(2023, 1, 10, 12, 0, 0); // Updated at 12 PM

    // Scenario 2: Carbon::now() is the day after updated_at
    Carbon::setTestNow(Carbon::create(2023, 1, 11, 1, 0, 0)); // A day after updated_at
    // Expiry date (Y-m-d) is 2023-01-10. Carbon::now() (Y-m-d) is 2023-01-11.
    // '2023-01-11' > '2023-01-10' is true.
    expect($transaction->isExpired())->toBeTrue();
});

afterEach(function () {
    Mockery::close();
});