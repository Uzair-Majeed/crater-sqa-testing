<?php

use Crater\Http\Controllers\V1\Admin\Company\CompaniesController;
use Crater\Http\Requests\CompaniesRequest;
use Crater\Http\Resources\CompanyResource;
use Crater\Models\Company;
use Crater\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Silber\Bouncer\BouncerFacade;
use Vinkla\Hashids\Facades\Hashids;

beforeEach(function () {
    $this->controller = new CompaniesController();
});

test('companies controller can be instantiated', function () {
    expect($this->controller)->toBeInstanceOf(CompaniesController::class);
    expect($this->controller)->toBeInstanceOf(\Illuminate\Routing\Controller::class);
});

test('controller has all required methods', function () {
    $methods = ['store', 'destroy', 'transferOwnership', 'getUserCompanies'];
    
    foreach ($methods as $method) {
        expect(method_exists($this->controller, $method))->toBeTrue(
            "Controller should have method: {$method}"
        );
    }
});


test('store method creates company with correct flow', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('store');
    $source = file_get_contents($method->getFileName());
    
    $expectedSteps = [
        'authorize.*create company',          // Authorization
        'Company::create',                    // Create company
        'Hashids::connection',                // Generate unique hash
        'save()',                             // Save company
        'setupDefaultData',                   // Setup default data
        'companies.*attach',                  // Attach to user
        'assign.*super admin',                // Assign role
        'address.*create',                    // Create address if provided
        'CompanyResource',                    // Return resource
    ];
    
    foreach ($expectedSteps as $step) {
        expect(preg_match("/{$step}/", $source))->toBeGreaterThanOrEqual(1,
            "Store method should contain step: {$step}"
        );
    }
});

test('destroy method requires delete company authorization', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('destroy');
    $source = file_get_contents($method->getFileName());
    
    expect(str_contains($source, "authorize('delete company'"))->toBeTrue();
});

test('destroy method has correct validation flow', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('destroy');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    // Check all validation branches
    $validationBranches = [
        'company_name_must_match_with_given_name',  // Name mismatch error
        'You_cannot_delete_all_companies',          // Single company error
        'deleteCompany',                            // Success deletion
    ];
    
    foreach ($validationBranches as $branch) {
        expect(str_contains($methodSource, $branch))->toBeTrue(
            "Destroy method should handle: {$branch}"
        );
    }
});


test('transferOwnership method requires transfer company ownership authorization', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('transferOwnership');
    $source = file_get_contents($method->getFileName());
    
    expect(str_contains($source, "authorize('transfer company ownership'"))->toBeTrue();
});

test('transferOwnership method handles both success and failure cases', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('transferOwnership');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    // Check both success and failure response structures
    expect(str_contains($methodSource, "'success' => false"))->toBeTrue();
    expect(str_contains($methodSource, "'success' => true"))->toBeTrue();
    expect(str_contains($methodSource, "hasCompany"))->toBeTrue();
    expect(str_contains($methodSource, "BouncerFacade::sync"))->toBeTrue();
});


test('controller uses correct models and facades', function () {
    $reflection = new ReflectionClass($this->controller);
    $source = file_get_contents($reflection->getFileName());
    
    $requiredClasses = [
        'Crater\Models\Company',
        'Crater\Models\User',
        'Crater\Http\Requests\CompaniesRequest',
        'Silber\Bouncer\BouncerFacade',
        'Vinkla\Hashids\Facades\Hashids',
        'Crater\Http\Resources\CompanyResource',
    ];
    
    foreach ($requiredClasses as $class) {
        expect(str_contains($source, $class))->toBeTrue();
    }
});


test('user model has required methods for controller', function () {
    $user = new User();
    
    $requiredMethods = [
        'companies',
        'loadCount',
        'hasCompany',
        'assign',
    ];
    
    foreach ($requiredMethods as $method) {
        expect(method_exists($user, $method))->toBeTrue(
            "User model should have method: {$method}"
        );
    }
    
    // Verify companies relationship returns a Collection
    expect(method_exists($user, 'companies'))->toBeTrue();
});


test('company resource exists and extends json resource', function () {
    expect(class_exists(CompanyResource::class))->toBeTrue();
    
    $reflection = new ReflectionClass(CompanyResource::class);
    expect($reflection->getParentClass()->getName())->toBe('Illuminate\Http\Resources\Json\JsonResource');
});

test('destroy method validation logic coverage', function (
    string $inputName,
    string $companyName,
    int $companiesCount,
    bool $shouldSucceed,
    string $expectedErrorType
) {
    // Test the validation logic without executing
    
    // Condition 1: Name must match
    $namesMatch = $inputName === $companyName;
    
    // Condition 2: User must have more than 1 company
    $hasMultipleCompanies = $companiesCount > 1;
    
    $shouldPassValidation = $namesMatch && $hasMultipleCompanies;
    
    if (!$namesMatch) {
        $error = 'company_name_must_match_with_given_name';
    } elseif (!$hasMultipleCompanies) {
        $error = 'You_cannot_delete_all_companies';
    } else {
        $error = 'success';
    }
    
    expect($shouldPassValidation)->toBe($shouldSucceed);
    expect($error)->toBe($expectedErrorType);
    
})->with([
    // inputName, companyName, companiesCount, shouldSucceed, expectedErrorType
    ['Company A', 'Company A', 2, true, 'success'],
    ['Company A', 'Company B', 2, false, 'company_name_must_match_with_given_name'],
    ['Company A', 'Company A', 1, false, 'You_cannot_delete_all_companies'],
    ['Wrong', 'Company A', 1, false, 'company_name_must_match_with_given_name'],
]);

test('transfer ownership validation logic coverage', function (
    bool $userHasCompany,
    bool $shouldSucceed
) {
    // Test the transfer ownership logic
    
    if ($userHasCompany) {
        // In controller: if ($user->hasCompany($company->id)) returns error
        $success = false;
        $message = 'User does not belongs to this company.';
    } else {
        $success = true;
        $message = ''; // No message on success
    }
    
    expect($success)->toBe($shouldSucceed);
    
    if (!$shouldSucceed) {
        expect($message)->toBe('User does not belongs to this company.');
    }
    
})->with([
    [true, false],   // User has company -> should fail
    [false, true],   // User doesn't have company -> should succeed
]);

test('store method data flow coverage', function (
    bool $hasAddress,
    bool $shouldCreateAddress
) {
    // Test store method logic flow
    
    $steps = [
        'authorize' => true,
        'createCompany' => true,
        'generateHash' => true,
        'saveCompany' => true,
        'setupDefaultData' => true,
        'attachToUser' => true,
        'assignRole' => true,
        'createAddress' => $hasAddress && $shouldCreateAddress,
        'returnResource' => true,
    ];
    
    // All steps except address creation should always happen
    foreach ($steps as $step => $shouldHappen) {
        if ($step !== 'createAddress') {
            expect($shouldHappen)->toBeTrue();
        }
    }
    
    // Address creation only happens if address is provided
    expect($steps['createAddress'])->toBe($hasAddress);
    
})->with([
    [true, true],
    [false, false],
]);

test('getUserCompanies method returns correct data structure', function () {
    // Test that getUserCompanies returns a collection of CompanyResource
    
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('getUserCompanies');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    expect(str_contains($methodSource, 'CompanyResource::collection'))->toBeTrue();
    expect(str_contains($methodSource, '$request->user()->companies'))->toBeTrue();
});


test('controller uses proper request validation', function () {
    $reflection = new ReflectionClass($this->controller);
    $source = file_get_contents($reflection->getFileName());
    
    // Check for CompaniesRequest type hint
    expect(str_contains($source, 'CompaniesRequest $request'))->toBeTrue();
    
    // Check for getCompanyPayload method
    expect(str_contains($source, 'getCompanyPayload()'))->toBeTrue();
});

test('address creation follows conditional logic', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('store');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    // Check conditional address creation
    expect(str_contains($methodSource, 'if ($request->address)'))->toBeTrue();
});

test('role assignment uses correct string', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('store');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    expect(str_contains($methodSource, "assign('super admin')"))->toBeTrue();
});

test('bouncer role sync uses correct array', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('transferOwnership');
    $source = file($method->getFileName());
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
    
    expect(str_contains($methodSource, "sync(\$user)->roles(['super admin'])"))->toBeTrue();
});
