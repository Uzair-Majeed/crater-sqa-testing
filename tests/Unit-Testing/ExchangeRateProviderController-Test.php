<?php

use Crater\Http\Controllers\V1\Admin\ExchangeRate\ExchangeRateProviderController;

// ========== CLASS STRUCTURE TESTS ==========

test('ExchangeRateProviderController can be instantiated', function () {
    $controller = new ExchangeRateProviderController();
    expect($controller)->toBeInstanceOf(ExchangeRateProviderController::class);
});

test('ExchangeRateProviderController extends Controller', function () {
    $controller = new ExchangeRateProviderController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('ExchangeRateProviderController is in correct namespace', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\ExchangeRate');
});

test('ExchangeRateProviderController is not abstract', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    expect($reflection->isAbstract())->toBeFalse();
});

test('ExchangeRateProviderController is instantiable', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    expect($reflection->isInstantiable())->toBeTrue();
});

// ========== METHOD EXISTENCE TESTS ==========

test('ExchangeRateProviderController has index method', function () {
    $controller = new ExchangeRateProviderController();
    expect(method_exists($controller, 'index'))->toBeTrue();
});

test('ExchangeRateProviderController has store method', function () {
    $controller = new ExchangeRateProviderController();
    expect(method_exists($controller, 'store'))->toBeTrue();
});

test('ExchangeRateProviderController has show method', function () {
    $controller = new ExchangeRateProviderController();
    expect(method_exists($controller, 'show'))->toBeTrue();
});

test('ExchangeRateProviderController has update method', function () {
    $controller = new ExchangeRateProviderController();
    expect(method_exists($controller, 'update'))->toBeTrue();
});

test('ExchangeRateProviderController has destroy method', function () {
    $controller = new ExchangeRateProviderController();
    expect(method_exists($controller, 'destroy'))->toBeTrue();
});

// ========== INDEX METHOD TESTS ==========

test('index method is public', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('index');
    
    expect($method->isPublic())->toBeTrue();
});

test('index method accepts Request parameter', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('index');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

test('index method is not static', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('index');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== STORE METHOD TESTS ==========

test('store method is public', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('store');
    
    expect($method->isPublic())->toBeTrue();
});

test('store method accepts ExchangeRateProviderRequest parameter', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('store');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('request');
});

test('store method is not static', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('store');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== SHOW METHOD TESTS ==========

test('show method is public', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('show');
    
    expect($method->isPublic())->toBeTrue();
});

test('show method accepts ExchangeRateProvider parameter', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('show');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('exchangeRateProvider');
});

test('show method is not static', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('show');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== UPDATE METHOD TESTS ==========

test('update method is public', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('update');
    
    expect($method->isPublic())->toBeTrue();
});

test('update method accepts two parameters', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('update');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[1]->getName())->toBe('exchangeRateProvider');
});

test('update method is not static', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('update');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== DESTROY METHOD TESTS ==========

test('destroy method is public', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('destroy');
    
    expect($method->isPublic())->toBeTrue();
});

test('destroy method accepts ExchangeRateProvider parameter', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('destroy');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('exchangeRateProvider');
});

test('destroy method is not static', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('destroy');
    
    expect($method->isStatic())->toBeFalse();
});

// ========== INSTANCE TESTS ==========

test('multiple ExchangeRateProviderController instances can be created', function () {
    $controller1 = new ExchangeRateProviderController();
    $controller2 = new ExchangeRateProviderController();
    
    expect($controller1)->toBeInstanceOf(ExchangeRateProviderController::class)
        ->and($controller2)->toBeInstanceOf(ExchangeRateProviderController::class)
        ->and($controller1)->not->toBe($controller2);
});

test('ExchangeRateProviderController can be cloned', function () {
    $controller = new ExchangeRateProviderController();
    $clone = clone $controller;
    
    expect($clone)->toBeInstanceOf(ExchangeRateProviderController::class)
        ->and($clone)->not->toBe($controller);
});

test('ExchangeRateProviderController can be used in type hints', function () {
    $testFunction = function (ExchangeRateProviderController $controller) {
        return $controller;
    };
    
    $controller = new ExchangeRateProviderController();
    $result = $testFunction($controller);
    
    expect($result)->toBe($controller);
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('ExchangeRateProviderController is not final', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    expect($reflection->isFinal())->toBeFalse();
});

test('ExchangeRateProviderController is not an interface', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    expect($reflection->isInterface())->toBeFalse();
});

test('ExchangeRateProviderController is not a trait', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    expect($reflection->isTrait())->toBeFalse();
});

test('ExchangeRateProviderController class is loaded', function () {
    expect(class_exists(ExchangeRateProviderController::class))->toBeTrue();
});

// ========== IMPORTS TESTS ==========

test('ExchangeRateProviderController uses required classes', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('use Crater\Http\Controllers\Controller')
        ->and($fileContent)->toContain('use Crater\Http\Requests\ExchangeRateProviderRequest')
        ->and($fileContent)->toContain('use Crater\Http\Resources\ExchangeRateProviderResource')
        ->and($fileContent)->toContain('use Crater\Models\ExchangeRateProvider')
        ->and($fileContent)->toContain('use Illuminate\Http\Request');
});

// ========== FILE STRUCTURE TESTS ==========

test('ExchangeRateProviderController file has expected structure', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('class ExchangeRateProviderController extends Controller')
        ->and($fileContent)->toContain('public function index')
        ->and($fileContent)->toContain('public function store')
        ->and($fileContent)->toContain('public function show')
        ->and($fileContent)->toContain('public function update')
        ->and($fileContent)->toContain('public function destroy');
});

test('ExchangeRateProviderController has reasonable line count', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeGreaterThan(50)
        ->and($lineCount)->toBeLessThan(200);
});

// ========== DOCUMENTATION TESTS ==========

test('index method has documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('index');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('store method has documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('store');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('show method has documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('show');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('update method has documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('update');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('destroy method has documentation', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $method = $reflection->getMethod('destroy');
    
    expect($method->getDocComment())->not->toBeFalse();
});

// ========== IMPLEMENTATION TESTS ==========

test('index method uses authorize', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'viewAny\', ExchangeRateProvider::class)');
});

test('index method uses whereCompany scope', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('ExchangeRateProvider::whereCompany()');
});

test('index method uses paginate', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->paginate($limit)');
});

test('index method returns resource collection', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('ExchangeRateProviderResource::collection');
});

test('store method uses checkActiveCurrencies', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('ExchangeRateProvider::checkActiveCurrencies');
});

test('store method uses checkExchangeRateProviderStatus', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('ExchangeRateProvider::checkExchangeRateProviderStatus');
});

test('store method uses createFromRequest', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('ExchangeRateProvider::createFromRequest');
});

test('show method returns ExchangeRateProviderResource', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('new ExchangeRateProviderResource($exchangeRateProvider)');
});

test('update method uses checkUpdateActiveCurrencies', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$exchangeRateProvider->checkUpdateActiveCurrencies');
});

test('update method uses updateFromRequest', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$exchangeRateProvider->updateFromRequest');
});

test('destroy method checks active status', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$exchangeRateProvider->active');
});

test('destroy method calls delete', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$exchangeRateProvider->delete()');
});

test('destroy method returns success response', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'success\' => true');
});

// ========== AUTHORIZATION TESTS ==========

test('all methods use authorization', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'viewAny\'')
        ->and($fileContent)->toContain('$this->authorize(\'create\'')
        ->and($fileContent)->toContain('$this->authorize(\'view\'')
        ->and($fileContent)->toContain('$this->authorize(\'update\'')
        ->and($fileContent)->toContain('$this->authorize(\'delete\'');
});

// ========== ERROR HANDLING TESTS ==========

test('store method handles currency already used error', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Currency used.');
});

test('update method handles currency already used error', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    // Should appear twice (in store and update)
    $count = substr_count($fileContent, 'Currency used.');
    expect($count)->toBeGreaterThanOrEqual(2);
});

test('destroy method handles active provider error', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Provider Active.');
});

// ========== PARENT CLASS TESTS ==========

test('ExchangeRateProviderController parent is Controller', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $parent = $reflection->getParentClass();
    
    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe('Crater\Http\Controllers\Controller');
});

// ========== METHOD COUNT TESTS ==========

test('ExchangeRateProviderController has exactly 5 public methods', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    // Filter out inherited methods
    $ownMethods = array_filter($methods, function($method) {
        return $method->class === ExchangeRateProviderController::class;
    });
    
    expect(count($ownMethods))->toBe(5);
});

// ========== RESPONDJON HELPER TESTS ==========

test('controller uses respondJson helper', function () {
    $reflection = new ReflectionClass(ExchangeRateProviderController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('respondJson');
});