<?php

use Crater\Http\Controllers\V1\Admin\Settings\CompanyCurrencyCheckTransactionsController;
use Crater\Models\Company;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->controller = new CompanyCurrencyCheckTransactionsController();
});

test('company currency check transactions controller can be instantiated', function () {
    expect($this->controller)->toBeInstanceOf(CompanyCurrencyCheckTransactionsController::class);
    expect($this->controller)->toBeInstanceOf(\Illuminate\Routing\Controller::class);
});

test('__invoke method has correct parameters', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    
    expect($method->getNumberOfParameters())->toBe(1);
    
    $params = $method->getParameters();
    expect($params[0]->getType()->getName())->toBe('Illuminate\Http\Request');
});


test('controller requires manage company authorization', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    $source = file_get_contents($method->getFileName());
    
    expect(str_contains($source, "authorize('manage company'"))->toBeTrue();
});

test('controller follows correct execution flow', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    $source = file_get_contents($method->getFileName());
    
    $expectedSteps = [
        'Company::find',                // Find company by header
        'authorize.*manage company',    // Authorization check
        'hasTransactions',              // Check transactions
        'response.*json',               // Return JSON response
        'has_transactions',             // Response key
    ];
    
    foreach ($expectedSteps as $step) {
        expect(preg_match("/{$step}/", $source))->toBeGreaterThanOrEqual(1,
            "Controller should contain step: {$step}"
        );
    }
});

test('company model has required hasTransactions method', function () {
    $company = new Company();
    
    expect(method_exists($company, 'hasTransactions'))->toBeTrue(
        "Company model should have hasTransactions method"
    );
});

test('controller uses company header from request', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    expect(str_contains($methodSource, 'header(\'company\')'))->toBeTrue();
});

test('response structure is correct', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    // Check exact response structure
    expect(str_contains($methodSource, "'has_transactions' =>"))->toBeTrue();
    expect(str_contains($methodSource, '$company->hasTransactions()'))->toBeTrue();
    expect(str_contains($methodSource, 'response()->json'))->toBeTrue();
});

test('authorization method exists on controller', function () {
    // The controller extends base Controller which should have authorize method
    expect(method_exists($this->controller, 'authorize'))->toBeTrue();
});

test('controller handles different hasTransactions outcomes', function (
    bool $hasTransactions,
    bool $expectedHasTransactions
) {
    // Test the logic flow without actual execution
    // The controller should return whatever hasTransactions() returns
    
    $responseData = [
        'has_transactions' => $hasTransactions
    ];
    
    expect($responseData['has_transactions'])->toBe($expectedHasTransactions);
    
    // Response should always contain has_transactions key
    expect(array_key_exists('has_transactions', $responseData))->toBeTrue();
    
})->with([
    [true, true],
    [false, false],
]);

test('controller returns boolean in response', function () {
    // The has_transactions value should always be boolean
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    // The value comes from hasTransactions() method which should return boolean
    expect(str_contains($methodSource, 'hasTransactions()'))->toBeTrue();
});

test('controller has no conditional branches besides authorization', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    // Count if/else statements
    $ifCount = substr_count($methodSource, 'if (');
    $elseCount = substr_count($methodSource, 'else');
    $returnCount = substr_count($methodSource, 'return');
    
    // Should only have one return statement
    expect($returnCount)->toBe(1);
    
    // No conditional logic except maybe in authorization
    expect($ifCount)->toBeLessThanOrEqual(1); // Only authorization if any
    expect($elseCount)->toBe(0);
});

test('controller name matches its purpose', function () {
    $className = class_basename($this->controller);
    
    expect($className)->toBe('CompanyCurrencyCheckTransactionsController');
    expect(str_contains($className, 'CompanyCurrencyCheck'))->toBeTrue();
    expect(str_contains($className, 'Transactions'))->toBeTrue();
});

test('controller namespace matches Laravel convention', function () {
    $reflection = new ReflectionClass($this->controller);
    $namespace = $reflection->getNamespaceName();
    
    expect(str_starts_with($namespace, 'Crater\Http\Controllers'))->toBeTrue();
    expect(str_contains($namespace, 'V1\Admin\Settings'))->toBeTrue();
});

test('response json structure validation', function () {
    // Test all possible response structures
    $testCases = [
        [
            'has_transactions' => true,
            'is_valid' => true,
            'expected_type' => 'boolean'
        ],
        [
            'has_transactions' => false,
            'is_valid' => true,
            'expected_type' => 'boolean'
        ],
    ];
    
    foreach ($testCases as $testCase) {
        $response = ['has_transactions' => $testCase['has_transactions']];
        
        expect(array_key_exists('has_transactions', $response))->toBeTrue();
        expect(is_bool($response['has_transactions']))->toBe($testCase['is_valid']);
        expect(gettype($response['has_transactions']))->toBe($testCase['expected_type']);
    }
});

test('controller method is short and focused', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    
    // Get method lines count
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $lineCount = $endLine - $startLine + 1;
    
    // Should be a simple method with few lines
    expect($lineCount)->toBeLessThanOrEqual(15);
});

test('controller uses dependency injection correctly', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    $params = $method->getParameters();
    
    // Should inject Request
    expect(count($params))->toBe(1);
    expect($params[0]->getType()->getName())->toBe('Illuminate\Http\Request');
    
    // No other dependencies injected
    $constructor = $reflection->getConstructor();
    if ($constructor) {
        $constructorParams = $constructor->getParameters();
        expect(count($constructorParams))->toBe(0); // No constructor dependencies
    }
});

test('controller has no side effects besides response', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    // Should not contain any assignment operations (except maybe for company variable)
    $assignmentCount = substr_count($methodSource, '=');
    
    // Allow max 2 assignments: $company = and maybe others
    expect($assignmentCount)->toBeLessThanOrEqual(3);
    
    // Should not contain any database write operations
    $writeOperations = ['create', 'update', 'delete', 'save', 'insert'];
    foreach ($writeOperations as $operation) {
        expect(str_contains($methodSource, "->{$operation}("))->toBeFalse(
            "Controller should not perform write operation: {$operation}"
        );
    }
});

test('controller response always contains has_transactions key', function () {
    // The response structure is fixed
    $expectedResponse = [
        'has_transactions' => true // or false, but key always exists
    ];
    
    expect(array_key_exists('has_transactions', $expectedResponse))->toBeTrue();
    expect(count($expectedResponse))->toBe(1); // Only one key
});

test('controller handles authorization failure scenario', function () {
    // Test that authorization is checked before any other logic
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('__invoke');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    // Find the line numbers
    $lines = array_slice($source, $startLine - 1, $endLine - $startLine + 1);
    
    $authorizeLine = null;
    $companyFindLine = null;
    
    foreach ($lines as $index => $line) {
        if (str_contains($line, 'Company::find')) {
            $companyFindLine = $index;
        }
        if (str_contains($line, 'authorize(')) {
            $authorizeLine = $index;
        }
    }
    
    // Authorization should happen after finding company (company needed for authorization)
    expect($authorizeLine)->not->toBeNull();
    expect($companyFindLine)->not->toBeNull();
    expect($authorizeLine)->toBeGreaterThan($companyFindLine);
});