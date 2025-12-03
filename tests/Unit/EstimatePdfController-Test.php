<?php

use Crater\Http\Controllers\V1\PDF\EstimatePdfController;
use Crater\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
    $expectedPdfData = ['pdf_data' => 'sample_pdf_json_data'];

    $this->request->shouldReceive('has')
        ->once()
        ->with('preview')
        ->andReturn(true);

    $this->estimate->shouldReceive('getPDFData')
        ->once()
        ->andReturn($expectedPdfData);

    $this->estimate->shouldNotReceive('getGeneratedPDFOrStream');

    $result = ($this->controller)($this->request, $this->estimate);

    expect($result)->toBe($expectedPdfData);
});

test('it returns a generated PDF stream as a Response when the preview parameter is absent', function () {
    $expectedResponse = m::mock(Response::class);
    $expectedResponse->shouldReceive('header')->zeroOrMoreTimes();

    $this->request->shouldReceive('has')
        ->once()
        ->with('preview')
        ->andReturn(false);

    $this->estimate->shouldReceive('getGeneratedPDFOrStream')
        ->once()
        ->with('estimate')
        ->andReturn($expectedResponse);

    $this->estimate->shouldNotReceive('getPDFData');

    $result = ($this->controller)($this->request, $this->estimate);

    expect($result)->toBe($expectedResponse);
});