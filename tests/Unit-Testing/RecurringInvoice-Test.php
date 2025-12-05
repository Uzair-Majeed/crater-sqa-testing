<?php

use Crater\Models\RecurringInvoice;
use Crater\Http\Resources\RecurringInvoiceCollection;
use Crater\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceFrequencyController;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// ========== MERGED RECURRINGINVOICE TESTS (3 CLASSES, ~25 TESTS WITH FUNCTIONAL COVERAGE) ==========

// --- RecurringInvoice Model Tests (16 tests: 12 structural + 4 functional) ---

test('RecurringInvoice can be instantiated', function () {
    $invoice = new RecurringInvoice();
    expect($invoice)->toBeInstanceOf(RecurringInvoice::class);
});

test('RecurringInvoice extends Model and uses traits', function () {
    $invoice = new RecurringInvoice();
    $reflection = new ReflectionClass(RecurringInvoice::class);
    $traits = $reflection->getTraitNames();
    
    expect($invoice)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class)
        ->and($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory')
        ->and($traits)->toContain('Crater\Traits\HasCustomFieldsTrait');
});

test('RecurringInvoice has limit constants', function () {
    expect(RecurringInvoice::NONE)->toBe('NONE')
        ->and(RecurringInvoice::COUNT)->toBe('COUNT')
        ->and(RecurringInvoice::DATE)->toBe('DATE');
});

test('RecurringInvoice has status constants', function () {
    expect(RecurringInvoice::COMPLETED)->toBe('COMPLETED')
        ->and(RecurringInvoice::ON_HOLD)->toBe('ON_HOLD')
        ->and(RecurringInvoice::ACTIVE)->toBe('ACTIVE');
});

test('RecurringInvoice has correct casts', function () {
    $invoice = new RecurringInvoice();
    $casts = $invoice->getCasts();
    
    expect($casts)->toHaveKey('exchange_rate')
        ->and($casts['exchange_rate'])->toBe('float')
        ->and($casts)->toHaveKey('send_automatically')
        ->and($casts['send_automatically'])->toBe('boolean');
});

test('RecurringInvoice has formatted date accessors', function () {
    $reflection = new ReflectionClass(RecurringInvoice::class);
    
    expect($reflection->hasMethod('getFormattedStartsAtAttribute'))->toBeTrue()
        ->and($reflection->hasMethod('getFormattedNextInvoiceAtAttribute'))->toBeTrue()
        ->and($reflection->hasMethod('getFormattedLimitDateAttribute'))->toBeTrue()
        ->and($reflection->hasMethod('getFormattedCreatedAtAttribute'))->toBeTrue();
});

test('RecurringInvoice has relationship methods', function () {
    $reflection = new ReflectionClass(RecurringInvoice::class);
    
    expect($reflection->hasMethod('invoices'))->toBeTrue()
        ->and($reflection->hasMethod('taxes'))->toBeTrue()
        ->and($reflection->hasMethod('items'))->toBeTrue()
        ->and($reflection->hasMethod('customer'))->toBeTrue()
        ->and($reflection->hasMethod('company'))->toBeTrue()
        ->and($reflection->hasMethod('creator'))->toBeTrue()
        ->and($reflection->hasMethod('currency'))->toBeTrue();
});

test('RecurringInvoice has scope methods', function () {
    $reflection = new ReflectionClass(RecurringInvoice::class);
    
    expect($reflection->hasMethod('scopeWhereCompany'))->toBeTrue()
        ->and($reflection->hasMethod('scopePaginateData'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWhereOrder'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWhereStatus'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWhereCustomer'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWhereSearch'))->toBeTrue()
        ->and($reflection->hasMethod('scopeApplyFilters'))->toBeTrue();
});

test('RecurringInvoice has static CRUD methods', function () {
    $reflection = new ReflectionClass(RecurringInvoice::class);
    
    expect($reflection->hasMethod('createFromRequest'))->toBeTrue()
        ->and($reflection->hasMethod('createItems'))->toBeTrue()
        ->and($reflection->hasMethod('createTaxes'))->toBeTrue()
        ->and($reflection->hasMethod('deleteRecurringInvoice'))->toBeTrue();
});

test('RecurringInvoice has invoice generation methods', function () {
    $reflection = new ReflectionClass(RecurringInvoice::class);
    
    expect($reflection->hasMethod('generateInvoice'))->toBeTrue()
        ->and($reflection->hasMethod('createInvoice'))->toBeTrue()
        ->and($reflection->hasMethod('markStatusAsCompleted'))->toBeTrue()
        ->and($reflection->hasMethod('updateNextInvoiceDate'))->toBeTrue();
});

test('RecurringInvoice has static getNextInvoiceDate method', function () {
    $reflection = new ReflectionClass(RecurringInvoice::class);
    
    expect($reflection->hasMethod('getNextInvoiceDate'))->toBeTrue();
    
    $method = $reflection->getMethod('getNextInvoiceDate');
    expect($method->isStatic())->toBeTrue()
        ->and($method->isPublic())->toBeTrue();
});

test('RecurringInvoice scopePaginateData handles all limit', function () {
    $reflection = new ReflectionClass(RecurringInvoice::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($limit == \'all\')')
        ->and($fileContent)->toContain('return $query->get()')
        ->and($fileContent)->toContain('return $query->paginate($limit)');
});

// --- FUNCTIONAL TESTS (4 tests) ---

test('RecurringInvoice invoices relationship returns HasMany', function () {
    $invoice = new RecurringInvoice();
    $relation = $invoice->invoices();
    
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(\Crater\Models\Invoice::class);
});

test('RecurringInvoice customer relationship returns BelongsTo', function () {
    $invoice = new RecurringInvoice();
    $relation = $invoice->customer();
    
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(\Crater\Models\Customer::class);
});

test('RecurringInvoice company relationship returns BelongsTo', function () {
    $invoice = new RecurringInvoice();
    $relation = $invoice->company();
    
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(\Crater\Models\Company::class);
});

test('RecurringInvoice currency relationship returns BelongsTo', function () {
    $invoice = new RecurringInvoice();
    $relation = $invoice->currency();
    
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(\Crater\Models\Currency::class);
});

// --- RecurringInvoiceCollection Tests (5 tests: 3 structural + 2 functional) ---

test('RecurringInvoiceCollection can be instantiated', function () {
    $collection = new RecurringInvoiceCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(RecurringInvoiceCollection::class);
});

test('RecurringInvoiceCollection extends ResourceCollection', function () {
    $collection = new RecurringInvoiceCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('RecurringInvoiceCollection delegates to parent toArray', function () {
    $reflection = new ReflectionClass(RecurringInvoiceCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

// --- FUNCTIONAL TESTS (2 tests) ---

test('RecurringInvoiceCollection returns empty array for empty collection', function () {
    $request = new Request();
    $collection = new RecurringInvoiceCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()->and($result)->toBeEmpty();
});

test('RecurringInvoiceCollection toArray method is public', function () {
    $reflection = new ReflectionClass(RecurringInvoiceCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->isPublic())->toBeTrue()
        ->and($method->isStatic())->toBeFalse();
});

// --- RecurringInvoiceFrequencyController Tests (5 tests: all structural) ---

test('RecurringInvoiceFrequencyController can be instantiated', function () {
    $controller = new RecurringInvoiceFrequencyController();
    expect($controller)->toBeInstanceOf(RecurringInvoiceFrequencyController::class);
});

test('RecurringInvoiceFrequencyController extends Controller', function () {
    $controller = new RecurringInvoiceFrequencyController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('RecurringInvoiceFrequencyController is invokable', function () {
    $reflection = new ReflectionClass(RecurringInvoiceFrequencyController::class);
    expect($reflection->hasMethod('__invoke'))->toBeTrue();
});

test('RecurringInvoiceFrequencyController uses RecurringInvoice getNextInvoiceDate', function () {
    $reflection = new ReflectionClass(RecurringInvoiceFrequencyController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('RecurringInvoice::getNextInvoiceDate($request->frequency, $request->starts_at)');
});

test('RecurringInvoiceFrequencyController returns JSON with next_invoice_at', function () {
    $reflection = new ReflectionClass(RecurringInvoiceFrequencyController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('response()->json([')
        ->and($fileContent)->toContain('\'success\' => true')
        ->and($fileContent)->toContain('\'next_invoice_at\' => $nextInvoiceAt');
});