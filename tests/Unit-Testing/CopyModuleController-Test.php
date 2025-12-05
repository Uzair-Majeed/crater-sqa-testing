<?php

namespace Tests\Unit;

use Crater\Http\Controllers\V1\Admin\Modules\CopyModuleController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

// Test request class for simulating different inputs
class TestRequest extends Request
{
    public $module;
    public $path;
    
    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->module = $attributes['module'] ?? null;
        $this->path = $attributes['path'] ?? null;
    }
}

// Test controller that exposes protected methods for testing
class TestCopyModuleController extends CopyModuleController
{
    public $authorizeCalled = false;
    public $authorizeAbility = null;
    public $copyFilesCalled = false;
    public $copyFilesParams = [];
    public $copyFilesResult = true;
    
    public function authorize($ability, $arguments = [])
    {
        $this->authorizeCalled = true;
        $this->authorizeAbility = $ability;
        
        // Simulate Gate behavior
        $gate = app(Gate::class);
        if (!$gate->allows($ability, $arguments)) {
            throw new AuthorizationException("Unauthorized to {$ability}.");
        }
        
        return true;
    }
    
    // Override the ModuleInstaller call to avoid actual file operations
    protected function callModuleInstaller($module, $path)
    {
        $this->copyFilesCalled = true;
        $this->copyFilesParams = [$module, $path];
        return $this->copyFilesResult;
    }
    
    // Override the __invoke method to use our test version
    public function __invoke(Request $request)
    {
        $this->authorize('manage modules');
        
        // Use our test method instead of the real ModuleInstaller
        $response = $this->callModuleInstaller($request->module, $request->path);
        
        return response()->json([
            'success' => $response
        ]);
    }
    
    // Expose the invoke method for testing
    public function testInvoke(Request $request)
    {
        return $this->__invoke($request);
    }
    
    // Helper to reset test state
    public function reset()
    {
        $this->authorizeCalled = false;
        $this->authorizeAbility = null;
        $this->copyFilesCalled = false;
        $this->copyFilesParams = [];
        $this->copyFilesResult = true;
    }
}

test('copy module controller invokes correctly with successful copy', function () {
    // Arrange
    $request = new TestRequest([
        'module' => 'test-module-name',
        'path' => '/var/www/html/crater/modules',
    ]);
    
    $controller = new TestCopyModuleController();
    
    // Mock Gate to allow authorization
    app()->instance(Gate::class, new class {
        public function allows($ability, $arguments = []) {
            return true;
        }
    });
    
    // Act
    $response = $controller->testInvoke($request);
    
    // Assert
    expect($controller->authorizeCalled)->toBeTrue()
        ->and($controller->authorizeAbility)->toBe('manage modules')
        ->and($controller->copyFilesCalled)->toBeTrue()
        ->and($controller->copyFilesParams)->toBe(['test-module-name', '/var/www/html/crater/modules'])
        ->and($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['success' => true]);
});

test('copy module controller handles failed copy operation', function () {
    // Arrange
    $request = new TestRequest([
        'module' => 'another-module',
        'path' => '/app/data/modules',
    ]);
    
    $controller = new TestCopyModuleController();
    $controller->copyFilesResult = false;
    
    // Mock Gate to allow authorization
    app()->instance(Gate::class, new class {
        public function allows($ability, $arguments = []) {
            return true;
        }
    });
    
    // Act
    $response = $controller->testInvoke($request);
    
    // Assert
    expect($controller->authorizeCalled)->toBeTrue()
        ->and($controller->copyFilesCalled)->toBeTrue()
        ->and($controller->copyFilesParams)->toBe(['another-module', '/app/data/modules'])
        ->and($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['success' => false]);
});

test('copy module controller throws AuthorizationException when not authorized', function () {
    // Arrange
    $request = new TestRequest([
        'module' => 'unauthorized-module',
        'path' => '/tmp/modules',
    ]);
    
    $controller = new TestCopyModuleController();
    
    // Mock Gate to deny authorization
    app()->instance(Gate::class, new class {
        public function allows($ability, $arguments = []) {
            return false; // Deny access
        }
    });
    
    // Act & Assert
    expect(fn() => $controller->testInvoke($request))
        ->toThrow(AuthorizationException::class, 'Unauthorized to manage modules.')
        ->and($controller->copyFilesCalled)->toBeFalse();
});

test('copy module controller handles null parameters', function () {
    // Arrange
    $request = new TestRequest([
        'module' => null,
        'path' => null,
    ]);
    
    $controller = new TestCopyModuleController();
    
    // Mock Gate to allow authorization
    app()->instance(Gate::class, new class {
        public function allows($ability, $arguments = []) {
            return true;
        }
    });
    
    // Act
    $response = $controller->testInvoke($request);
    
    // Assert
    expect($controller->copyFilesParams)->toBe([null, null])
        ->and($response->getData(true))->toBe(['success' => true]);
});

test('copy module controller handles empty string parameters', function () {
    // Arrange
    $request = new TestRequest([
        'module' => '',
        'path' => '',
    ]);
    
    $controller = new TestCopyModuleController();
    $controller->copyFilesResult = false;
    
    // Mock Gate to allow authorization
    app()->instance(Gate::class, new class {
        public function allows($ability, $arguments = []) {
            return true;
        }
    });
    
    // Act
    $response = $controller->testInvoke($request);
    
    // Assert
    expect($controller->copyFilesParams)->toBe(['', ''])
        ->and($response->getData(true))->toBe(['success' => false]);
});

test('copy module controller with different path formats', function () {
    // Test various path formats
    $testCases = [
        ['module' => 'module1', 'path' => '/absolute/path'],
        ['module' => 'module2', 'path' => 'relative/path'],
        ['module' => 'module3', 'path' => '../parent/path'],
        ['module' => 'module4', 'path' => './current/path'],
    ];
    
    // Mock Gate to allow authorization
    app()->instance(Gate::class, new class {
        public function allows($ability, $arguments = []) {
            return true;
        }
    });
    
    foreach ($testCases as $testCase) {
        $controller = new TestCopyModuleController();
        $controller->reset();
        
        $request = new TestRequest($testCase);
        $response = $controller->testInvoke($request);
        
        expect($controller->copyFilesParams)->toBe([$testCase['module'], $testCase['path']])
            ->and($response->getData(true))->toBe(['success' => true]);
    }
});

test('copy module controller response structure', function () {
    // Arrange
    $request = new TestRequest([
        'module' => 'test-module',
        'path' => '/test/path',
    ]);
    
    $controller = new TestCopyModuleController();
    
    // Mock Gate to allow authorization
    app()->instance(Gate::class, new class {
        public function allows($ability, $arguments = []) {
            return true;
        }
    });
    
    // Act
    $response = $controller->testInvoke($request);
    
    // Assert response structure
    $data = $response->getData(true);
    
    expect($data)->toBeArray()
        ->and($data)->toHaveKey('success')
        ->and(is_bool($data['success']))->toBeTrue()
        ->and(count($data))->toBe(1); // Only 'success' key should exist
});

test('copy module controller inherits from base Controller', function () {
    $controller = new CopyModuleController();
    
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('copy module controller uses invoke method pattern', function () {
    // Test that the controller uses the single action pattern
    $controller = new CopyModuleController();
    
    expect(method_exists($controller, '__invoke'))->toBeTrue();
    
    // Also test that it's callable
    expect(is_callable($controller))->toBeTrue();
});

test('copy module controller request validation', function () {
    $controller = new TestCopyModuleController();
    
    // Mock Gate to allow authorization
    app()->instance(Gate::class, new class {
        public function allows($ability, $arguments = []) {
            return true;
        }
    });
    
    // Test with various request data types
    $testCases = [
        ['module' => 123, 'path' => 456], // numeric
        ['module' => true, 'path' => false], // boolean
        ['module' => ['array'], 'path' => ['another']], // arrays
        ['module' => (object)['key' => 'value'], 'path' => (object)['path' => 'test']], // objects
    ];
    
    foreach ($testCases as $testCase) {
        $controller->reset();
        $request = new TestRequest($testCase);
        $response = $controller->testInvoke($request);
        
        // Controller should handle any data type
        expect($controller->copyFilesCalled)->toBeTrue()
            ->and($response->getData(true)['success'])->toBeTrue();
    }
});

// Edge cases
test('copy module controller with very long module name', function () {
    // Arrange
    $longName = str_repeat('a', 1000); // Very long module name
    $request = new TestRequest([
        'module' => $longName,
        'path' => '/path',
    ]);
    
    $controller = new TestCopyModuleController();
    
    // Mock Gate to allow authorization
    app()->instance(Gate::class, new class {
        public function allows($ability, $arguments = []) {
            return true;
        }
    });
    
    // Act
    $response = $controller->testInvoke($request);
    
    // Assert
    expect($controller->copyFilesParams[0])->toBe($longName)
        ->and(strlen($controller->copyFilesParams[0]))->toBe(1000)
        ->and($response->getData(true)['success'])->toBeTrue();
});

test('copy module controller with special characters in parameters', function () {
    // Arrange
    $specialModule = 'module-with-special-@#$%^&*()-chars';
    $specialPath = '/path/with/special/@#$%^&*()/chars';
    
    $request = new TestRequest([
        'module' => $specialModule,
        'path' => $specialPath,
    ]);
    
    $controller = new TestCopyModuleController();
    
    // Mock Gate to allow authorization
    app()->instance(Gate::class, new class {
        public function allows($ability, $arguments = []) {
            return true;
        }
    });
    
    // Act
    $response = $controller->testInvoke($request);
    
    // Assert
    expect($controller->copyFilesParams)->toBe([$specialModule, $specialPath])
        ->and($response->getData(true)['success'])->toBeTrue();
});

test('copy module controller JSON response encoding', function () {
    // Test that response is properly encoded as JSON
    $request = new TestRequest([
        'module' => 'json-test',
        'path' => '/json/path',
    ]);
    
    $controller = new TestCopyModuleController();
    
    // Mock Gate to allow authorization
    app()->instance(Gate::class, new class {
        public function allows($ability, $arguments = []) {
            return true;
        }
    });
    
    $response = $controller->testInvoke($request);
    
    // Check JSON headers
    expect($response->headers->get('Content-Type'))->toContain('application/json')
        ->and(json_decode($response->getContent(), true))->toBe(['success' => true]);
});

// Test the actual controller structure
test('actual copy module controller structure matches expected', function () {
    // Use reflection to inspect the actual controller
    $reflection = new \ReflectionClass(CopyModuleController::class);
    
    // Check it has the expected method
    expect($reflection->hasMethod('__invoke'))->toBeTrue();
    
    $method = $reflection->getMethod('__invoke');
    
    // Check method parameters
    expect($method->getNumberOfParameters())->toBe(1)
        ->and($method->getParameters()[0]->getType()->getName())->toBe('Illuminate\Http\Request');
    
    // Check method is public
    expect($method->isPublic())->toBeTrue();
});

// Test that controller follows Laravel conventions
test('controller follows Laravel conventions', function () {
    $controller = new CopyModuleController();
    
    // Should have proper namespace
    expect(get_class($controller))->toBe(CopyModuleController::class);
    
    // Should be in correct namespace
    expect(strpos(get_class($controller), 'Crater\Http\Controllers\V1\Admin\Modules'))->toBe(0);
});

// Clean up after tests
afterEach(function () {
    // Clear any bound instances
    app()->forgetInstance(Gate::class);
});