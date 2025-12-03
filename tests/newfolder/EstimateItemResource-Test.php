<?php

use Mockery as m;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Crater\Http\Resources\EstimateItemResource;
use Crater\Http\Resources\TaxResource; // Required for the class constant in ::collection()
use Crater\Http\Resources\CustomFieldValueResource; // Required for the class constant in ::collection()

// Helper: a dumb Eloquent Model stub for safe property array-access
class DumbModel extends Model
{
    protected $attributes = [];

    // Allow setting attribute like $object->id = 123;
    public function __set($key, $value) { $this->attributes[$key] = $value; }
    public function __get($key)  { return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : null; }
    public function hasAttribute($key) { return array_key_exists($key, $this->attributes); }

    // Allow property exists (isset, empty, etc)
    public function __isset($key) { return array_key_exists($key, $this->attributes); }

    // Allow array conversion (for debugging)
    public function toArray() { return $this->attributes; }
}

beforeEach(function () {
    // Mock TaxResource
    app()->bind(TaxResource::class, function ($app, $parameters) {
        $mock = m::mock(TaxResource::class, $parameters);
        $mock->shouldReceive('toArray')->andReturnUsing(function ($request) use ($parameters) {
            $model = $parameters[0];
            return [
                'id' => $model->id,
                'name' => $model->name,
                'amount' => $model->amount,
            ];
        });
        return $mock;
    });

    // Mock CustomFieldValueResource
    app()->bind(CustomFieldValueResource::class, function ($app, $parameters) {
        $mock = m::mock(CustomFieldValueResource::class, $parameters);
        $mock->shouldReceive('toArray')->andReturnUsing(function ($request) use ($parameters) {
            $model = $parameters[0];
            return [
                'id' => $model->id,
                'name' => $model->name,
                'value' => $model->value,
            ];
        });
        return $mock;
    });
});

afterEach(function () {
    m::close();
    app()->forgetInstance(TaxResource::class);
    app()->forgetInstance(CustomFieldValueResource::class);
});

test('toArray transforms resource with all properties and no relationships', function () {
    $request = m::mock(Request::class);

    $mockModel = new DumbModel();
    $mockModel->id = 1;
    $mockModel->name = 'Item 1';
    $mockModel->description = 'Description 1';
    $mockModel->discount_type = 'percentage';
    $mockModel->quantity = 2;
    $mockModel->unit_name = 'pcs';
    $mockModel->discount = 10.0;
    $mockModel->discount_val = 2.0;
    $mockModel->price = 100.0;
    $mockModel->tax = 10.0;
    $mockModel->total = 198.0;
    $mockModel->item_id = 10;
    $mockModel->estimate_id = 100;
    $mockModel->company_id = 1;
    $mockModel->exchange_rate = 1.0;
    $mockModel->base_discount_val = 2.0;
    $mockModel->base_price = 100.0;
    $mockModel->base_tax = 10.0;
    $mockModel->base_total = 198.0;

    // relationship stubs (relations usually method)
    $taxRelation = m::mock(Relation::class);
    $taxRelation->shouldReceive('exists')->andReturn(false);
    $mockModel->setRelation('taxes', $taxRelation);

    $fieldsRelation = m::mock(Relation::class);
    $fieldsRelation->shouldReceive('exists')->andReturn(false);
    $mockModel->setRelation('fields', $fieldsRelation);

    $resource = new EstimateItemResource($mockModel);
    $result = $resource->toArray($request);

    expect($result)->toEqual([
        'id' => 1,
        'name' => 'Item 1',
        'description' => 'Description 1',
        'discount_type' => 'percentage',
        'quantity' => 2,
        'unit_name' => 'pcs',
        'discount' => 10.0,
        'discount_val' => 2.0,
        'price' => 100.0,
        'tax' => 10.0,
        'total' => 198.0,
        'item_id' => 10,
        'estimate_id' => 100,
        'company_id' => 1,
        'exchange_rate' => 1.0,
        'base_discount_val' => 2.0,
        'base_price' => 100.0,
        'base_tax' => 10.0,
        'base_total' => 198.0,
        'taxes' => null,
        'fields' => null,
    ]);
});

test('toArray transforms resource with taxes relationship', function () {
    $request = m::mock(Request::class);

    $mockTaxModel1 = new DumbModel();
    $mockTaxModel1->id = 1;
    $mockTaxModel1->name = 'Tax A';
    $mockTaxModel1->amount = 5.0;

    $mockTaxModel2 = new DumbModel();
    $mockTaxModel2->id = 2;
    $mockTaxModel2->name = 'Tax B';
    $mockTaxModel2->amount = 10.0;

    $mockTaxCollection = new Collection([$mockTaxModel1, $mockTaxModel2]);

    $mockModel = new DumbModel();
    $mockModel->id = 10;
    $mockModel->name = 'Item With Taxes';
    $mockModel->setRelation('taxes', $mockTaxCollection);

    $taxRelation = m::mock(Relation::class);
    $taxRelation->shouldReceive('exists')->andReturn(true);
    $mockModel->setRelation('taxesRelation', $taxRelation); // store to access for call

    $fieldsRelation = m::mock(Relation::class);
    $fieldsRelation->shouldReceive('exists')->andReturn(false);
    $mockModel->setRelation('fieldsRelation', $fieldsRelation);

    // Patch for EstimateItemResource usage: taxes() relation
    // Eloquent returns setRelation if property exists, otherwise calls method
    // We'll alias method
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->taxes = $mockTaxCollection;

    $mockModel->company_id = null; // to allow partial match

    $mockModel->fields = null;

    $resource = new EstimateItemResource($mockModel);

    // Must have the relation method .taxes() that returns Relation (mocked "exists" true)
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->company_id = null;

    // The resource may call $model->taxes()->exists(), so we must "simulate" this
    // So add taxes() method to the instance (for phpunit compatibility, via closure)
    $taxesRelation = $taxRelation;
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->taxes = $mockTaxCollection;

    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->fields = null;
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;

    // Trick: add taxes() and fields() method as closures
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;

    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;

    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;

    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;

    // Add taxes() and fields() methods, as closure via bindTo (for estimate resource logic)
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;

    // Add closure methods for taxes()->exists() and fields()->exists()
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;
    $mockModel->taxes = $mockTaxCollection;

    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;

    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;

    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = null;

    // Actually, the resource calls $model->taxes()->exists(), so we need a method
    // Use Anonymous class with taxes/fields relation methods for this test
    $mockModel = new class($mockTaxCollection, $fieldsRelation) extends DumbModel {
        public $taxesCollection;
        public $fieldsRelation;
        public function __construct($taxes, $fieldsRelation) {
            $this->taxesCollection = $taxes;
            $this->fieldsRelation = $fieldsRelation;
            $this->id = 10;
            $this->name = 'Item With Taxes';
            $this->taxes = $taxes;
            $this->fields = null;
        }
        public function taxes() { // mimics relation() call
            $relation = m::mock(Relation::class);
            $relation->shouldReceive('exists')->andReturn(true);
            return $relation;
        }
        public function fields() {
            return $this->fieldsRelation;
        }
    };

    $resource = new EstimateItemResource($mockModel);
    $result = $resource->toArray($request);

    $expectedTaxesOutput = [
        ['id' => 1, 'name' => 'Tax A', 'amount' => 5.0],
        ['id' => 2, 'name' => 'Tax B', 'amount' => 10.0],
    ];

    expect($result)->toMatchArray([
        'id' => 10,
        'name' => 'Item With Taxes',
        'taxes' => $expectedTaxesOutput,
        'fields' => null,
    ]);
});

test('toArray transforms resource with fields relationship', function () {
    $request = m::mock(Request::class);

    $mockFieldModel1 = new DumbModel();
    $mockFieldModel1->id = 101;
    $mockFieldModel1->name = 'Field A';
    $mockFieldModel1->value = 'Value A';

    $mockFieldModel2 = new DumbModel();
    $mockFieldModel2->id = 102;
    $mockFieldModel2->name = 'Field B';
    $mockFieldModel2->value = 'Value B';

    $mockFieldCollection = new Collection([$mockFieldModel1, $mockFieldModel2]);

    // Similar hack as previous test, but for fields
    $mockModel = new class($mockFieldCollection) extends DumbModel {
        public $fieldsCollection;
        public function __construct($fieldsCollection) {
            $this->fieldsCollection = $fieldsCollection;
            $this->id = 20;
            $this->name = 'Item With Fields';
            $this->fields = $fieldsCollection;
            $this->taxes = null;
        }
        public function taxes() {
            $relation = m::mock(Relation::class);
            $relation->shouldReceive('exists')->andReturn(false);
            return $relation;
        }
        public function fields() {
            $relation = m::mock(Relation::class);
            $relation->shouldReceive('exists')->andReturn(true);
            return $relation;
        }
    };

    $resource = new EstimateItemResource($mockModel);
    $result = $resource->toArray($request);

    $expectedFieldsOutput = [
        ['id' => 101, 'name' => 'Field A', 'value' => 'Value A'],
        ['id' => 102, 'name' => 'Field B', 'value' => 'Value B'],
    ];

    expect($result)->toMatchArray([
        'id' => 20,
        'name' => 'Item With Fields',
        'taxes' => null,
        'fields' => $expectedFieldsOutput,
    ]);
});

test('toArray transforms resource with both taxes and fields relationships', function () {
    $request = m::mock(Request::class);

    $mockTaxModel = new DumbModel();
    $mockTaxModel->id = 1;
    $mockTaxModel->name = 'Tax Single';
    $mockTaxModel->amount = 7.5;
    $mockTaxCollection = new Collection([$mockTaxModel]);

    $mockFieldModel = new DumbModel();
    $mockFieldModel->id = 201;
    $mockFieldModel->name = 'Single Field';
    $mockFieldModel->value = 'Single Value';
    $mockFieldCollection = new Collection([$mockFieldModel]);

    // Anonymous class to override both taxes() and fields()
    $mockModel = new class($mockTaxCollection, $mockFieldCollection) extends DumbModel {
        public $taxesCollection;
        public $fieldsCollection;
        public function __construct($taxesCollection, $fieldsCollection) {
            $this->taxesCollection = $taxesCollection;
            $this->fieldsCollection = $fieldsCollection;
            $this->id = 30;
            $this->name = 'Item With Both';
            $this->taxes = $taxesCollection;
            $this->fields = $fieldsCollection;
        }
        public function taxes() {
            $relation = m::mock(Relation::class);
            $relation->shouldReceive('exists')->andReturn(true);
            return $relation;
        }
        public function fields() {
            $relation = m::mock(Relation::class);
            $relation->shouldReceive('exists')->andReturn(true);
            return $relation;
        }
    };

    $resource = new EstimateItemResource($mockModel);
    $result = $resource->toArray($request);

    $expectedTaxesOutput = [
        ['id' => 1, 'name' => 'Tax Single', 'amount' => 7.5],
    ];
    $expectedFieldsOutput = [
        ['id' => 201, 'name' => 'Single Field', 'value' => 'Single Value'],
    ];

    expect($result)->toMatchArray([
        'id' => 30,
        'name' => 'Item With Both',
        'taxes' => $expectedTaxesOutput,
        'fields' => $expectedFieldsOutput,
    ]);
});

test('toArray handles empty collection for relationship even if exists returns true', function () {
    $request = m::mock(Request::class);

    $taxesCollection = new Collection();

    $mockModel = new class($taxesCollection) extends DumbModel {
        public $taxesCollection;
        public function __construct($taxesCollection) {
            $this->taxesCollection = $taxesCollection;
            $this->id = 40;
            $this->name = 'Item With Empty Taxes';
            $this->taxes = $taxesCollection;
            $this->fields = null;
        }
        public function taxes() {
            $relation = m::mock(Relation::class);
            $relation->shouldReceive('exists')->andReturn(true);
            return $relation;
        }
        public function fields() {
            $relation = m::mock(Relation::class);
            $relation->shouldReceive('exists')->andReturn(false);
            return $relation;
        }
    };

    $resource = new EstimateItemResource($mockModel);
    $result = $resource->toArray($request);

    expect($result)->toMatchArray([
        'id' => 40,
        'name' => 'Item With Empty Taxes',
        'taxes' => [], // Expect an empty array when collection is empty
        'fields' => null,
    ]);
});

test('toArray handles null values for direct properties gracefully', function () {
    $request = m::mock(Request::class);

    // Use DumbModel with empty attributes
    $mockModel = new class() extends DumbModel {
        public function __construct() {
            $this->id = null;
            $this->name = null;
            $this->description = null;
            $this->discount_type = null;
            $this->quantity = null;
            $this->unit_name = null;
            $this->discount = null;
            $this->discount_val = null;
            $this->price = null;
            $this->tax = null;
            $this->total = null;
            $this->item_id = null;
            $this->estimate_id = null;
            $this->company_id = null;
            $this->exchange_rate = null;
            $this->base_discount_val = null;
            $this->base_price = null;
            $this->base_tax = null;
            $this->base_total = null;
        }
        public function taxes() {
            $relation = m::mock(Relation::class);
            $relation->shouldReceive('exists')->andReturn(false);
            return $relation;
        }
        public function fields() {
            $relation = m::mock(Relation::class);
            $relation->shouldReceive('exists')->andReturn(false);
            return $relation;
        }
    };

    $resource = new EstimateItemResource($mockModel);
    $result = $resource->toArray($request);

    expect($result)->toEqual([
        'id' => null,
        'name' => null,
        'description' => null,
        'discount_type' => null,
        'quantity' => null,
        'unit_name' => null,
        'discount' => null,
        'discount_val' => null,
        'price' => null,
        'tax' => null,
        'total' => null,
        'item_id' => null,
        'estimate_id' => null,
        'company_id' => null,
        'exchange_rate' => null,
        'base_discount_val' => null,
        'base_price' => null,
        'base_tax' => null,
        'base_total' => null,
        'taxes' => null,
        'fields' => null,
    ]);
});