<?php

use Crater\Http\Controllers\V1\PDF\EstimatePdfController;
use Crater\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Http\Response; // Required for mocking a Response object
use Mockery as m;

// Set up mocks and controller before each test
beforeEach(function () {
    $this->controller = new EstimatePdfController();
    $this->request = m::mock(Request::class);
    $this->estimate = m::mock(Estimate::class);
});

// Clean up mocks after each test
afterEach(function () {
    m::close();
});

test('it returns PDF data when the preview parameter is present', function () {
    // Arrange
    // In a real scenario, getPDFData might return an array that will be converted to JSON,
    // or direct content to be displayed. For unit testing, we control the return.
    $expectedPdfData = ['pdf_data' => 'sample_pdf_json_data'];

    // Configure the request mock to indicate 'preview' parameter is present
    $this->request->shouldReceive('has')
        ->once()
        ->with('preview')
        ->andReturn(true);

    // Configure the estimate mock to return specific data when getPDFData is called
    $this->estimate->shouldReceive('getPDFData')
        ->once()
        ->andReturn($expectedPdfData);

    // Ensure getGeneratedPDFOrStream is NOT called in this branch
    $this->estimate->shouldNotReceive('getGeneratedPDFOrStream');

    // Act
    $result = ($this->controller)($this->request, $this->estimate);

    // Assert
    expect($result)->toBe($expectedPdfData);
});

test('it returns a generated PDF stream as a Response when the preview parameter is absent', function () {
    // Arrange
    // The __invoke method signature suggests returning an Illuminate\Http\Response.
    // getGeneratedPDFOrStream is expected to return such a response or stream.
    $expectedResponse = m::mock(Response::class);
    $expectedResponse->shouldReceive('header')->zeroOrMoreTimes(); // Minimal mock behavior if the response is interacted with

    // Configure the request mock to indicate 'preview' parameter is absent.
    // This covers scenarios where 'preview' is not in the request, or is present but falsy.
    $this->request->shouldReceive('has')
        ->once()
        ->with('preview')
        ->andReturn(false);

    // Configure the estimate mock to return a Response when getGeneratedPDFOrStream is called
    $this->estimate->shouldReceive('getGeneratedPDFOrStream')
        ->once()
        ->with('estimate') // Ensure the correct type argument is passed
        ->andReturn($expectedResponse);

    // Ensure getPDFData is NOT called in this branch
    $this->estimate->shouldNotReceive('getPDFData');

    // Act
    $result = ($this->controller)($this->request, $this->estimate);

    // Assert
    expect($result)->toBe($expectedResponse);
});
