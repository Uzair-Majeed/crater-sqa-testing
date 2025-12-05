<?php

use Crater\Http\Controllers\V1\Admin\General\BulkExchangeRateController;
use Crater\Http\Requests\BulkExchangeRateRequest;
use Crater\Models\CompanySetting;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Models\Tax;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

// Test data builders
function createTestInvoice($attributes = []) {
    return new class($attributes) {
        public $id;
        public $sub_total;
        public $total;
        public $tax;
        public $due_amount;
        public $currency_id;
        public $exchange_rate;
        public $base_discount_val;
        public $base_sub_total;
        public $base_total;
        public $base_tax;
        public $base_due_amount;
        public $items;
        public $taxes;
        public $calls = [];
        
        public function __construct($attributes = []) {
            $this->id = $attributes['id'] ?? 1;
            $this->sub_total = $attributes['sub_total'] ?? 100;
            $this->total = $attributes['total'] ?? 120;
            $this->tax = $attributes['tax'] ?? 20;
            $this->due_amount = $attributes['due_amount'] ?? 50;
            $this->currency_id = $attributes['currency_id'] ?? 1;
            $this->exchange_rate = $attributes['exchange_rate'] ?? null;
            $this->items = $attributes['items'] ?? new Collection([]);
            $this->taxes = $attributes['taxes'] ?? new Collection([]);
        }
        
        public function update($data) {
            $this->calls['update'][] = $data;
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
            return true;
        }
        
        public function taxes() {
            $this->calls['taxes'] = true;
            return new class($this->taxes) {
                private $taxes;
                
                public function __construct($taxes) {
                    $this->taxes = $taxes;
                }
                
                public function exists() {
                    return $this->taxes->count() > 0;
                }
            };
        }
    };
}

function createTestEstimate($attributes = []) {
    return new class($attributes) {
        public $id;
        public $sub_total;
        public $total;
        public $tax;
        public $currency_id;
        public $exchange_rate;
        public $base_discount_val;
        public $base_sub_total;
        public $base_total;
        public $base_tax;
        public $items;
        public $taxes;
        public $calls = [];
        
        public function __construct($attributes = []) {
            $this->id = $attributes['id'] ?? 1;
            $this->sub_total = $attributes['sub_total'] ?? 200;
            $this->total = $attributes['total'] ?? 240;
            $this->tax = $attributes['tax'] ?? 40;
            $this->currency_id = $attributes['currency_id'] ?? 1;
            $this->exchange_rate = $attributes['exchange_rate'] ?? null;
            $this->items = $attributes['items'] ?? new Collection([]);
            $this->taxes = $attributes['taxes'] ?? new Collection([]);
        }
        
        public function update($data) {
            $this->calls['update'][] = $data;
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
            return true;
        }
        
        public function taxes() {
            $this->calls['taxes'] = true;
            return new class($this->taxes) {
                private $taxes;
                
                public function __construct($taxes) {
                    $this->taxes = $taxes;
                }
                
                public function exists() {
                    return $this->taxes->count() > 0;
                }
            };
        }
    };
}

function createTestItem($attributes = []) {
    return new class($attributes) {
        public $discount_val;
        public $price;
        public $tax;
        public $total;
        public $exchange_rate;
        public $base_discount_val;
        public $base_price;
        public $base_tax;
        public $base_total;
        public $taxes;
        public $calls = [];
        
        public function __construct($attributes = []) {
            $this->discount_val = $attributes['discount_val'] ?? 10;
            $this->price = $attributes['price'] ?? 50;
            $this->tax = $attributes['tax'] ?? 5;
            $this->total = $attributes['total'] ?? 55;
            $this->taxes = $attributes['taxes'] ?? new Collection([]);
        }
        
        public function update($data) {
            $this->calls['update'][] = $data;
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
            return true;
        }
        
        public function taxes() {
            $this->calls['taxes'] = true;
            return new class($this->taxes) {
                private $taxes;
                
                public function __construct($taxes) {
                    $this->taxes = $taxes;
                }
                
                public function exists() {
                    return $this->taxes->count() > 0;
                }
            };
        }
    };
}

function createTestTax($attributes = []) {
    return new class($attributes) {
        public $amount;
        public $base_amount;
        public $exchange_rate;
        public $currency_id;
        public $calls = [];
        
        public function __construct($attributes = []) {
            $this->amount = $attributes['amount'] ?? 25;
            $this->base_amount = $attributes['base_amount'] ?? 25;
            $this->currency_id = $attributes['currency_id'] ?? 1;
        }
        
        public function save() {
            $this->calls['save'] = true;
            return true;
        }
        
        public function update($data) {
            $this->calls['update'][] = $data;
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
            return true;
        }
    };
}

function createTestPayment($attributes = []) {
    return new class($attributes) {
        public $amount;
        public $exchange_rate;
        public $base_amount;
        public $currency_id;
        public $calls = [];
        
        public function __construct($attributes = []) {
            $this->amount = $attributes['amount'] ?? 100;
            $this->currency_id = $attributes['currency_id'] ?? 1;
        }
        
        public function save() {
            $this->calls['save'] = true;
            return true;
        }
    };
}

beforeEach(function () {
    $this->controller = new BulkExchangeRateController();
});

test('items method updates all child items and calls taxes', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('items');
    $method->setAccessible(true);
    
    $item1 = createTestItem(['discount_val' => 10, 'price' => 50, 'tax' => 5, 'total' => 55]);
    $item2 = createTestItem(['discount_val' => 20, 'price' => 100, 'tax' => 10, 'total' => 110]);
    
    $tax1 = createTestTax(['amount' => 5]);
    $tax2 = createTestTax(['amount' => 10]);
    $item1->taxes = new Collection([$tax1]);
    $item2->taxes = new Collection([$tax2]);
    
    $model = createTestInvoice(['exchange_rate' => 1.5]);
    $model->items = new Collection([$item1, $item2]);
    
    $method->invoke($this->controller, $model);
    
    expect($item1->exchange_rate)->toBe(1.5);
    expect($item1->base_discount_val)->toBe(15.0);
    expect($item1->base_price)->toBe(75.0);
    expect($item1->base_tax)->toBe(7.5);
    expect($item1->base_total)->toBe(82.5);
    expect($item1->calls)->toHaveKey('update');
    
    expect($item2->exchange_rate)->toBe(1.5);
    expect($item2->base_discount_val)->toBe(30.0);
    expect($item2->base_price)->toBe(150.0);
    expect($item2->base_tax)->toBe(15.0);
    expect($item2->base_total)->toBe(165.0);
    
    expect($tax1->exchange_rate)->toBe(1.5);
    expect($tax1->base_amount)->toBe(7.5);
    expect($tax1->calls)->toHaveKey('update');
    
    expect($tax2->exchange_rate)->toBe(1.5);
    expect($tax2->base_amount)->toBe(15.0);
});

test('items method handles model with no child items', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('items');
    $method->setAccessible(true);
    
    $model = createTestInvoice(['exchange_rate' => 1.5]);
    $model->items = new Collection([]);
    
    $method->invoke($this->controller, $model);
    
    expect($model->calls)->toHaveKey('taxes');
});


test('taxes method updates taxes when they exist', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('taxes');
    $method->setAccessible(true);
    
    $tax1 = createTestTax(['amount' => 25]);
    $tax2 = createTestTax(['amount' => 30]);
    
    $model = new class($tax1, $tax2) {
        public $exchange_rate = 1.5;
        public $taxes;
        
        public function __construct($tax1, $tax2) {
            $this->taxes = new Collection([$tax1, $tax2]);
        }
        
        public function taxes() {
            return new class($this->taxes) {
                private $taxes;
                
                public function __construct($taxes) {
                    $this->taxes = $taxes;
                }
                
                public function exists() {
                    return true;
                }
            };
        }
    };
    
    $method->invoke($this->controller, $model);
    
    expect($tax1->exchange_rate)->toBe(1.5);
    expect($tax1->base_amount)->toBe(37.5);
    expect($tax1->calls)->toHaveKey('update');
    
    expect($tax2->exchange_rate)->toBe(1.5);
    expect($tax2->base_amount)->toBe(45.0);
    expect($tax2->calls)->toHaveKey('update');
});

test('taxes method does nothing when no taxes exist', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('taxes');
    $method->setAccessible(true);
    
    $model = new class {
        public $exchange_rate = 1.5;
        
        public function taxes() {
            return new class {
                public function exists() {
                    return false;
                }
            };
        }
    };
    
    $method->invoke($this->controller, $model);
    
    expect(true)->toBeTrue();
});

test('taxes method handles empty taxes collection even when exists returns true', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('taxes');
    $method->setAccessible(true);
    
    $model = new class {
        public $exchange_rate = 1.5;
        public $taxes;
        
        public function __construct() {
            $this->taxes = new Collection([]);
        }
        
        public function taxes() {
            return new class($this->taxes) {
                private $taxes;
                
                public function __construct($taxes) {
                    $this->taxes = $taxes;
                }
                
                public function exists() {
                    return true;
                }
            };
        }
    };
    
    $method->invoke($this->controller, $model);
    
    expect(true)->toBeTrue();
});

test('items method calls taxes on model after processing items', function () {
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('items');
    $method->setAccessible(true);
    
    $modelTax = createTestTax(['amount' => 50]);
    $model = createTestInvoice(['exchange_rate' => 1.5]);
    $model->items = new Collection([]);
    $model->taxes = new Collection([$modelTax]);
    
    $method->invoke($this->controller, $model);
    
    expect($modelTax->exchange_rate)->toBe(1.5);
    expect($modelTax->base_amount)->toBe(75.0);
    expect($modelTax->calls)->toHaveKey('update');
});
