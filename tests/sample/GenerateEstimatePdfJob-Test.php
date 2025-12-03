<?php

use Crater\Jobs\GenerateEstimatePdfJob;


test('constructor sets estimate and deleteExistingFile properties correctly with default value', function () {
    // We only need a minimal object for the constructor test, as its methods aren't called here.
    $mockEstimate = (object) ['id' => 1, 'estimate_number' => 'EST-001'];
    $job = new GenerateEstimatePdfJob($mockEstimate);

    expect($job->estimate)->toBe($mockEstimate)
          ->and($job->deleteExistingFile)->toBeFalse();
});

test('constructor sets estimate and deleteExistingFile properties correctly when explicitly true', function () {
    $mockEstimate = (object) ['id' => 2, 'estimate_number' => 'EST-002'];
    $job = new GenerateEstimatePdfJob($mockEstimate, true);

    expect($job->estimate)->toBe($mockEstimate)
          ->and($job->deleteExistingFile)->toBeTrue();
});

test('handle calls generatePDF with correct arguments when deleteExistingFile is false', function () {
    $estimateNumber = 'EST-003';
    // Mock the estimate object to control its behavior and verify method calls
    $mockEstimate = Mockery::mock();
    // Set the property that the job will access
    $mockEstimate->estimate_number = $estimateNumber;

    // Expect generatePDF to be called once with specific arguments
    $mockEstimate->shouldReceive('generatePDF')
                 ->once()
                 ->with('estimate', $estimateNumber, false)
                 ->andReturn(null); // Assume generatePDF returns void

    $job = new GenerateEstimatePdfJob($mockEstimate, false);
    $job->handle();

    // Mockery::close() is handled by afterEach
});

test('handle calls generatePDF with correct arguments when deleteExistingFile is true', function () {
    $estimateNumber = 'EST-004';
    $mockEstimate = Mockery::mock();
    $mockEstimate->estimate_number = $estimateNumber;

    $mockEstimate->shouldReceive('generatePDF')
                 ->once()
                 ->with('estimate', $estimateNumber, true)
                 ->andReturn(null); // Assume generatePDF returns void

    $job = new GenerateEstimatePdfJob($mockEstimate, true);
    $job->handle();
});

test('handle returns 0 after successful execution', function () {
    $estimateNumber = 'EST-005';
    $mockEstimate = Mockery::mock();
    $mockEstimate->estimate_number = $estimateNumber;

    // Ensure generatePDF is called, but the specific arguments are less critical for this test.
    // The focus is on the return value of handle().
    $mockEstimate->shouldReceive('generatePDF')
                 ->once()
                 ->andReturn(null);

    $job = new GenerateEstimatePdfJob($mockEstimate);
    $result = $job->handle();

    expect($result)->toBe(0);
});