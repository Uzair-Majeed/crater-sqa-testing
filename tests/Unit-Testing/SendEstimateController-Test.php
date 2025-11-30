<?php

use Crater\Http\Controllers\V1\Admin\Estimate\SendEstimateController;
use Crater\Http\Requests\SendEstimatesRequest;
use Crater\Models\Estimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
uses(\Mockery::class);

test('it successfully sends an estimate and returns a json response', function () {
    // Arrange
    $requestData = ['key' => 'value', 'notes' => 'Some notes'];
    $sendResponse = ['status' => 'success', 'message' => 'Estimate sent successfully.'];

    $mockRequest = Mockery::mock(SendEstimatesRequest::class);
    $mockRequest->shouldReceive('all')->once()->andReturn($requestData);

    $mockEstimate = Mockery::mock(Estimate::class);
    $mockEstimate->shouldReceive('send')
        ->once()
        ->with($requestData)
        ->andReturn($sendResponse);

    // Create a partial mock of the controller to mock its own protected/trait methods like 'authorize'
    $controller = Mockery::mock(SendEstimateController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('send estimate', $mockEstimate)
        ->andReturn(true); // Simulate successful authorization

    // Act
    $response = $controller->__invoke($mockRequest, $mockEstimate);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($sendResponse);
    expect($response->getStatusCode())->toBe(200);
});

test('it throws an authorization exception if the user is not authorized to send an estimate', function () {
    // Arrange
    $mockRequest = Mockery::mock(SendEstimatesRequest::class);
    // 'all' method should not be called if authorization fails before it.
    $mockRequest->shouldNotReceive('all');

    $mockEstimate = Mockery::mock(Estimate::class);
    // 'send' method should not be called if authorization fails.
    $mockEstimate->shouldNotReceive('send');

    $controller = Mockery::mock(SendEstimateController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('send estimate', $mockEstimate)
        ->andThrow(new AuthorizationException('Unauthorized.'));

    // Act & Assert
    $this->expectException(AuthorizationException::class);
    $controller->__invoke($mockRequest, $mockEstimate);
});

test('it handles an empty request data successfully', function () {
    // Arrange
    $requestData = []; // Empty data for the request
    $sendResponse = ['status' => 'success', 'message' => 'Estimate sent with no additional data.'];

    $mockRequest = Mockery::mock(SendEstimatesRequest::class);
    $mockRequest->shouldReceive('all')->once()->andReturn($requestData);

    $mockEstimate = Mockery::mock(Estimate::class);
    $mockEstimate->shouldReceive('send')
        ->once()
        ->with($requestData)
        ->andReturn($sendResponse);

    $controller = Mockery::mock(SendEstimateController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('send estimate', $mockEstimate)
        ->andReturn(true);

    // Act
    $response = $controller->__invoke($mockRequest, $mockEstimate);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($sendResponse);
    expect($response->getStatusCode())->toBe(200);
});

test('it returns a failure response if the estimate send method returns an error', function () {
    // Arrange
    $requestData = ['key' => 'value'];
    // Simulate an internal failure from the estimate model's send method
    $sendResponse = ['status' => 'error', 'message' => 'Failed to process send operation.'];

    $mockRequest = Mockery::mock(SendEstimatesRequest::class);
    $mockRequest->shouldReceive('all')->once()->andReturn($requestData);

    $mockEstimate = Mockery::mock(Estimate::class);
    $mockEstimate->shouldReceive('send')
        ->once()
        ->with($requestData)
        ->andReturn($sendResponse);

    $controller = Mockery::mock(SendEstimateController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('send estimate', $mockEstimate)
        ->andReturn(true);

    // Act
    $response = $controller->__invoke($mockRequest, $mockEstimate);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($sendResponse);
    expect($response->getStatusCode())->toBe(200); // By default, json helper returns 200, status is in body.
});

test('it passes all request data, even if unexpected, to the estimate send method', function () {
    // Arrange
    $requestData = [
        'email_subject' => 'Your Estimate',
        'email_body' => 'Please find your estimate attached.',
        'recipient_email' => 'client@example.com',
        'extra_field' => 'some_data' // An unexpected field
    ];
    $sendResponse = ['status' => 'success', 'message' => 'Estimate sent with all data.'];

    $mockRequest = Mockery::mock(SendEstimatesRequest::class);
    $mockRequest->shouldReceive('all')->once()->andReturn($requestData);

    $mockEstimate = Mockery::mock(Estimate::class);
    // Ensure the estimate->send method receives *all* the data from the request
    $mockEstimate->shouldReceive('send')
        ->once()
        ->with($requestData)
        ->andReturn($sendResponse);

    $controller = Mockery::mock(SendEstimateController::class)->makePartial();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('send estimate', $mockEstimate)
        ->andReturn(true);

    // Act
    $response = $controller->__invoke($mockRequest, $mockEstimate);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual($sendResponse);
    expect($response->getStatusCode())->toBe(200);
});
