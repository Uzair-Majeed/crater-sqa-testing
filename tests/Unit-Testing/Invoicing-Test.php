<?php

use Crater\Http\Resources\InvoiceResource;
use Crater\Http\Controllers\V1\Admin\Invoice\InvoicesController;
use Crater\Http\Requests\InvoicesRequest;
use Illuminate\Http\Request;

// ========== MERGED INVOICE TESTS (3 CLASSES, 20 TESTS) ==========

// --- InvoiceResource Tests (7 tests) ---

test('InvoiceResource extends JsonResource', function () {
    $resource = new InvoiceResource((object)['id' => 1]);
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

test('InvoiceResource is in correct namespace', function () {
    $reflection = new ReflectionClass(InvoiceResource::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('InvoiceResource has toArray method', function () {
    $reflection = new ReflectionClass(InvoiceResource::class);
    expect($reflection->hasMethod('toArray'))->toBeTrue();
});

test('InvoiceResource toArray includes required invoice fields', function () {
    $reflection = new ReflectionClass(InvoiceResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'id\' => $this->id')
        ->and($fileContent)->toContain('\'invoice_date\' => $this->invoice_date')
        ->and($fileContent)->toContain('\'due_date\' => $this->due_date')
        ->and($fileContent)->toContain('\'invoice_number\' => $this->invoice_number')
        ->and($fileContent)->toContain('\'status\' => $this->status')
        ->and($fileContent)->toContain('\'paid_status\' => $this->paid_status');
});

test('InvoiceResource includes financial fields', function () {
    $reflection = new ReflectionClass(InvoiceResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'total\' => $this->total')
        ->and($fileContent)->toContain('\'sub_total\' => $this->sub_total')
        ->and($fileContent)->toContain('\'tax\' => $this->tax')
        ->and($fileContent)->toContain('\'discount\' => $this->discount');
});

test('InvoiceResource includes relationship fields', function () {
    $reflection = new ReflectionClass(InvoiceResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'customer_id\' => $this->customer_id')
        ->and($fileContent)->toContain('\'currency_id\' => $this->currency_id')
        ->and($fileContent)->toContain('\'creator_id\'');
});

test('InvoiceResource uses when() for conditional relationships', function () {
    $reflection = new ReflectionClass(InvoiceResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->when(');
});

// --- InvoicesController Tests (7 tests) ---

test('InvoicesController extends Controller', function () {
    $controller = new InvoicesController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('InvoicesController is in correct namespace', function () {
    $reflection = new ReflectionClass(InvoicesController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\Invoice');
});

test('InvoicesController has CRUD methods', function () {
    $reflection = new ReflectionClass(InvoicesController::class);
    
    expect($reflection->hasMethod('index'))->toBeTrue()
        ->and($reflection->hasMethod('store'))->toBeTrue()
        ->and($reflection->hasMethod('show'))->toBeTrue()
        ->and($reflection->hasMethod('update'))->toBeTrue()
        ->and($reflection->hasMethod('delete'))->toBeTrue();
});

test('InvoicesController index method uses authorization', function () {
    $reflection = new ReflectionClass(InvoicesController::class);
    $method = $reflection->getMethod('index');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'viewAny\', Invoice::class)');
});

test('InvoicesController store method creates invoice', function () {
    $reflection = new ReflectionClass(InvoicesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Invoice::createInvoice($request)')
        ->and($fileContent)->toContain('GenerateInvoicePdfJob::dispatch');
});

test('InvoicesController update method handles errors', function () {
    $reflection = new ReflectionClass(InvoicesController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if (is_string($invoice))')
        ->and($fileContent)->toContain('respondJson');
});

test('InvoicesController delete method uses DeleteInvoiceRequest', function () {
    $reflection = new ReflectionClass(InvoicesController::class);
    $method = $reflection->getMethod('delete');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getType()->getName())->toContain('DeleteInvoiceRequest');
});

// --- InvoicesRequest Tests (6 tests) ---

test('InvoicesRequest extends FormRequest', function () {
    $request = new InvoicesRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

test('InvoicesRequest is in correct namespace', function () {
    $reflection = new ReflectionClass(InvoicesRequest::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Requests');
});

test('InvoicesRequest has required methods', function () {
    $reflection = new ReflectionClass(InvoicesRequest::class);
    
    expect($reflection->hasMethod('authorize'))->toBeTrue()
        ->and($reflection->hasMethod('rules'))->toBeTrue()
        ->and($reflection->hasMethod('getInvoicePayload'))->toBeTrue();
});

test('InvoicesRequest authorize returns true', function () {
    $request = new InvoicesRequest();
    expect($request->authorize())->toBeTrue();
});

test('InvoicesRequest rules include required fields', function () {
    $reflection = new ReflectionClass(InvoicesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'invoice_date\' =>')
        ->and($fileContent)->toContain('\'customer_id\' =>')
        ->and($fileContent)->toContain('\'invoice_number\' =>')
        ->and($fileContent)->toContain('\'items\' =>')
        ->and($fileContent)->toContain('\'total\' =>');
});

test('InvoicesRequest getInvoicePayload merges default values', function () {
    $reflection = new ReflectionClass(InvoicesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Invoice::STATUS_SENT')
        ->and($fileContent)->toContain('Invoice::STATUS_DRAFT')
        ->and($fileContent)->toContain('Invoice::STATUS_UNPAID')
        ->and($fileContent)->toContain('exchange_rate')
        ->and($fileContent)->toContain('base_total');
});
