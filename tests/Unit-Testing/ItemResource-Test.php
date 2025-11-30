<?php

uses(\Mockery::class);
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Crater\Http\Resources\ItemResource;
use Crater\Http\Resources\UnitResource;
use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\TaxResource;
use Crater\Http\Resources\CurrencyResource;

beforeEach(function () {
    // Ensure Mockery is torn down after each test
    Mockery::close();

    // Mock the dependent resource classes using "alias:" to mock static methods
    // and to prevent actual class loading during unit tests.
    Mockery::mock('alias:' . UnitResource::class);
    Mockery::mock('alias:' . CompanyResource::class);
    Mockery::mock('alias:' . TaxResource::class);
    Mockery::mock('alias:' . CurrencyResource::class);
});

test('it transforms an item with all attributes and relationships correctly', function () {
    $now = Carbon::now();

    // 1. Mock the underlying Item model instance
    $itemModel = Mockery::mock();
    $itemModel->id = 1;
    $itemModel->name = 'Test Item';
    $itemModel->description = 'A detailed description';
    $itemModel->price = 100.50;
    $itemModel->unit_id = 10;
    $itemModel->company_id = 20;
    $itemModel->creator_id = 30;
    $itemModel->currency_id = 40;
    $itemModel->created_at = $now;
    $itemModel->updated_at = $now;
    $itemModel->tax_per_item = true;
    $itemModel->formattedCreatedAt = $now->toFormattedDateString();

    // 2. Mock related models and collections for relationships
    $unitModel = Mockery::mock('App\Models\Unit');
    $companyModel = Mockery::mock('App\Models\Company');
    $currencyModel = Mockery::mock('App\Models\Currency');
    $taxModel1 = Mockery::mock('App\Models\Tax');
    $taxModel2 = Mockery::mock('App\Models\Tax');
    $taxesCollection = new Collection([$taxModel1, $taxModel2]);

    // 3. Mock relationship methods (e.g., $this->unit()) to return relation objects that respond to `exists()`
    //    Also set the loaded relationship properties (e.g., $this->unit) on the itemModel.
    $unitRelation = Mockery::mock(BelongsTo::class);
    $unitRelation->shouldReceive('exists')->andReturn(true)->once();
    $itemModel->shouldReceive('unit')->andReturn($unitRelation)->once();
    $itemModel->unit = $unitModel; // Set the loaded relationship property for the closure

    $companyRelation = Mockery::mock(BelongsTo::class);
    $companyRelation->shouldReceive('exists')->andReturn(true)->once();
    $itemModel->shouldReceive('company')->andReturn($companyRelation)->once();
    $itemModel->company = $companyModel;

    $currencyRelation = Mockery::mock(BelongsTo::class);
    $currencyRelation->shouldReceive('exists')->andReturn(true)->once();
    $itemModel->shouldReceive('currency')->andReturn($currencyRelation)->once();
    $itemModel->currency = $currencyModel;

    $taxesRelation = Mockery::mock(HasMany::class);
    $taxesRelation->shouldReceive('exists')->andReturn(true)->once();
    $itemModel->shouldReceive('taxes')->andReturn($taxesRelation)->once();
    $itemModel->taxes = $taxesCollection;

    // 4. Mock the resource wrappers to confirm they are instantiated with the correct models
    //    And to provide dummy output for their toArray() method.
    $unitResourceOutput = ['id' => 10, 'name' => 'Unit 1'];
    UnitResource::shouldReceive('__construct')
        ->with(Mockery::on(fn ($arg) => $arg === $unitModel))
        ->andReturnSelf()
        ->once();
    UnitResource::shouldReceive('toArray')->andReturn($unitResourceOutput)->once();

    $companyResourceOutput = ['id' => 20, 'name' => 'Company A'];
    CompanyResource::shouldReceive('__construct')
        ->with(Mockery::on(fn ($arg) => $arg === $companyModel))
        ->andReturnSelf()
        ->once();
    CompanyResource::shouldReceive('toArray')->andReturn($companyResourceOutput)->once();

    $currencyResourceOutput = ['id' => 40, 'code' => 'USD'];
    CurrencyResource::shouldReceive('__construct')
        ->with(Mockery::on(fn ($arg) => $arg === $currencyModel))
        ->andReturnSelf()
        ->once();
    CurrencyResource::shouldReceive('toArray')->andReturn($currencyResourceOutput)->once();

    // For TaxResource::collection, we mock its static method.
    // It returns an AnonymousResourceCollection whose toArray() method is implicitly called by JsonResource.
    $taxesResourceOutput = [['id' => 1, 'name' => 'Tax 1'], ['id' => 2, 'name' => 'Tax 2']];
    TaxResource::shouldReceive('collection')
        ->with(Mockery::on(fn ($arg) => $arg === $taxesCollection))
        ->andReturn(
            Mockery::mock(AnonymousResourceCollection::class)
                ->shouldReceive('toArray')
                ->andReturn($taxesResourceOutput)
                ->getMock()
        )
        ->once();

    // 5. Create the ItemResource instance with the mocked model
    $resource = new ItemResource($itemModel);

    // 6. Call toArray with a mocked request
    $result = $resource->toArray(Mockery::mock(Request::class));

    // 7. Assert the output structure and values
    expect($result)->toEqual([
        'id' => 1,
        'name' => 'Test Item',
        'description' => 'A detailed description',
        'price' => 100.50,
        'unit_id' => 10,
        'company_id' => 20,
        'creator_id' => 30,
        'currency_id' => 40,
        'created_at' => $now,
        'updated_at' => $now,
        'tax_per_item' => true,
        'formatted_created_at' => $now->toFormattedDateString(),
        'unit' => $unitResourceOutput,
        'company' => $companyResourceOutput,
        'taxes' => $taxesResourceOutput,
        'currency' => $currencyResourceOutput,
    ]);
});

test('it transforms an item with no relationships', function () {
    $now = Carbon::now();

    // Mock the underlying Item model with scalar properties
    $itemModel = Mockery::mock();
    $itemModel->id = 1;
    $itemModel->name = 'Test Item';
    $itemModel->description = 'A detailed description';
    $itemModel->price = 100.50;
    $itemModel->unit_id = null;
    $itemModel->company_id = null;
    $itemModel->creator_id = 30;
    $itemModel->currency_id = null;
    $itemModel->created_at = $now;
    $itemModel->updated_at = $now;
    $itemModel->tax_per_item = false;
    $itemModel->formattedCreatedAt = $now->toFormattedDateString();

    // Mock relationship methods to return relation objects that respond to `exists()` with false
    $unitRelation = Mockery::mock(BelongsTo::class);
    $unitRelation->shouldReceive('exists')->andReturn(false)->once();
    $itemModel->shouldReceive('unit')->andReturn($unitRelation)->once();
    $itemModel->unit = null; // Ensure loaded relationship property is null

    $companyRelation = Mockery::mock(BelongsTo::class);
    $companyRelation->shouldReceive('exists')->andReturn(false)->once();
    $itemModel->shouldReceive('company')->andReturn($companyRelation)->once();
    $itemModel->company = null;

    $currencyRelation = Mockery::mock(BelongsTo::class);
    $currencyRelation->shouldReceive('exists')->andReturn(false)->once();
    $itemModel->shouldReceive('currency')->andReturn($currencyRelation)->once();
    $itemModel->currency = null;

    $taxesRelation = Mockery::mock(HasMany::class);
    $taxesRelation->shouldReceive('exists')->andReturn(false)->once();
    $itemModel->shouldReceive('taxes')->andReturn($taxesRelation)->once();
    $itemModel->taxes = new Collection(); // Empty collection

    // Ensure resource constructors/static methods are NOT called when relationships don't exist
    UnitResource::shouldNotReceive('__construct');
    CompanyResource::shouldNotReceive('__construct');
    CurrencyResource::shouldNotReceive('__construct');
    TaxResource::shouldNotReceive('collection');

    $resource = new ItemResource($itemModel);
    $result = $resource->toArray(Mockery::mock(Request::class));

    // Assert that relationship keys are absent in the output array
    expect($result)->toEqual([
        'id' => 1,
        'name' => 'Test Item',
        'description' => 'A detailed description',
        'price' => 100.50,
        'unit_id' => null,
        'company_id' => null,
        'creator_id' => 30,
        'currency_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'tax_per_item' => false,
        'formatted_created_at' => $now->toFormattedDateString(),
    ])->not->toHaveKey('unit')
      ->not->toHaveKey('company')
      ->not->toHaveKey('taxes')
      ->not->toHaveKey('currency');
});

test('it handles mixed relationship existence', function () {
    $now = Carbon::now();

    $itemModel = Mockery::mock();
    $itemModel->id = 2;
    $itemModel->name = 'Mixed Item';
    $itemModel->description = 'Some description';
    $itemModel->price = 50.00;
    $itemModel->unit_id = 10;
    $itemModel->company_id = null;
    $itemModel->creator_id = 30;
    $itemModel->currency_id = 40;
    $itemModel->created_at = $now;
    $itemModel->updated_at = $now;
    $itemModel->tax_per_item = true;
    $itemModel->formattedCreatedAt = $now->toFormattedDateString();

    // Mocks for relationships that will exist
    $unitModel = Mockery::mock('App\Models\Unit');
    $currencyModel = Mockery::mock('App\Models\Currency');

    // Unit relationship exists
    $unitRelation = Mockery::mock(BelongsTo::class);
    $unitRelation->shouldReceive('exists')->andReturn(true)->once();
    $itemModel->shouldReceive('unit')->andReturn($unitRelation)->once();
    $itemModel->unit = $unitModel;
    $unitResourceOutput = ['id' => 10, 'name' => 'Unit 1'];
    UnitResource::shouldReceive('__construct')
        ->with($unitModel)->andReturnSelf()->once();
    UnitResource::shouldReceive('toArray')->andReturn($unitResourceOutput)->once();

    // Company relationship does not exist
    $companyRelation = Mockery::mock(BelongsTo::class);
    $companyRelation->shouldReceive('exists')->andReturn(false)->once();
    $itemModel->shouldReceive('company')->andReturn($companyRelation)->once();
    $itemModel->company = null;
    CompanyResource::shouldNotReceive('__construct');

    // Taxes relationship does not exist
    $taxesRelation = Mockery::mock(HasMany::class);
    $taxesRelation->shouldReceive('exists')->andReturn(false)->once();
    $itemModel->shouldReceive('taxes')->andReturn($taxesRelation)->once();
    $itemModel->taxes = new Collection();
    TaxResource::shouldNotReceive('collection');

    // Currency relationship exists
    $currencyRelation = Mockery::mock(BelongsTo::class);
    $currencyRelation->shouldReceive('exists')->andReturn(true)->once();
    $itemModel->shouldReceive('currency')->andReturn($currencyRelation)->once();
    $itemModel->currency = $currencyModel;
    $currencyResourceOutput = ['id' => 40, 'code' => 'EUR'];
    CurrencyResource::shouldReceive('__construct')
        ->with($currencyModel)->andReturnSelf()->once();
    CurrencyResource::shouldReceive('toArray')->andReturn($currencyResourceOutput)->once();

    $resource = new ItemResource($itemModel);
    $result = $resource->toArray(Mockery::mock(Request::class));

    // Assert that only existing relationship keys are present
    expect($result)->toEqual([
        'id' => 2,
        'name' => 'Mixed Item',
        'description' => 'Some description',
        'price' => 50.00,
        'unit_id' => 10,
        'company_id' => null,
        'creator_id' => 30,
        'currency_id' => 40,
        'created_at' => $now,
        'updated_at' => $now,
        'tax_per_item' => true,
        'formatted_created_at' => $now->toFormattedDateString(),
        'unit' => $unitResourceOutput,
        'currency' => $currencyResourceOutput,
    ])->not->toHaveKey('company')
      ->not->toHaveKey('taxes');
});

test('it handles null scalar properties gracefully', function () {
    $itemModel = Mockery::mock();
    $itemModel->id = null;
    $itemModel->name = null;
    $itemModel->description = null;
    $itemModel->price = null;
    $itemModel->unit_id = null;
    $itemModel->company_id = null;
    $itemModel->creator_id = null;
    $itemModel->currency_id = null;
    $itemModel->created_at = null;
    $itemModel->updated_at = null;
    $itemModel->tax_per_item = null;
    $itemModel->formattedCreatedAt = null;

    // All relationships do not exist
    $unitRelation = Mockery::mock(BelongsTo::class);
    $unitRelation->shouldReceive('exists')->andReturn(false)->once();
    $itemModel->shouldReceive('unit')->andReturn($unitRelation)->once();
    $itemModel->unit = null;

    $companyRelation = Mockery::mock(BelongsTo::class);
    $companyRelation->shouldReceive('exists')->andReturn(false)->once();
    $itemModel->shouldReceive('company')->andReturn($companyRelation)->once();
    $itemModel->company = null;

    $currencyRelation = Mockery::mock(BelongsTo::class);
    $currencyRelation->shouldReceive('exists')->andReturn(false)->once();
    $itemModel->shouldReceive('currency')->andReturn($currencyRelation)->once();
    $itemModel->currency = null;

    $taxesRelation = Mockery::mock(HasMany::class);
    $taxesRelation->shouldReceive('exists')->andReturn(false)->once();
    $itemModel->shouldReceive('taxes')->andReturn($taxesRelation)->once();
    $itemModel->taxes = new Collection();

    // Ensure resource constructors/static methods are NOT called
    UnitResource::shouldNotReceive('__construct');
    CompanyResource::shouldNotReceive('__construct');
    CurrencyResource::shouldNotReceive('__construct');
    TaxResource::shouldNotReceive('collection');

    $resource = new ItemResource($itemModel);
    $result = $resource->toArray(Mockery::mock(Request::class));

    expect($result)->toEqual([
        'id' => null,
        'name' => null,
        'description' => null,
        'price' => null,
        'unit_id' => null,
        'company_id' => null,
        'creator_id' => null,
        'currency_id' => null,
        'created_at' => null,
        'updated_at' => null,
        'tax_per_item' => null,
        'formatted_created_at' => null,
    ])->not->toHaveKey('unit')
      ->not->toHaveKey('company')
      ->not->toHaveKey('taxes')
      ->not->toHaveKey('currency');
});
