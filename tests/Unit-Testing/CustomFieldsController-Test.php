<?php

use Tests\TestCase;
use Crater\Http\Controllers\V1\Admin\CustomField\CustomFieldsController;
use Crater\Http\Requests\CustomFieldRequest;
use Crater\Http\Resources\CustomFieldResource;
use Crater\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

// Use a setup for common mocks
beforeEach(function () {
    // Mock the Controller's authorize method for all tests
    // This allows us to test the controller's logic without needing a full policy setup
    // We bind a partial mock to the container so that it's resolved when `make` is called.
    $this->app->singleton(CustomFieldsController::class, function ($app) {
        $mock = Mockery::mock(CustomFieldsController::class);
        $mock->makePartial(); // Allows calling original methods that are not mocked
        $mock->shouldAllowMockingProtectedMethods(); // For 'authorize' which comes from a trait
        $mock->shouldReceive('authorize')->andReturn(true); // Always authorize
        return $mock;
    });
});


test('index displays a listing of custom fields with default limit', function () {
    /** @var CustomFieldsController $controller */
    $controller = $this->app->make(CustomFieldsController::class);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('has')->with('limit')->andReturn(false);
    $mockRequest->shouldReceive('all')->andReturn([]);

    $mockQueryBuilder = Mockery::mock(Builder::class);
    $mockQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('latest')->andReturnSelf();

    // Mock the static methods of CustomField model
    Mockery::mock('alias:' . CustomField::class)
        ->shouldReceive('applyFilters')
        ->with([])
        ->andReturn($mockQueryBuilder);

    $mockCustomFieldItems = Collection::make([
        (object)['id' => 1, 'name' => 'Field 1', 'type' => 'TEXT'],
        (object)['id' => 2, 'name' => 'Field 2', 'type' => 'NUMBER'],
    ]);

    $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockPaginator->shouldReceive('items')->andReturn($mockCustomFieldItems);
    $mockPaginator->shouldReceive('resource')->andReturn($mockCustomFieldItems); // Used by AnonymousResourceCollection internally for its 'resource' property
    $mockPaginator->shouldReceive('toArray')->andReturn(['data' => $mockCustomFieldItems->toArray(), 'meta' => []]);

    $mockQueryBuilder->shouldReceive('paginateData')
        ->with(5) // Default limit
        ->andReturn($mockPaginator);

    // Mock CustomFieldResource::collection static method
    Mockery::mock('alias:' . CustomFieldResource::class)
        ->shouldReceive('collection')
        ->with($mockPaginator)
        ->andReturnUsing(function ($paginator) use ($mockCustomFieldItems) {
            // Mimic the behavior of Resource::collection for a paginator
            return AnonymousResourceCollection::make($mockCustomFieldItems->map(fn($item) => new CustomFieldResource($item)));
        });

    $response = $controller->index($mockRequest);

    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->resource->count())->toBe(2);
    expect($response->resource->first()->id)->toBe(1);
    expect($response->resource->first()->name)->toBe('Field 1');
});

test('index displays a listing of custom fields with custom limit', function () {
    /** @var CustomFieldsController $controller */
    $controller = $this->app->make(CustomFieldsController::class);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('has')->with('limit')->andReturn(true);
    $mockRequest->limit = 10; // Property access for limit
    $mockRequest->shouldReceive('all')->andReturn(['limit' => 10, 'search' => 'test_search']);

    $mockQueryBuilder = Mockery::mock(Builder::class);
    $mockQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('latest')->andReturnSelf();

    Mockery::mock('alias:' . CustomField::class)
        ->shouldReceive('applyFilters')
        ->with(['limit' => 10, 'search' => 'test_search'])
        ->andReturn($mockQueryBuilder);

    $mockCustomFieldItems = Collection::make([
        (object)['id' => 1, 'name' => 'Field 1', 'type' => 'TEXT'],
        (object)['id' => 2, 'name' => 'Field 2', 'type' => 'NUMBER'],
    ]);

    $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockPaginator->shouldReceive('items')->andReturn($mockCustomFieldItems);
    $mockPaginator->shouldReceive('resource')->andReturn($mockCustomFieldItems);
    $mockPaginator->shouldReceive('toArray')->andReturn(['data' => $mockCustomFieldItems->toArray(), 'meta' => []]);

    $mockQueryBuilder->shouldReceive('paginateData')
        ->with(10) // Custom limit
        ->andReturn($mockPaginator);

    Mockery::mock('alias:' . CustomFieldResource::class)
        ->shouldReceive('collection')
        ->with($mockPaginator)
        ->andReturnUsing(function ($paginator) use ($mockCustomFieldItems) {
            return AnonymousResourceCollection::make($mockCustomFieldItems->map(fn($item) => new CustomFieldResource($item)));
        });

    $response = $controller->index($mockRequest);

    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response->resource->count())->toBe(2);
});

test('store creates a new custom field', function () {
    /** @var CustomFieldsController $controller */
    $controller = $this->app->make(CustomFieldsController::class);

    $mockRequest = Mockery::mock(CustomFieldRequest::class);
    $mockRequest->shouldReceive('all')->andReturn([
        'name' => 'New Field',
        'type' => 'TEXT',
        'field_type' => 'INPUT',
        'resource_type' => 'CLIENTS',
    ]);

    $mockCustomField = Mockery::mock(CustomField::class);
    $mockCustomField->id = 1;
    $mockCustomField->name = 'New Field';
    $mockCustomField->type = 'TEXT';
    $mockCustomField->field_type = 'INPUT';
    $mockCustomField->resource_type = 'CLIENTS';

    // Mock the static method createCustomField on the CustomField model
    Mockery::mock('alias:' . CustomField::class)
        ->shouldReceive('createCustomField')
        ->with($mockRequest)
        ->once()
        ->andReturn($mockCustomField);

    $response = $controller->store($mockRequest);

    expect($response)->toBeInstanceOf(CustomFieldResource::class);
    expect($response->resource->id)->toBe(1);
    expect($response->resource->name)->toBe('New Field');
    expect($response->resource->type)->toBe('TEXT');
});

test('show displays the specified custom field', function () {
    /** @var CustomFieldsController $controller */
    $controller = $this->app->make(CustomFieldsController::class);

    $mockCustomField = Mockery::mock(CustomField::class);
    $mockCustomField->id = 1;
    $mockCustomField->name = 'Existing Field';
    $mockCustomField->type = 'TEXT';
    $mockCustomField->field_type = 'TEXTAREA';
    $mockCustomField->resource_type = 'INVOICES';

    $response = $controller->show($mockCustomField);

    expect($response)->toBeInstanceOf(CustomFieldResource::class);
    expect($response->resource->id)->toBe(1);
    expect($response->resource->name)->toBe('Existing Field');
    expect($response->resource->type)->toBe('TEXT');
});

test('update updates the specified custom field', function () {
    /** @var CustomFieldsController $controller */
    $controller = $this->app->make(CustomFieldsController::class);

    $mockRequest = Mockery::mock(CustomFieldRequest::class);
    $mockRequest->shouldReceive('all')->andReturn(['name' => 'Updated Field Name', 'type' => 'NUMBER']);

    $mockCustomField = Mockery::mock(CustomField::class);
    $mockCustomField->id = 1;
    $mockCustomField->name = 'Original Name'; // Simulate initial state
    $mockCustomField->type = 'TEXT';
    $mockCustomField->field_type = 'INPUT';
    $mockCustomField->resource_type = 'CLIENTS';

    // Mock the instance method updateCustomField
    $mockCustomField->shouldReceive('updateCustomField')
        ->with($mockRequest)
        ->once()
        ->andReturnUsing(function ($request) use ($mockCustomField) {
            // Simulate the update happening within the model method
            $mockCustomField->name = $request->all()['name'];
            $mockCustomField->type = $request->all()['type'];
            return $mockCustomField;
        });

    $response = $controller->update($mockRequest, $mockCustomField);

    expect($response)->toBeInstanceOf(CustomFieldResource::class);
    expect($response->resource->id)->toBe(1);
    expect($response->resource->name)->toBe('Updated Field Name');
    expect($response->resource->type)->toBe('NUMBER');
});

test('destroy deletes the specified custom field without values', function () {
    /** @var CustomFieldsController $controller */
    $controller = $this->app->make(CustomFieldsController::class);

    $mockCustomFieldValuesBuilder = Mockery::mock(Builder::class);
    $mockCustomFieldValuesBuilder->shouldReceive('exists')->once()->andReturn(false);

    $mockCustomField = Mockery::mock(CustomField::class);
    $mockCustomField->shouldReceive('customFieldValues')->once()->andReturn($mockCustomFieldValuesBuilder);
    $mockCustomField->shouldReceive('forceDelete')->once()->andReturn(true); // Eloquent's forceDelete returns bool

    $response = $controller->destroy($mockCustomField);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->original)->toEqual(['success' => true]);
});

test('destroy deletes the specified custom field with values', function () {
    /** @var CustomFieldsController $controller */
    $controller = $this->app->make(CustomFieldsController::class);

    $mockCustomFieldValuesBuilder = Mockery::mock(Builder::class);
    $mockCustomFieldValuesBuilder->shouldReceive('exists')->once()->andReturn(true);
    $mockCustomFieldValuesBuilder->shouldReceive('delete')->once()->andReturn(1); // Simulate 1 value deleted

    $mockCustomField = Mockery::mock(CustomField::class);
    $mockCustomField->shouldReceive('customFieldValues')->once()->andReturn($mockCustomFieldValuesBuilder);
    $mockCustomField->shouldReceive('forceDelete')->once()->andReturn(true);

    $response = $controller->destroy($mockCustomField);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->original)->toEqual(['success' => true]);
});

 

afterEach(function () {
    Mockery::close();
});
