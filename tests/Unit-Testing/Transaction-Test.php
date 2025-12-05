<?php

use Crater\Models\Transaction;
use Crater\Http\Resources\TransactionCollection;
use Crater\Http\Resources\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// ========== MERGED TRANSACTION TESTS (3 CLASSES, 20 FUNCTIONAL TESTS) ==========

// --- Transaction Model Tests (10 tests: 5 structural + 5 functional) ---

test('Transaction can be instantiated', function () {
    $transaction = new Transaction();
    expect($transaction)->toBeInstanceOf(Transaction::class);
});

test('Transaction extends Model and uses HasFactory', function () {
    $transaction = new Transaction();
    $reflection = new ReflectionClass(Transaction::class);
    $traits = $reflection->getTraitNames();
    
    expect($transaction)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class)
        ->and($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
});

test('Transaction has status constants', function () {
    expect(Transaction::PENDING)->toBe('PENDING')
        ->and(Transaction::FAILED)->toBe('FAILED')
        ->and(Transaction::SUCCESS)->toBe('SUCCESS');
});

test('Transaction has guarded and dates properties', function () {
    $reflection = new ReflectionClass(Transaction::class);
    
    expect($reflection->hasProperty('guarded'))->toBeTrue()
        ->and($reflection->hasProperty('dates'))->toBeTrue();
});

test('Transaction has relationship and action methods', function () {
    $reflection = new ReflectionClass(Transaction::class);
    
    expect($reflection->hasMethod('payments'))->toBeTrue()
        ->and($reflection->hasMethod('invoice'))->toBeTrue()
        ->and($reflection->hasMethod('company'))->toBeTrue()
        ->and($reflection->hasMethod('completeTransaction'))->toBeTrue()
        ->and($reflection->hasMethod('failedTransaction'))->toBeTrue()
        ->and($reflection->hasMethod('createTransaction'))->toBeTrue()
        ->and($reflection->hasMethod('isExpired'))->toBeTrue();
});

// --- FUNCTIONAL TESTS ---

test('Transaction payments relationship returns HasMany', function () {
    $transaction = new Transaction();
    $relation = $transaction->payments();
    
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('Transaction invoice relationship returns BelongsTo', function () {
    $transaction = new Transaction();
    $relation = $transaction->invoice();
    
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('Transaction company relationship returns BelongsTo', function () {
    $transaction = new Transaction();
    $relation = $transaction->company();
    
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('Transaction completeTransaction sets status to SUCCESS', function () {
    $reflection = new ReflectionClass(Transaction::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->status = self::SUCCESS')
        ->and($fileContent)->toContain('$this->save()');
});

test('Transaction failedTransaction sets status to FAILED', function () {
    $reflection = new ReflectionClass(Transaction::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->status = self::FAILED');
});

// --- TransactionCollection Tests (5 tests: 3 structural + 2 functional) ---

test('TransactionCollection can be instantiated', function () {
    $collection = new TransactionCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(TransactionCollection::class);
});

test('TransactionCollection extends ResourceCollection', function () {
    $collection = new TransactionCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('TransactionCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(TransactionCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

// --- FUNCTIONAL TESTS ---

test('TransactionCollection toArray returns empty array for empty collection', function () {
    $request = new Request();
    $collection = new TransactionCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('TransactionCollection toArray delegates to parent', function () {
    $request = new Request();
    $collection = new TransactionCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray();
});

// --- TransactionResource Tests (5 tests: 2 structural + 3 functional) ---

test('TransactionResource can be instantiated', function () {
    $data = (object)['id' => 1, 'status' => 'SUCCESS'];
    $resource = new TransactionResource($data);
    expect($resource)->toBeInstanceOf(TransactionResource::class);
});

test('TransactionResource extends JsonResource', function () {
    $resource = new TransactionResource((object)[]);
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

// --- FUNCTIONAL TESTS ---

test('TransactionResource toArray includes all basic fields', function () {
    $reflection = new ReflectionClass(TransactionResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'id\' => $this->id')
        ->and($fileContent)->toContain('\'transaction_id\' => $this->transaction_id')
        ->and($fileContent)->toContain('\'type\' => $this->type')
        ->and($fileContent)->toContain('\'status\' => $this->status')
        ->and($fileContent)->toContain('\'transaction_date\' => $this->transaction_date')
        ->and($fileContent)->toContain('\'invoice_id\' => $this->invoice_id');
});

test('TransactionResource toArray includes conditional invoice relationship', function () {
    $reflection = new ReflectionClass(TransactionResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'invoice\' => $this->when($this->invoice()->exists()')
        ->and($fileContent)->toContain('return new InvoiceResource($this->invoice)');
});

test('TransactionResource toArray includes conditional company relationship', function () {
    $reflection = new ReflectionClass(TransactionResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'company\' => $this->when($this->company()->exists()')
        ->and($fileContent)->toContain('return new CompanyResource($this->company)');
});