<?php

use Crater\Http\Resources\PaymentMethodCollection;
use Crater\Http\Controllers\V1\Admin\Payment\PaymentMethodsController;
use Crater\Policies\PaymentMethodPolicy;
use Crater\Http\Requests\PaymentMethodRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// ========== MERGED PAYMENTMETHOD TESTS (4 CLASSES, ~20 TESTS) ==========

// --- PaymentMethodCollection Tests (4 tests) ---

test('PaymentMethodCollection can be instantiated', function () {
    $collection = new PaymentMethodCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(PaymentMethodCollection::class);
});

test('PaymentMethodCollection extends ResourceCollection', function () {
    $collection = new PaymentMethodCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('PaymentMethodCollection returns empty array for empty collection', function () {
    $request = new Request();
    $collection = new PaymentMethodCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()->and($result)->toBeEmpty();
});

test('PaymentMethodCollection delegates to parent toArray', function () {
    $reflection = new ReflectionClass(PaymentMethodCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

// --- PaymentMethodsController Tests (7 tests) ---

test('PaymentMethodsController can be instantiated', function () {
    $controller = new PaymentMethodsController();
    expect($controller)->toBeInstanceOf(PaymentMethodsController::class);
});

test('PaymentMethodsController extends Controller', function () {
    $controller = new PaymentMethodsController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('PaymentMethodsController has CRUD methods', function () {
    $reflection = new ReflectionClass(PaymentMethodsController::class);
    
    expect($reflection->hasMethod('index'))->toBeTrue()
        ->and($reflection->hasMethod('store'))->toBeTrue()
        ->and($reflection->hasMethod('show'))->toBeTrue()
        ->and($reflection->hasMethod('update'))->toBeTrue()
        ->and($reflection->hasMethod('destroy'))->toBeTrue();
});

test('PaymentMethodsController index uses authorization', function () {
    $reflection = new ReflectionClass(PaymentMethodsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'viewAny\', PaymentMethod::class)');
});

test('PaymentMethodsController index filters by TYPE_GENERAL', function () {
    $reflection = new ReflectionClass(PaymentMethodsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->where(\'type\', PaymentMethod::TYPE_GENERAL)')
        ->and($fileContent)->toContain('->whereCompany()');
});

test('PaymentMethodsController destroy checks for attached payments and expenses', function () {
    $reflection = new ReflectionClass(PaymentMethodsController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($paymentMethod->payments()->exists())')
        ->and($fileContent)->toContain('if ($paymentMethod->expenses()->exists())')
        ->and($fileContent)->toContain('respondJson');
});

test('PaymentMethodsController uses PaymentMethodRequest for store and update', function () {
    $reflection = new ReflectionClass(PaymentMethodsController::class);
    $storeMethod = $reflection->getMethod('store');
    $updateMethod = $reflection->getMethod('update');
    
    $storeParams = $storeMethod->getParameters();
    $updateParams = $updateMethod->getParameters();
    
    expect($storeParams[0]->getType()->getName())->toContain('PaymentMethodRequest')
        ->and($updateParams[0]->getType()->getName())->toContain('PaymentMethodRequest');
});

// --- PaymentMethodPolicy Tests (5 tests) ---

test('PaymentMethodPolicy can be instantiated', function () {
    $policy = new PaymentMethodPolicy();
    expect($policy)->toBeInstanceOf(PaymentMethodPolicy::class);
});

test('PaymentMethodPolicy uses HandlesAuthorization trait', function () {
    $reflection = new ReflectionClass(PaymentMethodPolicy::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Auth\Access\HandlesAuthorization');
});

test('PaymentMethodPolicy has all authorization methods', function () {
    $reflection = new ReflectionClass(PaymentMethodPolicy::class);
    
    expect($reflection->hasMethod('viewAny'))->toBeTrue()
        ->and($reflection->hasMethod('view'))->toBeTrue()
        ->and($reflection->hasMethod('create'))->toBeTrue()
        ->and($reflection->hasMethod('update'))->toBeTrue()
        ->and($reflection->hasMethod('delete'))->toBeTrue()
        ->and($reflection->hasMethod('restore'))->toBeTrue()
        ->and($reflection->hasMethod('forceDelete'))->toBeTrue();
});

test('PaymentMethodPolicy uses BouncerFacade for authorization', function () {
    $reflection = new ReflectionClass(PaymentMethodPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('BouncerFacade::can(\'view-payment\', Payment::class)');
});

test('PaymentMethodPolicy checks company ownership', function () {
    $reflection = new ReflectionClass(PaymentMethodPolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$user->hasCompany($paymentMethod->company_id)');
});

// --- PaymentMethodRequest Tests (5 tests) ---

test('PaymentMethodRequest can be instantiated', function () {
    $request = new PaymentMethodRequest();
    expect($request)->toBeInstanceOf(PaymentMethodRequest::class);
});

test('PaymentMethodRequest extends FormRequest', function () {
    $request = new PaymentMethodRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

test('PaymentMethodRequest authorize returns true', function () {
    $request = new PaymentMethodRequest();
    expect($request->authorize())->toBeTrue();
});

test('PaymentMethodRequest rules include unique name validation', function () {
    $reflection = new ReflectionClass(PaymentMethodRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'name\' =>')
        ->and($fileContent)->toContain('Rule::unique(\'payment_methods\')')
        ->and($fileContent)->toContain('->where(\'company_id\', $this->header(\'company\'))');
});

test('PaymentMethodRequest getPaymentMethodPayload merges company_id and type', function () {
    $reflection = new ReflectionClass(PaymentMethodRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('public function getPaymentMethodPayload()')
        ->and($fileContent)->toContain('\'company_id\' => $this->header(\'company\')')
        ->and($fileContent)->toContain('\'type\' => PaymentMethod::TYPE_GENERAL');
});