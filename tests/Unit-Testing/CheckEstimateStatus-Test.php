<?php
use Carbon\Carbon;
use Crater\Console\Commands\CheckEstimateStatus;
use Crater\Models\Estimate;
use Illuminate\Database\Eloquent\Collection;
uses(\Mockery::class);

beforeEach(function () {
    Mockery::close();
    Carbon::setTestNow(null); // Reset Carbon's test now state
});

test('it has the correct signature and description', function () {
    $command = new CheckEstimateStatus();

    $reflectionCommand = new ReflectionClass($command);

    $signatureProperty = $reflectionCommand->getProperty('signature');
    $signatureProperty->setAccessible(true);
    expect($signatureProperty->getValue($command))->toBe('check:estimates:status');

    $descriptionProperty = $reflectionCommand->getProperty('description');
    $descriptionProperty->setAccessible(true);
    expect($descriptionProperty->getValue($command))->toBe('Check invoices status.');
});

test('constructor instantiates the command', function () {
    $command = new CheckEstimateStatus();
    expect($command)->toBeInstanceOf(CheckEstimateStatus::class);
    expect($command)->toBeInstanceOf(Command::class); // Verifies parent::__construct() indirectly
});

test('handle does nothing when no estimates are expired', function () {
    Carbon::setTestNow($now = Carbon::create(2023, 1, 15, 12, 0, 0));

    // Expect the query chain to be called, but return an empty collection
    Estimate::shouldReceive('whereNotIn')
        ->once()
        ->with([Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED, Estimate::STATUS_EXPIRED])
        ->andReturnSelf();

    Estimate::shouldReceive('whereDate')
        ->once()
        ->with('expiry_date', '<', Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->equalTo($now)))
        ->andReturnSelf();

    Estimate::shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([]));

    $command = new CheckEstimateStatus();

    // Suppress printf output during test
    ob_start();
    $command->handle();
    ob_end_clean();

    // Mockery verifies that no 'save' calls occurred as no estimates were returned.
    expect(true)->toBeTrue(); // Dummy assertion
});

test('handle updates a single expired estimate', function () {
    Carbon::setTestNow($now = Carbon::create(2023, 1, 15, 12, 0, 0));

    $mockEstimate = Mockery::mock(Estimate::class);
    $mockEstimate->estimate_number = 'EST-001'; // Used by printf
    $mockEstimate->status = 'pending'; // Initial status

    // Expectations on the mock estimate
    $mockEstimate->shouldReceive('setAttribute')
        ->once()
        ->with('status', Estimate::STATUS_EXPIRED)
        ->andSet('status', Estimate::STATUS_EXPIRED); // Update internal state for assertion

    $mockEstimate->shouldReceive('save')
        ->once()
        ->andReturn(true);

    // Mock the Estimate model's query chain
    Estimate::shouldReceive('whereNotIn')
        ->once()
        ->with([Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED, Estimate::STATUS_EXPIRED])
        ->andReturnSelf();

    Estimate::shouldReceive('whereDate')
        ->once()
        ->with('expiry_date', '<', Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->equalTo($now)))
        ->andReturnSelf();

    Estimate::shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([$mockEstimate]));

    $command = new CheckEstimateStatus();

    ob_start();
    $command->handle();
    ob_end_clean();

    // Verify the status was changed on the mock
    expect($mockEstimate->status)->toBe(Estimate::STATUS_EXPIRED);
});

test('handle updates multiple expired estimates', function () {
    Carbon::setTestNow($now = Carbon::create(2023, 1, 15, 12, 0, 0));

    $mockEstimate1 = Mockery::mock(Estimate::class);
    $mockEstimate1->estimate_number = 'EST-001';
    $mockEstimate1->status = 'pending';
    $mockEstimate1->shouldReceive('setAttribute')
        ->once()
        ->with('status', Estimate::STATUS_EXPIRED)
        ->andSet('status', Estimate::STATUS_EXPIRED);
    $mockEstimate1->shouldReceive('save')->once()->andReturn(true);

    $mockEstimate2 = Mockery::mock(Estimate::class);
    $mockEstimate2->estimate_number = 'EST-002';
    $mockEstimate2->status = 'pending';
    $mockEstimate2->shouldReceive('setAttribute')
        ->once()
        ->with('status', Estimate::STATUS_EXPIRED)
        ->andSet('status', Estimate::STATUS_EXPIRED);
    $mockEstimate2->shouldReceive('save')->once()->andReturn(true);

    // Mock the Estimate model's query chain
    Estimate::shouldReceive('whereNotIn')
        ->once()
        ->with([Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED, Estimate::STATUS_EXPIRED])
        ->andReturnSelf();

    Estimate::shouldReceive('whereDate')
        ->once()
        ->with('expiry_date', '<', Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->equalTo($now)))
        ->andReturnSelf();

    Estimate::shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([$mockEstimate1, $mockEstimate2]));

    $command = new CheckEstimateStatus();

    ob_start();
    $command->handle();
    ob_end_clean();

    // Verify statuses were changed on both mocks
    expect($mockEstimate1->status)->toBe(Estimate::STATUS_EXPIRED);
    expect($mockEstimate2->status)->toBe(Estimate::STATUS_EXPIRED);
});

test('handle does not process estimates with expiry date in the future', function () {
    Carbon::setTestNow($now = Carbon::create(2023, 1, 15, 12, 0, 0));

    // We expect the query to correctly filter out estimates with future expiry dates,
    // resulting in an empty collection.
    Estimate::shouldReceive('whereNotIn')
        ->once()
        ->with([Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED, Estimate::STATUS_EXPIRED])
        ->andReturnSelf();

    Estimate::shouldReceive('whereDate')
        ->once()
        ->with('expiry_date', '<', Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->equalTo($now)))
        ->andReturnSelf();

    Estimate::shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([])); // No estimates returned after date filtering

    $command = new CheckEstimateStatus();

    ob_start();
    $command->handle();
    ob_end_clean();

    // Assert that no save operations were attempted, which Mockery handles implicitly.
    expect(true)->toBeTrue();
});

test('handle does not process estimates with already excluded statuses', function () {
    Carbon::setTestNow($now = Carbon::create(2023, 1, 15, 12, 0, 0));

    // Similar to the expiry date test, we ensure the `whereNotIn` clause is correctly mocked
    // to return an empty collection if all potential estimates were already in excluded statuses.
    Estimate::shouldReceive('whereNotIn')
        ->once()
        ->with([Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED, Estimate::STATUS_EXPIRED])
        ->andReturnSelf();

    Estimate::shouldReceive('whereDate')
        ->once()
        ->with('expiry_date', '<', Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->equalTo($now)))
        ->andReturnSelf();

    Estimate::shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([])); // No estimates returned after status filtering

    $command = new CheckEstimateStatus();

    ob_start();
    $command->handle();
    ob_end_clean();

    expect(true)->toBeTrue();
});
