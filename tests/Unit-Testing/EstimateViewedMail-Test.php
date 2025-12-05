<?php

use Crater\Mail\EstimateViewedMail;
use Illuminate\Mail\Mailable;

// ========== CLASS STRUCTURE TESTS ==========

test('EstimateViewedMail can be instantiated', function () {
    $data = ['estimate_number' => 'EST-001'];
    $mail = new EstimateViewedMail($data);
    
    expect($mail)->toBeInstanceOf(EstimateViewedMail::class);
});

test('EstimateViewedMail extends Mailable', function () {
    $data = ['estimate_number' => 'EST-001'];
    $mail = new EstimateViewedMail($data);
    
    expect($mail)->toBeInstanceOf(Mailable::class);
});

test('EstimateViewedMail is in correct namespace', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Mail');
});

test('EstimateViewedMail is not abstract', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('EstimateViewedMail is instantiable', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== CONSTRUCTOR TESTS ==========

test('constructor assigns data property', function () {
    $data = ['estimate_number' => 'EST-001', 'customer' => 'John Doe'];
    $mail = new EstimateViewedMail($data);
    
    expect($mail->data)->toBe($data);
});

test('constructor handles array data', function () {
    $data = ['key1' => 'value1', 'key2' => 'value2'];
    $mail = new EstimateViewedMail($data);
    
    expect($mail->data)->toBeArray()
        ->and($mail->data)->toHaveKey('key1')
        ->and($mail->data)->toHaveKey('key2');
});

test('constructor handles empty array', function () {
    $data = [];
    $mail = new EstimateViewedMail($data);
    
    expect($mail->data)->toBeArray()
        ->and($mail->data)->toBeEmpty();
});

test('constructor handles null data', function () {
    $mail = new EstimateViewedMail(null);
    
    expect($mail->data)->toBeNull();
});

test('constructor handles complex nested data', function () {
    $data = [
        'estimate' => [
            'number' => 'EST-001',
            'items' => [
                ['name' => 'Item 1', 'price' => 100],
                ['name' => 'Item 2', 'price' => 200]
            ]
        ]
    ];
    $mail = new EstimateViewedMail($data);
    
    expect($mail->data)->toBe($data);
});

// ========== DATA PROPERTY TESTS ==========

test('data property is public', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $property = $reflection->getProperty('data');
    
    expect($property->isPublic())->toBeTrue();
});

test('data property can be accessed directly', function () {
    $data = ['test' => 'value'];
    $mail = new EstimateViewedMail($data);
    
    expect($mail->data['test'])->toBe('value');
});

test('data property can be modified after instantiation', function () {
    $mail = new EstimateViewedMail(['initial' => 'data']);
    $mail->data = ['modified' => 'data'];
    
    expect($mail->data)->toBe(['modified' => 'data']);
});

// ========== METHOD EXISTENCE TESTS ==========

test('EstimateViewedMail has __construct method', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    expect($reflection->hasMethod('__construct'))->toBeTrue();
});

test('EstimateViewedMail has build method', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    expect($reflection->hasMethod('build'))->toBeTrue();
});

test('build method is public', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $method = $reflection->getMethod('build');
    
    expect($method->isPublic())->toBeTrue();
});

test('build method has no parameters', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $method = $reflection->getMethod('build');
    
    expect($method->getNumberOfParameters())->toBe(0);
});

test('build method is not static', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $method = $reflection->getMethod('build');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== BUILD METHOD TESTS ==========

test('build method returns self', function () {
    $data = ['estimate_number' => 'EST-001'];
    $mail = new EstimateViewedMail($data);
    $result = $mail->build();
    
    expect($result)->toBe($mail);
});

test('build method can be called multiple times', function () {
    $data = ['estimate_number' => 'EST-001'];
    $mail = new EstimateViewedMail($data);
    
    $result1 = $mail->build();
    $result2 = $mail->build();
    
    expect($result1)->toBe($mail)
        ->and($result2)->toBe($mail);
});

// ========== TRAITS TESTS ==========

test('EstimateViewedMail uses Queueable trait', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Bus\Queueable');
});

test('EstimateViewedMail uses SerializesModels trait', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Queue\SerializesModels');
});

// ========== INSTANCE TESTS ==========

test('multiple EstimateViewedMail instances can be created', function () {
    $mail1 = new EstimateViewedMail(['data1' => 'value1']);
    $mail2 = new EstimateViewedMail(['data2' => 'value2']);
    
    expect($mail1)->toBeInstanceOf(EstimateViewedMail::class)
        ->and($mail2)->toBeInstanceOf(EstimateViewedMail::class)
        ->and($mail1)->not->toBe($mail2);
});

test('EstimateViewedMail can be cloned', function () {
    $mail = new EstimateViewedMail(['test' => 'data']);
    $clone = clone $mail;
    
    expect($clone)->toBeInstanceOf(EstimateViewedMail::class)
        ->and($clone)->not->toBe($mail)
        ->and($clone->data)->toBe($mail->data);
});

test('EstimateViewedMail can be used in type hints', function () {
    $testFunction = function (EstimateViewedMail $mail) {
        return $mail;
    };
    
    $mail = new EstimateViewedMail(['test' => 'data']);
    $result = $testFunction($mail);
    
    expect($result)->toBe($mail);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('EstimateViewedMail is not final', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('EstimateViewedMail is not an interface', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('EstimateViewedMail is not a trait', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('EstimateViewedMail class is loaded', function () {
    expect(class_exists(EstimateViewedMail::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('EstimateViewedMail uses required classes', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Illuminate\Bus\Queueable')
        ->and($fileContent)->toContain('use Illuminate\Mail\Mailable')
        ->and($fileContent)->toContain('use Illuminate\Queue\SerializesModels');
});

// ========== FILE STRUCTURE TESTS ==========

test('EstimateViewedMail file has expected structure', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class EstimateViewedMail extends Mailable')
        ->and($fileContent)->toContain('public function __construct')
        ->and($fileContent)->toContain('public function build()');
});

test('EstimateViewedMail has compact implementation', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // File should be concise (< 1000 bytes)
    expect(strlen($fileContent))->toBeLessThan(1000);
});

test('EstimateViewedMail has minimal line count', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeLessThan(50);
});

// ========== BUILD METHOD IMPLEMENTATION TESTS ==========

test('build method uses from configuration', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('from(')
        ->and($fileContent)->toContain("config('mail.from.address')")
        ->and($fileContent)->toContain("config('mail.from.name')");
});

test('build method uses markdown view', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('markdown(')
        ->and($fileContent)->toContain('emails.viewed.estimate');
});

test('build method passes data to view', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->data');
});

test('build method returns $this', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('return $this->');
});

// ========== CONSTRUCTOR IMPLEMENTATION TESTS ==========

test('constructor assigns parameter to data property', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->data = $data');
});

test('constructor accepts data parameter', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $method = $reflection->getMethod('__construct');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('data');
});

// ========== DOCUMENTATION TESTS ==========

test('constructor has documentation', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $method = $reflection->getMethod('__construct');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('build method has documentation', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $method = $reflection->getMethod('build');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('build method has return type documentation', function () {
    $reflection = new ReflectionClass(EstimateViewedMail::class);
    $method = $reflection->getMethod('build');
    $docComment = $method->getDocComment();
    
    expect($docComment)->toContain('@return');
});

// ========== DATA INTEGRITY TESTS ==========

test('data is preserved through constructor', function () {
    $originalData = [
        'estimate_number' => 'EST-123',
        'customer_name' => 'Test Customer',
        'total' => 1500.50
    ];
    
    $mail = new EstimateViewedMail($originalData);
    
    expect($mail->data)->toBe($originalData)
        ->and($mail->data['estimate_number'])->toBe('EST-123')
        ->and($mail->data['customer_name'])->toBe('Test Customer')
        ->and($mail->data['total'])->toBe(1500.50);
});

test('different instances have independent data', function () {
    $data1 = ['estimate' => 'EST-001'];
    $data2 = ['estimate' => 'EST-002'];
    
    $mail1 = new EstimateViewedMail($data1);
    $mail2 = new EstimateViewedMail($data2);
    
    expect($mail1->data)->not->toBe($mail2->data)
        ->and($mail1->data['estimate'])->toBe('EST-001')
        ->and($mail2->data['estimate'])->toBe('EST-002');
});

// ========== MAILABLE FEATURES TESTS ==========

test('EstimateViewedMail inherits Mailable methods', function () {
    $mail = new EstimateViewedMail(['test' => 'data']);
    
    expect(method_exists($mail, 'to'))->toBeTrue()
        ->and(method_exists($mail, 'subject'))->toBeTrue()
        ->and(method_exists($mail, 'from'))->toBeTrue();
});

test('EstimateViewedMail can use Mailable features', function () {
    $mail = new EstimateViewedMail(['test' => 'data']);
    
    // Test that we can call Mailable methods
    expect(is_callable([$mail, 'to']))->toBeTrue()
        ->and(is_callable([$mail, 'subject']))->toBeTrue();
});