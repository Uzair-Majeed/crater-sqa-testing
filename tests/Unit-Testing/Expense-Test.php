<?php

use Crater\Http\Resources\ExpenseCollection;
use Crater\Http\Resources\ExpenseResource;
use Crater\Http\Requests\ExpenseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// ========== HELPER FUNCTIONS ==========

// Helper function to create dummy expense with all required properties
function createDummyExpense($id, $amount) {
    return new class($id, $amount) {
        private $data;
        
        public function __construct($id, $amount) {
            $this->data = [
                'id' => $id,
                'expense_date' => '2024-01-01',
                'amount' => $amount,
                'notes' => 'Test notes',
                'customer_id' => 1,
                'receipt_url' => 'http://example.com/receipt.pdf',
                'receipt' => 'receipt.pdf',
                'receipt_meta' => ['size' => 1024],
                'company_id' => 1,
                'expense_category_id' => 1,
                'creator_id' => 1,
                'formattedExpenseDate' => '01/01/2024',
                'formattedCreatedAt' => '01/01/2024 00:00:00',
                'exchange_rate' => 1,
                'currency_id' => 1,
                'base_amount' => $amount,
                'payment_method_id' => 1,
            ];
        }
        
        public function __get($key) {
            return $this->data[$key] ?? null;
        }
        
        public function customer() {
            return new class { public function exists() { return false; } };
        }
        
        public function category() {
            return new class { public function exists() { return false; } };
        }
        
        public function creator() {
            return new class { public function exists() { return false; } };
        }
        
        public function fields() {
            return new class { public function exists() { return false; } };
        }
        
        public function company() {
            return new class { public function exists() { return false; } };
        }
        
        public function currency() {
            return new class { public function exists() { return false; } };
        }
        
        public function paymentMethod() {
            return new class { public function exists() { return false; } };
        }
    };
}

// ========== EXPENSE COLLECTION TESTS ==========

test('ExpenseCollection can be instantiated', function () {
    $collection = new ExpenseCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(ExpenseCollection::class);
});

test('ExpenseCollection extends ResourceCollection', function () {
    $collection = new ExpenseCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('ExpenseCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(ExpenseCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('ExpenseCollection handles empty collection', function () {
    $request = new Request();
    $collection = new ExpenseCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('ExpenseCollection transforms single expense resource', function () {
    $request = new Request();
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    
    $collection = new ExpenseCollection(new Collection([$resource]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0])->toHaveKey('id')
        ->and($result[0]['id'])->toBe(1);
});

test('ExpenseCollection transforms multiple expense resources', function () {
    $request = new Request();
    $expense1 = createDummyExpense(1, 100);
    $expense2 = createDummyExpense(2, 200);
    
    $resource1 = new ExpenseResource($expense1);
    $resource2 = new ExpenseResource($expense2);
    
    $collection = new ExpenseCollection(new Collection([$resource1, $resource2]));
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[1]['id'])->toBe(2);
});

test('ExpenseCollection delegates to parent toArray', function () {
    $reflection = new ReflectionClass(ExpenseCollection::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('parent::toArray');
});

// ========== EXPENSE RESOURCE TESTS ==========

test('ExpenseResource can be instantiated', function () {
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    expect($resource)->toBeInstanceOf(ExpenseResource::class);
});

test('ExpenseResource extends JsonResource', function () {
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

test('ExpenseResource is in correct namespace', function () {
    $reflection = new ReflectionClass(ExpenseResource::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('ExpenseResource toArray returns all required fields', function () {
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKeys([
        'id',
        'expense_date',
        'amount',
        'notes',
        'customer_id',
        'attachment_receipt_url',
        'attachment_receipt',
        'attachment_receipt_meta',
        'company_id',
        'expense_category_id',
        'creator_id',
        'formatted_expense_date',
        'formatted_created_at',
        'exchange_rate',
        'currency_id',
        'base_amount',
        'payment_method_id'
    ]);
});

test('ExpenseResource toArray returns correct id', function () {
    $expense = createDummyExpense(42, 100);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result['id'])->toBe(42);
});

test('ExpenseResource toArray returns correct amount', function () {
    $expense = createDummyExpense(1, 250.75);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result['amount'])->toBe(250.75);
});

test('ExpenseResource toArray returns correct expense_date', function () {
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result['expense_date'])->toBe('2024-01-01');
});

test('ExpenseResource toArray handles zero amount', function () {
    $expense = createDummyExpense(1, 0);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result['amount'])->toBe(0);
});

test('ExpenseResource toArray includes customer field', function () {
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('customer');
});

test('ExpenseResource toArray includes expense_category field', function () {
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('expense_category');
});

test('ExpenseResource toArray includes creator field', function () {
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('creator');
});

test('ExpenseResource toArray includes fields field', function () {
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('fields');
});

test('ExpenseResource toArray includes company field', function () {
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('company');
});

test('ExpenseResource toArray includes currency field', function () {
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('currency');
});

test('ExpenseResource toArray includes payment_method field', function () {
    $expense = createDummyExpense(1, 100);
    $resource = new ExpenseResource($expense);
    $request = new Request();
    $result = $resource->toArray($request);
    
    expect($result)->toHaveKey('payment_method');
});

// ========== EXPENSE REQUEST TESTS ==========

test('ExpenseRequest can be instantiated', function () {
    $request = new ExpenseRequest();
    expect($request)->toBeInstanceOf(ExpenseRequest::class);
});

test('ExpenseRequest extends FormRequest', function () {
    $request = new ExpenseRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

test('ExpenseRequest is in correct namespace', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Requests');
});

test('ExpenseRequest has authorize method', function () {
    $request = new ExpenseRequest();
    expect(method_exists($request, 'authorize'))->toBeTrue();
});

test('ExpenseRequest has rules method', function () {
    $request = new ExpenseRequest();
    expect(method_exists($request, 'rules'))->toBeTrue();
});

test('ExpenseRequest has getExpensePayload method', function () {
    $request = new ExpenseRequest();
    expect(method_exists($request, 'getExpensePayload'))->toBeTrue();
});

test('ExpenseRequest authorize returns true', function () {
    $request = new ExpenseRequest();
    expect($request->authorize())->toBeTrue();
});

test('ExpenseRequest authorize method is public', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $method = $reflection->getMethod('authorize');
    
    expect($method->isPublic())->toBeTrue();
});

test('ExpenseRequest rules method is public', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $method = $reflection->getMethod('rules');
    
    expect($method->isPublic())->toBeTrue();
});

test('ExpenseRequest getExpensePayload method is public', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $method = $reflection->getMethod('getExpensePayload');
    
    expect($method->isPublic())->toBeTrue();
});

test('ExpenseRequest rules include expense_date as required', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'expense_date\' =>')
        ->and($fileContent)->toContain('\'required\'');
});

test('ExpenseRequest rules include expense_category_id as required', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'expense_category_id\' =>')
        ->and($fileContent)->toContain('\'required\'');
});

test('ExpenseRequest rules include amount as required', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'amount\' =>')
        ->and($fileContent)->toContain('\'required\'');
});

test('ExpenseRequest rules include currency_id as required', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'currency_id\' =>')
        ->and($fileContent)->toContain('\'required\'');
});

test('ExpenseRequest rules include exchange_rate as nullable', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'exchange_rate\' =>')
        ->and($fileContent)->toContain('\'nullable\'');
});

test('ExpenseRequest rules include payment_method_id as nullable', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'payment_method_id\' =>')
        ->and($fileContent)->toContain('\'nullable\'');
});

test('ExpenseRequest rules include customer_id as nullable', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'customer_id\' =>')
        ->and($fileContent)->toContain('\'nullable\'');
});

test('ExpenseRequest rules include notes as nullable', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'notes\' =>')
        ->and($fileContent)->toContain('\'nullable\'');
});

test('ExpenseRequest rules include attachment_receipt validation', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'attachment_receipt\' =>')
        ->and($fileContent)->toContain('\'file\'')
        ->and($fileContent)->toContain('\'mimes:jpg,png,pdf,doc,docx,xls,xlsx,ppt,pptx\'')
        ->and($fileContent)->toContain('\'max:20000\'');
});

test('ExpenseRequest getExpensePayload uses CompanySetting', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('CompanySetting::getSetting');
});

test('ExpenseRequest getExpensePayload uses collect helper', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('collect($this->validated())');
});

test('ExpenseRequest getExpensePayload merges additional fields', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->merge([')
        ->and($fileContent)->toContain('\'creator_id\' =>')
        ->and($fileContent)->toContain('\'company_id\' =>')
        ->and($fileContent)->toContain('\'exchange_rate\' =>')
        ->and($fileContent)->toContain('\'base_amount\' =>')
        ->and($fileContent)->toContain('\'currency_id\' =>');
});

test('ExpenseRequest getExpensePayload returns array', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('->toArray()');
});

// ========== CLASS CHARACTERISTICS TESTS ==========

test('all classes are not abstract', function () {
    expect((new ReflectionClass(ExpenseCollection::class))->isAbstract())->toBeFalse()
        ->and((new ReflectionClass(ExpenseResource::class))->isAbstract())->toBeFalse()
        ->and((new ReflectionClass(ExpenseRequest::class))->isAbstract())->toBeFalse();
});

test('all classes are loaded', function () {
    expect(class_exists(ExpenseCollection::class))->toBeTrue()
        ->and(class_exists(ExpenseResource::class))->toBeTrue()
        ->and(class_exists(ExpenseRequest::class))->toBeTrue();
});

// ========== DOCUMENTATION TESTS ==========

test('ExpenseCollection toArray has documentation', function () {
    $reflection = new ReflectionClass(ExpenseCollection::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('ExpenseResource toArray has documentation', function () {
    $reflection = new ReflectionClass(ExpenseResource::class);
    $method = $reflection->getMethod('toArray');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('ExpenseRequest authorize has documentation', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $method = $reflection->getMethod('authorize');
    
    expect($method->getDocComment())->not->toBeFalse();
});

test('ExpenseRequest rules has documentation', function () {
    $reflection = new ReflectionClass(ExpenseRequest::class);
    $method = $reflection->getMethod('rules');
    
    expect($method->getDocComment())->not->toBeFalse();
});
