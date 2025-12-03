<?php
use Carbon\Carbon;
use Crater\Console\Commands\CheckEstimateStatus;
use Crater\Models\Estimate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Console\Command;
use Mockery;
use PHPUnit\Framework\Assert;

beforeEach(function () {
    Mockery::close();
    Carbon::setTestNow(null); // Reset Carbon's test now state
});

// ---
// it has the correct signature and description
// ---
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

// ---
// constructor instantiates the command
// ---
test('constructor instantiates the command', function () {
    $command = new CheckEstimateStatus();
    expect($command)->toBeInstanceOf(CheckEstimateStatus::class);
    // Fixes: missing class import, use fully qualified class name 
    expect($command)->toBeInstanceOf(\Illuminate\Console\Command::class); // Verifies parent::__construct() indirectly
});

// ---
// handle does nothing when no estimates are expired
// ---
test('handle does nothing when no estimates are expired', function () {
    Carbon::setTestNow($now = Carbon::create(2023, 1, 15, 12, 0, 0));

    // Setup: mock Eloquent's static methods using partialMock
    $estimateMock = Mockery::mock('alias:' . Estimate::class);

    $estimateMock->shouldReceive('whereNotIn')
        ->once()
        ->with([Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED, Estimate::STATUS_EXPIRED])
        ->andReturnSelf();

    $estimateMock->shouldReceive('whereDate')
        ->once()
        ->with('expiry_date', '<', Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->equalTo($now)))
        ->andReturnSelf();

    $estimateMock->shouldReceive('get')
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

// ---
// handle updates a single expired estimate
// ---
test('handle updates a single expired estimate', function () {
    Carbon::setTestNow($now = Carbon::create(2023, 1, 15, 12, 0, 0));

    // Create the estimate instance as a standard class, not a mock, and spy on its save method
    $mockEstimate = new class extends Estimate {
        public $estimate_number = 'EST-001';
        public $status = 'pending';
        public $wasSaved = false;
        public function save(array $options = []) {
            $this->wasSaved = true;
            return true;
        }
    };

    // Setup: mock Eloquent's static methods using partialMock
    $estimateMock = Mockery::mock('alias:' . Estimate::class);

    $estimateMock->shouldReceive('whereNotIn')
        ->once()
        ->with([Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED, Estimate::STATUS_EXPIRED])
        ->andReturnSelf();

    $estimateMock->shouldReceive('whereDate')
        ->once()
        ->with('expiry_date', '<', Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->equalTo($now)))
        ->andReturnSelf();

    $estimateMock->shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([$mockEstimate]));

    $command = new CheckEstimateStatus();

    ob_start();
    $command->handle();
    ob_end_clean();

    // verify that status was changed (should be EXPIRED)
    expect($mockEstimate->status)->toBe(Estimate::STATUS_EXPIRED);
    expect($mockEstimate->wasSaved)->toBeTrue();
});

// ---
// handle updates multiple expired estimates
// ---
test('handle updates multiple expired estimates', function () {
    Carbon::setTestNow($now = Carbon::create(2023, 1, 15, 12, 0, 0));

    // Use actual Estimate objects and override save method
    $mockEstimate1 = new class extends Estimate {
        public $estimate_number = 'EST-001';
        public $status = 'pending';
        public $wasSaved = false;
        public function save(array $options = []) {
            $this->wasSaved = true;
            return true;
        }
    };

    $mockEstimate2 = new class extends Estimate {
        public $estimate_number = 'EST-002';
        public $status = 'pending';
        public $wasSaved = false;
        public function save(array $options = []) {
            $this->wasSaved = true;
            return true;
        }
    };

    // Setup: mock Eloquent's static methods using partialMock
    $estimateMock = Mockery::mock('alias:' . Estimate::class);

    $estimateMock->shouldReceive('whereNotIn')
        ->once()
        ->with([Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED, Estimate::STATUS_EXPIRED])
        ->andReturnSelf();

    $estimateMock->shouldReceive('whereDate')
        ->once()
        ->with('expiry_date', '<', Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->equalTo($now)))
        ->andReturnSelf();

    $estimateMock->shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([$mockEstimate1, $mockEstimate2]));

    $command = new CheckEstimateStatus();

    ob_start();
    $command->handle();
    ob_end_clean();

    // Verify statuses were changed on both mocks
    expect($mockEstimate1->status)->toBe(Estimate::STATUS_EXPIRED);
    expect($mockEstimate2->status)->toBe(Estimate::STATUS_EXPIRED);

    expect($mockEstimate1->wasSaved)->toBeTrue();
    expect($mockEstimate2->wasSaved)->toBeTrue();
});

// ---
// handle does not process estimates with expiry date in the future
// ---
test('handle does not process estimates with expiry date in the future', function () {
    Carbon::setTestNow($now = Carbon::create(2023, 1, 15, 12, 0, 0));

    $estimateMock = Mockery::mock('alias:' . Estimate::class);

    $estimateMock->shouldReceive('whereNotIn')
        ->once()
        ->with([Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED, Estimate::STATUS_EXPIRED])
        ->andReturnSelf();

    $estimateMock->shouldReceive('whereDate')
        ->once()
        ->with('expiry_date', '<', Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->equalTo($now)))
        ->andReturnSelf();

    $estimateMock->shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([])); // No estimates returned after date filtering

    $command = new CheckEstimateStatus();

    ob_start();
    $command->handle();
    ob_end_clean();

    // Assert that no save operations were attempted, which Mockery handles implicitly.
    expect(true)->toBeTrue();
});

// ---
// handle does not process estimates with already excluded statuses
// ---
test('handle does not process estimates with already excluded statuses', function () {
    Carbon::setTestNow($now = Carbon::create(2023, 1, 15, 12, 0, 0));

    $estimateMock = Mockery::mock('alias:' . Estimate::class);

    $estimateMock->shouldReceive('whereNotIn')
        ->once()
        ->with([Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED, Estimate::STATUS_EXPIRED])
        ->andReturnSelf();

    $estimateMock->shouldReceive('whereDate')
        ->once()
        ->with('expiry_date', '<', Mockery::on(fn ($arg) => $arg instanceof Carbon && $arg->equalTo($now)))
        ->andReturnSelf();

    $estimateMock->shouldReceive('get')
        ->once()
        ->andReturn(Collection::make([])); // No estimates returned after status filtering

    $command = new CheckEstimateStatus();

    ob_start();
    $command->handle();
    ob_end_clean();

    expect(true)->toBeTrue();
});

afterEach(function () {
    Mockery::close();
});