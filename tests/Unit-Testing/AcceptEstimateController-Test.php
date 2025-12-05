<?php

use Crater\Http\Controllers\V1\Customer\Estimate\AcceptEstimateController;
use Crater\Http\Resources\Customer\EstimateResource;
use Crater\Models\Company;
use Crater\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Mockery\MockInterface;

// Helper function to mock the Auth facade for the 'customer' guard
function mockAuthFacade(?int $customerId): void
{
    Auth::shouldReceive('guard')
        ->with('customer')
        ->andReturn(
            Mockery::mock(\Illuminate\Contracts\Auth\Guard::class, function (MockInterface $mock) use ($customerId) {
                $mock->shouldReceive('id')->andReturn($customerId);
            })
        );
}


test('invoke returns 404 if estimate is not found or does not belong to the authenticated customer', function () {
    // Arrange
    $customerId = 1;
    $estimateId = 99; // A non-existent or inaccessible estimate ID

    mockAuthFacade($customerId);

    // Mock the request to ensure it can be called correctly, even if not directly used in the 404 path
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('only')->with('status')->andReturn(['status' => 'accepted']);

    // Mock Company and its estimate relationship to return null
    $mockCompany = Mockery::mock(Company::class);
    $mockEstimateBuilder = Mockery::mock(Builder::class);

    $mockCompany->shouldReceive('estimates')->andReturn($mockEstimateBuilder);
    $mockEstimateBuilder->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $mockEstimateBuilder->shouldReceive('where')->with('id', $estimateId)->andReturnSelf();
    $mockEstimateBuilder->shouldReceive('first')->andReturn(null); // Simulate estimate not found or not matching customer

    $controller = new AcceptEstimateController();

    // Act
    $response = $controller->__invoke($mockRequest, $mockCompany, $estimateId);

    // Assert
    expect($response->getStatusCode())->toBe(404);
    expect(json_decode($response->getContent(), true))->toEqual(['error' => 'estimate_not_found']);
});

test('invoke successfully updates estimate status to "accepted" and returns resource', function () {
    // Arrange
    $customerId = 1;
    $estimateId = 123;
    $newStatus = 'accepted';

    mockAuthFacade($customerId);

    // Mock the request to simulate a status update
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('only')->with('status')->andReturn(['status' => $newStatus]);

    // Mock the Estimate model and set attributes directly to avoid BadMethodCallException
    $mockEstimate = Mockery::mock(Estimate::class)->makePartial();
    $mockEstimate->shouldAllowMockingProtectedMethods();
    // Set initial status
    $mockEstimate->setAttribute('id', $estimateId);
    $mockEstimate->setAttribute('status', 'pending'); // Initial status before update

    $mockEstimate->shouldReceive('update')
        ->once()
        ->with(['status' => $newStatus])
        ->andReturnUsing(function ($data) use ($mockEstimate, $newStatus) {
            // Simulate status change
            $mockEstimate->setAttribute('status', $newStatus);
            return true;
        });

    // Also allow reading the status attribute directly
    $mockEstimate->shouldReceive('getAttribute')
        ->with('status')->andReturn($newStatus);
    $mockEstimate->shouldReceive('getAttribute')
        ->with('id')->andReturn($estimateId);

    // Mock Company and its estimate relationship to return the found estimate
    $mockCompany = Mockery::mock(Company::class);
    $mockEstimateBuilder = Mockery::mock(Builder::class);

    $mockCompany->shouldReceive('estimates')->andReturn($mockEstimateBuilder);
    $mockEstimateBuilder->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $mockEstimateBuilder->shouldReceive('where')->with('id', $estimateId)->andReturnSelf();
    $mockEstimateBuilder->shouldReceive('first')->andReturn($mockEstimate); // Estimate found

    $controller = new AcceptEstimateController();

    // Act
    $resource = $controller->__invoke($mockRequest, $mockCompany, $estimateId);

    // Assert
    expect($resource)->toBeInstanceOf(EstimateResource::class);
    // Verify that the status was updated on the mock estimate
    expect($mockEstimate->getAttribute('status'))->toBe($newStatus);
});

test('invoke successfully updates estimate status to "declined" and returns resource', function () {
    // Arrange
    $customerId = 1;
    $estimateId = 124;
    $newStatus = 'declined'; // Test with a different valid status

    mockAuthFacade($customerId);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('only')->with('status')->andReturn(['status' => $newStatus]);

    $mockEstimate = Mockery::mock(Estimate::class)->makePartial();
    $mockEstimate->shouldAllowMockingProtectedMethods();
    $mockEstimate->setAttribute('id', $estimateId);
    $mockEstimate->setAttribute('status', 'pending'); // Initial status

    $mockEstimate->shouldReceive('update')
        ->once()
        ->with(['status' => $newStatus])
        ->andReturnUsing(function ($data) use ($mockEstimate, $newStatus) {
            $mockEstimate->setAttribute('status', $newStatus);
            return true;
        });

    $mockEstimate->shouldReceive('getAttribute')
        ->with('status')->andReturn($newStatus);
    $mockEstimate->shouldReceive('getAttribute')
        ->with('id')->andReturn($estimateId);

    $mockCompany = Mockery::mock(Company::class);
    $mockEstimateBuilder = Mockery::mock(Builder::class);

    $mockCompany->shouldReceive('estimates')->andReturn($mockEstimateBuilder);
    $mockEstimateBuilder->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $mockEstimateBuilder->shouldReceive('where')->with('id', $estimateId)->andReturnSelf();
    $mockEstimateBuilder->shouldReceive('first')->andReturn($mockEstimate);

    $controller = new AcceptEstimateController();

    // Act
    $resource = $controller->__invoke($mockRequest, $mockCompany, $estimateId);

    // Assert
    expect($resource)->toBeInstanceOf(EstimateResource::class);
    expect($mockEstimate->getAttribute('status'))->toBe($newStatus);
});

test('invoke handles request with no status field, resulting in no status change', function () {
    // Edge case: what if 'status' is not present in the request payload?
    // `request->only('status')` will return an empty array, and `update([])` will be called.
    // Eloquent's `update()` method with an empty array typically results in no changes.

    // Arrange
    $customerId = 1;
    $estimateId = 125;
    $originalStatus = 'pending';

    mockAuthFacade($customerId);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('only')->with('status')->andReturn([]); // Simulate no status field in request

    $mockEstimate = Mockery::mock(Estimate::class)->makePartial();
    $mockEstimate->shouldAllowMockingProtectedMethods();
    $mockEstimate->setAttribute('id', $estimateId);
    $mockEstimate->setAttribute('status', $originalStatus); // Initial status

    $mockEstimate->shouldReceive('update')
        ->once()
        ->with([])
        ->andReturnUsing(function ($data) use ($mockEstimate, $originalStatus) {
            // No change, status remains
            $mockEstimate->setAttribute('status', $originalStatus);
            return true;
        });

    $mockEstimate->shouldReceive('getAttribute')
        ->with('status')->andReturn($originalStatus);
    $mockEstimate->shouldReceive('getAttribute')
        ->with('id')->andReturn($estimateId);

    $mockCompany = Mockery::mock(Company::class);
    $mockEstimateBuilder = Mockery::mock(Builder::class);

    $mockCompany->shouldReceive('estimates')->andReturn($mockEstimateBuilder);
    $mockEstimateBuilder->shouldReceive('whereCustomer')->with($customerId)->andReturnSelf();
    $mockEstimateBuilder->shouldReceive('where')->with('id', $estimateId)->andReturnSelf();
    $mockEstimateBuilder->shouldReceive('first')->andReturn($mockEstimate);

    $controller = new AcceptEstimateController();

    // Act
    $resource = $controller->__invoke($mockRequest, $mockCompany, $estimateId);

    // Assert
    expect($resource)->toBeInstanceOf(EstimateResource::class);
    // Verify that the status on the mock estimate remained unchanged
    expect($mockEstimate->getAttribute('status'))->toBe($originalStatus);
});

afterEach(function () {
    Mockery::close();
});