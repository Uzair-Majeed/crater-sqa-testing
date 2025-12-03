```php
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
use Mockery;

// Use setup for common mocks
beforeEach(function () {
    // Mock the Controller's authorize method for all tests
    $this->app->singleton(CustomFieldsController::class, function ($app) {
        $mock = Mockery::mock(CustomFieldsController::class)->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('authorize')->andReturn(true);
        return $mock;
    });
});

test('index displays a listing of custom fields with default limit', function () {
    /** @var CustomFieldsController $controller */
    $controller = $this->app->make(CustomFieldsController::class);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('has')->with('limit')->andReturn(false);
    $mockRequest->shouldReceive('all')->andReturn([]);

    // Use instance mock for static method applyFilters
    $mockQueryBuilder = Mockery::mock(Builder::class);
    $mockQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('latest')->andReturnSelf();

    // Use instance mock for CustomField and swap into container
    $customFieldMock = Mockery::mock('overload:' . CustomField::class);
    $customFieldMock->shouldReceive('applyFilters')->with([])->andReturn($mockQueryBuilder);

    $mockCustomFieldItems = Collection::make([
        (object)['id' => 1, 'name' => 'Field 1', 'type' => 'TEXT'],
        (object)['id' => 2, 'name' => 'Field 2', 'type' => 'NUMBER'],
    ]);

    // Mock the paginator returned by paginateData
    $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockPaginator->shouldReceive('items')->andReturn($mockCustomFieldItems);
    $mockPaginator->shouldReceive('toArray')->andReturn(['data' => $mockCustomFieldItems->toArray(), 'meta' => []]);
    $mockPaginator->resource = $mockCustomFieldItems; // Used by AnonymousResourceCollection internally

    $mockQueryBuilder->shouldReceive('paginateData')
        ->with(5)
        ->andReturn($mockPaginator);

    // Mock CustomFieldResource::collection using overload
    $customFieldResourceMock = Mockery::mock('overload:' . CustomFieldResource::class);
    CustomFieldResource::shouldReceive('collection')
        ->with($mockPaginator)
        ->andReturn(
            AnonymousResourceCollection::make(
                $mockCustomFieldItems->map(fn($item) => new CustomFieldResource($item))
            )
        );

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
    $mockRequest->limit = 10;
    $mockRequest->shouldReceive('all')->andReturn(['limit' => 10, 'search' => 'test_search']);

    $mockQueryBuilder = Mockery::mock(Builder::class);
    $mockQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('latest')->andReturnSelf();

    $customFieldMock = Mockery::mock('overload:' . CustomField::class);
    $customFieldMock->shouldReceive('applyFilters')
        ->with(['limit' => 10, 'search' => 'test_search'])
        ->andReturn($mockQueryBuilder);

    $mockCustomFieldItems = Collection::make([
        (object)['id' => 1, 'name' => 'Field 1', 'type' => 'TEXT'],
        (object)['id' => 2, 'name' => 'Field 2', 'type' => 'NUMBER'],
    ]);

    $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockPaginator->shouldReceive('items')->andReturn($mockCustomFieldItems);
    $mockPaginator->shouldReceive('toArray')->andReturn(['data' => $mockCustomFieldItems->toArray(), 'meta' => []]);
    $mockPaginator->resource = $mockCustomFieldItems;

    $mockQueryBuilder->shouldReceive('paginateData')
        ->with(10)
        ->andReturn($mockPaginator);

    $customFieldResourceMock = Mockery::mock('overload:' . CustomFieldResource::class);
    CustomFieldResource::shouldReceive('collection')
        ->with($mockPaginator)
        ->andReturn(
            AnonymousResourceCollection::make(
                $mockCustomFieldItems->map(fn($item) => new CustomFieldResource($item))
            )
        );

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

    // Use a real (not mocked) Eloquent model for resource, set attributes directly
    $resolvedCustomField = new CustomField();
    $resolvedCustomField->id = 1;
    $resolvedCustomField->name = 'New Field';
    $resolvedCustomField->type = 'TEXT';
    $resolvedCustomField->field_type = 'INPUT';
    $resolvedCustomField->resource_type = 'CLIENTS';

    // Overload model and return real instance
    $customFieldMock = Mockery::mock('overload:' . CustomField::class);
    $customFieldMock->shouldReceive('createCustomField')
        ->with($mockRequest)
        ->once()
        ->andReturn($resolvedCustomField);

    $response = $controller->store($mockRequest);

    expect($response)->toBeInstanceOf(CustomFieldResource::class);
    expect($response->resource->id)->toBe(1);
    expect($response->resource->name)->toBe('New Field');
    expect($response->resource->type)->toBe('TEXT');
});

test('show displays the specified custom field', function () {
    /** @var CustomFieldsController $controller */
    $controller = $this->app->make(CustomFieldsController::class);

    // Use a real Eloquent model instance instead of a mock to avoid setAttribute exception
    $customFieldInstance = new CustomField();
    $customFieldInstance->id = 1;
    $customFieldInstance->name = 'Existing Field';
    $customFieldInstance->type = 'TEXT';
    $customFieldInstance->field_type = 'TEXTAREA';
    $customFieldInstance->resource_type = 'INVOICES';

    $response = $controller->show($customFieldInstance);

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

    // Use a real Eloquent model instance - but mock only updateCustomField method
    $customFieldInstance = new CustomField();
    $customFieldInstance->id = 1;
    $customFieldInstance->name = 'Original Name';
    $customFieldInstance->type = 'TEXT';
    $customFieldInstance->field_type = 'INPUT';
    $customFieldInstance->resource_type = 'CLIENTS';

    $mockCustomField = Mockery::mock($customFieldInstance)->makePartial();
    $mockCustomField->shouldReceive('updateCustomField')
        ->with($mockRequest)
        ->once()
        ->andReturnUsing(function ($request) use ($mockCustomField) {
            $attrs = $request->all();
            $mockCustomField->name = $attrs['name'];
            $mockCustomField->type = $attrs['type'];
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

    // Use partial mock for Eloquent model
    $customFieldInstance = new CustomField();
    $mockCustomField = Mockery::mock($customFieldInstance)->makePartial();
    $mockCustomField->shouldReceive('customFieldValues')->once()->andReturn($mockCustomFieldValuesBuilder);
    $mockCustomField->shouldReceive('forceDelete')->once()->andReturn(true);

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
    $mockCustomFieldValuesBuilder->shouldReceive('delete')->once()->andReturn(1);

    // Use partial mock for Eloquent model
    $customFieldInstance = new CustomField();
    $mockCustomField = Mockery::mock($customFieldInstance)->makePartial();

    // Because controller may call customFieldValues twice (exists + delete), allow twice
    $mockCustomField->shouldReceive('customFieldValues')
        ->twice()
        ->andReturn($mockCustomFieldValuesBuilder);
    $mockCustomField->shouldReceive('forceDelete')->once()->andReturn(true);

    $response = $controller->destroy($mockCustomField);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->original)->toEqual(['success' => true]);
});

afterEach(function () {
    Mockery::close();
});
```