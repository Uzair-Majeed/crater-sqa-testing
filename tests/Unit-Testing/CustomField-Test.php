<?php
use Crater\Models\Company;
use Crater\Models\CustomField;
use Crater\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
uses(\Mockery::class);

test('setTimeAnswerAttribute correctly formats time', function () {
    $model = new CustomField();

    $model->setTimeAnswerAttribute('10:30 AM');
    expect($model->getAttributes()['time_answer'])->toBe('10:30:00');

    $model->setTimeAnswerAttribute('2:45 PM');
    expect($model->getAttributes()['time_answer'])->toBe('14:45:00');

    $model->setTimeAnswerAttribute('14:00:00');
    expect($model->getAttributes()['time_answer'])->toBe('14:00:00');

    $model->setTimeAnswerAttribute('24:00:00');
    expect($model->getAttributes()['time_answer'])->toBe('00:00:00');
});

test('setTimeAnswerAttribute does nothing if value is null or empty', function () {
    $model = new CustomField();
    $model->setAttribute('time_answer', 'initial_value');

    $model->setTimeAnswerAttribute(null);
    expect($model->getAttributes())->not->toHaveKey('time_answer');

    $model = new CustomField();
    $model->setAttribute('time_answer', 'initial_value');

    $model->setTimeAnswerAttribute('');
    expect($model->getAttributes())->not->toHaveKey('time_answer');
});

test('setOptionsAttribute correctly encodes array to json string', function () {
    $model = new CustomField();
    $options = ['option1', 'option2'];

    $model->setOptionsAttribute($options);
    expect($model->getAttributes()['options'])->toBe(json_encode($options));

    $emptyOptions = [];
    $model->setOptionsAttribute($emptyOptions);
    expect($model->getAttributes()['options'])->toBe(json_encode($emptyOptions));
});

test('getDefaultAnswerAttribute returns correct value based on type', function () {
    \Brain\Monkey\Functions\when('getCustomFieldValueKey')->assume(function ($type) {
        return match ($type) {
            'TEXT' => 'string_answer',
            'NUMBER' => 'integer_answer',
            'DATE' => 'date_answer',
            'BOOLEAN' => 'boolean_answer',
            default => 'string_answer',
        };
    });

    $model = new CustomField();
    $model->type = 'TEXT';
    $model->string_answer = 'Some text';
    $model->integer_answer = 123;
    expect($model->defaultAnswer)->toBe('Some text');

    $model->type = 'NUMBER';
    $model->integer_answer = 456;
    expect($model->defaultAnswer)->toBe(456);

    $model->type = 'DATE';
    $model->date_answer = '2023-01-01';
    expect($model->defaultAnswer)->toBe('2023-01-01');

    $model->type = 'BOOLEAN';
    $model->boolean_answer = true;
    expect($model->defaultAnswer)->toBe(true);
});

test('getInUseAttribute returns true if custom field values exist', function () {
    $model = Mockery::mock(CustomField::class)->makePartial();
    $mockHasMany = Mockery::mock(HasMany::class);
    $mockHasMany->shouldReceive('exists')->once()->andReturn(true);

    $model->shouldReceive('customFieldValues')->once()->andReturn($mockHasMany);

    expect($model->inUse)->toBeTrue();
});

test('getInUseAttribute returns false if no custom field values exist', function () {
    $model = Mockery::mock(CustomField::class)->makePartial();
    $mockHasMany = Mockery::mock(HasMany::class);
    $mockHasMany->shouldReceive('exists')->once()->andReturn(false);

    $model->shouldReceive('customFieldValues')->once()->andReturn($mockHasMany);

    expect($model->inUse)->toBeFalse();
});

test('company relationship returns a BelongsTo relation', function () {
    $model = new CustomField();
    $relation = $model->company();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
    expect($relation->getForeignKeyName())->toBe('company_id');
});

test('customFieldValues relationship returns a HasMany relation', function () {
    $model = new CustomField();
    $relation = $model->customFieldValues();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(CustomFieldValue::class);
    expect($relation->getForeignKeyName())->toBe('custom_field_id');
});

test('scopeWhereCompany applies correct company_id filter', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $companyId = 'test-company-uuid';

    request()->shouldReceive('header')->with('company')->once()->andReturn($companyId);

    $mockQuery->shouldReceive('where')
        ->once()
        ->with('custom_fields.company_id', $companyId)
        ->andReturnSelf();

    $model = new CustomField();
    $result = $model->scopeWhereCompany($mockQuery);

    expect($result)->toBe($mockQuery);
});

test('scopeWhereSearch applies correct label and name search filters', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $search = 'test_search';

    $mockQuery->shouldReceive('where')
        ->once()
        ->andReturnUsing(function ($callback) use ($search, $mockQuery) {
            $internalQuery = Mockery::mock(Builder::class);
            $internalQuery->shouldReceive('where')
                ->once()
                ->with('label', 'LIKE', '%' . $search . '%')
                ->andReturnSelf();
            $internalQuery->shouldReceive('orWhere')
                ->once()
                ->with('name', 'LIKE', '%' . $search . '%')
                ->andReturnSelf();

            $callback($internalQuery);
            return $mockQuery;
        });

    $model = new CustomField();
    $result = $model->scopeWhereSearch($mockQuery, $search);

    expect($result)->toBe($mockQuery);
});

test('scopePaginateData returns all records when limit is "all"', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $expectedCollection = collect(['item1', 'item2']);

    $mockQuery->shouldReceive('get')->once()->andReturn($expectedCollection);
    $mockQuery->shouldNotReceive('paginate');

    $model = new CustomField();
    $result = $model->scopePaginateData($mockQuery, 'all');

    expect($result)->toBe($expectedCollection);
});

test('scopePaginateData paginates records when limit is a number', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $limit = 10;
    $expectedPaginator = Mockery::mock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

    $mockQuery->shouldReceive('paginate')->once()->with($limit)->andReturn($expectedPaginator);
    $mockQuery->shouldNotReceive('get');

    $model = new CustomField();
    $result = $model->scopePaginateData($mockQuery, $limit);

    expect($result)->toBe($expectedPaginator);
});

test('scopeApplyFilters applies type filter when present', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $type = 'MODEL_TYPE_A';
    $filters = ['type' => $type];

    $mockQuery->shouldReceive('whereType')->once()->with($type)->andReturnSelf();
    $mockQuery->shouldNotReceive('whereSearch');

    $model = new CustomField();
    $result = $model->scopeApplyFilters($mockQuery, $filters);

    expect($result)->toBe($mockQuery);
});

test('scopeApplyFilters applies search filter when present', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $search = 'test_search';
    $filters = ['search' => $search];

    $mockQuery->shouldReceive('whereSearch')->once()->with($search)->andReturnSelf();
    $mockQuery->shouldNotReceive('whereType');

    $model = new CustomField();
    $result = $model->scopeApplyFilters($mockQuery, $filters);

    expect($result)->toBe($mockQuery);
});

test('scopeApplyFilters applies both type and search filters when both are present', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $type = 'MODEL_TYPE_B';
    $search = 'another_search';
    $filters = ['type' => $type, 'search' => $search];

    $mockQuery->shouldReceive('whereType')->once()->with($type)->andReturnSelf();
    $mockQuery->shouldReceive('whereSearch')->once()->with($search)->andReturnSelf();

    $model = new CustomField();
    $result = $model->scopeApplyFilters($mockQuery, $filters);

    expect($result)->toBe($mockQuery);
});

test('scopeApplyFilters applies no filters when none are present', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $filters = ['other_filter' => 'value'];

    $mockQuery->shouldNotReceive('whereType');
    $mockQuery->shouldNotReceive('whereSearch');

    $model = new CustomField();
    $result = $model->scopeApplyFilters($mockQuery, $filters);

    expect($result)->toBe($mockQuery);
});

test('scopeWhereType applies correct model_type filter', function () {
    $mockQuery = Mockery::mock(Builder::class);
    $type = 'invoice_type';

    $mockQuery->shouldReceive('where')
        ->once()
        ->with('custom_fields.model_type', $type)
        ->andReturnSelf();

    $model = new CustomField();
    $result = $model->scopeWhereType($mockQuery, $type);

    expect($result)->toBe($mockQuery);
});

test('createCustomField creates a new custom field with correct data', function () {
    \Brain\Monkey\Functions\when('getCustomFieldValueKey')->assume(fn($type) => $type === 'TEXT' ? 'string_answer' : 'integer_answer');
    \Brain\Monkey\Functions\when('clean_slug')->assume(fn($model_type, $name) => 'clean-slug-' . $model_type . '-' . $name);

    $mockRequest = Mockery::mock(Request::class);
    $validatedData = [
        'label' => 'Test Label',
        'name' => 'test_name',
        'type' => 'TEXT',
        'model_type' => 'Invoice',
    ];
    $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
    $mockRequest->shouldReceive('get')->with('type')->andReturn('TEXT');
    $mockRequest->shouldReceive('type')->andReturn('TEXT');
    $mockRequest->shouldReceive('default_answer')->andReturn('Default Text Value');
    $mockRequest->shouldReceive('header')->with('company')->andReturn('company-uuid-123');
    $mockRequest->shouldReceive('model_type')->andReturn('Invoice');
    $mockRequest->shouldReceive('name')->andReturn('test_name');

    $createdModel = Mockery::mock(CustomField::class);
    Mockery::mock('overload:' . CustomField::class)
        ->shouldReceive('create')
        ->once()
        ->andReturnUsing(function ($data) use ($validatedData, $createdModel) {
            expect($data)->toMatchArray([
                'label' => 'Test Label',
                'name' => 'test_name',
                'type' => 'TEXT',
                'model_type' => 'Invoice',
                'string_answer' => 'Default Text Value',
                'company_id' => 'company-uuid-123',
                'slug' => 'clean-slug-Invoice-test_name',
            ]);
            return $createdModel;
        });

    $result = CustomField::createCustomField($mockRequest);

    expect($result)->toBe($createdModel);
});

test('updateCustomField updates existing custom field with correct data', function () {
    \Brain\Monkey\Functions\when('getCustomFieldValueKey')->assume(fn($type) => $type === 'TEXT' ? 'string_answer' : 'integer_answer');

    $mockRequest = Mockery::mock(Request::class);
    $validatedData = [
        'label' => 'Updated Label',
        'name' => 'updated_name',
        'type' => 'TEXT',
        'model_type' => 'Invoice',
    ];
    $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
    $mockRequest->shouldReceive('get')->with('type')->andReturn('TEXT');
    $mockRequest->shouldReceive('type')->andReturn('TEXT');
    $mockRequest->shouldReceive('default_answer')->andReturn('Updated Default Text Value');

    $customField = Mockery::mock(CustomField::class)->makePartial();
    $customField->shouldReceive('update')->once()->andReturnUsing(function ($data) use ($validatedData, $customField) {
        expect($data)->toMatchArray([
            'label' => 'Updated Label',
            'name' => 'updated_name',
            'type' => 'TEXT',
            'model_type' => 'Invoice',
            'string_answer' => 'Updated Default Text Value',
        ]);
        return true;
    });

    $result = $customField->updateCustomField($mockRequest);

    expect($result)->toBe($customField);
});
