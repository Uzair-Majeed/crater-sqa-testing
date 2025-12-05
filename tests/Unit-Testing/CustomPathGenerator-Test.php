<?php

use Crater\Generators\CustomPathGenerator;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

// Helper to create a test Media object
function createTestMedia($modelType, $key = 123)
{
    return new class($modelType, $key) extends Media {
        private $testModelType;
        private $testKey;
        
        public function __construct($modelType, $key)
        {
            $this->testModelType = $modelType;
            $this->testKey = $key;
            // Don't call parent constructor to avoid database requirements
        }
        
        public function __get($name)
        {
            if ($name === 'model_type') {
                return $this->testModelType;
            }
            return parent::__get($name);
        }
        
        public function getKey()
        {
            return $this->testKey;
        }
    };
}

// Test that generator implements PathGenerator interface
test('CustomPathGenerator implements PathGenerator interface', function () {
    $generator = new CustomPathGenerator();
    expect($generator)->toBeInstanceOf(PathGenerator::class);
});

// Test that generator can be instantiated
test('CustomPathGenerator can be instantiated', function () {
    $generator = new CustomPathGenerator();
    expect($generator)->toBeInstanceOf(CustomPathGenerator::class);
});

// Test getPath for Invoice model
test('getPath returns correct path for Invoice model', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Invoice::class, 123);
    
    $path = $generator->getPath($media);
    
    expect($path)->toBe('Invoices/');
});

// Test getPath for Estimate model
test('getPath returns correct path for Estimate model', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Estimate::class, 456);
    
    $path = $generator->getPath($media);
    
    expect($path)->toBe('Estimates/');
});

// Test getPath for Payment model
test('getPath returns correct path for Payment model', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Payment::class, 789);
    
    $path = $generator->getPath($media);
    
    expect($path)->toBe('Payments/');
});

// Test getPath for unknown model type
test('getPath returns media key path for unknown model type', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia('App\\Models\\User', 999);
    
    $path = $generator->getPath($media);
    
    expect($path)->toBe('999/');
});

// Test getPath with null model_type
test('getPath handles null model_type', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(null, 111);
    
    $path = $generator->getPath($media);
    
    expect($path)->toBe('111/');
});

// Test getPathForConversions for Invoice
test('getPathForConversions returns correct path for Invoice model', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Invoice::class, 123);
    
    $path = $generator->getPathForConversions($media);
    
    expect($path)->toBe('Invoices/conversations/');
});

// Test getPathForConversions for Estimate
test('getPathForConversions returns correct path for Estimate model', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Estimate::class, 456);
    
    $path = $generator->getPathForConversions($media);
    
    expect($path)->toBe('Estimates/conversations/');
});

// Test getPathForConversions for Payment
test('getPathForConversions returns correct path for Payment model', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Payment::class, 789);
    
    $path = $generator->getPathForConversions($media);
    
    expect($path)->toBe('Payments/conversations/');
});

// Test getPathForConversions for unknown model
test('getPathForConversions returns media key path for unknown model', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia('App\\Models\\Product', 555);
    
    $path = $generator->getPathForConversions($media);
    
    expect($path)->toBe('555/conversations/');
});

// Test getPathForResponsiveImages for Invoice
test('getPathForResponsiveImages returns correct path for Invoice model', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Invoice::class, 123);
    
    $path = $generator->getPathForResponsiveImages($media);
    
    expect($path)->toBe('Invoices/responsive-images/');
});

// Test getPathForResponsiveImages for Estimate
test('getPathForResponsiveImages returns correct path for Estimate model', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Estimate::class, 456);
    
    $path = $generator->getPathForResponsiveImages($media);
    
    expect($path)->toBe('Estimates/responsive-images/');
});

// Test getPathForResponsiveImages for Payment
test('getPathForResponsiveImages returns correct path for Payment model', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Payment::class, 789);
    
    $path = $generator->getPathForResponsiveImages($media);
    
    expect($path)->toBe('Payments/responsive-images/');
});

// Test getPathForResponsiveImages for unknown model
test('getPathForResponsiveImages returns media key path for unknown model', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia('App\\Models\\Comment', 777);
    
    $path = $generator->getPathForResponsiveImages($media);
    
    expect($path)->toBe('777/responsive-images/');
});

// Test all three methods with same media object
test('all path methods work consistently with same media object', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Invoice::class, 100);
    
    expect($generator->getPath($media))->toBe('Invoices/')
        ->and($generator->getPathForConversions($media))->toBe('Invoices/conversations/')
        ->and($generator->getPathForResponsiveImages($media))->toBe('Invoices/responsive-images/');
});

// Test with different media keys
test('methods use media key correctly for unknown models', function () {
    $generator = new CustomPathGenerator();
    $media1 = createTestMedia('App\\Models\\User', 1);
    $media2 = createTestMedia('App\\Models\\Post', 2);
    
    expect($generator->getPath($media1))->toBe('1/')
        ->and($generator->getPath($media2))->toBe('2/');
});

// Test that paths always end with slash
test('all path methods return paths ending with slash', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Invoice::class, 123);
    
    $path1 = $generator->getPath($media);
    $path2 = $generator->getPathForConversions($media);
    $path3 = $generator->getPathForResponsiveImages($media);
    
    expect($path1)->toEndWith('/')
        ->and($path2)->toEndWith('/')
        ->and($path3)->toEndWith('/');
});

// Test generator with multiple different model types
test('generator handles multiple model types correctly', function () {
    $generator = new CustomPathGenerator();
    
    $modelTypes = [
        Invoice::class => 'Invoices',
        Estimate::class => 'Estimates',
        Payment::class => 'Payments',
    ];
    
    foreach ($modelTypes as $modelClass => $expectedFolder) {
        $media = createTestMedia($modelClass, 999);
        expect($generator->getPath($media))->toBe($expectedFolder . '/');
    }
});

// Test that generator is reusable
test('generator instance can be reused for multiple media objects', function () {
    $generator = new CustomPathGenerator();
    $media1 = createTestMedia(Invoice::class, 1);
    $media2 = createTestMedia(Estimate::class, 2);
    
    $path1 = $generator->getPath($media1);
    $path2 = $generator->getPath($media2);
    
    expect($path1)->toBe('Invoices/')
        ->and($path2)->toBe('Estimates/');
});

// Test method existence
test('generator has all required methods', function () {
    $generator = new CustomPathGenerator();
    
    expect(method_exists($generator, 'getPath'))->toBeTrue()
        ->and(method_exists($generator, 'getPathForConversions'))->toBeTrue()
        ->and(method_exists($generator, 'getPathForResponsiveImages'))->toBeTrue();
});

// Test with string media keys
test('generator handles string media keys', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia('App\\Models\\Article', 'abc-123');
    
    $path = $generator->getPath($media);
    
    expect($path)->toBe('abc-123/');
});

// Test with large media keys
test('generator handles large media keys', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia('App\\Models\\File', 999999999);
    
    $path = $generator->getPath($media);
    
    expect($path)->toBe('999999999/');
});

// Test conversations path structure
test('conversations path has correct structure', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Invoice::class, 100);
    
    $path = $generator->getPathForConversions($media);
    
    expect($path)->toContain('conversations')
        ->and($path)->toStartWith('Invoices/');
});

// Test responsive images path structure
test('responsive images path has correct structure', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia(Payment::class, 200);
    
    $path = $generator->getPathForResponsiveImages($media);
    
    expect($path)->toContain('responsive-images')
        ->and($path)->toStartWith('Payments/');
});

// Test with zero as media key
test('generator handles zero as media key', function () {
    $generator = new CustomPathGenerator();
    $media = createTestMedia('App\\Models\\Test', 0);
    
    $path = $generator->getPath($media);
    
    expect($path)->toBe('0/');
});