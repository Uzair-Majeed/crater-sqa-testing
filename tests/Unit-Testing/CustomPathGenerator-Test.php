<?php

use Crater\Generators\CustomPathGenerator;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Mockery as m;

// Helper function to call protected methods using reflection
function callProtectedMethod(object $object, string $methodName, array $args = [])
{
    $reflection = new ReflectionClass($object);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $args);
}

beforeEach(function () {
    $this->generator = new CustomPathGenerator();
});

afterEach(function () {
    m::close(); // Close Mockery expectations
});

test('it implements the PathGenerator interface', function () {
    expect($this->generator)->toBeInstanceOf(PathGenerator::class);
});

// --- Tests for getBasePath (protected method) ---

test('getBasePath returns "Invoices" when media model_type is Invoice::class', function () {
    $media = m::mock(Media::class);
    $media->model_type = Invoice::class;
    $media->shouldNotReceive('getKey'); // getKey should not be called in this branch

    $path = callProtectedMethod($this->generator, 'getBasePath', [$media]);

    expect($path)->toBe('Invoices');
});

test('getBasePath returns "Estimates" when media model_type is Estimate::class', function () {
    $media = m::mock(Media::class);
    $media->model_type = Estimate::class;
    $media->shouldNotReceive('getKey');

    $path = callProtectedMethod($this->generator, 'getBasePath', [$media]);

    expect($path)->toBe('Estimates');
});

test('getBasePath returns "Payments" when media model_type is Payment::class', function () {
    $media = m::mock(Media::class);
    $media->model_type = Payment::class;
    $media->shouldNotReceive('getKey');

    $path = callProtectedMethod($this->generator, 'getBasePath', [$media]);

    expect($path)->toBe('Payments');
});

test('getBasePath returns media key when model_type is unknown', function () {
    $mediaKey = 123;
    $media = m::mock(Media::class);
    $media->model_type = 'App\\Models\\User'; // An arbitrary, unknown model type
    $media->shouldReceive('getKey')->once()->andReturn($mediaKey);

    $path = callProtectedMethod($this->generator, 'getBasePath', [$media]);

    expect($path)->toBe($mediaKey);
});

test('getBasePath returns media key when model_type is null', function () {
    $mediaKey = 456;
    $media = m::mock(Media::class);
    $media->model_type = null; // Explicitly null model_type
    $media->shouldReceive('getKey')->once()->andReturn($mediaKey);

    $path = callProtectedMethod($this->generator, 'getBasePath', [$media]);

    expect($path)->toBe($mediaKey);
});

test('getBasePath returns null when model_type is unknown and media key is null', function () {
    $media = m::mock(Media::class);
    $media->model_type = 'App\\Models\\Product';
    $media->shouldReceive('getKey')->once()->andReturn(null); // Edge case: getKey returns null

    $path = callProtectedMethod($this->generator, 'getBasePath', [$media]);

    expect($path)->toBe(null);
});

// --- Tests for getPath ---

test('getPath appends slash to base path for Invoice model', function () {
    $media = m::mock(Media::class);
    $media->model_type = Invoice::class;
    $media->shouldNotReceive('getKey');

    $path = $this->generator->getPath($media);

    expect($path)->toBe('Invoices/');
});

test('getPath appends slash to media key for unknown model', function () {
    $mediaKey = 789;
    $media = m::mock(Media::class);
    $media->model_type = 'App\\Models\\BlogPost';
    $media->shouldReceive('getKey')->once()->andReturn($mediaKey);

    $path = $this->generator->getPath($media);

    expect($path)->toBe($mediaKey . '/');
});

test('getPath handles null media key gracefully', function () {
    $media = m::mock(Media::class);
    $media->model_type = 'App\\Models\\Article';
    $media->shouldReceive('getKey')->once()->andReturn(null);

    $path = $this->generator->getPath($media);

    // PHP will cast null to an empty string in string concatenation
    expect($path)->toBe('/');
});

// --- Tests for getPathForConversions ---

test('getPathForConversions appends correct path for Estimate model', function () {
    $media = m::mock(Media::class);
    $media->model_type = Estimate::class;
    $media->shouldNotReceive('getKey');

    $path = $this->generator->getPathForConversions($media);

    expect($path)->toBe('Estimates/conversations/');
});

test('getPathForConversions appends correct path to media key for unknown model', function () {
    $mediaKey = 1011;
    $media = m::mock(Media::class);
    $media->model_type = 'App\\Models\\Comment';
    $media->shouldReceive('getKey')->once()->andReturn($mediaKey);

    $path = $this->generator->getPathForConversions($media);

    expect($path)->toBe($mediaKey . '/conversations/');
});

test('getPathForConversions handles null media key gracefully', function () {
    $media = m::mock(Media::class);
    $media->model_type = 'App\\Models\\Review';
    $media->shouldReceive('getKey')->once()->andReturn(null);

    $path = $this->generator->getPathForConversions($media);

    expect($path)->toBe('/conversations/');
});

// --- Tests for getPathForResponsiveImages ---

test('getPathForResponsiveImages appends correct path for Payment model', function () {
    $media = m::mock(Media::class);
    $media->model_type = Payment::class;
    $media->shouldNotReceive('getKey');

    $path = $this->generator->getPathForResponsiveImages($media);

    expect($path)->toBe('Payments/responsive-images/');
});

test('getPathForResponsiveImages appends correct path to media key for unknown model', function () {
    $mediaKey = 1213;
    $media = m::mock(Media::class);
    $media->model_type = 'App\\Models\\Setting';
    $media->shouldReceive('getKey')->once()->andReturn($mediaKey);

    $path = $this->generator->getPathForResponsiveImages($media);

    expect($path)->toBe($mediaKey . '/responsive-images/');
});

test('getPathForResponsiveImages handles null media key gracefully', function () {
    $media = m::mock(Media::class);
    $media->model_type = 'App\\Models\\Option';
    $media->shouldReceive('getKey')->once()->andReturn(null);

    $path = $this->generator->getPathForResponsiveImages($media);

    expect($path)->toBe('/responsive-images/');
});
 
