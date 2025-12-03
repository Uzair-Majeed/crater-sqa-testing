<?php

use Mockery as m;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Crater\Http\Resources\EstimateItemResource;
use Crater\Http\Resources\TaxResource; // Required for the class constant in ::collection()
use Crater\Http\Resources\CustomFieldValueResource; // Required for the class constant in ::collection()

// This setup assumes a Laravel test environment where a base TestCase or Pest's setup
// ensures that the application container is available for binding.
// For the purpose of strictly returning test code, explicit bindings are handled within tests.

beforeEach(function () {
    // We need to set up a way to mock `TaxResource` and `CustomFieldValueResource`
    // so that `TaxResource::collection` and `CustomFieldValueResource::collection`
    // return predictable arrays for unit testing `EstimateItemResource`.
    // Laravel's JsonResource::collection() internally uses `new $resourceClass($item)`.
    // We can use `app()->bind` to swap the implementation during tests.

    // Mock TaxResource
    app()->bind(TaxResource::class, function ($app, $parameters) {
        $mock = m::mock(TaxResource::class, $parameters);
        // Ensure toArray is mocked to return expected simple structure
        $mock->shouldReceive('toArray')->andReturnUsing(function ($request) use ($parameters) {
            $model = $parameters[0]; // The model wrapped by the resource
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
        // Ensure toArray is mocked to return expected simple structure
        $mock->shouldReceive('toArray')->andReturnUsing(function ($request) use ($parameters) {
            $model = $parameters[0]; // The model wrapped by the resource
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
    // Reset bindings after each test to prevent interference
    app()->forgetInstance(TaxResource::class);
    app()->forgetInstance(CustomFieldValueResource::class);
});

test('toArray transforms resource with all properties and no relationships', function () {
    $request = m::mock(Request::class);

    $mockModel = m::mock(Model::class);
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

    // Mock relation methods to indicate no existence
    $mockTaxRelation = m::mock(Relation::class);
    $mockTaxRelation->shouldReceive('exists')->andReturn(false);
    $mockModel->shouldReceive('taxes')->andReturn($mockTaxRelation);

    $mockFieldsRelation = m::mock(Relation::class);
    $mockFieldsRelation->shouldReceive('exists')->andReturn(false);
    $mockModel->shouldReceive('fields')->andReturn($mockFieldsRelation);

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
        'taxes' => null, // JsonResource::when returns null if condition is false
        'fields' => null, // JsonResource::when returns null if condition is false
    ]);
});

test('toArray transforms resource with taxes relationship', function () {
    $request = m::mock(Request::class);

    // Mock Tax models (these will be wrapped by the mocked TaxResource)
    $mockTaxModel1 = m::mock(Model::class);
    $mockTaxModel1->id = 1;
    $mockTaxModel1->name = 'Tax A';
    $mockTaxModel1->amount = 5.0;

    $mockTaxModel2 = m::mock(Model::class);
    $mockTaxModel2->id = 2;
    $mockTaxModel2->name = 'Tax B';
    $mockTaxModel2->amount = 10.0;

    $mockTaxCollection = new Collection([$mockTaxModel1, $mockTaxModel2]);

    $mockModel = m::mock(Model::class);
    $mockModel->id = 10;
    $mockModel->name = 'Item With Taxes';
    $mockModel->taxes = $mockTaxCollection; // This property is accessed when relation exists

    // Mock relation methods
    $mockTaxRelation = m::mock(Relation::class);
    $mockTaxRelation->shouldReceive('exists')->andReturn(true);
    $mockModel->shouldReceive('taxes')->andReturn($mockTaxRelation);

    $mockFieldsRelation = m::mock(Relation::class);
    $mockFieldsRelation->shouldReceive('exists')->andReturn(false);
    $mockModel->shouldReceive('fields')->andReturn($mockFieldsRelation);

    $resource = new EstimateItemResource($mockModel);
    $result = $resource->toArray($request);

    // Expected output based on the mocked TaxResource::toArray behavior
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

    // Mock CustomFieldValue models
    $mockFieldModel1 = m::mock(Model::class);
    $mockFieldModel1->id = 101;
    $mockFieldModel1->name = 'Field A';
    $mockFieldModel1->value = 'Value A';

    $mockFieldModel2 = m::mock(Model::class);
    $mockFieldModel2->id = 102;
    $mockFieldModel2->name = 'Field B';
    $mockFieldModel2->value = 'Value B';

    $mockFieldCollection = new Collection([$mockFieldModel1, $mockFieldModel2]);

    $mockModel = m::mock(Model::class);
    $mockModel->id = 20;
    $mockModel->name = 'Item With Fields';
    $mockModel->fields = $mockFieldCollection; // This property is accessed when relation exists

    // Mock relation methods
    $mockTaxRelation = m::mock(Relation::class);
    $mockTaxRelation->shouldReceive('exists')->andReturn(false);
    $mockModel->shouldReceive('taxes')->andReturn($mockTaxRelation);

    $mockFieldsRelation = m::mock(Relation::class);
    $mockFieldsRelation->shouldReceive('exists')->andReturn(true);
    $mockModel->shouldReceive('fields')->andReturn($mockFieldsRelation);

    $resource = new EstimateItemResource($mockModel);
    $result = $resource->toArray($request);

    // Expected output based on the mocked CustomFieldValueResource::toArray behavior
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

    // Mock Tax model
    $mockTaxModel = m::mock(Model::class);
    $mockTaxModel->id = 1;
    $mockTaxModel->name = 'Tax Single';
    $mockTaxModel->amount = 7.5;
    $mockTaxCollection = new Collection([$mockTaxModel]);

    // Mock CustomFieldValue model
    $mockFieldModel = m::mock(Model::class);
    $mockFieldModel->id = 201;
    $mockFieldModel->name = 'Single Field';
    $mockFieldModel->value = 'Single Value';
    $mockFieldCollection = new Collection([$mockFieldModel]);

    $mockModel = m::mock(Model::class);
    $mockModel->id = 30;
    $mockModel->name = 'Item With Both';
    $mockModel->taxes = $mockTaxCollection;
    $mockModel->fields = $mockFieldCollection;

    // Mock relation methods to indicate existence for both
    $mockTaxRelation = m::mock(Relation::class);
    $mockTaxRelation->shouldReceive('exists')->andReturn(true);
    $mockModel->shouldReceive('taxes')->andReturn($mockTaxRelation);

    $mockFieldsRelation = m::mock(Relation::class);
    $mockFieldsRelation->shouldReceive('exists')->andReturn(true);
    $mockModel->shouldReceive('fields')->andReturn($mockFieldsRelation);

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

    $mockModel = m::mock(Model::class);
    $mockModel->id = 40;
    $mockModel->name = 'Item With Empty Taxes';
    $mockModel->taxes = new Collection(); // Empty collection, but relation exists

    // Mock relation methods
    $mockTaxRelation = m::mock(Relation::class);
    $mockTaxRelation->shouldReceive('exists')->andReturn(true); // Condition is true
    $mockModel->shouldReceive('taxes')->andReturn($mockTaxRelation);

    $mockFieldsRelation = m::mock(Relation::class);
    $mockFieldsRelation->shouldReceive('exists')->andReturn(false);
    $mockModel->shouldReceive('fields')->andReturn($mockFieldsRelation);

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

    $mockModel = m::mock(Model::class);
    $mockModel->id = null;
    $mockModel->name = null;
    $mockModel->description = null;
    $mockModel->discount_type = null;
    $mockModel->quantity = null;
    $mockModel->unit_name = null;
    $mockModel->discount = null;
    $mockModel->discount_val = null;
    $mockModel->price = null;
    $mockModel->tax = null;
    $mockModel->total = null;
    $mockModel->item_id = null;
    $mockModel->estimate_id = null;
    $mockModel->company_id = null;
    $mockModel->exchange_rate = null;
    $mockModel->base_discount_val = null;
    $mockModel->base_price = null;
    $mockModel->base_tax = null;
    $mockModel->base_total = null;

    // Mock relation methods to indicate no existence
    $mockTaxRelation = m::mock(Relation::class);
    $mockTaxRelation->shouldReceive('exists')->andReturn(false);
    $mockModel->shouldReceive('taxes')->andReturn($mockTaxRelation);

    $mockFieldsRelation = m::mock(Relation::class);
    $mockFieldsRelation->shouldReceive('exists')->andReturn(false);
    $mockModel->shouldReceive('fields')->andReturn($mockFieldsRelation);

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



